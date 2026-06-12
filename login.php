<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/env.php';

// Si ya hay sesion, al tablero.
if (estaAutenticado()) {
    header('Location: ' . appUrl('home'));
    exit;
}

$RECAPTCHA_SITE_KEY = env('RECAPTCHA_SITE_KEY');
$RECAPTCHA_SECRET   = env('RECAPTCHA_SECRET');

/** Verifica el token reCAPTCHA con la API de Google. */
function verificarRecaptcha(string $token, string $secret): bool
{
    if ($token === '') return false;
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query(['secret' => $secret, 'response' => $token]),
        'timeout' => 5,
    ]]);
    $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    if (!$raw) return false;
    $data = json_decode($raw, true);
    return !empty($data['success']);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario        = $_POST['usuario'] ?? '';
    $clave          = $_POST['clave'] ?? '';
    $recordar       = !empty($_POST['recordar']);
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';

    if (!verificarRecaptcha($recaptchaToken, $RECAPTCHA_SECRET)) {
        $error = 'Por favor confirma que no eres un robot.';
    } elseif (verificarLogin($usuario, $clave, $recordar)) {
        iniciarSesion(trim($usuario));
        registrarBitacora('login', 'Ingreso correcto');
        header('Location: ' . appUrl('home'));
        exit;
    } else {
        registrarBitacora('login_fallido', 'Credenciales inválidas', [], trim($usuario) ?: 'desconocido');
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#150857">
    <title>Ingresar · PEL Digital</title>

    <script>
        (function () {
            var t = localStorage.getItem("cr-theme");
            if (!t) t = matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            document.documentElement.setAttribute("data-theme", t);
        })();
    </script>

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <?php
    $loginCss = [
        'assets/css/app/tokens.css',
        'assets/css/app/nav.css',
        'assets/css/app/layout.css',
        'assets/css/app/modals.css',
        'assets/css/app/responsive.css',
        'assets/css/app/reports.css',
        'assets/css/app/admin.css',
    ];
    ?>
    <?php foreach ($loginCss as $css): ?>
    <link href="<?= htmlspecialchars($css) ?>?v=<?= filemtime(__DIR__ . '/' . $css) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body class="login-body">
    <main class="login-card">
        <div class="login-brand">
            <img src="assets/img/logo02.png" class="brand-logo" alt="Esperanza y Libertad">
            <span class="brand-title">PEL Digital</span>
        </div>
        <p class="login-sub muted small">Acceso al tablero de análisis</p>

        <?php if ($error): ?>
            <div class="login-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="login-form" autocomplete="off">
            <label class="login-label">Usuario
                <input type="text" name="usuario" class="field" required autofocus
                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
            </label>
            <label class="login-label">Contraseña
                <div class="field-pw">
                    <input type="password" name="clave" id="campo-clave" class="field" required>
                    <button type="button" class="field-pw-toggle" aria-label="Mostrar contraseña"
                            onclick="(function(){var i=document.getElementById('campo-clave'),e=document.getElementById('pw-ico');i.type=i.type==='password'?'text':'password';e.className=i.type==='password'?'bi bi-eye':'bi bi-eye-slash';})()">
                        <i class="bi bi-eye" id="pw-ico"></i>
                    </button>
                </div>
            </label>
            <label class="login-remember">
                <input type="checkbox" name="recordar" value="1" <?= !empty($_POST['recordar']) ? 'checked' : '' ?>>
                Mantener sesión iniciada
            </label>
            <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($RECAPTCHA_SITE_KEY) ?>" style="margin-bottom:.75rem;"></div>
            <button type="submit" class="btn-wide"><i class="bi bi-box-arrow-in-right"></i> Ingresar</button>
        </form>
    </main>
</body>
</html>
