#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * scripts/setup_event.php — Crea el MySQL EVENT que regenera las tablas
 * summary_* automáticamente cada día a las 03:00.
 *
 * Alternativa al cron manual para servidores donde event_scheduler=ON.
 *
 * Uso:
 *   php scripts/setup_event.php           # crea o reemplaza el evento
 *   php scripts/setup_event.php --drop    # elimina el evento
 *   php scripts/setup_event.php --status  # muestra estado del evento
 *
 * Nota: MySQL/MariaDB debe tener event_scheduler activo.
 *   Comprobarlo: SHOW VARIABLES LIKE 'event_scheduler';
 *   Activar en sesión: SET GLOBAL event_scheduler = ON;
 *   Activar permanente: agregar event_scheduler=ON en [mysqld] de my.cnf
 *
 * Alternativa sin event_scheduler (cron del SO):
 *   # Agregar al crontab del servidor web:
 *   0 3 * * * php /ruta/al/proyecto/scripts/refresh_summaries.php --quiet
 */

define('CLI_MODE', true);

$opts   = getopt('', ['drop', 'status']);
$isDrop = isset($opts['drop']);
$isStatus = isset($opts['status']);

function out(string $msg): void { echo '[' . date('H:i:s') . '] ' . $msg . "\n"; }
function err(string $msg): void { fwrite(STDERR, '[ERROR] ' . $msg . "\n"); }

$rootDir = dirname(__DIR__);
require_once $rootDir . '/lib/db.php';

$pdo = dbData();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ─── Status ───────────────────────────────────────────────────────────────────
if ($isStatus) {
    $row = $pdo->query(
        "SELECT event_name, status, last_executed, next_not_after
         FROM information_schema.EVENTS
         WHERE event_schema = DATABASE() AND event_name = 'pel_refresh_summaries'"
    )->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        out("Evento:          pel_refresh_summaries");
        out("Estado:          " . $row['status']);
        out("Última ejecución: " . ($row['last_executed'] ?? 'nunca'));
    } else {
        out("Evento pel_refresh_summaries NO existe en esta base de datos.");
    }

    $scheduler = $pdo->query("SHOW VARIABLES LIKE 'event_scheduler'")->fetch(PDO::FETCH_ASSOC);
    out("event_scheduler: " . ($scheduler['Value'] ?? 'desconocido'));
    exit(0);
}

// ─── Drop ─────────────────────────────────────────────────────────────────────
if ($isDrop) {
    $pdo->exec("DROP EVENT IF EXISTS pel_refresh_summaries");
    out("Evento pel_refresh_summaries eliminado.");
    exit(0);
}

// ─── Verificar event_scheduler ───────────────────────────────────────────────
$scheduler = $pdo->query("SHOW VARIABLES LIKE 'event_scheduler'")->fetch(PDO::FETCH_ASSOC);
$schedulerOn = strtoupper($scheduler['Value'] ?? '') === 'ON';

if (!$schedulerOn) {
    out("ADVERTENCIA: event_scheduler está " . ($scheduler['Value'] ?? 'desconocido') . ".");
    out("  Para activarlo en la sesión actual: SET GLOBAL event_scheduler = ON;");
    out("  Para activarlo permanentemente, agregar en [mysqld] de my.cnf:");
    out("    event_scheduler=ON");
    out("");
    out("El evento se creará igualmente pero no se ejecutará hasta activar el scheduler.");
    out("Alternativa sin event_scheduler:");
    out("  Agregar al crontab: 0 3 * * * php " . realpath($rootDir) . "/scripts/refresh_summaries.php --quiet");
    out("");
}

// ─── Create Event ─────────────────────────────────────────────────────────────
// PDO no soporta DELIMITER, por lo que se ejecuta el CREATE EVENT como
// un único string (sin el delimitador custom de mysql CLI).
out("Creando evento pel_refresh_summaries…");

$pdo->exec("DROP EVENT IF EXISTS pel_refresh_summaries");

