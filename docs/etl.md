# ETL e Ingesta

Todas las fuentes externas se tratan como archivos descargables. No hay consumo
en tiempo real contra servicios del TSE durante la operacion normal.

## Fuentes esperadas

| Archivo local | Fuente | Uso |
|---|---|---|
| `raw/padron/PADRON_COMPLETO.txt` | ZIP de padron TSE 2026 | Electores en `voters`. |
| `raw/padron/distelec.txt` | ZIP de padron TSE 2026 | Catalogo geografico. |
| `raw/padron/Leame.txt` | ZIP de padron TSE 2026 | Formato fuente. |
| `raw/avr/avr2026.json` | AVR TSE 2026 | Resultados presidenciales 2026. |
| `raw/avr/avr2024.json` | AVR TSE 2024 | Resultados municipales 2024. |
| `raw/avr/avr2022.json` | AVR TSE 2022 | Presidencial 2022 primera ronda. |
| `raw/avr/avr2022_ii.json` | AVR TSE 2022 II | Presidencial 2022 segunda ronda. |

Estos archivos no deben estar en git. La estructura `raw/` se conserva con
`.gitkeep`.

## Orden de ejecucion

```bash
php scripts/migrate.php
php scripts/import_distelec.php --file=raw/padron/distelec.txt
php scripts/import_padron.php --file=raw/padron/PADRON_COMPLETO.txt
php scripts/enrich_sexo.php --batch=0
php scripts/import_resultados.php --json=raw/avr/avr2026.json --type=P --label="Presidencia 2026"
php scripts/import_resultados.php --json=raw/avr/avr2024.json --type=A --label="Municipal 2024"
php scripts/import_resultados.php --json=raw/avr/avr2022.json --type=P --label="Presidencial 2022 1ra"
php scripts/import_resultados.php --json=raw/avr/avr2022_ii.json --type=P --label="Presidencial 2022 2da"
php scripts/refresh_summaries.php
```

## Scripts

| Script | Responsabilidad |
|---|---|
| `scripts/migrate.php` | Aplica migraciones SQL pendientes. |
| `scripts/import_distelec.php` | Carga provincias, cantones y distritos desde DISTELEC. |
| `scripts/import_padron.php` | Carga el padron electoral a `voters`. |
| `scripts/import_resultados.php` | Carga resultados AVR a `election_results`. |
| `scripts/enrich_sexo.php` | Enriquece `voters.sexo` usando `name_gender_lookup` y `voter_enrichments`. |
| `scripts/enrich_fecha_nac.php` | Pendiente de continuar desarrollo; depende de una fuente oficial viable para fecha de nacimiento. |
| `scripts/refresh_summaries.php` | Reconstruye tablas `summary_*`. |
| `scripts/dev/test_batch.php` | Script auxiliar de pruebas de desarrollo. No forma parte del pipeline productivo. |

## Datos pendientes

- `fecha_nac`: pendiente de fuente oficial o continuidad del desarrollo del
  enriquecimiento.
- `polling_places`: debe cargarse desde un catalogo real de locales de votacion.
- `electoral_district_id`: requiere catalogo real y regla de asignacion.
