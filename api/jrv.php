<?php
/**
 * api/jrv.php — Inscritos por Junta Receptora de Votos.
 *
 * Usa summary_jrv (pre-agregada) para paginación SQL real con LIMIT/OFFSET.
 * Sin scan sobre los 3.7M filas de voters en cada request.
 *
 * Params GET:
 *   province_id  int   filtra por provincia (1-7)
 *   canton_id    int   filtra por cantón
 *   geo5         str   filtra por distrito (código geo5)
 *   page         int   página (default 1)
 *   size         int   filas por página (10-200, default 50)
 *   order        asc|desc  orden por inscritos (default desc)
 *   format       json|csv
 */
require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/api.php';

$format = apiFormat();
if ($format === 'json') {
    apiJsonHeaders();
}

$pdo = dbConnect();

$province_id = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
$canton_id   = isset($_GET['canton_id'])   && $_GET['canton_id']   !== '' ? (int)$_GET['canton_id']   : null;
$pageInfo = apiPaginationFromRequest(50, 200);
$page = $pageInfo['page'];
$size = max(10, $pageInfo['size']);
$order = ($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// Resolver geo5 → district_id
$district_id = null;
$geo5 = trim((string)($_GET['geo5'] ?? ''));
if ($geo5 !== '') {
    $codelec = substr($geo5, 0, 3) . str_pad((int)substr($geo5, 3), 3, '0', STR_PAD_LEFT);
    $dStmt = $pdo->prepare('SELECT id FROM districts WHERE codelec = ? LIMIT 1');
    $dStmt->execute([$codelec]);
    $district_id = $dStmt->fetchColumn() ?: null;
}

// ─── WHERE ────────────────────────────────────────────────────────────────────
$where  = [];
$params = [];
if ($province_id) { $where[] = 'province_id = ?'; $params[] = $province_id; }
if ($canton_id)   { $where[] = 'canton_id   = ?'; $params[] = $canton_id;   }
if ($district_id) { $where[] = 'district_id = ?'; $params[] = $district_id; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderSql = "ORDER BY inscritos {$order}";

// ─── Stats y total ────────────────────────────────────────────────────────────
$stRow = $pdo->prepare("
    SELECT COUNT(*) AS juntas, SUM(inscritos) AS total_ins,
           MAX(inscritos) AS max_ins, MIN(inscritos) AS min_ins
    FROM summary_jrv {$whereSql}
");
$stRow->execute($params);
$st = $stRow->fetch(PDO::FETCH_ASSOC);

$totalJuntas = (int)$st['juntas'];
$sumInscritos = (int)$st['total_ins'];
$maxInscritos = (int)$st['max_ins'];
$minInscritos = (int)$st['min_ins'];
$promedio     = $totalJuntas > 0 ? (int)round($sumInscritos / $totalJuntas) : 0;

$pageInfo = apiPagination($totalJuntas, $size, 200);
$page = $pageInfo['page'];
$size = max(10, $pageInfo['size']);
$pages = $pageInfo['pages'];
$offset = $pageInfo['offset'];

// ─── CSV ──────────────────────────────────────────────────────────────────────
if ($format === 'csv') {
    $csvStmt = $pdo->prepare(
        "SELECT junta, provincia, canton, distrito, inscritos, clasificacion
         FROM summary_jrv {$whereSql} {$orderSql} LIMIT 10000"
    );
    $csvStmt->execute($params);

    $filename = 'jrv_inscritos_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo "\xEF\xBB\xBF";
    echo "Junta,Provincia,Cantón,Distrito,Inscritos,Clasificación\n";
    while ($r = $csvStmt->fetch(PDO::FETCH_ASSOC)) {
        echo implode(',', [
            $r['junta'],
            '"' . str_replace('"', '""', $r['provincia'])  . '"',
            '"' . str_replace('"', '""', $r['canton'])     . '"',
            '"' . str_replace('"', '""', $r['distrito'])   . '"',
            $r['inscritos'],
            $r['clasificacion'],
        ]) . "\n";
    }
    exit;
}

// ─── Paginación SQL ───────────────────────────────────────────────────────────
$pageStmt = $pdo->prepare("
    SELECT junta, provincia, canton, distrito, inscritos, clasificacion,
           NULL AS votaron, NULL AS pct_part, NULL AS pct_abs, NULL AS oportunidad
    FROM summary_jrv {$whereSql} {$orderSql}
    LIMIT {$size} OFFSET {$offset}
");
$pageStmt->execute($params);
$pageRows = $pageStmt->fetchAll(PDO::FETCH_ASSOC);

apiJson([
    'stats' => [
        'juntas'          => $totalJuntas,
        'min_inscritos'   => $minInscritos,
        'max_inscritos'   => $maxInscritos,
        'promedio'        => $promedio,
        'total_inscritos' => $sumInscritos,
    ],
    'rows'  => $pageRows,
    'total' => $totalJuntas,
    'page'  => $page,
    'size'  => $size,
    'pages' => $pages,
    'order' => $order,
]);
