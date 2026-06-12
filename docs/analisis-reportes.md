# Análisis de Reportes — PEL Digital
> Auditoría generada: 2026-06-12  
> Base de datos: `peldigital_data` (DW) · `pel_electoral` (app)

---

## Resumen ejecutivo

| Métrica | Valor |
|---------|-------|
| Reportes totales | 12 |
| Activos | 9 |
| Parciales | 1 (ID 5) |
| Pendientes | 2 (IDs 7, y ID 3 debería ser partial) |
| Usan datos reales | 12 / 12 (100%) — no hay datos de prueba ni demo |
| Con datos completamente disponibles | 7 |
| Con datos parcialmente disponibles | 4 (falta `fecha_nac`, `sexo` oficial, participación por JRV) |
| Sin php_file (pendiente) | 1 (ID 7) |

---

## Fuentes de datos confirmadas

Todas las APIs usan `dbData()` → conecta a `peldigital_data` (Data Warehouse).  
No existe ningún dato simulado, fake, demo o hardcodeado real en APIs.

### Tablas clave en `peldigital_data`

| Tabla | Registros | Descripción |
|-------|-----------|-------------|
| `voters` | 3,731,788 | Padrón completo TSE 2026 |
| `voter_enrichments` | 3,731,788 | Enriquecimiento (sexo estimado, etc.) |
| `summary_jrv` | 7,063 | Inscritos agregados por JRV |
| `summary_inscritos_provincia` | 7 | Resumen por provincia |
| `summary_inscritos_canton` | 126 | Resumen por cantón |
| `summary_inscritos_distrito` | 2,130 | Resumen por distrito administrativo |
| `polling_places` | 2,191 | Locales de votación habilitados |
| `electoral_districts` | 7 | Circunscripciones legislativas |
| `election_results` | 29,909 | Resultados electorales (4 elecciones) |
| `election_sync_runs` | 4 | Metadatos de elecciones cargadas |
| `name_gender_lookup` | 400 | Lookup de nombres para inferir sexo |
| `parties` | — | Catálogo de partidos TSE |

### Elecciones disponibles

| Fecha | Etiqueta | Estado |
|-------|----------|--------|
| 2026-02-02 | Presidencia 2026-02-02 | completed |
| 2024-02-05 | Municipales 2024-02-05 — Alcaldes | completed |
| 2022-02-07 | Presidencial 2022 — 1ª vuelta | completed |
| 2022-04-04 | Presidencial 2022 — 2ª vuelta | completed |

---

## Catálogo de reportes

### Categoría: Padrón & Territorio

---

#### ID 1 · Distribución Territorial del Padrón Electoral
| Campo | Valor |
|-------|-------|
| **Slug** | `distribucion-territorial` |
| **PHP file** | `padron-distribucion.php` |
| **API** | `api/padron.php` |
| **Estado** | `active` |
| **Tabla principal** | `voters`, `summary_inscritos_*` |

**Descripción (BD):** Mapa interactivo de electores inscritos por provincia, cantón y distrito. Drill-down hasta nivel de distrito con panel de detalle y comparativa de métricas.

**Datos:** Reales — 3,731,788 electores TSE 2026 incluyendo padrón exterior (provincia 8).

**Documentación interna:** ✅ `description` y `notes` completos.

**Hallazgos:** Sin problemas críticos.

---

#### ID 2 · Distribución del Padrón por JRV
| Campo | Valor |
|-------|-------|
| **Slug** | `jrv-inscritos` |
| **PHP file** | `jrv-inscritos.php` |
| **API** | `api/jrv.php` |
| **Estado** | `active` |
| **Tabla principal** | `summary_jrv` |

**Descripción (BD):** Tabla paginada de las 7 154 juntas receptoras de votos con inscritos, ranking y filtros por territorio.

**Datos:** Reales — 7,063 JRVs con inscritos reales.

**Documentación interna:** ✅ `description` y `notes` completos.

**Hallazgos:**
- ⚠️ **Descripción desactualizada**: dice "7 154 juntas" pero la tabla `summary_jrv` tiene **7,063 registros**. Corregir el campo `description` en la tabla `reports`.

---

#### ID 3 · Análisis Estratégico de JRV
| Campo | Valor |
|-------|-------|
| **Slug** | `jrv-analisis-estrategico` |
| **PHP file** | `jrv-analisis.php` |
| **API** | `api/jrv.php` |
| **Estado** | `active` ← **debería ser `partial`** |
| **Tabla principal** | `summary_jrv` |

