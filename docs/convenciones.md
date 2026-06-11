# Convenciones

## Nombres

| Tipo | Convencion | Ejemplo |
|---|---|---|
| API PHP | `snake_case` | `api/analisis_territorial.php` |
| Vista de reporte | `kebab-case` | `includes/reports/analisis-territorial.php` |
| Script CLI | `snake_case` | `scripts/refresh_summaries.php` |
| CSS class | `kebab-case` | `admin-section` |
| JS function/variable | `camelCase` | `cargarUsuarios()` |
| Tabla SQL | `snake_case` | `summary_inscritos_distrito` |

## APIs

- Iniciar con `require auth.php`, `requerirLoginApi()` y `require lib/db.php`.
- Usar `lib/api.php` para nuevas respuestas JSON, errores y paginacion.
- Validar toda entrada `$_GET` / `$_POST`.
- No interpolar columnas, tablas u ordenamientos desde input sin whitelist.
- Mantener contratos JSON existentes cuando se refactoriza.

## Documentacion

- `README.md`: entrada general.
- `docs/arquitectura.md`: estructura y flujo.
- `docs/etl.md`: ingesta y fuentes.
- `docs/produccion.md`: despliegue.
- `docs/roadmap.md`: estado y prioridades.
- `docs/reportes.md`: inventario operativo de reportes.
- `docs/agents.md` y `docs/agents/skills/`: contexto para asistentes de codigo.
