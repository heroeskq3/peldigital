<?php
declare(strict_types=1);

/**
 * AvrParser — Parsea el JSON comprimido del sistema AVR del TSE de Costa Rica.
 *
 * Formato observado en AVR2026 (https://www.tse.go.cr/AVR2026/api/resultados/):
 *
 *   {"n":12, "f":"2026-02-02", "h":"12:00 p. m.", "e":[
 *     2, "id", "l", "D",
 *     [12, "t","n1","n2","n3","n4","tM","mP","vE","vV","nB","et","v",
 *       <fila>..., <fila>..., ...]
 *     ,
 *     2, "id2", ... (12 circunscripciones en total)
 *   ]}
 *
 * Estructura de cada fila (12 valores):
 *   t  = tipo (siempre 0 hasta ahora)
 *   n1 = índice de provincia (1-7, 8=exterior); 0 = agregado nacional
 *   n2 = índice de cantón dentro de la provincia (0 = provincia total)
 *   n3 = índice de distrito dentro del cantón (0 = cantón total)
 *   n4 = índice de JRV dentro del distrito (0 = distrito total)
 *   tM = total de mesas/JRVs en ese nivel
 *   mP = mesas procesadas
 *   vE = votos emitidos (total sufragantes)
 *   vV = votos válidos
 *   nB = votos nulos + blancos
 *   et = electores totales (inscritos al momento de la elección)
 *   v  = array de votos por partido [2,"cP","v", cod1,v1, cod2,v2, ...]
 *
 * Mapeo a territorio:
 *   province_id = n1  (igual que en voters)
 *   canton_id   = n1*100 + n2  (si n2 > 0)
 *   codelec     = sprintf('%d%02d%03d', n1, n2, n3)  (si n2>0 y n3>0)
 *   jrv_idx     = n4  (índice interno del TSE, ≠ campo junta de voters)
 *
 * NOTA: el campo jrv_idx NO equivale al número de junta (ej. "02475").
 * Para unir con voters usar province_id / canton_id / district_id.
 */
class AvrParser
{
    private PDO $pdo;
    private array $districtIdByCodelec = [];

    public function __construct()
    {
        $this->pdo = dbConnect();
        foreach ($this->pdo->query(
            'SELECT id, codelec FROM districts WHERE codelec IS NOT NULL'
        )->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $this->districtIdByCodelec[$r['codelec']] = (int)$r['id'];
        }
    }

    /**
     * Parsea el archivo JSON del AVR y retorna los datos estructurados.
     *
     * Estructura real del JSON TSE:
     *   e = [2, 'id', 'l', 'D', [dataDiputados], 'P', [dataPresidencia], ...]
     *   Los tipos conocidos son: 'D'=Diputados, 'P'=Presidencia, 'A'=Alcaldes,
     *   'R'=Regidores, 'S'=Síndicos, 'I'=Intendentes, 'C'=Concejales.
     *
     * @param string $filepath   Ruta al archivo JSON.
     * @param string $typeFilter Tipo a importar: 'P'=Presidencia, 'D'=Diputados,
     *                           'A'=Alcaldes, 'all'=todos (default: 'all').
     * @return array{date:string, n_circunsc:int, rows:array[], types_found:array}
     */
    public function parseFile(string $filepath, string $typeFilter = 'all'): array
    {
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new \RuntimeException("No se pudo leer el archivo: $filepath");
        }

        $data = json_decode($content, true);
        if ($data === null) {
            throw new \RuntimeException("JSON inválido: " . json_last_error_msg());
        }

        // Soportar formato corto (AVR2026: n/f/h) y largo (AVR2022: numero/fecha/hora)
        $nCircunsc    = (int)($data['n'] ?? $data['numero'] ?? 0);
        $electionDate = $data['f'] ?? $data['fecha'] ?? null;
        $rawE         = $data['e'] ?? [];

        // Estructura: [2, id, label, TYPE_LABEL, [data], TYPE_LABEL2, [data2], ...]
        // El bloque comienza con el entero 2, seguido de 2 strings (id, l),
        // luego pares (etiqueta_tipo, array_datos) hasta el próximo 2 o fin.
        $allRows    = [];
        $typesFound = [];
        $pos        = 0;

        while ($pos < count($rawE)) {
            if ($rawE[$pos] !== 2) { $pos++; continue; }

            $pos += 3; // saltar: 2, id, l
            // Leer pares (label, dataArray) mientras sean strings seguidos de arrays
            while ($pos + 1 < count($rawE) && is_string($rawE[$pos]) && is_array($rawE[$pos + 1])) {
                $typeLabel = $rawE[$pos];
                $dataArray = $rawE[$pos + 1];
                $pos += 2;

                $typesFound[] = $typeLabel;

                if ($typeFilter !== 'all' && $typeLabel !== $typeFilter) continue;
                if (count($dataArray) < 14) continue;

                $rows    = $this->parseDataArray($dataArray, 0);
                $allRows = array_merge($allRows, $rows);
            }
        }