**Descripción (BD):** Indicadores estadísticos, distribución por cuartiles y detección de juntas atípicas. Incluye histograma y ranking nacional.

**Datos:** Parciales — padrón real disponible; participación, abstención y oportunidad son **N/D** (requiere votos emitidos por JRV).

**Documentación interna:** ✅ `description` y `notes` completos. La vista tiene banner de advertencia inline.

**Hallazgos:**
- 🔴 **Estado incorrecto**: marcado como `active` pero muestra métricas de participación como N/D. Debe ser `partial`.
- ⚠️ **Clasificación proxy**: el sistema clasifica JRVs por volumen de padrón (≥600 = alta, 300-599 = media, <300 = baja) como medida temporal. No representa participación real.

---

#### ID 5 · Segmentación Electoral
| Campo | Valor |
|-------|-------|
| **Slug** | `segmentacion-electoral` |
| **PHP file** | `segmentacion.php` |
| **API** | `api/segmentacion.php` |
| **Estado** | `partial` ✓ |
| **Tabla principal** | `summary_inscritos_*`, `voter_enrichments` |

**Descripción (BD):** Distribución por sexo, rangos de edad y distrito electoral. Parcialmente construible cuando se carguen sexo y fecha_nac del padrón.

**Datos:** Parciales.

**Documentación interna:** ✅ `description`, `notes` y `requires_data` completos.

**Hallazgos:**
- 🔴 **Barras de edad hardcodeadas**: las barras de edad en el HTML (`segmentacion.php` líneas 58-65) tienen `width:78%`, `width:71%`, etc. escritos directamente en el HTML. **No son datos reales** — son placeholders visuales. El overlay "N/D" oculta la barra, pero el width existe en el DOM y puede inducir a error si se inspeccionan los estilos.
- ⚠️ **`fecha_nac` ausente**: campo `fecha_nac` en tabla `voters` = NULL para todos los registros. Edad promedio es N/D.
- ⚠️ **Cobertura de sexo limitada**: sexo estimado por lookup de nombres con solo **400 entradas** en `name_gender_lookup`. Cobertura real: 71.7% (1,036,554 / 3,664,518 sin sexo determinado). El campo `sexo` oficial del padrón TSE no está integrado.

---

#### ID 8 · Distritos Electorales
| Campo | Valor |
|-------|-------|
| **Slug** | `distritos-electorales` |
| **PHP file** | `distritos-electorales.php` |
| **API** | `api/distritos_electorales.php` |
| **Estado** | `active` |
| **Tabla principal** | `summary_inscritos_distrito`, `summary_jrv` |

**Descripción (BD):** Electorado por distrito administrativo con desglose por sexo y juntas receptoras.

**Datos:** Parciales — inscritos reales por los 2,199 distritos; sexo estimado (71.7% cobertura).

**Documentación interna:** ⚠️ `description` presente pero `notes = NULL`.

**Hallazgos:**
- ⚠️ `notes` vacío — sin documentación de limitaciones ni historial de cambios.
- ⚠️ Edad promedio es N/D (sin `fecha_nac`).
- ⚠️ Sexo estimado por nombre, 71.7% cobertura — documentado en la vista pero no en BD.

---

#### ID 9 · Juntas Electorales
| Campo | Valor |
|-------|-------|
| **Slug** | `juntas-padronal` |
| **PHP file** | `juntas-padronal.php` |
| **API** | `api/juntas_padronal.php` |
| **Estado** | `active` |
| **Tabla principal** | `summary_jrv` |

**Descripción (BD):** Distribución de juntas receptoras de votos por nivel geográfico con rango filtrable.

**Datos:** Reales — 7,063 JRVs.

**Documentación interna:** ⚠️ `description` presente pero `notes = NULL`.

**Hallazgos:**
- ⚠️ `notes` vacío.
- ⚠️ El slider en la vista tiene `max="7063"` hardcodeado (`juntas-padronal.php` líneas 33-36). Si el padrón se actualiza y cambia el número de JRVs, el slider no se ajusta automáticamente. Debería leerse del API.

---

### Categoría: Participación Electoral

---

#### ID 4 · Participación Electoral
| Campo | Valor |
|-------|-------|
| **Slug** | `participacion-electoral` |
| **PHP file** | `participacion.php` |
| **API** | `api/participacion.php` |
| **Estado** | `active` |
| **Tabla principal** | `election_results`, `election_sync_runs` |

