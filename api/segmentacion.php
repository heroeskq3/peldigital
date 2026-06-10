<?php
/**
 * api/segmentacion.php — Distribución del padrón por territorio.
 *
 * Usa tablas de resumen pre-agregadas (summary_inscritos_*) para paginación
 * SQL real con LIMIT/OFFSET. Sin scan sobre los 3.7M filas de voters.
 *
 * Params GET:
 *   nivel       province|canton|district  (default: province)
 *   province_id int   filtra por provincia
 *   canton_id   int   filtra por cantón
 *   page        int   página (default 1)
 *   size        int   filas por página (10-200, default 25)
 *   q           str   búsqueda por nombre
 *   order       desc|asc  (default desc)
 *   format      json|csv  (default json)
 */
require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';

$format = ($_GET['format'] ?? 'json') === 'csv' ? 'csv' : 'json';
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

$pdo = dbConnect();

$nivel      = in_array($_GET['nivel'] ?? '', ['province','canton','district']) ? $_GET['nivel'] : 'province';
$provinceId = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
$cantonId   = isset($_GET['canton_id'])   && $_GET['canton_id']   !== '' ? (int)$_GET['canton_id']   : null;
$page       = max(1, (int)($_GET['page']  ?? 1));
$size       = max(10, min(200, (int)($_GET['size'] ?? 25)));
$order      = ($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$q          = trim((string)($_GET['q'] ?? ''));

// Seleccionar tabla y columnas según nivel
switch ($nivel) {
    case 'province':
        $table   = 'summary_inscritos_provincia';
        $idCol   = 'province_id';
        $nameSql = 'nombre';
        $extraSel = '';
        $extraCols = [];
        break;
    case 'canton':
        $table   = 'summary_inscritos_canton';
        $idCol   = 'canton_id';
        $nameSql = 'nombre';
        $extraSel = ', provincia';
        $extraCols = ['provincia'];
        break;
    default: // district
        $table   = 'summary_inscritos_distrito';
        // fall through
        $idCol   = 'district_id';
        $nameSql = 'nombre';
        $extraSel = ', canton, provincia, geo5';
        $extraCols = ['canton','provincia','geo5'];
        break;
}

// ─── WHERE ────────────────────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($provinceId) {
    $where[]  = 'province_id = ?';
    $params[] = $provinceId;
}
if ($cantonId && $nivel === 'district') {
    $where[]  = 'canton_id = ?';
    $params[] = $cantonId;
}
if ($q !== '') {
    $qLike    = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
    $where[]  = 'nombre LIKE ?';
    $params[] = $qLike;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderSql = "ORDER BY inscritos {$order}";

// ─── Total y Grand Total ──────────────────────────────────────────────────────
$cntStmt = $pdo->prepare("SELECT COUNT(*), SUM(inscritos) FROM {$table} {$whereSql}");
$cntStmt->execute($params);
[$total, $grandTotal] = $cntStmt->fetch(PDO::FETCH_NUM);
$total      = (int)$total;
$grandTotal = (int)$grandTotal ?: 1;

$pages = max(1, (int)ceil($total / $size));
$page  = min($page, $pages);

// ─── Grand total nacional para % ─────────────────────────────────────────────
$natRow   = $pdo->query("SELECT SUM(inscritos), SUM(inscritos_m), SUM(inscritos_f), SUM(inscritos_n) FROM {$table}")->fetch(PDO::FETCH_NUM);
$natTotal = (int)$natRow[0] ?: 1;
$natM     = (int)$natRow[1];
$natF     = (int)$natRow[2];
$natN     = (int)$natRow[3];

// ─── Stats ────────────────────────────────────────────────────────────────────
$statsRow = $pdo->prepare("SELECT MAX(inscritos), MIN(inscritos), SUM(inscritos), COUNT(*) FROM {$table} {$whereSql}");
$statsRow->execute($params);
[$maxInsc, $minInsc, $sumInsc, $cnt] = $statsRow->fetch(PDO::FETCH_NUM);
$promedio = $cnt > 0 ? (int)round((int)$sumInsc / (int)$cnt) : 0;

// ─── CSV: devolver todo (hasta 5 000 filas), sin paginar ─────────────────────
if ($format === 'csv') {
    $csvSql = "SELECT {$idCol} AS id, {$nameSql} AS nombre{$extraSel}, inscritos, inscritos_m, inscritos_f, inscritos_n, pct_nacional
               FROM {$table} {$whereSql} {$orderSql} LIMIT 5000";
    $csvStmt = $pdo->prepare($csvSql);
    $csvStmt->execute($params);
    $csvRows = $csvStmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "segmentacion_{$nivel}_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo "\xEF\xBB\xBF";

    $headers = ['#', 'Nombre'];
    if ($nivel === 'canton')   $headers[] = 'Provincia';
    if ($nivel === 'district') { $headers[] = 'Cantón'; $headers[] = 'Provincia'; $headers[] = 'Código'; }
    $headers = array_merge($headers, ['Inscritos', 'Masculino', 'Femenino', 'Sin dato', '% del Total']);
    echo implode(',', $headers) . "\n";

    foreach ($csvRows as $i => $r) {
        $cols = [$i + 1, '"' . str_replace('"', '""', $r['nombre']) . '"'];
        if ($nivel === 'canton')   $cols[] = '"' . str_replace('"', '""', $r['provincia'])  . '"';
        if ($nivel === 'district') {
            $cols[] = '"' . str_replace('"', '""', $r['canton'])    . '"';
            $cols[] = '"' . str_replace('"', '""', $r['provincia']) . '"';
            $cols[] = $r['geo5'] ?? '';
        }
        $cols[] = $r['inscritos'];
        $cols[] = $r['inscritos_m'];
        $cols[] = $r['inscritos_f'];
        $cols[] = $r['inscritos_n'];
        $cols[] = number_format((float)$r['pct_nacional'], 3);
        echo implode(',', $cols) . "\n";
    }
    exit;
}

// ─── Paginación SQL ───────────────────────────────────────────────────────────
$offset = ($page - 1) * $size;
$pageSql = "SELECT {$idCol} AS id, {$nameSql} AS nombre{$extraSel}, inscritos, inscritos_m, inscritos_f, inscritos_n, pct_nacional
            FROM {$table} {$whereSql} {$orderSql}
            LIMIT {$size} OFFSET {$offset}";
$pageStmt = $pdo->prepare($pageSql);
$pageStmt->execute($params);
$rows = $pageStmt->fetchAll(PDO::FETCH_ASSOC);

// Añadir % del total de la vista filtrada, barra y campos de sexo normalizados
$viewMax = $rows ? (int)$rows[0]['inscritos'] : 1;
foreach ($rows as &$r) {
    $r['id']        = (int)$r['id'];
    $r['inscritos'] = (int)$r['inscritos'];
    $r['inscritos_m'] = (int)$r['inscritos_m'];
    $r['inscritos_f'] = (int)$r['inscritos_f'];
    $r['inscritos_n'] = (int)$r['inscritos_n'];
    $base = $r['inscritos'] ?: 1;
    $r['pct_m']     = round($r['inscritos_m'] / $base * 100, 1);
    $r['pct_f']     = round($r['inscritos_f'] / $base * 100, 1);
    $r['pct']       = round($r['inscritos'] / $natTotal * 100, 2);
    $r['pct_vista'] = round($r['inscritos'] / $grandTotal * 100, 2);
    $r['barra_pct'] = round($r['inscritos'] / $viewMax * 100);
}
unset($r);

echo json_encode([
    'nivel'       => $nivel,
    'rows'        => $rows,
    'total'       => $total,
    'pages'       => $pages,
    'page'        => $page,
    'grand_total' => $grandTotal,
    'stats'       => [
        'total_inscritos'   => (int)$sumInsc,
        'total_territorios' => $total,
        'max_inscritos'     => (int)$maxInsc,
        'promedio'          => $promedio,
        'total_m'           => $natM,
        'total_f'           => $natF,
        'total_n'           => $natN,
        'pct_m'             => round($natM / ($natTotal ?: 1) * 100, 1),
        'pct_f'             => round($natF / ($natTotal ?: 1) * 100, 1),
        'pct_n'             => round($natN / ($natTotal ?: 1) * 100, 1),
    ],
], JSON_UNESCAPED_UNICODE);
