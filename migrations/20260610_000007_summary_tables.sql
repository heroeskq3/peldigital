-- Tablas de resumen pre-agregadas para paginación SQL directa (LIMIT/OFFSET real).
-- Se populan desde scripts/refresh_summaries.php después de cada importación del padrón.
-- Objetivo: eliminar el scan de 3.7M filas en cada llamada a api/segmentacion.php y api/jrv.php.

CREATE TABLE IF NOT EXISTS summary_inscritos_provincia (
  province_id   TINYINT UNSIGNED    NOT NULL,
  nombre        VARCHAR(100)        NOT NULL,
  inscritos     INT UNSIGNED        NOT NULL DEFAULT 0,
  pct_nacional  DECIMAL(6,3)        NOT NULL DEFAULT 0.000,
  updated_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (province_id),
  INDEX idx_inscritos (inscritos DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS summary_inscritos_canton (
  canton_id     SMALLINT UNSIGNED   NOT NULL,
  nombre        VARCHAR(100)        NOT NULL,
  province_id   TINYINT UNSIGNED    NOT NULL,
  provincia     VARCHAR(100)        NOT NULL,
  inscritos     INT UNSIGNED        NOT NULL DEFAULT 0,
  pct_nacional  DECIMAL(6,3)        NOT NULL DEFAULT 0.000,
  updated_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (canton_id),
  INDEX idx_province   (province_id),
  INDEX idx_inscritos  (inscritos DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS summary_inscritos_distrito (
  district_id   SMALLINT UNSIGNED   NOT NULL,
  nombre        VARCHAR(100)        NOT NULL,
  canton_id     SMALLINT UNSIGNED   NOT NULL,
  canton        VARCHAR(100)        NOT NULL,
  province_id   TINYINT UNSIGNED    NOT NULL,
  provincia     VARCHAR(100)        NOT NULL,
  geo5          VARCHAR(7)          NOT NULL DEFAULT '',
  inscritos     INT UNSIGNED        NOT NULL DEFAULT 0,
  pct_nacional  DECIMAL(6,3)        NOT NULL DEFAULT 0.000,
  updated_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (district_id),
  INDEX idx_province   (province_id),
  INDEX idx_canton     (canton_id),
  INDEX idx_geo5       (geo5),
  INDEX idx_inscritos  (inscritos DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS summary_jrv (
  junta         VARCHAR(5)          NOT NULL,
  district_id   SMALLINT UNSIGNED   NOT NULL,
  canton_id     SMALLINT UNSIGNED   NOT NULL,
  province_id   TINYINT UNSIGNED    NOT NULL,
  distrito      VARCHAR(100)        NOT NULL,
  canton        VARCHAR(100)        NOT NULL,
  provincia     VARCHAR(100)        NOT NULL,
  inscritos     INT UNSIGNED        NOT NULL DEFAULT 0,
  clasificacion ENUM('alta','media','baja') NOT NULL DEFAULT 'baja',
  updated_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (junta, district_id),
  INDEX idx_province   (province_id),
  INDEX idx_canton     (canton_id),
  INDEX idx_district   (district_id),
  INDEX idx_inscritos  (inscritos DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
