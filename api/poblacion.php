<?php
/**
 * api/poblacion.php — Padrón electoral real + diáspora exterior.
 *
 * Métricas reales disponibles:
 *   - poblacion  : COUNT(*) de voters por región (province_id 1-7)
 *   - extranjero : COUNT(*) de voters con province_id = 8, asignados a la
 *                  provincia CR de origen por prefijo de cédula (1-7)
 *
 * Conversión de código TSE (6 dígitos "101001") a código GeoJSON (5 dígitos
 * "10101"): provincia(1) + cantón(2) + distrito sin cero inicial (2 dígitos).
 *   101001 → "101" + lpad(int("001"), 2) = "10101"
 *   706010 → "706" + lpad(int("010"), 2) = "70610"
 */

require __DIR__ . '/../auth.php';
requerirLoginApi();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Caché en archivo — se regenera si tiene más de 1 hora o si se pasa ?refresh=1
$cacheFile = __DIR__ . '/../data/poblacion_cache.json';
$cacheTTL  = 3600; // segundos
$refresh   = !empty($_GET['refresh']);

if (!$refresh && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    readfile($cacheFile);
    exit;
}

require_once __DIR__ . '/../lib/db.php';

// ---- Obtener conteos reales de la BD ----
// Estrategia de dos pasos para máximo rendimiento:
//  1. GROUP BY sobre voters usando solo el índice cubriente
//     idx_voters_geo_agg(district_id, province_id, canton_id) — sin JOINs.
//  2. Cargar nombres desde las tablas de geografía (pequeñas, en memoria).
// Esto es ~18x más rápido que el JOIN directo sobre 3.7M filas.

$pdo = dbConnect();

