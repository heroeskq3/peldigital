<?php
/**
 * api/bastiones-mapa.php — Datos de bastiones agregados para mapa de calor.
 *
 * Params GET:
 *   nivel       distrito|canton|provincia  (default: distrito)
 *   province_id int   filtra provincia
 *   canton_id   int   filtra cantón
 *   partido     int   tse_code del partido a destacar
 */
require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo = dbData();

$nivel    = in_array($_GET['nivel'] ?? '', ['canton','provincia']) ? $_GET['nivel'] : 'distrito';
$provId   = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
$cantId   = isset($_GET['canton_id'])   && $_GET['canton_id']   !== '' ? (int)$_GET['canton_id']   : null;
$partido  = isset($_GET['partido'])     && $_GET['partido']     !== '' ? (int)$_GET['partido']     : null;

// ── Catálogo de partidos ───────────────────────────────────────────────────────
$parties = [];
foreach ($pdo->query("SELECT tse_code, abbrev, name FROM parties WHERE scope='national'")->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $parties[(int)$p['tse_code']] = ['abbrev' => $p['abbrev'], 'name' => $p['name']];
}

// ── Construir WHERE ────────────────────────────────────────────────────────────
$where  = ['b.e4_tse_code IS NOT NULL'];
$params = [];
if ($provId) { $where[] = 'b.province_id = ?'; $params[] = $provId; }
if ($cantId) { $where[] = 'b.canton_id = ?';   $params[] = $cantId; }
if ($partido){ $where[] = 'b.dom_tse_code = ?';$params[] = $partido; }
$whereSql = implode(' AND ', $where);

