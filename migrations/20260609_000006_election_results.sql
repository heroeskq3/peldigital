-- Migración: tablas para resultados electorales (importador AVR)
-- Sigue el mismo patrón que padron_sync_runs / voters.

CREATE TABLE IF NOT EXISTS election_sync_runs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_url      VARCHAR(255),
    filename        VARCHAR(255)    NOT NULL,
    file_sha256     CHAR(64)        NOT NULL,
    election_date   DATE,
    election_label  VARCHAR(120),   -- ej. "Nacionales 2026 — Presidencia"
    n_circunsc      TINYINT UNSIGNED DEFAULT 0,  -- n=12 en AVR2026
    status          ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
    message         TEXT,
    records_ok      INT UNSIGNED    NOT NULL DEFAULT 0,
    records_error   INT UNSIGNED    NOT NULL DEFAULT 0,
    started_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at     DATETIME,
    UNIQUE KEY uq_sha (file_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resultados por territorio (province / canton / district / jrv).
-- La columna nivel distingue el nivel de agregación.
-- jrv_idx = índice secuencial del TSE dentro del distrito (no es el número de junta).
-- Para unir con voters usar province_id + canton_id + district_id (nivel district/jrv).
CREATE TABLE IF NOT EXISTS election_results (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sync_run_id         BIGINT UNSIGNED NOT NULL,
    circunscripcion     TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0..11 en AVR2026
    nivel               ENUM('province','canton','district','jrv') NOT NULL,
    province_id         TINYINT UNSIGNED,
    canton_id           SMALLINT UNSIGNED,
    district_id         MEDIUMINT UNSIGNED,
    jrv_idx             SMALLINT UNSIGNED,   -- índice TSE dentro del distrito (NULL si nivel != jrv)
    inscritos           INT UNSIGNED NOT NULL DEFAULT 0,
    votos_emitidos      INT UNSIGNED NOT NULL DEFAULT 0,
    votos_validos       INT UNSIGNED NOT NULL DEFAULT 0,
    votos_nulos_blancos INT UNSIGNED NOT NULL DEFAULT 0,
    juntas_total        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    juntas_procesadas   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    votos_por_partido   JSON,               -- {código_partido: votos, ...}
    CONSTRAINT fk_er_run FOREIGN KEY (sync_run_id) REFERENCES election_sync_runs(id),
    INDEX idx_er_territory (province_id, canton_id, district_id, nivel),
    INDEX idx_er_run       (sync_run_id, nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
