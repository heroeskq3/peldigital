# Checklist de estandarizacion

| # | Tarea | Tipo | Prioridad | Estado | Nota |
|---:|---|---|---|---|---|
| 1 | Eliminar `.DS_Store` y archivos locales innecesarios | Limpieza | Alta | Hecho | No quedan `.DS_Store` fuera de `.git`. |
| 2 | Verificar que `raw/` solo tenga `.gitkeep` en git | Limpieza | Alta | Hecho | Los datos reales quedan locales/no versionados. |
| 3 | Confirmar que `data/poblacion_cache.json` no esté versionado | Limpieza | Alta | Hecho | Git solo versiona GeoJSON en `data/`. |
| 4 | Crear `docs/arquitectura.md` | Docs | Alta | Hecho | Documenta flujo, directorios y riesgos actuales. |
| 5 | Crear `docs/etl.md` | Docs | Alta | Hecho | Consolida fuentes, scripts y orden ETL. |
| 6 | Crear `docs/roadmap.md` | Docs | Media | Hecho | Resume estado funcional y segunda etapa. |
| 7 | Crear `docs/agents/` | Docs | Media | Hecho | Contiene skills operativos para agentes. |
| 8 | Mover contenido útil de `.claude/commands/*.md` a `docs/agents/skills/` | Docs | Media | Hecho | `.claude` deja de ser la fuente documental canonica. |
| 9 | Reemplazar `CLAUDE.md` por `docs/agents.md` | Docs | Media | Hecho | `CLAUDE.md` fue movido a `docs/agents.md`. |
| 10 | Mover `EXPECTATIVAS_CLIENTE.md` a roadmap | Docs | Media | Hecho | Ahora vive en `docs/roadmap/expectativas-cliente.md`. |
| 11 | Documentar convenciones de nombres | Estandar | Alta | Hecho | Ver `docs/convenciones.md`. |
| 12 | Crear inventario de reportes sin reemplazar la BD | Docs | Alta | Hecho | Ver `docs/reportes.md`; la BD sigue siendo fuente runtime. |
| 13 | Crear helper comun para respuestas JSON/API | Codigo | Media | Iniciado | `lib/api.php` creado y usado en usuarios, JRV y segmentacion. |
| 14 | Crear helper comun para paginacion API | Codigo | Media | Iniciado | `apiPagination*()` creado y usado en usuarios, JRV y segmentacion. |
| 15 | Mover `scripts/test_batch.php` a desarrollo | Limpieza | Baja | Hecho | Ahora es `scripts/dev/test_batch.php`. |
| 16 | Mantener `scripts/enrich_fecha_nac.php` como pendiente de desarrollo | Codigo | Media | Hecho | Documentado como pendiente en `docs/etl.md`. |
| 17 | Eliminar datos demo de locales/distritos electorales | Datos | Alta | Hecho local | BD local limpiada: `polling_places=0`, `electoral_districts=0`. No se creo migracion destructiva. |
| 18 | Revisar placeholders admin | Producto | Media | Hecho | Admin documentado como funcional: usuarios, roles, reportes, bitacora, configuracion, datos y pipelines. |
| 19 | Partir `assets/js/app.js` por modulos | Codigo | Alta | Pendiente | Segunda etapa. |
| 20 | Partir `assets/css/style.css` por dominios | Codigo | Media | Pendiente | Segunda etapa. |
| 21 | Centralizar carga de vistas de reportes en `reports.php` | Codigo | Media | Pendiente | Segunda etapa; requiere validar rutas desde catalogo BD. |
| 22 | Deshabilitar/restringir fallback `demo` en produccion | Seguridad | Alta | Pendiente | Recomendado condicionar por `APP_ENV`. |
| 23 | Probar bloqueo web de carpetas sensibles por URL | Seguridad | Alta | Pendiente | `.htaccess` existe y Apache syntax OK; falta prueba HTTP. |
| 24 | Agregar pruebas minimas de parsers y ETL | Calidad | Media | Pendiente | Usar fixtures pequenas. |
| 25 | Agregar smoke test de APIs principales | Calidad | Media | Pendiente | Requiere sesion/auth o harness CLI. |
