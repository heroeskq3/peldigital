# Arquitectura — PEL Digital

PEL Digital es una aplicacion PHP 8 sin framework externo. La arquitectura combina
paginas PHP, parciales de layout, APIs JSON, scripts CLI de ingesta y frontend
HTML/CSS/JavaScript sin bundler.

## Flujo de pagina

La entrada normal es `index.php`, que valida sesion y redirige a
`reports.php?id=1`. `reports.php` consulta el catalogo de reportes en la base de
datos, define el reporte activo y ensambla la pagina con esta cadena:

```text
includes/layout/head.php
includes/layout/header.php
includes/reports/*.php
includes/layout/footer.php
includes/modals/*.php
includes/layout/loader.php
includes/layout/scripts.php
```

`admin.php` usa la misma cadena de layout, pero carga parciales desde
`includes/admin/` y usa `assets/js/admin.js`.

## Directorios

| Ruta | Responsabilidad |
|---|---|
| `api/` | Endpoints JSON consumidos por el frontend. Siempre deben validar sesion con `requerirLoginApi()`. |
| `api/admin/` | Endpoints del panel administrativo. |
| `assets/css/` | CSS global de la aplicacion. `style.css` concentra estilos base, layout, reportes, admin y responsive. |
| `assets/js/` | JavaScript del frontend. `app.js` concentra reportes publicos; `admin.js` concentra administracion; `nav.js` maneja navegacion. |
| `assets/img/` | Logos e imagenes de interfaz. |
| `data/` | GeoJSON versionados y archivos generados de cache. Los caches no deben versionarse. |
| `docs/` | Documentacion canonica del proyecto. |
| `includes/layout/` | Parciales compartidos de layout. |
| `includes/reports/` | Estructura HTML de cada reporte. |
| `includes/admin/` | Estructura HTML de cada seccion administrativa. |
| `includes/modals/` | Modales reutilizables. |
| `lib/` | Codigo compartido backend: DB, env, bitacora, helpers y parsers. |
| `lib/parsers/` | Parsers de archivos fuente TSE. |
| `migrations/` | Evolucion versionada del esquema y catalogos base. |
| `raw/` | Archivos fuente crudos del TSE. No se versionan, solo se conserva la estructura con `.gitkeep`. |
| `scripts/` | Comandos CLI de migracion, importacion y enriquecimiento. |
| `scripts/dev/` | Scripts auxiliares de desarrollo o pruebas manuales. |

## Convenciones

- Las vistas de reportes usan nombres `kebab-case`: `analisis-territorial.php`.
- Las APIs usan nombres `snake_case` cuando el concepto tiene varias palabras:
  `analisis_territorial.php`.
- Los scripts CLI usan nombres `snake_case`: `refresh_summaries.php`.
- Los reportes se registran en base de datos, no como una lista hardcodeada.
  La tabla `reports` define nombre, estado, archivo PHP y slug JS.
- Los archivos generados (`data/*cache*.json`, logs, datos crudos) no deben
  versionarse.

## Riesgos actuales

- `assets/js/app.js` y `assets/css/style.css` son monoliticos y ya tienen alto
  costo de mantenimiento.
- `reports.php` aun incluye manualmente todas las vistas de reportes aunque el
  catalogo vive en BD.
- Varias APIs repiten patrones de JSON, error y paginacion; usar `lib/api.php`
  para cambios nuevos.
