-- 20260610_000008_parties_catalog.sql
-- Catálogo de partidos políticos con sus códigos TSE internos.
-- Los códigos provienen del JSON AVR del TSE (campo cP en votos_por_partido).
-- Fuente: cruce AVR2026 (Presidencia) con resultados Wikipedia + AVR2024 (Municipales).

CREATE TABLE IF NOT EXISTS parties (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    tse_code    SMALLINT UNSIGNED NOT NULL,  -- código numérico TSE en el AVR
    abbrev      VARCHAR(16)     NOT NULL,
    name        VARCHAR(120)    NOT NULL,
    scope       ENUM('national','cantonal','coalition') NOT NULL DEFAULT 'national',
    verified    TINYINT(1)      NOT NULL DEFAULT 0,  -- 1 = verificado contra fuente oficial
    notes       VARCHAR(255)    NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tse_code (tse_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Partidos nacionales — Presidencia 2026 (verificados contra Wikipedia ES/EN)
-- Orden por votos obtenidos en primera ronda 2026-02-02
INSERT INTO parties (tse_code, abbrev, name, scope, verified, notes) VALUES
  (373, 'PPSO',  'Partido Pueblo Soberano',                  'national', 1, 'Ganador 2026 (48.5%). Laura Fernández.'),
  (4,   'PLN',   'Partido Liberación Nacional',              'national', 1, '2do 2026 (33.4%). Histórico fundado 1951.'),
  (428, 'CAC',   'Coalición Agenda Ciudadana',               'coalition',1, '3ro 2026 (4.9%). Claudia Dobles.'),
  (73,  'FA',    'Frente Amplio',                            'national', 1, '4to 2026 (3.8%). 7 diputados.'),
  (6,   'PUSC',  'Partido Unidad Social Cristiana',          'national', 1, '5to 2026 (2.7%). Histórico fundado 1983.'),
  (267, 'PNR',   'Partido Nueva República',                  'national', 1, '6to 2026 (2.2%). Fabricio Alvarado.'),
  (417, 'HA',    'Hacia Adelante',                           'national', 0, '7mo 2026 (1.8%). Nombre pendiente verificación.'),
  (241, 'UP',    'Unidos Podemos',                           'national', 1, '8vo 2026 (0.9%).'),
  (221, 'PAC',   'Partido Acción Ciudadana',                 'national', 0, '9no 2026 (~0.5%). Carlos Alvarado 2018-2022.'),
  (245, 'PLP',   'Partido Liberal Progresista',              'national', 1, '10mo 2026 (0.4%).'),
  (149, 'PPSD',  'Partido Progreso Social Democrático',      'national', 1, '11vo 2026 (0.3%). Rodrigo Chaves 2022.'),
  (150, 'PNG',   'Partido Nueva Generación',                 'national', 1, '12vo 2026 (0.2%).'),
  (13,  'PDSC',  'Partido Centro Democrático Social',        'national', 0, '13vo 2026 (0.2%). Código histórico bajo.'),
  (280, 'PIN',   'Partido Integración Nacional',             'national', 1, '14vo 2026 (0.1%).'),
  (229, 'PCO',   'Partido Clase Obrera',                     'national', 0, '15vo 2026 (0.1%). Nombre pendiente verificación.'),
  (278, 'PRSJ',  'Partido Renovación Social Cristiana',      'national', 0, '16vo 2026 (0.08%). Nombre pendiente verificación.'),
  (403, 'PUDC',  'Partido Unión Demócrata Costarricense',    'national', 0, '17vo 2026 (0.07%). Nombre pendiente verificación.'),
  (405, 'PEN',   'Partido Esperanza Nacional',               'national', 0, '18vo 2026 (0.07%). Nombre pendiente verificación.'),
  (249, 'PEL',   'Partido Esperanza y Libertad',             'national', 1, '19vo 2026 (0.06%). ~1,415 votos. PEL — cliente.'),
  (342, 'PCRA',  'Partido Costa Rica Aquí',                  'national', 0, '20vo 2026 (0.06%). Nombre pendiente verificación.'),
  -- Municipales 2024 (no participaron en presidenciales o participaron a nivel cantonal)
  (59,  'PRN',   'Partido Restauración Nacional',            'national', 0, 'Municipales 2024. Posiblemente fusionado o rebautizado.'),
  (164, 'PM1',   'Partido municipal código 164',             'cantonal', 0, 'Solo municipales 2024.'),
  (273, 'PM2',   'Partido municipal código 273',             'cantonal', 0, 'Solo municipales 2024.'),
  (295, 'PM3',   'Partido municipal código 295',             'cantonal', 0, 'Solo municipales 2024.'),
  (374, 'PM4',   'Partido municipal código 374',             'cantonal', 0, 'Solo municipales 2024.'),
  (377, 'PM5',   'Partido municipal código 377',             'cantonal', 0, 'Solo municipales 2024.'),
  (232, 'PRSC',  'Partido Republicano Social Cristiano',     'national', 0, 'Municipales 2024 (Cartago). Nombre pendiente verificación.'),
  (233, 'PM6',   'Partido municipal código 233',             'cantonal', 0, 'Solo Guanacaste 2024.'),
  (253, 'PM7',   'Partido municipal código 253',             'cantonal', 0, 'Solo municipales 2024.'),
  (297, 'PM8',   'Partido municipal código 297',             'cantonal', 0, 'Solo municipales 2024.'),
  (393, 'PM9',   'Partido municipal código 393',             'cantonal', 0, 'Solo municipales 2024.'),
  (43,  'PM10',  'Partido municipal código 43',              'cantonal', 0, 'Solo municipales 2024.'),
  (341, 'PM11',  'Partido municipal código 341',             'cantonal', 0, 'Solo municipales 2024.');
