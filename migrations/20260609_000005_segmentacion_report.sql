-- Migración: activar reporte Segmentación Territorial (#5)
-- Conecta el php_file y js_report_id para que reports.php lo sirva.
-- Status se mantiene 'partial' porque sexo/edad siguen pendientes del TSE.

UPDATE reports
SET
    php_file     = 'segmentacion.php',
    js_report_id = 'segmentacion',
    status       = 'partial',
    notes        = 'Reporte 2 parcial activo desde jun-2026. Territorial disponible - sexo/edad/distrito_electoral pendientes.'
WHERE slug = 'segmentacion-electoral';
