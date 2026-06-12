-- Categoría "Estrategia Electoral" y reporte "Análisis de Bastiones"

INSERT INTO `report_categories` (name, slug, icon, sort_order)
SELECT 'Estrategia Electoral', 'estrategia-electoral', 'bi-trophy', 50
WHERE NOT EXISTS (
    SELECT 1 FROM `report_categories` WHERE slug = 'estrategia-electoral'
);

INSERT INTO `reports` (category_id, name, short_name, slug, js_report_id, icon, php_file, sort_order, status)
SELECT
    (SELECT id FROM report_categories WHERE slug = 'estrategia-electoral'),
    'Análisis de Bastiones', 'Bastiones', 'bastiones', 'bastiones',
    'bi-shield-fill', 'bastiones.php', 10, 'active'
WHERE NOT EXISTS (SELECT 1 FROM `reports` WHERE slug = 'bastiones');
