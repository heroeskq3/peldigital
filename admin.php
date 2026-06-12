<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/db.php';
requerirAdmin();

$rootDir   = __DIR__;
$pageTitle = 'Administración · PEL Digital';
$reportId  = 0;

$pageScripts    = ['assets/js/admin.js'];
$bodyClass      = 'page-admin';

$pdo = dbConnect();

require $rootDir . '/includes/layout/head.php';
require $rootDir . '/includes/layout/header.php';
?>

<main class="admin-main">
    <?php
    require $rootDir . '/includes/admin/usuarios.php';
    require $rootDir . '/includes/admin/roles.php';
    require $rootDir . '/includes/admin/reportes.php';
    require $rootDir . '/includes/admin/bitacora.php';
    require $rootDir . '/includes/admin/configuracion.php';
    require $rootDir . '/includes/admin/cargar-datos.php';
    require $rootDir . '/includes/admin/etl.php';
    require $rootDir . '/includes/admin/explorador.php';
    require $rootDir . '/includes/admin/documentacion.php';
    ?>
</main>

<?php
require $rootDir . '/includes/layout/footer.php';
require $rootDir . '/includes/layout/scripts.php';
