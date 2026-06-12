#!/usr/bin/env php
<?php
/**
 * scripts/enrich_fecha_nac.php
 *
 * Enriquece voters.fecha_nac consultando la pagina publica del TSE.
 * Es reanudable: solo procesa cedulas donde chc_consultado_at IS NULL.
 *
 * Uso:
 *   php scripts/enrich_fecha_nac.php [--province=1] [--batch=500] [--delay=800]
 *
 * Opciones:
 *   --province   ID de provincia a procesar (1-7). Sin valor = todas.
 *   --batch      Cuantos registros procesar por ejecucion (default: 500).
 *                Usar un numero bajo para prueba inicial.
 *   --delay      Milisegundos de espera entre requests (default: 800).
 *                Minimo recomendado: 600. No bajar de 400.
 *   --dry-run    Muestra cuantos registros pendientes hay sin procesar nada.
 *
 * Ejemplo de prueba (10 cedulas de San Jose, 1 por segundo):
 *   php scripts/enrich_fecha_nac.php --province=1 --batch=10 --delay=1000
 *
 * Para produccion (San Jose completo, ~1M cedulas):
 *   nohup php scripts/enrich_fecha_nac.php --province=1 --batch=50000 > logs/enrich_sj.log 2>&1 &
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

function out(string $m): void { fwrite(STDOUT, '[' . date('H:i:s') . "] {$m}\n"); }
function err(string $m): void { fwrite(STDERR, '[ERROR] ' . $m . "\n"); }

$opts       = getopt('', ['province:', 'batch:', 'delay:', 'dry-run']);
$provinceId = isset($opts['province']) ? (int)$opts['province'] : null;
$batchSize  = max(1, (int)($opts['batch'] ?? 500));
$delayMs    = max(400, (int)($opts['delay'] ?? 800));
$dryRun     = isset($opts['dry-run']);

$pdo = dbData();

// --- Contar pendientes ---
$whereParts = ['chc_consultado_at IS NULL'];
$whereParams = [];
if ($provinceId) {
    $whereParts[]  = 'province_id = ?';
    $whereParams[] = $provinceId;
}
$whereSql = implode(' AND ', $whereParts);

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM voters WHERE {$whereSql}")
                  ->execute($whereParams) ?
         $pdo->prepare("SELECT COUNT(*) FROM voters WHERE {$whereSql}")->execute($whereParams) : 0;
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE {$whereSql}");
$cntStmt->execute($whereParams);
$total = (int)$cntStmt->fetchColumn();

$label = $provinceId ? "provincia {$provinceId}" : "todas las provincias";
out("Pendientes ({$label}): " . number_format($total));

if ($dryRun || $total === 0) {
    out($total === 0 ? 'Sin pendientes.' : 'Dry-run: sin cambios.');
    exit(0);
}

out("Procesando hasta {$batchSize} cedulas · delay {$delayMs}ms entre requests...");

// --- Obtener cedulas pendientes ---
$fetchSql = "SELECT cedula FROM voters WHERE {$whereSql} ORDER BY province_id, cedula LIMIT {$batchSize}";
$fetchStmt = $pdo->prepare($fetchSql);
$fetchStmt->execute($whereParams);
$cedulas = $fetchStmt->fetchAll(PDO::FETCH_COLUMN);

// --- Configuracion HTTP ---
$baseUrl  = 'https://servicioselectorales.tse.go.cr/chc/consulta_cedula.aspx';
$headers  = [
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
    'Accept: text/html,application/xhtml+xml',
    'Accept-Language: es-CR,es;q=0.9',
    'Content-Type: application/x-www-form-urlencoded',
    'Origin: https://servicioselectorales.tse.go.cr',
    'Referer: https://servicioselectorales.tse.go.cr/chc/consulta_cedula.aspx',
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEJAR      => '/tmp/chc_cookies.txt',
    CURLOPT_COOKIEFILE     => '/tmp/chc_cookies.txt',
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => $headers,
]);

// --- Sesion inicial: obtener VIEWSTATE ---
function getViewstateTokens(\CurlHandle $ch, string $url): array {
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    $html = curl_exec($ch);
    if (!$html) return [];
    return extractTokens($html);
}

function extractTokens(string $html): array {
    $tokens = [];
    foreach (['__VIEWSTATE','__VIEWSTATEGENERATOR','__EVENTVALIDATION'] as $field) {
        if (preg_match('/<input[^>]*id="' . $field . '"[^>]*value="([^"]*)"/', $html, $m)) {
            $tokens[$field] = $m[1];
        }
    }
    return $tokens;
}

function extractResult(string $html): ?array {
    $result = [];
    // Fecha de nacimiento: lblfechaNacimiento
    if (preg_match('/<span[^>]*id="lblfechaNacimiento"[^>]*>([^<]+)</', $html, $m)) {
        $result['fecha_nac_raw'] = trim($m[1]);
    }
    // Nombre completo
    if (preg_match('/<span[^>]*id="lblnombrecompleto"[^>]*>([^<]+)</', $html, $m)) {
        $result['nombre'] = trim($m[1]);
    }
    return $result['fecha_nac_raw'] ?? null ? $result : null;
}

function parsefecha(string $raw): ?string {
    // Formato TSE: "10/10/1980" → "1980-10-10"
    if (preg_match('#(\d{1,2})/(\d{1,2})/(\d{4})#', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    return null;
}

// Obtener tokens iniciales
out('Abriendo sesion con el TSE...');
$tokens = getViewstateTokens($ch, $baseUrl);
if (!isset($tokens['__VIEWSTATE'])) {
    err('No se pudieron obtener los tokens VIEWSTATE del TSE.');
    exit(1);
}
out('Sesion OK. Iniciando consultas...');

// --- Preparar UPDATE ---
$updateStmt = $pdo->prepare(
    'UPDATE voters SET fecha_nac = ?, chc_consultado_at = NOW() WHERE cedula = ?'
);
$markStmt = $pdo->prepare(
    'UPDATE voters SET chc_consultado_at = NOW() WHERE cedula = ?'
);

$ok = 0; $notFound = 0; $errors = 0; $sessionRefresh = 0;

foreach ($cedulas as $i => $cedula) {
    // Renovar sesion cada 200 requests para evitar token expirado
    if ($i > 0 && $i % 200 === 0) {
        $tokens = getViewstateTokens($ch, $baseUrl);
        $sessionRefresh++;
        out("  Session refresh #{$sessionRefresh} (req {$i})");
    }

    // Construir POST
    $postData = http_build_query([
        '__LASTFOCUS'          => '',
        '__EVENTTARGET'        => '',
        '__EVENTARGUMENT'      => '',
        '__VIEWSTATE'          => $tokens['__VIEWSTATE'] ?? '',
        '__VIEWSTATEGENERATOR' => $tokens['__VIEWSTATEGENERATOR'] ?? '',
        '__EVENTVALIDATION'    => $tokens['__EVENTVALIDATION'] ?? '',
        'txtcedula'            => $cedula,
        'btnConsultaCedula'    => 'Consultar',
    ]);

    curl_setopt($ch, CURLOPT_URL, $baseUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $retries = 0;
    $html = false;
    while ($retries < 3 && $html === false) {
        $html = curl_exec($ch);
        if ($html === false) {
            $retries++;
            usleep(2000000); // 2 segundos si hay error de conexion
        }
    }

    if ($html === false) {
        err("Cedula {$cedula}: curl falló tras 3 intentos");
        $errors++;
        // Marcar como consultado para no quedar en loop infinito
        $markStmt->execute([$cedula]);
        continue;
    }

    // Extraer nuevos tokens del response (para el siguiente request)
    $newTokens = extractTokens($html);
    if ($newTokens) $tokens = $newTokens;

    // Verificar si fue bloqueado por WAF (HTML muy corto o sin resultado)
    if (strlen($html) < 3000) {
        err("Cedula {$cedula}: respuesta sospechosamente corta (" . strlen($html) . " bytes) — posible bloqueo WAF");
        // Pausa larga y renovar sesion
        sleep(10);
        $tokens = getViewstateTokens($ch, $baseUrl);
        $errors++;
        continue; // No marcar como consultado: reintentar en la siguiente ejecucion
    }

    // Extraer resultado
    $result = extractResult($html);
    if ($result) {
        $fechaIso = parsefecha($result['fecha_nac_raw']);
        if ($fechaIso) {
            $updateStmt->execute([$fechaIso, $cedula]);
            $ok++;
        } else {
            $markStmt->execute([$cedula]);
            $notFound++;
        }
    } else {
        // Cedula no encontrada o error en la pagina
        $markStmt->execute([$cedula]);
        $notFound++;
    }

    // Log cada 50 registros
    if (($i + 1) % 50 === 0) {
        out(sprintf('  %d/%d procesados — OK:%d NF:%d ERR:%d',
            $i + 1, count($cedulas), $ok, $notFound, $errors));
    }

    // Delay con jitter: delayMs ± 20%
    $jitter = (int)($delayMs * 0.2);
    usleep((random_int($delayMs - $jitter, $delayMs + $jitter)) * 1000);
}

curl_close($ch);

out(sprintf(
    'Completado. OK: %d | Sin resultado: %d | Errores: %d | Refrescos de sesion: %d',
    $ok, $notFound, $errors, $sessionRefresh
));

// Mostrar cuantos quedan pendientes
$cntStmt->execute($whereParams);
$pendientes = (int)$cntStmt->fetchColumn();
out("Pendientes restantes ({$label}): " . number_format($pendientes));
