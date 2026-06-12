# Fuentes de Datos Externas — PEL Digital

Todas las fuentes externas son archivos descargables del TSE. No existe consumo
en tiempo real contra servicios del TSE durante la operación normal.

> Los archivos raw no deben estar en git. La estructura `raw/` se conserva con
> `.gitkeep`. Ver `etl.md` para ejecutar la carga y `datawarehouse.md` para
> el esquema de tablas resultante.

---

## Estado actual

| Fuente | Archivo local | Tablas destino | Estado |
|--------|---------------|----------------|--------|
| Padrón TSE 2026 | `raw/padron/PADRON_COMPLETO.txt` | `voters`, `voter_enrichments` | ✅ Cargado — 3,731,788 electores |
| DISTELEC (catálogo geográfico) | `raw/padron/distelec.txt` | `provinces`, `cantons`, `districts` | ✅ Cargado — 2,199 distritos |
| Centros de Votación 2026 | `raw/padron/centros_votacion_2026.xlsx` | `polling_places` | ✅ Cargado — 2,191 locales |
| AVR Presidencia 2026 | `raw/avr/avr2026.json` | `election_results`, `parties` | ✅ Cargado — 7,828 registros |
| AVR Municipales 2024 | `raw/avr/avr2024.json` | `election_results`, `parties` | ✅ Cargado — 7,045 registros |
| AVR Presidencial 2022 1ª | `raw/avr/avr2022.json` | `election_results`, `parties` | ✅ Cargado — 7,518 registros |
| AVR Presidencial 2022 2ª | `raw/avr/avr2022_ii.json` | `election_results`, `parties` | ✅ Cargado — 7,518 registros |

---

## Detalle por fuente

### Padrón Electoral TSE 2026

- **Proveedor:** Tribunal Supremo de Elecciones (tse.go.cr)
- **Obtención:** Solicitud oficial al TSE. Llega como ZIP con `PADRON_COMPLETO.TXT`, `DISTELEC.TXT` y `LEAME.TXT`.
- **Formato:** Una línea por elector, campos separados por `|` — cédula, nombre, apellido1, apellido2, junta, codelec, fecha_caduc.
- **WAF:** El sitio del TSE tiene protección anti-bot. Los archivos del padrón se obtienen por solicitud oficial, no descarga directa.
- **Tablas destino:** `voters` (3,731,788 filas), `voter_enrichments` (cache de enriquecimiento)
- **Script:** `scripts/import_padron.php`
- **Cadencia:** Con cada publicación oficial del TSE (~6 meses antes de cada elección)

### Catálogo Geográfico (DISTELEC)

- **Proveedor:** TSE — incluido en el mismo ZIP del Padrón
- **Formato:** Texto ancho fijo. CODELE = 6 dígitos: dígito 1 = provincia, 2-3 = cantón, 4-6 = distrito.
- **Tablas destino:** `provinces` (7), `cantons` (126), `districts` (2,199)
- **Script:** `scripts/import_distelec.php`
- **Cadencia:** Con cada redistritación del TSE (infrecuente)

### Centros de Votación 2026

- **Proveedor:** TSE — publicado por convocatoria electoral
- **Formato:** Excel (.xlsx). Datos desde fila 8, columnas A–I: código, nombre del local, dirección, codelec (6 dígitos), jrv_inicio, jrv_fin, total_jrv.
- **Tablas destino:** `polling_places` (2,191 locales)
- **Script:** `scripts/import_polling_places.php`
- **Cadencia:** Con cada convocatoria electoral

### Resultados AVR (Actas de Votación por Registro)

- **Proveedor:** TSE — plataforma AVR noche electoral y escrutinio definitivo
- **Formato:** JSON estructurado por circunscripción y nivel (nacional → provincia → cantón → distrito → JRV).
- **WAF:** Descarga manual desde el navegador (F12 → Network) o con `curl` con cabecera `Referer: https://avr.tse.go.cr`.
- **Tablas destino:** `election_results` (29,909 filas — 4 elecciones), `parties` (33 partidos)
- **Script:** `scripts/import_resultados.php`
- **Cadencia:** Una vez por elección (resultados definitivos del TSE)

---

## Trazado fuente → tabla → reportes

| Fuente | Tablas DW | IDs de reportes |
|--------|-----------|-----------------|
| Padrón TSE 2026 | `voters`, `voter_enrichments`, `summary_inscritos_*`, `summary_jrv` | 1, 2, 3, 5, 8, 9, 10, 11, 12 |
| DISTELEC | `provinces`, `cantons`, `districts` | Base de toda la geografía |
| Centros de Votación | `polling_places`, `summary_jrv` | 9, 10, 11 |
| AVR 2026/2024/2022 | `election_results`, `parties` | 4, 6 |

---

## Datos bloqueados / pendientes

| Campo | Tabla | Motivo | Estado |
|-------|-------|--------|--------|
| `fecha_nac` | `voters` | El padrón TSE no incluye fecha de nacimiento. Requiere fuente alternativa o acuerdo con el TSE. | ❌ Bloqueado |
| Sexo oficial | `voters.sexo` | El archivo descargable del padrón TSE no publica sexo. Actualmente se estima por nombre con 400 entradas en `name_gender_lookup` (71.7% cobertura). | ⚠️ Estimado |
| Votos emitidos por JRV | `election_results` | Los JSON AVR no desglosan votos emitidos a nivel de JRV individual, solo distrital. Bloquea participación real en reporte ID 3. | ❌ Bloqueado |
