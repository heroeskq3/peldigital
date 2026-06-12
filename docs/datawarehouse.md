# Data Warehouse — PEL Digital

Documentación completa del pipeline de datos: fuentes, ingesta, transformación, almacenamiento y APIs de consumo.

---

## 1. Topología general

```
┌─────────────────────────────────────────────────────────────────────┐
│                          FUENTES EXTERNAS                           │
│                                                                     │
│   TSE — Padrón Electoral     TSE — AVR (resultados)                │
│   tse.go.cr/padron/          tse.go.cr/AVR20XX/api/resultados/     │
│   PADRON_COMPLETO.TXT (ZIP)  avr20XX.json                          │
│                                                                     │
│   TSE — DISTELEC             TSE — Centros de Votación             │
│   DISTELEC.TXT               CENTROS_DE_VOTACION_...xlsx           │
└────────────────┬────────────────────────────────┬───────────────────┘
                 │ descarga manual / curl          │ descarga manual
                 ▼                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        CAPA RAW (Bronze)                            │
│                        raw/padron/                                  │
│                                                                     │
│   PADRON_COMPLETO.txt  ·  distelec.txt                             │
│   centros_votacion_2026.xlsx                                       │
│   raw/avr/  avr2026.json · avr2024.json · avr2022.json             │
│             avr2022_ii.json                                         │
└────────────────┬────────────────────────────────────────────────────┘
                 │ scripts PHP CLI
                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    BASE DE DATOS — peldigital_data                  │
│                      (MySQL/InnoDB — ~2.3 GB)                      │
│                                                                     │
│  ── Dimensiones ──────────────────────────────────────────────────  │
│  provinces · cantons · districts · electoral_districts             │
│  polling_places · parties · name_gender_lookup                     │
│                                                                     │
│  ── Hechos ────────────────────────────────────────────────────── │
│  voters (3.7 M filas · 2.1 GB)                                     │
│  voter_enrichments (3.5 M · 128 MB)                                │
│  election_results (30 K · 10 MB)                                   │
│                                                                     │
│  ── Gold (resúmenes pre-agregados) ───────────────────────────────  │
│  summary_inscritos_provincia · _canton · _distrito                  │
│  summary_jrv                                                        │
│                                                                     │
│  ── Trazabilidad ─────────────────────────────────────────────────  │
│  padron_sync_runs · election_sync_runs · import_jobs               │
│  schema_migrations                                                  │
└────────────────┬────────────────────────────────────────────────────┘
                 │ PHP APIs (lectura)
                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      CAPA DE PRESENTACIÓN                           │
│                   api/*.php  →  assets/js/app/                     │
│              PEL Digital — peldigital.org                          │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 2. Fuentes de datos raw

| Archivo | Origen | URL / ubicación | Formato | Cuándo actualizar |
|---------|--------|-----------------|---------|-------------------|
| `PADRON_COMPLETO.txt` | TSE | Padrón electoral oficial | Texto delimitado por `\|` (pipe) | Con cada publicación del TSE (~6 meses antes de elección) |
| `distelec.txt` | TSE | Catálogo geográfico DISTELEC | CSV ancho fijo (CODELE 6 dígitos) | Con cada redistritación |
| `centros_votacion_2026.xlsx` | TSE | `tse.go.cr/2026/docus/CENTROS_DE%20VOTACION_...xlsx` | Excel (.xlsx), datos desde fila 8, columnas A–I | Con cada convocatoria |
| `avr2026.json` | TSE AVR | `tse.go.cr/AVR2026/api/resultados/` | JSON TSE AVR | Noche electoral / resultados finales |
| `avr2024.json` | TSE AVR | `tse.go.cr/AVR2024/api/resultados/` | JSON TSE AVR | Importado (Municipal 2024) |
| `avr2022.json` | TSE AVR | `tse.go.cr/AVR2022/api/resultados/` | JSON TSE AVR | Importado (Presidencial 2022 1ª) |
| `avr2022_ii.json` | TSE AVR | `tse.go.cr/AVR2022/api/resultados/` | JSON TSE AVR | Importado (Presidencial 2022 2ª) |

> **Nota WAF:** El TSE tiene protección anti-bot. Los JSON de AVR se descargan manualmente desde el navegador (F12 → Network) o con `curl` con cabecera `Referer`. Los archivos del padrón se obtienen por solicitud oficial.

---

## 3. Pipelines de ingesta (scripts CLI)

### 3.1 `import_distelec.php` — Catálogo geográfico

```
Entrada : raw/padron/distelec.txt
Salida  : provinces · cantons · districts
Comando : php scripts/import_distelec.php --file=raw/padron/distelec.txt
```

**Transformación:**
- Parsea CODELE de 6 dígitos: dígito 1 = provincia, 2-3 = cantón, 4-6 = distrito
- `canton_id = provincia * 100 + num_canton` (ej. 104 = San José-Goicoechea)
- Normaliza nombres con `mb_convert_case` (mayúsculas → Título)
- Genera `codelec` (6 dígitos cero-padded) y `geo5` (5 dígitos: prov + dist 2 dígitos)
- Usa `INSERT IGNORE` — idempotente

**Tablas resultantes:**

| Tabla | Filas | Descripción |
|-------|-------|-------------|
| `provinces` | 7 | Provincias + exterior (id 8) |
| `cantons` | 126 | Cantones con province_id |
| `districts` | 2,199 | Distritos con codelec y geo5 |

---

### 3.2 `import_padron.php` — Padrón electoral TSE

```
Entrada : raw/padron/*.zip (contiene PADRON_COMPLETO.TXT)
Salida  : voters · padron_sync_runs
Comando : php scripts/import_padron.php --zip=raw/padron/padron_completo.zip
Duración: ~17 minutos para 3.7 M registros
```

**Transformación:**
1. Verifica SHA-256 del ZIP contra `padron_sync_runs.zip_sha256` — evita reimportar el mismo archivo
2. Descomprime en memoria y parsea con `PadronTSEParser`
3. Formato del TXT: campos separados por `|` — cédula, nombre, apellidos, junta, codelec, fecha_caduc
4. Resuelve `province_id / canton_id / district_id` desde el codelec
5. `TRUNCATE voters` + INSERT en lotes de 1,000 filas (bulk insert)
6. Registra inicio/fin/records_ok/records_error en `padron_sync_runs`

**Campos cargados en `voters`:**

| Campo | Fuente | Notas |
|-------|--------|-------|
| `cedula` | Padrón (col 1) | Índice único |
| `nombre` | Padrón | Texto libre TSE |
| `apellido1` | Padrón | |
| `apellido2` | Padrón | Nullable |
| `junta` | Padrón | Número de JRV (string 5 dígitos) |
| `fecha_caduc` | Padrón | Vencimiento de cédula |
| `province_id` | Resuelto desde codelec | |
| `canton_id` | Resuelto desde codelec | |
| `district_id` | Resuelto desde codelec via JOIN districts | |
| `sexo` | NULL al importar | Completado por enrich_sexo |
| `fecha_nac` | NULL | Bloqueado — WAF del TSE |
| `polling_place_id` | NULL al importar | Completado por link_voters_polling |

**Historial de runs:**

| Run | Archivo | Registros | Duración |
|-----|---------|-----------|----------|
| 1 | PADRON_TEST.TXT | 2 | — |
| 2 | test_5k.txt | 5,000 | — |
| 3 | padron_completo.zip | **3,731,788** | ~17 min |

---

### 3.3 `import_polling_places.php` — Centros de votación

```
Entrada : raw/padron/centros_votacion_2026.xlsx
Salida  : polling_places
Comando : php scripts/import_polling_places.php
          php scripts/import_polling_places.php --truncate   # vacía antes
          php scripts/import_polling_places.php --dry-run    # previsualiza
```

**Transformación:**
- Lee Excel desde fila 8, columnas A–I
- Resuelve `district_id` desde CÓDIGO (codelec 6 dígitos) via JOIN districts
- Carga `jrv_inicio / jrv_fin / total_jrv` — rangos de JRVs por local
- El rango `jrv_inicio..jrv_fin` permite luego mapear cualquier JRV a su local

**Tabla resultante:**

| Tabla | Filas | Descripción |
|-------|-------|-------------|
| `polling_places` | 2,191 | Centros de votación con dirección, rango JRV, provincia/cantón/distrito |

---

### 3.4 `import_resultados.php` — Resultados electorales AVR

```
Entrada : raw/avr/avr20XX.json  (JSON propio del TSE)
Salida  : election_results · election_sync_runs · parties
Comando : php scripts/import_resultados.php --json=raw/avr/avr2026.json \
            --label="Presidencia 2026" --type=P
```

**Transformación:**
- Parsea el JSON del TSE (`AvrParser`) que estructura resultados por circunscripción/nivel
- Niveles: `province`, `canton`, `district`, `jrv`
- Guarda votos por partido como JSON en `election_results.votos_por_partido`
- Registra partidos en `parties` con `INSERT IGNORE`
- SHA-256 del archivo como clave única (evita duplicados)

**Elecciones importadas:**

| Label | Tipo | Records | Fecha |
|-------|------|---------|-------|
| Presidencia 2026-02-02 | P | 7,828 | 2026-06-10 |
| Municipales 2024-02-05 — Alcaldes | A | 7,045 | 2026-06-10 |
| Presidencial 2022 — 1ª vuelta | P | 7,518 | 2026-06-10 |
| Presidencial 2022 — 2ª vuelta | P | 7,518 | 2026-06-10 |

---

### 3.5 `enrich_sexo.php` — Enriquecimiento de sexo por nombre

```
Entrada : voters (cedula, nombre) + name_gender_lookup + voter_enrichments
Salida  : voters.sexo · voter_enrichments
Comando : php scripts/enrich_sexo.php --batch=0   # procesa todo
          php scripts/enrich_sexo.php --dry-run
Duración: ~3-5 min para 3.7 M
```

**Transformación (3 pasos):**

1. **Restaurar desde `voter_enrichments`** — si el padrón fue re-importado (TRUNCATE), recupera el sexo ya conocido sin reprocessar. Copia directa por cédula. Instantáneo.

2. **Lookup por nombre** — para cédulas sin sexo: busca el primer nombre en `name_gender_lookup` (400 entradas). Match exacto → asigna M/F. Sin match → asigna 'N'.

3. **Persistir en `voter_enrichments`** — guarda los nuevos resultados del paso 2 para sobrevivir futuros re-imports.

**Cobertura actual:**

| Sexo | Registros | % |
|------|-----------|---|
| M (Masculino) | 1,428,900 | 38.3% |
| F (Femenino) | 1,246,161 | 33.4% |
| N (Sin clasificar) | 1,056,727 | 28.3% |

> **Pendiente:** ampliar `name_gender_lookup` con nombres compuestos para reducir N.

---

### 3.6 `link_voters_polling.php` — Vincular electores con local de votación

```
Entrada : voters.junta + polling_places.jrv_inicio/jrv_fin
Salida  : voters.polling_place_id
Comando : php scripts/link_voters_polling.php
```

**Transformación:**
- Para cada polling_place, actualiza todos los voters cuyo `junta` cae en el rango `jrv_inicio..jrv_fin`
- UPDATE masivo: `UPDATE voters SET polling_place_id = ? WHERE CAST(junta AS UNSIGNED) BETWEEN ? AND ?`

---

### 3.7 `refresh_summaries.php` — Regenerar tablas Gold

```
Entrada : voters · polling_places · districts · cantons · provinces
Salida  : summary_inscritos_provincia/canton/distrito · summary_jrv
Comando : php scripts/refresh_summaries.php
          php scripts/refresh_summaries.php --quiet
Duración: ~2 minutos
```

**Transformación:**

1. Carga geografía en memoria (provinces, cantons, districts)
2. Un solo `GROUP BY district_id, province_id, canton_id` sobre voters con desglose M/F/N:
   ```sql
   SELECT district_id, province_id, canton_id,
          COUNT(*) AS cnt,
          SUM(sexo='M') AS cnt_m,
          SUM(sexo='F') AS cnt_f,
          SUM(sexo='N') AS cnt_n
   FROM voters GROUP BY ...
   ```
3. Agrega en PHP → `REPLACE INTO` en provincias / cantones / distritos
4. Construye mapa `junta → [polling_place_id, nombre]` iterando rangos `jrv_inicio..jrv_fin`
5. `GROUP BY junta` sobre voters → `REPLACE INTO summary_jrv` con local_nombre y clasificación

**Clasificación de JRV:**
- `alta` ≥ 600 inscritos
- `media` 300–599 inscritos
- `baja` < 300 inscritos

---

## 4. Tablas del Data Warehouse

### Dimensiones

| Tabla | Filas | MB | Descripción |
|-------|-------|----|-------------|
| `provinces` | 7 | 0.02 | 7 provincias + exterior (id=8) |
| `cantons` | 126 | 0.05 | Cantones con province_id |
| `districts` | 2,199 | 0.34 | Distritos con codelec (6 dígitos) y geo5 |
| `electoral_districts` | 7 | 0.03 | Circunscripciones legislativas (1 por provincia) |
| `polling_places` | 2,191 | 0.44 | Centros de votación con dirección y rango JRV |
| `parties` | 33 | 0.03 | Partidos políticos del padrón y AVR |
| `name_gender_lookup` | 400 | 0.02 | Nombres → sexo (M/F) para enriquecimiento |

### Hechos

| Tabla | Filas | MB | Descripción |
|-------|-------|----|-------------|
| `voters` | 3,731,788 | 2,179 | Padrón electoral TSE 2026 completo |
| `voter_enrichments` | 3,466,813 | 128 | Cache de sexo/fecha_nac por cédula (sobrevive TRUNCATE) |
| `election_results` | 30,868 | 9.73 | Resultados AVR por nivel/circunscripción/partido (JSON) |

### Gold (pre-agregadas — alimentan las APIs directamente)

| Tabla | Filas | MB | Descripción |
|-------|-------|----|-------------|
| `summary_inscritos_provincia` | 7 | 0.03 | Inscritos M/F/N y % nacional por provincia |
| `summary_inscritos_canton` | 84 | 0.05 | Inscritos M/F/N por cantón |
| `summary_inscritos_distrito` | 2,130 | 0.39 | Inscritos por distrito con geo5 |
| `summary_jrv` | 7,063 | 2.17 | Inscritos por JRV con local, clasificación y geo |

**Esquema de `summary_jrv` (tabla más consultada):**

```sql
junta           VARCHAR(5)  PK  -- número de JRV (00001-07063)
district_id     SMALLINT    PK
canton_id       SMALLINT
province_id     TINYINT
distrito        VARCHAR(100)    -- nombre desnormalizado
canton          VARCHAR(100)
provincia       VARCHAR(100)
inscritos       INT
clasificacion   ENUM('alta','media','baja')
polling_place_id INT             -- FK → polling_places.id
local_nombre    VARCHAR(200)    -- nombre desnormalizado para evitar JOIN
updated_at      TIMESTAMP
```

### Trazabilidad

| Tabla | Filas | Descripción |
|-------|-------|-------------|
| `padron_sync_runs` | 3 | Historial de importaciones del padrón (ZIP, SHA-256, tiempo, records) |
| `election_sync_runs` | 4 | Historial de importaciones AVR (elección, label, records) |
| `import_jobs` | 4 | Jobs de carga iniciados desde la UI admin |
| `schema_migrations` | 2 | Migraciones aplicadas en peldigital_data |

---

## 5. APIs de consumo (lectura)

Todas requieren sesión activa (`requerirLoginApi()`). Base: `/api/`

| Endpoint | Tabla(s) fuente | Parámetros clave | Descripción |
|----------|----------------|-----------------|-------------|
| `poblacion.php` | `summary_inscritos_provincia/canton/distrito` | nivel, provincia, canton | Datos del mapa principal |
| `jrv.php` | `summary_jrv` | province_id, canton_id, geo5, order, page, format=csv | Inscritos por JRV paginado |
| `locales.php` | `polling_places + summary_jrv` | province_id, canton_id, buscar, order, page | Centros de votación con totales |
| `circunscripciones.php` | `electoral_districts + summary_inscritos_provincia + summary_jrv` | — | 7 circunscripciones con M/F/N, juntas, locales |
| `segmentacion.php` | `voters (GROUP BY sexo)` | province_id, canton_id | Segmentación por sexo con drill-down |
| `participacion.php` | `election_results + summary_inscritos_provincia` | election_id, province_id | Participación vs padrón por elección |
| `resultados.php` | `election_results + parties` | election_id, province_id, nivel | Resultados por partido/nivel |
| `analisis_territorial.php` | `election_results + summary_jrv` | province_id, canton_id | Análisis territorial combinado |
| `distritos_electorales.php` | `districts + summary_inscritos_distrito` | province_id, canton_id | Distritos con inscritos |
| `juntas_padronal.php` | `summary_jrv` | province_id, canton_id, district_id | Juntas por área geográfica |
| `parties.php` | `parties` | — | Catálogo de partidos |

### APIs admin (solo administradores)

| Endpoint | Descripción |
|----------|-------------|
| `admin/explorador.php` | Explorador DW: lista tablas, metadatos, datos paginados con filtros |
| `admin/etl.php` | Estado de los 9 pipelines con historial de runs |
| `admin/datos.php` | Conteos y tamaños de todas las tablas |
| `admin/bitacora.php` | Log de eventos de usuario |
| `admin/usuarios.php` | CRUD de usuarios |
| `admin/roles.php` | CRUD de roles |
| `admin/reportes.php` | CRUD de categorías y reportes |

---

## 6. Orden de ejecución del pipeline completo

En un servidor limpio o después de una actualización del padrón:

```bash
# 0. Migraciones de BD (siempre primero)
php scripts/migrate.php             # pel_electoral (sistema)
php scripts/migrate.php --db=data   # peldigital_data (DW)

# 1. Catálogo geográfico (prerequisito de todo lo demás)
php scripts/import_distelec.php --file=raw/padron/distelec.txt

# 2. Centros de votación
php scripts/import_polling_places.php --file=raw/padron/centros_votacion_2026.xlsx

# 3. Padrón electoral (~17 min)
php scripts/import_padron.php --zip=raw/padron/padron_completo.zip

# 4. Vincular electores con su local de votación
php scripts/link_voters_polling.php

# 5. Enriquecer sexo por nombre (~3-5 min)
php scripts/enrich_sexo.php --batch=0

# 6. Resultados electorales (cualquier orden, independientes)
php scripts/import_resultados.php --json=raw/avr/avr2026.json --label="Presidencia 2026" --type=P
php scripts/import_resultados.php --json=raw/avr/avr2024.json --label="Municipal 2024" --type=A
php scripts/import_resultados.php --json=raw/avr/avr2022.json --label="Presidencial 2022 1ª" --type=P
php scripts/import_resultados.php --json=raw/avr/avr2022_ii.json --label="Presidencial 2022 2ª" --type=P

# 7. Regenerar tablas Gold (~2 min) — siempre al final
php scripts/refresh_summaries.php
```

---

## 7. Migraciones

### pel_electoral (sistema de la app)

Runner: `php scripts/migrate.php`  
Directorio: `migrations/`  
Tabla de control: `pel_electoral.schema_migrations`

| Migración | Descripción |
|-----------|-------------|
| `000001_base_schema` | Esquema base de la aplicación (users, roles, audit) |
| `000002_padron_bronze` | Tablas DW iniciales en peldigital_data |
| `000003_diaspora_index` | Índices para electores en el exterior |
| `000004_reports_catalog` | Catálogo de reportes y categorías |
| `000005_segmentacion_report` | Reporte de segmentación electoral |
| `000006_election_results` | Tabla election_results y sync_runs |
| `000007_summary_tables` | Tablas Gold de resumen |
| `000008_parties_catalog` | Catálogo de partidos |
| `000009_voters_fecha_nac` | Columna fecha_nac en voters |
| `000010_name_gender_lookup` | Tabla de nombres → sexo |
| `000011_voter_enrichments` | Tabla de enriquecimientos persistentes |
| `000012_summary_sexo` | Columnas M/F/N en summary_inscritos_* |
| `000013_padron_tse_menu` | Ajustes de menú y categorías |
| `000014_reports_distritos_juntas` | Reportes Distritos y Juntas |
| `000015_analisis_menu_restructure` | Reestructura categorías del menú |
| `000016_polling_places_jrv` | Columnas jrv_inicio/jrv_fin en polling_places |
| `000017_movilizacion_territorial_reports` | Categoría + reportes 10-12 (IDs fijos) |
| `000018_movilizacion_territorial_fix` | Idem por slug (sin ID fijo, para cualquier servidor) |
| `000019_movilizacion_js_report_id` | Corrige js_report_id NULL en reportes 10-12 |

### peldigital_data (Data Warehouse)

Runner: `php scripts/migrate.php --db=data`  
Directorio: `migrations/data/`  
Tabla de control: `peldigital_data.schema_migrations`

| Migración | Descripción |
|-----------|-------------|
| `000001_drop_crossdb_fk_import_jobs` | Elimina FK cross-database que causaba error en mysqldump |
| `000002_summary_jrv_add_local` | Agrega polling_place_id y local_nombre a summary_jrv |

---

## 8. Variables de entorno requeridas

Definidas en `.env` (ignorado por git — nunca commiteado):

```ini
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=pel_electoral          # BD de la aplicación (usuarios, config, reportes)

DW_HOST=localhost
DW_USER=root
DW_PASS=
DW_NAME=peldigital_data        # BD del Data Warehouse
```

Los nombres de BD se leen en `lib/db.php` mediante `env()`. No están hardcodeados salvo como fallback local.

---

## 9. Consideraciones de rendimiento

| Tabla | Tamaño | Estrategia |
|-------|--------|-----------|
| `voters` | 3.7 M filas / 2.1 GB | Nunca se consulta directamente desde APIs de reportes — solo desde scripts de ingesta y enriquecimiento. Los reportes usan tablas Gold. |
| `summary_jrv` | 7,063 filas / 2.17 MB | Tabla principal de consumo para reportes de JRV, locales y circunscripciones. Se regenera con `refresh_summaries.php` tras cada ingesta. |
| `election_results` | 30,868 filas | `votos_por_partido` es JSON — se deserializa en PHP, no en SQL. |
| Paginación | Todas las APIs | `LIMIT/OFFSET` con índices. Para `voters` directa (explorador admin), el panel de filtros reduce el dataset antes de paginar. |

**Regla principal:** ninguna API de reportes hace `SELECT` sobre `voters` directamente. Todo va a través de las tablas Gold (`summary_*`) que se actualizan con `refresh_summaries.php`.
