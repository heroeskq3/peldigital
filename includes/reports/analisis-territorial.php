<div id="reporteAnalisisTerritorial" data-report="analisis-territorial" class="reporte-page d-none">

    <div class="rp-head">
        <div>
            <h1 class="rp-titulo">Análisis Territorial</h1>
            <p class="rp-sub muted">Comparativa de participación entre elecciones · <span id="atMetaLabel">—</span></p>
        </div>
        <div class="rp-head-actions">
            <button id="atExportar" class="btn-export" type="button" title="Exportar tabla filtrada a CSV">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- KPIs -->
    <div class="rp-stats">
        <div class="rp-stat-card">
            <div id="atStatPartA" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl" id="atStatPartALbl">Participación elección A</div>
        </div>
        <div class="rp-stat-card">
            <div id="atStatPartB" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl" id="atStatPartBLbl">Participación elección B</div>
        </div>
        <div class="rp-stat-card">
            <div id="atStatDelta" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Diferencia global</div>
        </div>
        <div class="rp-stat-card">
            <div id="atStatTerritorios" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl" id="atStatTerrLbl">Territorios</div>
        </div>
        <div class="rp-stat-card">
            <div id="atStatMaxDelta" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl" id="atStatMaxLbl">Mayor diferencia</div>
        </div>
        <div class="rp-stat-card">
            <div id="atStatMinDelta" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl" id="atStatMinLbl">Menor diferencia</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="rp-filtros">
        <div class="rp-order-wrap" style="gap:4px;align-items:center;">
            <span class="muted small" style="white-space:nowrap">Elección A:</span>
            <select id="atFiltEleccionA" class="field" style="max-width:240px">
                <option value="">Cargando…</option>
            </select>
        </div>
        <div class="rp-order-wrap" style="gap:4px;align-items:center;">
            <span class="muted small" style="white-space:nowrap">vs B:</span>
            <select id="atFiltEleccionB" class="field" style="max-width:240px">
                <option value="">Cargando…</option>
            </select>
        </div>
        <div class="rp-order-wrap">
            <button class="seg-btn active" type="button" data-at-tab="canton">Cantones</button>
            <button class="seg-btn"        type="button" data-at-tab="district">Distritos</button>
        </div>
        <select id="atFiltProv" class="field" style="max-width:170px">
            <option value="">Todas las provincias</option>
        </select>
        <select id="atFiltCant" class="field" style="max-width:170px" disabled>
            <option value="">Todos los cantones</option>
        </select>
        <input  id="atBuscador" class="field" type="search" placeholder="Buscar…" style="max-width:160px">
        <div class="rp-order-wrap">
            <button id="atOrdDesc" class="seg-btn active" type="button">Mayor → Menor</button>
            <button id="atOrdAsc"  class="seg-btn"        type="button">Menor → Mayor</button>
        </div>
        <select id="atPageSize" class="field page-size">
            <option value="25">25 / pág.</option>
            <option value="50">50 / pág.</option>
            <option value="100">100 / pág.</option>
        </select>
    </div>

    <!-- Tabla -->
    <div class="padron-table-wrap" style="margin-top:.5rem">
        <table class="padron-table">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th id="atThNombre">Cantón</th>
                    <th id="atThSub1" class="d-none">Provincia</th>
                    <th id="atThSub2" class="d-none">Cantón</th>
                    <th class="col-num">
                        <a href="#" id="atSortPartA" class="sort-link" title="Ordenar por elección A">% A</a>
                    </th>
                    <th class="col-num">
                        <a href="#" id="atSortPartB" class="sort-link" title="Ordenar por elección B">% B</a>
                    </th>
                    <th class="col-num">
                        <a href="#" id="atSortDelta" class="sort-link active" title="Ordenar por diferencia">Δ pp</a>
                    </th>
                    <th class="col-num seg-col-drill" title="Clic para ver padrón">
                        Inscritos <i class="bi bi-box-arrow-up-right" style="font-size:.7rem;opacity:.5"></i>
                    </th>
                    <th class="col-bar">Comparativa</th>
                </tr>
            </thead>
            <tbody id="atBody">
                <tr><td colspan="9" class="bita-empty">Cargando…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <div class="padron-paginacion" style="margin-top:.5rem">
        <button id="atFirst" class="pag-btn" title="Primera">«</button>
        <button id="atPrev"  class="pag-btn" title="Anterior">‹</button>
        <span   id="atPages" class="pag-info">—</span>
        <button id="atNext"  class="pag-btn" title="Siguiente">›</button>
        <button id="atLast"  class="pag-btn" title="Última">»</button>
        <span   id="atTotal" class="pag-total muted"></span>
    </div>

</div>
