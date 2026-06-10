-- Agrega fecha_nac y timestamp de consulta CHC al TSE
-- Script: scripts/enrich_fecha_nac.php
-- Fuente: servicioselectorales.tse.go.cr/chc/consulta_cedula.aspx

ALTER TABLE voters
    ADD COLUMN fecha_nac DATE NULL DEFAULT NULL AFTER fecha_caduc,
    ADD COLUMN chc_consultado_at TIMESTAMP NULL DEFAULT NULL AFTER fecha_nac;

-- Indice para queries de segmentacion por rango de edad
CREATE INDEX idx_voters_fecha_nac ON voters (fecha_nac);

-- Indice para el script de enriquecimiento: encontrar rapido los pendientes
CREATE INDEX idx_voters_chc_pendiente ON voters (chc_consultado_at, province_id);
