<section id="adminPipelines" class="admin-section">

    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-diagram-3"></i> Pipelines de migración</h1>
            <p class="admin-page-sub">Estado de cada archivo en <code>migrations/</code></p>
        </div>
        <button class="btn-secondary" id="btnRefreshPipes" type="button">
            <i class="bi bi-arrow-clockwise"></i> Actualizar
        </button>
    </div>

    <!-- Stats -->
    <div class="admin-stats" style="max-width:420px">
        <div class="admin-stat">
            <div class="admin-stat-lbl">Total</div>
            <div class="admin-stat-val" id="pipeTotal">—</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-lbl">Aplicadas</div>
            <div class="admin-stat-val green" id="pipeApplied">—</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-lbl">Pendientes</div>
            <div class="admin-stat-val amber" id="pipePending">—</div>
        </div>
    </div>

    <div class="admin-card">
        <ul class="pipe-list" id="pipeList">
            <li class="pipe-item" style="color:var(--text-muted)">
                <i class="bi bi-hourglass-split pipe-icon"></i>
                <span class="pipe-name">Cargando…</span>
            </li>
        </ul>
    </div>

</section>
