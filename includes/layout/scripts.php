<?php if (empty($pageScripts)): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="assets/js/app.js?v=<?= filemtime($rootDir . '/assets/js/app.js') ?>"></script>
<?php else: ?>
<?php foreach ($pageScripts as $script): ?>
<script src="<?= htmlspecialchars($script) ?>?v=<?= filemtime($rootDir . '/' . $script) ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
