<?php
/**
 * api/resultados.php — Participación y resultados electorales por territorio.
 *
 * Params GET:
 *   nivel        province|canton|district  (default: province)
 *   province_id  int   filtra por provincia
 *   canton_id    int   filtra por cantón
 *   circunsc     int   filtra por circunscripción (default: 0)
 *   run_id       int   ID de importación (default: último completado)
 */
require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo = dbConnect();

// Obtener último run completado si no se especifica
$runId = isset($_GET['run_id']) ? (int)$_GET['run_id'] : null;
if (!$runId) {
    $last = $pdo->query(
        "SELECT id, election_date, election_label FROM election_sync_runs
         WHERE status='completed' ORDER BY finished_at DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if (!$last) {
        echo json_encode(['error' => 'No hay resultados importados.', 'rows' => []]);
        exit;
    }
    $runId = (int)$last['id'];
    $meta  = $last;
} else {
    $meta = $pdo->prepare("SELECT id, election_date, election_label FROM election_sync_runs WHERE id=?")->execute([$runId]);
}

$nivel      = in_array($_GET['nivel'] ?? '', ['province','canton','district']) ? $_GET['nivel'] : 'province';
$circunsc   = isset($_GET['circunsc']) ? (int)$_GET['circunsc'] : 0;
$provinceId = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
$cantonId   = isset($_GET['canton_id'])   && $_GET['canton_id']   !== '' ? (int)$_GET['canton_id']   : null;

// Construir WHERE
$where  = ['r.sync_run_id = ?', 'r.nivel = ?', 'r.circunscripcion = ?'];
$params = [$runId, $nivel, $circunsc];
if ($provinceId) { $where[] = 'r.province_id = ?'; $params[] = $provinceId; }
if ($cantonId)   { $where[] = 'r.canton_id = ?';   $params[] = $cantonId;   }

$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT r.*,
           p.name AS provincia_nombre,
           c.name AS canton_nombre,
           d.name AS distrito_nombre
    FROM   election_results r
    LEFT JOIN provinces p ON p.id = r.province_id
    LEFT JOIN cantons   c ON c.id = r.canton_id
    LEFT JOIN districts d ON d.id = r.district_id
    WHERE  {$whereSql}
    ORDER  BY r.votos_emitidos DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular % participación
foreach ($rows as &$row) {
    $insc = (int)$row['inscritos'];
    $emit = (int)$row['votos_emitidos'];
    $row['pct_participacion'] = $insc > 0 ? round($emit / $insc * 100, 2) : null;
    $row['pct_abstencion']    = $insc > 0 ? round((1 - $emit / $insc) * 100, 2) : null;
    $row['votos_por_partido'] = $row['votos_por_partido']
        ? json_decode($row['votos_por_partido'], true)
        : [];
}
unset($row);

echo json_encode([
    'run_id'   => $runId,
    'meta'     => $meta,
    'nivel'    => $nivel,
    'circunsc' => $circunsc,
    'total'    => count($rows),
    'rows'     => $rows,
], JSON_UNESCAPED_UNICODE);
