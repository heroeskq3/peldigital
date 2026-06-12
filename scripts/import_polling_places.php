#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * import_polling_places.php
 *
 * Importa el catálogo oficial de centros de votación del TSE en polling_places.
 *
 * FUENTE: CENTROS_DE_VOTACION_RATIFICADOS-A-28-01-26.xlsx
 *   URL:  https://www.tse.go.cr/2026/docus/CENTROS_DE%20VOTACION_%20RATIFICADOS-A-28-01-26.xlsx
 *   Guardar en: raw/padron/centros_votacion_2026.xlsx
 *
 * ESTRUCTURA VERIFICADA DEL XLSX (columnas A-I, datos desde fila 8):
 *   A: CÓDIGO (codelec 6 dígitos)
 *   B: PROVINCIA
 *   C: CANTÓN
 *   D: DISTRITO
 *   E: JRV Inicial
 *   F: JRV Final
 *   G: Total JRV
 *   H: TIPO DE CENTRO (ESCUELA, COLEGIO, etc.)
 *   I: CENTRO DE VOTACIÓN (nombre del local)
 *
 * Uso:
 *   php scripts/import_polling_places.php
 *   php scripts/import_polling_places.php --file=raw/padron/centros_votacion_2026.xlsx
 *   php scripts/import_polling_places.php --dry-run   # muestra primeras 20 filas sin insertar
 *   php scripts/import_polling_places.php --truncate  # vacía tabla antes de importar
 *
 * REQUISITOS PREVIOS:
 *   php scripts/migrate.php                       (migración 000016 con jrv_inicio/jrv_fin)
 *   php scripts/import_distelec.php               (provinces, cantons, districts)
 *   php scripts/import_electoral_districts.php    (electoral_districts con 7 provincias)
 */

define('CLI_MODE', true);

$opts     = getopt('', ['file:', 'dry-run', 'truncate']);
$filePath = $opts['file'] ?? dirname(__DIR__) . '/raw/padron/centros_votacion_2026.xlsx';
$dryRun   = isset($opts['dry-run']);
$truncate = isset($opts['truncate']);

function out(string $m): void { fwrite(STDOUT, '[' . date('H:i:s') . "] {$m}\n"); }
function err(string $m): void { fwrite(STDERR, '[ERROR] ' . $m . "\n"); }

$rootDir = dirname(__DIR__);
require_once $rootDir . '/lib/db.php';

// ─── Validar archivo ──────────────────────────────────────────────────────────
$resolved = realpath($filePath);
if ($resolved === false || !is_file($resolved)) {
    err("No se encontró: {$filePath}");
    err("Descargar desde: https://www.tse.go.cr/2026/docus/CENTROS_DE%20VOTACION_%20RATIFICADOS-A-28-01-26.xlsx");
    err("Guardar como:    raw/padron/centros_votacion_2026.xlsx");
    exit(1);
}
$filePath = $resolved;
out("Archivo: {$filePath}");

// ─── Leer XLSX nativo (ZIP + XML, sin dependencias externas) ─────────────────
/**
 * Columnas en el XLSX del TSE (0-indexed):
 *   0=CÓDIGO  1=PROVINCIA  2=CANTON  3=DISTRITO
 *   4=JRV_INICIO  5=JRV_FIN  6=TOTAL_JRV
 *   7=TIPO_CENTRO  8=NOMBRE_LOCAL
 * Datos desde fila 8 (índice 7 en 0-based).
 */
const COL_CODIGO     = 0;
const COL_PROVINCIA  = 1;
const COL_CANTON     = 2;
const COL_DISTRITO   = 3;
const COL_JRV_INI    = 4;
const COL_JRV_FIN    = 5;
const COL_JRV_TOTAL  = 6;
const COL_TIPO       = 7;
const COL_NOMBRE     = 8;
const DATA_START_ROW = 8;  // fila 1-based donde empiezan los datos

