<div id="reporteJuntasPadronal" data-report="juntas-padronal" class="reporte-page d-none">

    <!-- Cabecera estilo TSE -->
    <div class="tse-report-head">
        <div>
            <h1 class="tse-report-title">Padrón Nacional Electoral 2026</h1>
            <h2 class="tse-report-sub">Juntas Receptoras de Votos</h2>
        </div>
        <div class="tse-report-actions">
            <button id="jpBorrarFiltros" class="tse-btn" type="button">
                <i class="bi bi-x-circle"></i> Borrar Filtros
            </button>
            <button id="juntasExportar" class="tse-btn" type="button">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- Contenido de dos paneles -->
    <div class="tse-bicolumn">

        <!-- Panel izquierdo: slider + lista de provincias + KPIs -->
        <div class="tse-left-panel" style="width:220px">

            <!-- Slider de rango -->
            <div class="tse-slider-section">
                <div class="tse-slider-label">Seleccione junta o rango de juntas</div>
                <div class="tse-slider-row">
                    <input id="juntaNumMin" type="number" class="tse-slider-num" min="1" max="7063" value="1">
                    <div class="tse-slider-track">
                        <div class="tse-slider-track-bg"></div>
                        <div id="juntaTrackFill" class="tse-slider-track-fill"></div>
                        <input id="juntaSliderMin" type="range" class="tse-slider-input" min="1" max="7063" value="1" step="1">
                        <input id="juntaSliderMax" type="range" class="tse-slider-input" min="1" max="7063" value="7063" step="1">
                    </div>
                    <input id="juntaNumMax" type="number" class="tse-slider-num" min="1" max="7063" value="7063">
                </div>
            </div>

            <!-- Lista de Provincias (filtro) -->
            <div class="tse-filter-col-head" style="padding:.4rem .6rem">Provincia</div>
            <ul class="tse-filter-list" id="jpProvList" style="flex:1"></ul>

            <!-- KPIs en panel izquierdo -->
            <div class="tse-left-kpis">
                <div class="tse-left-kpi">
                    <div class="tse-left-kpi-lbl">Cantidad Juntas</div>
                    <div id="juntasStatJuntas" class="tse-left-kpi-val">—</div>
                </div>
                <div class="tse-left-kpi">
                    <div class="tse-left-kpi-lbl">Electorado</div>
                    <div id="juntasStatInscritos" class="tse-left-kpi-val">—</div>
                </div>
                <div class="tse-left-kpi">
                    <div class="tse-left-kpi-lbl">Edad Promedio</div>
                    <div class="tse-left-kpi-val muted" style="font-size:.9rem;font-style:italic">N/D</div>
                </div>
                <div class="tse-left-kpi">
                    <div class="tse-left-kpi-lbl" id="juntasStatTerrLbl">Territorios</div>
                    <div id="juntasStatTerr" class="tse-left-kpi-val">—</div>
                </div>
            </div>
        </div>

        <!-- Panel derecho: tabs de nivel + tabla -->
        <div class="tse-right-panel">

            <!-- Tabs de nivel + ordenamiento + tamaño -->
            <div class="tse-right-toolbar">
                <div class="tse-tab-row">
                    <button class="tse-tab-btn active" type="button" data-juntas-tab="province">Provincias</button>
                    <button class="tse-tab-btn"        type="button" data-juntas-tab="canton">Cantones</button>
                    <button class="tse-tab-btn"        type="button" data-juntas-tab="district">Distritos</button>
                    <button class="tse-tab-btn"        type="button" data-juntas-tab="junta">Juntas</button>
                </div>
                <div class="rp-order-wrap">
                    <button id="juntasOrdAsc"  class="seg-btn active" type="button">Asc ↑</button>
                    <button id="juntasOrdDesc" class="seg-btn"        type="button">Desc ↓</button>
                </div>
                <select id="juntasPageSize" class="field page-size">
                    <option value="25">25 / pág.</option>
                    <option value="50" selected>50 / pág.</option>
                    <option value="100">100 / pág.</option>
                </select>
            </div>

            <!-- Tabla -->
            <div class="padron-table-wrap">
                <table class="padron-table">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th id="juntasThNombre">Provincia</th>
                            <th id="juntasThSub1" class="d-none">—</th>
                            <th id="juntasThSub2" class="d-none">—</th>
                            <th id="juntasThSub3" class="d-none">—</th>
                            <th class="col-num">Juntas</th>
                            <th class="col-num">Junta Menor</th>
                            <th class="col-num">Junta Mayor</th>
                            <th class="col-num">Electorado</th>
                            <th class="col-bar">Concentración</th>
                        </tr>
                    </thead>
                    <tbody id="juntasBody">
                        <tr><td colspan="10" class="bita-empty">Cargando…</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="padron-paginacion">
                <button id="juntasFirst" class="pag-btn" title="Primera">«</button>
                <button id="juntasPrev"  class="pag-btn" title="Anterior">‹</button>
                <span   id="juntasPages" class="pag-info">—</span>
                <button id="juntasNext"  class="pag-btn" title="Siguiente">›</button>
                <button id="juntasLast"  class="pag-btn" title="Última">»</button>
                <span   id="juntasTotal" class="pag-total muted"></span>
            </div>

        </div><!-- /.tse-right-panel -->
    </div><!-- /.tse-bicolumn -->

</div>