$sql = "
CREATE EVENT pel_refresh_summaries
ON SCHEDULE EVERY 1 DAY
STARTS DATE_ADD(DATE(NOW()), INTERVAL 3 HOUR)
COMMENT 'Regenera tablas summary_* de inscritos y JRV. Equivalente a refresh_summaries.php.'
DO
BEGIN

  DECLARE grand_total BIGINT DEFAULT 0;

  SELECT COUNT(*) INTO grand_total
  FROM voters
  WHERE province_id BETWEEN 1 AND 7
    AND district_id IS NOT NULL;

  IF grand_total = 0 THEN
    LEAVE;
  END IF;

  -- Provincias: inscritos totales y por sexo
  REPLACE INTO summary_inscritos_provincia
      (province_id, nombre, inscritos, pct_nacional, inscritos_m, inscritos_f, inscritos_n)
  SELECT v.province_id,
         p.name,
         COUNT(*),
         ROUND(COUNT(*) / grand_total * 100, 3),
         SUM(v.sexo = 'M'),
         SUM(v.sexo = 'F'),
         SUM(v.sexo = 'N')
  FROM voters v
  INNER JOIN provinces p ON p.id = v.province_id
  WHERE v.province_id BETWEEN 1 AND 7
    AND v.district_id IS NOT NULL
  GROUP BY v.province_id, p.name;

  -- Cantones: inscritos totales y por sexo
  REPLACE INTO summary_inscritos_canton
      (canton_id, nombre, province_id, provincia, inscritos, pct_nacional, inscritos_m, inscritos_f, inscritos_n)
  SELECT v.canton_id,
         c.name,
         v.province_id,
         p.name,
         COUNT(*),
         ROUND(COUNT(*) / grand_total * 100, 3),
         SUM(v.sexo = 'M'),
         SUM(v.sexo = 'F'),
         SUM(v.sexo = 'N')
  FROM voters v
  INNER JOIN cantons c ON c.id = v.canton_id
  INNER JOIN provinces p ON p.id = v.province_id
  WHERE v.province_id BETWEEN 1 AND 7
    AND v.district_id IS NOT NULL
  GROUP BY v.canton_id, c.name, v.province_id, p.name;

  -- Distritos: inscritos totales y por sexo
  REPLACE INTO summary_inscritos_distrito
      (district_id, nombre, canton_id, canton, province_id, provincia, geo5, inscritos, pct_nacional, inscritos_m, inscritos_f, inscritos_n)
  SELECT v.district_id,
         d.name,
         v.canton_id,
         c.name,
         v.province_id,
         p.name,
         CONCAT(SUBSTR(d.codelec,1,3), LPAD(CAST(SUBSTR(d.codelec,4) AS UNSIGNED), 2, '0')),
         COUNT(*),
         ROUND(COUNT(*) / grand_total * 100, 3),
         SUM(v.sexo = 'M'),
         SUM(v.sexo = 'F'),
         SUM(v.sexo = 'N')
  FROM voters v
  INNER JOIN districts d ON d.id = v.district_id
  INNER JOIN cantons c ON c.id = v.canton_id
  INNER JOIN provinces p ON p.id = v.province_id
  WHERE v.province_id BETWEEN 1 AND 7
    AND v.district_id IS NOT NULL
    AND d.codelec IS NOT NULL
  GROUP BY v.district_id, d.name, v.canton_id, c.name, v.province_id, p.name, d.codelec;

  -- JRV: inscritos por junta con clasificación alta/media/baja
  REPLACE INTO summary_jrv
      (junta, district_id, canton_id, province_id, distrito, canton, provincia, inscritos, clasificacion)
  SELECT v.junta,
         v.district_id,
         v.canton_id,
         v.province_id,
         d.name,
         c.name,
         p.name,
         COUNT(*),
         CASE
           WHEN COUNT(*) >= 700 THEN 'alta'
           WHEN COUNT(*) >= 400 THEN 'media'
           ELSE 'baja'
         END
  FROM voters v
  INNER JOIN districts d ON d.id = v.district_id
  INNER JOIN cantons c ON c.id = v.canton_id
  INNER JOIN provinces p ON p.id = v.province_id
  WHERE v.province_id BETWEEN 1 AND 7
    AND v.junta IS NOT NULL
    AND v.district_id IS NOT NULL
  GROUP BY v.junta, v.district_id, v.canton_id, v.province_id, d.name, c.name, p.name;

END
";

try {
    $pdo->exec($sql);
    out("Evento pel_refresh_summaries creado. Se ejecutará diariamente a las 03:00.");
    out("Verificar con: php scripts/setup_event.php --status");
} catch (Throwable $e) {
    err("No se pudo crear el evento: " . $e->getMessage());
    out("");
    out("Si el servidor no soporta eventos, usar cron del SO:");
    out("  0 3 * * * php " . realpath($rootDir) . "/scripts/refresh_summaries.php --quiet");
    exit(1);
}
