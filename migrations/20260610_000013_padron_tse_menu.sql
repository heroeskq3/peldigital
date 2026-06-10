-- Categoría separada para los reportes estilo TSE del padrón
-- (Segmentación Electoral #5, Distritos Electorales #8, Juntas Electorales #9)
-- Se muestra como menú padre "Padrón" en la barra de navegación,
-- independiente del menú padre "Análisis".

INSERT INTO report_categories (slug, name, icon, description, sort_order)
VALUES ('padron-tse', 'Padrón', 'bi-person-vcard-fill',
        'Vistas del padrón electoral estilo TSE', 5)
ON DUPLICATE KEY UPDATE
    name        = VALUES(name),
    icon        = VALUES(icon),
    description = VALUES(description),
    sort_order  = VALUES(sort_order);

UPDATE reports
SET category_id = (SELECT id FROM report_categories WHERE slug = 'padron-tse')
WHERE id IN (5, 8, 9);
