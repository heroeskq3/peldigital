<div id="reporteDistritosElectorales" data-report="distritos-electorales" class="reporte-page d-none">

    <!-- Cabecera estilo TSE -->
    <div class="tse-report-head">
        <div>
            <h1 class="tse-report-title">Padrón Nacional Electoral 2026</h1>
            <h2 class="tse-report-sub">Distritos Electorales</h2>
        </div>
        <div class="tse-report-actions">
            <button id="deBorrarFiltros" class="tse-btn" type="button">
                <i class="bi bi-x-circle"></i> Borrar Filtros
            </button>
            <button id="distElExportar" class="tse-btn" type="button">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- Contenido de dos paneles -->
    <div class="tse-bicolumn">

        <!-- Panel izquierdo: listas + KPIs -->
        <div class="tse-left-panel" style="width:300px">
            <div class="tse-filter-cols">

                <!-- Columna Provincia -->
                <div class="tse-filter-col">
                    <div class="tse-filter-col-head">Provincia</div>
                    <ul class="tse-filter-list" id="deProvList"></ul>
                </div>

                <!-- Columna Cantón -->
                <div class="tse-filter-col">
                    <div class="tse-filter-col-head">Cantón</div>
                    <ul class="tse-filter-list" id="deCantList">
                        <li class="tse-filter-item-empty">Selecciona provincia</li>
                    </ul>
                </div>

            </div>

            <!-- KPI stats en panel izquierdo -->
            <div class="tse-left-kpis">
                <div class="tse-left-kpi">
                    <div class="tse-left-kpi-lbl">Cantidad Juntas</div>
                    <div id="distElStatJuntas" class="tse-left-kpi-val">—</div>
                </div>
                <div class="tse-left-kpi">
                    <div class="tse-left-kpi-lbl">Distritos</div>
                    <div id="distElStatDistritos" class="tse-left-kpi-val">—</div>
                </div>
                <div class="tse-left-kpi">
                    <div class="tse-left-kpi-lbl">Edad Promedio</div>
                    <div class="tse-left-kpi-val muted" style="font-size:.9rem;font-style:italic">N/D</div>
                </div>
                <div class="tse-left-kpi">
                    <div class="tse-left-kpi-lbl">Electorado</div>
                    <div id="distElStatTotal" class="tse-left-kpi-val">—</div>
                </div>
                <div class="tse-left-kpi" style="grid-column:span 2">
                    <div class="tse-left-kpi-lbl">Hombre <span id="distElStatMPct" class="muted" style="font-weight:400">—</span></div>
                    <div id="distElStatM" class="tse-left-kpi-val" style="color:#3b82f6">—</div>
                </div>
                <div class="tse-left-kpi" style="grid-column:span 2">
                    <div class="tse-left-kpi-lbl">Mujer <span id="distElStatFPct" class="muted" style="font-weight:400">—</span></div>
                    <div id="distElStatF" class="tse-left-kpi-val" style="color:#ec4899">—</div>
                </div>
            </div>
        </div>

        <!-- Panel derecho: búsqueda + tabla -->
        <div class="tse-right-panel">

            <!-- Barra de herramientas -->
            <div class="tse-right-toolbar">
                <input id="distElBuscador" class="field" type="search" placeholder="Buscar distrito…" style="max-width:200px;flex:1">
                <div class="rp-order-wrap">
                    <button id="distElOrdDesc" class="seg-btn active" type="button">Mayor → Menor</button>
                    <button id="distElOrdAsc"  class="seg-btn"        type="button">Menor → Mayor</button>
                </div>
                <select id="distElPageSize" class="field page-size">
                    <option value="25">25 / pág.</option>
                    <option value="50">50 / pág.</option>
                    <option value="100">100 / pág.</option>
                </select>
            </div>

            <!-- Tabla -->
            <div class="padron-table-wrap">
                <table class="padron-table">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th>Distrito Administrativo</th>
                            <th>Cantón</th>
                            <th>Provincia</th>
                            <th class="col-num" style="color:#3b82f6">♂ HOMBRE</th>
                            <th class="col-num" style="color:#ec4899">♀ MUJER</th>
                            <th class="col-num">Total</th>
                            <th class="col-num" title="Juntas receptoras de votos">Juntas</th>
                            <th class="col-bar">Concentración</th>
                        </tr>
                    </thead>
                    <tbody id="distElBody">
                        <tr><td colspan="9" class="bita-empty">Cargando…</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="padron-paginacion">
                <button id="distElFirst" class="pag-btn" title="Primera">«</button>
                <button id="distElPrev"  class="pag-btn" title="Anterior">‹</button>
                <span   id="distElPages" class="pag-info">—</span>
                <button id="distElNext"  class="pag-btn" title="Siguiente">›</button>
                <button id="distElLast"  class="pag-btn" title="Última">»</button>
                <span   id="distElTotal" class="pag-total muted"></span>
            </div>

            <!-- Nota -->
            <p class="muted" style="font-size:.72rem;margin:.25rem 0 0">
                <i class="bi bi-info-circle"></i>
                Muestra <strong>distritos administrativos</strong> del padrón TSE 2026.
                Sexo estimado por nombre · 71.7% cobertura.
            </p>

        </div><!-- /.tse-right-panel -->
    </div><!-- /.tse-bicolumn -->

</div>
