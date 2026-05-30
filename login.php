<?php
require __DIR__ . '/auth.php';

// Si ya hay sesion, al tablero.
if (estaAutenticado()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $clave   = $_POST['clave'] ?? '';
    if (verificarLogin($usuario, $clave)) {
        iniciarSesion(trim($usuario));
        header('Location: index.php');
        exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#150857">
    <title>Ingresar · PEL Digital</title>

    <script>
        (function () {
            var t = localStorage.getItem("cr-theme");
            if (!t) t = matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            document.documentElement.setAttribute("data-theme", t);
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>" rel="stylesheet">
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
                <input type="password" name="clave" class="field" required>
            </label>
            <button type="submit" class="btn-wide"><i class="bi bi-box-arrow-in-right"></i> Ingresar</button>
        </form>
    </main>
</body>
</html>
