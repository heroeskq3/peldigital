<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/db.php';
requerirLogin();

$rootDir = __DIR__;

// ── Obtener el reporte solicitado ──────────────────────────────────────────────
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 1;

$pdo = dbConnect();
$stmt = $pdo->prepare("
    SELECT r.*, c.name AS category_name, c.slug AS category_slug
    FROM reports r
    JOIN report_categories c ON c.id = r.category_id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->execute([$reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    // Reporte no existe → redirigir al primero
    header('Location: reports.php?id=1');
    exit;
}

$pageTitle = $report['name'] . ' · PEL Digital';
$activeReportSlug = $report['js_report_id'] ?? 'padron-distribucion';
$reportStatus     = $report['status'];
$reportPhpFile    = $report['php_file'];

require $rootDir . '/includes/layout/head.php';
?>
<script>
    window.ACTIVE_REPORT_ID   = <?= json_encode($activeReportSlug) ?>;
    window.ACTIVE_REPORT_DB   = <?= json_encode($reportId) ?>;
    window.ACTIVE_REPORT_STATUS = <?= json_encode($reportStatus) ?>;
</script>
<?php
require $rootDir . '/includes/layout/header.php';

// ── Partials de reportes activos (siempre incluidos porque app.js necesita
//    el elemento #map para inicializar Leaflet, independientemente del reporte).
require $rootDir . '/includes/reports/padron-distribucion.php';
require $rootDir . '/includes/reports/jrv-inscritos.php';
require $rootDir . '/includes/reports/jrv-analisis.php';
require $rootDir . '/includes/reports/segmentacion.php';
require $rootDir . '/includes/reports/participacion.php';
require $rootDir . '/includes/reports/analisis-territorial.php';

// ── Panel "próximamente" superpuesto para reportes no construidos aún ─────────
// Solo se muestra si no hay php_file; los reportes 'partial' con php_file
// gestionan su propio banner de estado interno.
if (!$reportPhpFile) {
    $requiresData = [];
    if (!empty($report['requires_data'])) {
        $requiresData = json_decode($report['requires_data'], true) ?? [];
    }
    $badgeClass = $reportStatus === 'partial' ? 'badge-warning' : 'badge-muted';
    $badgeText  = $reportStatus === 'partial'  ? 'Parcialmente disponible' : 'Próximamente';
    $badgeIcon  = $reportStatus === 'partial'  ? 'bi-hourglass-split'      : 'bi-lock';
?>
<div class="coming-soon-wrap">
    <div class="coming-soon-card">
        <i class="bi <?= htmlspecialchars($report['icon']) ?> coming-soon-icon"></i>
        <span class="badge <?= $badgeClass ?>">
            <i class="bi <?= $badgeIcon ?>"></i>
            <?= htmlspecialchars($badgeText) ?>
        </span>
        <h1 class="coming-soon-title"><?= htmlspecialchars($report['name']) ?></h1>
        <p class="coming-soon-desc"><?= htmlspecialchars($report['description']) ?></p>

        <?php if ($requiresData): ?>
        <div class="coming-soon-requires">
            <p class="coming-soon-requires-lbl">
                <i class="bi bi-database-exclamation"></i> Datos requeridos del TSE:
            </p>
            <ul>
                <?php foreach ($requiresData as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <a href="reports.php?id=1" class="btn-back-report">
            <i class="bi bi-arrow-left"></i> Volver al inicio
        </a>
    </div>
</div>
<?php
}

require $rootDir . '/includes/layout/footer.php';
require $rootDir . '/includes/modals/padron.php';
require $rootDir . '/includes/modals/bitacora.php';
require $rootDir . '/includes/layout/loader.php';
require $rootDir . '/includes/layout/scripts.php';
