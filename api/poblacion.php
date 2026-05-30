<?php
/**
 * API de poblacion DUMMY para el mapa de calor de Costa Rica.
 *
 * Genera una poblacion pseudo-aleatoria PERO determinista por distrito
 * (misma semilla = mismo valor en cada carga) y la agrega hacia
 * canton y provincia, de modo que las cifras son coherentes entre niveles.
 *
 * Respuesta JSON:
 * {
 *   "provincias": { "1": {"poblacion":N, "nombre":"..."} , ... },
 *   "cantones":   { "101": {...}, ... },
 *   "distritos":  { "10101": {...}, ... }
 * }
 */

require __DIR__ . '/../auth.php';
requerirLoginApi();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dataDir = __DIR__ . '/../data';

function leerFeatures($archivo) {
    $raw = file_get_contents($archivo);
    if ($raw === false) {
        http_response_code(500);
        echo json_encode(['error' => "No se pudo leer $archivo"]);
        exit;
    }
    $json = json_decode($raw, true);
    return $json['features'] ?? [];
}

/** Poblacion dummy determinista para un distrito segun su codigo. */
function poblacionDistrito($codigo) {
    // semilla estable a partir del codigo
    mt_srand(crc32('cr-pob-' . $codigo));
    // rango realista para un distrito (de ~200 a ~55 000 habitantes)
    $base = mt_rand(200, 55000);
    mt_srand(); // restaurar aleatoriedad global
    return $base;
}

/** Tasa de abstencion dummy determinista (18%..55%) por distrito. */
function tasaAbstencion($codigo) {
    mt_srand(crc32('cr-abs-' . $codigo));
    $t = mt_rand(18, 55) / 100;
    mt_srand();
    return $t;
}

/** Fraccion dummy de inscritos que reside en el extranjero (0.4%..6%). */
function tasaExtranjero($codigo) {
    mt_srand(crc32('cr-ext-' . $codigo));
    $t = mt_rand(4, 60) / 1000;
    mt_srand();
    return $t;
}

$distFeatures = leerFeatures("$dataDir/distritos.geojson");
$cantFeatures = leerFeatures("$dataDir/cantones.geojson");
$provFeatures = leerFeatures("$dataDir/provincias.geojson");

$distritos = [];
$cantones  = [];
$provincias = [];

// Nombres por codigo desde los geojson
foreach ($provFeatures as $f) {
    $p = $f['properties'];
    $provincias[$p['codigo']] = [
        'nombre' => $p['nombre'],
        'poblacion' => 0,
        'abstencion' => 0,
        'participacion' => 0,
        'extranjero' => 0,
    ];
}
foreach ($cantFeatures as $f) {
    $p = $f['properties'];
    $cantones[$p['codigo']] = [
        'nombre' => $p['nombre'],
        'provincia' => $p['provincia'],
        'cod_provincia' => $p['cod_provincia'],
        'poblacion' => 0,
        'abstencion' => 0,
        'participacion' => 0,
        'extranjero' => 0,
    ];
}

// Distritos + agregacion ascendente
foreach ($distFeatures as $f) {
    $p = $f['properties'];
    $cod = $p['codigo'];
    $pob  = poblacionDistrito($cod);
    $abst = (int) round($pob * tasaAbstencion($cod));
    $part = $pob - $abst;
    $ext  = (int) round($pob * tasaExtranjero($cod));
    $distritos[$cod] = [
        'nombre' => $p['nombre'],
        'canton' => $p['canton'],
        'provincia' => $p['provincia'],
        'cod_canton' => $p['cod_canton'],
        'cod_provincia' => $p['cod_provincia'],
        'poblacion' => $pob,
        'abstencion' => $abst,
        'participacion' => $part,
        'extranjero' => $ext,
    ];
    if (isset($cantones[$p['cod_canton']])) {
        $cantones[$p['cod_canton']]['poblacion']    += $pob;
        $cantones[$p['cod_canton']]['abstencion']    += $abst;
        $cantones[$p['cod_canton']]['participacion'] += $part;
        $cantones[$p['cod_canton']]['extranjero']    += $ext;
    }
    if (isset($provincias[$p['cod_provincia']])) {
        $provincias[$p['cod_provincia']]['poblacion']    += $pob;
        $provincias[$p['cod_provincia']]['abstencion']    += $abst;
        $provincias[$p['cod_provincia']]['participacion'] += $part;
        $provincias[$p['cod_provincia']]['extranjero']    += $ext;
    }
}

