<?php
/**
 * scripts/refresh_summaries.php — Regenera las tablas de resumen pre-agregadas.
 *
 * Corre en segundos (no minutos): hace GROUP BY una vez sobre voters con el
 * índice cubriente, enriquece con tablas de geografía en memoria, e inserta
 * todo por REPLACE INTO.  Correr después de cada importación del padrón.
 *
 * Uso:
 *   php scripts/refresh_summaries.php
 *   php scripts/refresh_summaries.php --quiet
 */

define('CLI_MODE', true);
$quiet = in_array('--quiet', $argv ?? []);

function log_msg(string $msg, bool $quiet = false): void {
    if (!$quiet) echo '[' . date('H:i:s') . '] ' . $msg . "\n";
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/lib/db.php';

$pdo = dbData();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ─── Cargar tablas de geografía en memoria ────────────────────────────────────
log_msg('Cargando geografía…', $quiet);

$provNames = [];
foreach ($pdo->query("SELECT id, name FROM provinces")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $provNames[(int)$r['id']] = $r['name'];
}

$cantData = [];
foreach ($pdo->query("SELECT id, name, province_id FROM cantons")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $cantData[(int)$r['id']] = ['name' => $r['name'], 'province_id' => (int)$r['province_id']];
}

$distData = [];
foreach ($pdo->query("SELECT id, name, codelec, canton_id FROM districts WHERE codelec IS NOT NULL")
              ->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $c    = $r['codelec'];
    $geo5 = substr($c, 0, 3) . str_pad((int)substr($c, 3), 2, '0', STR_PAD_LEFT);
    $distData[(int)$r['id']] = [
        'name'      => $r['name'],
        'codelec'   => $c,
        'geo5'      => $geo5,
        'canton_id' => (int)$r['canton_id'],
    ];
}

// ─── Paso 1: conteos base por district/province/canton ────────────────────────
log_msg('Contando inscritos por distrito (GROUP BY sobre voters)…', $quiet);
$rawRows = $pdo->query("
    SELECT district_id, province_id, canton_id, COUNT(*) AS cnt
    FROM   voters
    WHERE  province_id BETWEEN 1 AND 7
      AND  district_id IS NOT NULL
    GROUP  BY district_id, province_id, canton_id
")->fetchAll(PDO::FETCH_ASSOC);

$grandTotal = array_sum(array_column($rawRows, 'cnt'));
log_msg("  Total inscritos nacionales: " . number_format($grandTotal), $quiet);

// ─── Agregación en PHP ────────────────────────────────────────────────────────
$byProv = [];
$byCant = [];
$byDist = [];

foreach ($rawRows as $r) {
    $dId = (int)$r['district_id'];
    $pId = (int)$r['province_id'];
    $cId = (int)$r['canton_id'];
    $cnt = (int)$r['cnt'];

    $byProv[$pId] = ($byProv[$pId] ?? 0) + $cnt;
    $byCant[$cId] = ($byCant[$cId] ?? 0) + $cnt;

    if (!isset($byDist[$dId])) {
        $dd = $distData[$dId] ?? null;
        $byDist[$dId] = [
            'district_id' => $dId,
            'nombre'      => $dd['name']   ?? 'N/D',
            'geo5'        => $dd['geo5']   ?? '',
            'canton_id'   => $cId,
            'province_id' => $pId,
            'inscritos'   => 0,
        ];
    }
    $byDist[$dId]['inscritos'] += $cnt;
}

// ─── REPLACE INTO summary_inscritos_provincia ────────────────────────────────
log_msg('Actualizando summary_inscritos_provincia…', $quiet);
$stProv = $pdo->prepare("
    REPLACE INTO summary_inscritos_provincia
        (province_id, nombre, inscritos, pct_nacional)
    VALUES (?, ?, ?, ?)
");
foreach ($byProv as $pId => $cnt) {
    $stProv->execute([$pId, $provNames[$pId] ?? 'N/D', $cnt, round($cnt / $grandTotal * 100, 3)]);
}

// ─── REPLACE INTO summary_inscritos_canton ───────────────────────────────────
log_msg('Actualizando summary_inscritos_canton…', $quiet);
$stCant = $pdo->prepare("
    REPLACE INTO summary_inscritos_canton
        (canton_id, nombre, province_id, provincia, inscritos, pct_nacional)
    VALUES (?, ?, ?, ?, ?, ?)
");
foreach ($byCant as $cId => $cnt) {
    $cd   = $cantData[$cId] ?? null;
    $pId2 = $cd ? $cd['province_id'] : 0;
    $stCant->execute([$cId, $cd['name'] ?? 'N/D', $pId2, $provNames[$pId2] ?? 'N/D', $cnt, round($cnt / $grandTotal * 100, 3)]);
}

// ─── REPLACE INTO summary_inscritos_distrito ─────────────────────────────────
log_msg('Actualizando summary_inscritos_distrito…', $quiet);
$stDist = $pdo->prepare("
    REPLACE INTO summary_inscritos_distrito
        (district_id, nombre, canton_id, canton, province_id, provincia, geo5, inscritos, pct_nacional)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($byDist as $d) {
    $cId2 = $d['canton_id'];
    $pId2 = $d['province_id'];
    $cd   = $cantData[$cId2] ?? null;
    $stDist->execute([
        $d['district_id'], $d['nombre'],
        $cId2, $cd['name'] ?? 'N/D',
        $pId2, $provNames[$pId2] ?? 'N/D',
        $d['geo5'], $d['inscritos'],
        round($d['inscritos'] / $grandTotal * 100, 3),
    ]);
}

// ─── JRV ─────────────────────────────────────────────────────────────────────
log_msg('Contando inscritos por JRV…', $quiet);
$jrvRows = $pdo->query("
    SELECT junta, district_id, province_id, canton_id, COUNT(*) AS cnt
    FROM   voters
    WHERE  junta IS NOT NULL
      AND  province_id BETWEEN 1 AND 7
    GROUP  BY junta, district_id, province_id, canton_id
")->fetchAll(PDO::FETCH_ASSOC);

log_msg('Actualizando summary_jrv…', $quiet);
$stJrv = $pdo->prepare("
    REPLACE INTO summary_jrv
        (junta, district_id, canton_id, province_id, distrito, canton, provincia, inscritos, clasificacion)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($jrvRows as $r) {
    $cnt  = (int)$r['cnt'];
    $dId  = (int)$r['district_id'];
    $cId  = (int)$r['canton_id'];
    $pId  = (int)$r['province_id'];
    $dd   = $distData[$dId] ?? null;
    $cd   = $cantData[$cId] ?? null;
    $clsf = $cnt >= 600 ? 'alta' : ($cnt >= 300 ? 'media' : 'baja');
    $stJrv->execute([
        $r['junta'], $dId, $cId, $pId,
        $dd['name']  ?? 'N/D',
        $cd['name']  ?? 'N/D',
        $provNames[$pId] ?? 'N/D',
        $cnt, $clsf,
    ]);
}

log_msg('Resumen completado.', $quiet);
log_msg('  Provincias : ' . count($byProv),  $quiet);
log_msg('  Cantones   : ' . count($byCant),  $quiet);
log_msg('  Distritos  : ' . count($byDist),  $quiet);
log_msg('  JRVs       : ' . count($jrvRows), $quiet);
