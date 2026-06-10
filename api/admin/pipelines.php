<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../lib/db.php';
requerirLoginApi();

header('Content-Type: application/json; charset=utf-8');

$pdo = dbConnect();

$applied = [];
$rows = $pdo->query('SELECT migration, executed_at FROM schema_migrations ORDER BY executed_at, id')
             ->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $applied[$r['migration']] = $r['executed_at'];
}

$dir   = __DIR__ . '/../../migrations';
$files = glob($dir . '/*.sql') ?: [];
sort($files);

$migrations = [];
foreach ($files as $path) {
    $name = basename($path);
    $ran  = isset($applied[$name]);
    $migrations[] = [
        'file'       => $name,
        'ran'        => $ran,
        'executed_at'=> $ran ? $applied[$name] : null,
    ];
}

// Migrations in DB but not on disk (orphaned)
$onDisk = array_map('basename', $files);
foreach ($applied as $name => $at) {
    if (!in_array($name, $onDisk, true)) {
        $migrations[] = [
            'file'        => $name,
            'ran'         => true,
            'executed_at' => $at,
            'orphaned'    => true,
        ];
    }
}

usort($migrations, fn($a, $b) => strcmp($a['file'], $b['file']));

$pending = count(array_filter($migrations, fn($m) => !$m['ran']));

echo json_encode([
    'migrations' => $migrations,
    'total'      => count($migrations),
    'applied'    => count($applied),
    'pending'    => $pending,
], JSON_UNESCAPED_UNICODE);
