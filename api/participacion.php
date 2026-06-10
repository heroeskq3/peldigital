<?php
/**
 * api/participacion.php — Participación y abstención electoral por territorio.
 *
 * Usa la tabla election_results importada desde el AVR del TSE.
 * Paginación real con SQL LIMIT/OFFSET directa (tabla es pequeña: <600 filas).
 *
 * Params GET:
 *   nivel       province|canton|district  (default: province)
 *   province_id int   filtra por provincia
 *   canton_id   int   filtra por cantón
 *   run_id      int   ID de importación (default: último completado)
 *   page        int   (default 1)
 *   size        int   (10-200, default 25)
 *   order       desc|asc  por % participación (default desc)
 *   sort        participacion|abstencion|inscritos|nombre  (default: participacion)
 *   q           str   búsqueda por nombre
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

$pdo = dbConnect();

// Lista completa de elecciones disponibles (para el selector del frontend)
$allRuns = $pdo->query(
    "SELECT id, election_date, election_label
     FROM election_sync_runs WHERE status='completed'
     ORDER BY election_date DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Obtener run_id (el más reciente si no se especifica)
$runId = isset($_GET['run_id']) ? (int)$_GET['run_id'] : 0;
if (!$runId) {
    if (!$allRuns) {
        echo json_encode(['error' => 'No hay resultados electorales importados.', 'rows' => [], 'stats' => []]);
        exit;
    }
    $meta  = $allRuns[0];
    $runId = (int)$meta['id'];
} else {
    $stmt = $pdo->prepare("SELECT id, election_date, election_label FROM election_sync_runs WHERE id=?");
    $stmt->execute([$runId]);
    $meta = $stmt->fetch(PDO::FETCH_ASSOC) ?: $allRuns[0];
    $runId = (int)$meta['id'];
}

$nivel      = in_array($_GET['nivel'] ?? '', ['province','canton','district']) ? $_GET['nivel'] : 'province';
$provinceId = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
$cantonId   = isset($_GET['canton_id'])   && $_GET['canton_id']   !== '' ? (int)$_GET['canton_id']   : null;
$page       = max(1, (int)($_GET['page']  ?? 1));
$size       = max(10, min(200, (int)($_GET['size'] ?? 25)));
$order      = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$q          = trim((string)($_GET['q'] ?? ''));
$sortField  = in_array($_GET['sort'] ?? '', ['participacion','abstencion','inscritos','nombre'])
              ? $_GET['sort'] : 'participacion';

// Mapa de sort a expresión SQL
$sortSql = match($sortField) {
    'abstencion'  => "(1 - r.votos_emitidos / r.inscritos)",
    'inscritos'   => "r.inscritos",
    'nombre'      => "geo_name",
    default       => "(r.votos_emitidos / r.inscritos)",  // participacion
};

// Joins y nombre geográfico según nivel
switch ($nivel) {
    case 'province':
        $join    = "LEFT JOIN provinces p ON p.id = r.province_id";
        $nameSel = "p.name AS geo_name";
        $idSel   = "r.province_id AS geo_id";
        $extra   = "";
        break;
    case 'canton':
        $join    = "LEFT JOIN cantons c ON c.id = r.canton_id
                    LEFT JOIN provinces p ON p.id = r.province_id";
        $nameSel = "c.name AS geo_name";
        $idSel   = "r.canton_id AS geo_id";
        $extra   = ", p.name AS provincia";
        break;
    default: // district
        $join    = "LEFT JOIN districts d ON d.id = r.district_id
                    LEFT JOIN cantons c ON c.id = r.canton_id
                    LEFT JOIN provinces p ON p.id = r.province_id";
        $nameSel = "d.name AS geo_name";
        $idSel   = "r.district_id AS geo_id";
        $extra   = ", c.name AS canton, p.name AS provincia, d.codelec";
        break;
}

// WHERE
$where  = ['r.sync_run_id = ?', 'r.nivel = ?', 'r.inscritos > 0'];
$params = [$runId, $nivel];
// Limitar province_id <= 8 a nivel provincia (9+ son países de diáspora tratados como "provincia")
if ($nivel === 'province') { $where[] = 'r.province_id <= 8'; }
if ($provinceId) { $where[] = 'r.province_id = ?'; $params[] = $provinceId; }
if ($cantonId && $nivel === 'district') { $where[] = 'r.canton_id = ?'; $params[] = $cantonId; }
if ($q !== '') {
    $qLike    = '%' . str_replace(['%','_'],['\\%','\\_'], $q) . '%';
    // Necesita subquery para filtrar por nombre geográfico
    // Como los JOINs ya están, se filtra sobre alias via HAVING
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

// GROUP BY campo geográfico (hay múltiples filas por territorio en el AVR)
$groupId = match($nivel) {
    'province' => 'r.province_id',
    'canton'   => 'r.canton_id, p.name, c.name',
    default    => 'r.district_id, p.name, c.name, d.name, d.codelec',
};

// Subquery base con agregación por territorio
// Build SELECT list as array to avoid comma issues with optional $extra
$selCols = array_filter(array_merge(
    ["{$idSel}", "{$nameSel}"],
    $extra ? [ltrim($extra, ', ')] : [],
    [
        "SUM(r.inscritos)           AS inscritos",
        "SUM(r.votos_emitidos)      AS votos_emitidos",
        "SUM(r.votos_validos)       AS votos_validos",
        "SUM(r.votos_nulos_blancos) AS votos_nulos_blancos",
        "SUM(r.juntas_total)        AS juntas_total",
        "SUM(r.juntas_procesadas)   AS juntas_procesadas",
        "ROUND(SUM(r.votos_emitidos) / SUM(r.inscritos) * 100, 2)       AS pct_participacion",
        "ROUND((1 - SUM(r.votos_emitidos) / SUM(r.inscritos)) * 100, 2) AS pct_abstencion",
    ]
));
$aggSql = "SELECT " . implode(", ", $selCols) . "
    FROM election_results r {$join}
    {$whereSql}
    GROUP BY {$groupId}";

// Sort map sobre alias de la subquery
$sortSqlAgg = match($sortField) {
    'abstencion' => "pct_abstencion",
    'inscritos'  => "inscritos",
    'nombre'     => "geo_name",
    default      => "pct_participacion",
};

$havingQ   = $q !== '' ? "HAVING geo_name LIKE ?" : "";
$cntParams = $q !== '' ? array_merge($params, [$qLike]) : $params;
$cntStmt   = $pdo->prepare("SELECT COUNT(*) FROM ({$aggSql} {$havingQ}) sub");
$cntStmt->execute($cntParams);
$total  = (int)$cntStmt->fetchColumn();
$pages  = max(1, (int)ceil($total / $size));
$page   = min($page, $pages);
$offset = ($page - 1) * $size;

$mainSql    = "{$aggSql} {$havingQ} ORDER BY {$sortSqlAgg} {$order} LIMIT {$size} OFFSET {$offset}";
$mainParams = $cntParams;
$stmt = $pdo->prepare($mainSql);
$stmt->execute($mainParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar votos_por_partido JSON y calcular geo5 para distritos
foreach ($rows as &$r) {
    $r['geo_id']           = (int)$r['geo_id'];
    $r['inscritos']        = (int)$r['inscritos'];
    $r['votos_emitidos']   = (int)$r['votos_emitidos'];
    $r['votos_validos']    = (int)$r['votos_validos'];
    $r['votos_nulos_blancos'] = (int)$r['votos_nulos_blancos'];
    $r['juntas_total']     = (int)$r['juntas_total'];
    $r['juntas_procesadas']= (int)$r['juntas_procesadas'];
    $r['pct_participacion']= (float)$r['pct_participacion'];
    $r['pct_abstencion']   = (float)$r['pct_abstencion'];
    if (isset($r['codelec']) && $r['codelec']) {
        $c    = $r['codelec'];
        $r['geo5'] = substr($c, 0, 3) . str_pad((int)substr($c, 3), 2, '0', STR_PAD_LEFT);
    }
    unset($r['votos_por_partido'], $r['codelec']);
}
unset($r);

// Stats globales — sobre la subquery agregada por territorio
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS territorios,
        SUM(s.inscritos) AS total_inscritos,
        SUM(s.votos_emitidos) AS total_votos,
        ROUND(SUM(s.votos_emitidos) / SUM(s.inscritos) * 100, 2) AS pct_part_global,
        MAX(s.pct_participacion) AS max_part,
        MIN(s.pct_participacion) AS min_part
    FROM ({$aggSql}) s
");
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// ─── Votos por partido (agregado del nivel filtrado) ─────────────────────────
$vppSql = "SELECT votos_por_partido FROM election_results r {$join} {$whereSql} AND r.votos_por_partido IS NOT NULL";
$vppStmt = $pdo->prepare($vppSql);
$vppStmt->execute($params);
$partyTotals = [];
while ($vppRow = $vppStmt->fetch(PDO::FETCH_NUM)) {
    $decoded = json_decode($vppRow[0], true) ?: [];
    foreach ($decoded as $code => $votes) {
        $c = (int)$code;
        $partyTotals[$c] = ($partyTotals[$c] ?? 0) + (int)$votes;
    }
}
arsort($partyTotals);
$partyTotals = array_slice($partyTotals, 0, 30, true); // top 30

// Enriquecer con nombres del catálogo
$partyCatalog = [];
if ($partyTotals) {
    $pCodes = implode(',', array_keys($partyTotals));
    $pRows  = $pdo->query("SELECT tse_code, abbrev, name FROM parties WHERE tse_code IN ($pCodes)")
                  ->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pRows as $pr) {
        $partyCatalog[(int)$pr['tse_code']] = ['abbrev' => $pr['abbrev'], 'name' => $pr['name']];
    }
}
$partyBreakdown = [];
foreach ($partyTotals as $code => $votes) {
    $partyBreakdown[] = [
        'code'   => $code,
        'votes'  => $votes,
        'abbrev' => $partyCatalog[$code]['abbrev'] ?? "Código $code",
        'name'   => $partyCatalog[$code]['name']   ?? "Partido código $code",
    ];
}

// ─── CSV ──────────────────────────────────────────────────────────────────────
if ($format === 'csv') {
    // Re-query sin LIMIT para CSV
    $csvSql = "
        SELECT {$idSel}, {$nameSel}, {$extra}
               r.inscritos, r.votos_emitidos,
               ROUND(r.votos_emitidos/r.inscritos*100,2) AS pct_participacion,
               ROUND((1-r.votos_emitidos/r.inscritos)*100,2) AS pct_abstencion,
               r.juntas_total, r.juntas_procesadas
        FROM election_results r {$join}
        {$whereSql}
        " . ($q !== '' ? "HAVING geo_name LIKE ?" : "") . "
        ORDER BY {$sortSql} {$order} LIMIT 5000
    ";
    $csvStmt = $pdo->prepare($csvSql);
    $csvStmt->execute($mainParams);

    $filename = "participacion_{$nivel}_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo "\xEF\xBB\xBF";
    $hdrs = ['#','Nombre'];
    if ($nivel === 'canton')   $hdrs[] = 'Provincia';
    if ($nivel === 'district') { $hdrs[] = 'Cantón'; $hdrs[] = 'Provincia'; }
    array_push($hdrs, 'Inscritos','Votos Emitidos','% Participación','% Abstención','Juntas Total','Juntas Procesadas');
    echo implode(',', $hdrs) . "\n";
    $i = 0;
    while ($r = $csvStmt->fetch(PDO::FETCH_ASSOC)) {
        $cols = [++$i, '"' . str_replace('"','""',$r['geo_name']) . '"'];
        if ($nivel === 'canton')   $cols[] = '"' . str_replace('"','""',$r['provincia']) . '"';
        if ($nivel === 'district') { $cols[] = '"' . str_replace('"','""',$r['canton']) . '"'; $cols[] = '"' . str_replace('"','""',$r['provincia']) . '"'; }
        array_push($cols, $r['inscritos'], $r['votos_emitidos'], $r['pct_participacion'], $r['pct_abstencion'], $r['juntas_total'], $r['juntas_procesadas']);
        echo implode(',', $cols) . "\n";
    }
    exit;
}

echo json_encode([
    'nivel'     => $nivel,
    'run_id'    => $runId,
    'meta'      => $meta,
    'elections'       => $allRuns,
    'rows'            => $rows,
    'total'           => $total,
    'pages'           => $pages,
    'page'            => $page,
    'stats'           => $stats,
    'party_breakdown' => $partyBreakdown,
], JSON_UNESCAPED_UNICODE);
