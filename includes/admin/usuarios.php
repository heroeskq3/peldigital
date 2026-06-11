<section id="adminUsuarios" class="admin-section">

    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-people"></i> Usuarios</h1>
            <p class="admin-page-sub">Gestión de cuentas de acceso al sistema</p>
        </div>
        <button class="btn-primary" id="btnNuevoUsuario" type="button">
            <i class="bi bi-plus-lg"></i> Nuevo usuario
        </button>
    </div>

    <div class="admin-card">
        <div class="admin-toolbar">
            <input id="usuQ" type="search" class="admin-search" placeholder="Buscar por nombre o email…">
            <select id="usuRoleFilter" class="admin-select">
                <option value="">Todos los roles</option>
            </select>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="col-num hide-mobile">#</th>
                        <th>Nombre</th>
                        <th class="hide-mobile">Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th class="hide-mobile">Alta</th>
                        <th class="col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="usuBody">
                    <tr><td colspan="7" class="admin-empty">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="admin-pag">
            <button id="usuFirst" class="admin-pag-btn" title="Primera">«</button>
            <button id="usuPrev"  class="admin-pag-btn" title="Anterior">‹</button>
            <span   id="usuPages" class="admin-pag-info">—</span>
            <button id="usuNext"  class="admin-pag-btn" title="Siguiente">›</button>
            <button id="usuLast"  class="admin-pag-btn" title="Última">»</button>
            <span   id="usuTotal" class="admin-pag-total"></span>
        </div>
    </div>

</section>

<!-- Modal: crear / editar usuario -->
<div id="usuModal" class="admin-overlay d-none" role="dialog" aria-modal="true">
    <div class="admin-modal">
        <div class="admin-modal-head">
            <h2 class="admin-modal-title" id="usuModalTitle">
                <i class="bi bi-person-plus"></i> Nuevo usuario
            </h2>
            <button class="admin-modal-close" id="usuModalClose" aria-label="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form id="usuForm" novalidate>
            <input type="hidden" id="usuId" value="">
            <div class="admin-modal-body">
                <div class="admin-field">
                    <label class="admin-label" for="usuName">Nombre completo</label>
                    <input class="admin-input" id="usuName" type="text" placeholder="Ej. Ana López" autocomplete="off">
                </div>
                <div class="admin-field">
                    <label class="admin-label" for="usuEmail">Correo electrónico</label>
                    <input class="admin-input" id="usuEmail" type="email" placeholder="correo@ejemplo.com" autocomplete="off">
                </div>
                <div class="admin-field">
                    <label class="admin-label" for="usuRole">Rol</label>
                    <select class="admin-select-field" id="usuRole"></select>
                </div>
                <div class="admin-field">
                    <label class="admin-label" for="usuPass">
                        Contraseña <span id="usuPassHint" class="admin-field-hint">(dejar vacío para no cambiar)</span>
                    </label>
                    <input class="admin-input" id="usuPass" type="password" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                </div>
                <div id="usuError" class="admin-alert admin-alert-error d-none"></div>
            </div>
            <div class="admin-modal-foot">
                <button type="button" class="btn-secondary" id="usuModalCancel">Cancelar</button>
                <button type="submit" class="btn-primary" id="usuSubmit">
                    <i class="bi bi-check-lg"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: confirmar eliminación -->
<div id="usuDeleteModal" class="admin-overlay d-none" role="dialog" aria-modal="true">
    <div class="admin-modal admin-confirm">
        <div class="admin-modal-head">
            <h2 class="admin-modal-title"><i class="bi bi-trash3"></i> Eliminar usuario</h2>
            <button class="admin-modal-close" id="usuDeleteClose"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="admin-confirm-body">
            ¿Eliminar a <strong id="usuDeleteName"></strong>? Esta acción no se puede deshacer.
        </div>
        <div class="admin-modal-foot">
            <button class="btn-secondary" id="usuDeleteCancel">Cancelar</button>
            <button class="btn-icon btn-icon-red" id="usuDeleteConfirm">
                <i class="bi bi-trash3"></i> Eliminar
            </button>
        </div>
    </div>
</div>
