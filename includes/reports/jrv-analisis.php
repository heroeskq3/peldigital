<div id="reporteJrvAnalisis" data-report="jrv-analisis" class="reporte-page d-none">

    <div class="rp-head">
        <div>
            <h1 class="rp-titulo">Análisis Estratégico de JRV</h1>
            <p class="rp-sub muted">Juntas Receptoras de Votos · Participación, abstención y oportunidad territorial</p>
        </div>
    </div>

    <!-- Banner de datos pendientes -->
    <div class="rp-alerta" id="jrvAlertaDatos">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            <strong>Análisis de participación pendiente.</strong>
            Las métricas de participación, abstención y oportunidad requieren los resultados
            electorales oficiales del TSE (votos emitidos por JRV). La clasificación actual
            se basa en volumen de padrón como proxy temporal.
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="rp-stats">
        <div class="rp-stat-card">
            <div id="jaStatJuntas" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">JRV analizadas</div>
        </div>
        <div class="rp-stat-card">
            <div id="jaStatInscritos" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Electores inscritos</div>
        </div>
        <div class="rp-stat-card rp-stat-nd">
            <div class="rp-stat-num rp-nd">N/D</div>
            <div class="rp-stat-lbl">Participación promedio</div>
        </div>
        <div class="rp-stat-card rp-stat-nd">
            <div class="rp-stat-num rp-nd">N/D</div>
            <div class="rp-stat-lbl">Abstención promedio</div>
        </div>
        <div class="rp-stat-card rp-stat-nd">
            <div class="rp-stat-num rp-nd">N/D</div>
            <div class="rp-stat-lbl">JRV mayor participación</div>
        </div>
        <div class="rp-stat-card rp-stat-nd">
            <div class="rp-stat-num rp-nd">N/D</div>
            <div class="rp-stat-lbl">JRV menor participación</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="rp-filtros">
        <select id="jaProvincia" class="field">
            <option value="">Todas las provincias</option>
        </select>
        <select id="jaCanton" class="field" disabled>
            <option value="">Todos los cantones</option>
        </select>
        <select id="jaDistrito" class="field" disabled>
            <option value="">Todos los distritos</option>
        </select>
        <div class="rp-order-wrap">
            <button id="jaBtnDesc" class="seg-btn active" type="button">Mayor → Menor</button>
            <button id="jaBtnAsc"  class="seg-btn"        type="button">Menor → Mayor</button>
        </div>
        <select id="jaPageSize" class="field page-size">
            <option value="25">25 / pág.</option>
            <option value="50" selected>50 / pág.</option>
            <option value="100">100 / pág.</option>
        </select>
    </div>

    <!-- Leyenda de clasificación -->
    <div class="rp-leyenda">
        <span class="leyenda-item"><span class="dot dot-alta"></span> Alta (≥600 inscritos)</span>
        <span class="leyenda-item"><span class="dot dot-media"></span> Media (300–599)</span>
        <span class="leyenda-item"><span class="dot dot-baja"></span> Baja (&lt;300)</span>
        <span class="leyenda-nd">· Clasificación por volumen de padrón — se actualizará con participación real</span>
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
                    <th class="col-num">Votaron</th>
                    <th class="col-num">% Part.</th>
                    <th class="col-num">Abstención</th>
                    <th class="col-num">Oportunidad</th>
                    <th>Clasificación</th>
                    <th class="col-bar"></th>
                </tr>
            </thead>
            <tbody id="jaBody"></tbody>
        </table>
    </div>

    <!-- Paginación -->
    <footer class="rp-foot">
        <span id="jaInfo" class="muted small"></span>
        <div class="pager">
            <button id="jaFirst" class="pg-btn" title="Primera">«</button>
            <button id="jaPrev"  class="pg-btn" title="Anterior">‹</button>
            <span   id="jaPage"  class="muted small"></span>
            <button id="jaNext"  class="pg-btn" title="Siguiente">›</button>
            <button id="jaLast"  class="pg-btn" title="Última">»</button>
        </div>
    </footer>

</div>