**Descripción (BD):** Porcentaje de participación y abstención por provincia, cantón y JRV. Requiere datos oficiales de votos emitidos del TSE.

**Datos:** Reales — 4 elecciones completadas, 29,909 filas en `election_results`.

**Documentación interna:** ✅ `description` y `notes` completos.

**Hallazgos:**
- ⚠️ **`requires_data` en BD desactualizado**: el campo dice que los datos de votos emitidos son requeridos, pero la tabla `election_results` ya tiene 29,909 registros para 4 elecciones completadas. Actualizar el campo `notes` para reflejar el estado real.

---

### Categoría: Resultados & Estrategia

---

#### ID 6 · Análisis Territorial
| Campo | Valor |
|-------|-------|
| **Slug** | `analisis-territorial` |
| **PHP file** | `analisis-territorial.php` |
| **API** | `api/analisis_territorial.php` |
| **Estado** | `active` |
| **Tabla principal** | `election_results`, `election_sync_runs` |

**Descripción (BD):** Resultados electorales históricos por territorio. Comparativos entre elecciones municipales y nacionales.

**Datos:** Reales — comparativa de participación entre las 4 elecciones disponibles.

**Documentación interna:** ✅ `description` y `notes` completos.

**Hallazgos:**
- ⚠️ **`notes` desactualizado**: dice "parcial posible cuando se carguen resultados históricos" — los resultados YA están cargados (4 elecciones). Actualizar `notes`.

---

#### ID 7 · Indicadores Estratégicos
| Campo | Valor |
|-------|-------|
| **Slug** | `indicadores-estrategicos` |
| **PHP file** | `NULL` |
| **API** | Ninguna |
| **Estado** | `pending` |

**Descripción (BD):** KPIs consolidados y comparativos multi-elección. Depende de los reportes de participación y segmentación.

**Datos:** Sin datos — reporte no implementado.

**Documentación interna:** ✅ `description`, `notes` y `requires_data` completos (correctamente documenta dependencias).

**Hallazgos:** Sin problemas — correctamente marcado como `pending`.

---

### Categoría: Movilización Territorial

---

#### ID 10 · Locales de Votación
| Campo | Valor |
|-------|-------|
| **Slug** | `locales-votacion` |
| **PHP file** | `locales-votacion.php` |
| **API** | `api/locales.php` |
| **Estado** | `active` |
| **Tabla principal** | `polling_places`, `summary_jrv` |

**Descripción (BD):** Centros de votación habilitados por el TSE con total de inscritos y JRVs asignados.

**Datos:** Reales — 2,191 locales de votación.

**Documentación interna:** ⚠️ `description` presente pero `notes = NULL`.

**Hallazgos:**
- ⚠️ `notes` vacío.

---

#### ID 11 · Densidad Electoral por Local
| Campo | Valor |
|-------|-------|
| **Slug** | `densidad-electoral` |
| **PHP file** | `densidad-electoral.php` |
| **API** | `api/locales.php` |
| **Estado** | `active` |
| **Tabla principal** | `polling_places`, `summary_jrv` |

**Descripción (BD):** Ranking de centros de votación por peso electoral — identifica los locales estratégicos para movilización.

**Datos:** Reales — 2,191 locales con JRVs asignados.

**Documentación interna:** ⚠️ `description` presente pero `notes = NULL`.

**Hallazgos:**
- ⚠️ `notes` vacío.
- ⚠️ **Umbrales hardcodeados sin justificación**: los umbrales Alta (≥600), Media (300-599), Baja (<300) están escritos en el HTML y en la lógica de clasificación sin documentar su origen o criterio técnico.

---

#### ID 12 · Circunscripciones Legislativas
| Campo | Valor |
|-------|-------|
| **Slug** | `circunscripciones` |
| **PHP file** | `circunscripciones.php` |
| **API** | `api/circunscripciones.php` |
| **Estado** | `active` |
| **Tabla principal** | `electoral_districts`, `summary_jrv` |

**Descripción (BD):** Las 7 circunscripciones legislativas del país con desglose de inscritos por sexo.

**Datos:** Reales — 7 circunscripciones, inscritos reales, sexo estimado (71.7% cobertura).

**Documentación interna:** ⚠️ `description` presente pero `notes = NULL`.

**Hallazgos:**
- ⚠️ `notes` vacío.

---

## Tabla consolidada de hallazgos

