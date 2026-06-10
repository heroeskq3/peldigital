-- Índice FULLTEXT adicional para búsqueda por nombre completo en el padrón.
-- NOTA: Reconstruido el 10-jun-2026. Era la segunda migración aplicada.
-- El índice FULLTEXT idx_voters_ft ya está en la tabla base (000001).
-- Esta migración es un placeholder para mantener la secuencia en schema_migrations.

-- Índice de nombre individual (para autocomplete en buscador territorial)
-- ya incluido en 000001; esta migración no agrega nuevas DDL para evitar
-- errores de "duplicate key" en servidores nuevos.
SELECT 1; -- no-op intencional
