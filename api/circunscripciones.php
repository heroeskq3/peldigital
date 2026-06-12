<?php
/**
 * api/circunscripciones.php — Las 7 circunscripciones legislativas con inscritos por sexo.
 */
require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/api.php';

apiJsonHeaders();
$pdo = dbData();

$rows = $pdo->query("
    SELECT
        ed.id,
        ed.name         AS circunscripcion,
        p.name          AS provincia,
        p.id            AS province_id,
        s.inscritos,
        s.inscritos_m,
        s.inscritos_f,
        s.inscritos_n,
        s.pct_nacional,
        (SELECT COUNT(DISTINCT junta) FROM voters v
         WHERE v.electoral_district_id = ed.id
           AND v.province_id BETWEEN 1 AND 7) AS juntas,
        (SELECT COUNT(DISTINCT polling_place_id) FROM voters v
         WHERE v.electoral_district_id = ed.id
           AND v.polling_place_id IS NOT NULL) AS locales
    FROM electoral_districts ed
    JOIN provinces p ON p.id = ed.province_id
    JOIN summary_inscritos_provincia s ON s.province_id = p.id
    ORDER BY s.inscritos DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totales = [
    'inscritos'   => array_sum(array_column($rows, 'inscritos')),
    'inscritos_m' => array_sum(array_column($rows, 'inscritos_m')),
    'inscritos_f' => array_sum(array_column($rows, 'inscritos_f')),
    'inscritos_n' => array_sum(array_column($rows, 'inscritos_n')),
];

apiJson(['rows' => $rows, 'totales' => $totales]);
