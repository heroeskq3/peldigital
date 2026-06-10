<div id="reporteSegmentacion" data-report="segmentacion" class="reporte-page d-none">

    <div class="rp-head">
        <div>
            <h1 class="rp-titulo">Segmentación Electoral</h1>
            <p class="rp-sub muted">Distribución del padrón por territorio y sexo · TSE 2026</p>
        </div>
        <div class="rp-head-actions">
            <button id="segExportar" class="btn-export" type="button" title="Exportar tabla filtrada a CSV">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- KPI Cards — inscritos -->
    <div class="rp-stats">
        <div class="rp-stat-card">
            <div id="segStatTotal" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Inscritos totales</div>
        </div>
        <div class="rp-stat-card">
            <div id="segStatTerr" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl" id="segStatTerrLbl">Territorios</div>
        </div>
        <div class="rp-stat-card">
            <div id="segStatMax" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl" id="segStatMaxLbl">Mayor</div>
        </div>
        <div class="rp-stat-card">
            <div id="segStatProm" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Promedio</div>
        </div>
    </div>

    <!-- KPI Cards — sexo (datos nacionales) -->
    <div class="rp-stats" style="margin-top:.5rem">
        <div class="rp-stat-card" style="border-left:3px solid #3b82f6">
            <div id="segStatM" class="rp-stat-num" style="color:#3b82f6">—</div>
            <div class="rp-stat-lbl"><i class="bi bi-gender-male"></i> Masculino · <span id="segStatMPct" class="muted">—</span></div>
        </div>
        <div class="rp-stat-card" style="border-left:3px solid #ec4899">
            <div id="segStatF" class="rp-stat-num" style="color:#ec4899">—</div>
            <div class="rp-stat-lbl"><i class="bi bi-gender-female"></i> Femenino · <span id="segStatFPct" class="muted">—</span></div>
        </div>
        <div class="rp-stat-card" style="border-left:3px solid #9ca3af">
            <div id="segStatN" class="rp-stat-num" style="color:#9ca3af">—</div>
            <div class="rp-stat-lbl"><i class="bi bi-question-circle"></i> Sin clasificar · <span id="segStatNPct" class="muted">—</span></div>
        </div>
    </div>

    <!-- Tabs + Filtros -->
    <div class="rp-filtros" style="margin-top:.75rem">
        <div class="rp-order-wrap">
            <button class="seg-btn active" type="button" data-seg-tab="province">Provincias</button>
            <button class="seg-btn"        type="button" data-seg-tab="canton">Cantones</button>
            <button class="seg-btn"        type="button" data-seg-tab="district">Distritos</button>
        </div>
        <select id="segFiltProv" class="field" style="max-width:170px">
            <option value="">Todas las provincias</option>
        </select>
        <select id="segFiltCant" class="field" style="max-width:170px" disabled>
            <option value="">Todos los cantones</option>
        </select>
        <input  id="segBuscador" class="field" type="search" placeholder="Buscar…" style="max-width:180px">
        <div class="rp-order-wrap">
            <button id="segOrdDesc" class="seg-btn active" type="button">Mayor → Menor</button>
            <button id="segOrdAsc"  class="seg-btn"        type="button">Menor → Mayor</button>
        </div>
        <select id="segPageSize" class="field page-size">
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
                    <th id="segThNombre">Provincia</th>
                    <th id="segThSub1" class="d-none">Provincia</th>
                    <th id="segThSub2" class="d-none">Cantón</th>
                    <th class="col-num seg-col-drill" title="Clic en el número para ver inscritos">
                        Inscritos <i class="bi bi-box-arrow-up-right" style="font-size:.7rem;opacity:.5"></i>
                    </th>
                    <th class="col-num" style="color:#3b82f6" title="Inscritos masculino">♂ M%</th>
                    <th class="col-num" style="color:#ec4899" title="Inscritos femenino">♀ F%</th>
                    <th class="col-num">% del total</th>
                    <th class="col-bar">Concentración</th>
                </tr>
            </thead>
            <tbody id="segBody">
                <tr><td colspan="9" class="bita-empty">Cargando…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <div class="padron-paginacion" style="margin-top:.5rem">
        <button id="segFirst" class="pag-btn" title="Primera">«</button>
        <button id="segPrev"  class="pag-btn" title="Anterior">‹</button>
        <span   id="segPages" class="pag-info">—</span>
        <button id="segNext"  class="pag-btn" title="Siguiente">›</button>
        <button id="segLast"  class="pag-btn" title="Última">»</button>
        <span   id="segTotal" class="pag-total muted"></span>
    </div>

    <!-- Pendientes restantes -->
    <div class="coming-soon-requires" style="margin-top:1.5rem;max-width:600px">
        <p class="coming-soon-requires-lbl">
            <i class="bi bi-database-exclamation"></i> Segmentaciones pendientes de datos adicionales:
        </p>
        <ul>
            <li>Por edad — <code>fecha_nac</code> requiere acuerdo oficial con TSE (WAF bloquea scraping masivo)</li>
            <li>Por distrito electoral — requiere asignación de <code>electoral_district_id</code> en el padrón</li>
        </ul>
    </div>

</div>
