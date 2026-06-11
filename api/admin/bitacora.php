<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../lib/db.php';
requerirAdminApi();

header('Content-Type: application/json; charset=utf-8');

$pdo    = dbConnect();
$page   = max(1, (int)($_GET['page'] ?? 1));
$size   = min(100, max(10, (int)($_GET['size'] ?? 50)));
$offset = ($page - 1) * $size;
$q      = trim($_GET['q'] ?? '');
$userId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
$action = trim($_GET['action_filter'] ?? '');

$where  = [];
$params = [];

if ($q !== '') {
    $where[]  = '(a.action LIKE ? OR a.description LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($userId !== null) {
    $where[]  = 'a.user_id = ?';
    $params[] = $userId;
}
if ($action !== '') {
    $where[]  = 'a.action = ?';
    $params[] = $action;
}

$wSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$stTotal = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a{$wSql}");
$stTotal->execute($params);
$total = (int)$stTotal->fetchColumn();

$stRows = $pdo->prepare(
    "SELECT a.id, a.action, a.description, a.ip_address, a.created_at,
            u.name AS user_name
     FROM audit_logs a
     LEFT JOIN users u ON u.id = a.user_id
     {$wSql}
     ORDER BY a.id DESC
     LIMIT {$size} OFFSET {$offset}"
);
$stRows->execute($params);
$rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

// Distinct actions for filter dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")
               ->fetchAll(PDO::FETCH_COLUMN);

// Distinct users for filter dropdown
$users = $pdo->query("SELECT DISTINCT u.id, u.name FROM audit_logs a
                       JOIN users u ON u.id = a.user_id ORDER BY u.name")
              ->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'rows'    => $rows,
    'total'   => $total,
    'pages'   => (int)ceil($total / $size),
    'page'    => $page,
    'actions' => $actions,
    'users'   => $users,
], JSON_UNESCAPED_UNICODE);
