<?php
/**
 * Autenticacion minima por sesion para el tablero.
 *
 * Usuario demo:
 *   usuario:    demo
 *   contrasena: demo1234
 *
 * Las contrasenas se guardan como hash bcrypt (password_hash) y se
 * verifican con password_verify. Para agregar usuarios, añade otra
 * entrada al arreglo $USUARIOS con su propio hash.
 */

session_start();

// Bitácora de auditoría (registro de accesos e interacciones).
require_once __DIR__ . '/lib/bitacora.php';

// usuario => hash bcrypt de la contrasena
$USUARIOS = [
    'demo' => '$2y$12$EcFM4j2oK1pv3HSdhg9F5eeRVML.MXVzhiH9c7IJL1Y6gWYGTJYvW', // demo1234
];

/** Verifica credenciales; devuelve true si son validas. */
function verificarLogin(string $usuario, string $contrasena): bool
{
    global $USUARIOS;
    $usuario = trim($usuario);
    if (!isset($USUARIOS[$usuario])) {
        // Igual ejecuta un verify para no filtrar usuarios por timing.
        password_verify($contrasena, '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinv');
        return false;
    }
    return password_verify($contrasena, $USUARIOS[$usuario]);
}

/** Marca la sesion como autenticada. */
function iniciarSesion(string $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['auth'] = true;
    $_SESSION['usuario'] = $usuario;
}

/** Cierra la sesion actual. */
function cerrarSesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** ¿Hay sesion activa? */
function estaAutenticado(): bool
{
    return !empty($_SESSION['auth']);
}

/** Usuario en sesion (o null). */
function usuarioActual(): ?string
{
    return $_SESSION['usuario'] ?? null;
}

/** Exige login en una pagina; si no, redirige al login. */
function requerirLogin(): void
{
    if (!estaAutenticado()) {
        header('Location: login.php');
        exit;
    }
}

/** Exige login en un endpoint de API; si no, responde 401 JSON. */
function requerirLoginApi(): void
{
    if (!estaAutenticado()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
}
