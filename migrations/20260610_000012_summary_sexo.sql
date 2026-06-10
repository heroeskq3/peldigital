-- Agrega columnas de sexo (M/F/N) a las tablas de resumen de inscritos.
-- Permite breakdown de género en el reporte de Segmentación Electoral
-- sin hacer full-scan de 3.7M filas en cada consulta.
--
-- Fuente: voters.sexo (enriquecido via scripts/enrich_sexo.php)

ALTER TABLE summary_inscritos_provincia
    ADD COLUMN inscritos_m INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN inscritos_f INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN inscritos_n INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE summary_inscritos_canton
    ADD COLUMN inscritos_m INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN inscritos_f INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN inscritos_n INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE summary_inscritos_distrito
    ADD COLUMN inscritos_m INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN inscritos_f INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN inscritos_n INT UNSIGNED NOT NULL DEFAULT 0;

-- Poblar provincias
UPDATE summary_inscritos_provincia p
INNER JOIN (
    SELECT province_id,
           SUM(sexo = 'M') AS m,
           SUM(sexo = 'F') AS f,
           SUM(sexo = 'N') AS n
    FROM voters
    WHERE province_id IS NOT NULL
    GROUP BY province_id
) v ON v.province_id = p.province_id
SET p.inscritos_m = v.m,
    p.inscritos_f = v.f,
    p.inscritos_n = v.n;

-- Poblar cantones
UPDATE summary_inscritos_canton c
INNER JOIN (
    SELECT canton_id,
           SUM(sexo = 'M') AS m,
           SUM(sexo = 'F') AS f,
           SUM(sexo = 'N') AS n
    FROM voters
    WHERE canton_id IS NOT NULL
    GROUP BY canton_id
) v ON v.canton_id = c.canton_id
SET c.inscritos_m = v.m,
    c.inscritos_f = v.f,
    c.inscritos_n = v.n;

-- Poblar distritos
UPDATE summary_inscritos_distrito d
INNER JOIN (
    SELECT district_id,
           SUM(sexo = 'M') AS m,
           SUM(sexo = 'F') AS f,
           SUM(sexo = 'N') AS n
    FROM voters
    WHERE district_id IS NOT NULL
    GROUP BY district_id
) v ON v.district_id = d.district_id
SET d.inscritos_m = v.m,
    d.inscritos_f = v.f,
    d.inscritos_n = v.n;
