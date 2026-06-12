<section id="adminEtl" class="admin-section">

    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-arrow-repeat"></i> Pipelines ETL</h1>
            <p class="admin-page-sub">Procesos de extracción, transformación y carga · Estado y última ejecución</p>
        </div>
        <button class="btn-secondary" id="btnRefreshEtl" type="button">
            <i class="bi bi-arrow-clockwise"></i> Actualizar
        </button>
    </div>

    <!-- Resumen rápido -->
    <div class="admin-stats" id="etlStats" style="max-width:600px">
        <div class="admin-stat">
            <div class="admin-stat-lbl">Total pipelines</div>
            <div class="admin-stat-val" id="etlTotal">—</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-lbl">Completados</div>
            <div class="admin-stat-val green" id="etlOk">—</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-lbl">Pendientes</div>
            <div class="admin-stat-val amber" id="etlPend">—</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-lbl">Bloqueados</div>
            <div class="admin-stat-val" style="color:var(--text-muted)" id="etlBlock">—</div>
        </div>
    </div>

    <!-- Tabla de pipelines -->
    <div class="admin-card" style="overflow-x:auto">
        <table class="admin-table" id="etlTable">
            <thead>
                <tr>
                    <th>Pipeline</th>
                    <th class="hide-mobile">Tipo</th>
                    <th class="hide-mobile">Origen → Destino</th>
                    <th>Estado</th>
                    <th class="col-num">Registros</th>
                    <th class="hide-mobile">Última ejecución</th>
                    <th class="hide-mobile">Duración</th>
                </tr>
            </thead>
            <tbody id="etlBody">
                <tr><td colspan="7" class="admin-empty"><i class="bi bi-hourglass-split"></i> Cargando…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Historial de ejecuciones (runs) -->
    <div class="admin-page-head" style="margin-top:2rem">
        <div>
            <h2 class="admin-page-title" style="font-size:1.1rem"><i class="bi bi-clock-history"></i> Historial de ejecuciones</h2>
            <p class="admin-page-sub">Últimas corridas registradas en BD por pipeline</p>
        </div>
    </div>
    <div id="etlRuns"></div>

</section>
