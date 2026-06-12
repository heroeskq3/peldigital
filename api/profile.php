<?php
require __DIR__ . '/../auth.php';
requerirLoginApi();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

// ── Actualizar nombre y correo ────────────────────────────────────────────
if ($action === 'update_info') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name === '' || $email === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Nombre y correo son requeridos.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Correo electrónico inválido.']);
        exit;
    }

    try {
        $pdo  = dbConnect();
        $dup  = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $dup->execute([$email, $_SESSION['user_id']]);
        if ($dup->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Ese correo ya está en uso por otra cuenta.']);
            exit;
        }
        $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')
            ->execute([$name, $email, $_SESSION['user_id']]);

        $_SESSION['usuario'] = $name;
        $_SESSION['email']   = $email;

        echo json_encode(['ok' => true, 'name' => $name, 'email' => $email]);
    } catch (Throwable) {
        http_response_code(500);
        echo json_encode(['error' => 'Error interno al actualizar el perfil.']);
    }
    exit;
}

// ── Cambiar contraseña ────────────────────────────────────────────────────
if ($action === 'change_password') {
    $current = $_POST['current'] ?? '';
    $new     = $_POST['new']     ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($new !== $confirm) {
        http_response_code(400);
        echo json_encode(['error' => 'Las contraseñas nuevas no coinciden.']);
        exit;
    }
    if (strlen($new) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'La nueva contraseña debe tener al menos 8 caracteres.']);
        exit;
    }

    try {
        $pdo  = dbConnect();
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($current, $row['password'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Contraseña actual incorrecta.']);
            exit;
        }
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);

        echo json_encode(['ok' => true]);
    } catch (Throwable) {
        http_response_code(500);
        echo json_encode(['error' => 'Error interno al cambiar la contraseña.']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no reconocida.']);
