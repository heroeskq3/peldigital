<script src="<?= $appBaseUrl ?>assets/js/nav.js?v=<?= filemtime($rootDir . '/assets/js/nav.js') ?>"></script>
<?php if (empty($pageScripts)): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<?php
$defaultAppScripts = [
    'assets/js/app/core.js',
    'assets/js/app/map.js',
    'assets/js/app/controls.js',
    'assets/js/app/padron-bitacora.js',
    'assets/js/app/reports.js',
    'assets/js/reports/bastiones.js',
    'assets/js/reports/bastiones-mapa.js',
];
?>
<?php foreach ($defaultAppScripts as $script): ?>
<script src="<?= $appBaseUrl . htmlspecialchars($script) ?>?v=<?= filemtime($rootDir . '/' . $script) ?>"></script>
<?php endforeach; ?>
<?php else: ?>
<?php foreach ($pageScripts as $script): ?>
<script src="<?= $appBaseUrl . htmlspecialchars($script) ?>?v=<?= filemtime($rootDir . '/' . $script) ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
