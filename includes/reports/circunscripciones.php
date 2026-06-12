<div id="reporteCircunscripciones" data-report="circunscripciones" class="reporte-page d-none">

    <div class="rp-head">
        <div>
            <h1 class="rp-titulo">Circunscripciones Legislativas</h1>
            <p class="rp-sub muted">Las 7 circunscripciones del país · Desglose de inscritos por sexo · Padrón TSE 2026</p>
        </div>
        <div class="rp-head-actions"></div>
    </div>

    <!-- KPIs nacionales -->
    <div class="rp-stats">
        <div class="rp-stat-card">
            <div id="cirStatTotal" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Total inscritos</div>
        </div>
        <div class="rp-stat-card">
            <div id="cirStatH" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Hombres (M)</div>
        </div>
        <div class="rp-stat-card">
            <div id="cirStatM" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Mujeres (F)</div>
        </div>
        <div class="rp-stat-card">
            <div id="cirStatLocales" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Locales de votación</div>
        </div>
    </div>

    <!-- Tarjetas por circunscripción -->
    <div id="cirCards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;margin-top:1rem;">
        <div class="rp-stat-card" style="grid-column:1/-1;text-align:center;padding:2rem">
            <span class="muted">Cargando circunscripciones…</span>
        </div>
    </div>

    <!-- Tabla detalle -->
    <div class="rp-tabla-wrap" style="margin-top:1.5rem">
        <table class="dt">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Circunscripción</th>
                    <th>Provincia</th>
                    <th class="col-num">Locales</th>
                    <th class="col-num">JRVs</th>
                    <th class="col-num">Hombres</th>
                    <th class="col-num">Mujeres</th>
                    <th class="col-num">Inscritos</th>
                    <th class="col-num">% País</th>
                    <th class="col-bar"></th>
                </tr>
            </thead>
            <tbody id="cirBody"></tbody>
        </table>
    </div>

</div>
