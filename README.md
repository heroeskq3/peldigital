# PEL Digital

Plataforma interna de análisis electoral y territorial para el Partido Esperanza y Libertad.
Uso exclusivo interno del partido. No distribuir.

## Stack

- PHP 8.1+ (sin framework — funciones globales, includes directos)
- MySQL / MariaDB — InnoDB, FULLTEXT habilitado
- Leaflet 1.9.4 (mapas interactivos)
- Bootstrap Icons 1.11.3
- HTML / CSS / JavaScript puro — sin bundler, sin npm

## Arquitectura del layout

Todas las páginas del sistema comparten los **mismos cuatro parciales** de layout,
sin duplicación. El HTML de cada página se ensambla en esta cadena exacta:

```
head.php  →  header.php  →  [contenido de la página]  →  footer.php  →  scripts.php
```

### Qué produce cada parcial

| Archivo | Abre | Cierra | Contenido |
|---|---|---|---|
| `includes/layout/head.php` | `<html>` `<head>` `<body>` `<div class="app-shell">` | — | DOCTYPE, meta, anti-flash de tema, Bootstrap Icons, Leaflet CSS, `style.css`, CSS extra de la página (`$extraHeadLinks`) |
| `includes/layout/header.php` | — | — | Barra superior + menú dinámico cargado desde BD (`reports` + `report_categories`) |
| `includes/layout/footer.php` | — | `</div>` (cierra `app-shell`) | Footer con atribución TSE |
| `includes/layout/scripts.php` | — | `</body>` `</html>` | JS de la página (`$pageScripts` si se define, o leaflet + chart.js + app.js por defecto) |

El `<div class="app-shell">` lo abre `head.php` y lo cierra `footer.php`.
El `</body></html>` lo cierra **siempre** `scripts.php`. Ninguna página los escribe
manualmente.

### Cómo funciona el HTML generado

```
<!-- head.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta ...>
  <link href="bootstrap-icons.css">
  <link href="leaflet.css">
  <link href="style.css">
  <!-- si $extraHeadLinks está definido: -->
  <link href="assets/css/mi-pagina.css">
</head>
<body>
<div class="app-shell">

<!-- header.php -->
<header class="app-header"> ... </header>

<!-- contenido específico de la página -->
<div class="mi-contenido"> ... </div>

<!-- footer.php -->
<footer class="app-footer"> ... </footer>
</div>  <!-- cierra app-shell -->

<!-- scripts.php — si NO hay $pageScripts: -->
<script src="leaflet.js"></script>
<script src="chart.js"></script>
<script src="assets/js/app.js"></script>
<!-- scripts.php — si HAY $pageScripts: -->
<script src="assets/js/mi-pagina.js"></script>

</body>
</html>
```

### Variables de inyección

Para incluir CSS o JS específico de una página sin tocar los parciales compartidos,
definir antes de hacer el primer `require`:

```php
// CSS extra — se inyecta en <head> antes de </head>
$extraHeadLinks = ['assets/css/admin.css'];

// JS de la página — reemplaza leaflet+chart+app.js
// Si no se define, scripts.php carga leaflet + chart.js + app.js
$pageScripts = ['assets/js/admin.js'];
```

### Páginas actuales y sus cadenas de layout

