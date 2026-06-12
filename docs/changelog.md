# Historial de Versiones — PEL Digital

Registro de entregas y funcionalidades disponibles por versión.

---

## v1.0 · Entrega inicial — 8 de junio 2026

### Acceso y seguridad

- Portal web con inicio de sesión seguro por usuario y contraseña.
- Control de acceso por roles: los reportes requieren sesión activa; el panel de administración es exclusivo para administradores.
- Cada usuario tiene perfil propio con nombre, correo y opción de cambiar contraseña.

---

### Padrón Electoral TSE 2026

- Consulta del padrón oficial completo con **3,731,788 electores** inscritos para las elecciones presidenciales de febrero 2026.
- Incluye electores en el exterior (padrón diáspora).
- Los datos provienen directamente del archivo oficial del Tribunal Supremo de Elecciones.

---

### Reportes disponibles

#### Padrón y Territorio

- **Distribución Territorial** — visualización del padrón por provincia, cantón y distrito con drill-down interactivo y comparativa de métricas por zona.
- **Distribución por Junta Receptora de Votos (JRV)** — tabla completa de las 7,063 JRVs con número de inscritos, ranking y filtros por territorio.
- **Análisis Estratégico de JRV** — clasificación de juntas por volumen electoral (alta, media, baja) con histograma y detección de juntas atípicas.
- **Distritos Electorales** — desglose del electorado por cada uno de los 2,199 distritos administrativos del país.
- **Juntas Electorales** — distribución de juntas por nivel geográfico con selector de rango interactivo.
- **Segmentación Electoral** *(parcial)* — distribución estimada del padrón por sexo. Sexo oficial pendiente de integración con TSE.

#### Participación Electoral

- **Participación Electoral** — porcentaje de participación y abstención en las 4 elecciones históricas cargadas, con filtros por provincia.

#### Resultados y Estrategia

- **Análisis Territorial** — comparativa de resultados electorales entre elecciones municipales y nacionales por zona geográfica.

#### Movilización Territorial

- **Locales de Votación** — directorio completo de los 2,191 centros de votación habilitados por el TSE con total de inscritos y juntas asignadas.
- **Densidad Electoral por Local** — ranking de centros de votación por peso electoral para identificar locales estratégicos de movilización.
- **Circunscripciones Legislativas** — las 7 circunscripciones del país con desglose de inscritos.

---

### Resultados electorales históricos

Cuatro elecciones cargadas con resultados oficiales del TSE:

| Elección | Fecha |
|----------|-------|
| Presidencial 2026 — primera vuelta | 2 febrero 2026 |
| Municipal 2024 — Alcaldes | 5 febrero 2024 |
| Presidencial 2022 — primera vuelta | 7 febrero 2022 |
| Presidencial 2022 — segunda vuelta | 4 abril 2022 |

---

### Herramientas de análisis

- Filtros por provincia, cantón y distrito en todos los reportes.
- Exportación de datos a formato CSV desde reportes de JRVs y locales.
- Visualizaciones interactivas: mapas, gráficos de barras, tablas paginadas.
- Modo oscuro disponible en toda la interfaz.

---

### Panel de administración

- Gestión de usuarios: creación, edición y desactivación de cuentas.
- Gestión de roles y permisos de acceso.
- Catálogo de reportes: activación, edición de descripciones y ordenamiento en el menú.
- Bitácora de actividad del sistema.
- Explorador del Data Warehouse: navegación de tablas con filtros y exportación.
- Gestión de pipelines ETL: carga de datos desde fuentes TSE y estado de cada proceso.
- Sección de documentación técnica (este portal).

---

## Próximas versiones

| Funcionalidad | Estado |
|---------------|--------|
| Indicadores estratégicos consolidados multi-elección | En diseño |
| Integración de sexo oficial del padrón TSE | Bloqueado — pendiente de acuerdo TSE |
| Integración de fecha de nacimiento | Bloqueado — no disponible en archivo del padrón |
| Participación real por JRV | Bloqueado — AVR no desglosa votos emitidos por JRV |
