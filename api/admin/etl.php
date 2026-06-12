<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../lib/db.php';
requerirAdminApi();
header('Content-Type: application/json; charset=utf-8');

$sys = dbConnect();
$dw  = dbData();

function safe_count(PDO $pdo, string $table): int {
    try { return (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(); }
    catch (Throwable) { return -1; }
}
function safe_max(PDO $pdo, string $table, string $col): ?string {
    try { return $pdo->query("SELECT MAX(`{$col}`) FROM `{$table}`")->fetchColumn() ?: null; }
    catch (Throwable) { return null; }
}
function dur(string $start, ?string $end): ?string {
    if (!$end) return null;
    $s = (new DateTime($end))->getTimestamp() - (new DateTime($start))->getTimestamp();
    if ($s < 60)   return "{$s}s";
    if ($s < 3600) return round($s/60,1) . 'min';
    return round($s/3600,1) . 'h';
}

// ── Runtime desde tablas de sync ───────────────────────────────────────────────
// Padrón
$padron = $dw->query(
    "SELECT id, status, records_ok, records_error, started_at, finished_at, message
     FROM padron_sync_runs ORDER BY id DESC LIMIT 1"
)->fetch(PDO::FETCH_ASSOC) ?: null;

// Resultados (múltiples corridas, una por elección)
$resultados = $dw->query(
    "SELECT id, election_label, filename, status, records_ok, records_error,
            started_at, finished_at, message
     FROM election_sync_runs ORDER BY started_at DESC LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);

// Import jobs recientes
$importJobs = $dw->query(
    "SELECT id, filename, status, records_ok, records_error, created_at, updated_at
     FROM import_jobs ORDER BY id DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

// ── Datos de estado para scripts sin tabla de sync ────────────────────────────
$countProvinces     = safe_count($dw, 'provinces');
$countCantons       = safe_count($dw, 'cantons');
$countDistricts     = safe_count($dw, 'districts');
$countVoters        = safe_count($dw, 'voters');
$countEnrichments   = safe_count($dw, 'voter_enrichments');
$countPolling       = safe_count($dw, 'polling_places');
$countElectDist     = safe_count($dw, 'electoral_districts');
$pctLinked          = -1;
$countResults       = safe_count($dw, 'election_results');
$maxSummaryJrv      = safe_max($dw, 'summary_jrv', 'updated_at');
$countSummaryJrv    = safe_count($dw, 'summary_jrv');

try {
    $row = $dw->query("SELECT
        COUNT(*) AS total,
        SUM(polling_place_id IS NOT NULL) AS linked,
        SUM(sexo IS NOT NULL) AS con_sexo
      FROM voters")->fetch(PDO::FETCH_ASSOC);
    $pctLinked  = $row['total'] > 0 ? round($row['linked']  / $row['total'] * 100, 1) : 0;
    $pctSexo    = $row['total'] > 0 ? round($row['con_sexo'] / $row['total'] * 100, 1) : 0;
} catch (Throwable) {
    $pctLinked = $pctSexo = -1;
}

// ── Catálogo estático de ETLs ──────────────────────────────────────────────────
$etls = [

    [
        'id'          => 'import_distelec',
        'nombre'      => 'Importar Geografía Electoral',
        'script'      => 'scripts/import_distelec.php',
        'descripcion' => 'Carga provincias, cantones y distritos desde el archivo DISTELEC.TXT del TSE',
        'origen'      => 'raw/padron/distelec.txt',
        'destino'     => 'provinces · cantons · districts',
        'estado'      => $countProvinces > 0 ? 'completado' : 'pendiente',
        'registros_ok'=> $countProvinces + $countCantons + $countDistricts,
        'ultima_exec' => null,
        'duracion'    => null,
        'detalle'     => "Provincias: {$countProvinces} · Cantones: {$countCantons} · Distritos: {$countDistricts}",
        'tipo'        => 'dimension',
    ],

    [
        'id'          => 'import_padron',
        'nombre'      => 'Importar Padrón Electoral',
        'script'      => 'scripts/import_padron.php',
        'descripcion' => 'Carga los 3.7M electores desde PADRON_COMPLETO.TXT. Actualización mensual del TSE.',
        'origen'      => 'raw/padron/PADRON_COMPLETO.txt',
        'destino'     => 'voters (3.7M)',
        'estado'      => $padron ? $padron['status'] : ($countVoters > 0 ? 'completado' : 'nunca'),
        'registros_ok'=> $padron ? (int)$padron['records_ok']    : $countVoters,
        'registros_err'=> $padron ? (int)$padron['records_error'] : 0,
        'ultima_exec' => $padron ? $padron['started_at'] : null,
        'duracion'    => $padron ? dur($padron['started_at'], $padron['finished_at']) : null,
        'detalle'     => $padron ? ($padron['message'] ?? '') : "Voters en BD: " . number_format($countVoters),
        'tipo'        => 'hecho',
        'sync_runs'   => $padron ? [$padron] : [],
    ],

    [
        'id'          => 'import_polling_places',
        'nombre'      => 'Importar Centros de Votación',
        'script'      => 'scripts/import_polling_places.php',
        'descripcion' => 'Carga los 2,191 locales de votación con rangos JRV desde el XLSX del TSE',
        'origen'      => 'raw/padron/centros_votacion_2026.xlsx',
        'destino'     => 'polling_places',
        'estado'      => $countPolling > 0 ? 'completado' : 'pendiente',
        'registros_ok'=> $countPolling,
        'ultima_exec' => null,
        'duracion'    => null,
        'detalle'     => "Locales en BD: {$countPolling}",
        'tipo'        => 'dimension',
    ],

    [
        'id'          => 'import_electoral_districts',
        'nombre'      => 'Importar Circunscripciones Legislativas',
        'script'      => 'scripts/import_electoral_districts.php',
        'descripcion' => 'Siembra las 7 circunscripciones legislativas (una por provincia)',
        'origen'      => 'provinces (BD)',
        'destino'     => 'electoral_districts',
        'estado'      => $countElectDist > 0 ? 'completado' : 'pendiente',
        'registros_ok'=> $countElectDist,
        'ultima_exec' => null,
        'duracion'    => null,
        'detalle'     => "Circunscripciones: {$countElectDist}",
        'tipo'        => 'dimension',
    ],

    [
        'id'          => 'link_voters_polling',
        'nombre'      => 'Vincular Electores → Locales',
        'script'      => 'scripts/link_voters_polling.php',
        'descripcion' => 'Asigna polling_place_id y electoral_district_id a cada elector por número de junta',
        'origen'      => 'voters · polling_places',
        'destino'     => 'voters.polling_place_id · voters.electoral_district_id',
        'estado'      => $pctLinked >= 99 ? 'completado' : ($pctLinked > 0 ? 'parcial' : 'pendiente'),
        'registros_ok'=> $countVoters > 0 ? (int)round($countVoters * $pctLinked / 100) : 0,
        'ultima_exec' => null,
        'duracion'    => null,
        'detalle'     => "Vinculación: {$pctLinked}% · Requiere import_polling_places primero",
        'tipo'        => 'enriquecimiento',
    ],

    [
        'id'          => 'enrich_sexo',
        'nombre'      => 'Enriquecer Sexo',
        'script'      => 'scripts/enrich_sexo.php',
        'descripcion' => 'Infiere sexo (M/F/N) desde name_gender_lookup por primer nombre. Persiste en voter_enrichments.',
        'origen'      => 'voters.nombre · name_gender_lookup',
        'destino'     => 'voters.sexo · voter_enrichments',
        'estado'      => $countEnrichments > 0 ? 'completado' : ($countVoters > 0 ? 'pendiente' : 'nunca'),
        'registros_ok'=> $countEnrichments,
        'ultima_exec' => null,
        'duracion'    => null,
        'detalle'     => "Sexo: {$pctSexo}% con valor · voter_enrichments: " . number_format($countEnrichments),
        'tipo'        => 'enriquecimiento',
    ],

    [
        'id'          => 'enrich_fecha_nac',
        'nombre'      => 'Enriquecer Fecha de Nacimiento',
        'script'      => 'scripts/enrich_fecha_nac.php',
        'descripcion' => 'Consulta la API CHC del TSE por cédula para obtener fecha de nacimiento. ~3.7M requests.',
        'origen'      => 'voters.cedula → API TSE CHC',
        'destino'     => 'voters.fecha_nac · voter_enrichments',
        'estado'      => 'bloqueado',
        'registros_ok'=> 0,
        'ultima_exec' => null,
        'duracion'    => null,
        'detalle'     => 'Pendiente acuerdo con TSE. WAF Radware bloquea scraping masivo.',
        'tipo'        => 'enriquecimiento',
    ],

    [
        'id'          => 'import_resultados',
        'nombre'      => 'Importar Resultados Electorales',
        'script'      => 'scripts/import_resultados.php',
        'descripcion' => 'Carga resultados AVR del TSE (JSON) por elección. Ejecutar una vez por archivo.',
        'origen'      => 'raw/avr/avr*.json',
        'destino'     => 'election_results · parties',
        'estado'      => !empty($resultados) ? $resultados[0]['status'] : ($countResults > 0 ? 'completado' : 'nunca'),
        'registros_ok'=> $countResults,
        'ultima_exec' => !empty($resultados) ? $resultados[0]['started_at'] : null,
        'duracion'    => !empty($resultados) ? dur($resultados[0]['started_at'], $resultados[0]['finished_at']) : null,
        'detalle'     => count($resultados) . ' elecciones importadas · ' . number_format($countResults) . ' resultados totales',
        'tipo'        => 'hecho',
        'sync_runs'   => $resultados,
    ],

    [
        'id'          => 'refresh_summaries',
        'nombre'      => 'Regenerar Tablas de Resumen',
        'script'      => 'scripts/refresh_summaries.php',
        'descripcion' => 'Pre-agrega inscritos por provincia/cantón/distrito/JRV. Correr tras cada importación del padrón.',
        'origen'      => 'voters · provinces · cantons · districts · polling_places',
        'destino'     => 'summary_inscritos_provincia · summary_inscritos_canton · summary_inscritos_distrito · summary_jrv',
        'estado'      => $countSummaryJrv > 0 ? 'completado' : 'pendiente',
        'registros_ok'=> $countSummaryJrv,
        'ultima_exec' => $maxSummaryJrv,
        'duracion'    => null,
        'detalle'     => "summary_jrv: {$countSummaryJrv} filas · Última actualización: " . ($maxSummaryJrv ?? 'N/D'),
        'tipo'        => 'agregado',
    ],

];

echo json_encode([
    'etls'        => $etls,
    'import_jobs' => $importJobs,
    'updated_at'  => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);
