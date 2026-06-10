<?php
/**
 * lib/db.php — Conexión PDO a la base de datos del padrón electoral.
 * Singleton: una sola conexión por request.
 * Credenciales leídas desde .env (ver .env.example).
 */

require_once __DIR__ . '/env.php';
loadEnv(__DIR__ . '/../.env');

function dbConnect(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        env('DB_NAME', 'pel_electoral')
    );

    $pdo = new PDO(
        $dsn,
        env('DB_USER', 'root'),
        env('DB_PASS', ''),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    return $pdo;
}
