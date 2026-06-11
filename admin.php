<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/db.php';
requerirLogin();

$rootDir   = __DIR__;
$pageTitle = 'Administración · PEL Digital';
$reportId  = 0;

// CSS específico de admin — inyectado en <head> via $extraHeadLinks
$extraHeadLinks = ['assets/css/admin.css'];

// JS específico de admin — reemplaza leaflet+chart+app.js en scripts.php
$pageScripts = ['assets/js/admin.js'];

$pdo = dbConnect();

require $rootDir . '/includes/layout/head.php';
require $rootDir . '/includes/layout/header.php';
?>

<!-- Mobile section tabs -->
<div class="admin-mobile-tabs">
    <button class="admin-mob-tab" data-section="usuarios">    <i class="bi bi-people"></i> Usuarios</button>
    <button class="admin-mob-tab" data-section="roles">       <i class="bi bi-shield-check"></i> Roles</button>
    <button class="admin-mob-tab" data-section="reportes">    <i class="bi bi-layout-text-sidebar"></i> Reportes</button>
    <button class="admin-mob-tab" data-section="bitacora">    <i class="bi bi-journal-text"></i> Bitácora</button>
    <button class="admin-mob-tab" data-section="configuracion"><i class="bi bi-sliders"></i> Config</button>
    <button class="admin-mob-tab" data-section="cargar-datos"><i class="bi bi-cloud-upload"></i> Datos</button>
    <button class="admin-mob-tab" data-section="pipelines">   <i class="bi bi-diagram-3"></i> Pipelines</button>
</div>

<div class="admin-shell">

    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="admin-sidebar-head">Administración</div>
        <nav>
            <ul class="admin-sidebar-nav">
                <li>
                    <button class="admin-sidebar-link" data-section="usuarios">
                        <i class="bi bi-people"></i> Usuarios
                    </button>
                </li>
                <li>
                    <button class="admin-sidebar-link" data-section="roles">
                        <i class="bi bi-shield-check"></i> Roles
                    </button>
                </li>
                <li>
                    <button class="admin-sidebar-link" data-section="reportes">
                        <i class="bi bi-layout-text-sidebar"></i> Reportes
                    </button>
                </li>
                <li>
                    <button class="admin-sidebar-link" data-section="bitacora">
                        <i class="bi bi-journal-text"></i> Bitácora
                    </button>
                </li>
                <li>
                    <button class="admin-sidebar-link" data-section="configuracion">
                        <i class="bi bi-sliders"></i> Configuración
                    </button>
                </li>
                <li>
                    <button class="admin-sidebar-link" data-section="cargar-datos">
                        <i class="bi bi-cloud-upload"></i> Cargar Datos
                    </button>
                </li>
                <li>
                    <button class="admin-sidebar-link" data-section="pipelines">
                        <i class="bi bi-diagram-3"></i> Pipelines
                    </button>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main content -->
    <main class="admin-main">
        <?php
        require $rootDir . '/includes/admin/usuarios.php';
        require $rootDir . '/includes/admin/roles.php';
        require $rootDir . '/includes/admin/reportes.php';
        require $rootDir . '/includes/admin/bitacora.php';
        require $rootDir . '/includes/admin/configuracion.php';
        require $rootDir . '/includes/admin/cargar-datos.php';
        require $rootDir . '/includes/admin/pipelines.php';
        ?>
    </main>

</div>

<?php
require $rootDir . '/includes/layout/footer.php';
require $rootDir . '/includes/layout/scripts.php';
