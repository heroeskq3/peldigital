<section id="adminBitacora" class="admin-section">

    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-journal-text"></i> Bitácora de actividad</h1>
            <p class="admin-page-sub">Registro de eventos del sistema — solo lectura</p>
        </div>
        <select id="bitSize" class="admin-select">
            <option value="25">25 / pág.</option>
            <option value="50" selected>50 / pág.</option>
            <option value="100">100 / pág.</option>
        </select>
    </div>

    <div class="admin-card">
        <div class="admin-toolbar">
            <input id="bitQ" type="search" class="admin-search" placeholder="Buscar acción o descripción…">
            <select id="bitUserFilter" class="admin-select">
                <option value="">Todos los usuarios</option>
            </select>
            <select id="bitActionFilter" class="admin-select">
                <option value="">Todas las acciones</option>
            </select>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody id="bitBody">
                    <tr><td colspan="5" class="admin-empty">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="admin-pag">
            <button id="bitFirst" class="admin-pag-btn" title="Primera">«</button>
            <button id="bitPrev"  class="admin-pag-btn" title="Anterior">‹</button>
            <span   id="bitPages" class="admin-pag-info">—</span>
            <button id="bitNext"  class="admin-pag-btn" title="Siguiente">›</button>
            <button id="bitLast"  class="admin-pag-btn" title="Última">»</button>
            <span   id="bitTotal" class="admin-pag-total"></span>
        </div>
    </div>

</section>
