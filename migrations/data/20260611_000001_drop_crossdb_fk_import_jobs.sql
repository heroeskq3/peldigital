-- Elimina la FK cross-database que referencia pel_electoral.users.
-- El nombre de la BD sistema puede variar por entorno (.env DB_NAME).
-- La integridad user_id → users se garantiza a nivel de aplicación.
ALTER TABLE `import_jobs`
  DROP FOREIGN KEY `import_jobs_ibfk_1`;
