<section id="adminRoles" class="admin-section">

    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-shield-check"></i> Roles de usuario</h1>
            <p class="admin-page-sub">Permisos y descripciones de cada rol del sistema</p>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="col-num hide-mobile">#</th>
                        <th>Nombre del rol</th>
                        <th class="hide-mobile">Descripción</th>
                        <th class="col-num">Usuarios</th>
                        <th class="col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="rolesBody">
                    <tr><td colspan="5" class="admin-empty">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</section>

<!-- Modal: editar descripción de rol -->
<div id="rolModal" class="admin-overlay d-none" role="dialog" aria-modal="true">
    <div class="admin-modal">
        <div class="admin-modal-head">
            <h2 class="admin-modal-title"><i class="bi bi-pencil"></i> Editar rol</h2>
            <button class="admin-modal-close" id="rolModalClose"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="rolForm" novalidate>
            <input type="hidden" id="rolId">
            <div class="admin-modal-body">
                <div class="admin-field">
                    <label class="admin-label">Nombre del rol</label>
                    <input class="admin-input" id="rolName" type="text" readonly style="opacity:.6;cursor:default">
                </div>
                <div class="admin-field">
                    <label class="admin-label" for="rolDesc">Descripción</label>
                    <input class="admin-input" id="rolDesc" type="text" placeholder="Descripción del rol…">
                </div>
                <div id="rolError" class="admin-alert admin-alert-error d-none"></div>
            </div>
            <div class="admin-modal-foot">
                <button type="button" class="btn-secondary" id="rolModalCancel">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>
