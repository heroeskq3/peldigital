#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * import_electoral_districts.php
 *
 * Puebla la tabla electoral_districts con las 7 circunscripciones electorales
 * legislativas de Costa Rica (una por provincia).
 *
 * En el sistema electoral costarricense, para elecciones presidenciales y
 * legislativas, la circunscripción electoral equivale a la provincia.
 * Cada provincia elige diputados en proporción a su padrón.
 *
 * Uso:
 *   php scripts/import_electoral_districts.php
 *   php scripts/import_electoral_districts.php --dry-run   # solo imprime sin insertar
 */

define('CLI_MODE', true);

$opts   = getopt('', ['dry-run']);
$dryRun = isset($opts['dry-run']);

function out(string $m): void { fwrite(STDOUT, '[' . date('H:i:s') . "] {$m}\n"); }
function err(string $m): void { fwrite(STDERR, '[ERROR] ' . $m . "\n"); }

$rootDir = dirname(__DIR__);
require_once $rootDir . '/lib/db.php';

// Circunscripciones electorales = las 7 provincias (provincia 8 = exterior, no aplica)
const CIRCUNSCRIPCIONES = [
    1 => 'San José',
    2 => 'Alajuela',
    3 => 'Cartago',
    4 => 'Heredia',
    5 => 'Guanacaste',
    6 => 'Puntarenas',
    7 => 'Limón',
];

if ($dryRun) {
    out('[DRY-RUN] No se escribirá nada en la BD.');
    foreach (CIRCUNSCRIPCIONES as $provId => $name) {
        out("  Circunscripción #{$provId}: {$name}");
    }
    exit(0);
}

$pdo = dbData();

out('Verificando provinces...');
$existing = $pdo->query('SELECT id FROM provinces WHERE id BETWEEN 1 AND 7')
               ->fetchAll(PDO::FETCH_COLUMN);
if (count($existing) < 7) {
    err('Faltan provincias en la tabla provinces. Ejecutar primero: php scripts/import_distelec.php');
    exit(1);
}

out('Insertando circunscripciones electorales...');
$stmt = $pdo->prepare(
    'INSERT INTO electoral_districts (province_id, name)
     VALUES (:province_id, :name)
     ON DUPLICATE KEY UPDATE name = VALUES(name)'
);

$done = 0;
foreach (CIRCUNSCRIPCIONES as $provId => $name) {
    // Verificar si ya existe para esta provincia
    $exists = $pdo->prepare('SELECT id FROM electoral_districts WHERE province_id = ?');
    $exists->execute([$provId]);
    if ($exists->fetch()) {
        out("  Ya existe para provincia {$provId} ({$name}) — actualizando nombre.");
    }

    $stmt->execute([':province_id' => $provId, ':name' => $name]);
    out("  [OK] #{$provId} {$name}");
    $done++;
}

$total = (int)$pdo->query('SELECT COUNT(*) FROM electoral_districts')->fetchColumn();
out("Circunscripciones insertadas/actualizadas: {$done}");
out("Total en electoral_districts: {$total}");
out('Importación completada.');
exit(0);
