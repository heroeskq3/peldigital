<?php
/**
 * api/jrv.php — Inscritos por Junta Receptora de Votos.
 *
 * Estrategia de dos pasos (igual que api/poblacion.php):
 *   1. GROUP BY sobre voters usando idx_voters_jrv(junta, district_id, province_id, canton_id).
 *   2. Enriquecer con nombres de geografía desde tablas pequeñas cargadas en memoria.
 *   3. Ordenar, paginar y calcular stats en PHP — un solo scan de BD.
 *
 * Params GET:
 *   province_id  int   filtra por provincia (1-7)
 *   canton_id    int   filtra por cantón
 *   geo5         str   filtra por distrito (código GeoJSON 5 dígitos, ej. "10101")
 *   page         int   página (default 1)
 *   size         int   filas por página (10-200, default 50)
 *   order        asc|desc  orden por inscritos (default desc)
 */

require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo = dbConnect();

$province_id = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
$canton_id   = isset($_GET['canton_id'])   && $_GET['canton_id']   !== '' ? (int)$_GET['canton_id']   : null;
$page  = max(1, (int)($_GET['page']  ?? 1));
$size  = max(10, min(200, (int)($_GET['size'] ?? 50)));
$order = ($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// Resolver geo5 → district_id numérico (si se pasa filtro de distrito)
$district_id = null;
$geo5 = trim((string)($_GET['geo5'] ?? ''));
if ($geo5 !== '') {
    $codelec = substr($geo5, 0, 3) . str_pad((int)substr($geo5, 3), 3, '0', STR_PAD_LEFT);
    $dStmt = $pdo->prepare('SELECT id FROM districts WHERE codelec = ? LIMIT 1');
    $dStmt->execute([$codelec]);
    $district_id = $dStmt->fetchColumn() ?: null;
}

// ---- Paso 1: conteos por junta (cubiertos por idx_voters_jrv) ----
$where  = ['junta IS NOT NULL', 'province_id BETWEEN 1 AND 7'];
$params = [];
if ($province_id) { $where[] = 'province_id = ?'; $params[] = $province_id; }
if ($canton_id)   { $where[] = 'canton_id = ?';   $params[] = $canton_id;   }
if ($district_id) { $where[] = 'district_id = ?'; $params[] = $district_id; }
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT junta, district_id, province_id, canton_id, COUNT(*) AS cnt
    FROM voters
    WHERE {$whereSql}
    GROUP BY junta, district_id, province_id, canton_id
");
$stmt->execute($params);
$rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Paso 2: tablas de geografía en memoria (pequeñas, sin JOIN sobre 3.7M filas) ----
$provNames = [];
foreach ($pdo->query("SELECT id, name FROM provinces")->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $provNames[(int)$p['id']] = $p['name'];
}
$cantNames = [];
foreach ($pdo->query("SELECT id, name FROM cantons")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $cantNames[(int)$c['id']] = $c['name'];
}
$distNames = [];
foreach ($pdo->query("SELECT id, name FROM districts")->fetchAll(PDO::FETCH_ASSOC) as $d) {
    $distNames[(int)$d['id']] = $d['name'];
}

// ---- Enriquecer, calcular stats y ordenar en PHP ----
$rows = [];
$sumInscritos = 0;
$minInscritos = PHP_INT_MAX;
$maxInscritos = 0;

foreach ($rawRows as $r) {
    $cnt  = (int)$r['cnt'];
    $pId  = (int)$r['province_id'];
    $cId  = (int)$r['canton_id'];
    $dId  = (int)$r['district_id'];

    $sumInscritos += $cnt;
    if ($cnt < $minInscritos) $minInscritos = $cnt;
    if ($cnt > $maxInscritos) $maxInscritos = $cnt;

    // Clasificación proxy por volumen de padrón (sin datos de participación).
    // Se actualizará a clasificación real cuando se carguen resultados TSE.
    $clasificacion = $cnt >= 600 ? 'alta' : ($cnt >= 300 ? 'media' : 'baja');

    $rows[] = [
        'junta'          => $r['junta'],
        'provincia'      => $provNames[$pId] ?? 'N/D',
        'canton'         => $cantNames[$cId] ?? 'N/D',
        'distrito'       => $distNames[$dId] ?? 'N/D',
        'inscritos'      => $cnt,
        'clasificacion'  => $clasificacion,
        // Campos de participación: null hasta cargar resultados electorales TSE.
        'votaron'        => null,
        'pct_part'       => null,
        'pct_abs'        => null,
        'oportunidad'    => null,
    ];
}

$totalJuntas = count($rows);
if ($totalJuntas === 0) {
    $minInscritos = 0;
}

// Ordenar
usort($rows, $order === 'asc'
    ? static fn($a, $b) => $a['inscritos'] <=> $b['inscritos']
    : static fn($a, $b) => $b['inscritos'] <=> $a['inscritos']
);

// Paginar
$pages  = max(1, (int)ceil($totalJuntas / $size));
$page   = min($page, $pages);
$offset = ($page - 1) * $size;
$pageRows = array_slice($rows, $offset, $size);

echo json_encode([
    'stats' => [
        'juntas'          => $totalJuntas,
        'min_inscritos'   => $totalJuntas > 0 ? $minInscritos : 0,
        'max_inscritos'   => $maxInscritos,
        'promedio'        => $totalJuntas > 0 ? (int)round($sumInscritos / $totalJuntas) : 0,
        'total_inscritos' => $sumInscritos,
    ],
    'rows'  => $pageRows,
    'total' => $totalJuntas,
    'page'  => $page,
    'size'  => $size,
    'pages' => $pages,
    'order' => $order,
], JSON_UNESCAPED_UNICODE);
