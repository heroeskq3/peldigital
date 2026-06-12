<?php
/**
 * api/analisis_territorial.php — Comparativa territorial entre elecciones.
 *
 * Compara participación entre la elección presidencial 2026 y la municipal 2024
 * a nivel de cantón o distrito. Muestra delta de participación.
 *
 * Params GET:
 *   nivel       canton|district  (default: canton)
 *   province_id int
 *   canton_id   int   (solo para district)
 *   run_a       int   ID primera elección (default: la más reciente)
 *   run_b       int   ID segunda elección (default: la anterior a run_a)
 *   order       desc|asc  (por delta de participación, default: desc)
 *   sort        delta|part_a|part_b|nombre|inscritos
 *   q           búsqueda por nombre
 *   page        int
 *   size        int
 *   format      json|csv
 */
require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';

$format = ($_GET['format'] ?? 'json') === 'csv' ? 'csv' : 'json';
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

$pdo = dbData();

// Obtener todas las elecciones completadas
$allRuns = $pdo->query(
    "SELECT id, election_date, election_label
     FROM election_sync_runs WHERE status='completed'
     ORDER BY election_date DESC"
)->fetchAll(PDO::FETCH_ASSOC);

if (count($allRuns) < 1) {
    echo json_encode(['error' => 'No hay suficientes elecciones importadas para comparar.', 'rows' => []]);
    exit;
}

// run_a = más reciente, run_b = segunda más reciente (o lo que el usuario elija)
$runAId = isset($_GET['run_a']) ? (int)$_GET['run_a'] : (int)$allRuns[0]['id'];
$runBId = isset($_GET['run_b']) ? (int)$_GET['run_b'] : (int)($allRuns[1]['id'] ?? $allRuns[0]['id']);

// Metadatos de las dos elecciones
$runA = array_filter($allRuns, fn($r) => (int)$r['id'] === $runAId);
$runB = array_filter($allRuns, fn($r) => (int)$r['id'] === $runBId);
$metaA = array_values($runA)[0] ?? $allRuns[0];
$metaB = array_values($runB)[0] ?? ($allRuns[1] ?? $allRuns[0]);

$nivel      = ($_GET['nivel'] ?? '') === 'district' ? 'district' : 'canton';
$provinceId = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
$cantonId   = isset($_GET['canton_id'])   && $_GET['canton_id']   !== '' ? (int)$_GET['canton_id']   : null;
$page       = max(1, (int)($_GET['page']  ?? 1));
$size       = max(10, min(200, (int)($_GET['size'] ?? 25)));
$order      = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$q          = trim((string)($_GET['q'] ?? ''));
$sortField  = in_array($_GET['sort'] ?? '', ['delta','part_a','part_b','nombre','inscritos'])
              ? $_GET['sort'] : 'delta';

// SQL: join election_results para los dos runs
$join = $nivel === 'canton'
    ? "LEFT JOIN cantons c ON c.id = a.canton_id LEFT JOIN provinces p ON p.id = a.province_id"
    : "LEFT JOIN districts d ON d.id = a.district_id LEFT JOIN cantons c ON c.id = a.canton_id LEFT JOIN provinces p ON p.id = a.province_id";

$nameField = $nivel === 'canton' ? "c.name" : "d.name";
$idField   = $nivel === 'canton' ? "a.canton_id" : "a.district_id";
$extraSel  = $nivel === 'canton' ? ", p.name AS provincia"
           : ", c.name AS canton, p.name AS provincia, d.codelec";

$baseWhere = ["a.sync_run_id = ?", "a.nivel = ?", "a.inscritos > 0", "a.province_id <= 8"];
$params    = [$runAId, $nivel];
if ($provinceId) { $baseWhere[] = "a.province_id = ?"; $params[] = $provinceId; }
if ($cantonId && $nivel === 'district') { $baseWhere[] = "a.canton_id = ?"; $params[] = $cantonId; }
$whereSql  = "WHERE " . implode(" AND ", $baseWhere);

$qLike = null;
if ($q !== '') {
    $qLike = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
}

$sortSql = match($sortField) {
    'part_a'   => "pct_a",
    'part_b'   => "pct_b",
    'nombre'   => "nombre",
    'inscritos' => "a.inscritos",
    default    => "delta",
};

$selectSql = "
    SELECT
        {$idField} AS geo_id,
        {$nameField} AS nombre
        {$extraSel},
        a.inscritos,
        ROUND(a.votos_emitidos / a.inscritos * 100, 2) AS pct_a,
        ROUND(IFNULL(b.votos_emitidos, 0) / a.inscritos * 100, 2) AS pct_b,
        ROUND((a.votos_emitidos - IFNULL(b.votos_emitidos, 0)) / a.inscritos * 100, 2) AS delta,
        a.votos_emitidos AS votos_a,
        IFNULL(b.votos_emitidos, 0) AS votos_b
    FROM election_results a
    LEFT JOIN election_results b
        ON b.sync_run_id = {$runBId}
        AND b.nivel = a.nivel
        AND b.{$nivel}_id = a.{$nivel}_id
    {$join}
    {$whereSql}
