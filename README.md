# PEL Digital

Plataforma interna de analisis electoral y territorial para el Partido
Esperanza y Libertad. Uso exclusivo interno del partido.

El primer reporte operativo es **Padron Electoral → Distribucion Territorial**:
mapa de calor con drill-down territorial, consulta paginada del padron real y
vista de diaspora electoral mundial.

## Estado actual (06 junio 2026)

- Reporte principal: **Analisis → Padron Electoral → Distribucion Territorial**.
- Mapa nacional con drill-down por **provincia → canton → distrito**.
- Modo **Nacional** restringido a Costa Rica (bounds fijos).
- Modo **Extranjero** con vista mundial de diaspora electoral (burbujas por pais).
- Minimapa de contexto al navegar en cantones o distritos; clic vuelve a vista
  nacional y limpia filtros.
- Consulta real del padron desde MySQL via `api/padron.php`, con paginacion,
  busqueda por cedula/nombre/apellidos/junta y exportacion CSV.
- Busqueda territorial con autocomplete y selects encadenados.
- Ranking Top 10, resumen estadistico del nivel y leyenda de escala cromatica.
- Login por sesion PHP con hash bcrypt. La tabla `users` tiene 3 usuarios reales
  (administrador, analista, consulta) pero el login aun usa arreglo hardcodeado
  en `auth.php`; la integracion contra BD esta pendiente.
- Bitacora basica de accesos e interacciones (archivo + tabla `audit_logs`).
- Tema claro / oscuro persistido en localStorage.

## Datos

El mapa y el modal de resultados usan datos reales del padron TSE 2026.

Datos verificados el 06 junio 2026:

- Padron importado: `3,731,788` registros en `voters`.
- Padron nacional usado por el mapa: `3,664,518` inscritos (province_id 1-7).
- Registros de exterior (diaspora): `67,270`.
- Juntas distintas en `voters.junta`: `7,154`.
- Ultima carga completa verificada: `2026-06-01 17:49:50` a `2026-06-01 18:07:15`.

Campos con datos en voters: `cedula`, `nombre`, `apellido1`, `apellido2`,
`fecha_caduc`, `junta`, `province_id`, `canton_id`, `district_id`.

Campos vacios en todos los registros: `sexo`, `fecha_nac`,
`electoral_district_id`, `polling_place_id`. Estos limitan los reportes de
segmentacion por edad/sexo y por distrito electoral hasta que se carguen desde
una fuente oficial completa.

La fuente declarada por `api/poblacion.php` es `TSE 2026 — padron real`.

## Alcance funcional actual

### Incluido

- Distribucion territorial del padron.
- Concentracion de inscritos por provincia, canton y distrito.
- Consulta de personas inscritas por region seleccionada.
- Visualizacion de diaspora electoral por pais.
- Navegacion y reset rapido a vista nacional.

### No incluido aun

- Personas que votaron.
- Personas que no votaron.
- Participacion electoral real.
- Abstencion real.
- Resultados electorales por partido/candidato.
- Comparativos historicos.
- Reportes de participacion por JRV.
- Priorizacion automatica de zonas de campana.
- Gestion completa de usuarios/roles desde interfaz.

Esos puntos requieren cargar resultados electorales historicos y/o archivos
oficiales adicionales, no solo el padron.

## Stack

- PHP 8.x
- MySQL/MariaDB en XAMPP
- Leaflet 1.9.4
- HTML/CSS/JavaScript sin framework de build
- GeoJSON de fronteras de Costa Rica

## Estructura principal

```text
.
├── index.php              # Tablero principal protegido por login
├── login.php              # Pantalla de ingreso
├── logout.php             # Cierre de sesion
├── auth.php               # Sesion y credenciales actuales
├── includes/
│   ├── layout/            # Head, header, footer, loader y scripts comunes
│   ├── modals/            # Modales reutilizados por la interfaz
│   └── reports/           # Vistas especificas de cada reporte
├── api/
│   ├── poblacion.php      # Agregados territoriales del padron
│   ├── padron.php         # Consulta paginada del padron real
│   ├── bitacora.php       # Lectura de bitacora
│   └── log.php            # Registro de interacciones frontend
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── img/
├── data/
│   ├── provincias.geojson
│   ├── cantones.geojson
│   ├── distritos.geojson
│   └── poblacion_cache.json
├── lib/
│   ├── db.php
│   ├── bitacora.php
│   └── parsers/PadronTSEParser.php
└── scripts/
    ├── import_distelec.php
    ├── import_padron.php
    ├── migrate.php
    └── test_batch.php
```

## Como ejecutar

Con PHP integrado:

```bash
php -S localhost:8099
```

Luego abrir:

```text
http://localhost:8099/
```

Tambien funciona desde `htdocs` de XAMPP.

## Acceso actual

- Usuario: `demo`
- Contrasena: `demo1234`

La autenticacion actual usa un arreglo en `auth.php`. La base de datos ya tiene
tablas de `users`, `roles` y `permissions`, pero esa gestion aun no esta
integrada al login de la aplicacion.

## Notas tecnicas

- `index.php` funciona como ensamblador de vistas: valida sesion y carga
  parciales de `includes/`.
- La estructura nueva separa layout, modales y reportes para evitar duplicar
  encabezado, pie, contenedores y controles comunes cuando se agreguen nuevos
  reportes.
- El reporte actual vive en `includes/reports/padron-distribucion.php`.
- `api/padron.php` usa paginacion para evitar cargar millones de filas en el
  navegador.
- Las busquedas textuales del padron usan el indice FULLTEXT existente sobre
  `nombre`, `apellido1` y `apellido2`.
- Las busquedas numericas funcionan por prefijo de cedula o junta exacta.
- En modo busqueda, el total puede mostrarse como estimado para evitar conteos
  costosos sobre millones de registros.
- El boton **Nacional** mantiene internamente la metrica `electoral`.

## Pendientes tecnicos conocidos

- Texto "Poblacion simulada (no oficial)" en `includes/layout/footer.php` aun
  no fue corregido.
- Mostrar `fuente` y `generado` de `api/poblacion.php` en la interfaz (footer o
  panel lateral).
- Integrar login contra la tabla `users` (hoy usa arreglo en `auth.php`).
- Cargar `sexo` y `fecha_nac` desde la fuente TSE — hoy son NULL en todos los
  registros, lo que bloquea segmentacion por edad y sexo.
- Poblar `electoral_district_id` y `polling_place_id` en `voters`; hoy son NULL.
  Las tablas `polling_places` (13 filas) y `electoral_districts` (10 filas) son
  datos de prueba, no el catalogo real del TSE.
- Crear reporte de inscritos por JRV (el campo `junta` existe en voters con
  7,154 distintos, pero no hay pantalla de reporte dedicada).
- Completar modulos de Admin: usuarios, roles, carga de datos y pipelines
  (hoy son placeholders en el menu).
- Formalizar migraciones completas del esquema base.
- Cargar datos oficiales de resultados electorales historicos (requerido para
  reportes de participacion, abstencion y comportamiento electoral).

Fronteras: `schweini/CR_distritos_geojson`.
