<?php
/**
 * api/distritos_electorales.php — Distritos administrativos con desglose M/F y juntas.
 *
 * Usa summary_inscritos_distrito para datos de electorado y summary_jrv para
 * conteo de juntas por distrito. Sin scan sobre las 3.7M filas de voters.
 *
 * Params GET:
 *   province_id  int    filtra por provincia
 *   canton_id    int    filtra por cantón
 *   page         int    página (default 1)
 *   size         int    filas por página (10-200, default 25)
 *   q            str    búsqueda por nombre de distrito
 *   order        desc|asc  (default desc, por inscritos)
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

$provinceId = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
$cantonId   = isset($_GET['canton_id'])   && $_GET['canton_id']   !== '' ? (int)$_GET['canton_id']   : null;
$page       = max(1, (int)($_GET['page']  ?? 1));
$size       = max(10, min(200, (int)($_GET['size'] ?? 25)));
$order      = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$q          = trim((string)($_GET['q'] ?? ''));

// ─── WHERE ─────────────────────────────────────────────────────────────────────
$where  = [];
$params = [];
if ($provinceId) { $where[] = 's.province_id = ?'; $params[] = $provinceId; }
if ($cantonId)   { $where[] = 's.canton_id = ?';   $params[] = $cantonId; }
if ($q !== '') {
    $qLike    = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
    $where[]  = 's.nombre LIKE ?';
    $params[] = $qLike;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ─── Sub-consulta de juntas por distrito (de summary_jrv, 7063 filas) ─────────
// Se calcula una sola vez y se usa como inline view.
$juntaSubSql = "SELECT district_id,
                       COUNT(*) AS num_juntas,
                       CAST(MIN(CAST(junta AS UNSIGNED)) AS UNSIGNED) AS junta_menor,
                       CAST(MAX(CAST(junta AS UNSIGNED)) AS UNSIGNED) AS junta_mayor
                FROM summary_jrv
                GROUP BY district_id";

// ─── Totales y estadísticas ────────────────────────────────────────────────────
$cntStmt = $pdo->prepare("
    SELECT COUNT(*), SUM(s.inscritos), SUM(s.inscritos_m), SUM(s.inscritos_f)
    FROM summary_inscritos_distrito s {$whereSql}
");
$cntStmt->execute($params);
[$total, $sumInsc, $sumM, $sumF] = $cntStmt->fetch(PDO::FETCH_NUM);
$total   = (int)$total;
$sumInsc = (int)$sumInsc ?: 1;

// Juntas dentro de la vista filtrada
$juntaFiltWhere  = [];
$juntaFiltParams = [];
if ($provinceId) { $juntaFiltWhere[] = 'j.province_id = ?'; $juntaFiltParams[] = $provinceId; }
if ($cantonId)   { $juntaFiltWhere[] = 'j.canton_id = ?';   $juntaFiltParams[] = $cantonId; }
$jfWhereSql = $juntaFiltWhere ? 'WHERE ' . implode(' AND ', $juntaFiltWhere) : '';
$jFiltRow = $pdo->prepare("SELECT COUNT(DISTINCT junta) FROM summary_jrv j {$jfWhereSql}");
$jFiltRow->execute($juntaFiltParams);
$totalJuntas = (int)$jFiltRow->fetchColumn();

// Grand total nacional para %
$natRow   = $pdo->query("SELECT SUM(inscritos) FROM summary_inscritos_distrito")->fetch(PDO::FETCH_NUM);
$natTotal = (int)$natRow[0] ?: 1;

// ─── Paginación ────────────────────────────────────────────────────────────────
$pages  = max(1, (int)ceil($total / $size));
$page   = min($page, $pages);
$offset = ($page - 1) * $size;

$selectSql = "SELECT s.district_id, s.nombre, s.canton, s.provincia,
                     s.canton_id, s.province_id, s.geo5,
                     s.inscritos, s.inscritos_m, s.inscritos_f, s.inscritos_n, s.pct_nacional,
                     COALESCE(jg.num_juntas, 0)  AS num_juntas,
                     jg.junta_menor, jg.junta_mayor
              FROM summary_inscritos_distrito s
              LEFT JOIN ({$juntaSubSql}) jg ON jg.district_id = s.district_id
              {$whereSql}
              ORDER BY s.inscritos {$order}";

if ($format === 'csv') {
    $csvStmt = $pdo->prepare($selectSql . " LIMIT 5000");
    $csvStmt->execute($params);
    $csvRows = $csvStmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "distritos_electorales_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo "\xEF\xBB\xBF";
    echo "Distrito,Cantón,Provincia,Hombre,Mujer,Sin dato,Total,% Nacional,Juntas,Junta Menor,Junta Mayor\n";
    foreach ($csvRows as $r) {
        echo implode(',', [
            '"' . str_replace('"', '""', $r['nombre'])    . '"',
            '"' . str_replace('"', '""', $r['canton'])    . '"',
            '"' . str_replace('"', '""', $r['provincia']) . '"',
            (int)$r['inscritos_m'], (int)$r['inscritos_f'], (int)$r['inscritos_n'],
            (int)$r['inscritos'],
            number_format((float)$r['pct_nacional'], 3),
            (int)$r['num_juntas'], (int)$r['junta_menor'], (int)$r['junta_mayor'],
        ]) . "\n";
    }
    exit;
}

$pageStmt = $pdo->prepare($selectSql . " LIMIT {$size} OFFSET {$offset}");
$pageStmt->execute($params);
$rows = $pageStmt->fetchAll(PDO::FETCH_ASSOC);

$viewMax = $rows ? (int)$rows[0]['inscritos'] : 1;
foreach ($rows as &$r) {
    $r['district_id']  = (int)$r['district_id'];
    $r['inscritos']    = (int)$r['inscritos'];
    $r['inscritos_m']  = (int)$r['inscritos_m'];
    $r['inscritos_f']  = (int)$r['inscritos_f'];
    $r['inscritos_n']  = (int)$r['inscritos_n'];
    $r['num_juntas']   = (int)$r['num_juntas'];
    $r['junta_menor']  = (int)$r['junta_menor'];
    $r['junta_mayor']  = (int)$r['junta_mayor'];
    $base              = $r['inscritos'] ?: 1;
    $r['pct_m']        = round($r['inscritos_m'] / $base * 100, 1);
    $r['pct_f']        = round($r['inscritos_f'] / $base * 100, 1);
    $r['pct_total']    = round($r['inscritos'] / $natTotal * 100, 2);
    $r['barra_pct']    = round($r['inscritos'] / $viewMax * 100);
}
unset($r);

echo json_encode([
    'rows'  => $rows,
    'total' => $total,
    'pages' => $pages,
    'page'  => $page,
    'stats' => [
        'total_inscritos' => (int)$sumInsc,
        'total_distritos' => $total,
        'total_juntas'    => $totalJuntas,
        'total_m'         => (int)$sumM,
        'total_f'         => (int)$sumF,
        'pct_m'           => round((int)$sumM / $sumInsc * 100, 1),
        'pct_f'           => round((int)$sumF / $sumInsc * 100, 1),
    ],
], JSON_UNESCAPED_UNICODE);
