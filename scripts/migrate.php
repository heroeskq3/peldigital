#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * scripts/migrate.php — Runner de migraciones SQL.
 *
 * Lee todos los archivos *.sql de migrations/ en orden alfabético,
 * omite los ya registrados en schema_migrations y aplica los pendientes.
 *
 * Uso:
 *   php scripts/migrate.php            # aplica migraciones pendientes
 *   php scripts/migrate.php --dry-run  # muestra pendientes sin ejecutar
 */

require_once __DIR__ . '/../lib/db.php';

function out(string $m): void { fwrite(STDOUT, '[' . date('H:i:s') . "] {$m}\n"); }
function err(string $m): void { fwrite(STDERR, '[ERROR] ' . $m . "\n"); }

$opts   = getopt('', ['dry-run']);
$dryRun = isset($opts['dry-run']);

$migrationsDir = __DIR__ . '/../migrations';

// Recopilar archivos .sql ordenados
$archivos = glob($migrationsDir . '/*.sql');
if ($archivos === false || count($archivos) === 0) {
    out('No hay archivos de migración en migrations/.');
    exit(0);
}
sort($archivos);

$pdo = dbConnect();

// Cargar migraciones ya aplicadas
$aplicadas = $pdo
    ->query('SELECT migration FROM schema_migrations')
    ->fetchAll(PDO::FETCH_COLUMN);
$aplicadas = array_flip($aplicadas);

$pendientes = [];
foreach ($archivos as $path) {
    $nombre = basename($path);
    if (!isset($aplicadas[$nombre])) {
        $pendientes[] = $path;
    }
}

if (count($pendientes) === 0) {
    out('Base de datos al día. No hay migraciones pendientes.');
    exit(0);
}

out(count($pendientes) . ' migración(es) pendiente(s):');
foreach ($pendientes as $path) {
    out('  → ' . basename($path));
}

if ($dryRun) {
    out('Modo --dry-run: nada fue ejecutado.');
    exit(0);
}

// MySQL/MariaDB no soporta DDL transaccional (ALTER TABLE, CREATE INDEX, etc.
// causan commit implícito). El runner ejecuta cada sentencia directamente y
// registra la migración en schema_migrations al terminar con éxito.
$ok     = 0;
$fallos = 0;

foreach ($pendientes as $path) {
    $nombre = basename($path);
    $sql    = file_get_contents($path);

    if ($sql === false || trim($sql) === '') {
        err("No se pudo leer o está vacío: {$nombre}");
        $fallos++;
        continue;
    }

    out("Aplicando {$nombre}...");

    try {
        // Dividir en sentencias individuales y omitir las que solo tienen comentarios
        $sentencias = array_filter(
            array_map('trim', explode(';', $sql)),
            static function (string $s): bool {
                return trim(preg_replace('/--[^\n]*/m', '', $s)) !== '';
            }
        );

        foreach ($sentencias as $sentencia) {
            $pdo->exec($sentencia);
        }

        $pdo->prepare('INSERT INTO schema_migrations (migration, executed_at) VALUES (?, NOW())')
            ->execute([$nombre]);

        out("  OK: {$nombre}");
        $ok++;
    } catch (Throwable $e) {
        err("Falló {$nombre}: " . $e->getMessage());
        $fallos++;
        // Detener en el primer fallo para no dejar la BD en estado parcial
        break;
    }
}

out("Resultado: {$ok} aplicada(s), {$fallos} fallida(s).");
exit($fallos > 0 ? 1 : 0);
