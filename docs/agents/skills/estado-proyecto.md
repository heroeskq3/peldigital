# Skill: Estado del proyecto PEL Digital — 10 jun 2026

Contexto completo para continuar el desarrollo en una nueva sesión de Claude/Codex.

## ¿Qué es este proyecto?

**PEL Digital** es una plataforma de análisis electoral para el partido Esperanza y Libertad (PEL) de Costa Rica. Permite explorar el padrón electoral TSE 2026 (3.7M votantes), resultados electorales históricos y segmentación por territorio y sexo.

- **URL local**: `http://localhost:8099/` (o XAMPP en `http://localhost/pel_02/`)
- **Login**: usuario `demo`, contraseña `demo1234`
- **BD**: `pel_electoral` · MySQL/MariaDB · root sin password

## Estado de reportes (10-jun-2026)

| # | Reporte | Estado | Slug BD | Archivo vista | API |
|---|---|---|---|---|---|
| 1 | Distribución Territorial del Padrón | ✅ Completo | `distribucion-territorial` | `padron-distribucion.php` | `api/poblacion.php` |
| 2 | Participación Electoral | ✅ Completo | `participacion-electoral` | `participacion.php` | `api/participacion.php` |
| 3 | Segmentación Electoral | ✅ Con sexo real | `segmentacion-electoral` | `segmentacion.php` | `api/segmentacion.php` |
| 4 | Análisis Territorial | ✅ Completo | `analisis-territorial` | `analisis-territorial.php` | `api/analisis_territorial.php` |
| 5 | JRV — Inscritos por Junta | ✅ Completo | `jrv-inscritos` | `jrv-inscritos.php` | `api/jrv.php` |
| 6 | JRV — Análisis Estratégico | ✅ Completo | `jrv-analisis-estrategico` | `jrv-analisis.php` | `api/jrv.php` + resultados |
| 7 | Indicadores Estratégicos PEL | ⚠️ Pendiente | `indicadores-estrategicos` | — | — |

## Lo que está hecho y funcionando

### Datos en BD
- **Padrón completo**: 3,731,788 votantes con cédula, nombre, apellidos, junta, provincia/cantón/distrito
- **Sexo enriquecido**: M=1,428,900 (38.3%), F=1,246,161 (33.4%), N=1,056,727 (28.3% sin match en lookup)
  - Tabla `voter_enrichments` persiste los datos ante `TRUNCATE voters`
  - Tabla `name_gender_lookup` tiene 321 nombres clasificados
- **Resultados electorales**: AVR2026 (Presidencia), AVR2024 (Municipal), AVR2022 1ra y 2da ronda
- **Tablas de resumen pre-agregadas** con sexo: `summary_inscritos_provincia/canton/distrito`

### Datos ausentes
- `voters.fecha_nac`: NULL en todos los registros (WAF del TSE bloquea scraping)
- `voters.electoral_district_id`: NULL (catálogo no disponible)
- `polling_places`: falta cargar catálogo oficial de locales (~7,000 locales)

## Campos de voters

**Completos**: `cedula`, `nombre`, `apellido1`, `apellido2`, `fecha_caduc`, `junta`, `province_id`, `canton_id`, `district_id`

**Enriquecidos**: `sexo` (M/F/N via `name_gender_lookup`)

**Vacíos**: `fecha_nac`, `chc_consultado_at`, `electoral_district_id`, `polling_place_id`

## Arquitectura de app.js

Archivo monolítico IIFE (~2700 líneas). Funciones clave:
- `init()` → carga `api/poblacion.php` y construye el mapa
- `navegarA(nivel, prov, cant, dist)` → drill-down del mapa
- `activarReporte(slug)` → muestra el panel del reporte indicado
- `setupNav()` → inicializa menú y enruta reportes
- `abrirReporteSegmentacion()` / `abrirReporteJrv()` etc. → cada reporte tiene su función

## Archivos críticos

```
index.php               ← ensamblador de vistas (incluye todos los reports/)
auth.php                ← sesión PHP, login contra users; fallback demo solo local
includes/layout/
  head.php              ← CSS, anti-flash de tema
  header.php            ← nav con dropdown por categoría de reporte (BD-driven)
  scripts.php           ← Leaflet + app.js al final del body
includes/reports/       ← una vista .php por reporte
api/                    ← una API .php por reporte
assets/js/app/*.js      ← frontend público dividido por dominio
assets/css/style.css    ← CSS con variables de tema claro/oscuro
lib/db.php              ← dbConnect() PDO singleton
migrations/             ← migraciones SQL (runner: scripts/migrate.php)
```

## Pendientes conocidos

1. **Reporte 7 — Indicadores Estratégicos**: sin definición de KPIs con el cliente. Requiere reunión.
2. **Permisos admin**: `admin.php` y `api/admin/*` requieren rol administrador;
   falta definir permisos granulares si se necesitan administradores parciales.
3. **fecha_nac**: requiere acuerdo oficial con TSE. Desbloquea segmentación por edad.
4. **Producción**: configurar usuarios reales y confirmar `APP_ENV=production`.
5. **polling_places**: catálogo real de locales de votación con ~7,000 locales + direcciones.
6. **Comparativos históricos en UI**: AVR2022 importado en BD pero la UI de Participación solo muestra 2026 y 2024. Agregar selector de elección 2022.

## Cómo agregar un nuevo reporte

Carga el skill `/project:nuevo-reporte` para la guía paso a paso completa.

## Cómo ejecutar la ingesta ETL

Carga el skill `/project:etl-ingesta` para la guía completa del pipeline.

## Cómo desplegar en producción

Ver `docs/produccion.md` para checklist completo.

## Convención de migraciones

Archivo: `migrations/YYYYMMDD_NNNNNN_descripcion.sql`
Runner: `php scripts/migrate.php`
Tabla de control: `schema_migrations (id, migration)`
Las migraciones son idempotentes — usar `CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`, etc.
