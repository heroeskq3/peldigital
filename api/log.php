<?php
/**
 * Endpoint para registrar interacciones del frontend en la bitácora.
 *
 * Recibe POST con JSON { tipo, detalle, meta } (o form-urlencoded vía
 * navigator.sendBeacon). Requiere sesión activa. Responde 204 sin cuerpo.
 */

require __DIR__ . '/../auth.php';
requerirLoginApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// sendBeacon puede mandar el cuerpo como texto JSON.
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST; // respaldo: form-urlencoded
}

$tipo    = trim((string) ($data['tipo'] ?? ''));
$detalle = trim((string) ($data['detalle'] ?? ''));
$meta    = (isset($data['meta']) && is_array($data['meta'])) ? $data['meta'] : [];

if ($tipo === '') {
    http_response_code(400);
    exit;
}

// Lista blanca de tipos aceptados desde el cliente (evita ruido/inyección).
$permitidos = [
    'navegacion', 'metrica', 'analisis', 'busqueda',
    'padron_abrir', 'padron_export', 'admin_open', 'reset', 'tema',
];
if (!in_array($tipo, $permitidos, true)) {
    $tipo = 'otro';
}

// Recorta longitudes para no inflar el log.
$detalle = mb_substr($detalle, 0, 400);

registrarBitacora($tipo, $detalle, $meta);

http_response_code(204);
