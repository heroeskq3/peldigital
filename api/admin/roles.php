<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../lib/db.php';
requerirLoginApi();

header('Content-Type: application/json; charset=utf-8');

$pdo = dbConnect();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query('SELECT r.id, r.name, r.description,
                         (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count
                         FROM roles r ORDER BY r.id')
                ->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id   = (int)($body['id'] ?? 0);
    $desc = trim($body['description'] ?? '');

    if ($id < 1) { http_response_code(400); echo json_encode(['error' => 'ID inválido']); exit; }

    $pdo->prepare('UPDATE roles SET description=? WHERE id=?')->execute([$desc, $id]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
