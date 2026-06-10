<div id="reporteParticipacion" data-report="participacion" class="reporte-page d-none">

    <div class="rp-head">
        <div>
            <h1 class="rp-titulo">Participación Electoral</h1>
            <p class="rp-sub muted">% votación y abstención por territorio · <span id="partMetaLabel">Elecciones 2026</span></p>
        </div>
        <div class="rp-head-actions">
            <button id="partExportar" class="btn-export" type="button" title="Exportar tabla filtrada a CSV">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- KPIs -->
    <div class="rp-stats">
        <div class="rp-stat-card">
            <div id="partStatPart" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Participación global</div>
        </div>
        <div class="rp-stat-card">
            <div id="partStatAbs" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Abstención global</div>
        </div>
        <div class="rp-stat-card">
            <div id="partStatVotos" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Votos emitidos</div>
        </div>
        <div class="rp-stat-card">
            <div id="partStatInscritos" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Inscritos (TSE)</div>
        </div>
        <div class="rp-stat-card">
            <div id="partStatMax" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl" id="partStatMaxLbl">Mayor participación</div>
        </div>
        <div class="rp-stat-card">
            <div id="partStatMin" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl" id="partStatMinLbl">Menor participación</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="rp-filtros">
        <select id="partFiltEleccion" class="field" style="max-width:260px">
            <option value="">Cargando elecciones…</option>
        </select>
        <div class="rp-order-wrap">
            <button class="seg-btn active" type="button" data-part-tab="province">Provincias</button>
            <button class="seg-btn"        type="button" data-part-tab="canton">Cantones</button>
            <button class="seg-btn"        type="button" data-part-tab="district">Distritos</button>
        </div>
        <select id="partFiltProv" class="field" style="max-width:170px">
            <option value="">Todas las provincias</option>
        </select>
        <select id="partFiltCant" class="field" style="max-width:170px" disabled>
            <option value="">Todos los cantones</option>
        </select>
        <input  id="partBuscador" class="field" type="search" placeholder="Buscar…" style="max-width:180px">
        <div class="rp-order-wrap">
            <button id="partOrdDesc" class="seg-btn active" type="button">Mayor → Menor</button>
            <button id="partOrdAsc"  class="seg-btn"        type="button">Menor → Mayor</button>
        </div>
        <select id="partPageSize" class="field page-size">
            <option value="25">25 / pág.</option>
            <option value="50">50 / pág.</option>
            <option value="100">100 / pág.</option>
        </select>
    </div>

    <!-- Desglose por partido (colapsable) -->
    <div id="partPartidosWrap" class="d-none" style="margin-bottom:.75rem">
        <div class="rp-filtros" style="justify-content:space-between;align-items:center;padding:.5rem .75rem;border-radius:6px;background:var(--bg-card);border:1px solid var(--border-color)">
            <span style="font-weight:600;font-size:.85rem">Votos por partido <span id="partPartidosLabel" class="muted" style="font-weight:normal;font-size:.78rem"></span></span>
            <button id="partTogglePartidos" class="seg-btn" type="button" style="padding:.2rem .75rem;font-size:.8rem">
                <i class="bi bi-bar-chart-fill"></i> Ver desglose
            </button>
        </div>
        <div id="partPartidosPanel" class="d-none" style="margin-top:.4rem;padding:.75rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:6px">
            <div id="partPartidosBody" style="display:grid;gap:.35rem"></div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="padron-table-wrap" style="margin-top:.5rem">
        <table class="padron-table">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th id="partThNombre">Provincia</th>
                    <th id="partThSub1" class="d-none">Provincia</th>
                    <th id="partThSub2" class="d-none">Cantón</th>
                    <th class="col-num">
                        <a href="#" id="partSortPart" class="sort-link active" title="Ordenar por participación">
                            % Participación <i class="bi bi-arrow-down-short"></i>
                        </a>
                    </th>
                    <th class="col-num">% Abstención</th>
                    <th class="col-num seg-col-drill" title="Clic para ver inscritos">
                        Inscritos <i class="bi bi-box-arrow-up-right" style="font-size:.7rem;opacity:.5"></i>
                    </th>
                    <th class="col-num">Votos</th>
                    <th class="col-bar">Participación</th>
                </tr>
            </thead>
            <tbody id="partBody">
                <tr><td colspan="9" class="bita-empty">Cargando…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <div class="padron-paginacion" style="margin-top:.5rem">
        <button id="partFirst" class="pag-btn" title="Primera">«</button>
        <button id="partPrev"  class="pag-btn" title="Anterior">‹</button>
        <span   id="partPages" class="pag-info">—</span>
        <button id="partNext"  class="pag-btn" title="Siguiente">›</button>
        <button id="partLast"  class="pag-btn" title="Última">»</button>
        <span   id="partTotal" class="pag-total muted"></span>
    </div>

</div>
