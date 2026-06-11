<section id="adminReportes" class="admin-section">

    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-layout-text-sidebar"></i> Reportes y Categorías</h1>
            <p class="admin-page-sub">Gestión del menú de análisis y estado de cada reporte</p>
        </div>
    </div>

    <!-- Sub-tabs -->
    <div class="rep-tabs" id="repTabs">
        <button class="rep-tab active" data-reptab="categorias">
            <i class="bi bi-folder2"></i> Categorías
        </button>
        <button class="rep-tab" data-reptab="reportes">
            <i class="bi bi-file-earmark-bar-graph"></i> Reportes
        </button>
    </div>

    <!-- ── Panel: Categorías ───────────────────────────────────────────────── -->
    <div id="repPanelCategorias" class="rep-panel">
        <div class="admin-card">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="col-num">Orden</th>
                            <th>Nombre</th>
                            <th class="hide-mobile">Slug</th>
                            <th class="hide-mobile">Ícono</th>
                            <th class="col-num">Reportes</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="catBody">
                        <tr><td colspan="6" class="admin-empty">Cargando…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div style="margin-top:1rem">
            <button class="btn-primary" id="btnNuevaCat" type="button">
                <i class="bi bi-plus-lg"></i> Nueva categoría
            </button>
        </div>
    </div>

    <!-- ── Panel: Reportes ────────────────────────────────────────────────── -->
    <div id="repPanelReportes" class="rep-panel d-none">
        <div class="admin-card">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="col-num hide-mobile">#</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th class="col-num hide-mobile">Orden</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="repBody">
                        <tr><td colspan="6" class="admin-empty">Cargando…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Modal: Editar Categoría ───────────────────────────────────────── -->
    <div id="catModal" class="admin-overlay d-none">
        <div class="admin-modal">
            <div class="admin-modal-head">
                <h2 class="admin-modal-title" id="catModalTitle">Categoría</h2>
                <button class="icon-only" id="catModalClose" type="button"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="admin-modal-body">
                <input type="hidden" id="catId">
                <div class="form-group">
                    <label class="form-label">Nombre</label>
                    <input type="text" class="form-input" id="catName" placeholder="Padrón & Territorio">
                </div>
                <div class="form-group">
                    <label class="form-label">Ícono Bootstrap <small style="opacity:.6">(ej: bi-person-vcard-fill)</small></label>
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <input type="text" class="form-input" id="catIcon" placeholder="bi-folder">
                        <i id="catIconPreview" class="bi bi-folder" style="font-size:1.4rem;min-width:1.5rem"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Orden</label>
                    <input type="number" class="form-input" id="catSort" min="1" max="999" value="50" style="width:100px">
                </div>
            </div>
            <div class="admin-modal-foot">
                <button class="btn-secondary" id="catModalCancel" type="button">Cancelar</button>
                <button class="btn-primary" id="catModalSave" type="button">
                    <i class="bi bi-check-lg"></i> Guardar
                </button>
            </div>
        </div>
    </div>

    <!-- ── Modal: Editar Reporte ──────────────────────────────────────────── -->
    <div id="repModal" class="admin-overlay d-none">
        <div class="admin-modal">
            <div class="admin-modal-head">
                <h2 class="admin-modal-title">Editar reporte</h2>
                <button class="icon-only" id="repModalClose" type="button"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="admin-modal-body">
                <input type="hidden" id="repId">
                <div class="form-group">
                    <label class="form-label">Nombre completo</label>
                    <input type="text" class="form-input" id="repName">
                </div>
                <div class="form-group">
                    <label class="form-label">Nombre corto (menú)</label>
                    <input type="text" class="form-input" id="repShortName">
                </div>
                <div class="form-group">
                    <label class="form-label">Categoría</label>
                    <select class="form-input" id="repCatId"></select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label class="form-label">Ícono Bootstrap</label>
                        <div style="display:flex;gap:.5rem;align-items:center">
                            <input type="text" class="form-input" id="repIcon">
                            <i id="repIconPreview" class="bi" style="font-size:1.4rem;min-width:1.5rem"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Orden</label>
                        <input type="number" class="form-input" id="repSort" min="1" max="999">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select class="form-input" id="repStatus">
                        <option value="active">Activo</option>
                        <option value="partial">Parcial</option>
                        <option value="pending">Pendiente</option>
                    </select>
                </div>
            </div>
            <div class="admin-modal-foot">
                <button class="btn-secondary" id="repModalCancel" type="button">Cancelar</button>
                <button class="btn-primary" id="repModalSave" type="button">
                    <i class="bi bi-check-lg"></i> Guardar
                </button>
            </div>
        </div>
    </div>

</section>
