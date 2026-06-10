-- Inserta los reportes Distritos Electorales (#8) y Juntas Electorales (#9)
-- en la categoría padron-tse. Usa ON DUPLICATE KEY para ser idempotente.

INSERT INTO reports
    (id, category_id, slug, name, short_name, description, icon, status,
     php_file, js_report_id, sort_order, requires_data, notes)
VALUES
    (8,
     (SELECT id FROM report_categories WHERE slug = 'padron-tse'),
     'distritos-electorales',
     'Distritos Electorales',
     'Distritos Electorales',
     'Electorado por distrito administrativo con desglose por sexo y juntas receptoras',
     'bi-buildings',
     'active',
     'distritos-electorales.php',
     'distritos-electorales',
     22, NULL, NULL),
    (9,
     (SELECT id FROM report_categories WHERE slug = 'padron-tse'),
     'juntas-padronal',
     'Juntas Electorales',
     'Juntas Electorales',
     'Distribución de juntas receptoras de votos por nivel geográfico con rango filtrable',
     'bi-collection',
     'active',
     'juntas-padronal.php',
     'juntas-padronal',
     24, NULL, NULL)
ON DUPLICATE KEY UPDATE
    category_id  = VALUES(category_id),
    slug         = VALUES(slug),
    name         = VALUES(name),
    short_name   = VALUES(short_name),
    description  = VALUES(description),
    icon         = VALUES(icon),
    status       = VALUES(status),
    php_file     = VALUES(php_file),
    js_report_id = VALUES(js_report_id),
    sort_order   = VALUES(sort_order);
