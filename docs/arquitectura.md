# Arquitectura — PEL Digital

## Visión general

El sistema tiene **dos capas independientes** con responsabilidades distintas, dos bases de datos separadas, y un frontend liviano sin bundler.

```
┌──────────────────────────────────────────────────────────────────┐
│  CAPA 1 — Procesamiento de datos / Data Warehouse                │
│  BD: peldigital_data                                             │
│  Fuente: archivos crudos del TSE  (raw/)                        │
│  Scripts: scripts/import_*.php  scripts/enrich_*.php            │
└──────────────────────────────────────────────────────────────────┘
                         ↕  dbData()  (lib/db.php)
┌──────────────────────────────────────────────────────────────────┐
│  CAPA 2 — Sistema de gestión / UI / CRUDs                        │
│  BD: pel_electoral                                               │
│  Fuente: administración interna (panel admin, configuración)     │
│  APIs: api/admin/*.php   auth.php   api/log.php                  │
└──────────────────────────────────────────────────────────────────┘
                         ↕  dbConnect()  (lib/db.php)
┌──────────────────────────────────────────────────────────────────┐
│  FRONTEND  (sin framework, sin bundler)                          │
│  HTML/PHP views  +  assets/js/app/  +  assets/css/              │
└──────────────────────────────────────────────────────────────────┘
```

---

## Estructura de directorios

```
pel_02/
├── api/                  Endpoints JSON (autenticados vía sesión PHP)
│   ├── admin/            APIs protegidas — requieren rol administrador
│   └── *.php             Endpoints de reportes (datos electorales)
├── assets/
│   ├── css/              Estilos (variables CSS para light/dark)
│   └── js/
│       ├── app/          Módulos ES: core.js, map.js, controls.js, reports.js…
│       └── admin.js      Panel de administración completo
├── docs/                 Documentación técnica
├── lib/                  Helpers PHP compartidos
│   ├── db.php            dbConnect() + dbData() — singletons PDO
│   ├── api.php           apiJson(), apiError(), apiPagination()…
│   ├── env.php           Cargador de .env
│   └── auth.php          Sesión, CSRF, middleware de roles
├── migrations/           Archivos SQL numerados — aplicados por migrate.php
├── raw/                  Archivos crudos del TSE — excluidos del repo (.gitignore)
│   ├── padron/           PADRON_COMPLETO.txt, distelec.txt, centros_votacion_2026.xlsx
│   └── avr/              avr2026.json, avr2024.json, avr2022.json, avr2022_ii.json
├── scripts/              CLIs PHP: ETL, enriquecimiento, migraciones
├── views/ (o *.php root) Vistas PHP con layout chain: head→header→content→footer→scripts
├── .env                  Credenciales locales — NO va al repo
└── .env.example          Plantilla de configuración
```

---

## Capa 1 — Datos electorales (`peldigital_data`)

### Subcapas del pipeline

```
raw/                 Bronze  — archivos originales del TSE sin transformar
     ↓ import_*.php
peldigital_data      Silver  — datos normalizados, indexados, con FK
     ↓ enrich_*.php
voter_enrichments    Silver+ — enriquecimientos derivados (sexo, fecha_nac)
     ↓ refresh_*.php
summary_*            Gold    — agregados precalculados para reportes rápidos
```

### Pipeline ETL — orden de ejecución

| Paso | Script | Entrada | Salida | Tiempo aprox. |
|---|---|---|---|---|
| 1 | `import_distelec.php` | `distelec.txt` | provinces, cantons, districts | ~30 seg |
| 2 | `import_padron.php` | `PADRON_COMPLETO.txt` | voters (3.7M) | ~20 min |
| 3 | `enrich_sexo.php` | voters.nombre + name_gender_lookup | voters.sexo | ~51 seg |
| 4 | `enrich_fecha_nac.php` | cédulas → API CHC TSE | voters.fecha_nac | horas (requiere red) |
| 5 | `import_resultados.php` ×4 | avr*.json | election_results, parties | ~5 min |
| 6 | `import_electoral_districts.php` | provinces | electoral_districts (7) | ~1 seg |
| 7 | `import_polling_places.php` | centros_votacion_2026.xlsx | polling_places (2,191) | ~10 seg |
| 8 | `link_voters_polling.php` | voters + polling_places | voters.polling_place_id (100%) / electoral_district_id (98.2%) | ~30 min |
| 9 | `refresh_summaries.php` | voters | summary_inscritos_*, summary_jrv | ~2 min |

