<?php
/**
 * api/padron.php — consulta paginada del padrón real por región.
 */

require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';

$pdo = dbData();

$nivel = (string)($_GET['nivel'] ?? '');
$codigo = trim((string)($_GET['codigo'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$size = max(10, min(200, (int)($_GET['size'] ?? 25)));
$offset = ($page - 1) * $size;

$where = [];
$params = [];
$orderSql = 'v.id';
$searchActive = false;

switch ($nivel) {
    case 'pais':
        if ($codigo !== 'CR') {
            http_response_code(400);
            echo json_encode(['error' => 'País no soportado']);
            exit;
        }
        $where[] = 'v.province_id BETWEEN 1 AND 7';
        $orderSql = 'v.id';
        break;

    case 'provincia':
        $where[] = 'v.province_id = :province_id';
        $params[':province_id'] = (int)$codigo;
        break;

    case 'canton':
        $where[] = 'v.canton_id = :canton_id';
        $params[':canton_id'] = (int)$codigo;
        break;

    case 'distrito':
        if (!preg_match('/^\d{5}$/', $codigo)) {
            http_response_code(400);
            echo json_encode(['error' => 'Código de distrito inválido']);
            exit;
        }
        $codelec = substr($codigo, 0, 3) . str_pad(substr($codigo, 3), 3, '0', STR_PAD_LEFT);
        $distStmt = $pdo->prepare('SELECT id FROM districts WHERE codelec = ? LIMIT 1');
        $distStmt->execute([$codelec]);
        $districtId = $distStmt->fetchColumn();
        if (!$districtId) {
            http_response_code(404);
            echo json_encode(['error' => 'Distrito no encontrado']);
            exit;
        }
        $where[] = 'v.district_id = :district_id';
        $params[':district_id'] = (int)$districtId;
        break;

    case 'diaspora':
        $where[] = 'v.province_id = 8';
        if ($codigo !== 'ext:Exterior') {
            if (strpos($codigo, 'ext:') !== 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Código de diáspora inválido']);
                exit;
            }
            $paisStmt = $pdo->prepare('SELECT id FROM cantons WHERE province_id = 8 AND name = ? LIMIT 1');
            $paisStmt->execute([substr($codigo, 4)]);
            $paisId = $paisStmt->fetchColumn();
            if (!$paisId) {
                http_response_code(404);
                echo json_encode(['error' => 'País no encontrado']);
                exit;
            }
            $where[] = 'v.canton_id = :pais_id';
            $params[':pais_id'] = (int)$paisId;
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Nivel inválido']);
        exit;
}

if ($q !== '') {
    if (preg_match('/\d/', $q)) {
        $searchActive = true;
        $digits = preg_replace('/[^\d]/', '', $q);
        $where[] = '(v.cedula LIKE :q_cedula OR v.junta = :q_junta)';
        $params[':q_cedula'] = $digits . '%';
        $params[':q_junta'] = str_pad($digits, 5, '0', STR_PAD_LEFT);
    } else {
        $terms = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
        $terms = array_slice(array_filter($terms, static fn($t) => mb_strlen($t) >= 3), 0, 5);
        if ($terms) {
            $searchActive = true;
            $boolean = implode(' ', array_map(static fn($t) => '+' . $t . '*', $terms));
            $where[] = 'MATCH(v.nombre, v.apellido1, v.apellido2) AGAINST(:q_text IN BOOLEAN MODE)';
            $params[':q_text'] = $boolean;
        }
    }
}

$whereSql = implode(' AND ', $where);
$fromSql = "FROM voters v WHERE {$whereSql}";

$estimated = false;
$limit = $size;

if ($searchActive) {
    $total = 0;
    $pages = $page;
    $limit = $size + 1;
} else {
    $countStmt = $pdo->prepare("SELECT COUNT(*) {$fromSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $size));
    if ($page > $pages) {
        $page = $pages;
        $offset = ($page - 1) * $size;
    }
}

$sql = "
    SELECT
        page.cedula,
        page.nombre,
        page.apellido1,
        page.apellido2,
        page.fecha_caduc,
        page.junta,
        p.name AS provincia,
        c.name AS canton,
        d.name AS distrito
    FROM (
        SELECT
            v.cedula,
            v.nombre,
            v.apellido1,
            v.apellido2,
            v.fecha_caduc,
            v.junta,
            v.province_id,
            v.canton_id,
            v.district_id
        {$fromSql}
        ORDER BY {$orderSql}
        LIMIT {$limit} OFFSET {$offset}
    ) page
    LEFT JOIN provinces p ON p.id = page.province_id
    LEFT JOIN cantons c ON c.id = page.canton_id
    LEFT JOIN districts d ON d.id = page.district_id
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($searchActive) {
    $hasMore = count($rows) > $size;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $size);
    }
    $total = $offset + count($rows) + ($hasMore ? 1 : 0);
    $pages = $page + ($hasMore ? 1 : 0);
    $estimated = $hasMore;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'rows' => $rows,
    'total' => $total,
    'page' => $page,
    'size' => $size,
    'pages' => $pages,
    'estimated' => $estimated,
], JSON_UNESCAPED_UNICODE);
