-- Corrección: inserta la categoría y reportes de Movilización Territorial
-- usando slug como clave natural (sin ID fijo) para que funcione en cualquier
-- servidor independientemente de los auto-increments existentes.

-- 1. Categoría (si no existe ya por slug)
INSERT INTO `report_categories` (name, slug, icon, sort_order)
SELECT 'Movilización Territorial', 'movilizacion-territorial', 'bi-geo-fill', 40
WHERE NOT EXISTS (
    SELECT 1 FROM `report_categories` WHERE slug = 'movilizacion-territorial'
);

-- 2. Reportes (lookup de category_id por slug de la categoría)
INSERT INTO `reports` (category_id, name, short_name, slug, icon, php_file, sort_order, status)
SELECT
    (SELECT id FROM report_categories WHERE slug = 'movilizacion-territorial'),
    'Locales de Votación', 'Locales', 'locales-votacion',
    'bi-building', 'locales-votacion.php', 10, 'active'
WHERE NOT EXISTS (SELECT 1 FROM `reports` WHERE slug = 'locales-votacion');

INSERT INTO `reports` (category_id, name, short_name, slug, icon, php_file, sort_order, status)
SELECT
    (SELECT id FROM report_categories WHERE slug = 'movilizacion-territorial'),
    'Densidad Electoral por Local', 'Densidad', 'densidad-electoral',
    'bi-bar-chart-steps', 'densidad-electoral.php', 20, 'active'
WHERE NOT EXISTS (SELECT 1 FROM `reports` WHERE slug = 'densidad-electoral');

INSERT INTO `reports` (category_id, name, short_name, slug, icon, php_file, sort_order, status)
SELECT
    (SELECT id FROM report_categories WHERE slug = 'movilizacion-territorial'),
    'Circunscripciones Legislativas', 'Circunscripciones', 'circunscripciones',
    'bi-diagram-3', 'circunscripciones.php', 30, 'active'
WHERE NOT EXISTS (SELECT 1 FROM `reports` WHERE slug = 'circunscripciones');
