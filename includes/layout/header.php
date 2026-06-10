<?php
// Carga el catálogo de reportes desde la BD para construir el menú dinámicamente.
// $pdo ya está disponible (inyectado por reports.php o index.php).
$activeRid = $reportId ?? 0;

try {
    $navPdo = isset($pdo) ? $pdo : dbConnect();
    $navStmt = $navPdo->query("
        SELECT r.id, r.short_name, r.icon, r.status, r.php_file,
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
    return '<a class="'.$cls.'" href="reports.php?id='.$nr['id'].'" data-report-id="'.$nr['id'].'" title="'.$name.'">
        <i class="bi '.$icon.'"></i><span>'.$name.'</span>
        <span class="report-id-badge">#'.$nr['id'].'</span>'.$si.'</a>';
}

// Separar categoría Padrón TSE del resto
$catPadron   = [];
$catAnalisis = [];
foreach ($navByCategory as $cid => $cat) {
    if ($cat['slug'] === 'padron-tse') $catPadron[$cid]   = $cat;
    else                               $catAnalisis[$cid] = $cat;
}
$padronActive  = false;
foreach ($catPadron  as $cat) { if (catHasActive($cat, $activeRid)) { $padronActive  = true; break; } }
$analisisActive = false;
foreach ($catAnalisis as $cat) { if (catHasActive($cat, $activeRid)) { $analisisActive = true; break; } }
?>
<header class="app-header">
    <div class="header-left">
        <button id="btnMenu" class="icon-only menu-toggle" aria-label="Abrir menú"
                aria-controls="mainNav" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <a class="brand" href="reports.php?id=1" title="Inicio">
            <img src="assets/img/logo02.png" class="brand-logo" alt="Esperanza y Libertad">
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

                <!-- ── Menú padre: Padrón (reportes estilo TSE) ── -->
                <?php if (!empty($catPadron)): ?>
                <li class="nav-item has-dropdown">
                    <button class="nav-link<?= $padronActive ? ' nav-link-active' : '' ?>" type="button" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-person-vcard-fill"></i> <span>Padrón</span>
                        <i class="bi bi-chevron-down nav-caret"></i>
                    </button>
                    <ul class="dropdown">
                        <?php foreach ($catPadron as $cat):
                            foreach ($cat['reports'] as $nr): ?>
                        <li><?= navReportLink($nr, $activeRid) ?></li>
                        <?php endforeach; endforeach; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- ── Menú padre: Análisis (resto de reportes agrupados por subcategoría) ── -->
                <li class="nav-item has-dropdown">
                    <button class="nav-link<?= $analisisActive ? ' nav-link-active' : '' ?>" type="button" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-graph-up"></i> <span>Análisis</span>
                        <i class="bi bi-chevron-down nav-caret"></i>
                    </button>
                    <ul class="dropdown">
                        <?php foreach ($catAnalisis as $cat): ?>
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
                <li class="nav-item has-dropdown">
                    <button class="nav-link" type="button" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-shield-lock"></i> <span>Admin</span>
                        <i class="bi bi-chevron-down nav-caret"></i>
                    </button>
                    <ul class="dropdown">
                        <li><button class="dropdown-link" type="button" data-admin="bitacora"><i class="bi bi-journal-text"></i> Bitácora</button></li>
                        <li><button class="dropdown-link" type="button" data-admin="configuracion"><i class="bi bi-sliders"></i> Configuración</button></li>
                        <li><button class="dropdown-link" type="button" data-admin="usuarios"><i class="bi bi-people"></i> Usuarios</button></li>
                        <li><button class="dropdown-link" type="button" data-admin="roles"><i class="bi bi-person-badge"></i> Roles de usuario</button></li>
                        <li><button class="dropdown-link" type="button" data-admin="cargar"><i class="bi bi-cloud-upload"></i> Cargar Datos</button></li>
                        <li><button class="dropdown-link" type="button" data-admin="pipelines"><i class="bi bi-diagram-3"></i> Pipelines</button></li>
                    </ul>
                </li>
            </ul>

            <div class="nav-tools">
                <span class="nav-tools-lbl">Sesión: <strong><?= htmlspecialchars(usuarioActual() ?? '') ?></strong></span>
                <button id="btnThemeM" class="nav-link" type="button">
                    <i class="bi bi-moon"></i> <span id="themeLabelM">Modo oscuro</span>
                </button>
                <button id="btnResetM" class="nav-link" type="button">
                    <i class="bi bi-arrow-counterclockwise"></i> <span>Reiniciar vista</span>
                </button>
                <a href="logout.php" class="nav-link nav-link-logout">
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
        <button id="btnReset" class="icon-only" aria-label="Reiniciar vista" title="Reiniciar vista">
            <i class="bi bi-arrow-counterclockwise"></i>
        </button>
        <span class="header-user" title="Sesión activa"><?= htmlspecialchars(usuarioActual() ?? '') ?></span>
        <a href="logout.php" class="icon-only" aria-label="Cerrar sesión" title="Cerrar sesión">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</header>

<div id="navBackdrop" class="nav-backdrop d-none"></div>
<div id="filtersBackdrop" class="filters-backdrop d-none"></div>
