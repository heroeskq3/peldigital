#!/usr/bin/env php
<?php
/**
 * scripts/import_resultados.php — Importa resultados electorales del TSE (AVR).
 *
 * Uso:
 *   php scripts/import_resultados.php --json=/ruta/avr2026.json [--label="Nacionales 2026 — Presidencia"]
 *
 * Descarga manual del JSON:
 *   1. Abrir https://www.tse.go.cr/AVR2026/api/resultados/ en el navegador
 *   2. Guardar la respuesta como avr2026.json
 *   3. Ejecutar este script con --json=/ruta/avr2026.json
 *
 * El JSON también puede descargarse con:
 *   curl "https://www.tse.go.cr/AVR2026/api/resultados/" \
 *     -H "Referer: https://www.tse.go.cr/SVR2026/" > avr2026.json
 * (Nota: el TSE tiene protección anti-bot que puede bloquear curl desde servidores.)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/parsers/AvrParser.php';

function out(string $m): void { fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] {$m}\n"); }
function err(string $m): void { fwrite(STDERR, '[ERROR] ' . $m . "\n"); }

$options      = getopt('', ['json:', 'label:', 'type:']);
$jsonArg      = trim($options['json']  ?? '');
$label        = trim($options['label'] ?? '');
$electionType = strtoupper(trim($options['type'] ?? 'all'));  // P, D, A, all

if ($jsonArg === '') {
    err('Uso: php scripts/import_resultados.php --json=/ruta/avr.json [--label="..."] [--type=P|D|A|all]');
    err('  --type=P  → solo Presidencia');
    err('  --type=D  → solo Diputados');
    err('  --type=A  → solo Alcaldes');
    err('  --type=all → todos los tipos (default)');
    exit(1);
}

$jsonPath = realpath($jsonArg);
if ($jsonPath === false || !is_file($jsonPath)) {
    err('No existe el archivo JSON: ' . $jsonArg);
    exit(1);
}

$runId = 0;
$pdo   = dbData();

try {
    out('Iniciando importación de resultados electorales...');

    $sha      = hash_file('sha256', $jsonPath);
    $filesize = filesize($jsonPath) ?: 0;
    $filename = basename($jsonPath);

    // Evitar duplicados
    $exists = $pdo->prepare('SELECT id FROM election_sync_runs WHERE file_sha256 = ? LIMIT 1');
    $exists->execute([$sha]);
    if ($exists->fetch()) {
        out('Archivo ya procesado (SHA-256 repetido). Se omite.');
        exit(0);
    }

    // Crear registro de corrida
    $ins = $pdo->prepare('INSERT INTO election_sync_runs
        (filename, file_sha256, election_label, status, started_at)
        VALUES (?, ?, ?, ?, NOW())');
    $ins->execute([$filename, $sha, $label ?: $filename, 'processing']);
    $runId = (int)$pdo->lastInsertId();

    // Parsear
    $typeDesc = $electionType === 'all' ? 'todos los tipos' : "tipo={$electionType}";
    out("Parseando {$filename} (" . number_format($filesize) . " bytes, {$typeDesc})...");
    $parser = new AvrParser();
    $result = $parser->parseFile($jsonPath, $electionType);
    if (!empty($result['types_found'])) {
        out("Tipos encontrados en el JSON: " . implode(', ', $result['types_found']));
    }

    // Actualizar fecha y n_circunsc en el run
    $pdo->prepare('UPDATE election_sync_runs SET election_date=?, n_circunsc=? WHERE id=?')
        ->execute([$result['date'], $result['n_circunsc'], $runId]);

    out("Fecha elección: {$result['date']} · circunscripciones: {$result['n_circunsc']}");
    out("Filas parseadas: " . count($result['rows']));

    // Insertar en lotes de 500
    $batch     = [];
    $ok        = 0;
    $batchSize = 500;

    foreach ($result['rows'] as $row) {
        $batch[] = $row;
        if (count($batch) >= $batchSize) {
            $ok   += $parser->insertBatch($batch, $runId);
            $batch = [];
            fwrite(STDOUT, '.');
        }
    }
    if ($batch) {
        $ok += $parser->insertBatch($batch, $runId);
    }
    fwrite(STDOUT, "\n");

    // Finalizar run
    $pdo->prepare('UPDATE election_sync_runs SET status=?, message=?, records_ok=?, finished_at=NOW() WHERE id=?')
        ->execute(['completed', 'Importación completada', $ok, $runId]);

    out("Completado. Filas importadas: {$ok}");
    exit(0);

} catch (\Throwable $e) {
    if ($runId > 0) {
        $pdo->prepare('UPDATE election_sync_runs SET status=?, message=?, finished_at=NOW() WHERE id=?')
            ->execute(['failed', $e->getMessage(), $runId]);
    }
    err($e->getMessage());
    exit(1);
}
