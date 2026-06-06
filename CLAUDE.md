# CLAUDE.md — PEL Digital

Guia tecnica para agentes de codigo. Describe la arquitectura real del proyecto,
convenciones activas y puntos criticos a tener en cuenta.

## Stack

- PHP 8.x (sin framework — funciones globales, include directo)
- MySQL / MariaDB via XAMPP (puerto 3306, DB: `pel_electoral`, usuario: `root`, sin password)
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
index.php                        # Ensamblador de vistas (14 lineas). Valida sesion y
                                 # concatena parciales de includes/.
auth.php                         # Sesion PHP. Login, logout, helpers de autenticacion.
                                 # Usuarios hardcodeados en arreglo $USUARIOS (bcrypt).
login.php / logout.php           # Pantallas de acceso / cierre de sesion.

includes/
  layout/
    head.php                     # DOCTYPE, meta, CSS, anti-flash de tema
    header.php                   # Barra superior + menu principal (nav con dropdowns)
    footer.php                   # Pie de pagina (ATENCION: aun dice "simulada")
    loader.php                   # Spinner de carga inicial
    scripts.php                  # Tags <script> al final del body (Leaflet + app.js)
  modals/
    padron.php                   # Modal de consulta del padron (tabla paginada)
    bitacora.php                 # Modal de bitacora de actividad
  reports/
    padron-distribucion.php      # Vista HTML del reporte actual: mapa + panel lateral

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
  css/style.css                  # CSS global (929 lineas). Variables CSS para temas.
  js/app.js                      # JS monolitico (1393 lineas). Todo el frontend vive
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

scripts/
  import_padron.php              # Importa el padron TSE a la tabla voters.
  import_distelec.php            # Importa el catalogo DISTELEC (provincias/cantones/
                                 # distritos) a las tablas de geografia.
  migrate.php                    # Runner de migraciones SQL desde migrations/.
  test_batch.php                 # Pruebas de importacion por lotes.

migrations/
  20260601_000003_diaspora_index.sql  # Indice de diaspora
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
| `polling_places` | Locales de votacion (JRV) | 13 (datos de prueba) |
| `electoral_districts` | Distritos electorales | 10 (datos de prueba) |
| `audit_logs` | Bitacora de actividad | variable |

### Campos poblados en voters

Completos: `cedula`, `nombre`, `apellido1`, `apellido2`, `fecha_caduc`, `junta`,
`province_id`, `canton_id`, `district_id`.

Vacios (NULL en todos los registros): `sexo`, `fecha_nac`,
`electoral_district_id`, `polling_place_id`.

### Convencion de codigos geograficos

El TSE usa codigos de 6 digitos para distritos (`codelec`): `101001`.
El GeoJSON usa 5 digitos: `10101`.
Conversion: `substr(codelec, 0, 3) + lpad(int(substr(codelec, 3)), 2, '0')`.

La provincia exterior (diaspora) usa `province_id = 8`. Los paises se almacenan
como "cantones" hijos de esa provincia.

El campo `junta` en voters es el numero de junta receptora de votos (string
con padding a 5 digitos). Hay 7,154 juntas distintas. No hay FK a
`polling_places` porque esa tabla tiene datos de prueba.

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

### Estado por reporte (al 06 junio 2026)

| # | Reporte | Estado | Bloqueado por |
|---|---|---|---|
| 0 | Distribución Territorial del Padrón (actual) | ✅ Construido | — |
| 1 | Participación Electoral | ❌ Pendiente | Requiere votos emitidos por elección y JRV del TSE |
| 2 | Segmentación Electoral | ⚠️ Parcial posible | sexo/fecha_nac vacios; electoral_district_id vacio |
| 3 | Análisis Territorial | ⚠️ Parcial posible | Resultados electorales historicos no cargados |
| 4 | JRV — Inscritos | ✅ Construible ahora | Campo junta disponible, 7,063 juntas nacionales |
| 4 | JRV — Participación | ❌ Pendiente | Requiere votos emitidos por JRV del TSE |
| 5 | Indicadores Estratégicos | ❌ Pendiente | Depende de reportes 1-4 |

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
- Nombre y direccion del local de votacion (catalogo real de polling_places).
  Hoy polling_places tiene 13 filas de prueba; el real deberia tener ~7,000+.
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

- `includes/layout/footer.php` dice "Poblacion simulada (no oficial)" — cambiar
  a atribucion real del TSE.
- `assets/js/app.js` linea 3 tiene comentario "Poblacion dummy" — corregir.
- Login usa arreglo hardcodeado en `auth.php`; integrar contra tabla `users`.
- `sexo` y `fecha_nac` son NULL en todos los registros de `voters` — revisar
  el parser TSE.
- `electoral_district_id` y `polling_place_id` son NULL en `voters` — datos
  de las tablas de catalogo no son reales.
- La fecha y fuente de actualizacion TSE se deben mostrar en la interfaz
  (el API ya las devuelve en los campos `fuente` y `generado`).
- Falta reporte de inscritos por JRV (datos disponibles: campo `junta`).
- Falta cargar resultados electorales historicos para reportes de participacion.
