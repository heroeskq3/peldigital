<?php
/**
 * api/bastiones.php — Análisis de bastiones electorales por JRV.
 *
 * Params GET:
 *   nivel        jrv|distrito|canton|provincia  (default: jrv)
 *   province_id  int    filtra por provincia
 *   canton_id    int    filtra por cantón
 *   district_id  int    filtra por distrito
 *   partido      int    tse_code del partido dominante a filtrar
 *   clasificacion  bastion_fuerte|bastion_moderado|competitivo|volatil|transicion
 *   tendencia    subiendo|bajando|estable
 *   mode         bastiones|oportunidades  (default: bastiones → sort por pct; oportunidades → sort por índice)
 *   limit        int    (default: 200, max: 2000)
 *   format       json|csv
 */
require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';

header('Cache-Control: no-store');

$pdo = dbData();

// ── Parámetros ────────────────────────────────────────────────────────────────
$nivel    = in_array($_GET['nivel'] ?? '', ['jrv','distrito','canton','provincia'])
            ? $_GET['nivel'] : 'jrv';
$provId   = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
$cantId   = isset($_GET['canton_id'])   && $_GET['canton_id']   !== '' ? (int)$_GET['canton_id']   : null;
$distId   = isset($_GET['district_id']) && $_GET['district_id'] !== '' ? (int)$_GET['district_id'] : null;
$partido  = isset($_GET['partido'])     && $_GET['partido']     !== '' ? (int)$_GET['partido']     : null;
$clasif   = $_GET['clasificacion'] ?? '';
$tend     = $_GET['tendencia']     ?? '';
$mode     = ($_GET['mode'] ?? '') === 'oportunidades' ? 'oportunidades' : 'bastiones';
$lim      = min(max((int)($_GET['limit'] ?? 200), 1), 2000);
$format   = ($_GET['format'] ?? '') === 'csv' ? 'csv' : 'json';

$allowedClasif = ['bastion_fuerte','bastion_moderado','competitivo','volatil','transicion'];
if ($clasif && !in_array($clasif, $allowedClasif)) $clasif = '';
$allowedTend = ['subiendo','bajando','estable'];
if ($tend && !in_array($tend, $allowedTend)) $tend = '';

// ── Catálogo de partidos ──────────────────────────────────────────────────────
$parties = [];
foreach ($pdo->query("SELECT tse_code, abbrev, name FROM parties WHERE scope='national'")->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $parties[(int)$p['tse_code']] = ['abbrev' => $p['abbrev'], 'name' => $p['name']];
}

