<div id="reporteBastionesMapa" data-report="bastiones-mapa" class="reporte-page d-none">

    <div class="rp-head">
        <div>
            <h1 class="rp-titulo">Mapa de Bastiones</h1>
            <p class="rp-sub muted">Distribución territorial de fortaleza electoral · color por clasificación, partido u oportunidad</p>
        </div>
    </div>

    <!-- Controles del mapa -->
    <div class="bm-controles">
        <div class="rp-order-wrap">
            <label class="muted small" for="bmNivel">Nivel</label>
            <select id="bmNivel" class="field">
                <option value="distrito">Distrito</option>
                <option value="canton">Cantón</option>
                <option value="provincia">Provincia</option>
            </select>
        </div>
        <div class="rp-order-wrap">
            <label class="muted small" for="bmModo">Colorear por</label>
            <select id="bmModo" class="field">
                <option value="clasificacion">Clasificación</option>
                <option value="partido">Partido dominante</option>
                <option value="oportunidad">Índice de oportunidad</option>
            </select>
        </div>
        <div class="rp-order-wrap">
            <label class="muted small" for="bmProv">Provincia</label>
            <select id="bmProv" class="field">
                <option value="">Todas</option>
            </select>
        </div>
        <div id="bmLoadingBadge" class="bm-loading-badge d-none">
            <i class="bi bi-hourglass-split"></i> Cargando…
        </div>
    </div>

    <!-- Mapa -->
    <div class="bm-wrap">
        <div id="bmMap" class="bm-map"></div>
        <div id="bmLeyenda" class="bm-leyenda-mapa"></div>
    </div>

    <!-- Tooltip fijo (panel lateral al hover) -->
    <div id="bmTooltip" class="bm-tooltip d-none">
        <div class="bm-tt-nombre" id="bmTtNombre"></div>
        <div class="bm-tt-body" id="bmTtBody"></div>
    </div>

    <!-- Error -->
    <div id="bmError" class="rp-error d-none">
        <i class="bi bi-exclamation-circle"></i> <span id="bmErrorMsg"></span>
    </div>

</div>
