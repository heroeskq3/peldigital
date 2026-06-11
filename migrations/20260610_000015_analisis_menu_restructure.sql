-- ── Migración 015: Consolida menú bajo un único "Análisis" con 3 subcategorías ──
-- Antes: 6 categorías dispersas (padron-tse, padron-electoral, participacion,
--        segmentacion, territorial, estrategico)
-- Después: 3 categorías limpias bajo un único menú Análisis

-- 1. Renombrar cat 1 → "Padrón & Territorio" (recibe todos los reportes de padrón)
UPDATE report_categories
SET name='Padrón & Territorio', slug='padron-territorio',
    icon='bi-person-vcard-fill', sort_order=10
WHERE id = 1;

-- 2. Renombrar cat 4 → "Resultados & Estrategia" (resultados electorales + indicadores)
UPDATE report_categories
SET name='Resultados & Estrategia', slug='resultados-estrategia',
    icon='bi-bar-chart-fill', sort_order=30
WHERE id = 4;

-- 3. Mover reportes #5, #8, #9 de cat 6 (padron-tse) → cat 1 (padron-territorio)
UPDATE reports SET category_id=1, sort_order=40 WHERE id=5;   -- Segmentación
UPDATE reports SET category_id=1, sort_order=50 WHERE id=8;   -- Distritos Electorales
UPDATE reports SET category_id=1, sort_order=60 WHERE id=9;   -- Juntas Electorales

-- 4. Mover reporte #7 de cat 5 (estrategico) → cat 4 (resultados-estrategia)
UPDATE reports SET category_id=4, sort_order=20 WHERE id=7;

-- 5. Ajustar sort_order de reportes en cat 1 para que queden ordenados
UPDATE reports SET sort_order=10 WHERE id=1;
UPDATE reports SET sort_order=20 WHERE id=2;
UPDATE reports SET sort_order=30 WHERE id=3;

-- 6. Eliminar categorías vacías (cat 3: segmentacion, cat 5: estrategico, cat 6: padron-tse)
DELETE FROM report_categories WHERE id IN (3, 5, 6);
