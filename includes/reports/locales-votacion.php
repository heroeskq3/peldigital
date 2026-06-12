<div id="reporteLocalesVotacion" data-report="locales-votacion" class="reporte-page d-none">

    <div class="rp-head">
        <div>
            <h1 class="rp-titulo">Locales de Votación</h1>
            <p class="rp-sub muted">Centros de votación habilitados por el TSE · Padrón 2026</p>
        </div>
        <div class="rp-head-actions">
            <button id="lvExportar" class="btn-export" type="button" title="Exportar a CSV">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <div class="rp-stats">
        <div class="rp-stat-card">
            <div id="lvStatLocales" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Locales</div>
        </div>
        <div class="rp-stat-card">
            <div id="lvStatTotal" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Inscritos</div>
        </div>
        <div class="rp-stat-card">
            <div id="lvStatProm" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Promedio / local</div>
        </div>
        <div class="rp-stat-card">
            <div id="lvStatMax" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Local más grande</div>
        </div>
    </div>

    <div class="rp-filtros">
        <input id="lvBuscar" class="field" type="search" placeholder="Buscar local…" style="max-width:220px">
        <select id="lvProvincia" class="field">
            <option value="">Todas las provincias</option>
        </select>
        <select id="lvCanton" class="field" disabled>
            <option value="">Todos los cantones</option>
        </select>
        <div class="rp-order-wrap">
            <button id="lvBtnDesc" class="seg-btn active" type="button">Mayor → Menor</button>
            <button id="lvBtnAsc"  class="seg-btn"        type="button">Menor → Mayor</button>
        </div>
        <select id="lvPageSize" class="field page-size">
            <option value="25">25 / pág.</option>
            <option value="50" selected>50 / pág.</option>
            <option value="100">100 / pág.</option>
        </select>
    </div>

    <div class="rp-tabla-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Local de Votación</th>
                    <th>Provincia</th>
                    <th>Cantón</th>
                    <th class="col-num">JRVs</th>
                    <th class="col-num">Inscritos</th>
                    <th class="col-bar"></th>
                </tr>
            </thead>
            <tbody id="lvBody"></tbody>
        </table>
    </div>

    <footer class="rp-foot">
        <span id="lvInfo" class="muted small"></span>
        <div class="pager">
            <button id="lvFirst" class="pg-btn" title="Primera">«</button>
            <button id="lvPrev"  class="pg-btn" title="Anterior">‹</button>
            <span   id="lvPage"  class="muted small"></span>
            <button id="lvNext"  class="pg-btn" title="Siguiente">›</button>
            <button id="lvLast"  class="pg-btn" title="Última">»</button>
        </div>
    </footer>

</div>
