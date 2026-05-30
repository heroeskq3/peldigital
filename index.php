<?php
// Mapa de calor de poblacion de Costa Rica - Visual Analytics
// Stack: PHP + Leaflet. Datos dummy via api/poblacion.php
// UI: admin minimalista monocromatico con light/dark mode.
require __DIR__ . '/auth.php';
requerirLogin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Población · Costa Rica</title>

    <!-- Evita el parpadeo de tema: aplica el tema guardado antes de pintar -->
    <script>
        (function () {
            var t = localStorage.getItem("cr-theme");
            if (!t) t = matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            document.documentElement.setAttribute("data-theme", t);
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">

    <header class="app-header">
        <a class="brand" href="index.php" title="Inicio">
            <img src="assets/img/logo02.png" class="brand-logo" alt="Esperanza y Libertad">
            <div class="brand-text">
                <span class="brand-title">PEL Digital</span>
            </div>
        </a>
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

    <div class="app-body">
        <div id="map"></div>

        <aside class="app-side">

            <!-- Buscador + selects -->
            <section class="panel">
                <div class="search-wrap">
                    <i class="bi bi-search search-ico"></i>
                    <input id="buscador" type="text" class="field search-input"
                           placeholder="Buscar región…" autocomplete="off">
                    <ul id="resultados" class="results d-none"></ul>
                </div>
                <div class="selects">
                    <select id="selProvincia" class="field"><option value="">Provincia</option></select>
                    <select id="selCanton" class="field" disabled><option value="">Cantón</option></select>
                    <select id="selDistrito" class="field" disabled><option value="">Distrito</option></select>
                </div>
            </section>

            <!-- Selector de metrica: padron electoral vs residencia real -->
            <section class="panel">
                <span class="label">Métrica</span>
                <div class="seg" id="segMetrica">
                    <button type="button" class="seg-btn active" data-metrica="electoral">Padrón</button>
                    <button type="button" class="seg-btn" data-metrica="real">Residencia</button>
                    <button type="button" class="seg-btn" data-metrica="diferencia">Saldo</button>
                    <button type="button" class="seg-btn" data-metrica="abstencion">Abstención</button>
                    <button type="button" class="seg-btn" data-metrica="participacion">Participación</button>
                    <button type="button" class="seg-btn" data-metrica="extranjero">Extranjero</button>
                </div>
                <p id="metricaAyuda" class="muted small mb-0">
                    Población según el domicilio electoral (dónde está inscrita).
                </p>
            </section>

            <!-- Breadcrumb -->
            <nav class="crumbs"><ol id="breadcrumb"></ol></nav>

            <!-- Nivel actual -->
            <section class="panel">
                <h2 id="nivelTitulo" class="panel-title-lg">Provincias</h2>
                <p id="nivelAyuda" class="muted small mb-0">
                    Selecciona una provincia para ver sus cantones.
                </p>
            </section>

            <!-- Detalle -->
            <section id="detalle" class="panel d-none">
                <span class="label">Seleccionado</span>
                <h3 id="detalleNombre" class="panel-title-lg"></h3>
                <div class="metric">
                    <span id="detallePob" class="metric-num"></span>
                    <span id="detalleUnidad" class="metric-unit">habitantes</span>
                </div>
                <div id="detalleExtra" class="muted small"></div>
                <div id="detalleFlujo" class="flujo d-none"></div>
                <button id="btnPadron" class="btn-wide" type="button">
                    <i class="bi bi-table"></i> Mostrar resultados
                </button>
            </section>

            <!-- Resumen -->
            <section class="panel">
                <span class="label">Resumen del nivel</span>
                <div class="stats">
                    <div class="stat"><div id="statTotal" class="stat-num">–</div><div class="stat-lbl">Total</div></div>
                    <div class="stat"><div id="statRegiones" class="stat-num">–</div><div class="stat-lbl">Regiones</div></div>
                    <div class="stat"><div id="statProm" class="stat-num">–</div><div class="stat-lbl">Promedio</div></div>
                </div>
            </section>

            <!-- Top 5 -->
            <section class="panel">
                <span class="label">Top 10 por población</span>
                <ol id="topList" class="ranking"></ol>
            </section>

            <!-- Leyenda -->
            <section class="panel">
                <span class="label">Escala</span>
                <div id="legend" class="legend"></div>
            </section>

        </aside>
    </div>

    <footer class="app-footer">
        Fronteras: schweini/CR_distritos_geojson · Población simulada (no oficial)
    </footer>
</div>

<!-- Modal: padron completo (DataTable) de la region seleccionada -->
<div id="padronModal" class="modal-overlay d-none">
    <div class="modal-card">
        <header class="modal-head">
            <div>
                <h3 id="padronTitulo" class="modal-title"></h3>
                <span id="padronSub" class="muted small"></span>
            </div>
            <button id="padronClose" class="icon-only" aria-label="Cerrar" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </header>
        <div class="modal-tools">
            <div class="search-wrap modal-search">
                <i class="bi bi-search search-ico"></i>
                <input id="padronBuscar" type="text" class="field search-input"
                       placeholder="Filtrar por cédula, nombre o apellidos…" autocomplete="off">
            </div>
            <select id="padronPageSize" class="field page-size">
                <option value="25">25 / pág.</option>
                <option value="50">50 / pág.</option>
                <option value="100">100 / pág.</option>
            </select>
            <button id="btnExport" class="btn-export" type="button" title="Exportar a Excel">
                <i class="bi bi-file-earmark-spreadsheet"></i> Excel
            </button>
        </div>
        <div class="table-wrap">
            <table class="dt">
                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>Edad</th>
                        <th>Nacimiento</th>
                        <th>Hijos</th>
                        <th>Estado civil</th>
                        <th>Provincia</th>
                        <th>Cantón</th>
                        <th>Distrito</th>
                        <th>Centro de votación</th>
                    </tr>
                </thead>
                <tbody id="padronBody"></tbody>
            </table>
        </div>
        <footer class="modal-foot">
            <span id="padronInfo" class="muted small"></span>
            <div class="pager">
                <button id="pgFirst" class="pg-btn" title="Primera">«</button>
                <button id="pgPrev" class="pg-btn" title="Anterior">‹</button>
                <span id="pgNow" class="muted small"></span>
                <button id="pgNext" class="pg-btn" title="Siguiente">›</button>
                <button id="pgLast" class="pg-btn" title="Última">»</button>
            </div>
        </footer>
    </div>
</div>

<div id="loader" class="loader-overlay">
    <div class="spinner"></div>
    <div class="loader-txt">Cargando…</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
