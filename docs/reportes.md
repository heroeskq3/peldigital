# Inventario de reportes

La fuente principal del catalogo es la base de datos (`reports` y
`report_categories`). Este documento no reemplaza esa fuente; sirve como mapa
operativo para desarrollo y soporte.

| # | Reporte | Slug JS / BD | Vista | API principal | Estado |
|---|---|---|---|---|---|
| 1 | Distribucion Territorial del Padron | `distribucion-territorial` | `includes/reports/padron-distribucion.php` | `api/poblacion.php` | Activo |
| 2 | Distribucion Padron / JRV | `jrv-inscritos` | `includes/reports/jrv-inscritos.php` | `api/jrv.php` | Activo |
| 3 | Analisis Estrategico JRV | `jrv-analisis-estrategico` | `includes/reports/jrv-analisis.php` | `api/jrv.php`, `api/resultados.php` | Activo |
| 4 | Participacion Electoral | `participacion-electoral` | `includes/reports/participacion.php` | `api/participacion.php` | Activo |
| 5 | Segmentacion Electoral | `segmentacion-electoral` | `includes/reports/segmentacion.php` | `api/segmentacion.php` | Parcial |
| 6 | Analisis Territorial | `analisis-territorial` | `includes/reports/analisis-territorial.php` | `api/analisis_territorial.php` | Activo |
| 7 | Indicadores Estrategicos | `indicadores-estrategicos` | Pendiente | Pendiente | Pendiente |

## Regla de mantenimiento

- Cambiar el catalogo funcional en BD/migraciones.
- Mantener este documento sincronizado cuando se agregue, retire o renombre un
  reporte.
- No usar este documento como fuente runtime.