| Página | `$extraHeadLinks` | `$pageScripts` | Incluye además |
|---|---|---|---|
| `reports.php` | — | — (leaflet + chart + app.js) | modals/padron.php, modals/bitacora.php, loader.php |
| `admin.php` | `admin.css` | `admin.js` | includes/admin/*.php |
| `login.php` | — | — | solo HTML inline |

### Cómo crear una nueva página

1. Crear `mi-pagina.php` en la raíz del proyecto.
2. Definir `$pageTitle`, `$reportId = 0`, `$pdo`, y las variables opcionales
   `$extraHeadLinks` / `$pageScripts`.
3. Incluir la cadena completa:

```php
<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/db.php';
requerirLogin();

$rootDir        = __DIR__;
$pageTitle      = 'Mi página · PEL Digital';
$reportId       = 0;
$extraHeadLinks = ['assets/css/mi-pagina.css']; // opcional
$pageScripts    = ['assets/js/mi-pagina.js'];   // opcional

$pdo = dbConnect();

require $rootDir . '/includes/layout/head.php';
require $rootDir . '/includes/layout/header.php';
?>

<!-- contenido específico aquí -->

<?php
require $rootDir . '/includes/layout/footer.php';
require $rootDir . '/includes/layout/scripts.php';
```

4. Si necesita JS con toggle de tema (sin app.js), implementarlo dentro del IIFE
   de `mi-pagina.js` usando `btnTheme` / `btnThemeM` — los botones ya están en el
   header compartido.

## Requisitos del servidor

| Componente | Versión mínima | Notas |
|---|---|---|
| PHP | 8.1+ | Extensions: `pdo_mysql`, `mbstring`, `json` |
| MySQL / MariaDB | 10.6+ / 8.0+ | InnoDB, FULLTEXT habilitado |
| Apache / Nginx | Cualquiera reciente | mod_rewrite si se usa Apache |
| Disco disponible | ≥ 10 GB | Padrón 427 MB + BD ~2-3 GB |
| RAM | ≥ 2 GB | Importación del padrón usa ~512 MB en pico |

## Estructura principal

```
pel_02/
├── index.php                      # Redirige a reports.php?id=1
├── reports.php                    # Ensamblador principal: valida sesión, carga el
│                                  # reporte solicitado por ?id= desde la BD
├── login.php / logout.php         # Pantallas de acceso / cierre de sesión
├── auth.php                       # Sesión PHP. Login, logout, helpers de auth.
│                                  # Usuarios hardcodeados en $USUARIOS (bcrypt).
│
├── includes/
│   ├── layout/
│   │   ├── head.php               # DOCTYPE, meta, CSS, anti-flash de tema
│   │   ├── header.php             # Barra superior + menú dinámico desde BD
│   │   ├── footer.php             # Pie de página con atribución TSE
│   │   ├── loader.php             # Spinner de carga inicial
│   │   └── scripts.php           # <script> al final del body
│   ├── modals/
│   │   ├── padron.php             # Modal de consulta del padrón (tabla paginada)
│   │   └── bitacora.php           # Modal de bitácora de actividad
│   └── reports/
│       ├── padron-distribucion.php    # Reporte #1 — Distribución Territorial
│       ├── jrv-inscritos.php          # Reporte #2 — Distribución Padrón / JRV
│       ├── jrv-analisis.php           # Reporte #3 — Análisis Estratégico JRV
│       ├── participacion.php          # Reporte #4 — Participación Electoral
│       ├── segmentacion.php           # Reporte #5 — Segmentación Electoral
│       ├── analisis-territorial.php   # Reporte #6 — Análisis Territorial
│       └── (pendiente)                # Reporte #7 — Indicadores Estratégicos
│
├── api/
│   ├── poblacion.php              # Agregados territoriales del padrón (con caché 1h)
│   ├── padron.php                 # Consulta paginada del padrón real
│   ├── jrv.php                    # Datos de JRV por territorio
│   ├── segmentacion.php           # Datos de segmentación por sexo
│   ├── participacion.php          # Datos de participación electoral
│   ├── resultados.php             # Resultados electorales por territorio y partido
│   ├── analisis_territorial.php   # Comparativos territoriales electorales
│   ├── parties.php                # Catálogo de partidos políticos
│   ├── bitacora.php               # Lectura de bitácora de actividad
│   └── log.php                    # Registro de eventos desde el frontend
│
├── assets/
│   ├── css/style.css              # CSS global (~930 líneas). Variables de tema.
│   ├── js/app.js                  # JS monolítico (~1400 líneas). Todo el frontend.
│   └── img/                       # Logos del partido
│
├── data/
│   ├── provincias.geojson         # Fronteras de las 7 provincias de CR
│   ├── cantones.geojson           # Fronteras de los cantones
│   ├── distritos.geojson          # Fronteras de los distritos
│   └── poblacion_cache.json       # Caché de api/poblacion.php (TTL 1h, auto-generado)
│
├── lib/
│   ├── db.php                     # dbConnect(): PDO singleton
│   ├── env.php                    # Carga variables de entorno desde .env
│   ├── bitacora.php               # Funciones de registro de eventos en BD
│   └── parsers/
│       ├── PadronTSEParser.php    # Parser del archivo plano del TSE
│       └── AvrParser.php          # Parser de archivos AVR de resultados electorales
│
├── scripts/
│   ├── migrate.php                # Runner de migraciones SQL desde migrations/
│   ├── import_distelec.php        # ETL: catálogo geográfico DISTELEC
│   ├── import_padron.php          # ETL: padrón electoral TSE
│   ├── import_resultados.php      # ETL: resultados electorales AVR
│   ├── enrich_sexo.php            # Enriquecimiento: sexo por lookup de nombres
│   ├── enrich_fecha_nac.php       # Enriquecimiento: fecha de nacimiento (pendiente)
│   ├── refresh_summaries.php      # Regenera tablas de resumen summary_*
│   └── test_batch.php             # Pruebas de importación por lotes
│
├── migrations/                    # Migraciones SQL versionadas (runner: migrate.php)
│   ├── 20260601_000001_base_schema.sql
│   ├── 20260601_000002_padron_bronze.sql
│   ├── 20260601_000003_diaspora_index.sql
│   ├── 20260606_000004_reports_catalog.sql
│   ├── 20260609_000005_segmentacion_report.sql
│   ├── 20260609_000006_election_results.sql
│   ├── 20260610_000007_summary_tables.sql
│   ├── 20260610_000008_parties_catalog.sql
│   ├── 20260610_000009_voters_fecha_nac.sql
│   ├── 20260610_000010_name_gender_lookup.sql
│   ├── 20260610_000011_voter_enrichments.sql
│   └── 20260610_000012_summary_sexo.sql
│
├── raw/                           # Archivos crudos del TSE — NO están en git
│   ├── padron/
│   │   ├── PADRON_COMPLETO.txt    # 427 MB — padrón plano 2026 (8 campos, ~3.7M filas)
│   │   ├── distelec.txt           # 172 KB — catálogo geográfico DISTELEC
│   │   └── Leame.txt             # Descripción de formato del TSE
│   └── avr/
│       ├── avr2026.json           # 2.5 MB — resultados presidenciales 2026
│       ├── avr2024.json           # 1.3 MB — resultados municipales 2024
│       ├── avr2022.json           # 2.9 MB — resultados presidenciales 2022 1ra ronda
│       └── avr2022_ii.json        # 514 KB — resultados presidenciales 2022 2da ronda
│
└── docs/
    └── produccion.md              # Guía de despliegue en producción
```

## Cómo ejecutar localmente

El proyecto está diseñado para correr sobre Apache + PHP + MySQL. No requiere
servidor embebido de PHP ni herramientas de build.

**Con XAMPP (recomendado para desarrollo):**

1. Colocar el proyecto en `/Applications/XAMPP/xamppfiles/htdocs/pel_02` (Mac)
   o en `C:\xampp\htdocs\pel_02` (Windows).
2. Iniciar Apache y MySQL desde el panel de XAMPP.
3. Abrir: `http://localhost/pel_02/`

**Con Apache/Nginx en Linux (staging o producción):**

Ver guía completa en [`docs/produccion.md`](docs/produccion.md).

## Migraciones de base de datos

El runner `scripts/migrate.php` aplica los archivos SQL de `migrations/` en
orden, registrando cada una en `schema_migrations` para no repetirlas.

```bash
# Aplicar todas las migraciones pendientes
php scripts/migrate.php

# Verificar qué migraciones se han aplicado
php -r "
  require_once 'lib/db.php';
  foreach (dbConnect()->query('SELECT filename, applied_at FROM schema_migrations ORDER BY applied_at') as \$r)
    echo \$r['filename'] . '  →  ' . \$r['applied_at'] . PHP_EOL;
"
```

## Pipeline ETL de ingesta

Todos los datos externos son **archivos descargables del TSE**. No se usan APIs
en tiempo real. Los archivos se depositan en `raw/` y los scripts ETL los procesan.

### Archivos crudos necesarios

| Archivo | Tamaño | Fuente (descarga manual) |
|---|---|---|
| `raw/padron/PADRON_COMPLETO.txt` | 427 MB | https://www.tse.go.cr/padron.html (ZIP del padrón 2026) |
| `raw/padron/distelec.txt` | 172 KB | incluido en el mismo ZIP del padrón |
| `raw/padron/Leame.txt` | — | incluido en el mismo ZIP |
| `raw/avr/avr2026.json` | 2.5 MB | Presidencia 2026: `tse.go.cr/APISVR2026/cortes/ultimo?corte=0` |
| `raw/avr/avr2024.json` | 1.3 MB | Municipal 2024 — vía herramienta del TSE |
| `raw/avr/avr2022.json` | 2.9 MB | Presidencial 2022 1ra ronda — vía herramienta del TSE |
| `raw/avr/avr2022_ii.json` | 514 KB | Presidencial 2022 2da ronda — vía herramienta del TSE |

> **Nota WAF del TSE**: Las descargas AVR pueden requerir un browser con el
> Referer correcto del dominio TSE. El WAF Radware bloquea `curl` directo desde
> servidores externos. En desarrollo local se hicieron desde el browser.

### Comandos ETL

```bash
# 1. Migraciones de BD (prerequisito de todo)
php scripts/migrate.php

# 2. Catálogo geográfico (~30 segundos)
php scripts/import_distelec.php --file=raw/padron/distelec.txt

# 3. Padrón electoral completo (~20 minutos)
php scripts/import_padron.php --file=raw/padron/PADRON_COMPLETO.txt
#    también acepta ZIP: --zip=raw/padron/padron.zip

# 4. Enriquecer campo sexo (~51 segundos, no requiere red)
php scripts/enrich_sexo.php --batch=0

# 5. Resultados electorales (en cualquier orden)
php scripts/import_resultados.php --json=raw/avr/avr2026.json --type=P --label="Presidencia 2026"
php scripts/import_resultados.php --json=raw/avr/avr2024.json --type=A --label="Municipal 2024"
php scripts/import_resultados.php --json=raw/avr/avr2022.json --type=P --label="Presidencial 2022 1ra"
php scripts/import_resultados.php --json=raw/avr/avr2022_ii.json --type=P --label="Presidencial 2022 2da"

# 6. Regenerar tablas de resumen (si se modifican datos manualmente)
php scripts/refresh_summaries.php
```

**Tiempo estimado en servidor nuevo:** ~30 minutos desde cero.

### Tablas destino

| Script | Tablas pobladas |
|---|---|
| `import_distelec.php` | `provinces`, `cantons`, `districts` |
| `import_padron.php` | `voters`, `padron_sync_runs` |
| `enrich_sexo.php` | `voters.sexo` (via `name_gender_lookup`) |
| `import_resultados.php` | `election_results`, `parties`, `election_sync_runs` |
| `refresh_summaries.php` | `summary_inscritos_provincia`, `summary_inscritos_canton`, `summary_inscritos_distrito`, `summary_jrv` |

## Base de datos

### Tablas principales

| Tabla | Propósito | Registros (10-jun-2026) |
|---|---|---|
| `voters` | Padrón electoral completo TSE 2026 | 3,731,788 |
| `provinces` | Catálogo de 7 provincias + exterior (id=8) | 8 |
| `cantons` | Catálogo de cantones + países de diáspora | ~90 |
| `districts` | Catálogo de distritos con `codelec` (TSE) | ~500 |
| `election_results` | Resultados electorales por partido y territorio | variable |
| `parties` | Catálogo de partidos políticos | variable |
| `summary_inscritos_provincia` | Resumen de inscritos por provincia | 8 |
| `summary_inscritos_canton` | Resumen de inscritos por cantón | ~90 |
| `summary_inscritos_distrito` | Resumen de inscritos por distrito | ~500 |
| `summary_jrv` | Resumen de inscritos por JRV | 7,154 |
| `name_gender_lookup` | Lookup nombre → sexo (321 nombres frecuentes) | 321 |
| `users` | Usuarios con roles | 3 |
| `roles` | Roles: administrador, analista, consulta | 3 |
| `reports` | Catálogo de reportes (nombre, estado, archivo) | 7 |
| `report_categories` | Categorías del menú de análisis | 5 |
| `audit_logs` | Bitácora de actividad | variable |
| `polling_places` | Locales de votación (datos de prueba, no reales) | 13 |
| `electoral_districts` | Distritos electorales (datos de prueba) | 10 |

### Campos en `voters`

**Poblados:** `cedula`, `nombre`, `apellido1`, `apellido2`, `fecha_caduc`,
`junta`, `province_id`, `canton_id`, `district_id`.

**Enriquecidos vía ETL:**
- `sexo` (M/F/N): poblado con `enrich_sexo.php`. Distribución actual:
  M = 1,428,900 (38.3%), F = 1,246,161 (33.4%), N = 1,056,727 (28.3% sin match).

**Vacíos (NULL en todos los registros):**
- `fecha_nac`: el archivo TSE incluye el campo pero el parser aún no lo extrae.
  Bloquea segmentación por edad.
- `electoral_district_id`, `polling_place_id`: tablas de catálogo con datos
  de prueba, no reales. Bloquean asignación de locales de votación.

## Inventario de reportes

Los reportes se administran desde la tabla `reports` en BD. El estado determina
si aparecen activos, con advertencia o bloqueados en el menú.

| # | Nombre | Estado | Fuente de datos | Tablas utilizadas |
|---|---|---|---|---|
| 1 | Distribución Territorial | Activo | Padrón TSE 2026 | `voters`, `provinces`, `cantons`, `districts`, GeoJSON |
| 2 | Distribución Padrón / JRV | Activo | Padrón TSE 2026 | `summary_jrv`, `voters`, `districts` |
| 3 | Análisis Estratégico · JRV | Activo | Padrón TSE 2026 | `summary_jrv`, `districts`, `cantons`, `provinces` |
| 4 | Participación Electoral | Activo | AVR TSE 2026/2022 | `election_results`, `parties`, `provinces`, `cantons` |
| 5 | Segmentación Electoral | Parcial | Padrón TSE 2026 (sexo enriquecido) | `summary_inscritos_*`, `voters.sexo` — fecha_nac pendiente |
| 6 | Análisis Territorial | Activo | AVR 2026/2024/2022 | `election_results`, `parties`, `provinces`, `cantons` |
| 7 | Indicadores Estratégicos | Pendiente | Depende de reportes 1–6 | — |

**Estados:**
- **Activo**: completamente funcional.
- **Parcial** (`partial`): funciona con datos disponibles pero tiene limitaciones
  (ícono de reloj en el menú).
- **Pendiente** (`pending`): bloqueado hasta tener datos adicionales del TSE
  (ícono de candado en el menú).

## Módulo de Gestión y Administración

El menú Admin incluye los siguientes módulos. Los marcados como *placeholder*
muestran una pantalla de "próximamente" — la lógica está pendiente.

| Módulo | Acceso | Estado |
|---|---|---|
| Bitácora | `data-admin="bitacora"` | Funcional — muestra `audit_logs` |
| Configuración | `data-admin="configuracion"` | Placeholder |
| Usuarios | `data-admin="usuarios"` | Placeholder — tabla `users` existe con roles |
| Roles de usuario | `data-admin="roles"` | Placeholder — tabla `roles` existe |
| Cargar Datos | `data-admin="cargar"` | Placeholder |
| Pipelines | `data-admin="pipelines"` | Placeholder |

## Despliegue en producción

Ver guía detallada en [`docs/produccion.md`](docs/produccion.md). Pasos
resumidos:

1. Clonar repositorio en el servidor.
2. Crear BD `pel_electoral` y usuario dedicado.
3. Copiar `.env.example` a `.env` y configurar credenciales.
4. Ejecutar `php scripts/migrate.php`.
5. Descargar archivos crudos del TSE (ver sección Pipeline ETL).
6. Ejecutar el pipeline ETL completo (~30 min).
7. Configurar Apache para bloquear acceso web a `raw/`, `migrations/`,
   `scripts/`, `lib/`. Ver directivas en `docs/produccion.md`.
8. Habilitar HTTPS.
9. Cambiar contraseñas en `auth.php` con hashes bcrypt nuevos.

**Carpetas que no deben ser accesibles desde el browser:**
`raw/`, `migrations/`, `scripts/`, `lib/`

## Pendientes técnicos

| Item | Archivo | Impacto |
|---|---|---|
| `fecha_nac` NULL en todos los registros | `lib/parsers/PadronTSEParser.php` | Bloquea segmentación por edad |
| Login hardcodeado en `auth.php` — no usa tabla `users` | `auth.php` | Gestión de usuarios desde Admin no funciona |
| `polling_places` tiene 13 filas de prueba | BD | Reporte de locales de votación no es real |
| `electoral_district_id` y `polling_place_id` NULL en `voters` | BD | Asignación de JRV no está cruzada |
| Reporte #7 Indicadores Estratégicos no construido | — | Requiere definir KPIs con el cliente |
| Coordinar con TSE acceso oficial a `fecha_nac` | Externo | Requerido para segmentación por edad |
| Obtener catálogo real de locales de votación (~7,000 filas) | Externo | `polling_places` tiene datos de prueba |

## Cumplimiento normativo y límites de responsabilidad

Los datos del padrón electoral y resultados electorales utilizados en esta
plataforma son **fuentes públicas oficiales** del Tribunal Supremo de Elecciones
(TSE) de Costa Rica.

**Fuentes:**
- Padrón electoral 2026: descargado desde https://www.tse.go.cr/padron.html
- Resultados electorales (AVR): publicados por el TSE en sus sistemas de
  divulgación de resultados.
- Catálogo geográfico (DISTELEC): incluido en el ZIP del padrón oficial del TSE.
- Fronteras GeoJSON: basadas en datos del repositorio `schweini/CR_distritos_geojson`.

**Límites de responsabilidad:**
- Esta plataforma **reproduce** datos públicos del TSE. No produce, modifica ni
  certifica datos electorales.
- Los análisis, segmentaciones y rankings generados son herramientas internas
  de trabajo del partido y **no constituyen resultados electorales oficiales**.
- El TSE es la única fuente autorizada de resultados electorales en Costa Rica
  (Artículo 102 de la Constitución Política).
- La plataforma no almacena datos sensibles adicionales sobre los electores
  más allá de lo publicado en el padrón oficial.
- El acceso a la plataforma está restringido a usuarios internos autorizados
  del partido.
- Para uso, distribución o publicación de cualquier dato derivado, verificar
  los términos de uso del TSE en https://www.tse.go.cr

## Créditos de datos

- Padrón y catálogo geográfico: Tribunal Supremo de Elecciones de Costa Rica (TSE)
- Fronteras distritales GeoJSON: `schweini/CR_distritos_geojson`
