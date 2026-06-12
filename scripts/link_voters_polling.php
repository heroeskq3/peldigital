#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * link_voters_polling.php
 *
 * Vincula cada voter con su local de votación (polling_place) y su
 * circunscripción electoral (electoral_district) usando el campo junta.
 *
 * El padrón TSE asigna a cada elector un número de junta (5 dígitos, ej "02475").
 * El catálogo de centros de votación (polling_places) cubre rangos de juntas:
 *   jrv_inicio ≤ junta ≤ jrv_fin
 * Por eso el JOIN se hace con BETWEEN.
 *
 * Uso:
 *   php scripts/link_voters_polling.php
 *   php scripts/link_voters_polling.php --dry-run     # muestra conteos sin UPDATE
 *   php scripts/link_voters_polling.php --batch=50000 # filas por lote (default 100000)
 *
 * REQUISITO PREVIO:
 *   1. php scripts/import_polling_places.php  (polling_places poblado con jrv_inicio/jrv_fin)
 *   2. php scripts/import_electoral_districts.php (electoral_districts poblado)
 *
 * TIEMPO ESTIMADO: ~5-10 minutos para 3.7M de voters.
 */

define('CLI_MODE', true);

$opts      = getopt('', ['dry-run', 'batch:']);
$dryRun    = isset($opts['dry-run']);
$batchSize = max(10000, (int)($opts['batch'] ?? 100000));

function out(string $m): void { fwrite(STDOUT, '[' . date('H:i:s') . "] {$m}\n"); }
function err(string $m): void { fwrite(STDERR, '[ERROR] ' . $m . "\n"); }

$rootDir = dirname(__DIR__);
require_once $rootDir . '/lib/db.php';

$pdo = dbData();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ─── Verificar prerequisitos ──────────────────────────────────────────────────
$ppCount = (int)$pdo->query('SELECT COUNT(*) FROM polling_places WHERE jrv_inicio IS NOT NULL')->fetchColumn();
if ($ppCount === 0) {
    err('La tabla polling_places no tiene datos con jrv_inicio/jrv_fin.');
    err('Ejecutar primero: php scripts/import_polling_places.php');
    exit(1);
}
out("polling_places con rangos JRV: {$ppCount}");

$edCount = (int)$pdo->query('SELECT COUNT(*) FROM electoral_districts')->fetchColumn();
if ($edCount === 0) {
    err('La tabla electoral_districts está vacía.');
    err('Ejecutar primero: php scripts/import_electoral_districts.php');
    exit(1);
}
out("electoral_districts: {$edCount}");

// ─── DRY-RUN: solo muestra estadísticas ───────────────────────────────────────
if ($dryRun) {
    out('[DRY-RUN] Calculando coincidencias...');

    $row = $pdo->query(
        "SELECT
           COUNT(*) AS total,
           SUM(CASE WHEN v.junta IS NOT NULL AND pp.id IS NOT NULL THEN 1 ELSE 0 END) AS con_match,
           SUM(CASE WHEN v.junta IS NOT NULL AND pp.id IS NULL    THEN 1 ELSE 0 END) AS sin_match,
           SUM(CASE WHEN v.junta IS NULL                          THEN 1 ELSE 0 END) AS sin_junta
         FROM voters v
         LEFT JOIN polling_places pp
           ON pp.province_id = v.province_id
          AND CAST(v.junta AS UNSIGNED) BETWEEN pp.jrv_inicio AND pp.jrv_fin"
    )->fetch(PDO::FETCH_ASSOC);

    out("  Voters total:        {$row['total']}");
    out("  Con match JRV:       {$row['con_match']}");
    out("  Sin match (junta≠0): {$row['sin_match']}");
    out("  Sin junta asignada:  {$row['sin_junta']}");
    exit(0);
}

// ─── Paso 1: Tabla temporal junta → polling_place_id ─────────────────────────
// Los JRV son únicos a nivel país (1-7154), así que expandemos cada rango en
// filas individuales y luego hacemos JOIN directo por junta_num (usa índice).
out('Paso 1/2: Construyendo mapa junta→local y actualizando voters.polling_place_id...');

$pdo->exec('CREATE TEMPORARY TABLE tmp_junta_map (
    junta_num SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    polling_place_id INT UNSIGNED NOT NULL,
    electoral_district_id INT UNSIGNED
) ENGINE=MEMORY');

$ranges = $pdo->query(
    'SELECT id, jrv_inicio, jrv_fin, electoral_district_id
     FROM polling_places WHERE jrv_inicio IS NOT NULL ORDER BY jrv_inicio'
)->fetchAll(PDO::FETCH_ASSOC);

$ins = $pdo->prepare('INSERT INTO tmp_junta_map VALUES (?,?,?)');
foreach ($ranges as $pp) {
    for ($j = (int)$pp['jrv_inicio']; $j <= (int)$pp['jrv_fin']; $j++) {
        $ins->execute([$j, $pp['id'], $pp['electoral_district_id']]);
    }
}
out('  Mapa creado: ' . count($ranges) . ' locales → 7154 entradas de junta.');

$updated = 0;
do {
    $affected = $pdo->exec(
        "UPDATE voters v
         JOIN tmp_junta_map m ON m.junta_num = CAST(v.junta AS UNSIGNED)
         SET v.polling_place_id = m.polling_place_id
         WHERE v.polling_place_id IS NULL
         LIMIT {$batchSize}"
    );
    $updated += $affected;
    if ($affected > 0) {
        $pct = round($updated / 3731788 * 100, 1);
        out("  polling_place_id: {$updated} ({$pct}%)");
    }
} while ($affected > 0);

out("Paso 1 completado: {$updated} voters con polling_place_id.");

// ─── Paso 2: Actualizar electoral_district_id ─────────────────────────────────
out('Paso 2/2: Actualizando voters.electoral_district_id...');

$affected2 = 0;
do {
    $aff = $pdo->exec(
        "UPDATE voters v
         JOIN electoral_districts ed ON ed.province_id = v.province_id
         SET v.electoral_district_id = ed.id
         WHERE v.electoral_district_id IS NULL
           AND v.province_id BETWEEN 1 AND 7
         LIMIT {$batchSize}"
    );
    $affected2 += $aff;
    if ($aff > 0) {
        out("  electoral_district_id: {$affected2}");
    }
} while ($aff > 0);

out("Paso 2 completado: {$affected2} voters con electoral_district_id.");

// ─── Resumen ──────────────────────────────────────────────────────────────────
$stats = $pdo->query(
    "SELECT
       COUNT(*) AS total,
       SUM(polling_place_id IS NOT NULL)      AS con_local,
       SUM(electoral_district_id IS NOT NULL) AS con_ed
     FROM voters"
)->fetch(PDO::FETCH_ASSOC);

out('');
out('╔══════════════════════════════════════════════╗');
out('║  RESUMEN FINAL                               ║');
out('╠══════════════════════════════════════════════╣');
out(sprintf('║  Total voters              : %12s  ║', number_format((int)$stats['total'])));
out(sprintf('║  Con polling_place_id      : %12s  ║', number_format((int)$stats['con_local'])));
out(sprintf('║  Con electoral_district_id : %12s  ║', number_format((int)$stats['con_ed'])));
out('╚══════════════════════════════════════════════╝');
out('Vinculación completada.');
exit(0);
