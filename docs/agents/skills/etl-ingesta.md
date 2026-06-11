# Skill: ETL e Ingesta de datos — PEL Digital

Contexto completo del pipeline de ingesta para nuevas sesiones de Claude/Codex.

## Stack y entorno

- PHP 8.x sin framework · MySQL/MariaDB vía XAMPP
- BD: `pel_electoral` · usuario: `root` · sin password · puerto 3306
- Proyecto en `/Applications/XAMPP/xamppfiles/htdocs/pel_02/`
- URL local: `http://localhost:8099/` (php -S) o `http://localhost/pel_02/` (XAMPP)

## Arquitectura de ingesta

**Regla fundamental**: todas las fuentes de datos son archivos descargables que se depositan en `raw/` antes de procesar. No hay APIs en tiempo real.

```
raw/
  padron/  → PADRON_COMPLETO.txt (427 MB), distelec.txt (172 KB), Leame.txt
  avr/     → avr2026.json (2.5 MB), avr2024.json (1.3 MB),
             avr2022.json (2.9 MB), avr2022_ii.json (514 KB)
  geo/     → reservado para fuentes geográficas crudas
```

## Orden de ejecución en servidor nuevo

```bash
# 1. Migraciones de BD (todas en migrations/, runner en scripts/migrate.php)
php scripts/migrate.php

# 2. Catálogo geográfico DISTELEC (prerequisito para padron y AVR)
php scripts/import_distelec.php --file=raw/padron/distelec.txt

# 3. Padrón electoral TSE 2026 (~20 min en servidor moderno)
php scripts/import_padron.php --file=raw/padron/PADRON_COMPLETO.txt

# 4. Enriquecer sexo desde lookup de nombres (no requiere red, ~51s)
php scripts/enrich_sexo.php --batch=0

# 5. Reconstruir tablas de resumen (si no se construyeron en migrate.php)
#    Las tablas summary_inscritos_* se llenan con datos de voters.
#    Si están vacías tras el migrate: re-ejecutar la migración 000007.

# 6. Resultados electorales (en cualquier orden)
php scripts/import_resultados.php --json=raw/avr/avr2026.json --type=P --label="Presidencia 2026"
php scripts/import_resultados.php --json=raw/avr/avr2024.json --type=A --label="Municipal 2024"
php scripts/import_resultados.php --json=raw/avr/avr2022.json --type=P --label="Presidencial 2022 1ra"
php scripts/import_resultados.php --json=raw/avr/avr2022_ii.json --type=P --label="Presidencial 2022 2da"
```

## Tablas destino y scripts ETL

| Fuente | Script ETL | Tablas destino | Notas |
|---|---|---|---|
| `raw/padron/distelec.txt` | `import_distelec.php` | `provinces`, `cantons`, `districts` | Prerequisito |
| `raw/padron/PADRON_COMPLETO.txt` | `import_padron.php` | `voters` | 3,731,788 filas |
| `name_gender_lookup` (BD) | `enrich_sexo.php` | `voters.sexo`, `voter_enrichments` | M=38.3% F=33.4% N=28.3% |
| `raw/avr/avr2026.json` | `import_resultados.php --type=P` | `election_results` | 7,063 filas nivel JRV |
| `raw/avr/avr2024.json` | `import_resultados.php --type=A` | `election_results` | Municipales |
| `raw/avr/avr2022.json` | `import_resultados.php --type=P` | `election_results` | 2022 1ra ronda |
| `raw/avr/avr2022_ii.json` | `import_resultados.php --type=P` | `election_results` | 2022 2da ronda |

## Diseño clave: voter_enrichments

Los datos enriquecidos (no en el padrón TSE original) van en `voter_enrichments`:

```sql
-- Sin FK intencional: sobrevive TRUNCATE TABLE voters
CREATE TABLE voter_enrichments (
    cedula    VARCHAR(20) NOT NULL,
    sexo      CHAR(1)     NULL,   -- M/F/N
    fecha_nac DATE        NULL,   -- pendiente: acuerdo TSE
    updated_at TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (cedula)
);
```

**Flujo de re-importación del padrón:**
1. `TRUNCATE voters` → `import_padron.php` → voters tiene 3.7M filas con sexo=NULL
2. `enrich_sexo.php` → Paso 1: restaura desde `voter_enrichments` en ~10s sin re-procesar nombres
3. `enrich_sexo.php` → Paso 2: solo corre para cédulas genuinamente nuevas

## API de caché del padrón

`api/poblacion.php` agrega conteos del padrón por provincia/cantón/distrito.
- Cache en `data/poblacion_cache.json` con TTL de 1 hora.
- Para forzar regeneración: `GET api/poblacion.php?refresh=1`
- Las tablas `summary_inscritos_*` son caché pre-agregado para el reporte de Segmentación.

## Tablas principales con registros actuales (10-jun-2026)

| Tabla | Filas | Notas |
|---|---|---|
| `voters` | 3,731,788 | Padrón TSE 2026 completo |
| `provinces` | 8 | 7 provincias + exterior (id=8) |
| `cantons` | ~90+ | Incluye países de diáspora |
| `districts` | ~500+ | Campo `codelec` del TSE (6 dígitos) |
| `election_results` | ~300K+ | Niveles: nacional, provincia, canton, distrito, jrv |
| `parties` | ~25 | Catálogo TSE 2026 |
| `voter_enrichments` | 3,731,788 | sexo poblado; fecha_nac pendiente |
| `name_gender_lookup` | 321 | Top nombres clasificados M/F |

## Datos pendientes de obtener

- **`fecha_nac`**: scraping de `servicioselectorales.tse.go.cr/chc/consulta_cedula.aspx` bloqueado por Radware WAF. Requiere acuerdo oficial con TSE o acceso a padron con fecha_nac incluida.
- **`polling_places`** reales: falta cargar el catálogo oficial de locales (~7,000 locales). No debe asumirse información de prueba como productiva.
- **`electoral_district_id`** en voters: sin datos reales aún.

## Convenciones del código de ingesta

- `lib/db.php` → `dbConnect()`: PDO singleton
- `lib/bitacora.php` → funciones de registro de eventos
- `lib/parsers/PadronTSEParser.php` → parser del TXT plano del TSE
- `lib/parsers/AvrParser.php` → parser del JSON AVR (soporta formato 2022 y 2026)
- Los scripts de importación aceptan `--dry-run` para verificar sin escribir
- Detección de duplicados por SHA-256 del archivo fuente en `import_resultados.php`
