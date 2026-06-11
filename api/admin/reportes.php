<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../lib/db.php';
requerirLoginApi();

header('Content-Type: application/json');
$pdo    = dbConnect();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: lista categorías con sus reportes ────────────────────────────────────
if ($method === 'GET') {
    $cats = $pdo->query("
        SELECT id, name, slug, icon, sort_order
        FROM report_categories ORDER BY sort_order
    ")->fetchAll(PDO::FETCH_ASSOC);

    $reps = $pdo->query("
        SELECT id, category_id, name, short_name, slug, icon, status, sort_order, php_file
        FROM reports ORDER BY category_id, sort_order
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['categories' => $cats, 'reports' => $reps]);
    exit;
}

// ── POST: acciones ────────────────────────────────────────────────────────────
if ($method !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Método no permitido']); exit; }

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

switch ($action) {

    // ── Categorías ────────────────────────────────────────────────────────────
    case 'cat_create': {
        $name  = trim($body['name'] ?? '');
        $icon  = trim($body['icon'] ?? 'bi-folder');
        $sort  = (int)($body['sort_order'] ?? 99);
        if (!$name) { http_response_code(422); echo json_encode(['error'=>'Nombre requerido']); exit; }
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
        $st = $pdo->prepare("INSERT INTO report_categories (name,slug,icon,sort_order) VALUES (?,?,?,?)");
        $st->execute([$name, $slug, $icon, $sort]);
        echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId()]);
        break;
    }

    case 'cat_update': {
        $id   = (int)($body['id'] ?? 0);
        $name = trim($body['name'] ?? '');
        $icon = trim($body['icon'] ?? '');
        $sort = (int)($body['sort_order'] ?? 0);
        if (!$id || !$name) { http_response_code(422); echo json_encode(['error'=>'Datos incompletos']); exit; }
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
        $pdo->prepare("UPDATE report_categories SET name=?,slug=?,icon=?,sort_order=? WHERE id=?")
            ->execute([$name, $slug, $icon, $sort, $id]);
        echo json_encode(['ok'=>true]);
        break;
    }

    case 'cat_delete': {
        $id = (int)($body['id'] ?? 0);
        $count = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE category_id=?");
        $count->execute([$id]);
        if ($count->fetchColumn() > 0) {
            http_response_code(422);
            echo json_encode(['error'=>'La categoría tiene reportes asignados — reasignarlos primero']);
            exit;
        }
        $pdo->prepare("DELETE FROM report_categories WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
        break;
    }

    // ── Reportes ──────────────────────────────────────────────────────────────
    case 'rep_update': {
        $id       = (int)($body['id'] ?? 0);
        $catId    = (int)($body['category_id'] ?? 0);
        $name     = trim($body['name'] ?? '');
        $shortName= trim($body['short_name'] ?? '');
        $icon     = trim($body['icon'] ?? '');
        $status   = $body['status'] ?? 'active';
        $sort     = (int)($body['sort_order'] ?? 0);
        if (!$id || !$name || !$catId) { http_response_code(422); echo json_encode(['error'=>'Datos incompletos']); exit; }
        if (!in_array($status, ['active','partial','pending'])) $status = 'pending';
        $pdo->prepare("UPDATE reports SET category_id=?,name=?,short_name=?,icon=?,status=?,sort_order=? WHERE id=?")
            ->execute([$catId, $name, $shortName, $icon, $status, $sort, $id]);
        echo json_encode(['ok'=>true]);
        break;
    }

    case 'rep_status': {
        $id     = (int)($body['id'] ?? 0);
        $status = $body['status'] ?? 'active';
        if (!in_array($status, ['active','partial','pending'])) { http_response_code(422); echo json_encode(['error'=>'Estado inválido']); exit; }
        $pdo->prepare("UPDATE reports SET status=? WHERE id=?")->execute([$status, $id]);
        echo json_encode(['ok'=>true]);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['error'=>'Acción desconocida']);
}
