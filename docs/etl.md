# ETL & Pipelines — PEL Digital

Pipeline de ingesta del Data Warehouse. Ver `fuentes-datos.md` para el origen
de cada archivo raw y `datawarehouse.md` para el esquema de tablas resultante.

---

## Orden de ejecución

En un servidor limpio o tras actualización del padrón:

```bash
# 0. Migraciones (siempre primero)
php scripts/migrate.php             # pel_electoral (app)
php scripts/migrate.php --db=data   # peldigital_data (DW)

# 1. Catálogo geográfico — prerequisito de todo lo demás
php scripts/import_distelec.php --file=raw/padron/distelec.txt

# 2. Centros de votación
php scripts/import_polling_places.php

# 3. Padrón electoral (~17 min)
php scripts/import_padron.php --zip=raw/padron/padron_completo.zip

# 4. Vincular electores con su local de votación
php scripts/link_voters_polling.php

# 5. Enriquecer sexo por nombre (~3-5 min)
php scripts/enrich_sexo.php --batch=0

# 6. Resultados electorales (independientes entre sí)
php scripts/import_resultados.php --json=raw/avr/avr2026.json --label="Presidencia 2026" --type=P
php scripts/import_resultados.php --json=raw/avr/avr2024.json --label="Municipal 2024" --type=A
php scripts/import_resultados.php --json=raw/avr/avr2022.json --label="Presidencial 2022 1ª" --type=P
php scripts/import_resultados.php --json=raw/avr/avr2022_ii.json --label="Presidencial 2022 2ª" --type=P

# 7. Tablas Gold (~2 min) — siempre al final
php scripts/refresh_summaries.php
```

---

## Referencia de scripts

| Script | Entrada | Tablas modificadas | Notas |
|--------|---------|--------------------|-------|
| `scripts/migrate.php` | `migrations/` | Esquema app/DW | `--db=data` para el DW |
| `scripts/import_distelec.php` | `distelec.txt` | `provinces`, `cantons`, `districts` | Idempotente (`INSERT IGNORE`) |
| `scripts/import_polling_places.php` | `centros_votacion_2026.xlsx` | `polling_places` | `--truncate` vacía antes; `--dry-run` previsualiza |
| `scripts/import_padron.php` | `padron_completo.zip` | `voters`, `padron_sync_runs` | Verifica SHA-256 — no reimporta el mismo archivo |
| `scripts/link_voters_polling.php` | `voters`, `polling_places` | `voters.polling_place_id` | UPDATE masivo por rango jrv_inicio..jrv_fin |
| `scripts/enrich_sexo.php` | `voters`, `name_gender_lookup` | `voters.sexo`, `voter_enrichments` | `--batch=0` procesa todo; `--dry-run` disponible |
| `scripts/import_resultados.php` | `avr*.json` | `election_results`, `parties` | `--label` y `--type` requeridos |
| `scripts/refresh_summaries.php` | `voters`, `polling_places` | `summary_inscritos_*`, `summary_jrv` | Siempre al final del pipeline; ~2 min |
| `scripts/enrich_fecha_nac.php` | — | — | En desarrollo — fuente oficial bloqueada por WAF |
| `scripts/dev/test_batch.php` | — | — | Auxiliar de desarrollo — no es parte del pipeline productivo |
