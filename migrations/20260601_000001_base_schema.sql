-- Esquema base de PEL Digital
-- Tablas: voters, provinces, cantons, districts, users, roles,
--         audit_logs, polling_places, electoral_districts, schema_migrations
-- NOTA: Este archivo fue reconstruido el 10-jun-2026 a partir del esquema vivo.
--       Era la primera migración aplicada pero el archivo fuente se perdió.

CREATE TABLE IF NOT EXISTS schema_migrations (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    migration  VARCHAR(255)    NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Geografía ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS provinces (
    id   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100)     NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cantons (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    province_id TINYINT UNSIGNED  NOT NULL,
    name        VARCHAR(100)      NOT NULL,
    PRIMARY KEY (id),
    KEY fk_cantons_province (province_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS districts (
    id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    canton_id  SMALLINT UNSIGNED NOT NULL,
    name       VARCHAR(100)      NOT NULL,
    codelec    VARCHAR(10)       NULL,
    PRIMARY KEY (id),
    KEY fk_districts_canton (canton_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Padrón electoral ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS voters (
    id                   INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    cedula               VARCHAR(20)      NOT NULL,
    nombre               VARCHAR(100)     NOT NULL,
    apellido1            VARCHAR(80)      NOT NULL,
    apellido2            VARCHAR(80)      NOT NULL DEFAULT '',
    sexo                 CHAR(1)          NULL,
    fecha_caduc          DATE             NULL,
    junta                VARCHAR(10)      NULL,
    province_id          TINYINT UNSIGNED NULL,
    canton_id            SMALLINT UNSIGNED NULL,
    district_id          SMALLINT UNSIGNED NULL,
    electoral_district_id INT UNSIGNED    NULL,
    polling_place_id     INT UNSIGNED     NULL,
    imported_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cedula      (cedula),
    KEY idx_voters_geo_agg    (province_id, canton_id, district_id),
    KEY idx_voters_jrv        (junta, province_id, canton_id, district_id),
    KEY idx_voters_junta      (junta),
    KEY idx_voters_prov_sort  (province_id, canton_id, apellido1, apellido2, nombre),
    KEY idx_voters_canton_sort(canton_id, apellido1, apellido2, nombre),
    KEY idx_voters_apellido   (apellido1, apellido2, nombre),
    KEY idx_voters_diaspora   (province_id, canton_id, cedula),
    KEY idx_voters_fecha_caduc(fecha_caduc),
    KEY electoral_district_id (electoral_district_id),
    KEY idx_polling           (polling_place_id),
    FULLTEXT KEY idx_voters_ft(nombre, apellido1, apellido2),
    KEY idx_nombre            (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Usuarios y roles ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
    id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(50)      NOT NULL,
    description VARCHAR(200)     NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100)     NOT NULL,
    email      VARCHAR(150)     NOT NULL,
    password   VARCHAR(255)     NOT NULL,
    role_id    TINYINT UNSIGNED NOT NULL DEFAULT 3,
    active     TINYINT(1)       NOT NULL DEFAULT 1,
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email),
    KEY fk_users_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Locales de votación (catálogo — datos reales pendientes del TSE) ───────
CREATE TABLE IF NOT EXISTS polling_places (
    id                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name                  VARCHAR(200)     NOT NULL,
    address               TEXT             NULL,
    junta                 VARCHAR(10)      NULL,
    province_id           TINYINT UNSIGNED NULL,
    canton_id             SMALLINT UNSIGNED NULL,
    district_id           SMALLINT UNSIGNED NULL,
    electoral_district_id INT UNSIGNED     NULL,
    PRIMARY KEY (id),
    KEY fk_pp_district (district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS electoral_districts (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    province_id TINYINT UNSIGNED NULL,
    name        VARCHAR(150)     NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Bitácora de actividad ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED     NULL,
    action      VARCHAR(100)     NOT NULL,
    description TEXT             NULL,
    ip_address  VARCHAR(45)      NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user   (user_id),
    KEY idx_audit_action (action),
    KEY idx_audit_ts     (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos iniciales
INSERT IGNORE INTO roles (id, name, description) VALUES
    (1, 'administrador', 'Acceso total al sistema'),
    (2, 'analista',      'Acceso a reportes y consultas'),
    (3, 'consulta',      'Solo lectura de reportes');
