<div id="reporteDensidadElectoral" data-report="densidad-electoral" class="reporte-page d-none">

    <div class="rp-head">
        <div>
            <h1 class="rp-titulo">Densidad Electoral por Local</h1>
            <p class="rp-sub muted">Ranking de locales por peso electoral · Identificación de puntos estratégicos para movilización</p>
        </div>
        <div class="rp-head-actions">
            <button id="deExportar" class="btn-export" type="button" title="Exportar a CSV">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <div class="rp-stats">
        <div class="rp-stat-card">
            <div id="deStatAlta" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Locales alta densidad <span class="badge-alta">≥600 ins/JRV</span></div>
        </div>
        <div class="rp-stat-card">
            <div id="deStatMedia" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Locales densidad media <span class="badge-media">300–599</span></div>
        </div>
        <div class="rp-stat-card">
            <div id="deStatBaja" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Locales baja densidad <span class="badge-baja">&lt;300</span></div>
        </div>
        <div class="rp-stat-card">
            <div id="deStatTop10" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Inscritos en top 10%</div>
        </div>
    </div>

    <div class="rp-filtros">
        <select id="deProvincia" class="field">
            <option value="">Todas las provincias</option>
        </select>
        <select id="deCanton" class="field" disabled>
            <option value="">Todos los cantones</option>
        </select>
        <div class="rp-order-wrap">
            <button id="deBtnDesc" class="seg-btn active" type="button">Mayor → Menor</button>
            <button id="deBtnAsc"  class="seg-btn"        type="button">Menor → Mayor</button>
        </div>
        <select id="dePageSize" class="field page-size">
            <option value="25">25 / pág.</option>
            <option value="50" selected>50 / pág.</option>
            <option value="100">100 / pág.</option>
        </select>
    </div>

    <div class="rp-tabla-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th>Local de Votación</th>
                    <th>Provincia</th>
                    <th>Cantón</th>
                    <th class="col-num">JRVs</th>
                    <th class="col-num">Inscritos</th>
                    <th class="col-num">Prom/JRV</th>
                    <th class="col-bar"></th>
                </tr>
            </thead>
            <tbody id="deBody"></tbody>
        </table>
    </div>

    <footer class="rp-foot">
        <span id="deInfo" class="muted small"></span>
        <div class="pager">
            <button id="deFirst" class="pg-btn" title="Primera">«</button>
            <button id="dePrev"  class="pg-btn" title="Anterior">‹</button>
            <span   id="dePage"  class="muted small"></span>
            <button id="deNext"  class="pg-btn" title="Siguiente">›</button>
            <button id="deLast"  class="pg-btn" title="Última">»</button>
        </div>
    </footer>

</div>
