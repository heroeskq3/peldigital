<?php
/**
 * api/admin/explorador.php — Explorador de tablas del Data Warehouse.
 *
 * Modos (param GET ?modo=):
 *   tablas  → lista todas las tablas de peldigital_data con info_schema
 *   meta    → columnas + valores distintos (ENUMs, tinyint(1)) de una tabla
 *   datos   → filas paginadas con filtros dinámicos por columna
 *
 * Solo lectura — nunca escribe en BD.
 */
require __DIR__ . '/../../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/api.php';

apiJsonHeaders();
$pdo    = dbData();
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
$modo   = $_GET['modo'] ?? 'tablas';

// ── TABLAS ────────────────────────────────────────────────────────────────────
if ($modo === 'tablas') {
    $stmt = $pdo->prepare("
        SELECT TABLE_NAME    AS nombre,
               TABLE_ROWS    AS filas_aprox,
               ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS mb,
               ENGINE        AS motor,
               TABLE_COMMENT AS comentario,
               DATE_FORMAT(CREATE_TIME, '%Y-%m-%d %H:%i') AS creada,
               DATE_FORMAT(UPDATE_TIME, '%Y-%m-%d %H:%i') AS actualizada
        FROM   information_schema.TABLES
        WHERE  TABLE_SCHEMA = ?
        ORDER  BY TABLE_NAME
    ");
    $stmt->execute([$dbName]);
    apiJson(['tablas' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'db' => $dbName]);
}

// ── Validar nombre de tabla ───────────────────────────────────────────────────
$tabla = trim($_GET['tabla'] ?? '');
if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabla)) {
    http_response_code(400);
    apiJson(['error' => 'Nombre de tabla inválido']);
}
$chk = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?'
);
$chk->execute([$dbName, $tabla]);
if (!(int)$chk->fetchColumn()) {
    http_response_code(404);
    apiJson(['error' => 'Tabla no encontrada: ' . $tabla]);
}

$columnas = $pdo->query("SHOW FULL COLUMNS FROM `{$tabla}`")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($columnas, 'Field');

// ── META ──────────────────────────────────────────────────────────────────────
if ($modo === 'meta') {
    $distintos = [];
    foreach ($columnas as $col) {
        $tipo = strtolower($col['Type']);
        if (str_starts_with($tipo, 'enum(')) {
            preg_match_all("/'([^']+)'/", $tipo, $m);
            $distintos[$col['Field']] = array_values($m[1]);
        } elseif ($tipo === 'tinyint(1)') {
            $distintos[$col['Field']] = ['0' => 'No', '1' => 'Sí'];
        }
    }
    apiJson(['columnas' => $columnas, 'distintos' => $distintos]);
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
// Recolectar filtros: f[col]=valor
$filtros = $_GET['f'] ?? [];
if (!is_array($filtros)) $filtros = [];

$colTypeMap = array_column($columnas, 'Type', 'Field');
$where  = [];
$params = [];

foreach ($filtros as $col => $val) {
    $val = trim((string)$val);
    if ($val === '') continue;
    if (!in_array($col, $colNames, true)) continue; // solo columnas reales

    $tipo = strtolower($colTypeMap[$col] ?? '');

    if (str_starts_with($tipo, 'enum(')
        || str_contains($tipo, 'int')
        || str_contains($tipo, 'decimal')
        || str_contains($tipo, 'float')
        || str_contains($tipo, 'double')
    ) {
        $where[]  = "`{$col}` = ?";
        $params[] = $val;
    } elseif (str_starts_with($tipo, 'date') || str_starts_with($tipo, 'timestamp')) {
        $where[]  = "DATE(`{$col}`) = ?";
        $params[] = $val;
    } else {
        $where[]  = "`{$col}` LIKE ?";
        $params[] = '%' . $val . '%';
    }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Orden
$orderCol = trim($_GET['order_col'] ?? '');
$orderDir = ($_GET['order_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
if (!in_array($orderCol, $colNames, true)) {
    $orderCol = $colNames[0];
}
$orderSql = "ORDER BY `{$orderCol}` {$orderDir}";

// Paginación
$size = max(10, min(200, (int)($_GET['size'] ?? 50)));

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$tabla}` {$whereSql}");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();

$pages  = max(1, (int)ceil($total / $size));
$page   = max(1, min($pages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $size;

$dataStmt = $pdo->prepare(
    "SELECT * FROM `{$tabla}` {$whereSql} {$orderSql} LIMIT {$size} OFFSET {$offset}"
);
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

apiJson([
    'columnas' => $columnas,
    'rows'     => $rows,
    'total'    => $total,
    'page'     => $page,
    'size'     => $size,
    'pages'    => $pages,
    'tabla'    => $tabla,
    'db'       => $dbName,
    'order_col'=> $orderCol,
    'order_dir'=> strtolower($orderDir),
]);