        return [
            'date'        => $electionDate,
            'n_circunsc'  => $nCircunsc,
            'rows'        => $allRows,
            'types_found' => array_unique($typesFound),
        ];
    }

    /**
     * Parsea el array de datos de una circunscripción.
     * Estructura: [nCols, col1, col2, ..., val1, val2, ..., val_n, ...]
     */
    private function parseDataArray(array $data, int $circId): array
    {
        $pos    = 0;
        $nCols  = (int)$data[$pos++];  // 12
        $cols   = [];
        for ($i = 0; $i < $nCols; $i++) {
            $cols[] = $data[$pos++];
        }
        // cols debería ser: ["t","n1","n2","n3","n4","tM","mP","vE","vV","nB","et","v"]

        // Normalizar nombres de columna: AVR2022 usa nombres largos, AVR2026 usa cortos
        static $FIELD_MAP = [
            'territorio' => 't',
            'nivel1' => 'n1', 'nivel2' => 'n2', 'nivel3' => 'n3', 'nivel4' => 'n4',
            'totalMesas' => 'tM', 'mesasProcesadas' => 'mP',
            'votosEmitidos' => 'vE', 'votosValidos' => 'vV',
            'nulosYBlancos' => 'nB', 'electores' => 'et',
        ];
        $cols = array_map(fn($c) => $FIELD_MAP[$c] ?? $c, $cols);

        $rows = [];
        while ($pos < count($data)) {
            $row = [];
            for ($i = 0; $i < $nCols; $i++) {
                if ($pos >= count($data)) break;
                $row[$cols[$i]] = $data[$pos++];
            }
            if (count($row) < $nCols) break;
            $parsed = $this->buildRow($row, $circId);
            if ($parsed !== null) $rows[] = $parsed;
        }

        return $rows;
    }

    private function buildRow(array $r, int $circId): ?array
    {
        $n1 = (int)($r['n1'] ?? 0);
        $n2 = (int)($r['n2'] ?? 0);
        $n3 = (int)($r['n3'] ?? 0);
        $n4 = (int)($r['n4'] ?? 0);

        if ($n1 === 0) return null; // agregado nacional — omitir

        // Determinar nivel
        if ($n2 === 0)      { $nivel = 'province'; }
        elseif ($n3 === 0)  { $nivel = 'canton';   }
        elseif ($n4 === 0)  { $nivel = 'district';  }
        else                { $nivel = 'jrv';       }

        $provinceId = $n1;
        $cantonId   = ($n2 > 0) ? $n1 * 100 + $n2 : null;
        $districtId = null;
        if ($n2 > 0 && $n3 > 0) {
            $codelec    = sprintf('%d%02d%03d', $n1, $n2, $n3);
            $districtId = $this->districtIdByCodelec[$codelec] ?? null;
        }
        $jrvIdx = ($n4 > 0) ? $n4 : null;

        // Votos por partido desde el array v
        $votesByParty = [];
        $vArray = $r['v'] ?? null;
        // Soportar 'cP' (AVR2026) y 'codPartido' (AVR2022)
        if (is_array($vArray) && count($vArray) >= 3 && $vArray[0] === 2
            && ($vArray[1] === 'cP' || $vArray[1] === 'codPartido')) {
            // [2, "cP"/"codPartido", "v"/"votos", cod1, v1, cod2, v2, ...]
            $i = 3;
            while ($i + 1 < count($vArray)) {
                $code  = (int)$vArray[$i];
                $votes = (int)$vArray[$i + 1];
                $votesByParty[$code] = $votes;
                $i += 2;
            }
        }

        return [
            'circunscripcion'    => $circId,
            'nivel'              => $nivel,
            'province_id'        => $provinceId,
            'canton_id'          => $cantonId,
            'district_id'        => $districtId,
            'jrv_idx'            => $jrvIdx,
            'inscritos'          => (int)($r['et'] ?? 0),
            'votos_emitidos'     => (int)($r['vE'] ?? 0),
            'votos_validos'      => (int)($r['vV'] ?? 0),
            'votos_nulos_blancos'=> (int)($r['nB'] ?? 0),
            'juntas_total'       => (int)($r['tM'] ?? 0),
            'juntas_procesadas'  => (int)($r['mP'] ?? 0),
            'votos_por_partido'  => $votesByParty,
        ];
    }

    /**
     * Inserta un lote de filas en election_results.
     */
    public function insertBatch(array $rows, int $syncRunId): int
    {
        if (!$rows) return 0;
        $this->pdo->beginTransaction();
        try {
            $sql = 'INSERT INTO election_results
                (sync_run_id, circunscripcion, nivel,
                 province_id, canton_id, district_id, jrv_idx,
                 inscritos, votos_emitidos, votos_validos, votos_nulos_blancos,
                 juntas_total, juntas_procesadas, votos_por_partido)
                VALUES
                (:run_id, :circ, :nivel,
                 :prov, :cant, :dist, :jrv,
                 :inscr, :vE, :vV, :nB, :tM, :mP, :vPP)';
            $stmt = $this->pdo->prepare($sql);
            foreach ($rows as $r) {
                $stmt->execute([
                    ':run_id' => $syncRunId,
                    ':circ'   => $r['circunscripcion'],
                    ':nivel'  => $r['nivel'],
                    ':prov'   => $r['province_id'],
                    ':cant'   => $r['canton_id'],
                    ':dist'   => $r['district_id'],
                    ':jrv'    => $r['jrv_idx'],
                    ':inscr'  => $r['inscritos'],
                    ':vE'     => $r['votos_emitidos'],
                    ':vV'     => $r['votos_validos'],
                    ':nB'     => $r['votos_nulos_blancos'],
                    ':tM'     => $r['juntas_total'],
                    ':mP'     => $r['juntas_procesadas'],
                    ':vPP'    => json_encode($r['votos_por_partido']),
                ]);
            }
            $this->pdo->commit();
            return count($rows);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
