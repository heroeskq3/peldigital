# Agentes de codigo — PEL Digital

Guia tecnica para agentes de codigo. Describe la arquitectura real del proyecto,
convenciones activas y puntos criticos a tener en cuenta.

## Stack

- PHP 8.x (sin framework — funciones globales, include directo)
- MySQL / MariaDB via XAMPP; credenciales por `.env` (`.env.example` documenta defaults locales)
- Leaflet 1.9.4 (mapas)
- Bootstrap Icons 1.11.3 (iconos)
- HTML / CSS / JavaScript — sin framework de build, sin bundler, sin npm

## Como ejecutar localmente

```bash
# Opcion 1: XAMPP (recomendado)
# Colocar el proyecto en /Applications/XAMPP/xamppfiles/htdocs/pel_02
# Iniciar Apache y MySQL desde XAMPP
# Abrir: http://localhost/pel_02/

# Opcion 2: PHP built-in desde la raiz del proyecto
php -S localhost:8099
# Abrir: http://localhost:8099/
```

Login de acceso: usuario `demo`, contrasena `demo1234`.

## Estructura de archivos

```
index.php                        # Entrada minima. Valida sesion y redirige a reports.php?id=1.
auth.php                         # Sesion PHP. Login, logout, helpers de autenticacion.
                                 # Autentica contra tabla users; fallback demo solo local.
login.php / logout.php           # Pantallas de acceso / cierre de sesion.

includes/
  layout/
    head.php                     # DOCTYPE, meta, CSS, anti-flash de tema
    header.php                   # Barra superior + menu principal (nav con dropdowns)
    footer.php                   # Pie de pagina con atribucion TSE y fecha/fuente si la API la expone.
    loader.php                   # Spinner de carga inicial
    scripts.php                  # Tags <script> al final del body (Leaflet + app.js)
  modals/
    padron.php                   # Modal de consulta del padron (tabla paginada)
    bitacora.php                 # Modal de bitacora de actividad
  reports/
    padron-distribucion.php      # Reporte de distribucion territorial.
    jrv-inscritos.php            # Reporte de inscritos por JRV.
    jrv-analisis.php             # Analisis estrategico por JRV.
    participacion.php            # Participacion electoral.
    segmentacion.php             # Segmentacion electoral.
    analisis-territorial.php     # Comparativos territoriales.
    distritos-electorales.php    # Distritos electorales.
    juntas-padronal.php          # Juntas padronales.

api/
  poblacion.php                  # Agrega conteos del padron por provincia/canton/
                                 # distrito. Cache en data/poblacion_cache.json (1h TTL).
                                 # Devuelve: { provincias, cantones, distritos, diaspora,
                                 #            fuente, generado }
  padron.php                     # Consulta paginada del padron real. Params: nivel,
                                 # codigo, page, size, q. Usa FULLTEXT para busqueda
                                 # textual y prefijo para cedula/junta.
  bitacora.php                   # Lectura de bitacora de actividad.
  log.php                        # Registro de eventos desde frontend.

assets/
  css/style.css                  # CSS global (~2600 lineas). Variables CSS para temas.
  js/app.js                      # JS monolitico (~2700 lineas). Todo el frontend vive
                                 # aqui: mapa, navegacion, panel, diaspora, padron,
                                 # bitacora, busqueda, exportacion, toast, minimapa.
  img/logo.svg / logo02.png      # Logos del partido

lib/
  db.php                         # dbConnect(): PDO singleton. Conexion a pel_electoral.
  bitacora.php                   # Funciones de registro de eventos en bitacora.
  parsers/PadronTSEParser.php    # Parser del archivo plano del TSE para importacion.

data/
  provincias.geojson             # Fronteras de las 7 provincias de CR
  cantones.geojson               # Fronteras de los cantones
  distritos.geojson              # Fronteras de los distritos
  poblacion_cache.json           # Cache de api/poblacion.php. Se regenera si tiene >1h.

raw/                             # ARCHIVOS CRUDOS (fuentes originales — NO en git)
  padron/                        # Descarga manual desde TSE (https://www.tse.go.cr/padron.html)
    PADRON_COMPLETO.txt          #   427 MB — padron plano 2026 (8 campos, ~3.7M filas)
    distelec.txt                 #   172 KB — catalogo geografico DISTELEC del TSE
    Leame.txt                    #   Descripcion de formato del TSE
  avr/                           # Descarga manual desde servicioselectorales.tse.go.cr
    avr2026.json                 #   2.5 MB — resultados presidenciales 2026 (final)
    avr2024.json                 #   1.3 MB — resultados municipales 2024 — Alcaldes
    avr2022.json                 #   2.9 MB — resultados presidenciales 2022 1ra ronda
    avr2022_ii.json              #   514 KB — resultados presidenciales 2022 2da ronda
  geo/                           # Fuentes geograficas (GeoJSON ya en data/, esta carpeta
                                 #   reservada para nuevas fuentes geo crudas)

scripts/
  import_padron.php              # Importa el padron TSE a la tabla voters.
                                 #   Uso: php scripts/import_padron.php --zip=raw/padron/padron.zip
                                 #        php scripts/import_padron.php --file=raw/padron/PADRON_COMPLETO.txt
  import_distelec.php            # Importa el catalogo DISTELEC (provincias/cantones/
                                 # distritos) a las tablas de geografia.
                                 #   Uso: php scripts/import_distelec.php --file=raw/padron/distelec.txt
  import_resultados.php          # Importa resultados electorales del AVR del TSE.
                                 #   Uso: php scripts/import_resultados.php --json=raw/avr/avr2026.json --type=P
                                 #        php scripts/import_resultados.php --json=raw/avr/avr2024.json --type=A
  migrate.php                    # Runner de migraciones SQL desde migrations/.
  dev/test_batch.php             # Pruebas de importacion por lotes (desarrollo).

migrations/
  20260601_000003_diaspora_index.sql  # Indice de diaspora
```