";

// Count
$cntSql = "SELECT COUNT(*) FROM ({$selectSql}) sub";
$cntQ   = $q !== '' ? "{$cntSql} WHERE sub.nombre LIKE ?" : $cntSql;
$cntP   = $q !== '' ? array_merge($params, [$qLike]) : $params;
$total  = (int)$pdo->prepare($cntQ)->execute($cntP) ? $pdo->prepare($cntQ)->execute($cntP) : 0;
$cntStmt = $pdo->prepare($cntQ);
$cntStmt->execute($cntP);
$total = (int)$cntStmt->fetchColumn();

$pages  = max(1, (int)ceil($total / $size));
$page   = min($page, $pages);
$offset = ($page - 1) * $size;

$mainSql = $selectSql
    . ($q !== '' ? " HAVING nombre LIKE ?" : "")
    . " ORDER BY {$sortSql} {$order} LIMIT {$size} OFFSET {$offset}";
$mainP = $q !== '' ? array_merge($params, [$qLike]) : $params;
$stmt  = $pdo->prepare($mainSql);
$stmt->execute($mainP);
$rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $r['geo_id']   = (int)$r['geo_id'];
    $r['inscritos']= (int)$r['inscritos'];
    $r['votos_a']  = (int)$r['votos_a'];
    $r['votos_b']  = (int)$r['votos_b'];
    $r['pct_a']    = (float)$r['pct_a'];
    $r['pct_b']    = (float)$r['pct_b'];
    $r['delta']    = (float)$r['delta'];
    if (isset($r['codelec'])) {
        $c = $r['codelec'];
        $r['geo5'] = substr($c, 0, 3) . str_pad((int)substr($c, 3), 2, '0', STR_PAD_LEFT);
        unset($r['codelec']);
    }
}
unset($r);

// Stats globales
$statsSql = "
    SELECT
        COUNT(*) AS territorios,
        SUM(a.inscritos) AS total_inscritos,
        ROUND(SUM(a.votos_emitidos)/SUM(a.inscritos)*100,2) AS pct_global_a,
        ROUND(SUM(IFNULL(b.votos_emitidos,0))/SUM(a.inscritos)*100,2) AS pct_global_b,
        ROUND((SUM(a.votos_emitidos)-SUM(IFNULL(b.votos_emitidos,0)))/SUM(a.inscritos)*100,2) AS delta_global,
        MAX(ROUND((a.votos_emitidos - IFNULL(b.votos_emitidos,0))/a.inscritos*100,2)) AS max_delta,
        MIN(ROUND((a.votos_emitidos - IFNULL(b.votos_emitidos,0))/a.inscritos*100,2)) AS min_delta
    FROM election_results a
    LEFT JOIN election_results b ON b.sync_run_id={$runBId} AND b.nivel=a.nivel AND b.{$nivel}_id=a.{$nivel}_id
    {$join}
    {$whereSql}
";
$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// CSV
if ($format === 'csv') {
    $csvSql = $selectSql
        . ($q !== '' ? " HAVING nombre LIKE ?" : "")
        . " ORDER BY {$sortSql} {$order} LIMIT 5000";
    $csvStmt = $pdo->prepare($csvSql);
    $csvStmt->execute($mainP);
    $filename = "analisis_territorial_{$nivel}_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo "\xEF\xBB\xBF";
    $hdrs = ['#','Nombre'];
    if ($nivel === 'canton')   $hdrs[] = 'Provincia';
    if ($nivel === 'district') { $hdrs[] = 'Cantón'; $hdrs[] = 'Provincia'; }
    array_push($hdrs, 'Inscritos', '% Part. '.$metaA['election_label'], '% Part. '.$metaB['election_label'], 'Delta %', 'Votos '.$metaA['election_label'], 'Votos '.$metaB['election_label']);
    echo implode(',', $hdrs) . "\n";
    $i = 0;
    while ($r = $csvStmt->fetch(PDO::FETCH_ASSOC)) {
        $cols = [++$i, '"'.str_replace('"','""',$r['nombre']).'"'];
        if ($nivel === 'canton')   $cols[] = '"'.str_replace('"','""',$r['provincia']).'"';
        if ($nivel === 'district') { $cols[] = '"'.str_replace('"','""',$r['canton']).'"'; $cols[] = '"'.str_replace('"','""',$r['provincia']).'"'; }
        array_push($cols, $r['inscritos'], $r['pct_a'], $r['pct_b'], $r['delta'], $r['votos_a'], $r['votos_b']);
        echo implode(',', $cols) . "\n";
    }
    exit;
}

echo json_encode([
    'nivel'    => $nivel,
    'run_a'    => $runAId,
    'run_b'    => $runBId,
    'meta_a'   => $metaA,
    'meta_b'   => $metaB,
    'elections'=> $allRuns,
    'rows'     => $rows,
    'total'    => $total,
    'pages'    => $pages,
    'page'     => $page,
    'stats'    => $stats,
], JSON_UNESCAPED_UNICODE);
