<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/db.php';
requerirLogin();

$rootDir = __DIR__;

// ── Obtener el reporte solicitado ──────────────────────────────────────────────
$pdo = dbConnect();

// URL amigable: /reportes/{slug}
if (isset($_GET['slug'])) {
    $slugStmt = $pdo->prepare("SELECT id FROM reports WHERE slug = ? LIMIT 1");
    $slugStmt->execute([$_GET['slug']]);
    $foundId = $slugStmt->fetchColumn();
    if ($foundId) $_GET['id'] = $foundId;
}

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 1;

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
    header('Location: ' . appUrl('home'));
    exit;
}

$pageTitle = $report['name'] . ' · PEL Digital';
$activeReportSlug = $report['js_report_id'] ?? 'padron-distribucion';
$reportStatus     = $report['status'];
$reportPhpFile    = $report['php_file'];

$reportViewDir = $rootDir . '/includes/reports';
$allowedReportViews = [];
$viewStmt = $pdo->query("
    SELECT DISTINCT php_file
    FROM reports
    WHERE php_file IS NOT NULL AND php_file <> ''
");
foreach ($viewStmt->fetchAll(PDO::FETCH_COLUMN) as $file) {
    if (preg_match('/^[a-z0-9-]+\.php$/', $file)) {
        $allowedReportViews[$file] = $reportViewDir . '/' . $file;
    }
}

require $rootDir . '/includes/layout/head.php';
?>
<script>
    window.ACTIVE_REPORT_ID   = <?= json_encode($activeReportSlug) ?>;
    window.ACTIVE_REPORT_DB   = <?= json_encode($reportId) ?>;
    window.ACTIVE_REPORT_STATUS = <?= json_encode($reportStatus) ?>;
</script>
<?php
require $rootDir . '/includes/layout/header.php';

// El mapa base se carga siempre porque el frontend inicializa Leaflet en #map.
require $rootDir . '/includes/reports/padron-distribucion.php';

// La vista del reporte activo se resuelve desde el catalogo BD y se valida
// contra nombres permitidos para evitar includes arbitrarios.
if ($reportPhpFile && $reportPhpFile !== 'padron-distribucion.php') {
    $activeView = $allowedReportViews[$reportPhpFile] ?? null;
    if ($activeView && is_file($activeView)) {
        require $activeView;
    } else {
        $reportPhpFile = null;
    }
}

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

        <a href="<?= appUrl('home') ?>" class="btn-back-report">
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
