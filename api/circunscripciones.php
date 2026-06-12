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

// juntas y locales desde summary_jrv (7K filas) — no desde voters (3.7M)
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
        COALESCE(sj.juntas,  0) AS juntas,
        COALESCE(sj.locales, 0) AS locales
    FROM electoral_districts ed
    JOIN provinces p ON p.id = ed.province_id
    JOIN summary_inscritos_provincia s ON s.province_id = p.id
    LEFT JOIN (
        SELECT province_id,
               COUNT(*)                        AS juntas,
               COUNT(DISTINCT polling_place_id) AS locales
        FROM   summary_jrv
        GROUP  BY province_id
    ) sj ON sj.province_id = p.id
    ORDER BY s.inscritos DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totales = [
    'inscritos'   => array_sum(array_column($rows, 'inscritos')),
    'inscritos_m' => array_sum(array_column($rows, 'inscritos_m')),
    'inscritos_f' => array_sum(array_column($rows, 'inscritos_f')),
    'inscritos_n' => array_sum(array_column($rows, 'inscritos_n')),
];

apiJson(['rows' => $rows, 'totales' => $totales]);
