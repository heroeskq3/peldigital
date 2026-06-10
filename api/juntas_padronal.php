<?php
/**
 * api/juntas_padronal.php — Juntas receptoras de votos por nivel geográfico.
 *
 * Usa summary_jrv (7 063 filas) para todas las agregaciones. Sin scan sobre voters.
 *
 * Params GET:
 *   nivel        province|canton|district|junta  (default: province)
 *   province_id  int
 *   canton_id    int
 *   district_id  int
 *   junta_min    int  (default: 1)
 *   junta_max    int  (default: 9999)
 *   page         int  (default: 1)
 *   size         int  (10-200, default 50)
 *   order        asc|desc  (default asc para juntas, desc para territorios)
 *   format       json|csv
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

$nivel      = in_array($_GET['nivel'] ?? '', ['province','canton','district','junta'])
                ? $_GET['nivel'] : 'province';
$provinceId = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
$cantonId   = isset($_GET['canton_id'])   && $_GET['canton_id']   !== '' ? (int)$_GET['canton_id']   : null;
$districtId = isset($_GET['district_id']) && $_GET['district_id'] !== '' ? (int)$_GET['district_id'] : null;
$juntaMin   = isset($_GET['junta_min'])   && $_GET['junta_min']   !== '' ? max(1, (int)$_GET['junta_min'])    : 1;
$juntaMax   = isset($_GET['junta_max'])   && $_GET['junta_max']   !== '' ? min(9999, (int)$_GET['junta_max']) : 9999;
$page       = max(1, (int)($_GET['page']  ?? 1));
$size       = max(10, min(200, (int)($_GET['size'] ?? 50)));
$defaultOrder = ($nivel === 'junta') ? 'asc' : 'desc';
$order      = ($_GET['order'] ?? $defaultOrder) === 'asc' ? 'ASC' : 'DESC';

// ─── WHERE base ────────────────────────────────────────────────────────────────
$where  = ['CAST(junta AS UNSIGNED) BETWEEN ? AND ?'];
$params = [$juntaMin, $juntaMax];
if ($provinceId) { $where[] = 'province_id = ?'; $params[] = $provinceId; }
if ($cantonId)   { $where[] = 'canton_id = ?';   $params[] = $cantonId; }
if ($districtId) { $where[] = 'district_id = ?'; $params[] = $districtId; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

// ─── Selects por nivel ─────────────────────────────────────────────────────────
switch ($nivel) {
    case 'province':
        $selectFields  = 'province_id AS id, provincia AS nombre';
        $selectExtra   = '';
        $groupBy       = 'GROUP BY province_id, provincia';
        $orderBySql    = "ORDER BY SUM(inscritos) {$order}";
        break;
    case 'canton':
        $selectFields  = 'canton_id AS id, canton AS nombre';
        $selectExtra   = ', provincia';
        $groupBy       = 'GROUP BY canton_id, canton, provincia';
        $orderBySql    = "ORDER BY SUM(inscritos) {$order}";
        break;
    case 'district':
        $selectFields  = 'district_id AS id, distrito AS nombre';
        $selectExtra   = ', canton, provincia';
        $groupBy       = 'GROUP BY district_id, distrito, canton, provincia';
        $orderBySql    = "ORDER BY SUM(inscritos) {$order}";
        break;
    default: // junta
        $selectFields  = 'junta AS nombre';
        $selectExtra   = ', distrito, canton, provincia, district_id AS id';
        $groupBy       = 'GROUP BY junta, district_id, distrito, canton, provincia';
        $orderBySql    = "ORDER BY junta {$order}";
        break;
}

// ─── Count y stats ─────────────────────────────────────────────────────────────
$cntSql  = "SELECT COUNT(DISTINCT " . ($nivel === 'junta' ? 'junta' : ($nivel === 'province' ? 'province_id' : ($nivel === 'canton' ? 'canton_id' : 'district_id'))) . ")
             AS cnt, SUM(inscritos) AS total_ins, COUNT(DISTINCT junta) AS total_juntas
             FROM summary_jrv {$whereSql}";
$cntStmt = $pdo->prepare($cntSql);
$cntStmt->execute($params);
$cntRow = $cntStmt->fetch(PDO::FETCH_ASSOC);

$total      = (int)$cntRow['cnt'];
$sumInsc    = (int)$cntRow['total_ins'];
$totalJuntas = (int)$cntRow['total_juntas'];

// ─── CSV ───────────────────────────────────────────────────────────────────────
if ($format === 'csv') {
    $csvSql = "SELECT {$selectFields}{$selectExtra},
                      COUNT(DISTINCT junta) AS num_juntas,
                      CAST(MIN(CAST(junta AS UNSIGNED)) AS UNSIGNED) AS junta_menor,
                      CAST(MAX(CAST(junta AS UNSIGNED)) AS UNSIGNED) AS junta_mayor,
                      SUM(inscritos) AS inscritos
               FROM summary_jrv {$whereSql} {$groupBy} {$orderBySql} LIMIT 10000";
    $csvStmt = $pdo->prepare($csvSql);
    $csvStmt->execute($params);
    $csvRows = $csvStmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "juntas_{$nivel}_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo "\xEF\xBB\xBF";
    $hdrs = ['Territorio'];
    if ($nivel === 'canton')   $hdrs[] = 'Provincia';
    if ($nivel === 'district') { $hdrs[] = 'Cantón'; $hdrs[] = 'Provincia'; }
    if ($nivel === 'junta')    { $hdrs[] = 'Distrito'; $hdrs[] = 'Cantón'; $hdrs[] = 'Provincia'; }
    $hdrs = array_merge($hdrs, ['Juntas','Junta Menor','Junta Mayor','Inscritos']);
    echo implode(',', $hdrs) . "\n";
    foreach ($csvRows as $r) {
        $cols = ['"' . str_replace('"','""',$r['nombre']) . '"'];
        if ($nivel === 'canton')   $cols[] = '"' . str_replace('"','""',$r['provincia']) . '"';
        if ($nivel === 'district') { $cols[] = '"' . str_replace('"','""',$r['canton'])    . '"'; $cols[] = '"' . str_replace('"','""',$r['provincia']) . '"'; }
        if ($nivel === 'junta')    { $cols[] = '"' . str_replace('"','""',$r['distrito'])  . '"'; $cols[] = '"' . str_replace('"','""',$r['canton'])    . '"'; $cols[] = '"' . str_replace('"','""',$r['provincia']) . '"'; }
        $cols[] = (int)$r['num_juntas'];
        $cols[] = (int)$r['junta_menor'];
        $cols[] = (int)$r['junta_mayor'];
        $cols[] = (int)$r['inscritos'];
        echo implode(',', $cols) . "\n";
    }
    exit;
}

// ─── Paginación ────────────────────────────────────────────────────────────────
$pages  = max(1, (int)ceil($total / $size));
$page   = min($page, $pages);
$offset = ($page - 1) * $size;

$pageSql = "SELECT {$selectFields}{$selectExtra},
                   COUNT(DISTINCT junta) AS num_juntas,
                   CAST(MIN(CAST(junta AS UNSIGNED)) AS UNSIGNED) AS junta_menor,
                   CAST(MAX(CAST(junta AS UNSIGNED)) AS UNSIGNED) AS junta_mayor,
                   SUM(inscritos) AS inscritos
            FROM summary_jrv {$whereSql}
            {$groupBy}
            {$orderBySql}
            LIMIT {$size} OFFSET {$offset}";
$pageStmt = $pdo->prepare($pageSql);
$pageStmt->execute($params);
$rows = $pageStmt->fetchAll(PDO::FETCH_ASSOC);

$viewMax = 1;
foreach ($rows as $r) { $viewMax = max($viewMax, (int)$r['inscritos']); }

foreach ($rows as &$r) {
    $r['num_juntas']  = (int)$r['num_juntas'];
    $r['junta_menor'] = (int)$r['junta_menor'];
    $r['junta_mayor'] = (int)$r['junta_mayor'];
    $r['inscritos']   = (int)$r['inscritos'];
    $r['barra_pct']   = round($r['inscritos'] / $viewMax * 100);
}
unset($r);

echo json_encode([
    'rows'   => $rows,
    'total'  => $total,
    'pages'  => $pages,
    'page'   => $page,
    'nivel'  => $nivel,
    'stats'  => [
        'total_juntas'    => $totalJuntas,
        'total_inscritos' => $sumInsc,
        'total_territorios' => $total,
    ],
], JSON_UNESCAPED_UNICODE);
