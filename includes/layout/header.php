<header class="app-header">
    <div class="header-left">
        <button id="btnMenu" class="icon-only menu-toggle" aria-label="Abrir menú"
                aria-controls="mainNav" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <a class="brand" href="index.php" title="Inicio">
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
                <li class="nav-item has-dropdown">
                    <button class="nav-link" type="button" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-graph-up"></i> <span>Análisis</span>
                        <i class="bi bi-chevron-down nav-caret"></i>
                    </button>
                    <ul class="dropdown">
                        <li class="dropdown-submenu">
                            <button class="dropdown-link submenu-trigger" type="button" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-person-vcard"></i>
                                <span>Padrón Electoral</span>
                                <i class="bi bi-chevron-right submenu-caret"></i>
                            </button>
                            <ul class="dropdown submenu-list">
                                <li><button class="dropdown-link" type="button" data-analisis="electoral" title="Distribución Territorial del Padrón Electoral"><i class="bi bi-map"></i> Distribución Territorial</button></li>
                                <li><button class="dropdown-link" type="button" data-reporte="jrv-inscritos" title="Inscritos por Junta Receptora de Votos"><i class="bi bi-list-ol"></i> Inscritos por JRV</button></li>
                            </ul>
                        </li>
                    </ul>
                </li>
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
