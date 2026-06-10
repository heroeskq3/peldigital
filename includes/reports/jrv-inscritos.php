<div id="reporteJrvInscritos" data-report="jrv-inscritos" class="reporte-page d-none">

    <div class="rp-head">
        <div>
            <h1 class="rp-titulo">Distribución del Padrón por JRV</h1>
            <p class="rp-sub muted">Electores inscritos por Junta Receptora de Votos · Padrón TSE 2026</p>
        </div>
        <div class="rp-head-actions">
            <button id="jrvExportar" class="btn-export" type="button" title="Exportar tabla filtrada a CSV">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- Estadísticas resumen -->
    <div class="rp-stats">
        <div class="rp-stat-card">
            <div id="jrvStatJuntas" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Juntas en vista</div>
        </div>
        <div class="rp-stat-card">
            <div id="jrvStatTotal" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Inscritos</div>
        </div>
        <div class="rp-stat-card">
            <div id="jrvStatProm" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Promedio / junta</div>
        </div>
        <div class="rp-stat-card">
            <div id="jrvStatMax" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Mayor junta</div>
        </div>
        <div class="rp-stat-card">
            <div id="jrvStatMin" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Menor junta</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="rp-filtros">
        <select id="jrvProvincia" class="field">
            <option value="">Todas las provincias</option>
        </select>
        <select id="jrvCanton" class="field" disabled>
            <option value="">Todos los cantones</option>
        </select>
        <select id="jrvDistrito" class="field" disabled>
            <option value="">Todos los distritos</option>
        </select>
        <div class="rp-order-wrap">
            <button id="jrvBtnDesc" class="seg-btn active" type="button">Mayor → Menor</button>
            <button id="jrvBtnAsc"  class="seg-btn"        type="button">Menor → Mayor</button>
        </div>
        <select id="jrvPageSize" class="field page-size">
            <option value="25">25 / pág.</option>
            <option value="50" selected>50 / pág.</option>
            <option value="100">100 / pág.</option>
        </select>
    </div>

    <!-- Tabla -->
    <div class="rp-tabla-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Junta</th>
                    <th>Provincia</th>
                    <th>Cantón</th>
                    <th>Distrito</th>
                    <th class="col-num">Inscritos</th>
                    <th class="col-bar"></th>
                </tr>
            </thead>
            <tbody id="jrvBody"></tbody>
        </table>
    </div>

    <!-- Paginación -->
    <footer class="rp-foot">
        <span id="jrvInfo" class="muted small"></span>
        <div class="pager">
            <button id="jrvFirst" class="pg-btn" title="Primera">«</button>
            <button id="jrvPrev"  class="pg-btn" title="Anterior">‹</button>
            <span   id="jrvPage"  class="muted small"></span>
            <button id="jrvNext"  class="pg-btn" title="Siguiente">›</button>
            <button id="jrvLast"  class="pg-btn" title="Última">»</button>
        </div>
    </footer>

</div>
