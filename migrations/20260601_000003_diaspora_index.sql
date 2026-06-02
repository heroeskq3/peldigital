-- Migración: índice cubriente para consultas de diáspora (votantes del exterior)
-- Feature: mapa mundial de diáspora en métrica "extranjero"
--
-- Las dos consultas nuevas en api/poblacion.php filtran por province_id = 8
-- (Exterior) y necesitan acceso a cedula y canton_id sin full scan.
-- Este índice cubre ambas en una sola estructura.

ALTER TABLE voters
    ADD INDEX idx_voters_diaspora (province_id, canton_id, cedula);

-- Registrar en historial de migraciones
INSERT INTO schema_migrations (migration, executed_at)
VALUES ('20260601_000003_diaspora_index.sql', NOW());
