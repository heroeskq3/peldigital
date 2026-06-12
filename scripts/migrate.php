#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * scripts/migrate.php — Runner de migraciones SQL.
 *
 * Lee todos los archivos *.sql del directorio de migraciones en orden
 * alfabético, omite los ya registrados en schema_migrations y aplica los
 * pendientes.
 *
 * Uso:
 *   php scripts/migrate.php                  # pel_electoral  ← migrations/
 *   php scripts/migrate.php --db=data        # peldigital_data ← migrations/data/
 *   php scripts/migrate.php --dry-run        # muestra pendientes sin ejecutar
 *   php scripts/migrate.php --db=data --dry-run
 */

require_once __DIR__ . '/../lib/db.php';

function out(string $m): void { fwrite(STDOUT, '[' . date('H:i:s') . "] {$m}\n"); }
function err(string $m): void { fwrite(STDERR, '[ERROR] ' . $m . "\n"); }

$opts   = getopt('', ['dry-run', 'db:']);
$dryRun = isset($opts['dry-run']);
$dbFlag = $opts['db'] ?? 'system';   // 'system' | 'data'

if (!in_array($dbFlag, ['system', 'data'], true)) {
    err("--db debe ser 'system' o 'data'. Recibido: '{$dbFlag}'");
    exit(1);
}

$isData = ($dbFlag === 'data');

$migrationsDir = $isData
    ? __DIR__ . '/../migrations/data'
    : __DIR__ . '/../migrations';

// Recopilar archivos .sql ordenados
$archivos = glob($migrationsDir . '/*.sql');
if ($archivos === false || count($archivos) === 0) {
    out("No hay archivos de migración en {$migrationsDir}.");
    exit(0);
}
sort($archivos);

$pdo = $isData ? dbData() : dbConnect();

out('Base de datos: ' . $pdo->query('SELECT DATABASE()')->fetchColumn());

// Asegurar que schema_migrations exista en la BD objetivo (necesario en peldigital_data)
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    migration     VARCHAR(255) NOT NULL UNIQUE,
    executed_at   DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Cargar migraciones ya aplicadas
$stmtAplicadas = $pdo->query('SELECT migration FROM schema_migrations');
$aplicadas = $stmtAplicadas->fetchAll(PDO::FETCH_COLUMN);
$stmtAplicadas->closeCursor();

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
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $sql = preg_replace('/^\s*#.*$/m', '', $sql);

        $sentencias = array_filter(
            array_map('trim', explode(';', $sql)),
            static function (string $s): bool {
                return $s !== '';
            }
        );

        foreach ($sentencias as $sentencia) {
            $stmt = $pdo->query($sentencia);
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }
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