function readXlsx(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("No se pudo abrir el XLSX como ZIP.");
    }

    // Shared Strings
    $ss = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $sst = new SimpleXMLElement($ssXml);
        foreach ($sst->si as $si) {
            if (isset($si->t)) {
                $ss[] = (string)$si->t;
            } else {
                $parts = [];
                foreach ($si->r ?? [] as $r) {
                    $parts[] = (string)($r->t ?? '');
                }
                $ss[] = implode('', $parts);
            }
        }
    }

    // Hoja 1
    $wsXml = $zip->getFromName('xl/worksheets/sheet1.xml')
           ?: $zip->getFromName('xl/worksheets/Sheet1.xml');
    if ($wsXml === false) {
        throw new RuntimeException("No se encontró sheet1.xml dentro del XLSX.");
    }

    $ws   = new SimpleXMLElement($wsXml);
    $rows = [];

    foreach ($ws->sheetData->row as $row) {
        $rowNum  = (int)$row['r'];
        $rowData = [];

        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
            $col = 0;
            foreach (str_split($m[1] ?? 'A') as $ch) {
                $col = $col * 26 + (ord($ch) - 64);
            }
            $col--;  // 0-based

            $type  = (string)($cell['t'] ?? '');
            $value = (string)($cell->v ?? '');

            if ($type === 's') {
                $value = $ss[(int)$value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string)($cell->is->t ?? '');
            }

            $rowData[$col] = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        }

        $rows[$rowNum] = $rowData;
    }

    $zip->close();
    return $rows;
}

out('Leyendo XLSX...');
try {
    $allRows = readXlsx($filePath);
} catch (Throwable $e) {
    err($e->getMessage());
    exit(1);
}

// Tomar solo las filas de datos (desde DATA_START_ROW en adelante)
$dataRows = array_filter($allRows, fn($rn) => $rn >= DATA_START_ROW, ARRAY_FILTER_USE_KEY);
out('Filas de datos: ' . count($dataRows));

// ─── Preview dry-run ──────────────────────────────────────────────────────────
if ($dryRun) {
    out('[DRY-RUN] Primeras 20 filas:');
    $i = 0;
    foreach ($dataRows as $rn => $row) {
        if ($i++ >= 20) break;
        $cod   = $row[COL_CODIGO]    ?? '';
        $prov  = $row[COL_PROVINCIA] ?? '';
        $dist  = $row[COL_DISTRITO]  ?? '';
        $nom   = $row[COL_NOMBRE]    ?? '';
        $tipo  = $row[COL_TIPO]      ?? '';
        $ini   = $row[COL_JRV_INI]   ?? '';
        $fin   = $row[COL_JRV_FIN]   ?? '';
        out(sprintf("  [R%d] %s | %s | %s | %s %s | JRV %s-%s",
            $rn, $cod, $prov, $dist, $tipo, $nom, $ini, $fin));
    }
    exit(0);
}

// ─── Cargar lookups ───────────────────────────────────────────────────────────
$pdo = dbData();

function normalizeGeo(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
        'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'ñ'=>'n',
    ]);
    return preg_replace('/\s+/', ' ', $s) ?? $s;
}

out('Cargando lookups...');

// provinces: nombre_norm → id
$provIndex = [];
foreach ($pdo->query('SELECT id, name FROM provinces')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $provIndex[normalizeGeo($r['name'])] = (int)$r['id'];
}

// cantons: "provId_nombreNorm" → canton_id
$cantonIndex = [];
foreach ($pdo->query('SELECT id, name, province_id FROM cantons')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $cantonIndex[$r['province_id'] . '_' . normalizeGeo($r['name'])] = (int)$r['id'];
}

// districts: "cantonId_nombreNorm" → district_id  y  codelec → district_id
$districtByKey    = [];
$districtByCode   = [];
foreach ($pdo->query('SELECT id, name, canton_id, codelec FROM districts')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $districtByKey[$r['canton_id'] . '_' . normalizeGeo($r['name'])] = (int)$r['id'];
    if ($r['codelec']) {
        $districtByCode[(string)$r['codelec']] = (int)$r['id'];
    }
}

// electoral_districts: province_id → id
$edByProvince = [];
foreach ($pdo->query('SELECT id, province_id FROM electoral_districts')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $edByProvince[(int)$r['province_id']] = (int)$r['id'];
}

if ($truncate) {
    out('Vaciando polling_places...');
    $pdo->exec('DELETE FROM polling_places');
}

// ─── Insertar ─────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    'INSERT INTO polling_places
       (name, address, junta, province_id, canton_id, district_id,
        electoral_district_id, jrv_inicio, jrv_fin, total_jrv)
     VALUES
       (:name, NULL, :junta, :province_id, :canton_id, :district_id,
        :electoral_district_id, :jrv_inicio, :jrv_fin, :total_jrv)
     ON DUPLICATE KEY UPDATE
       name                  = VALUES(name),
       jrv_inicio            = VALUES(jrv_inicio),
       jrv_fin               = VALUES(jrv_fin),
       total_jrv             = VALUES(total_jrv),
       province_id           = VALUES(province_id),
       canton_id             = VALUES(canton_id),
       district_id           = VALUES(district_id),
       electoral_district_id = VALUES(electoral_district_id)'
);

