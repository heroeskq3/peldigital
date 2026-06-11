#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * test_padron_batch.php
 *
 * Prueba el parser del padrón con una muestra de líneas sin escribir
 * nada en la base de datos. Útil para validar el parseo antes de
 * correr el import completo.
 *
 * Uso:
 *   php scripts/test_padron_batch.php --file=/ruta/PADRON.TXT [--lines=100] [--offset=0]
 *
 * Opciones:
 *   --file     Ruta al PADRON_COMPLETO.TXT
 *   --lines    Cuántas líneas analizar (default: 100)
 *   --offset   Empezar desde esta línea (default: 0, inicio del archivo)
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/parsers/PadronTSEParser.php';

$opts   = getopt('', ['file:', 'lines:', 'offset:']);
$file   = $opts['file'] ?? '';
$limit  = max(1, (int)($opts['lines']  ?? 100));
$offset = max(0, (int)($opts['offset'] ?? 0));

if ($file === '') {
    fwrite(STDERR, "Uso: php scripts/test_padron_batch.php --file=/ruta/PADRON.TXT [--lines=100] [--offset=0]\n");
    exit(1);
}

$file = realpath($file);
if ($file === false || !is_file($file)) {
    fwrite(STDERR, "No existe el archivo: {$file}\n");
    exit(1);
}

echo "============================================================\n";
echo " TEST PARSER PADRÓN TSE\n";
echo " Archivo : {$file}\n";
echo " Muestra : {$limit} líneas desde offset {$offset}\n";
echo "============================================================\n\n";

$parser = new PadronTSEParser();

$handle = fopen($file, 'rb');
if ($handle === false) {
    fwrite(STDERR, "No se pudo abrir el archivo.\n");
    exit(1);
}

// Saltar líneas hasta el offset
for ($i = 0; $i < $offset; $i++) {
    if (fgets($handle) === false) {
        break;
    }
}

$read     = 0;
$ok       = 0;
$errLines = [];

// Campos que vienen del TSE y que NO deberían resolverse
$nullProvince  = 0;
$nullCanton    = 0;
$nullDistrict  = 0;
$nullFechaCad  = 0;

printf("%-12s %-25s %-22s %-22s %-10s %-7s %s\n",
    'CEDULA', 'NOMBRE', 'APELLIDO1', 'APELLIDO2', 'FECHA_CADUC', 'JUNTA', 'PROV/CANTON/DIST');
echo str_repeat('-', 130) . "\n";

while ($read < $limit && ($line = fgets($handle)) !== false) {
    $line = rtrim($line, "\r\n");
    if ($line === '') {
        continue;
    }
    $read++;

    $row = $parser->parseLine($line);
    if ($row === null) {
        $errLines[] = $read + $offset;
        continue;
    }

    $ok++;
    if ($row['province_id'] === null) $nullProvince++;
    if ($row['canton_id']   === null) $nullCanton++;
    if ($row['district_id'] === null) $nullDistrict++;
    if ($row['fecha_caduc'] === null) $nullFechaCad++;

    $geo = sprintf('%s/%s/%s',
        $row['province_id'] ?? '?',
        $row['canton_id']   ?? '?',
        $row['district_id'] ?? '?'
    );

    printf("%-12s %-25s %-22s %-22s %-10s %-7s %s\n",
        $row['cedula'],
        mb_substr($row['nombre'], 0, 24),
        mb_substr($row['apellido1'], 0, 21),
        mb_substr($row['apellido2'] ?? '', 0, 21),
        $row['fecha_caduc'] ?? '—',
        $row['junta'] ?? '—',
        $geo
    );
}
fclose($handle);

echo "\n" . str_repeat('=', 130) . "\n";
printf("Líneas leídas : %d\n", $read);
printf("OK            : %d (%.1f%%)\n", $ok, $read > 0 ? ($ok / $read * 100) : 0);
printf("Errores parse : %d\n", count($errLines));
if ($errLines) {
    echo 'Líneas con error: ' . implode(', ', array_slice($errLines, 0, 20));
    if (count($errLines) > 20) echo ' ...';
    echo "\n";
}
echo "\n-- Resolución geográfica --\n";
printf("Sin province_id : %d\n", $nullProvince);
printf("Sin canton_id   : %d\n", $nullCanton);
printf("Sin district_id : %d  (requiere import_distelec.php previo)\n", $nullDistrict);
printf("Sin fecha_caduc : %d\n", $nullFechaCad);
echo str_repeat('=', 130) . "\n";
exit(count($errLines) > 0 ? 1 : 0);
