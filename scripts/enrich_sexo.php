#!/usr/bin/env php
<?php
/**
 * scripts/enrich_sexo.php
 *
 * Enriquece voters.sexo en tres pasos:
 *
 *   Paso 1 — Restaurar desde voter_enrichments (instantaneo tras re-importar padron).
 *            Copia sexo ya conocido sin reprocessar nada.
 *
 *   Paso 2 — Lookup de nombres para cedulas sin sexo ni enriquecimiento previo.
 *            Usa name_gender_lookup (321 nombres top del padron).
 *            Marca 'N' a los que no tienen match.
 *
 *   Paso 3 — Persistir los nuevos resultados del paso 2 en voter_enrichments
 *            para que sobrevivan un futuro TRUNCATE TABLE voters.
 *
 * Uso:
 *   php scripts/enrich_sexo.php [--dry-run] [--batch=50000]
 *
 * Opciones:
 *   --dry-run   Muestra cuantas filas se actualizarian sin tocar nada.
 *   --batch     Limite de filas en paso 2 (default: 50000, 0 = todas).
 *               El paso 1 siempre procesa todo.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

function out(string $m): void { fwrite(STDOUT, '[' . date('H:i:s') . "] {$m}\n"); }

$opts    = getopt('', ['dry-run', 'batch:']);
$dryRun  = isset($opts['dry-run']);
$batchSz = isset($opts['batch']) ? (int)$opts['batch'] : 50000;

$pdo = dbConnect();

$total   = (int)$pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$pending = (int)$pdo->query("SELECT COUNT(*) FROM voters WHERE sexo IS NULL")->fetchColumn();

out("Padron total: " . number_format($total) . " | Sin sexo: " . number_format($pending));

if ($pending === 0) {
    out('Sin pendientes. Todos los registros ya tienen sexo.');
    exit(0);
}

// -----------------------------------------------------------------------
// DRY-RUN
// -----------------------------------------------------------------------
if ($dryRun) {
    $fromCache = (int)$pdo->query("
        SELECT COUNT(*)
        FROM voters v
        INNER JOIN voter_enrichments e ON e.cedula = v.cedula
        WHERE v.sexo IS NULL AND e.sexo IS NOT NULL
    ")->fetchColumn();
    $newMatch = (int)$pdo->query("
        SELECT COUNT(*)
        FROM voters v
        INNER JOIN name_gender_lookup n ON n.first_name = SUBSTRING_INDEX(v.nombre, ' ', 1)
        LEFT JOIN voter_enrichments e ON e.cedula = v.cedula
        WHERE v.sexo IS NULL AND (e.cedula IS NULL OR e.sexo IS NULL) AND n.sex != 'ND'
    ")->fetchColumn();
    $noMatch = $pending - $fromCache - $newMatch;
    out("Dry-run — resultado esperado:");
    out("  Paso 1 (restaurar voter_enrichments): " . number_format($fromCache));
    out("  Paso 2 (nuevo lookup de nombres):     " . number_format($newMatch));
    out("  Sin match (→ N):                      " . number_format($noMatch));
    exit(0);
}

// -----------------------------------------------------------------------
// PASO 1: Restaurar desde voter_enrichments
// -----------------------------------------------------------------------
out("Paso 1: restaurando desde voter_enrichments...");
$t0 = microtime(true);
$stmt = $pdo->query("
    UPDATE voters v
    INNER JOIN voter_enrichments e ON e.cedula = v.cedula
    SET v.sexo = e.sexo
    WHERE v.sexo IS NULL AND e.sexo IS NOT NULL
");
$restored = $stmt->rowCount();
out(sprintf("  Restauradas: %s en %.1fs", number_format($restored), microtime(true) - $t0));

$pending = (int)$pdo->query("SELECT COUNT(*) FROM voters WHERE sexo IS NULL")->fetchColumn();
out("  Sin sexo tras paso 1: " . number_format($pending));

if ($pending === 0) {
    out('Paso 2 omitido: todos los registros tienen sexo.');
    exit(0);
}

// -----------------------------------------------------------------------
// PASO 2: Lookup de nombres para cedulas nuevas
// -----------------------------------------------------------------------
$limitClause = $batchSz > 0 ? "LIMIT {$batchSz}" : '';
out("Paso 2: lookup de nombres" . ($batchSz > 0 ? " (hasta {$batchSz} filas)" : '') . "...");

$t1 = microtime(true);
$updated = ['M' => 0, 'F' => 0];

foreach (['M', 'F'] as $sex) {
    $stmt = $pdo->prepare("
        UPDATE voters v
        INNER JOIN name_gender_lookup n ON n.first_name = SUBSTRING_INDEX(v.nombre, ' ', 1)
        LEFT JOIN voter_enrichments e ON e.cedula = v.cedula
        SET v.sexo = ?
        WHERE v.sexo IS NULL AND (e.cedula IS NULL OR e.sexo IS NULL) AND n.sex = ?
        {$limitClause}
    ");
    $stmt->execute([$sex, $sex]);
    $updated[$sex] = $stmt->rowCount();
    out("  {$sex}: " . number_format($updated[$sex]));
}

// Marcar sin match como 'N' (solo si ya agotamos los matcheables con el limit)
$totalNew = $updated['M'] + $updated['F'];
$noMatchCount = 0;
if ($batchSz === 0 || $totalNew < $batchSz) {
    $stmt2 = $pdo->prepare("UPDATE voters SET sexo = 'N' WHERE sexo IS NULL" . ($batchSz > 0 ? " LIMIT {$batchSz}" : ''));
    $stmt2->execute();
    $noMatchCount = $stmt2->rowCount();
    out("  Sin match (→ N): " . number_format($noMatchCount));
}

out(sprintf("  Paso 2 completado en %.1fs", microtime(true) - $t1));

// -----------------------------------------------------------------------
// PASO 3: Persistir nuevos en voter_enrichments
// -----------------------------------------------------------------------
if ($totalNew + $noMatchCount > 0) {
    out("Paso 3: persistiendo " . number_format($totalNew + $noMatchCount) . " nuevos en voter_enrichments...");
    $t2 = microtime(true);

    // Solo insertamos cedulas que no existen aun en voter_enrichments
    $stmt3 = $pdo->query("
        INSERT INTO voter_enrichments (cedula, sexo)
        SELECT v.cedula, v.sexo
        FROM voters v
        LEFT JOIN voter_enrichments e ON e.cedula = v.cedula
        WHERE v.sexo IS NOT NULL AND e.cedula IS NULL
        ON DUPLICATE KEY UPDATE sexo = VALUES(sexo)
    ");
    out(sprintf("  Persistidas: %s en %.1fs", number_format($stmt3->rowCount()), microtime(true) - $t2));
}

// -----------------------------------------------------------------------
// Resumen final
// -----------------------------------------------------------------------
$pendingAfter = (int)$pdo->query("SELECT COUNT(*) FROM voters WHERE sexo IS NULL")->fetchColumn();
out(sprintf(
    "Completado — sin sexo restantes: %s",
    number_format($pendingAfter)
));

$dist = $pdo->query("SELECT sexo, COUNT(*) AS n FROM voters GROUP BY sexo ORDER BY n DESC")->fetchAll(PDO::FETCH_ASSOC);
out("Distribucion sexo en voters:");
foreach ($dist as $row) {
    $pct = round($row['n'] / $total * 100, 1);
    out(sprintf("  %-4s : %s (%s%%)", $row['sexo'] ?? 'NULL', number_format($row['n']), $pct));
}
