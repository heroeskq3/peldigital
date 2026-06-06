#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/parsers/PadronTSEParser.php';

function out(string $m): void { fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] {$m}\n"); }
function err(string $m): void { fwrite(STDERR, '[ERROR] ' . $m . "\n"); }

function findFileByPattern(string $dir, string $pattern): ?string {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isFile() && preg_match($pattern, $file->getFilename())) {
            return $file->getPathname();
        }
    }
    return null;
}

$options = getopt('', ['zip:']);
$zipArg = $options['zip'] ?? '';
if ($zipArg === '') {
    err('Uso: php scripts/sync_padron_local.php --zip=/ruta/padron.zip');
    exit(1);
}

$zipPath = realpath($zipArg);
if ($zipPath === false || !is_file($zipPath)) {
    err('No existe ZIP: ' . $zipArg);
    exit(1);
}

$runId = 0;
$pdo = dbConnect();

try {
    out('Iniciando carga local desde ZIP...');

    $zipSha      = hash_file('sha256', $zipPath);
    $zipSize     = filesize($zipPath) ?: 0;
    $zipFilename = basename($zipPath);

    $exists = $pdo->prepare('SELECT id FROM padron_sync_runs WHERE zip_sha256 = ? LIMIT 1');
    $exists->execute([$zipSha]);
    if ($exists->fetch()) {
        out('ZIP ya procesado antes (SHA-256 repetido). Se omite.');
        exit(0);
    }

    $ins = $pdo->prepare(
        'INSERT INTO padron_sync_runs (source_page_url, source_zip_url, zip_filename, zip_sha256, zip_size_bytes, status, started_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $ins->execute(['local-manual', $zipPath, $zipFilename, $zipSha, $zipSize, 'processing']);
    $runId = (int)$pdo->lastInsertId();

    $runDir = __DIR__ . '/../data/imports/padron_' . date('Ymd_His') . '_' . $runId;
    if (!is_dir($runDir) && !mkdir($runDir, 0775, true) && !is_dir($runDir)) {
        throw new RuntimeException('No se pudo crear carpeta temporal: ' . $runDir);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('No se pudo abrir ZIP local.');
    }
    if (!$zip->extractTo($runDir)) {
        $zip->close();
        throw new RuntimeException('No se pudo extraer ZIP local.');
    }
    $zip->close();

    $padronFile = findFileByPattern($runDir, '/^PADRON.*\.TXT$/i');
    $leameFile  = findFileByPattern($runDir, '/^LEAME\.TXT$/i');

    if ($padronFile === null) {
        throw new RuntimeException('No se encontró PADRON.TXT en el ZIP.');
    }

    if ($leameFile !== null) {
        $leameText = mb_convert_encoding((string)file_get_contents($leameFile), 'UTF-8', 'ISO-8859-1');
        $pdo->prepare('UPDATE padron_sync_runs SET leame_text = ? WHERE id = ?')
            ->execute([$leameText, $runId]);
    }

    $result = (new PadronTSEParser())->parseFile($padronFile, $runId, 1000, true);

    $pdo->prepare(
        'UPDATE padron_sync_runs SET status = ?, message = ?, records_ok = ?, records_error = ?, finished_at = NOW() WHERE id = ?'
    )->execute(['completed', 'Carga local completada', $result['records_ok'], $result['records_error'], $runId]);

    out('Completado. records_ok=' . $result['records_ok'] . ' records_error=' . $result['records_error']);
    exit(0);
} catch (Throwable $e) {
    if ($runId > 0) {
        $pdo->prepare('UPDATE padron_sync_runs SET status = ?, message = ?, finished_at = NOW() WHERE id = ?')
            ->execute(['failed', $e->getMessage(), $runId]);
    }
    err($e->getMessage());
    exit(1);
}
