# Roadmap

Este documento resume el estado funcional y las brechas. El detalle historico de
la minuta del cliente queda en `docs/roadmap/expectativas-cliente.md`.

## Estado actual

| Area | Estado | Nota |
|---|---|---|
| Distribucion territorial | Activo | Usa padron real y GeoJSON. |
| JRV inscritos | Activo | Usa `summary_jrv`. |
| JRV analisis estrategico | Activo | Cruza padron y resultados disponibles. |
| Participacion electoral | Activo | Usa resultados AVR importados. |
| Segmentacion electoral | Parcial | Sexo enriquecido; edad pendiente por `fecha_nac`. |
| Analisis territorial | Activo | Comparativos con resultados historicos cargados. |
| Indicadores estrategicos | Pendiente | Requiere definicion de KPIs con cliente. |
| Locales de votacion | Pendiente | Requiere catalogo real de `polling_places`. |

## Prioridades tecnicas

1. Convertir gradualmente `assets/js/app/*` a modulos con dependencias explicitas.
2. Usar helpers de `lib/api.php` en APIs nuevas y al tocar APIs existentes.
3. Cargar catalogos reales faltantes para locales y distritos electorales.
4. Completar estrategia de `fecha_nac`.
5. Agregar pruebas automatizadas para parsers y APIs principales.

## Segunda etapa sugerida

- Refactor de reportes para cargar vistas desde el catalogo BD con una capa de
  validacion de rutas permitidas.
- Pruebas automatizadas para parsers y APIs principales.
- Politicas de permisos por rol mas granulares si se necesitan roles de admin
  parciales. Hoy el panel admin requiere rol `administrador`.
