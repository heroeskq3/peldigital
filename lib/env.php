<?php
/**
 * lib/env.php — Cargador de variables de entorno desde .env
 * Sin dependencias externas. Se llama una sola vez desde lib/db.php.
 *
 * Formato soportado:
 *   KEY=value
 *   KEY="value with spaces"
 *   # comentario
 *   (líneas vacías ignoradas)
 */

function loadEnv(string $path): void
{
    if (!is_file($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;

        $eq = strpos($line, '=');
        if ($eq === false) continue;

        $key   = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));

        // Eliminar comillas envolventes
        if (strlen($value) >= 2
            && (($value[0] === '"' && $value[-1] === '"')
             || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

/** Lee una variable de entorno con valor por defecto. */
function env(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? getenv($key);
    return $v !== false ? (string)$v : $default;
}
