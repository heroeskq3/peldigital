-- Tabla de análisis de bastiones electorales por JRV.
-- Generada por scripts/refresh_bastiones.php
-- Elecciones presidenciales usadas para clasificación: sync_run_id 4 (2026), 6 (2022-1a), 7 (2022-2a)

CREATE TABLE IF NOT EXISTS `summary_bastiones` (
    `junta`              VARCHAR(5)   NOT NULL,
    `province_id`        TINYINT      UNSIGNED NOT NULL,
    `canton_id`          SMALLINT     UNSIGNED NOT NULL,
    `district_id`        MEDIUMINT    UNSIGNED NOT NULL,
    `provincia`          VARCHAR(100) NOT NULL DEFAULT '',
    `canton`             VARCHAR(100) NOT NULL DEFAULT '',
    `distrito`           VARCHAR(100) NOT NULL DEFAULT '',
    `inscritos`          INT          UNSIGNED NOT NULL DEFAULT 0,
    `polling_place_id`   INT          UNSIGNED NULL,
    `local_nombre`       VARCHAR(200) NULL,

    -- Presidencial 2026 (sync_run_id = 4)
    `e4_tse_code`        SMALLINT     UNSIGNED NULL,
    `e4_votos`           INT          UNSIGNED NULL,
    `e4_pct`             DECIMAL(5,2) NULL,
    `e4_margen`          INT          NULL,
    `e4_participacion`   DECIMAL(5,2) NULL,
    `e4_votos_emitidos`  INT          UNSIGNED NULL,

    -- Municipal 2024 (sync_run_id = 5)
    `e5_tse_code`        SMALLINT     UNSIGNED NULL,
    `e5_votos`           INT          UNSIGNED NULL,
    `e5_pct`             DECIMAL(5,2) NULL,
    `e5_margen`          INT          NULL,
    `e5_participacion`   DECIMAL(5,2) NULL,
    `e5_votos_emitidos`  INT          UNSIGNED NULL,

    -- Presidencial 2022 1a vuelta (sync_run_id = 6)
    `e6_tse_code`        SMALLINT     UNSIGNED NULL,
    `e6_votos`           INT          UNSIGNED NULL,
    `e6_pct`             DECIMAL(5,2) NULL,
    `e6_margen`          INT          NULL,
    `e6_participacion`   DECIMAL(5,2) NULL,
    `e6_votos_emitidos`  INT          UNSIGNED NULL,

    -- Presidencial 2022 2a vuelta (sync_run_id = 7)
    `e7_tse_code`        SMALLINT     UNSIGNED NULL,
    `e7_votos`           INT          UNSIGNED NULL,
    `e7_pct`             DECIMAL(5,2) NULL,
    `e7_margen`          INT          NULL,
    `e7_participacion`   DECIMAL(5,2) NULL,
    `e7_votos_emitidos`  INT          UNSIGNED NULL,

    -- Clasificación agregada (presidenciales: e4, e6, e7)
    `dom_tse_code`       SMALLINT     UNSIGNED NULL  COMMENT 'Partido dominante histórico',
    `dom_wins`           TINYINT      UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Victorias presidenciales (de 3)',
    `dom_pct_avg`        DECIMAL(5,2) NULL COMMENT 'Promedio % en elecciones donde ganó',
    `margen_avg`         DECIMAL(7,2) NULL COMMENT 'Promedio votos de diferencia vs 2do',
    `clasificacion`      ENUM('bastion_fuerte','bastion_moderado','competitivo','volatil','transicion') NOT NULL DEFAULT 'volatil',
    `tendencia`          ENUM('subiendo','bajando','estable') NOT NULL DEFAULT 'estable',
    `votos_conquista`    INT          NULL COMMENT 'Votos necesarios para voltear la JRV en 2026',
    `indice_oportunidad` DECIMAL(10,2) NULL COMMENT 'Score compuesto de rentabilidad de campaña',

    `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`junta`),
    INDEX `idx_province`    (`province_id`),
    INDEX `idx_canton`      (`canton_id`),
    INDEX `idx_district`    (`district_id`),
    INDEX `idx_clasif`      (`clasificacion`),
    INDEX `idx_dom_party`   (`dom_tse_code`),
    INDEX `idx_oportunidad` (`indice_oportunidad` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