### Tablas de `peldigital_data`

| Tabla | Capa | Descripción | Filas |
|---|---|---|---|
| `provinces` | Dimensión | 7 provincias + exterior (id=8) | 8 |
| `cantons` | Dimensión | 84 cantones (id = prov×100 + num) | 126 |
| `districts` | Dimensión | Distritos administrativos, codelec 6 dígitos | 2,199 |
| `electoral_districts` | Dimensión | 7 circunscripciones legislativas | 7 |
| `polling_places` | Dimensión | Centros de votación TSE 2026 con rango JRV | 2,191 |
| `parties` | Dimensión | Partidos políticos del TSE | 33 |
| `name_gender_lookup` | Lookup | Top 400 primeros nombres → sexo M/F | 400 |
| `voters` | Hecho | Padrón nacional electoral — registro maestro | 3,731,788 |
| `voter_enrichments` | Satélite | Enriquecimientos persistentes (sobreviven TRUNCATE voters) | ~3.5M |
| `election_results` | Hecho | Resultados electorales por territorio y partido | ~30k |
| `election_sync_runs` | Control ETL | Registro de importaciones de resultados | pequeño |
| `padron_sync_runs` | Control ETL | Registro de importaciones del padrón | pequeño |
| `import_jobs` | Control ETL | Historial de jobs de importación | pequeño |
| `summary_inscritos_provincia` | Gold / DW | Inscritos + sexo por provincia | 7 |
| `summary_inscritos_canton` | Gold / DW | Inscritos + sexo por cantón | 84 |
| `summary_inscritos_distrito` | Gold / DW | Inscritos + sexo por distrito | ~2,130 |
| `summary_jrv` | Gold / DW | Inscritos por junta receptora de votos | ~7,063 |

### Campos de `voters` y sus fuentes

| Campo | Fuente | Completitud actual |
|---|---|---|
| `cedula`, `nombre`, `apellido1`, `apellido2` | PADRON_COMPLETO.TXT | 100% |
| `fecha_caduc` | PADRON_COMPLETO.TXT | 100% |
| `junta` | PADRON_COMPLETO.TXT | 100% |
| `province_id`, `canton_id`, `district_id` | PADRON_COMPLETO.TXT (codelec) | 100% |
| `sexo` | `enrich_sexo.php` via name_gender_lookup | 100% — M/F 71.7%, N 28.3% |
| `fecha_nac` | `enrich_fecha_nac.php` via CHC/TSE | 0% — pendiente (requiere red) |
| `polling_place_id` | `link_voters_polling.php` via junta | 100% |
| `electoral_district_id` | `link_voters_polling.php` via province_id | 98.2% — 67k exterior = NULL correcto |

### Notas importantes

**`voter_enrichments` sin FK intencional**: el padrón se reimporta con `TRUNCATE voters` cada ciclo. Si tuviera FK, el TRUNCATE fallaría. `voter_enrichments` persiste enriquecimientos por `cedula` y `enrich_sexo.php` los restaura en el Paso 1 antes de re-procesar.

**`summary_jrv` vs juntas reales**: la tabla tiene ~7,063 filas vs 7,154 juntas únicas en `voters`. La diferencia (~91) son juntas de la diáspora (province_id=8) excluidas intencionalmente del refresh (solo procesa provincias 1-7).

**`electoral_district_id` 98.2%**: los 67,270 voters sin ID son electores del exterior (province_id=8). No existe circunscripción legislativa para el voto en el extranjero, por eso quedan en NULL correctamente.

**`sexo='N'` en 28.3%**: el lookup de 400 nombres cubre los nombres más frecuentes del padrón. Nombres compuestos (MARIA DEL CARMEN) o poco comunes quedan como 'N' (no determinado). La cobertura se puede ampliar agregando filas a `name_gender_lookup` sin cambiar código.

---

## Capa 2 — Sistema (`pel_electoral`)

### Tablas de `pel_electoral`

