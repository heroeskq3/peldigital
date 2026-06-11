<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../lib/db.php';
requerirAdminApi();

header('Content-Type: application/json; charset=utf-8');

$pdo = dbConnect();

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

$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();

$sources = [
    ['key' => 'voters',     'label' => 'Padrón Electoral',     'table' => 'voters',     'icon' => 'bi-people'],
    ['key' => 'provinces',  'label' => 'Provincias',           'table' => 'provinces',  'icon' => 'bi-geo-alt'],
    ['key' => 'cantons',    'label' => 'Cantones',             'table' => 'cantons',    'icon' => 'bi-pin-map'],
    ['key' => 'districts',  'label' => 'Distritos',            'table' => 'districts',  'icon' => 'bi-map'],
    ['key' => 'users',      'label' => 'Usuarios',             'table' => 'users',      'icon' => 'bi-person'],
    ['key' => 'roles',      'label' => 'Roles',                'table' => 'roles',      'icon' => 'bi-shield'],
    ['key' => 'audit_logs', 'label' => 'Registros de auditoría','table' => 'audit_logs','icon' => 'bi-journal'],
    ['key' => 'reports',    'label' => 'Reportes configurados','table' => 'reports',    'icon' => 'bi-bar-chart'],
    ['key' => 'migrations', 'label' => 'Migraciones aplicadas','table' => 'schema_migrations','icon' => 'bi-database-check'],
];

$result = [];
foreach ($sources as $s) {
    $s['count']   = count_table($pdo, $s['table']);
    $s['size_mb']  = table_size_mb($pdo, $dbName, $s['table']);
    $result[] = $s;
}

// Juntas stats
try {
    $juntas = $pdo->query("SELECT COUNT(DISTINCT junta) FROM voters WHERE province_id < 8")->fetchColumn();
} catch (Throwable) { $juntas = 0; }

echo json_encode([
    'sources'  => $result,
    'juntas'   => (int)$juntas,
    'db'       => $dbName,
    'server'   => PHP_OS . ' / PHP ' . PHP_VERSION,
    'now'      => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);