$done        = 0;
$skipped     = 0;
$geoWarnings = [];

foreach ($dataRows as $rn => $row) {
    $codelec   = trim((string)($row[COL_CODIGO]   ?? ''));
    $provNom   = trim($row[COL_PROVINCIA] ?? '');
    $cantNom   = trim($row[COL_CANTON]    ?? '');
    $distNom   = trim($row[COL_DISTRITO]  ?? '');
    $tipo      = trim($row[COL_TIPO]      ?? '');
    $nombre    = trim($row[COL_NOMBRE]    ?? '');
    $jrvInicio = (int)($row[COL_JRV_INI]  ?? 0);
    $jrvFin    = (int)($row[COL_JRV_FIN]  ?? 0);
    $totalJrv  = (int)($row[COL_JRV_TOTAL] ?? 0);

    // Saltar filas vacías
    if ($nombre === '' && $codelec === '') {
        continue;
    }

    // Nombre completo = TIPO + NOMBRE (ej: "ESCUELA REPUBLICA DEL PERU VITALIA MADRIGAL")
    $fullName = $tipo !== '' ? "{$tipo} {$nombre}" : $nombre;

    // Resolver province_id
    $provId = $provIndex[normalizeGeo($provNom)] ?? null;
    if ($provId === null) {
        // Fallback por primer dígito del codelec
        if (ctype_digit($codelec) && strlen($codelec) === 6) {
            $provId = (int)$codelec[0];
        }
    }
    if ($provId === null || $provId < 1 || $provId > 8) {
        $geoWarnings[] = "R{$rn}: provincia no encontrada '{$provNom}'";
        $skipped++;
        continue;
    }

    // Resolver canton_id
    $cantonId  = $cantonIndex[$provId . '_' . normalizeGeo($cantNom)] ?? null;
    if ($cantonId === null) {
        // Búsqueda parcial
        $norm = normalizeGeo($cantNom);
        foreach ($cantonIndex as $k => $cid) {
            if (str_starts_with($k, $provId . '_') && str_contains($k, $norm)) {
                $cantonId = $cid;
                break;
            }
        }
    }

    // Resolver district_id: primero por codelec, luego por nombre
    $districtId = null;
    if (ctype_digit($codelec) && strlen($codelec) === 6) {
        $districtId = $districtByCode[$codelec] ?? null;
    }
    if ($districtId === null && $cantonId !== null) {
        $districtId = $districtByKey[$cantonId . '_' . normalizeGeo($distNom)] ?? null;
    }

    $edId     = $edByProvince[$provId] ?? null;
    $juntaStr = $jrvInicio > 0
        ? str_pad((string)$jrvInicio, 5, '0', STR_PAD_LEFT)
        : null;

    $stmt->execute([
        ':name'                  => mb_substr($fullName, 0, 200),
        ':junta'                 => $juntaStr,
        ':province_id'           => $provId,
        ':canton_id'             => $cantonId,
        ':district_id'           => $districtId,
        ':electoral_district_id' => $edId,
        ':jrv_inicio'            => $jrvInicio > 0 ? $jrvInicio : null,
        ':jrv_fin'               => $jrvFin    > 0 ? $jrvFin    : null,
        ':total_jrv'             => $totalJrv  > 0 ? $totalJrv  : null,
    ]);
    $done++;
}

out("Locales importados: {$done}  |  Omitidos: {$skipped}");

if (!empty($geoWarnings)) {
    out('Advertencias geográficas (primeras 20):');
    foreach (array_slice($geoWarnings, 0, 20) as $w) {
        out("  ⚠  {$w}");
    }
}

$total = (int)$pdo->query('SELECT COUNT(*) FROM polling_places')->fetchColumn();
out("Total en polling_places: {$total}");

// Verificación rápida
$sample = $pdo->query(
    'SELECT name, province_id, canton_id, district_id, jrv_inicio, jrv_fin
     FROM polling_places LIMIT 3'
)->fetchAll(PDO::FETCH_ASSOC);
out('Muestra:');
foreach ($sample as $s) {
    out(sprintf('  prov=%d canton=%s dist=%s JRV %d-%d | %s',
        $s['province_id'],
        $s['canton_id'] ?? 'NULL',
        $s['district_id'] ?? 'NULL',
        $s['jrv_inicio'] ?? 0,
        $s['jrv_fin'] ?? 0,
        $s['name']
    ));
}

out('');
out('Siguiente paso: php scripts/link_voters_polling.php');
exit(0);
