-- Agrega la categoría "Movilización Territorial" y sus 3 reportes:
-- Locales de Votación, Densidad Electoral, Circunscripciones Legislativas.

INSERT IGNORE INTO `report_categories` (id, name, slug, icon, sort_order)
VALUES (8, 'Movilización Territorial', 'movilizacion-territorial', 'bi-geo-fill', 40);

INSERT IGNORE INTO `reports`
    (id, category_id, name, short_name, slug, icon, php_file, sort_order, status)
VALUES
    (10, 8, 'Locales de Votación',           'Locales',          'locales-votacion',  'bi-building',       'locales-votacion.php',    10, 'active'),
    (11, 8, 'Densidad Electoral por Local',  'Densidad',         'densidad-electoral','bi-bar-chart-steps','densidad-electoral.php',   20, 'active'),
    (12, 8, 'Circunscripciones Legislativas','Circunscripciones','circunscripciones', 'bi-diagram-3',      'circunscripciones.php',    30, 'active');