// ── Consulta principal por nivel ──────────────────────────────────────────────
if ($nivel === 'jrv') {
    $where  = ['1=1'];
    $params = [];

    if ($provId)  { $where[] = 'b.province_id = ?';  $params[] = $provId; }
    if ($cantId)  { $where[] = 'b.canton_id = ?';    $params[] = $cantId; }
    if ($distId)  { $where[] = 'b.district_id = ?';  $params[] = $distId; }
    if ($partido) { $where[] = 'b.dom_tse_code = ?'; $params[] = $partido; }
    if ($clasif)  { $where[] = 'b.clasificacion = ?';$params[] = $clasif; }
    if ($tend)    { $where[] = 'b.tendencia = ?';    $params[] = $tend; }

    $orderBy = $mode === 'oportunidades'
        ? 'b.indice_oportunidad DESC'
        : 'b.dom_pct_avg DESC, b.inscritos DESC';

    $whereSql = implode(' AND ', $where);
    $params[] = $lim;

    $stmt = $pdo->prepare("
        SELECT b.junta, b.province_id, b.canton_id, b.district_id,
               b.provincia, b.canton, b.distrito, b.inscritos,
               b.local_nombre,
               b.e4_tse_code, b.e4_pct, b.e4_margen, b.e4_participacion, b.e4_votos_emitidos,
               b.e6_tse_code, b.e6_pct, b.e6_participacion,
               b.e7_tse_code, b.e7_pct,
               b.dom_tse_code, b.dom_wins, b.dom_pct_avg, b.margen_avg,
               b.clasificacion, b.tendencia,
               b.votos_conquista, b.indice_oportunidad
        FROM   summary_bastiones b
        WHERE  $whereSql
        ORDER  BY $orderBy
        LIMIT  ?
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Enriquecer con nombre del partido
    foreach ($rows as &$r) {
        $tc = (int)($r['dom_tse_code'] ?? 0);
        $r['dom_partido_abbrev'] = $parties[$tc]['abbrev'] ?? '—';
        $r['dom_partido_nombre'] = $parties[$tc]['name']   ?? '—';
        $tc4 = (int)($r['e4_tse_code'] ?? 0);
        $r['e4_partido_abbrev'] = $parties[$tc4]['abbrev'] ?? '—';
    }
    unset($r);

} else {
    // Vistas agregadas por distrito / cantón / provincia
    $groupMap  = ['distrito' => 'district_id', 'canton' => 'canton_id', 'provincia' => 'province_id'];
    $labelMap  = ['distrito' => 'distrito', 'canton' => 'canton', 'provincia' => 'provincia'];
    $gCol  = $groupMap[$nivel];
    $lblCol = $labelMap[$nivel];

    $where  = ['1=1'];
    $params = [];
    if ($provId) { $where[] = 'b.province_id = ?'; $params[] = $provId; }
    if ($cantId) { $where[] = 'b.canton_id = ?';   $params[] = $cantId; }
    if ($partido){ $where[] = 'b.dom_tse_code = ?';$params[] = $partido; }
    if ($clasif) { $where[] = 'b.clasificacion = ?';$params[] = $clasif; }

    $whereSql = implode(' AND ', $where);
    $params[] = $lim;

    $stmt = $pdo->prepare("
        SELECT b.$gCol AS geo_id, b.$lblCol AS geo_nombre,
               COUNT(*) AS total_jrvs,
               SUM(b.inscritos) AS inscritos,
               SUM(CASE WHEN b.clasificacion='bastion_fuerte'    THEN 1 ELSE 0 END) AS cnt_bastion_fuerte,
               SUM(CASE WHEN b.clasificacion='bastion_moderado'  THEN 1 ELSE 0 END) AS cnt_bastion_moderado,
               SUM(CASE WHEN b.clasificacion='competitivo'       THEN 1 ELSE 0 END) AS cnt_competitivo,
               SUM(CASE WHEN b.clasificacion='transicion'        THEN 1 ELSE 0 END) AS cnt_transicion,
               SUM(CASE WHEN b.clasificacion='volatil'           THEN 1 ELSE 0 END) AS cnt_volatil,
               ROUND(AVG(b.dom_pct_avg),1) AS pct_prom,
               ROUND(SUM(b.indice_oportunidad),1) AS oportunidad_total,
               COUNT(DISTINCT b.dom_tse_code) AS partidos_distintos
        FROM   summary_bastiones b
        WHERE  $whereSql
        GROUP  BY b.$gCol, b.$lblCol
        ORDER  BY inscritos DESC
        LIMIT  ?
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['pct_bastion'] = $r['total_jrvs'] > 0
            ? round(($r['cnt_bastion_fuerte'] + $r['cnt_bastion_moderado']) / $r['total_jrvs'] * 100, 1)
            : 0;
    }
    unset($r);
}

// ── Totales/resumen para KPIs ─────────────────────────────────────────────────
$whereKpi  = ['1=1'];
$paramsKpi = [];
if ($provId)  { $whereKpi[] = 'province_id = ?';  $paramsKpi[] = $provId; }
if ($cantId)  { $whereKpi[] = 'canton_id = ?';    $paramsKpi[] = $cantId; }
if ($partido) { $whereKpi[] = 'dom_tse_code = ?'; $paramsKpi[] = $partido; }
$whereKpiSql = implode(' AND ', $whereKpi);

$kpi = $pdo->prepare("
    SELECT
        COUNT(*) AS total_jrvs,
        SUM(inscritos) AS inscritos_total,
        SUM(CASE WHEN clasificacion='bastion_fuerte'   THEN 1 ELSE 0 END) AS bastion_fuerte,
        SUM(CASE WHEN clasificacion='bastion_moderado' THEN 1 ELSE 0 END) AS bastion_moderado,
        SUM(CASE WHEN clasificacion='competitivo'      THEN 1 ELSE 0 END) AS competitivo,
        SUM(CASE WHEN clasificacion='transicion'       THEN 1 ELSE 0 END) AS transicion,
        SUM(CASE WHEN clasificacion='volatil'          THEN 1 ELSE 0 END) AS volatil,
        ROUND(AVG(e4_participacion),1) AS part_prom_2026,
        ROUND(AVG(dom_pct_avg),1) AS pct_dom_prom
    FROM summary_bastiones
    WHERE $whereKpiSql AND e4_tse_code IS NOT NULL
");
$kpi->execute($paramsKpi);
$kpiData = $kpi->fetch(PDO::FETCH_ASSOC);

// ── Output ────────────────────────────────────────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bastiones_' . date('Ymd_Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($out, array_values($r));
    }
    fclose($out);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'nivel'   => $nivel,
    'mode'    => $mode,
    'kpi'     => $kpiData,
    'parties' => $parties,
    'rows'    => $rows,
    'total'   => count($rows),
]);
