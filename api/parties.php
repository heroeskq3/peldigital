<?php
/**
 * api/parties.php — Catálogo de partidos políticos con sus códigos TSE.
 *
 * GET api/parties.php
 *   → { parties: [{tse_code, abbrev, name, scope, verified}] }
 *
 * GET api/parties.php?codes=4,6,373
 *   → filtra solo los códigos pedidos (útil para enriquecer votos_por_partido)
 */
require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$pdo = dbData();

$codesParam = trim($_GET['codes'] ?? '');
$where = '';
$params = [];

if ($codesParam !== '') {
    $codes = array_map('intval', explode(',', $codesParam));
    $codes = array_filter($codes, fn($c) => $c > 0);
    if ($codes) {
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $where  = "WHERE tse_code IN ($placeholders)";
        $params = array_values($codes);
    }
}

$stmt = $pdo->prepare("
    SELECT tse_code, abbrev, name, scope, verified
    FROM parties
    $where
    ORDER BY tse_code
");
$stmt->execute($params);
$parties = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($parties as &$p) {
    $p['tse_code'] = (int)$p['tse_code'];
    $p['verified'] = (bool)$p['verified'];
}
unset($p);

echo json_encode(['parties' => $parties], JSON_UNESCAPED_UNICODE);
