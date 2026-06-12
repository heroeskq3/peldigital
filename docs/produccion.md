# Guía de despliegue en producción — PEL Digital

## Requisitos del servidor

| Componente | Versión mínima | Notas |
|---|---|---|
| PHP | 8.1+ | Extensions: pdo_mysql, mbstring, json |
| MySQL / MariaDB | 10.6+ / 8.0+ | InnoDB, FULLTEXT habilitado |
| Apache / Nginx | Cualquiera reciente | mod_rewrite si se usa Apache |
| Disco disponible | ≥ 10 GB | Padrón 427 MB + BD ~2-3 GB |
| RAM | ≥ 2 GB | Importación del padrón usa ~512 MB en pico |

## Checklist de preparación en producción

### 1. Código fuente

```bash
# Clonar o copiar el repositorio
git clone <repo-url> /var/www/pel_02
cd /var/www/pel_02

# Verificar que .gitignore excluye correctamente los archivos grandes
# (los archivos raw/ NO están en el repo — descargarlos manualmente, ver sección 3)
```

### 2. Configurar la BD

```bash
mysql -u root -p

CREATE DATABASE pel_electoral CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pel_user'@'localhost' IDENTIFIED BY 'CAMBIAR_PASSWORD_SEGURO';
GRANT ALL PRIVILEGES ON pel_electoral.* TO 'pel_user'@'localhost';
FLUSH PRIVILEGES;
```

**IMPORTANTE**: En producción no editar `lib/db.php`. Copiar `.env.example` a
`.env` y cambiar las credenciales ahí:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=pel_electoral
DB_USER=pel_user
DB_PASS=CAMBIAR_PASSWORD_SEGURO
APP_ENV=production
SESSION_NAME=PEL_SESSION
```

### 3. Ejecutar todas las migraciones

```bash
php scripts/migrate.php
```

Verificar que aplica todas las migraciones en orden:
```
20260601_000001_base_schema.sql
20260601_000002_padron_bronze.sql
20260601_000003_diaspora_index.sql
20260606_000004_reports_catalog.sql
20260609_000005_segmentacion_report.sql
20260609_000006_election_results.sql
20260610_000007_summary_tables.sql
20260610_000008_parties_catalog.sql
20260610_000009_voters_fecha_nac.sql
20260610_000010_name_gender_lookup.sql
20260610_000011_voter_enrichments.sql
20260610_000012_summary_sexo.sql
20260610_000013_padron_tse_menu.sql
20260610_000014_reports_distritos_juntas.sql
20260610_000015_analisis_menu_restructure.sql
```

### 4. Descargar los archivos de datos crudos

Crear la estructura de carpetas:
```bash
mkdir -p raw/padron raw/avr raw/geo
```

**Padrón TSE 2026 (archivo más grande — 427 MB)**
- URL: https://www.tse.go.cr/padron.html
- Descargar el ZIP, extraer:
  - `PADRON_COMPLETO.txt` → copiar a `raw/padron/`
  - `distelec.txt` → copiar a `raw/padron/`
  - `Leame.txt` → copiar a `raw/padron/`

**Resultados electorales AVR (archivos JSON estáticos del TSE)**
- `avr2026.json` (2.5 MB) → `raw/avr/`
  - Presidencia 2026: `https://www.tse.go.cr/APISVR2026/cortes/ultimo?corte=0`
- `avr2024.json` (1.3 MB) → `raw/avr/`
  - Municipal 2024 (requiere acceso a la herramienta del TSE)
- `avr2022.json` (2.9 MB) → `raw/avr/`
  - Presidencial 2022 1ra ronda
- `avr2022_ii.json` (514 KB) → `raw/avr/`
  - Presidencial 2022 2da ronda

> **Nota sobre el WAF del TSE**: Las descargas de AVR pueden requerir hacerlas desde un browser con el Referer correcto del dominio TSE, ya que el WAF Radware bloquea curl directo desde servidores externos. En desarrollo local se hicieron desde el browser.

