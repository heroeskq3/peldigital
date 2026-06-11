<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/api.php';
requerirLoginApi();

apiJsonHeaders();

$pdo    = dbConnect();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function jsonOk(array $data): never  { apiJson($data); }
function jsonErr(string $msg, int $code = 400): never { apiError($msg, $code); }

// ── GET: list ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $q      = trim($_GET['q'] ?? '');
    $roleId = isset($_GET['role_id']) && $_GET['role_id'] !== '' ? (int)$_GET['role_id'] : null;
    $pageInfo = apiPaginationFromRequest(25, 100);
    $page     = $pageInfo['page'];
    $size     = max(10, $pageInfo['size']);
    $offset   = ($page - 1) * $size;

    $where  = [];
    $params = [];

    if ($q !== '') {
        $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if ($roleId !== null) {
        $where[]  = 'u.role_id = ?';
        $params[] = $roleId;
    }

    $sql  = "SELECT u.id, u.name, u.email, u.active, u.created_at, r.id AS role_id, r.name AS role_name
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id"
          . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
          . ' ORDER BY u.id ASC';

    $stCount = $pdo->prepare("SELECT COUNT(*) FROM users u" . ($where ? ' WHERE ' . implode(' AND ', $where) : ''));
    $stCount->execute($params);
    $total = (int)$stCount->fetchColumn();
    $pages = max(1, (int)ceil($total / $size));
    $page = min($page, $pages);
    $offset = ($page - 1) * $size;

    $stRows = $pdo->prepare($sql . " LIMIT {$size} OFFSET {$offset}");
    $stRows->execute($params);
    $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

    jsonOk(['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page]);
}

// ── POST actions ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('Method not allowed', 405);

$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($action) {

    case 'create':
        $name    = trim($body['name'] ?? '');
        $email   = trim($body['email'] ?? '');
        $pass    = $body['password'] ?? '';
        $roleId  = (int)($body['role_id'] ?? 0);

        if ($name === '' || $email === '' || $pass === '' || $roleId < 1)
            jsonErr('Todos los campos son obligatorios');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            jsonErr('Email inválido');

        if (strlen($pass) < 6)
            jsonErr('La contraseña debe tener al menos 6 caracteres');

        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch()) jsonErr('Ya existe un usuario con ese email');

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $st   = $pdo->prepare('INSERT INTO users (name, email, password, role_id, active, created_at) VALUES (?,?,?,?,1,NOW())');
        $st->execute([$name, $email, $hash, $roleId]);
        jsonOk(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);

    case 'update':
        $id     = (int)($body['id'] ?? 0);
        $name   = trim($body['name'] ?? '');
        $email  = trim($body['email'] ?? '');
        $roleId = (int)($body['role_id'] ?? 0);
        $pass   = $body['password'] ?? '';

        if ($id < 1 || $name === '' || $email === '' || $roleId < 1)
            jsonErr('Datos incompletos');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            jsonErr('Email inválido');

        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $chk->execute([$email, $id]);
        if ($chk->fetch()) jsonErr('Ese email ya está en uso');

        if ($pass !== '') {
            if (strlen($pass) < 6) jsonErr('La contraseña debe tener al menos 6 caracteres');
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $st   = $pdo->prepare('UPDATE users SET name=?, email=?, password=?, role_id=?, updated_at=NOW() WHERE id=?');
            $st->execute([$name, $email, $hash, $roleId, $id]);
        } else {
            $st = $pdo->prepare('UPDATE users SET name=?, email=?, role_id=?, updated_at=NOW() WHERE id=?');
            $st->execute([$name, $email, $roleId, $id]);
        }
        jsonOk(['ok' => true]);

    case 'toggle':
        $id = (int)($body['id'] ?? 0);
        if ($id < 1) jsonErr('ID inválido');
        $st = $pdo->prepare('UPDATE users SET active = 1 - active, updated_at=NOW() WHERE id=?');
        $st->execute([$id]);
        $active = (int)$pdo->query("SELECT active FROM users WHERE id={$id}")->fetchColumn();
        jsonOk(['ok' => true, 'active' => $active]);

    case 'delete':
        $id = (int)($body['id'] ?? 0);
        if ($id < 1) jsonErr('ID inválido');
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        jsonOk(['ok' => true]);

    default:
        jsonErr('Acción desconocida');
}