/* ===========================================================
   Cruce DUMMY: domicilio electoral (padron) vs. residencia real.
   Matriz origen-destino determinista. La "poblacion" calculada
   arriba es la ELECTORAL (donde la persona esta inscrita). Aqui
   simulamos donde RESIDE realmente: cada canton retiene una
   fraccion de sus inscritos y "presta" el resto a los cantones
   magneto (los de mayor padron, p.ej. cabeceras). El total
   nacional se conserva (cada persona se cuenta una sola vez).
   =========================================================== */

/** Fraccion de inscritos que reside en su propio canton (determinista). */
function retencionCanton($cod) {
    mt_srand(crc32('cr-ret-' . $cod));
    $r = mt_rand(62, 92) / 100;   // 62%..92% se queda
    mt_srand();
    return $r;
}

// Cantones magneto: top 12 por poblacion electoral.
$ordenPob = $cantones;
uasort($ordenPob, fn($a, $b) => $b['poblacion'] - $a['poblacion']);
$magnets = array_slice(array_keys($ordenPob), 0, 12);
$pesoMagnet = [];
foreach ($magnets as $m) { $pesoMagnet[$m] = max(1, $cantones[$m]['poblacion']); }

// Inicializar residencia real y contenedores de flujo.
foreach ($cantones as $cod => $_c) {
    $cantones[$cod]['pob_real']      = 0;
    $cantones[$cod]['flujo_salida']  = [];
    $cantones[$cod]['flujo_entrada'] = [];
}

$entradas = [];   // dest => [ origen => n ]
foreach ($cantones as $cod => $c) {
    $E    = $c['poblacion'];
    $stay = (int) round($E * retencionCanton($cod));
    $out  = $E - $stay;
    $cantones[$cod]['pob_real'] += $stay;

    $destinos  = array_values(array_filter($magnets, fn($m) => $m !== $cod));
    $sumaPeso  = 0;
    foreach ($destinos as $m) { $sumaPeso += $pesoMagnet[$m]; }

    $salidas   = [];
    $acumulado = 0;
    $nd        = count($destinos);
    foreach ($destinos as $i => $m) {
        // El ultimo destino recibe el remanente para conservar el total.
        $share = ($i < $nd - 1)
            ? (int) round($out * $pesoMagnet[$m] / $sumaPeso)
            : $out - $acumulado;
        $acumulado += $share;
        if ($share <= 0) continue;
        $cantones[$m]['pob_real'] += $share;
        $salidas[$m] = $share;
        $entradas[$m][$cod] = ($entradas[$m][$cod] ?? 0) + $share;
    }

    arsort($salidas);
    foreach (array_slice($salidas, 0, 3, true) as $m => $n) {
        $cantones[$cod]['flujo_salida'][] =
            ['cod' => $m, 'nombre' => $cantones[$m]['nombre'], 'n' => $n];
    }
}

// Top 3 origenes de los residentes que vienen de fuera.
foreach ($entradas as $dest => $orls) {
    arsort($orls);
    foreach (array_slice($orls, 0, 3, true) as $o => $n) {
        $cantones[$dest]['flujo_entrada'][] =
            ['cod' => $o, 'nombre' => $cantones[$o]['nombre'], 'n' => $n];
    }
}

// Provincias: residencia real = suma de sus cantones.
foreach ($provincias as $cp => $_p) { $provincias[$cp]['pob_real'] = 0; }
foreach ($cantones as $cod => $c) {
    $cp = $c['cod_provincia'];
    if (isset($provincias[$cp])) { $provincias[$cp]['pob_real'] += $c['pob_real']; }
}

// Distritos: se reparte el saldo del canton de forma proporcional.
foreach ($distritos as $cod => $d) {
    $cc    = $d['cod_canton'];
    $ce    = $cantones[$cc]['poblacion'] ?? 0;
    $cr    = $cantones[$cc]['pob_real'] ?? $d['poblacion'];
    $ratio = $ce > 0 ? $cr / $ce : 1;
    $distritos[$cod]['pob_real'] = (int) round($d['poblacion'] * $ratio);
}

echo json_encode([
    'provincias' => $provincias,
    'cantones'   => $cantones,
    'distritos'  => $distritos,
    'generado'   => date('c'),
], JSON_UNESCAPED_UNICODE);
