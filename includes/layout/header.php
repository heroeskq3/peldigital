<?php
// Carga el catálogo de reportes desde la BD para construir el menú dinámicamente.
// $pdo ya está disponible (inyectado por reports.php o index.php).
$activeRid = $reportId ?? 0;

try {
    $navPdo = isset($pdo) ? $pdo : dbConnect();
    $navStmt = $navPdo->query("
        SELECT r.id, r.short_name, r.icon, r.status, r.php_file, r.slug,
               c.id AS cat_id, c.name AS cat_name, c.icon AS cat_icon, c.slug AS cat_slug
        FROM reports r
        JOIN report_categories c ON c.id = r.category_id
        ORDER BY c.sort_order, r.sort_order
    ");
    $navReports = $navStmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por categoría
    $navByCategory = [];
    foreach ($navReports as $nr) {
        $navByCategory[$nr['cat_id']] ??= [
            'name' => $nr['cat_name'], 'icon' => $nr['cat_icon'],
            'slug' => $nr['cat_slug'], 'reports' => []
        ];
        $navByCategory[$nr['cat_id']]['reports'][] = $nr;
    }
} catch (Exception $e) {
    $navByCategory = [];
}

// Determina si hay algún reporte activo en una categoría (para marcar el trigger)
function catHasActive(array $cat, int $activeRid): bool {
    foreach ($cat['reports'] as $r) { if ($r['id'] === $activeRid) return true; }
    return false;
}

// Renderiza el <a> de un reporte
function navReportLink(array $nr, int $activeRid): string {
    $isActive  = ($nr['id'] === $activeRid);
    $isPending = ($nr['status'] !== 'active');
    $cls = 'dropdown-link' . ($isActive ? ' report-active' : '') . ($isPending ? ' report-pending' : '');
    $si  = match($nr['status']) {
        'pending' => '<i class="bi bi-lock-fill" title="Próximamente" style="font-size:.7rem;opacity:.45;margin-left:auto"></i>',
        'partial' => '<i class="bi bi-hourglass-split" title="Parcialmente disponible" style="font-size:.7rem;opacity:.55;margin-left:auto;color:#d97706"></i>',
        default   => ''
    };
    $name = htmlspecialchars($nr['short_name']);
    $icon = htmlspecialchars($nr['icon']);
    $href = appUrl('reportes/' . ($nr['slug'] ?? $nr['id']));
    return '<a class="'.$cls.'" href="'.$href.'" data-report-id="'.$nr['id'].'" title="'.$name.'">
        <i class="bi '.$icon.'"></i><span>'.$name.'</span>
        <span class="report-id-badge">'.$nr['id'].'</span>'.$si.'</a>';
}

// ¿Alguna categoría contiene el reporte activo?
$analisisActive = false;
foreach ($navByCategory as $cat) {
    if (catHasActive($cat, $activeRid)) { $analisisActive = true; break; }
}
?>
<header class="app-header">
    <div class="header-left">
        <button id="btnMenu" class="icon-only menu-toggle" aria-label="Abrir menú"
                aria-controls="mainNav" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <a class="brand" href="<?= appUrl('home') ?>" title="Inicio">
            <img src="<?= $appBaseUrl ?>assets/img/logo02.png" class="brand-logo" alt="Esperanza y Libertad">
            <div class="brand-text">
                <span class="brand-title">PEL Digital</span>
            </div>
        </a>

        <nav id="mainNav" class="main-nav" aria-label="Navegación principal">
            <div class="nav-drawer-head">
                <span class="nav-drawer-title">Menú</span>
                <button id="btnMenuClose" class="icon-only" aria-label="Cerrar menú">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <ul class="nav-list">

                <!-- ── Inicio ── -->
                <?php $isHome = (basename($_SERVER['PHP_SELF'] ?? '') === 'home.php'); ?>
                <li class="nav-item">
                    <a class="nav-link<?= $isHome ? ' nav-link-active' : '' ?>" href="<?= appUrl('home') ?>">
                        <i class="bi bi-house"></i> <span>Inicio</span>
                    </a>
                </li>

                <!-- ── Menú padre: Análisis (todas las categorías como subcategorías) ── -->
                <li class="nav-item has-dropdown">
                    <button class="nav-link<?= $analisisActive ? ' nav-link-active' : '' ?>" type="button" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-graph-up"></i> <span>Análisis</span>
                        <i class="bi bi-chevron-down nav-caret"></i>
                    </button>
                    <ul class="dropdown">
                        <?php foreach ($navByCategory as $cat): ?>
                        <li class="dropdown-submenu">
                            <button class="dropdown-link submenu-trigger<?= catHasActive($cat, $activeRid) ? ' submenu-trigger-active' : '' ?>"
                                    type="button" aria-haspopup="true" aria-expanded="false">
                                <i class="bi <?= htmlspecialchars($cat['icon']) ?>"></i>
                                <span><?= htmlspecialchars($cat['name']) ?></span>
                                <i class="bi bi-chevron-right submenu-caret"></i>
                            </button>
                            <ul class="dropdown submenu-list">
                                <?php foreach ($cat['reports'] as $nr): ?>
                                <li><?= navReportLink($nr, $activeRid) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <!-- ── Menú padre: Admin ── -->
                <?php if (function_exists('esAdministrador') && esAdministrador()): ?>
                <?php $isAdmin = (basename($_SERVER['PHP_SELF'] ?? '') === 'admin.php'); ?>
                <li class="nav-item has-dropdown">
                    <button class="nav-link<?= $isAdmin ? ' nav-link-active' : '' ?>" type="button" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-shield-lock"></i> <span>Admin</span>
                        <i class="bi bi-chevron-down nav-caret"></i>
                    </button>
                    <ul class="dropdown">
                        <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#usuarios"     data-admin="usuarios"     ><i class="bi bi-people"></i> Usuarios</a></li>
                        <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#roles"         data-admin="roles"        ><i class="bi bi-shield-check"></i> Roles</a></li>
                        <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#reportes"      data-admin="reportes"     ><i class="bi bi-layout-text-sidebar"></i> Reportes</a></li>
                        <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#bitacora"      data-admin="bitacora"     ><i class="bi bi-journal-text"></i> Bitácora</a></li>
                        <!-- ── Data Warehouse submenu ── -->
                        <li class="dropdown-submenu">
                            <button class="dropdown-link submenu-trigger" type="button" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-database"></i>
                                <span>Data Warehouse</span>
                                <i class="bi bi-chevron-right submenu-caret"></i>
                            </button>
                            <ul class="dropdown submenu-list">
                                <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#explorador"    data-admin="explorador"   ><i class="bi bi-table"></i> Explorador DW</a></li>
                                <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#cargar-datos"  data-admin="cargar-datos" ><i class="bi bi-cloud-upload"></i> Fuentes de datos</a></li>
                                <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#etl"           data-admin="etl"          ><i class="bi bi-arrow-repeat"></i> Pipelines ETL</a></li>
                                <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#configuracion" data-admin="configuracion"><i class="bi bi-sliders"></i> Configuración</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-submenu">
                            <button class="dropdown-link submenu-trigger" type="button" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-journal-code"></i>
                                <span>Documentación técnica</span>
                                <i class="bi bi-chevron-right submenu-caret"></i>
                            </button>
                            <ul class="dropdown submenu-list">
                                <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#documentacion" data-admin="documentacion" data-docs-tab="datawarehouse"  ><i class="bi bi-database"></i> Data Warehouse</a></li>
                                <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#documentacion" data-admin="documentacion" data-docs-tab="fuentes-datos"  ><i class="bi bi-cloud-download"></i> Fuentes de datos</a></li>
                                <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#documentacion" data-admin="documentacion" data-docs-tab="etl"             ><i class="bi bi-arrow-repeat"></i> ETL &amp; Pipelines</a></li>
                                <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#documentacion" data-admin="documentacion" data-docs-tab="reportes"        ><i class="bi bi-bar-chart-line"></i> Análisis de reportes</a></li>
                                <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#documentacion" data-admin="documentacion" data-docs-tab="topologia"       ><i class="bi bi-diagram-3"></i> Topología</a></li>
                                <li><a class="dropdown-link" href="<?= appUrl('admin') ?>#documentacion" data-admin="documentacion" data-docs-tab="changelog"       ><i class="bi bi-clock-history"></i> Changelog</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <div class="nav-tools">
                <span class="nav-tools-lbl">Sesión: <strong><?= htmlspecialchars(usuarioActual() ?? '') ?></strong></span>
                <button id="btnThemeM" class="nav-link" type="button">
                    <i class="bi bi-moon"></i> <span id="themeLabelM">Modo oscuro</span>
                </button>
                <button id="btnResetM" class="nav-link" type="button">
                    <i class="bi bi-arrow-counterclockwise"></i> <span>Reiniciar vista</span>
                </button>
                <a href="<?= appUrl('logout') ?>" class="nav-link nav-link-logout">
                    <i class="bi bi-box-arrow-right"></i> <span>Cerrar sesión</span>
                </a>
            </div>
        </nav>
    </div>
    <button id="btnFilters" class="icon-only btn-filters-mob" aria-label="Filtros" title="Filtros">
        <i class="bi bi-sliders"></i>
    </button>
    <div class="header-actions">
        <button id="btnTheme" class="icon-only" aria-label="Cambiar tema" title="Cambiar tema">
            <i class="bi bi-moon"></i>
        </button>
        <div class="user-menu">
            <button class="icon-only user-menu-btn" id="btnUserMenu"
                    aria-label="Mi perfil" aria-expanded="false" title="Mi perfil">
                <i class="bi bi-person-circle"></i>
            </button>
            <div class="user-menu-panel" id="userMenuPanel">
                <div class="user-menu-header">
                    <div class="user-menu-avatar"><?= htmlspecialchars(mb_substr(usuarioActual() ?? 'U', 0, 1)) ?></div>
                    <div class="user-menu-meta">
                        <span class="user-menu-name"><?= htmlspecialchars(usuarioActual() ?? '') ?></span>
                        <span class="user-menu-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></span>
                    </div>
                </div>
                <hr class="user-menu-sep">
                <a class="user-menu-item" href="<?= appUrl('perfil') ?>">
                    <i class="bi bi-person"></i> Editar perfil
                </a>
                <a class="user-menu-item" href="<?= appUrl('perfil') ?>#contrasena">
                    <i class="bi bi-key"></i> Cambiar contraseña
                </a>
                <hr class="user-menu-sep">
                <a class="user-menu-item user-menu-item-danger" href="<?= appUrl('logout') ?>">
                    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                </a>
            </div>
        </div>
    </div>
</header>

<div id="navBackdrop" class="nav-backdrop d-none"></div>
<div id="filtersBackdrop" class="filters-backdrop d-none"></div>
