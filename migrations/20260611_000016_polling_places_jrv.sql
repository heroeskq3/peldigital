-- Agrega columnas de rango JRV a polling_places para poder ligar
-- voters.junta (número de JRV) con el local de votación que le corresponde.
-- Cada local cubre un rango de JRV: jrv_inicio..jrv_fin.

ALTER TABLE `polling_places`
  ADD COLUMN `jrv_inicio` SMALLINT UNSIGNED NULL AFTER `junta`,
  ADD COLUMN `jrv_fin`    SMALLINT UNSIGNED NULL AFTER `jrv_inicio`,
  ADD COLUMN `total_jrv`  SMALLINT UNSIGNED NULL AFTER `jrv_fin`,
  ADD INDEX  `idx_jrv_range` (`province_id`, `jrv_inicio`, `jrv_fin`);