## Pipeline de ingesta (ETL)

Todas las fuentes de datos externas son **archivos descargables**. No se usan APIs
en tiempo real. Los archivos se descargan manualmente (o via script de descarga),
se depositan en `raw/`, y los scripts ETL los procesan sobre esa carpeta.

| Fuente | Tipo | URL de descarga | Script ETL | Tablas destino |
|---|---|---|---|---|
| Padron TSE 2026 | TXT plano (427 MB) | tse.go.cr/padron.html | `scripts/import_padron.php --file=raw/padron/PADRON_COMPLETO.txt` | `voters` |
| DISTELEC 2026 | TXT plano (172 KB) | incluido en ZIP del padron | `scripts/import_distelec.php --file=raw/padron/distelec.txt` | `provinces`, `cantons`, `districts` |
| AVR2026 — Presidencia | JSON (2.5 MB) | tse.go.cr/APISVR2026/cortes/ultimo?corte=0 | `scripts/import_resultados.php --json=raw/avr/avr2026.json --type=P` | `election_results` |
| AVR2024 — Municipales | JSON (1.3 MB) | servicioselectorales.tse.go.cr/AVR2024/api/resultados/ | `scripts/import_resultados.php --json=raw/avr/avr2024.json --type=A` | `election_results` |
| AVR2022 1ra ronda | JSON (2.9 MB) | servicioselectorales.tse.go.cr/AVR2022/api/resultados/ | `scripts/import_resultados.php --json=raw/avr/avr2022.json --type=P` | `election_results` |
| AVR2022 2da ronda | JSON (514 KB) | servicioselectorales.tse.go.cr/AVR2022_II/api/resultados/ | `scripts/import_resultados.php --json=raw/avr/avr2022_ii.json --type=P` | `election_results` |
| GeoJSON fronteras | JSON estatico | Incluido en repo (data/*.geojson) | No requiere ETL | Solo lectura JS |
| Catalogo partidos | SQL manual | Construido desde Wikipedia + AVR | `scripts/migrate.php` | `parties` |
| Lookup nombre→sexo | SQL en migracion | 321 nombres top del padron, clasificados manualmente | `scripts/enrich_sexo.php` | `voters.sexo` (via `name_gender_lookup`) |

### Nota sobre los endpoints AVR del TSE

Las URLs del TSE que terminan en `/api/resultados/` NO son APIs dinamicas. Son
archivos JSON estaticos de resultado final. El TSE los llama "api" pero en produccion
son equivalentes a un archivo descargable que no cambia una vez cerrado el escrutinio.
La descarga en ambiente local se hizo via browser (mismo origen) por restricciones
del WAF del TSE. En produccion usar curl con Referer apropiado.

### Orden de ejecucion en un servidor nuevo

```bash
# 1. Migraciones de BD
php scripts/migrate.php

# 2. Catalogo geografico (prerequisito para padron y AVR)
php scripts/import_distelec.php --file=raw/padron/distelec.txt

# 3. Padron electoral
php scripts/import_padron.php --file=raw/padron/PADRON_COMPLETO.txt

# 4. Resultados electorales (en cualquier orden)
php scripts/import_resultados.php --json=raw/avr/avr2026.json --type=P --label="Presidencia 2026"
php scripts/import_resultados.php --json=raw/avr/avr2024.json --type=A --label="Municipal 2024"
php scripts/import_resultados.php --json=raw/avr/avr2022.json --type=P --label="Presidencial 2022 1ra"
php scripts/import_resultados.php --json=raw/avr/avr2022_ii.json --type=P --label="Presidencial 2022 2da"

# 5. Enriquecer sexo via lookup de nombres (no requiere red)
php scripts/enrich_sexo.php --batch=0
```

## Base de datos: tablas clave

| Tabla | Proposito | Registros (06-jun-2026) |
|---|---|---|
| `voters` | Padron electoral completo TSE 2026 | 3,731,788 |
| `provinces` | Catalogo de 7 provincias + exterior (id=8) | 8 |
| `cantons` | Catalogo de cantones + paises de diaspora | ~90+ |
| `districts` | Catalogo de distritos con campo `codelec` (TSE) | ~500+ |
| `users` | Usuarios de la plataforma con roles | 3 (demo) |
| `roles` | Roles: administrador, analista, consulta | 3 |
| `polling_places` | Locales de votacion (JRV) | Pendiente de carga oficial |
| `electoral_districts` | Distritos electorales | Pendiente de carga oficial |
| `audit_logs` | Bitacora de actividad | variable |

### Campos poblados en voters

Completos: `cedula`, `nombre`, `apellido1`, `apellido2`, `fecha_caduc`, `junta`,
`province_id`, `canton_id`, `district_id`.

Enriquecidos via ETL (no en el padron TSE original):
- `sexo`: poblado con M/F/N via `scripts/enrich_sexo.php` usando lookup de nombres.
  Distribucion: M=1,428,900 (38.3%), F=1,246,161 (33.4%), N=1,056,727 (28.3% sin match).
  Lookup en tabla `name_gender_lookup` (321 nombres, top frecuentes del padron).

Vacios (NULL en todos los registros): `fecha_nac`,
`electoral_district_id`, `polling_place_id`.

### Convencion de codigos geograficos

El TSE usa codigos de 6 digitos para distritos (`codelec`): `101001`.
El GeoJSON usa 5 digitos: `10101`.
Conversion: `substr(codelec, 0, 3) + lpad(int(substr(codelec, 3)), 2, '0')`.

La provincia exterior (diaspora) usa `province_id = 8`. Los paises se almacenan
como "cantones" hijos de esa provincia.

El campo `junta` en voters es el numero de junta receptora de votos (string
con padding a 5 digitos). Hay 7,154 juntas distintas. No hay FK a
`polling_places` porque falta cargar el catalogo oficial de locales.

## app.js: arquitectura interna

El archivo es un IIFE (`(function(){ ... })()`). Todo el estado vive en variables
de modulo (no globales). Estructura de funciones por area:

- **Estado**: objeto `state` con `nivel`, `codProvincia`, `codCanton`,
  `codDistrito`, `metrica`.
- **Carga de datos**: `init()` → `fetchJSON('api/poblacion.php')` → construye
  indices internos → `dibujarNivel('provincia')`.
- **Mapa**: `dibujarNivel(nivel)`, `estiloFeature()`, `calcularEscala()`,
  `colorPara()`. Usa `capasPorCodigo` para acceso O(1) a capas por codigo.
- **Navegacion**: `navegarA(nivel, prov, cant, dist)` es el punto central de
  drill-down y breadcrumb.
- **Panel lateral**: `actualizarPanel()`, `mostrarDetalle()`, `actualizarLeyenda()`.
- **Diaspora**: `dibujarDiaspora()` / `limpiarDiaspora()` — cambia bounds a mundo.
- **Minimapa**: `setupMiniMap()` — muestra contexto nacional al navegar a canton/
  distrito. Clic vuelve a `navegarA('provincia', null, null, null)`.
- **Padron (modal)**: objeto `padron` con estado de paginacion. `cargarPadron()`
  llama a `api/padron.php`. `renderPadron()` pinta la tabla. Export CSV en
  `exportarPadron()`.
- **Busqueda territorial**: `construirIndice()` + autocomplete en `#buscador`.
- **Bitacora**: `setupBitacora()` — abre modal, carga desde `api/bitacora.php`.
- **Menu**: `setupNav()` — maneja dropdowns y submenus. Activa reportes via
  atributo `data-analisis` en botones del menu.

## api/poblacion.php: logica de cache y rendimiento

- Cache en `data/poblacion_cache.json` con TTL de 1 hora.
- Para forzar regeneracion: GET `api/poblacion.php?refresh=1`.
- La consulta usa dos pasos para evitar JOINs sobre 3.7M filas: primero
  `GROUP BY` con indice cubriente, luego enriquece con tablas pequeñas de
  geografia en memoria (≈18x mas rapido que JOIN directo).
- El campo `extranjero` a nivel canton y distrito es proporcional al padron,
  no un conteo directo (no hay dato real por canton/distrito para el exterior).

## Temas (light / dark)

Implementado via `data-theme="light|dark"` en `<html>`. Variables CSS en
`style.css` bajo `:root` y `[data-theme="dark"]`. Persiste en `localStorage`
con clave `cr-theme`. Anti-flash en `head.php` (script inline antes del CSS).

## Agregar un nuevo reporte

1. Crear la vista HTML en `includes/reports/mi-reporte.php`.
2. Agregar un `<button data-analisis="mi-reporte">` en el menu de `header.php`.
3. En `app.js`, dentro de `setupNav()`, manejar el caso `data-analisis === 'mi-reporte'`
   e inicializar la logica del reporte.
4. Si necesita API propia, crear `api/mi-reporte.php` siguiendo el patron de
   `api/padron.php` (requerirLoginApi + PDO + JSON output).
5. Actualizar `index.php` para incluir la nueva vista si es una pagina distinta,
   o manejarla como panel dinamico dentro del mismo index.

## Roadmap de reportes

### Estado por reporte (al 11 junio 2026)

| # | Reporte | Estado | Nota |
|---|---|---|---|
| 1 | Distribucion Territorial del Padron | Activo | Mapa + panel territorial con padron real |
| 2 | Distribucion Padron / JRV | Activo | Inscritos por junta desde `summary_jrv` |
| 3 | Analisis Estrategico JRV | Activo | Usa padron y resultados disponibles |
| 4 | Participacion Electoral | Activo | Usa AVR importado |
| 5 | Segmentacion Electoral | Parcial | Sexo enriquecido; edad bloqueada por `fecha_nac` |
| 6 | Analisis Territorial | Activo | Comparativos con resultados historicos |
| 7 | Indicadores Estrategicos | Pendiente | Requiere definir KPIs con el cliente |

### Reporte #4 JRV — contexto para construcción futura

**Datos disponibles hoy:**
- `voters.junta`: numero de junta (string, ej. "02475"), presente en todos los
  registros. 7,063 juntas nacionales + 91 del exterior = 7,154 total.
- Cada junta pertenece a exactamente un `district_id` — verificado: ninguna junta
  aparece en mas de un distrito. Se puede ubicar territorialmente via JOIN con
  `provinces`, `cantons`, `districts`.
- Rango de inscritos: 6 (minimo) a 886 (maximo nacional). Promedio 519.
- El maximo de 886 corresponde a una junta en carcel (CAI) en Alajuela.

**Lo que se puede construir sin datos adicionales:**
- Ranking de juntas por inscritos (mayor a menor y menor a mayor).
- Top 10 / Bottom 10 nacional.
- Distribucion de juntas por provincia, canton y distrito.
- Tabla paginada filtrable por territorio.

**Lo que requiere datos adicionales del TSE:**
- Participacion real (votos emitidos / inscritos por JRV).
- Abstencion por JRV.
- Nombre y direccion del local de votacion (catalogo real de `polling_places`,
  pendiente de carga oficial).
- Comparativos historicos por JRV.

**API sugerida para cuando se construya:**
```
GET api/jrv.php?nivel=provincia&codigo=2&page=1&size=25
GET api/jrv.php?nivel=canton&codigo=101&page=1
GET api/jrv.php?nivel=distrito&codigo=10101
```
Respuesta: `{ rows: [{junta, provincia, canton, distrito, inscritos}], total, pages }`

### Reporte #1 Participación Electoral — datos requeridos del TSE

Para construir este reporte se necesita un archivo oficial con:
- Total de votos emitidos por JRV por eleccion.
- Fecha de cada eleccion (municipales y nacionales separadas).
- Idealmente: resultados por partido/candidato por JRV.

Sin esos datos no se puede calcular % participacion, abstencion ni historicos.
El padron actual (voters) solo dice quien ESTABA inscrito, no quien VOTO.

## Pendientes criticos conocidos

- `auth.php` conserva fallback `demo` solo fuera de produccion; confirmar
  `APP_ENV=production` antes de publicar.
- `fecha_nac` es NULL en todos los registros de `voters`; requiere fuente oficial
  o completar parser/ingesta si el archivo disponible contiene el campo.
- `electoral_district_id` y `polling_place_id` son NULL en `voters` — datos
  de las tablas de catalogo no son reales.
- Reporte #7 Indicadores Estrategicos requiere definicion de KPIs con el cliente.
- `polling_places` no tiene catalogo oficial cargado; falta fuente real de locales.
