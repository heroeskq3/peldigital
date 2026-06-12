<?php
/**
 * scripts/refresh_bastiones.php — Clasifica cada JRV como bastión electoral.
 *
 * Lee election_results (nivel=jrv) cruzando con summary_jrv para obtener
 * la geografía. Calcula ganador por partido, porcentaje y margen para
 * las 4 elecciones disponibles. Clasifica sobre las 3 presidenciales (2026,
 * 2022-1a, 2022-2a). Inserta todo en summary_bastiones via REPLACE INTO.
 *
 * Uso:
 *   php scripts/refresh_bastiones.php
 *   php scripts/refresh_bastiones.php --quiet
 */

define('CLI_MODE', true);
$quiet = in_array('--quiet', $argv ?? []);

function log_msg(string $msg): void {
    global $quiet;
    if (!$quiet) echo '[' . date('H:i:s') . '] ' . $msg . "\n";
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/lib/db.php';

$pdo = dbData();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// sync_run_id → descripción
// 4 = Presidencial 2026, 5 = Municipal 2024, 6 = Presidencial 2022 1a, 7 = Presidencial 2022 2a
const RUN_IDS = [4, 5, 6, 7];
// Elecciones presidenciales para clasificación
const PRES_RUNS = [4, 6, 7];

// ─── Cargar geografía de summary_jrv ─────────────────────────────────────────
log_msg('Cargando summary_jrv…');
$jrvGeo = [];
$rows = $pdo->query("
    SELECT junta, province_id, canton_id, district_id,
           provincia, canton, distrito, inscritos,
           polling_place_id, local_nombre
    FROM summary_jrv
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $jrvGeo[$r['junta']] = $r;
}
log_msg('  ' . count($jrvGeo) . ' JRVs cargadas desde summary_jrv.');

// ─── Cargar resultados de elecciones ─────────────────────────────────────────
// Estructura: $resultados[junta][run_id] = [...datos elección]
log_msg('Cargando election_results…');

$runPlaceholders = implode(',', array_fill(0, count(RUN_IDS), '?'));
$stmt = $pdo->prepare("
    SELECT jrv_idx, sync_run_id,
           inscritos, votos_emitidos, votos_validos,
           votos_por_partido
    FROM   election_results
    WHERE  nivel = 'jrv'
      AND  sync_run_id IN ($runPlaceholders)
");
$stmt->execute(RUN_IDS);
$elecRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
log_msg('  ' . count($elecRows) . ' filas de election_results cargadas.');

// Indexar por junta (lpad a 5 chars) × run_id
$elecByJrv = [];
$noGeoCount = 0;
foreach ($elecRows as $r) {
    $junta = str_pad((string)(int)$r['jrv_idx'], 5, '0', STR_PAD_LEFT);
    $runId = (int)$r['sync_run_id'];

    if (!isset($jrvGeo[$junta])) {
        $noGeoCount++;
        continue;
    }

    $vpj = json_decode($r['votos_por_partido'] ?? '{}', true) ?: [];
    if (empty($vpj)) continue;

    // Ordenar de mayor a menor para encontrar 1ro y 2do
    arsort($vpj);
    $tsCodes = array_keys($vpj);
    $votes   = array_values($vpj);

    $winner     = (int)$tsCodes[0];
    $winnerVots = (int)$votes[0];
    $secondVots = isset($votes[1]) ? (int)$votes[1] : 0;
    $validos    = (int)$r['votos_validos'];
    $emitidos   = (int)$r['votos_emitidos'];
    $inscritos  = (int)$r['inscritos'];

    $pct  = $validos > 0 ? round($winnerVots / $validos * 100, 2) : 0.0;
    $marg = $winnerVots - $secondVots;
    $part = $inscritos > 0 ? round($emitidos / $inscritos * 100, 2) : 0.0;

    $elecByJrv[$junta][$runId] = [
        'tse_code'      => $winner,
        'votos'         => $winnerVots,
        'pct'           => $pct,
        'margen'        => $marg,
        'participacion' => $part,
        'votos_emitidos'=> $emitidos,
    ];
}
log_msg('  ' . $noGeoCount . ' filas sin geo ignoradas (JRVs fuera del padrón).');

// ─── Clasificar cada JRV ──────────────────────────────────────────────────────
log_msg('Clasificando JRVs…');

function clasificar(array $presElec): array {
    // $presElec = [run_id => datos] para runs presidenciales disponibles

    if (empty($presElec)) {
        return ['bastion' => 'volatil', 'tendencia' => 'estable', 'dom' => null, 'dom_wins' => 0, 'pct_avg' => null, 'marg_avg' => null];
    }

    // Contar victorias por partido en presidenciales
    $wins = [];
    foreach ($presElec as $e) {
        $tc = $e['tse_code'];
        $wins[$tc] = ($wins[$tc] ?? 0) + 1;
    }
    arsort($wins);
    $domParty = (int)array_key_first($wins);
    $domWins  = (int)$wins[$domParty];

    // Promedio de pct para elecciones donde ganó ese partido
    $domPcts  = [];
    $domMargens = [];
    foreach ($presElec as $e) {
        if ((int)$e['tse_code'] === $domParty) {
            $domPcts[]   = (float)$e['pct'];
            $domMargens[] = (float)$e['margen'];
        }
    }
    $avgPct  = count($domPcts) > 0 ? round(array_sum($domPcts) / count($domPcts), 2) : null;
    $avgMarg = count($domMargens) > 0 ? round(array_sum($domMargens) / count($domMargens), 2) : null;

    // Clasificación — requiere mínimo 2 elecciones presidenciales para bastión
    $total = count($presElec);
    $clsf  = 'volatil';

    if ($total >= 2 && $domWins === $total && $avgPct >= 60) {
        $clsf = 'bastion_fuerte';
    } elseif ($total >= 2 && $domWins >= ($total - 1) && $avgPct >= 50) {
        $clsf = 'bastion_moderado';
    } elseif ($total >= 2) {
        // ¿Cambió el ganador entre la más antigua y la más reciente?
        $runIds = array_keys($presElec);
        sort($runIds);
        $oldest = $presElec[min($runIds)]['tse_code'] ?? null;
        $newest = $presElec[max($runIds)]['tse_code'] ?? null;
        if ($oldest !== null && $newest !== null && $oldest !== $newest) {
            $clsf = 'transicion';
        } else {
            // ¿Competitivo?: diferencia de pct < 15 pts
            $avgMargPct = count($domPcts) > 0 ? (100 - $avgPct) : 50;
            $clsf = $avgMargPct < 15 || ($domWins > 0 && $avgPct >= 45) ? 'competitivo' : 'volatil';
        }
    }
    // total < 2 (JRVs nuevas sin historial): queda 'volatil'

    // Tendencia: comparar participación del dom_party entre e6 (2022-1a) y e4 (2026)
    $tendencia = 'estable';
    if (isset($presElec[4], $presElec[6])) {
        $pct4 = (float)($presElec[4]['pct'] ?? 0);
        $pct6 = (float)($presElec[6]['pct'] ?? 0);
        if ($presElec[4]['tse_code'] === $presElec[6]['tse_code']) {
            if ($pct4 >= $pct6 + 5)      $tendencia = 'subiendo';
            elseif ($pct4 <= $pct6 - 5)  $tendencia = 'bajando';
        } else {
            $tendencia = 'estable'; // cambio de ganador — transición ya captura esto
        }
    }

    return [
        'bastion'   => $clsf,
        'tendencia' => $tendencia,
        'dom'       => $domParty,
        'dom_wins'  => $domWins,
        'pct_avg'   => $avgPct,
        'marg_avg'  => $avgMarg,
    ];
}

// ─── Construir conjunto completo de juntas a procesar ────────────────────────
$allJuntas = array_unique(array_merge(
    array_keys($jrvGeo),
    array_keys($elecByJrv)
));

log_msg('  ' . count($allJuntas) . ' JRVs únicas a procesar.');

$stmt = $pdo->prepare("
    REPLACE INTO summary_bastiones (
        junta, province_id, canton_id, district_id,
        provincia, canton, distrito,
        inscritos, polling_place_id, local_nombre,
        e4_tse_code, e4_votos, e4_pct, e4_margen, e4_participacion, e4_votos_emitidos,
        e5_tse_code, e5_votos, e5_pct, e5_margen, e5_participacion, e5_votos_emitidos,
        e6_tse_code, e6_votos, e6_pct, e6_margen, e6_participacion, e6_votos_emitidos,
        e7_tse_code, e7_votos, e7_pct, e7_margen, e7_participacion, e7_votos_emitidos,
        dom_tse_code, dom_wins, dom_pct_avg, margen_avg,
        clasificacion, tendencia,
        votos_conquista, indice_oportunidad
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?
    )
");

$inserted = 0;
$skipped  = 0;

foreach ($allJuntas as $junta) {
    $geo = $jrvGeo[$junta] ?? null;
    if (!$geo) { $skipped++; continue; }

    $elec = $elecByJrv[$junta] ?? [];

    // Presidenciales para clasificación
    $presElec = [];
    foreach (PRES_RUNS as $rId) {
        if (isset($elec[$rId])) $presElec[$rId] = $elec[$rId];
    }

    $cls = clasificar($presElec);

    // Índice de oportunidad (aplica solo si hay datos 2026)
    $conquista   = null;
    $oportunidad = null;
    if (isset($elec[4])) {
        $e4       = $elec[4];
        $conquista = $e4['margen'] + 1;

        // avg_participacion de presidenciales disponibles
        $parts = [];
        foreach ($presElec as $pe) $parts[] = $pe['participacion'];
        $avgPart = count($parts) > 0 ? array_sum($parts) / count($parts) : 0;

        $inscritos   = (int)$geo['inscritos'];
        $vuln        = max(0, 1.0 - (($cls['pct_avg'] ?? 50) / 100.0));
        $denom       = max($conquista, 1);
        $oportunidad = round(($inscritos * ($avgPart / 100) * $vuln) / $denom, 2);
    }

    $e = fn($rid, $k) => $elec[$rid][$k] ?? null;

    $stmt->execute([
        $junta,
        (int)$geo['province_id'],
        (int)$geo['canton_id'],
        (int)$geo['district_id'],
        $geo['provincia'],
        $geo['canton'],
        $geo['distrito'],
        (int)$geo['inscritos'],
        $geo['polling_place_id'] ? (int)$geo['polling_place_id'] : null,
        $geo['local_nombre'],
        // e4
        $e(4,'tse_code'), $e(4,'votos'), $e(4,'pct'), $e(4,'margen'), $e(4,'participacion'), $e(4,'votos_emitidos'),
        // e5
        $e(5,'tse_code'), $e(5,'votos'), $e(5,'pct'), $e(5,'margen'), $e(5,'participacion'), $e(5,'votos_emitidos'),
        // e6
        $e(6,'tse_code'), $e(6,'votos'), $e(6,'pct'), $e(6,'margen'), $e(6,'participacion'), $e(6,'votos_emitidos'),
        // e7
        $e(7,'tse_code'), $e(7,'votos'), $e(7,'pct'), $e(7,'margen'), $e(7,'participacion'), $e(7,'votos_emitidos'),
        // clasificación
        $cls['dom'],
        $cls['dom_wins'],
        $cls['pct_avg'],
        $cls['marg_avg'],
        $cls['bastion'],
        $cls['tendencia'],
        $conquista,
        $oportunidad,
    ]);
    $inserted++;
}

log_msg("Insertadas/actualizadas: $inserted JRVs.");
if ($skipped > 0) log_msg("Ignoradas (sin geo): $skipped.");

// ─── Resumen por clasificación ────────────────────────────────────────────────
log_msg('');
log_msg('Distribución por clasificación:');
$resumen = $pdo->query("
    SELECT clasificacion, COUNT(*) AS cnt, dom_tse_code AS partido_dom,
           ROUND(AVG(dom_pct_avg),1) AS pct_prom
    FROM summary_bastiones
    WHERE e4_tse_code IS NOT NULL
    GROUP BY clasificacion
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($resumen as $r) {
    log_msg('  ' . str_pad($r['clasificacion'], 20) . ' : ' . $r['cnt'] . ' JRVs');
}
log_msg('');
log_msg('refresh_bastiones completado.');