| Tabla | Descripción |
|---|---|
| `users` | Usuarios del sistema con hash bcrypt |
| `roles` | Roles (Administrador, Analista, Viewer) |
| `permissions` | Permisos granulares por acción |
| `role_permissions` | Relación roles ↔ permisos |
| `settings` | Configuración key/value de la app |
| `reports` | Catálogo de reportes habilitados (menú BD-driven) |
| `report_categories` | Categorías de agrupación de reportes |
| `audit_logs` | Bitácora de actividad de usuarios |
| `schema_migrations` | Control de versión del esquema |

### Layout chain (vistas PHP)

```
head.php → header.php → [contenido de la página] → footer.php → scripts.php
```

### Conexiones PHP (`lib/db.php`)

```php
dbConnect()  →  pel_electoral      // sistema, auth, audit, configuración
dbData()     →  peldigital_data    // datos electorales, ETL, reportes
```

| Archivo | Conexión | Motivo |
|---|---|---|
| `api/admin/usuarios.php` | `dbConnect()` | users, roles — sistema |
| `api/admin/bitacora.php` | `dbConnect()` | audit_logs — sistema |
| `api/admin/pipelines.php` | `dbConnect()` | schema_migrations — sistema |
| `api/poblacion.php` | `dbData()` | provinces, summary — datos |
| `api/padron.php` | `dbData()` | voters — datos |
| `api/participacion.php` | `dbData()` | election_results — datos |
| `scripts/import_*.php` | `dbData()` | ETL — datos |
| `api/admin/datos.php` | **ambas** | estadísticas de las dos BDs |

---

## Archivos crudos (`raw/`) — NO van al repositorio

| Archivo | URL de descarga | Tamaño |
|---|---|---|
| `raw/padron/PADRON_COMPLETO.txt` | https://www.tse.go.cr/2026/padron.html | 427 MB |
| `raw/padron/distelec.txt` | Incluido en el ZIP del padrón | 172 KB |
| `raw/padron/centros_votacion_2026.xlsx` | https://www.tse.go.cr/2026/docus/CENTROS_DE%20VOTACION_%20RATIFICADOS-A-28-01-26.xlsx | 203 KB |
| `raw/avr/avr2026.json` | https://www.tse.go.cr/APISVR2026/cortes/ultimo?corte=0 | 2.5 MB |
| `raw/avr/avr2024.json` | Portal TSE — requiere browser con Referer TSE | 1.3 MB |
| `raw/avr/avr2022.json` | Portal TSE | 2.9 MB |
| `raw/avr/avr2022_ii.json` | Portal TSE | 514 KB |

> El WAF Radware del TSE bloquea descargas con curl desde IPs externas. Las descargas AVR deben hacerse desde browser en el dominio TSE.

---

## Configuración de entorno (`.env`)

```dotenv
# Sistema (pel_electoral)
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=pel_electoral
DB_USER=pel_user
DB_PASS=password_seguro

# Datos electorales (peldigital_data)
# DW_HOST / DW_USER / DW_PASS son opcionales — heredan de DB_* si no se definen
DW_HOST=127.0.0.1
DW_PORT=3306
DW_NAME=peldigital_data
DW_USER=pel_user
DW_PASS=password_seguro

APP_ENV=development   # production bloquea el fallback demo
SESSION_NAME=PEL_SESSION
```

---

## Setup desde cero

```bash
# 1. Migraciones del sistema (pel_electoral)
php scripts/migrate.php                     # sistema: pel_electoral

# 2. Crear peldigital_data y mover tablas de datos
php scripts/setup_databases.php

# 2b. Migraciones del DW (peldigital_data) — cuando existan archivos en migrations/data/
php scripts/migrate.php --db=data

# 3-9. ETL completo (ver docs/produccion.md para detalle completo)
php scripts/import_distelec.php --file=raw/padron/distelec.txt
php scripts/import_padron.php --file=raw/padron/PADRON_COMPLETO.txt
php scripts/enrich_sexo.php --batch=0
php scripts/import_resultados.php --json=raw/avr/avr2026.json --type=P --label="Presidencia 2026"
php scripts/import_electoral_districts.php
php scripts/import_polling_places.php
php scripts/link_voters_polling.php
php scripts/refresh_summaries.php

# 10. (Opcional) Evento MySQL para refresh automático diario
php scripts/setup_event.php
```

Ver [`docs/produccion.md`](produccion.md) para el checklist completo de producción.