### 5. Ejecutar el pipeline ETL

```bash
# Paso 1: Catálogo geográfico (prerequisito — ~30 segundos)
php scripts/import_distelec.php --file=raw/padron/distelec.txt

# Paso 2: Padrón completo (~20 minutos en servidor moderno)
php scripts/import_padron.php --file=raw/padron/PADRON_COMPLETO.txt

# Verificar: debe mostrar ~3,731,788 registros
php -r "require_once 'lib/db.php'; echo dbConnect()->query('SELECT COUNT(*) FROM voters')->fetchColumn();"

# Paso 3: Enriquecer sexo (~51 segundos — no requiere red)
php scripts/enrich_sexo.php --batch=0

# Paso 4: Resultados electorales
php scripts/import_resultados.php --json=raw/avr/avr2026.json --type=P --label="Presidencia 2026"
php scripts/import_resultados.php --json=raw/avr/avr2024.json --type=A --label="Municipal 2024"
php scripts/import_resultados.php --json=raw/avr/avr2022.json --type=P --label="Presidencial 2022 1ra"
php scripts/import_resultados.php --json=raw/avr/avr2022_ii.json --type=P --label="Presidencial 2022 2da"

# Paso 5: Centros de votación (requiere descarga previa del TSE)
# Descargar: https://www.tse.go.cr/2026/docus/CENTROS_DE%20VOTACION_%20RATIFICADOS-A-28-01-26.xlsx
# Guardar en: raw/padron/centros_votacion_2026.xlsx
php scripts/import_electoral_districts.php                          # 7 circunscripciones (~1 segundo)
php scripts/import_polling_places.php                               # ~7,000 locales (~10 segundos)
php scripts/link_voters_polling.php                                 # vincula 3.7M voters (~5-10 min)
```

### 6. Configurar actualización automática de tablas de resumen

Las tablas `summary_*` deben regenerarse cada vez que se reimporte el padrón.
Hay dos opciones — usar la que corresponda al entorno:

**Opción A — MySQL/MariaDB EVENT (recomendado si `event_scheduler=ON`):**

```bash
# Crear el evento que corre diariamente a las 03:00
php scripts/setup_event.php

# Verificar estado
php scripts/setup_event.php --status

# Activar event_scheduler si no está activo (requiere acceso root a MySQL)
mysql -u root -e "SET GLOBAL event_scheduler = ON;"
# Para persistir en reinicios, agregar en [mysqld] de my.cnf:
#   event_scheduler=ON
```

**Opción B — Cron del sistema operativo:**

```bash
# Agregar al crontab del usuario del servidor web
crontab -e

# Agregar esta línea (corre a las 3am todos los días):
0 3 * * * php /var/www/pel_02/scripts/refresh_summaries.php --quiet >> /var/log/pel_summaries.log 2>&1
```

### 7. Verificar que las tablas de resumen tienen datos

```sql
SELECT 'provincias' AS tabla, COUNT(*), SUM(inscritos) FROM summary_inscritos_provincia
UNION ALL
SELECT 'cantones',  COUNT(*), SUM(inscritos) FROM summary_inscritos_canton
UNION ALL
SELECT 'distritos', COUNT(*), SUM(inscritos) FROM summary_inscritos_distrito;
```

Si alguna está vacía, regenerar:
```bash
# Regenerar tablas de resumen desde la BD
php scripts/refresh_summaries.php

# Luego forzar regeneración del caché de la API si aplica
curl http://localhost/pel_02/api/poblacion.php?refresh=1
```

### 8. Configurar el servidor web (Apache)

```apache
<VirtualHost *:80>
    ServerName pel.tudominio.com
    DocumentRoot /var/www/pel_02

    <Directory /var/www/pel_02>
        AllowOverride All
        Require all granted
    </Directory>

    # Proteger carpetas sensibles
    <DirectoryMatch "^/var/www/pel_02/(raw|migrations|scripts|lib)">
        Require all denied
    </DirectoryMatch>
</VirtualHost>
```

