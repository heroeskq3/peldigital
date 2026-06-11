<?php
/**
 * auth.php — Autenticación por sesión contra la tabla `users`.
 *
 * Login: campo "usuario" acepta email O nombre de usuario.
 * Fallback local: usuario `demo` / `demo1234` solo fuera de producción.
 */
session_start();

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/bitacora.php';

/** Verifica credenciales contra la BD; devuelve array del usuario o false. */
function verificarLoginDB(string $usuario, string $contrasena): array|false
{
    try {
        $pdo  = dbConnect();
        $stmt = $pdo->prepare(
            'SELECT id, name, email, password, role_id, active
             FROM users
             WHERE (email = ? OR name = ?) AND active = 1
             LIMIT 1'
        );
        $stmt->execute([$usuario, $usuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            password_verify($contrasena, '$2y$12$invalidinvalidinvalidinvalid');
            return false;
        }
        if (!password_verify($contrasena, $row['password'])) return false;
        return $row;
    } catch (Throwable) {
        return false;
    }
}

/** Verifica credenciales; devuelve true si son válidas. */
function verificarLogin(string $usuario, string $contrasena): bool
{
    $usuario = trim($usuario);

    // 1. Intentar contra la BD
    $dbUser = verificarLoginDB($usuario, $contrasena);
    if ($dbUser) {
        iniciarSesionConUsuario($dbUser);
        return true;
    }

    // 2. Fallback local (usuario demo). Nunca se habilita en producción.
    if (env('APP_ENV', 'development') === 'production') {
        password_verify($contrasena, '$2y$12$invalidinvalidinvalidinvalid');
        return false;
    }

    static $FALLBACK = [
        'demo' => '$2y$12$EcFM4j2oK1pv3HSdhg9F5eeRVML.MXVzhiH9c7IJL1Y6gWYGTJYvW',
    ];
    if (!isset($FALLBACK[$usuario])) {
        password_verify($contrasena, '$2y$12$invalidinvalidinvalidinvalid');
        return false;
    }
    if (!password_verify($contrasena, $FALLBACK[$usuario])) return false;
    // Iniciar sesión con datos mínimos para el fallback
    iniciarSesionConUsuario(['id' => 0, 'name' => 'Demo', 'email' => 'demo', 'role_id' => 1]);
    return true;
}

/** Inicia sesión a partir de un array de usuario. */
function iniciarSesionConUsuario(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['auth']     = true;
    $_SESSION['usuario']  = $user['name'] ?? $user['email'] ?? 'demo';
    $_SESSION['user_id']  = $user['id']      ?? 0;
    $_SESSION['role_id']  = $user['role_id'] ?? 1;
    $_SESSION['email']    = $user['email']   ?? '';
}

/** Inicia sesión (compatibilidad — solo nombre de usuario). */
function iniciarSesion(string $usuario): void
{
    // Esta función se llama desde login.php tras verificarLogin() exitoso.
    // El estado ya fue establecido por iniciarSesionConUsuario(); solo
    // garantizamos que el campo 'usuario' quede sincronizado.
    if (empty($_SESSION['auth'])) {
        session_regenerate_id(true);
        $_SESSION['auth']    = true;
        $_SESSION['usuario'] = $usuario;
    }
}

/** Cierra la sesión actual. */
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

/** ¿Hay sesión activa? */
function estaAutenticado(): bool
{
    return !empty($_SESSION['auth']);
}

/** Usuario en sesión (o null). */
function usuarioActual(): ?string
{
    return $_SESSION['usuario'] ?? null;
}

/** Exige login en una página; si no, redirige. */
function requerirLogin(): void
{
    if (!estaAutenticado()) {
        header('Location: login.php');
        exit;
    }
}

/** Exige login en un endpoint de API; si no, responde 401. */
function requerirLoginApi(): void
{
    if (!estaAutenticado()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
}
