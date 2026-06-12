<?php
/**
 * api/locales.php — Locales de votación con inscritos y JRVs.
 *
 * Params GET:
 *   province_id  int     filtra por provincia
 *   canton_id    int     filtra por cantón
 *   buscar       string  filtra por nombre del local
 *   order        asc|desc  orden por inscritos (default desc)
 *   page/size    paginación
 *   format       json|csv
 */
require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/api.php';

$format = apiFormat();
if ($format === 'json') apiJsonHeaders();

$pdo = dbData();

$province_id = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
$canton_id   = isset($_GET['canton_id'])   && $_GET['canton_id']   !== '' ? (int)$_GET['canton_id']   : null;
$buscar      = trim((string)($_GET['buscar'] ?? ''));
$order       = ($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$pageInfo = apiPaginationFromRequest(50, 200);
$size  = max(10, $pageInfo['size']);

$where  = ['pp.province_id BETWEEN 1 AND 7'];
$params = [];

if ($province_id) { $where[] = 'pp.province_id = ?'; $params[] = $province_id; }
if ($canton_id)   { $where[] = 'pp.canton_id   = ?'; $params[] = $canton_id;   }
if ($buscar !== '') {
    $where[] = 'pp.name LIKE ?';
    $params[] = '%' . $buscar . '%';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);
$orderSql = "ORDER BY inscritos {$order}";

$countStmt = $pdo->prepare("
    SELECT COUNT(*) AS total,
           SUM(agg.inscritos) AS total_ins,
           MAX(agg.inscritos) AS max_ins,
           MIN(agg.inscritos) AS min_ins
    FROM polling_places pp
    LEFT JOIN (
        SELECT polling_place_id, SUM(inscritos) AS inscritos, COUNT(*) AS juntas
        FROM summary_jrv WHERE polling_place_id IS NOT NULL
        GROUP BY polling_place_id
    ) agg ON agg.polling_place_id = pp.id
    {$whereSql}
");
$countStmt->execute($params);
$stats = $countStmt->fetch(PDO::FETCH_ASSOC);

$total = (int)$stats['total'];
$pageInfo = apiPagination($total, $size, 200);
$page   = $pageInfo['page'];
$size   = max(10, $pageInfo['size']);
$pages  = $pageInfo['pages'];
$offset = $pageInfo['offset'];

$baseSelect = "
    SELECT pp.id, pp.name AS local, pp.jrv_inicio, pp.jrv_fin, pp.total_jrv,
           pr.name AS provincia, c.name AS canton, d.name AS distrito,
           pp.province_id, pp.canton_id,
           COALESCE(agg.inscritos, 0) AS inscritos,
           COALESCE(agg.juntas, 0)    AS juntas_con_datos
    FROM polling_places pp
    JOIN provinces pr ON pr.id = pp.province_id
    LEFT JOIN cantons c   ON c.id  = pp.canton_id
    LEFT JOIN districts d ON d.id  = pp.district_id
    LEFT JOIN (
        SELECT polling_place_id, SUM(inscritos) AS inscritos, COUNT(*) AS juntas
        FROM summary_jrv WHERE polling_place_id IS NOT NULL
        GROUP BY polling_place_id
    ) agg ON agg.polling_place_id = pp.id
    {$whereSql} {$orderSql}";

if ($format === 'csv') {
    $csvStmt = $pdo->prepare($baseSelect . ' LIMIT 5000');
    $csvStmt->execute($params);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="locales_votacion_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    echo "ID,Local,Provincia,Cantón,Distrito,JRV Inicio,JRV Fin,Total JRV,Inscritos\n";
    while ($r = $csvStmt->fetch(PDO::FETCH_ASSOC)) {
        echo implode(',', [
            $r['id'],
            '"' . str_replace('"', '""', $r['local'])    . '"',
            '"' . str_replace('"', '""', $r['provincia']) . '"',
            '"' . str_replace('"', '""', $r['canton'] ?? '') . '"',
            '"' . str_replace('"', '""', $r['distrito'] ?? '') . '"',
            $r['jrv_inicio'], $r['jrv_fin'], $r['total_jrv'],
            $r['inscritos'],
        ]) . "\n";
    }
    exit;
}

$rowStmt = $pdo->prepare($baseSelect . " LIMIT {$size} OFFSET {$offset}");
$rowStmt->execute($params);
$rows = $rowStmt->fetchAll(PDO::FETCH_ASSOC);

apiJson([
    'stats' => [
        'total'          => $total,
        'total_inscritos'=> (int)$stats['total_ins'],
        'max_inscritos'  => (int)$stats['max_ins'],
        'min_inscritos'  => (int)$stats['min_ins'],
        'promedio'       => $total > 0 ? (int)round((int)$stats['total_ins'] / $total) : 0,
    ],
    'rows'  => $rows,
    'total' => $total,
    'page'  => $page,
    'size'  => $size,
    'pages' => $pages,
    'order' => $order,
]);
