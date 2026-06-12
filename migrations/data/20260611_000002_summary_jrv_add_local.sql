ALTER TABLE `summary_jrv`
  ADD COLUMN `polling_place_id`  INT UNSIGNED  NULL AFTER `clasificacion`,
  ADD COLUMN `local_nombre`      VARCHAR(200)  NULL AFTER `polling_place_id`,
  ADD INDEX  `idx_sjrv_pp`       (`polling_place_id`);
