<div id="reporteBastiones" data-report="bastiones" class="reporte-page d-none">

    <div class="rp-head">
        <div>
            <h1 class="rp-titulo">Análisis de Bastiones</h1>
            <p class="rp-sub muted">Clasificación territorial por fortaleza electoral histórica · 4 elecciones</p>
        </div>
        <div class="rp-head-actions">
            <button id="bExportar" class="btn-export" type="button" title="Exportar a CSV">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- KPIs -->
    <div class="rp-stats">
        <div class="rp-stat-card">
            <div id="bStatTotalJrvs" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">JRVs analizadas</div>
        </div>
        <div class="rp-stat-card">
            <div id="bStatBastiones" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Bastiones (fuertes + moderados)</div>
        </div>
        <div class="rp-stat-card">
            <div id="bStatCompetitivo" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Competitivos</div>
        </div>
        <div class="rp-stat-card">
            <div id="bStatTransicion" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">En transición</div>
        </div>
        <div class="rp-stat-card">
            <div id="bStatPctProm" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">% dom. promedio</div>
        </div>
        <div class="rp-stat-card">
            <div id="bStatPartProm" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Participación 2026 prom.</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="rp-filtros">
        <div class="rp-order-wrap">
            <label class="muted small" for="bFiltNivel">Nivel</label>
            <select id="bFiltNivel" class="field">
                <option value="jrv">JRV</option>
                <option value="distrito">Distrito</option>
                <option value="canton">Cantón</option>
                <option value="provincia">Provincia</option>
            </select>
        </div>
        <div class="rp-order-wrap">
            <label class="muted small" for="bFiltMode">Vista</label>
            <select id="bFiltMode" class="field">
                <option value="bastiones">Bastiones (% dom.)</option>
                <option value="oportunidades">Oportunidades (índice)</option>
            </select>
        </div>
        <div class="rp-order-wrap">
            <label class="muted small" for="bFiltClasif">Clasificación</label>
            <select id="bFiltClasif" class="field">
                <option value="">Todas</option>
                <option value="bastion_fuerte">Bastión fuerte</option>
                <option value="bastion_moderado">Bastión moderado</option>
                <option value="competitivo">Competitivo</option>
                <option value="transicion">En transición</option>
                <option value="volatil">Volátil</option>
            </select>
        </div>
        <div class="rp-order-wrap">
            <label class="muted small" for="bFiltPartido">Partido dom.</label>
            <select id="bFiltPartido" class="field">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="rp-order-wrap">
            <label class="muted small" for="bFiltProv">Provincia</label>
            <select id="bFiltProv" class="field">
                <option value="">Todas</option>
            </select>
        </div>
        <div class="rp-order-wrap">
            <label class="muted small" for="bFiltCanton">Cantón</label>
            <select id="bFiltCanton" class="field">
                <option value="">Todos</option>
            </select>
        </div>
    </div>

    <!-- Leyenda de clasificaciones -->
    <div class="b-leyenda" id="bLeyenda">
        <span class="b-badge b-badge--fuerte">Bastión fuerte</span>
        <span class="b-badge-lbl">= mismo partido gana 2+ elecciones presidenciales con ≥ 60% prom.</span>
        <span class="b-badge b-badge--moderado">Bastión moderado</span>
        <span class="b-badge-lbl">≥ 50%, 2+ victorias.</span>
        <span class="b-badge b-badge--competitivo">Competitivo</span>
        <span class="b-badge-lbl">margen estrecho o 2 partidos alternantes.</span>
        <span class="b-badge b-badge--transicion">En transición</span>
        <span class="b-badge-lbl">cambió partido dominante entre 2022 y 2026.</span>
        <span class="b-badge b-badge--volatil">Volátil / sin historial</span>
        <span class="b-badge-lbl">sin patrón claro o JRV nueva.</span>
    </div>

    <!-- Estado de carga -->
    <div id="bLoading" class="rp-loading d-none">
        <i class="bi bi-hourglass-split"></i> Cargando…
    </div>
    <div id="bError" class="rp-error d-none">
        <i class="bi bi-exclamation-circle"></i> <span id="bErrorMsg"></span>
    </div>

    <!-- Tabla -->
    <div class="rep-table-wrap" id="bTableWrap">
        <table class="rep-table rep-table--bastiones" id="bTabla">
            <thead id="bThead"></thead>
            <tbody id="bTbody"></tbody>
        </table>
        <p id="bTotalLabel" class="muted small" style="margin:.5rem 0 0"></p>
    </div>

</div>
