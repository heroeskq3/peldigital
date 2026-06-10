<div id="reporteSegmentacion" data-report="segmentacion" class="reporte-page d-none">

    <!-- Cabecera estilo TSE -->
    <div class="tse-report-head">
        <div>
            <h1 class="tse-report-title">Padrón Nacional Electoral 2026</h1>
            <h2 class="tse-report-sub">Provincia, Cantones y Distritos</h2>
        </div>
        <div class="tse-report-actions">
            <button id="sfBorrarFiltros" class="tse-btn" type="button">
                <i class="bi bi-x-circle"></i> Borrar Filtros
            </button>
            <button id="segExportar" class="tse-btn" type="button">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- Contenido de dos paneles -->
    <div class="tse-bicolumn">

        <!-- Panel izquierdo: listas de filtro -->
        <div class="tse-left-panel">
            <div class="tse-filter-cols">

                <!-- Columna Provincia -->
                <div class="tse-filter-col">
                    <div class="tse-filter-col-head">Provincia</div>
                    <ul class="tse-filter-list" id="sfProvList"></ul>
                </div>

                <!-- Columna Cantón -->
                <div class="tse-filter-col">
                    <div class="tse-filter-col-head">Cantón</div>
                    <ul class="tse-filter-list" id="sfCantList">
                        <li class="tse-filter-item-empty">Selecciona provincia</li>
                    </ul>
                </div>

                <!-- Columna Distrito -->
                <div class="tse-filter-col">
                    <div class="tse-filter-col-head">Distrito</div>
                    <ul class="tse-filter-list" id="sfDistList">
                        <li class="tse-filter-item-empty">Selecciona cantón</li>
                    </ul>
                </div>

            </div>
        </div>

        <!-- Panel derecho: gráficas + estadísticas -->
        <div class="tse-right-panel">

            <!-- Barras de edad (placeholder) -->
            <div class="sf-age-wrap">
                <div class="sf-section-label">Según grupo de edad</div>
                <div class="sf-age-bars">
                    <div class="sf-edad-row"><span class="sf-edad-lbl">De 18 a 29 años</span><div class="sf-edad-bar-outer"><div class="sf-edad-bar" style="width:78%"></div></div><span class="sf-edad-nd">N/D</span></div>
                    <div class="sf-edad-row"><span class="sf-edad-lbl">De 30 a 39 años</span><div class="sf-edad-bar-outer"><div class="sf-edad-bar" style="width:71%"></div></div><span class="sf-edad-nd">N/D</span></div>
                    <div class="sf-edad-row"><span class="sf-edad-lbl">De 40 a 49 años</span><div class="sf-edad-bar-outer"><div class="sf-edad-bar" style="width:62%"></div></div><span class="sf-edad-nd">N/D</span></div>
                    <div class="sf-edad-row"><span class="sf-edad-lbl">De 50 a 59 años</span><div class="sf-edad-bar-outer"><div class="sf-edad-bar" style="width:49%"></div></div><span class="sf-edad-nd">N/D</span></div>
                    <div class="sf-edad-row"><span class="sf-edad-lbl">De 60 a 69 años</span><div class="sf-edad-bar-outer"><div class="sf-edad-bar" style="width:43%"></div></div><span class="sf-edad-nd">N/D</span></div>
                    <div class="sf-edad-row"><span class="sf-edad-lbl">De 70 a 79 años</span><div class="sf-edad-bar-outer"><div class="sf-edad-bar" style="width:24%"></div></div><span class="sf-edad-nd">N/D</span></div>
                    <div class="sf-edad-row"><span class="sf-edad-lbl">De 80 a 89 años</span><div class="sf-edad-bar-outer"><div class="sf-edad-bar" style="width:9%"></div></div><span class="sf-edad-nd">N/D</span></div>
                    <div class="sf-edad-row"><span class="sf-edad-lbl">90 años y más</span><div class="sf-edad-bar-outer"><div class="sf-edad-bar" style="width:2%"></div></div><span class="sf-edad-nd">N/D</span></div>
                </div>
                <div class="sf-age-overlay">
                    <i class="bi bi-calendar-x"></i>
                    <span>Requiere <code>fecha_nac</code> · pendiente integración TSE</span>
                </div>
            </div>

            <!-- KPIs: Edad Promedio + Electorado -->
            <div class="sf-stat-row">
                <div class="sf-stat-box">
                    <div class="sf-stat-lbl">Edad Promedio</div>
                    <div class="sf-stat-val nd">N/D</div>
                </div>
                <div class="sf-stat-box">
                    <div class="sf-stat-lbl">Electorado</div>
                    <div id="sfElectorado" class="sf-stat-val">—</div>
                </div>
            </div>

            <!-- Fila inferior: dona + tabla de sexo -->
            <div class="sf-bottom">
                <div class="sf-pie-wrap">
                    <div class="sf-pie-canvas-wrap">
                        <canvas id="segSexChart"></canvas>
                    </div>
                    <div class="sf-pie-legend">
                        <div class="sf-pie-leg-h">HOMBRE <span id="sfPctH">—</span></div>
                        <div class="sf-pie-leg-m">MUJER <span id="sfPctM">—</span></div>
                    </div>
                </div>
                <div class="sf-sex-table-wrap">
                    <table class="sf-sex-table">
                        <thead>
                            <tr>
                                <th>SEXO</th>
                                <th>Electorado</th>
                                <th>Edad Promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="sf-sex-h sex-lbl">HOMBRE</td>
                                <td id="sfHombre">—</td>
                                <td class="muted" style="font-style:italic;font-size:.8rem">N/D</td>
                            </tr>
                            <tr>
                                <td class="sf-sex-m sex-lbl">MUJER</td>
                                <td id="sfMujer">—</td>
                                <td class="muted" style="font-style:italic;font-size:.8rem">N/D</td>
                            </tr>
                            <tr>
                                <td class="sex-lbl muted">SIN DATO</td>
                                <td id="sfSinDato">—</td>
                                <td class="muted" style="font-style:italic;font-size:.8rem">N/D</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="muted" style="font-size:.68rem;margin:.6rem 0 0">
                        <i class="bi bi-info-circle"></i>
                        Sexo estimado por lookup de nombres · 71.7% de cobertura
                    </p>
                </div>
            </div>

        </div><!-- /.tse-right-panel -->
    </div><!-- /.tse-bicolumn -->

</div>
