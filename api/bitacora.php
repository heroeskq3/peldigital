<?php
/**
 * Devuelve los últimos eventos de la bitácora (JSON), más recientes primero.
 * Parámetros opcionales: ?n=200 (límite), ?q=texto (filtro).
 */

require __DIR__ . '/../auth.php';
requerirLoginApi();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$n = isset($_GET['n']) ? max(1, min(1000, (int) $_GET['n'])) : 200;
$q = isset($_GET['q']) ? (string) $_GET['q'] : '';

$eventos = leerBitacora($n, $q);

echo json_encode([
    'eventos' => $eventos,
    'total'   => count($eventos),
], JSON_UNESCAPED_UNICODE);