**CRÍTICO**: Las carpetas `raw/`, `migrations/`, `scripts/`, `lib/` NO deben ser accesibles desde el browser.
El repositorio incluye un `.htaccess` base que bloquea estas rutas cuando Apache
permite `AllowOverride All`; mantener también la regla del VirtualHost en
producción porque es más difícil de omitir accidentalmente.

### 9. Seguridad en producción

- [ ] Configurar credenciales reales en `.env`
- [ ] Crear usuarios reales en tabla `users`
- [x] Deshabilitar fallback `demo` en producción (`APP_ENV=production`)
- [ ] Deshabilitar display_errors en php.ini: `display_errors = Off`
- [ ] Configurar `error_log` a un archivo fuera del webroot
- [ ] Bloquear acceso a carpetas sensibles en el servidor web (ver sección 7)
- [ ] Habilitar HTTPS (Let's Encrypt o certificado corporativo)
- [ ] Revisar permisos de archivos: el webserver debe ser propietario de `data/` (caché)

### 10. Usuarios del sistema

El login principal usa la tabla `users`; `auth.php` conserva un fallback `demo`
solo fuera de producción. Con `APP_ENV=production`, el fallback queda bloqueado.
Para producción crear usuarios reales con hashes bcrypt:

```php
// Generar hash de nueva contraseña:
echo password_hash('nueva_clave_segura', PASSWORD_BCRYPT);
```

Insertar o actualizar esos hashes en la tabla `users`. Verificar que `.env`
tenga `APP_ENV=production` antes de publicar el sistema.

### 11. Monitoreo post-despliegue

Verificar en el browser:
1. Login en `/login.php` con usuario `demo` (o el nuevo usuario de producción)
2. Reporte 1 — Distribución Territorial carga el mapa con datos
3. Reporte 3 — Segmentación muestra 7 provincias con M%/F%
4. Reporte 2 — Participación muestra 69.98% participación 2026
5. Reporte 5 — JRV Inscritos muestra ranking de juntas

## Qué preparar antes de ir a producción

### Obligatorio
- [ ] Servidor con PHP 8.1+ y MySQL/MariaDB
- [ ] Descargar `PADRON_COMPLETO.txt` desde tse.go.cr (427 MB)
- [ ] Descargar `distelec.txt` (viene en el mismo ZIP del padrón)
- [ ] Descargar los 4 archivos AVR JSON (2026, 2024, 2022 1ra, 2022 2da)
- [ ] Configurar `.env`, credenciales de BD y usuarios reales
- [ ] Configurar bloqueo de carpetas sensibles en el servidor

### Recomendado para primera reunión de producción
- [ ] Definir KPIs del Reporte 7 (Indicadores Estratégicos) con el cliente
- [ ] Coordinar con TSE acceso oficial a `fecha_nac` para segmentación por edad
- [ ] Obtener catálogo real de `polling_places` (~7,000 locales con direcciones)
- [x] Fallback `demo` bloqueado por `APP_ENV=production`

### Tiempo estimado de setup en servidor nuevo
- Migraciones: ~2 minutos
- ETL geográfico (distelec): ~30 segundos
- ETL padrón completo: ~20 minutos
- Enriquecimiento de sexo: ~51 segundos
- ETL resultados electorales (4 archivos): ~5 minutos
- **Total**: ~30 minutos desde cero

## Archivos que NO van al servidor (excluidos por .gitignore)

```
raw/padron/*.txt          # Descargar manualmente del TSE
raw/padron/*.zip
raw/avr/*.json            # Descargar manualmente del TSE
raw/geo/*.geojson
data/poblacion_cache.json # Se genera automáticamente
data/bitacora.log         # Se genera automáticamente
```
