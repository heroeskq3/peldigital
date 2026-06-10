-- Tabla de enriquecimiento de voters fuera del padron TSE.
-- Guarda datos derivados o scrapeados que NO vienen del padron original.
--
-- DISENO INTENCIONAL: sin FK a voters para que sobreviva TRUNCATE TABLE voters.
-- Cuando se re-importa el padron, enrich_sexo.php restaura voters.sexo desde
-- esta tabla sin reprocessar los 3.7M nombres.
--
-- Campos actuales:
--   sexo      : M/F/N — derivado de name_gender_lookup por scripts/enrich_sexo.php
--   fecha_nac : pendiente — requiere acuerdo oficial con TSE (WAF bloquea scraping)

CREATE TABLE IF NOT EXISTS voter_enrichments (
    cedula      VARCHAR(20)  NOT NULL,
    sexo        CHAR(1)      NULL     COMMENT 'M/F/N via name_gender_lookup',
    fecha_nac   DATE         NULL     COMMENT 'Pendiente: requiere datos oficiales TSE',
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (cedula)
    -- Sin FOREIGN KEY intencional: persiste ante TRUNCATE TABLE voters
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Poblar desde los datos ya enriquecidos en voters.sexo
INSERT INTO voter_enrichments (cedula, sexo)
SELECT cedula, sexo
FROM   voters
WHERE  sexo IS NOT NULL
ON DUPLICATE KEY UPDATE sexo = VALUES(sexo);
