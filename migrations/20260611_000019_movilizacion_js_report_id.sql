-- Corrige js_report_id en los reportes de Movilización Territorial.
-- El campo es requerido por reports.php para que el frontend sepa qué
-- componente JS activar. Sin él carga el mapa de distribución por defecto.

UPDATE `reports` SET js_report_id = 'locales-votacion'  WHERE slug = 'locales-votacion'  AND (js_report_id IS NULL OR js_report_id = '');
UPDATE `reports` SET js_report_id = 'densidad-electoral' WHERE slug = 'densidad-electoral' AND (js_report_id IS NULL OR js_report_id = '');
UPDATE `reports` SET js_report_id = 'circunscripciones'  WHERE slug = 'circunscripciones'  AND (js_report_id IS NULL OR js_report_id = '');
