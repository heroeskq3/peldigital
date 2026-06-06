<?php
require __DIR__ . '/auth.php';
requerirLogin();
$rootDir = __DIR__;
$pageTitle = 'Distribución Territorial del Padrón Electoral · PEL Digital';

require $rootDir . '/includes/layout/head.php';
require $rootDir . '/includes/layout/header.php';
require $rootDir . '/includes/reports/padron-distribucion.php';
require $rootDir . '/includes/reports/jrv-inscritos.php';
require $rootDir . '/includes/reports/jrv-analisis.php';
require $rootDir . '/includes/layout/footer.php';
require $rootDir . '/includes/modals/padron.php';
require $rootDir . '/includes/modals/bitacora.php';
require $rootDir . '/includes/layout/loader.php';
require $rootDir . '/includes/layout/scripts.php';
