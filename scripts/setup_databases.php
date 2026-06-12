#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * scripts/setup_databases.php
 *
 * Crea la base de datos peldigital_data y mueve todas las tablas
 * de datos electorales desde pel_electoral hacia ella.
 *
 * Tablas que quedan en pel_electoral (sistema):
 *   users, roles, permissions, role_permissions, settings,
 *   reports, report_categories, audit_logs, schema_migrations
 *
 * Tablas que se mueven a peldigital_data (datos):
 *   provinces, cantons, districts, electoral_districts, polling_places,
 *   voters, voter_enrichments, parties, election_results, election_sync_runs,
 *   padron_sync_runs, import_jobs, name_gender_lookup,
 *   summary_inscritos_provincia, summary_inscritos_canton,
 *   summary_inscritos_distrito, summary_jrv
 *
 * Uso:
 *   php scripts/setup_databases.php             # ejecuta la migración
 *   php scripts/setup_databases.php --dry-run   # solo muestra qué haría
 *   php scripts/setup_databases.php --verify    # verifica estado actual
 */

define('CLI_MODE', true);

$opts   = getopt('', ['dry-run', 'verify']);
$dryRun = isset($opts['dry-run']);
$verify = isset($opts['verify']);

function out(string $m): void { fwrite(STDOUT, '[' . date('H:i:s') . "] {$m}\n"); }
function err(string $m): void { fwrite(STDERR, '[ERROR] ' . $m . "\n"); }

require_once dirname(__DIR__) . '/lib/db.php';

// Tablas de datos que se mueven a peldigital_data
const DW_TABLES = [
    // Catálogo geográfico TSE
    'provinces',
    'cantons',
    'districts',
    'electoral_districts',
    'polling_places',
    // Padrón y enriquecimientos
    'voters',
    'voter_enrichments',
    'name_gender_lookup',
    // Resultados electorales
    'parties',
    'election_results',
    // Tracking ETL (pertenecen a los datos, no al sistema)
    'election_sync_runs',
    'padron_sync_runs',
    'import_jobs',
    // Tablas de resumen / DW layer
    'summary_inscritos_provincia',
    'summary_inscritos_canton',
    'summary_inscritos_distrito',
    'summary_jrv',
];

// Tablas del sistema que se quedan en pel_electoral
const SYS_TABLES = [
    'users', 'roles', 'permissions', 'role_permissions',
    'settings', 'reports', 'report_categories',
    'audit_logs', 'schema_migrations',
];

$sysDb = env('DB_NAME',  'pel_electoral');
$dwDb  = env('DW_NAME',  'peldigital_data');

// ─── VERIFY ──────────────────────────────────────────────────────────────────
if ($verify) {
    out("Verificando estado de las bases de datos...");
    out("  Sistema : {$sysDb}");
    out("  Datos   : {$dwDb}");

    $sys = dbConnect();

    // Tablas en sistema
    $sysTablesFound = $sys->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$sysDb}' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    out("\nTablas en {$sysDb} (" . count($sysTablesFound) . "):");
    foreach ($sysTablesFound as $t) out("  - {$t}");

    // Tablas en datos
    try {
        $dw = dbData();
        $dwTablesFound = $dw->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$dwDb}' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
        out("\nTablas en {$dwDb} (" . count($dwTablesFound) . "):");
        foreach ($dwTablesFound as $t) out("  - {$t}");
    } catch (Throwable $e) {
        out("\nBase de datos {$dwDb} no existe todavía.");
    }
    exit(0);
}

// ─── DRY-RUN / MIGRATE ───────────────────────────────────────────────────────
$sys = dbConnect();

// Verificar que las tablas de datos existen en pel_electoral
$existing = $sys->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$sysDb}'")->fetchAll(PDO::FETCH_COLUMN);
$existingSet = array_flip($existing);

$toMove   = [];
$notFound = [];
foreach (DW_TABLES as $t) {
    if (isset($existingSet[$t])) {
        $toMove[] = $t;
    } else {
        $notFound[] = $t;
    }
}

if (!empty($notFound)) {
    out("Las siguientes tablas no existen en {$sysDb} (ya fueron movidas o no existen):");
    foreach ($notFound as $t) out("  - {$t}");
}

out("Tablas a mover de {$sysDb} → {$dwDb}: " . count($toMove));
foreach ($toMove as $t) out("  → {$t}");

if (empty($toMove)) {
    out("Nada que mover. Verificando con --verify...");
    exit(0);
}

if ($dryRun) {
    out('[DRY-RUN] No se ejecutó ningún cambio.');
    exit(0);
}

// ─── Crear peldigital_data si no existe ───────────────────────────────────────
out("Creando base de datos {$dwDb} si no existe...");
$sys->exec("CREATE DATABASE IF NOT EXISTS `{$dwDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
out("  OK: {$dwDb} lista.");

// ─── Mover tablas con RENAME TABLE ───────────────────────────────────────────
// RENAME TABLE mueve la tabla sin copiar datos (operación O(1) para InnoDB).
// Con FK_CHECKS=0 se pueden mover aunque tengan foreign keys entre ellas.
// Una vez movidas todas al mismo schema, los FKs vuelven a ser válidos.

out("Desactivando foreign key checks...");
$sys->exec('SET FOREIGN_KEY_CHECKS = 0');

$moved  = 0;
$errors = 0;

foreach ($toMove as $table) {
    try {
        $sys->exec("RENAME TABLE `{$sysDb}`.`{$table}` TO `{$dwDb}`.`{$table}`");
        out("  [OK] {$table}");
        $moved++;
    } catch (Throwable $e) {
        err("  {$table}: " . $e->getMessage());
        $errors++;
    }
}

out("Reactivando foreign key checks...");
$sys->exec('SET FOREIGN_KEY_CHECKS = 1');

out("\nMovidas: {$moved}  |  Errores: {$errors}");

if ($errors === 0) {
    out("Migración completada exitosamente.");
    out("");
    out("Próximos pasos:");
    out("  1. Verificar: php scripts/setup_databases.php --verify");
    out("  2. El sistema usa automáticamente las dos BDs via lib/db.php");
} else {
    out("Hubo errores. Verificar con: php scripts/setup_databases.php --verify");
}

exit($errors > 0 ? 1 : 0);