| ID | Reporte | Severidad | Hallazgo |
|----|---------|-----------|----------|
| 2 | Distribución Padrón / JRV | ⚠️ Menor | Descripción dice 7,154 JRVs — son 7,063 |
| 3 | Análisis Estratégico JRV | 🔴 Mayor | Status `active` incorrecto — debería ser `partial` |
| 3 | Análisis Estratégico JRV | ⚠️ Menor | Clasificación por proxy de padrón, no participación real |
| 4 | Participación Electoral | ⚠️ Menor | `requires_data` desactualizado — datos ya disponibles |
| 5 | Segmentación Electoral | 🔴 Mayor | Barras de edad con widths hardcodeados en HTML — no son datos reales |
| 5 | Segmentación Electoral | ⚠️ Menor | `fecha_nac` ausente — edad N/D |
| 5 | Segmentación Electoral | ⚠️ Menor | Sexo estimado con solo 400 entradas en lookup |
| 6 | Análisis Territorial | ⚠️ Menor | `notes` desactualizado — datos ya cargados |
| 8 | Distritos Electorales | ℹ️ Info | `notes` = NULL — sin documentación interna |
| 9 | Juntas Electorales | ℹ️ Info | `notes` = NULL — sin documentación interna |
| 9 | Juntas Electorales | ⚠️ Menor | Slider max hardcodeado (7063) — frágil ante actualizaciones |
| 10 | Locales de Votación | ℹ️ Info | `notes` = NULL — sin documentación interna |
| 11 | Densidad Electoral | ℹ️ Info | `notes` = NULL — sin documentación interna |
| 11 | Densidad Electoral | ⚠️ Menor | Umbrales Alta/Media/Baja hardcodeados sin justificación documentada |
| 12 | Circunscripciones | ℹ️ Info | `notes` = NULL — sin documentación interna |

---

## Acciones recomendadas

### Críticas (antes del próximo demo/entrega)
1. **ID 3**: Cambiar status de `active` a `partial` en tabla `reports`.
2. **ID 5**: Eliminar o reemplazar los widths hardcodeados en las barras de edad de `segmentacion.php` (líneas 58-65). Dejar solo el overlay "N/D" visible o quitar los bars hasta tener `fecha_nac`.

### Menores (próximo sprint)
3. **ID 2**: Corregir descripción en BD: "7,063 juntas" en lugar de "7,154".
4. **IDs 4, 6**: Actualizar `notes` y `requires_data` para reflejar que los datos ya están disponibles.
5. **IDs 8, 9, 10, 11, 12**: Completar campo `notes` con historial, limitaciones conocidas y fuente del dato.
6. **ID 9**: Leer el máximo de JRVs desde el API en lugar de hardcodear `max="7063"` en el slider.
7. **ID 11**: Documentar en `notes` el criterio técnico para los umbrales de densidad (≥600, 300-599, <300).

### Backlog (cuando se integren datos TSE pendientes)
8. Integrar `fecha_nac` del padrón TSE → desbloquea edad promedio en IDs 3, 5, 8, 9.
9. Integrar sexo oficial del padrón TSE → reemplaza estimación por nombre en IDs 5, 8, 12.
10. Integrar votos emitidos por JRV → desbloquea participación real en ID 3, habilita ID 7.
11. Ampliar `name_gender_lookup` (actualmente 400 nombres) para mejorar cobertura de sexo estimado.

---

## Estado de documentación por reporte

| ID | `description` | `notes` | `requires_data` | `js_report_id` |
|----|:---:|:---:|:---:|:---:|
| 1 | ✅ | ✅ | — | ✅ |
| 2 | ✅ ⚠️ | ✅ | — | ✅ |
| 3 | ✅ | ✅ | — | ✅ |
| 4 | ✅ | ✅ ⚠️ | ✅ ⚠️ | ✅ |
| 5 | ✅ | ✅ | ✅ | ✅ |
| 6 | ✅ | ✅ ⚠️ | ✅ ⚠️ | ✅ |
| 7 | ✅ | ✅ | ✅ | — |
| 8 | ✅ | ❌ | — | ✅ |
| 9 | ✅ | ❌ | — | ✅ |
| 10 | ✅ | ❌ | — | ✅ |
| 11 | ✅ | ❌ | — | ✅ |
| 12 | ✅ | ❌ | — | ✅ |

`⚠️` = presente pero desactualizado · `—` = no aplica
