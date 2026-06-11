<div class="app-body">
    <div id="map"></div>

    <aside class="app-side">
        <div class="side-handle" id="sideHandle"><div class="side-handle-bar"></div></div>

        <section class="panel">
            <div class="side-top-row">
                <span class="label mb-0">Filtros</span>
                <button id="btnReset" class="btn-reset-side" aria-label="Reiniciar vista" title="Reiniciar vista">
                    <i class="bi bi-arrow-counterclockwise"></i> Reiniciar
                </button>
            </div>
            <div class="search-wrap">
                <i class="bi bi-search search-ico"></i>
                <input id="buscador" type="text" class="field search-input"
                       placeholder="Buscar región..." autocomplete="off">
                <ul id="resultados" class="results d-none"></ul>
            </div>
            <div class="selects">
                <select id="selProvincia" class="field"><option value="">Provincia</option></select>
                <select id="selCanton" class="field" disabled><option value="">Cantón</option></select>
                <select id="selDistrito" class="field" disabled><option value="">Distrito</option></select>
            </div>
        </section>

        <section class="panel">
            <span class="label">Métrica</span>
            <div class="seg" id="segMetrica">
                <button type="button" class="seg-btn active" data-metrica="electoral">Nacional</button>
                <button type="button" class="seg-btn" data-metrica="extranjero">Extranjero</button>
            </div>
            <p id="metricaAyuda" class="muted small mb-0">
                Población según el domicilio electoral (dónde está inscrita).
            </p>
        </section>

        <nav class="crumbs"><ol id="breadcrumb"></ol></nav>

        <section class="panel">
            <h2 id="nivelTitulo" class="panel-title-lg">Provincias</h2>
            <p id="nivelAyuda" class="muted small mb-0">
                Selecciona una provincia para ver sus cantones.
            </p>
        </section>

        <section id="detalle" class="panel d-none">
            <span class="label">Seleccionado</span>
            <h3 id="detalleNombre" class="panel-title-lg"></h3>
            <div class="metric">
                <span id="detallePob" class="metric-num"></span>
                <span id="detalleUnidad" class="metric-unit">habitantes</span>
            </div>
            <div id="detallePorc" class="detalle-pct d-none"></div>
            <div id="detalleExtra" class="muted small"></div>
            <button id="btnPadron" class="btn-wide" type="button">
                <i class="bi bi-table"></i> Mostrar resultados
            </button>
        </section>

        <section class="panel">
            <span class="label">Resumen del nivel</span>
            <div class="stats">
                <div class="stat"><div id="statTotal" class="stat-num">-</div><div class="stat-lbl">Total</div></div>
                <div class="stat"><div id="statRegiones" class="stat-num">-</div><div class="stat-lbl">Regiones</div></div>
                <div class="stat"><div id="statProm" class="stat-num">-</div><div class="stat-lbl">Promedio</div></div>
            </div>
        </section>

        <section class="panel">
            <span class="label">Top 10 por población</span>
            <ol id="topList" class="ranking"></ol>
        </section>

        <section id="diasporaPanel" class="panel d-none">
            <span class="label">Diáspora · distribución por país</span>
            <div class="stats">
                <div class="stat"><div id="diasporaTotal" class="stat-num">-</div><div class="stat-lbl">Total exterior</div></div>
                <div class="stat"><div id="diasporaPaises" class="stat-num">-</div><div class="stat-lbl">Países</div></div>
                <div class="stat"><div id="diasporaPct" class="stat-num">-</div><div class="stat-lbl">Del padrón</div></div>
            </div>
            <ol id="diasporaList" class="ranking"></ol>
        </section>

        <section class="panel">
            <span class="label">Escala</span>
            <div id="legend" class="legend"></div>
        </section>
    </aside>
</div>
