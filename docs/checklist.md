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
| 19 | Partir `assets/js/app.js` por modulos | Codigo | Alta | Hecho | Dividido en `assets/js/app/core.js`, `map.js`, `controls.js`, `padron-bitacora.js` y `reports.js`. |
| 20 | Partir `assets/css/style.css` por dominios | Codigo | Media | Hecho | Dividido en `assets/css/app/tokens.css`, `nav.css`, `layout.css`, `modals.css`, `responsive.css`, `reports.css` y `admin.css`. |
| 21 | Centralizar carga de vistas de reportes en `reports.php` | Codigo | Media | Hecho | Carga `php_file` del reporte activo desde BD con whitelist; mantiene `padron-distribucion.php` como base por dependencia de `#map`. |
| 22 | Deshabilitar/restringir fallback `demo` en produccion | Seguridad | Alta | Hecho | `auth.php` bloquea fallback cuando `APP_ENV=production`; prueba CLI confirmada. |
| 23 | Probar bloqueo web de carpetas sensibles por URL | Seguridad | Alta | Hecho | XAMPP: `raw/`, `scripts/`, `migrations/`, `lib/` y `.env` devuelven `403`; rutas públicas devuelven `200`. |
| 24 | Agregar pruebas minimas de parsers y ETL | Calidad | Media | Pendiente | Usar fixtures pequenas. |
| 25 | Agregar smoke test de APIs principales | Calidad | Media | Pendiente | Requiere sesion/auth o harness CLI. |
| 26 | Restringir panel y APIs admin por rol | Seguridad | Alta | Hecho | `admin.php` y `api/admin/*` requieren rol `administrador`; el menú Admin se oculta para otros roles. |
| 27 | Agregar CSRF para escrituras admin | Seguridad | Alta | Hecho | `api/admin/*` valida `X-CSRF-Token` en métodos mutables; `admin.js` lo envía automáticamente. |
