<?php
declare(strict_types=1);

use PDO;

/**
 * PadronTSEParser
 *
 * Parsea PADRON_COMPLETO.TXT del TSE (padrón 2026).
 *
 * Formato del archivo (campos separados por coma, ancho fijo):
 *   CEDULA(9), CODELEC(6), RELLENO(1), FECHACADUC(8), JUNTA(5),
 *   NOMBRE(30), 1.APELLIDO(26), 2.APELLIDO(26)
 *
 * CODELEC se descompone como:
 *   dígito 1    = province_id (1-8)
 *   dígitos 2-3 = cantón dentro de la provincia → canton_id = prov*100 + canton
 *   dígitos 4-6 = distrito → lookup en districts.codelec → district_id
 *
 * FECHACADUC = vencimiento de la cédula (formato YYYYMMDD)
 */
class PadronTSEParser
{
    private PDO $pdo;
    private array $validProvinces    = [];  // province_id  → true
    private array $validCantons      = [];  // canton_id    → true
    private array $districtByCodelec = [];  // codelec(str) → district_id (int)

    public function __construct()
    {
        $this->pdo = dbConnect();

        $this->validProvinces = array_flip(
            array_map('intval', $this->pdo->query('SELECT id FROM provinces')->fetchAll(PDO::FETCH_COLUMN) ?: [])
        );
        $this->validCantons = array_flip(
            array_map('intval', $this->pdo->query('SELECT id FROM cantons')->fetchAll(PDO::FETCH_COLUMN) ?: [])
        );

        // Lookup codelec → district.id (solo distritos con codelec asignado)
        $rows = $this->pdo->query(
            'SELECT id, codelec FROM districts WHERE codelec IS NOT NULL'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $this->districtByCodelec[(string)$row['codelec']] = (int)$row['id'];
        }
    }

    /**
     * Lee el archivo PADRON.TXT completo e inserta en voters por lotes.
     *
     * @param string $filepath   Ruta al archivo PADRON.TXT
     * @param int    $syncRunId  ID del registro en padron_sync_runs
     * @param int    $batchSize  Registros por transacción (default 1000)
     * @param bool   $replaceAll Si true, trunca voters antes de insertar
     */
    public function parseFile(string $filepath, int $syncRunId, int $batchSize = 1000, bool $replaceAll = false): array
    {
        $handle = fopen($filepath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el archivo del padrón para lectura.');
        }

        if ($replaceAll) {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $this->pdo->exec('TRUNCATE TABLE voters');
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $inserted = 0;
        $errors   = 0;
        $batch    = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '') {
                    continue;
                }

                $row = $this->parseLine($line);
                if ($row === null) {
                    $errors++;
                    continue;
                }

                $batch[] = $row;
                if (count($batch) >= $batchSize) {
                    $inserted += $this->upsertBatch($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $inserted += $this->upsertBatch($batch);
            }
        } finally {
            fclose($handle);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE padron_sync_runs SET records_ok = ?, records_error = ? WHERE id = ?'
        );
        $stmt->execute([$inserted, $errors, $syncRunId]);

        return ['records_ok' => $inserted, 'records_error' => $errors];
    }

    /**
     * Parsea una línea del PADRON_COMPLETO.TXT.
     * Retorna null si la línea es inválida o incompleta.
     */
    public function parseLine(string $line): ?array
    {
        // El archivo del TSE viene en ISO-8859-1
        $line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');

        // El archivo usa coma como separador entre los 8 campos
        $parts = explode(',', $line);
        if (count($parts) < 8) {
            return null;
        }

        $cedula        = trim($parts[0]);
        $codelec       = trim($parts[1]);
        // $parts[2]   = RELLENO, se ignora
        $fechaCaducRaw = trim($parts[3]);
        $junta         = trim($parts[4]);
        $nombre        = trim($parts[5]);
        $apellido1     = trim($parts[6]);
        $apellido2     = trim($parts[7]);

        // Validaciones mínimas
        if ($cedula === '' || !ctype_digit($cedula)) {
            return null;
        }
        if ($nombre === '' || $apellido1 === '') {
            return null;
        }

        // ---- Decodificar CODELEC ----
        $provinceId = null;
        $cantonId   = null;
        $districtId = null;

        if (strlen($codelec) === 6 && ctype_digit($codelec)) {
            $provinceDigit = (int)$codelec[0];
            $cantonNum     = (int)substr($codelec, 1, 2);
            $cantonIdCalc  = $provinceDigit * 100 + $cantonNum;

            if (isset($this->validProvinces[$provinceDigit])) {
                $provinceId = $provinceDigit;
            }
            if (isset($this->validCantons[$cantonIdCalc])) {
                $cantonId = $cantonIdCalc;
            }
            $districtId = $this->districtByCodelec[$codelec] ?? null;
        }

        // ---- Parsear FECHACADUC (YYYYMMDD) ----
        $fechaCaduc = null;
        if (preg_match('/^\d{8}$/', $fechaCaducRaw)) {
            $dt = \DateTime::createFromFormat('Ymd', $fechaCaducRaw);
            if ($dt instanceof \DateTime) {
                $fechaCaduc = $dt->format('Y-m-d');
            }
        }

        return [
            'cedula'      => $cedula,
            'nombre'      => $nombre,
            'apellido1'   => $apellido1,
            'apellido2'   => $apellido2 !== '' ? $apellido2 : null,
            'fecha_caduc' => $fechaCaduc,
            'junta'       => $junta !== '' ? $junta : null,
            'province_id' => $provinceId,
            'canton_id'   => $cantonId,
            'district_id' => $districtId,
        ];
    }

    private function upsertBatch(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $this->pdo->beginTransaction();
        try {
            $sql = 'INSERT INTO voters (
                        cedula, nombre, apellido1, apellido2,
                        fecha_caduc, junta,
                        province_id, canton_id, district_id,
                        imported_at
                    ) VALUES (
                        :cedula, :nombre, :apellido1, :apellido2,
                        :fecha_caduc, :junta,
                        :province_id, :canton_id, :district_id,
                        NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        nombre      = VALUES(nombre),
                        apellido1   = VALUES(apellido1),
                        apellido2   = VALUES(apellido2),
                        fecha_caduc = VALUES(fecha_caduc),
                        junta       = VALUES(junta),
                        province_id = VALUES(province_id),
                        canton_id   = VALUES(canton_id),
                        district_id = VALUES(district_id),
                        imported_at = NOW()';

            $stmt = $this->pdo->prepare($sql);

            foreach ($rows as $r) {
                $stmt->execute([
                    ':cedula'      => $r['cedula'],
                    ':nombre'      => $r['nombre'],
                    ':apellido1'   => $r['apellido1'],
                    ':apellido2'   => $r['apellido2'],
                    ':fecha_caduc' => $r['fecha_caduc'],
                    ':junta'       => $r['junta'],
                    ':province_id' => $r['province_id'],
                    ':canton_id'   => $r['canton_id'],
                    ':district_id' => $r['district_id'],
                ]);
            }

            $this->pdo->commit();
            return count($rows);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