// ── Consulta por nivel ─────────────────────────────────────────────────────────
if ($nivel === 'distrito') {
    $stmt = $pdo->prepare("
        SELECT
            b.district_id,
            b.province_id,
            b.canton_id,
            b.provincia,
            b.canton,
            b.distrito,
            CONCAT(
                SUBSTR(d.codelec, 1, 3),
                LPAD(CAST(SUBSTR(d.codelec, 4) AS UNSIGNED), 2, '0')
            ) AS geo5,
            COUNT(*)                                                        AS total_jrvs,
            SUM(b.inscritos)                                                AS inscritos,
            SUM(CASE WHEN b.clasificacion='bastion_fuerte'   THEN 1 ELSE 0 END) AS cnt_fuerte,
            SUM(CASE WHEN b.clasificacion='bastion_moderado' THEN 1 ELSE 0 END) AS cnt_moderado,
            SUM(CASE WHEN b.clasificacion='competitivo'      THEN 1 ELSE 0 END) AS cnt_competitivo,
            SUM(CASE WHEN b.clasificacion='transicion'       THEN 1 ELSE 0 END) AS cnt_transicion,
            SUM(CASE WHEN b.clasificacion='volatil'          THEN 1 ELSE 0 END) AS cnt_volatil,
            ROUND(SUM(b.indice_oportunidad), 1)                             AS oportunidad_total,
            ROUND(AVG(b.dom_pct_avg), 1)                                    AS pct_prom
        FROM   summary_bastiones b
        JOIN   districts d ON d.id = b.district_id AND d.codelec IS NOT NULL
        WHERE  $whereSql
        GROUP  BY b.district_id, b.province_id, b.canton_id,
                  b.provincia, b.canton, b.distrito, d.codelec
    ");

} elseif ($nivel === 'canton') {
    $stmt = $pdo->prepare("
        SELECT
            b.canton_id,
            b.province_id,
            b.canton  AS nombre,
            b.provincia,
            CONCAT(
                LPAD(b.province_id, 1, '0'),
                LPAD(
                    (SELECT MAX(CAST(SUBSTR(d2.codelec,2,2) AS UNSIGNED))
                     FROM districts d2 WHERE d2.canton_id = b.canton_id AND d2.codelec IS NOT NULL
                     LIMIT 1),
                2,'0')
            )                                                               AS geo5,
            COUNT(*)                                                        AS total_jrvs,
            SUM(b.inscritos)                                                AS inscritos,
            SUM(CASE WHEN b.clasificacion='bastion_fuerte'   THEN 1 ELSE 0 END) AS cnt_fuerte,
            SUM(CASE WHEN b.clasificacion='bastion_moderado' THEN 1 ELSE 0 END) AS cnt_moderado,
            SUM(CASE WHEN b.clasificacion='competitivo'      THEN 1 ELSE 0 END) AS cnt_competitivo,
            SUM(CASE WHEN b.clasificacion='transicion'       THEN 1 ELSE 0 END) AS cnt_transicion,
            SUM(CASE WHEN b.clasificacion='volatil'          THEN 1 ELSE 0 END) AS cnt_volatil,
            ROUND(SUM(b.indice_oportunidad), 1)                             AS oportunidad_total,
            ROUND(AVG(b.dom_pct_avg), 1)                                    AS pct_prom
        FROM   summary_bastiones b
        WHERE  $whereSql
        GROUP  BY b.canton_id, b.province_id, b.canton, b.provincia
    ");

} else {
    // provincia
    $stmt = $pdo->prepare("
        SELECT
            b.province_id,
            b.provincia AS nombre,
            CAST(b.province_id AS CHAR)                                     AS geo5,
            COUNT(*)                                                        AS total_jrvs,
            SUM(b.inscritos)                                                AS inscritos,
            SUM(CASE WHEN b.clasificacion='bastion_fuerte'   THEN 1 ELSE 0 END) AS cnt_fuerte,
            SUM(CASE WHEN b.clasificacion='bastion_moderado' THEN 1 ELSE 0 END) AS cnt_moderado,
            SUM(CASE WHEN b.clasificacion='competitivo'      THEN 1 ELSE 0 END) AS cnt_competitivo,
            SUM(CASE WHEN b.clasificacion='transicion'       THEN 1 ELSE 0 END) AS cnt_transicion,
            SUM(CASE WHEN b.clasificacion='volatil'          THEN 1 ELSE 0 END) AS cnt_volatil,
            ROUND(SUM(b.indice_oportunidad), 1)                             AS oportunidad_total,
            ROUND(AVG(b.dom_pct_avg), 1)                                    AS pct_prom
        FROM   summary_bastiones b
        WHERE  $whereSql
        GROUP  BY b.province_id, b.provincia
    ");
}

$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Enriquecer: clasificación predominante y partido dominante por geo ─────────
// Para clasificación predominante: la que tiene más JRVs
// Para partido dominante: consulta separada
$domParty = [];
if ($nivel === 'distrito') {
    $dpStmt = $pdo->prepare("
        SELECT b.district_id, b.dom_tse_code, COUNT(*) AS cnt
        FROM   summary_bastiones b
        JOIN   districts d ON d.id = b.district_id AND d.codelec IS NOT NULL
        WHERE  $whereSql AND b.dom_tse_code IS NOT NULL
        GROUP  BY b.district_id, b.dom_tse_code
        ORDER  BY b.district_id, cnt DESC
    ");
    $dpStmt->execute($params);
    foreach ($dpStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $domParty[$r['district_id']] ??= (int)$r['dom_tse_code'];
    }
}

$clasifOrder = ['bastion_fuerte','bastion_moderado','competitivo','transicion','volatil'];
foreach ($rows as &$r) {
    // Clasificación predominante
    $best = null; $bestCnt = -1;
    foreach ($clasifOrder as $cl) {
        $key = 'cnt_' . str_replace('bastion_','', $cl);
        $key = str_replace(['bastion_','transicion','volatil','competitivo'],
                           ['','cnt_transicion','cnt_volatil','cnt_competitivo'], 'cnt_' . $cl);
        // map key names properly
        $keyMap = [
            'bastion_fuerte'   => 'cnt_fuerte',
            'bastion_moderado' => 'cnt_moderado',
            'competitivo'      => 'cnt_competitivo',
            'transicion'       => 'cnt_transicion',
            'volatil'          => 'cnt_volatil',
        ];
        $cnt = (int)($r[$keyMap[$cl]] ?? 0);
        if ($cnt > $bestCnt) { $bestCnt = $cnt; $best = $cl; }
    }
    $r['clasif_predominante'] = $best;
    $r['pct_bastion'] = $r['total_jrvs'] > 0
        ? round(($r['cnt_fuerte'] + $r['cnt_moderado']) / $r['total_jrvs'] * 100, 1)
        : 0;

    // Partido dominante
    $domTc = $nivel === 'distrito' ? ($domParty[(int)$r['district_id']] ?? null) : null;
    $r['dom_tse_code']     = $domTc;
    $r['dom_partido_abbrev'] = $domTc ? ($parties[$domTc]['abbrev'] ?? '?') : null;
    $r['dom_partido_nombre'] = $domTc ? ($parties[$domTc]['name']   ?? '?') : null;
}
unset($r);

// ── Índice de oportunidad: normalizar 0-100 para la rampa ─────────────────────
$maxOport = max(array_column($rows, 'oportunidad_total') ?: [1]);
foreach ($rows as &$r) {
    $r['oportunidad_norm'] = $maxOport > 0
        ? round((float)$r['oportunidad_total'] / $maxOport * 100, 1)
        : 0;
}
unset($r);

// Index por geo5
$indexed = [];
foreach ($rows as $r) {
    if (!empty($r['geo5'])) $indexed[$r['geo5']] = $r;
}

echo json_encode([
    'nivel'   => $nivel,
    'data'    => $indexed,
    'parties' => $parties,
    'total'   => count($rows),
    'max_oportunidad' => $maxOport,
], JSON_UNESCAPED_UNICODE);
