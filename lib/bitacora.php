<?php
/**
 * Bitácora de auditoría.
 *
 * Registra accesos e interacciones en un archivo JSON Lines (un evento
 * por línea) en data/bitacora.log. Cada evento incluye marca de tiempo,
 * usuario, IP, agente de usuario, tipo y detalle.
 *
 * El archivo .log está excluido por .gitignore (no se versiona).
 */

if (!defined('BITACORA_ARCHIVO')) {
    define('BITACORA_ARCHIVO', __DIR__ . '/../data/bitacora.log');
}

/** IP del cliente (respeta proxy si viene X-Forwarded-For). */
function bitacoraIP(): string
{
    $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($fwd !== '') {
        $partes = explode(',', $fwd);
        return trim($partes[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * Registra un evento en la bitácora.
 *
 * @param string      $tipo    Categoría corta (login, navegacion, metrica…).
 * @param string|null $detalle Descripción legible.
 * @param array       $meta    Datos extra estructurados (opcional).
 * @param string|null $usuario Forzar usuario (p.ej. login fallido); por
 *                             defecto toma el de la sesión activa.
 */
function registrarBitacora(string $tipo, ?string $detalle = null, array $meta = [], ?string $usuario = null): void
{
    $evento = [
        'ts'      => date('c'),
        'usuario' => $usuario ?? (function_exists('usuarioActual') ? (usuarioActual() ?? 'anónimo') : 'anónimo'),
        'ip'      => bitacoraIP(),
        'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
        'tipo'    => $tipo,
        'detalle' => $detalle ?? '',
    ];
    if ($meta) {
        $evento['meta'] = $meta;
    }

    $linea = json_encode($evento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

    $dir = dirname(BITACORA_ARCHIVO);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents(BITACORA_ARCHIVO, $linea, FILE_APPEND | LOCK_EX);
}

/**
 * Lee los últimos eventos de la bitácora (más recientes primero).
 *
 * @param int    $limite Máximo de eventos a devolver.
 * @param string $q      Filtro de subcadena (insensible a mayúsculas) sobre
 *                       usuario, tipo, ip o detalle.
 * @return array Lista de eventos decodificados.
 */
function leerBitacora(int $limite = 200, string $q = ''): array
{
    if (!is_file(BITACORA_ARCHIVO)) {
        return [];
    }
    $raw = file(BITACORA_ARCHIVO, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($raw === false) {
        return [];
    }

    $q = trim(mb_strtolower($q));
    $eventos = [];
    // Recorre de la más reciente a la más antigua.
    for ($i = count($raw) - 1; $i >= 0 && count($eventos) < $limite; $i--) {
        $ev = json_decode($raw[$i], true);
        if (!is_array($ev)) {
            continue;
        }
        if ($q !== '') {
            $heno = mb_strtolower(
                ($ev['usuario'] ?? '') . ' ' . ($ev['tipo'] ?? '') . ' ' .
                ($ev['ip'] ?? '') . ' ' . ($ev['detalle'] ?? '')
            );
            if (mb_strpos($heno, $q) === false) {
                continue;
            }
        }
        $eventos[] = $ev;
    }
    return $eventos;
}
