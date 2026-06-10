-- Migración: catálogo de reportes y categorías
-- Crea las tablas report_categories y reports con datos semilla.

CREATE TABLE IF NOT EXISTS report_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(80)  NOT NULL UNIQUE,
    name        VARCHAR(120) NOT NULL,
    icon        VARCHAR(60)  NOT NULL DEFAULT 'bi-folder',
    description TEXT,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reports (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id     INT UNSIGNED NOT NULL,
    slug            VARCHAR(80)  NOT NULL UNIQUE,
    name            VARCHAR(180) NOT NULL,
    short_name      VARCHAR(80)  NOT NULL,
    description     TEXT,
    icon            VARCHAR(60)  NOT NULL DEFAULT 'bi-bar-chart',
    status          ENUM('active','partial','pending') NOT NULL DEFAULT 'pending',
    php_file        VARCHAR(200),        -- ruta relativa a includes/reports/
    js_report_id    VARCHAR(80),         -- valor del data-report= en el HTML
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    requires_data   JSON,                -- descripción de datos externos necesarios
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reports_category FOREIGN KEY (category_id) REFERENCES report_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Categorías ───────────────────────────────────────────────────────────────

INSERT INTO report_categories (id, slug, name, icon, description, sort_order) VALUES
(1, 'padron-electoral',  'Padrón Electoral',       'bi-person-vcard',   'Reportes basados en el padrón electoral del TSE 2026',              10),
(2, 'participacion',     'Participación Electoral', 'bi-check-square',   'Reportes de participación, votos emitidos y abstención',            20),
(3, 'segmentacion',      'Segmentación Electoral',  'bi-people',         'Reportes de segmentación por sexo, edad y distrito electoral',      30),
(4, 'territorial',       'Análisis Territorial',    'bi-map',            'Reportes territoriales con resultados electorales históricos',      40),
(5, 'estrategico',       'Indicadores Estratégicos','bi-trophy',         'KPIs consolidados y comparativos multi-elección',                  50)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ─── Reportes ─────────────────────────────────────────────────────────────────

INSERT INTO reports
    (id, category_id, slug, name, short_name, description, icon, status, php_file, js_report_id, sort_order, requires_data, notes)
VALUES
-- Padrón Electoral
(1, 1,
 'distribucion-territorial',
 'Distribución Territorial del Padrón Electoral',
 'Distribución Territorial',
 'Mapa interactivo de electores inscritos por provincia, cantón y distrito. Drill-down hasta nivel de distrito con panel de detalle y comparativa de métricas.',
 'bi-map',
 'active',
 'padron-distribucion.php',
 'padron-distribucion',
 10,
 NULL,
 'Reporte 0 — operativo desde jun-2026'),

(2, 1,
 'jrv-inscritos',
 'Distribución del Padrón por JRV',
 'Distribución Padrón / JRV',
 'Tabla paginada de las 7 154 juntas receptoras de votos con inscritos, ranking y filtros por territorio.',
 'bi-list-ol',
 'active',
 'jrv-inscritos.php',
 'jrv-inscritos',
 20,
 NULL,
 'Reporte 4a — operativo desde jun-2026'),

(3, 1,
 'jrv-analisis-estrategico',
 'Análisis Estratégico de JRV',
 'Análisis Estratégico · JRV',
 'Indicadores estadísticos, distribución por cuartiles y detección de juntas atípicas (p.ej. CAI). Incluye histograma y ranking nacional.',
 'bi-bar-chart-steps',
 'active',
 'jrv-analisis.php',
 'jrv-analisis',
 30,
 NULL,
 'Reporte 4b — operativo desde jun-2026'),

-- Participación Electoral
(4, 2,
 'participacion-electoral',
 'Participación Electoral',
 'Participación Electoral',
 'Porcentaje de participación y abstención por provincia, cantón y JRV. Requiere datos oficiales de votos emitidos del TSE.',
 'bi-check-square',
 'pending',
 NULL,
 NULL,
 10,
 '["Votos emitidos por JRV por elección", "Fecha de cada elección", "Resultados por partido/candidato por JRV (opcional)"]',
 'Reporte 1 — bloqueado por datos TSE'),

-- Segmentación Electoral
(5, 3,
 'segmentacion-electoral',
 'Segmentación Electoral',
 'Segmentación Electoral',
 'Distribución por sexo, rangos de edad y distrito electoral. Parcialmente construible cuando se carguen sexo y fecha_nac del padrón.',
 'bi-people',
 'partial',
 NULL,
 NULL,
 10,
 '["Campo sexo en voters (actualmente NULL)", "Campo fecha_nac en voters (actualmente NULL)", "electoral_district_id asignado en voters"]',
 'Reporte 2 — parcial posible cuando se cargue sexo/fecha_nac'),

-- Análisis Territorial
(6, 4,
 'analisis-territorial',
 'Análisis Territorial',
 'Análisis Territorial',
 'Resultados electorales históricos por territorio. Comparativos entre elecciones municipales y nacionales.',
 'bi-pin-map',
 'partial',
 NULL,
 NULL,
 10,
 '["Resultados electorales históricos por territorio", "Catálogo real de polling_places (~7000 registros)"]',
 'Reporte 3 — parcial posible cuando se carguen resultados históricos'),

-- Indicadores Estratégicos
(7, 5,
 'indicadores-estrategicos',
 'Indicadores Estratégicos',
 'Indicadores Estratégicos',
 'KPIs consolidados y comparativos multi-elección. Depende de los reportes de participación y segmentación.',
 'bi-trophy',
 'pending',
 NULL,
 NULL,
 10,
 '["Completar reportes 1 a 4 primero"]',
 'Reporte 5 — depende de reportes 1-4')

ON DUPLICATE KEY UPDATE
    category_id=VALUES(category_id),
    name=VALUES(name),
    short_name=VALUES(short_name),
    description=VALUES(description),
    icon=VALUES(icon),
    status=VALUES(status),
    php_file=VALUES(php_file),
    js_report_id=VALUES(js_report_id),
    sort_order=VALUES(sort_order),
    requires_data=VALUES(requires_data),
    notes=VALUES(notes);
