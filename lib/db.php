<?php
/**
 * lib/db.php — Conexiones PDO al sistema de dos bases de datos.
 *
 * dbConnect()  →  pel_electoral      (sistema: users, roles, reports, audit_logs…)
 * dbData()     →  peldigital_data    (datos:   voters, provinces, election_results…)
 *
 * Ambas son singletons: una sola conexión por request/proceso.
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

    $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function dbData(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DW_HOST', env('DB_HOST', '127.0.0.1')),
        env('DW_PORT', env('DB_PORT', '3306')),
        env('DW_NAME', 'peldigital_data')
    );

    $pdo = new PDO($dsn, env('DW_USER', env('DB_USER', 'root')), env('DW_PASS', env('DB_PASS', '')), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
