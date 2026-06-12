<?php
require __DIR__ . '/auth.php';
requerirLogin();
require_once __DIR__ . '/lib/db.php';

$rootDir    = __DIR__;
$pageTitle  = 'Inicio · PEL Digital';
$bodyClass  = 'page-hub';
$reportId   = 0;
$extraHeadLinks = ['assets/css/app/hub.css'];

$pdo   = dbConnect();
$pdoDW = dbData();

// Categorías y reportes agrupados
$stmt = $pdo->query("
    SELECT c.id AS cat_id, c.name AS cat_name, c.icon AS cat_icon, c.slug AS cat_slug,
           r.id, r.name, r.description, r.icon AS rep_icon, r.status, r.slug AS rep_slug
    FROM report_categories c
    JOIN reports r ON r.category_id = c.id
    ORDER BY c.sort_order, r.sort_order
");
$cats = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cid = $row['cat_id'];
    $cats[$cid] ??= ['name' => $row['cat_name'], 'icon' => $row['cat_icon'], 'reports' => []];
    $cats[$cid]['reports'][] = $row;
}

// Stats hero desde el DW
try {
    $statsElectores = number_format(
        (int) $pdoDW->query("SELECT SUM(inscritos) FROM summary_inscritos_provincia")->fetchColumn(),
        0, '.', ','
    );
    $statsJRV = number_format(
        (int) $pdoDW->query("SELECT COUNT(*) FROM summary_jrv")->fetchColumn(),
        0, '.', ','
    );
    $statsLocales = number_format(
        (int) $pdoDW->query("SELECT COUNT(*) FROM polling_places")->fetchColumn(),
        0, '.', ','
    );
    // Fecha de cierre del padrón: el TSE publica al cierre del mes anterior a la extracción
    $padronFechaRaw = $pdoDW->query("
        SELECT finished_at FROM padron_sync_runs
        WHERE status = 'completed' ORDER BY finished_at DESC LIMIT 1
    ")->fetchColumn();
    if ($padronFechaRaw) {
        $meses = ['enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $dt = new DateTime($padronFechaRaw);
        $dt->modify('first day of this month');
        $dt->modify('-1 day'); // último día del mes anterior = fecha de cierre del padrón
        $padronFecha = $dt->format('j') . ' de ' . $meses[(int)$dt->format('n') - 1] . ' ' . $dt->format('Y');
    } else {
        $padronFecha = null;
    }
} catch (Throwable $e) {
    $statsElectores = $statsJRV = $statsLocales = '—';
    $padronFecha = null;
}

function hubStatusLabel(string $s): string {
    return match($s) {
        'active'  => 'Disponible',
        'partial' => 'En construcción',
        default   => 'Pendiente',
    };
}
function hubStatusClass(string $s): string {
    return match($s) {
        'active'  => 'hub-badge-active',
        'partial' => 'hub-badge-partial',
        default   => 'hub-badge-pending',
    };
}

require $rootDir . '/includes/layout/head.php';
require $rootDir . '/includes/layout/header.php';
?>
<main class="hub-main">

    <!-- Hero / Stats -->
    <section class="hub-hero">
        <div class="hub-hero-brand">
            <i class="bi bi-graph-up-arrow"></i>
            <div>
                <h1 class="hub-hero-title">Fuente de Datos</h1>
                <p class="hub-hero-sub">Centro de análisis electoral</p>
                <?php if ($padronFecha): ?>
                <p class="hub-padron-fecha"><i class="bi bi-calendar-check"></i> Actualizado al <?= htmlspecialchars($padronFecha) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="hub-hero-stats">
            <div class="hub-stat">
                <span class="hub-stat-num"><?= $statsElectores ?></span>
                <span class="hub-stat-label">electores inscritos</span>
            </div>
            <div class="hub-stat">
                <span class="hub-stat-num"><?= $statsJRV ?></span>
                <span class="hub-stat-label">juntas receptoras</span>
            </div>
            <div class="hub-stat">
                <span class="hub-stat-num"><?= $statsLocales ?></span>
                <span class="hub-stat-label">centros de votación</span>
            </div>
        </div>
    </section>

    <!-- Buscador -->
    <div class="hub-search-wrap">
        <i class="bi bi-search hub-search-ico"></i>
        <input id="hubSearch" class="hub-search-input" type="search"
               placeholder="Buscar reporte…" autocomplete="off" spellcheck="false">
        <span id="hubSearchEmpty" class="hub-search-empty d-none">Sin resultados para esta búsqueda.</span>
    </div>

    <!-- Categorías -->
    <?php foreach ($cats as $cat): ?>
    <section class="hub-category">
        <h2 class="hub-cat-title">
            <i class="bi <?= htmlspecialchars($cat['icon']) ?>"></i>
            <?= htmlspecialchars($cat['name']) ?>
        </h2>
        <div class="hub-report-grid">
            <?php foreach ($cat['reports'] as $r):
                $pending = ($r['status'] === 'pending');
                $href    = $pending ? '#' : appUrl('reportes/' . $r['rep_slug']);
            ?>
            <a class="hub-report-card<?= $pending ? ' hub-card-pending' : '' ?>"
               href="<?= $href ?>"
               <?= $pending ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                <div class="hub-card-header">
                    <span class="hub-card-icon"><i class="bi <?= htmlspecialchars($r['rep_icon']) ?>"></i></span>
                    <span class="hub-badge <?= hubStatusClass($r['status']) ?>"><?= hubStatusLabel($r['status']) ?></span>
                </div>
                <h3 class="hub-card-name"><span class="hub-card-num"><?= $r['id'] ?>.</span><?= htmlspecialchars($r['name']) ?></h3>
                <p class="hub-card-desc"><?= htmlspecialchars($r['description'] ?? '') ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

</main>
<?php require $rootDir . '/includes/layout/footer.php'; ?>
<script src="<?= $appBaseUrl ?>assets/js/nav.js?v=<?= filemtime($rootDir . '/assets/js/nav.js') ?>"></script>
<script>
(function () {
    const input  = document.getElementById('hubSearch');
    const empty  = document.getElementById('hubSearchEmpty');
    const cats   = document.querySelectorAll('.hub-category');

    input.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        let anyVisible = false;

        cats.forEach(function (sec) {
            const cards = sec.querySelectorAll('.hub-report-card');
            let secVisible = false;

            cards.forEach(function (card) {
                const text = card.textContent.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
                const match = !q || text.includes(q);
                card.style.display = match ? '' : 'none';
                if (match) secVisible = true;
            });

            sec.style.display = secVisible ? '' : 'none';
            if (secVisible) anyVisible = true;
        });

        empty.classList.toggle('d-none', !q || anyVisible);
    });
})();
</script>
</body>
</html>
