-- Migración: índice cubriente para consultas de diáspora (votantes del exterior)
-- Feature: mapa mundial de diáspora en métrica "extranjero"
--
-- Las dos consultas nuevas en api/poblacion.php filtran por province_id = 8
-- (Exterior) y necesitan acceso a cedula y canton_id sin full scan.
-- Este índice cubre ambas en una sola estructura.

CREATE INDEX IF NOT EXISTS idx_voters_diaspora
    ON voters (province_id, canton_id, cedula);
