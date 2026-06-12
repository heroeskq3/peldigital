<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../lib/db.php';
requerirAdminApi();

header('Content-Type: application/json; charset=utf-8');

$sys = dbConnect();  // pel_electoral  — tablas de sistema
$dw  = dbData();     // peldigital_data — tablas de datos

function count_table(PDO $pdo, string $table): int {
    try { return (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(); }
    catch (Throwable) { return -1; }
}

function table_size_mb(PDO $pdo, string $db, string $table): ?float {
    try {
        $row = $pdo->query("SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) AS mb
                            FROM information_schema.tables
                            WHERE table_schema = '{$db}' AND table_name = '{$table}'")->fetch();
        return $row ? (float)$row['mb'] : null;
    } catch (Throwable) { return null; }
}

$sysDb = $sys->query("SELECT DATABASE()")->fetchColumn();
$dwDb  = $dw->query("SELECT DATABASE()")->fetchColumn();

// Fuentes de datos: [pdo, tabla, label, icon]
$sources = [
    // ── Datos electorales (peldigital_data) ─────────────────────────────────
    ['pdo' => $dw,  'db' => $dwDb,  'key' => 'voters',              'label' => 'Padrón Electoral',           'table' => 'voters',                        'icon' => 'bi-people'],
    ['pdo' => $dw,  'db' => $dwDb,  'key' => 'voter_enrichments',   'label' => 'Enriquecimientos',           'table' => 'voter_enrichments',             'icon' => 'bi-person-check'],
    ['pdo' => $dw,  'db' => $dwDb,  'key' => 'provinces',           'label' => 'Provincias',                 'table' => 'provinces',                     'icon' => 'bi-geo-alt'],
    ['pdo' => $dw,  'db' => $dwDb,  'key' => 'cantons',             'label' => 'Cantones',                   'table' => 'cantons',                       'icon' => 'bi-pin-map'],
    ['pdo' => $dw,  'db' => $dwDb,  'key' => 'districts',           'label' => 'Distritos',                  'table' => 'districts',                     'icon' => 'bi-map'],
    ['pdo' => $dw,  'db' => $dwDb,  'key' => 'polling',             'label' => 'Centros de votación',        'table' => 'polling_places',                'icon' => 'bi-building'],
    ['pdo' => $dw,  'db' => $dwDb,  'key' => 'electoral_districts', 'label' => 'Circunscripciones legis.',  'table' => 'electoral_districts',           'icon' => 'bi-diagram-3'],
    ['pdo' => $dw,  'db' => $dwDb,  'key' => 'results',             'label' => 'Resultados electorales',     'table' => 'election_results',              'icon' => 'bi-bar-chart'],
    ['pdo' => $dw,  'db' => $dwDb,  'key' => 'parties',             'label' => 'Partidos políticos',         'table' => 'parties',                       'icon' => 'bi-flag'],
    ['pdo' => $dw,  'db' => $dwDb,  'key' => 'summary_jrv',         'label' => 'Resumen JRV (Gold)',         'table' => 'summary_jrv',                   'icon' => 'bi-table'],
    // ── Sistema (pel_electoral) ──────────────────────────────────────────────
    ['pdo' => $sys, 'db' => $sysDb, 'key' => 'users',               'label' => 'Usuarios',                   'table' => 'users',                         'icon' => 'bi-person'],
    ['pdo' => $sys, 'db' => $sysDb, 'key' => 'roles',               'label' => 'Roles',                      'table' => 'roles',                         'icon' => 'bi-shield'],
    ['pdo' => $sys, 'db' => $sysDb, 'key' => 'audit_logs',          'label' => 'Registros de auditoría',     'table' => 'audit_logs',                    'icon' => 'bi-journal'],
    ['pdo' => $sys, 'db' => $sysDb, 'key' => 'reports',             'label' => 'Reportes configurados',      'table' => 'reports',                       'icon' => 'bi-bar-chart-line'],
    ['pdo' => $sys, 'db' => $sysDb, 'key' => 'report_categories',   'label' => 'Categorías de reportes',     'table' => 'report_categories',             'icon' => 'bi-folder'],
    ['pdo' => $sys, 'db' => $sysDb, 'key' => 'migrations',          'label' => 'Migraciones sistema',        'table' => 'schema_migrations',             'icon' => 'bi-database-check'],
];

$result = [];
foreach ($sources as $s) {
    $s['count']    = count_table($s['pdo'], $s['table']);
    $s['size_mb']  = table_size_mb($s['pdo'], $s['db'], $s['table']);
    unset($s['pdo']);
    $result[] = $s;
}

try {
    $juntas = $dw->query("SELECT COUNT(DISTINCT junta) FROM voters WHERE province_id < 8")->fetchColumn();
} catch (Throwable) { $juntas = 0; }

echo json_encode([
    'sources'  => $result,
    'juntas'   => (int)$juntas,
    'sys_db'   => $sysDb,
    'dw_db'    => $dwDb,
    'server'   => PHP_OS . ' / PHP ' . PHP_VERSION,
    'now'      => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);
