# PEL Digital

Plataforma interna de analisis electoral y territorial para el Partido
Esperanza y Libertad. El sistema actual consolida el primer reporte operativo:
**Padron Electoral -> Distribucion Territorial**.

El reporte permite explorar la distribucion del padron nacional de Costa Rica y
la poblacion electoral inscrita en el extranjero, con navegacion territorial,
mapa interactivo y consulta paginada del padron real.

## Estado actual

- Reporte principal: **Analisis -> Padron Electoral -> Distribucion Territorial**.
- Mapa nacional con drill-down por **provincia -> canton -> distrito**.
- Modo **Nacional** restringido a Costa Rica.
- Modo **Extranjero** con vista mundial de diaspora electoral.
- Minimapa de contexto al navegar en cantones o distritos; al hacer clic vuelve
  a la vista nacional y limpia filtros.
- Consulta real del padron desde MySQL, con paginacion y busqueda por cedula,
  nombre, apellidos o junta.
- Exportacion CSV de la pagina visible del padron.
- Busqueda territorial y selects encadenados.
- Ranking Top 10, resumen del nivel y leyenda de escala.
- Login por sesion PHP.
- Bitacora basica de accesos e interacciones.
- Tema claro / oscuro.

## Datos

El mapa y el modal de resultados usan datos reales cargados desde la base de
datos local `pel_electoral`.

Datos verificados durante la revision:

- Padron importado: `3,731,788` registros en `voters`.
- Padron nacional usado por el mapa: `3,664,518` inscritos.
- Registros de exterior: `67,270`.
- Juntas distintas en el campo `junta`: `7,154`.
- Ultima carga completa verificada: `2026-06-01 17:49:50` a `2026-06-01 18:07:15`.
- Cache de poblacion generado: `2026-06-02T06:01:53+00:00`.

La fuente declarada por `api/poblacion.php` es `TSE 2026 - padron real`.

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

- Actualizar textos visibles que aun mencionan poblacion simulada/no oficial.
- Mostrar en la interfaz la fecha de ultima actualizacion oficial del TSE.
- Integrar login contra la tabla `users`.
- Completar modulos de Admin: usuarios, roles, carga de datos y pipelines.
- Formalizar migraciones completas del esquema base.

Fronteras: `schweini/CR_distritos_geojson`.