// Paso 1: conteos puros (cubiertos por idx_voters_geo_agg)
$counts = $pdo->query("
    SELECT district_id, province_id, canton_id, COUNT(*) AS cnt
    FROM   voters
    WHERE  province_id BETWEEN 1 AND 7
      AND  district_id IS NOT NULL
    GROUP  BY district_id, province_id, canton_id
")->fetchAll(PDO::FETCH_ASSOC);

// Paso 2: cargar tablas de nombres (pequeñas, caben en memoria)
$provNames = [];
foreach ($pdo->query("SELECT id, name FROM provinces")->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $provNames[$p['id']] = $p['name'];
}
$cantNames = [];
foreach ($pdo->query("SELECT id, name FROM cantons")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $cantNames[$c['id']] = $c['name'];
}
$distCodelecById   = [];  // district.id → codelec
$distNameByCodelec = [];  // codelec → district.name
foreach ($pdo->query("SELECT id, codelec, name FROM districts WHERE codelec IS NOT NULL")
              ->fetchAll(PDO::FETCH_ASSOC) as $d) {
    $distCodelecById[(int)$d['id']]  = $d['codelec'];
    $distNameByCodelec[$d['codelec']] = $d['name'];
}

// Construir $rows combinando conteos con nombres
$rows = [];
foreach ($counts as $r) {
    $distId  = (int)$r['district_id'];
    $provId  = (string)$r['province_id'];
    $cantId  = (string)$r['canton_id'];
    $codelec = $distCodelecById[$distId] ?? null;
    if (!$codelec) { continue; }

    $geo5 = substr($codelec, 0, 3) . str_pad((int)substr($codelec, 3), 2, '0', STR_PAD_LEFT);
    $rows[] = [
        'prov_id'   => $provId,
        'cant_id'   => $cantId,
        'geo5'      => $geo5,
        'prov_name' => $provNames[(int)$provId]  ?? 'N/D',
        'cant_name' => $cantNames[(int)$cantId]  ?? 'N/D',
        'dist_name' => $distNameByCodelec[$codelec] ?? 'N/D',
        'cnt'       => (int)$r['cnt'],
    ];
}

// ---- Construir arrays de provincia / cantón / distrito ----
$provincias = [];
$cantones   = [];
$distritos  = [];

foreach ($rows as $r) {
    $pId  = (string)$r['prov_id'];
    $cId  = (string)$r['cant_id'];
    $geo5 = $r['geo5'];
    $cnt  = (int)$r['cnt'];

    if (!isset($provincias[$pId])) {
        $provincias[$pId] = ['nombre' => $r['prov_name'], 'poblacion' => 0];
    }
    $provincias[$pId]['poblacion'] += $cnt;

    if (!isset($cantones[$cId])) {
        $cantones[$cId] = [
            'nombre'        => $r['cant_name'],
            'provincia'     => $r['prov_name'],
            'cod_provincia' => $pId,
            'poblacion'     => 0,
        ];
    }
    $cantones[$cId]['poblacion'] += $cnt;

    $distritos[$geo5] = [
        'nombre'        => $r['dist_name'],
        'canton'        => $r['cant_name'],
        'provincia'     => $r['prov_name'],
        'cod_canton'    => $cId,
        'cod_provincia' => $pId,
        'poblacion'     => $cnt,
    ];
}

// ---- Diáspora real: votantes del exterior agrupados por prefijo de cédula ----
// El primer dígito identifica la provincia de inscripción original (1–7 = provincias CR).
// Prefijos 8–9 corresponden a naturalizados; no se asignan a ninguna provincia del mapa.
$extRows = $pdo->query("
    SELECT LEFT(cedula, 1) AS prefijo, COUNT(*) AS cnt
    FROM   voters
    WHERE  province_id = 8
    GROUP  BY LEFT(cedula, 1)
")->fetchAll(PDO::FETCH_ASSOC);

$extPorProv = [];
foreach ($extRows as $r) {
    $p = (int)$r['prefijo'];
    if ($p >= 1 && $p <= 7) {
        $extPorProv[(string)$p] = ($extPorProv[(string)$p] ?? 0) + (int)$r['cnt'];
    }
}

// Diáspora por país para el panel de países
$diaspora = $pdo->query("
    SELECT c.name AS pais, COUNT(v.id) AS votantes
    FROM   voters v
    JOIN   cantons c ON v.canton_id = c.id
    WHERE  v.province_id = 8
    GROUP  BY c.id, c.name
    ORDER  BY votantes DESC
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($diaspora as &$dr) { $dr['votantes'] = (int)$dr['votantes']; }
unset($dr);

// Asignar extranjero real a provincias desde el prefijo de cédula
foreach ($provincias as $pId => &$p) {
    $p['extranjero'] = $extPorProv[$pId] ?? 0;
}
unset($p);

// Distribuir a cantones en proporción al padrón de la provincia
foreach ($cantones as $cId => &$c) {
    $pId  = $c['cod_provincia'];
    $extP = $provincias[$pId]['extranjero'] ?? 0;
    $pobP = $provincias[$pId]['poblacion']  ?: 1;
    $c['extranjero'] = (int)round($extP * ($c['poblacion'] / $pobP));
}
unset($c);

// Distribuir a distritos en proporción al padrón del cantón
foreach ($distritos as $geo5 => &$d) {
    $cId  = $d['cod_canton'];
    $extC = $cantones[$cId]['extranjero'] ?? 0;
    $pobC = $cantones[$cId]['poblacion']  ?: 1;
    $d['extranjero'] = (int)round($extC * ($d['poblacion'] / $pobC));
}
unset($d);

// Obtener fecha de la última carga exitosa del padrón
$syncRow = $pdo->query("
    SELECT finished_at FROM padron_sync_runs
    WHERE status = 'completed'
    ORDER BY finished_at DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
$padronActualizado = $syncRow ? $syncRow['finished_at'] : null;

$json = json_encode([
    'provincias'        => $provincias,
    'cantones'          => $cantones,
    'distritos'         => $distritos,
    'diaspora'          => $diaspora,
    'fuente'            => 'Padrón Nacional Electoral · TSE 2026',
    'padron_actualizado' => $padronActualizado,
    'generado'          => date('c'),
], JSON_UNESCAPED_UNICODE);

file_put_contents($cacheFile, $json);
echo $json;
