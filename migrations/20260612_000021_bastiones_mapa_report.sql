-- Reporte "Mapa de Bastiones" en categoría "Estrategia Electoral"

INSERT INTO `reports` (category_id, name, short_name, slug, js_report_id, icon, php_file, sort_order, status)
SELECT
    (SELECT id FROM report_categories WHERE slug = 'estrategia-electoral'),
    'Mapa de Bastiones', 'Mapa Bastiones', 'bastiones-mapa', 'bastiones-mapa',
    'bi-map-fill', 'bastiones-mapa.php', 20, 'active'
WHERE NOT EXISTS (SELECT 1 FROM `reports` WHERE slug = 'bastiones-mapa');
