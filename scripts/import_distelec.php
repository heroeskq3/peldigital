#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * import_distelec.php
 *
 * Puebla provinces, cantons y districts en la BD usando el archivo
 * DISTELEC.TXT del TSE (padrón 2026).
 *
 * Uso:
 *   php scripts/import_distelec.php --file=/ruta/DISTELEC.TXT
 *
 * Formato de DISTELEC.TXT (campos separados por coma, ancho fijo):
 *   CODELE(6), PROVINCIA(10), CANTON(20), DISTRITO(34)
 *
 * El CODELE se descompone como:
 *   dígito 1      = provincia (1-8, donde 8 = exterior/consulados)
 *   dígitos 2-3   = cantón dentro de la provincia (01-XX)
 *   dígitos 4-6   = distrito dentro del cantón (001-XXX)
 *
 * canton_id en DB = provincia * 100 + num_canton  (ej: 104, 216, 801)
 */

require_once __DIR__ . '/../lib/db.php';

function out(string $m): void { fwrite(STDOUT, '[' . date('H:i:s') . "] {$m}\n"); }
function err(string $m): void { fwrite(STDERR, '[ERROR] ' . $m . "\n"); }

$opts = getopt('', ['file:']);
$filePath = $opts['file'] ?? '';

if ($filePath === '') {
    err('Uso: php scripts/import_distelec.php --file=/ruta/DISTELEC.TXT');
    exit(1);
}

$filePath = realpath($filePath);
if ($filePath === false || !is_file($filePath)) {
    err('No existe el archivo: ' . ($opts['file'] ?? ''));
    exit(1);
}

// ---- Mapeo de nombre de provincia (según TSE) → ID ----
const PROVINCE_NAMES = [
    'SAN JOSE'    => 1,
    'ALAJUELA'    => 2,
    'CARTAGO'     => 3,
    'HEREDIA'     => 4,
    'GUANACASTE'  => 5,
    'PUNTARENAS'  => 6,
    'LIMON'       => 7,
    'CONSULADO'   => 8,
];

// ---- Nombres oficiales para provinces.name ----
const PROVINCE_LABELS = [
    1 => 'San José',
    2 => 'Alajuela',
    3 => 'Cartago',
    4 => 'Heredia',
    5 => 'Guanacaste',
    6 => 'Puntarenas',
    7 => 'Limón',
    8 => 'Exterior',
];

out("Leyendo DISTELEC: {$filePath}");

$handle = fopen($filePath, 'rb');
if ($handle === false) {
    err('No se pudo abrir el archivo.');
    exit(1);
}

// Acumular cantones y distritos únicos
$cantons   = [];  // canton_id → ['province_id' => int, 'name' => string]
$districts = [];  // codelec   → ['canton_id'  => int, 'name' => string]

$lineNum = 0;
$parseErrors = 0;

while (($line = fgets($handle)) !== false) {
    $line = rtrim($line, "\r\n");
    $lineNum++;
    if ($line === '') {
        continue;
    }

    // El archivo del TSE viene en ISO-8859-1
    $line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');

    $parts = explode(',', $line, 4);
    if (count($parts) < 4) {
        $parseErrors++;
        continue;
    }

    $codelec      = trim($parts[0]);
    $provinceName = strtoupper(trim($parts[1]));
    $cantonName   = mb_convert_case(mb_strtolower(trim($parts[2])), MB_CASE_TITLE, 'UTF-8');
    $districtName = mb_convert_case(mb_strtolower(trim($parts[3])), MB_CASE_TITLE, 'UTF-8');

    if (strlen($codelec) !== 6 || !ctype_digit($codelec)) {
        $parseErrors++;
        continue;
    }

    $provinceDigit = (int)$codelec[0];
    $cantonNum     = (int)substr($codelec, 1, 2);
    $cantonId      = $provinceDigit * 100 + $cantonNum;

    $provinceId = PROVINCE_NAMES[$provinceName] ?? $provinceDigit;

    if (!isset($cantons[$cantonId])) {
        $cantons[$cantonId] = [
            'province_id' => $provinceId,
            'name'        => $cantonName,
        ];
    }

    $districts[$codelec] = [
        'canton_id' => $cantonId,
        'name'      => $districtName,
    ];
}
fclose($handle);

out("Líneas procesadas: {$lineNum}  |  Errores parse: {$parseErrors}");
out('Cantones únicos: ' . count($cantons) . '  |  Distritos: ' . count($districts));

$pdo = dbData();

// ---- Upsert provinces (por si falta alguna) ----
out('Actualizando provinces...');
$stmtProv = $pdo->prepare(
    'INSERT INTO `provinces` (`id`, `name`) VALUES (:id, :name)
     ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)'
);
foreach (PROVINCE_LABELS as $id => $name) {
    $stmtProv->execute([':id' => $id, ':name' => $name]);
}

// ---- Upsert cantons ----
out('Importando cantones...');
$stmtCanton = $pdo->prepare(
    'INSERT INTO `cantons` (`id`, `province_id`, `name`) VALUES (:id, :province_id, :name)
     ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `province_id` = VALUES(`province_id`)'
);
$cantonsDone = 0;
foreach ($cantons as $cantonId => $data) {
    $stmtCanton->execute([
        ':id'          => $cantonId,
        ':province_id' => $data['province_id'],
        ':name'        => $data['name'],
    ]);
    $cantonsDone++;
}
out("Cantones insertados/actualizados: {$cantonsDone}");

// ---- Upsert districts ----
out('Importando distritos...');

// Cargar IDs de cantones válidos para validar FK
$validCantons = array_flip(
    $pdo->query('SELECT id FROM cantons')->fetchAll(\PDO::FETCH_COLUMN) ?: []
);

$stmtDist = $pdo->prepare(
    'INSERT INTO `districts` (`canton_id`, `name`, `codelec`) VALUES (:canton_id, :name, :codelec)
     ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `canton_id` = VALUES(`canton_id`)'
);

$distDone   = 0;
$distErrors = 0;

foreach ($districts as $codelec => $data) {
    if (!isset($validCantons[$data['canton_id']])) {
        $distErrors++;
        continue;
    }
    try {
        $stmtDist->execute([
            ':canton_id' => $data['canton_id'],
            ':name'      => $data['name'],
            ':codelec'   => $codelec,
        ]);
        $distDone++;
    } catch (\Throwable $e) {
        $distErrors++;
    }
}

out("Distritos insertados/actualizados: {$distDone}  |  Errores: {$distErrors}");
out('Importación DISTELEC completada.');
exit(0);
