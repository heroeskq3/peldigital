<div id="bitacoraModal" class="modal-overlay d-none">
    <div class="modal-card">
        <header class="modal-head">
            <div>
                <h3 class="modal-title"><i class="bi bi-journal-text"></i> Bitácora de actividad</h3>
                <span id="bitacoraSub" class="muted small">Accesos e interacciones registradas</span>
            </div>
            <button id="bitacoraClose" class="icon-only" aria-label="Cerrar" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </header>
        <div class="modal-tools">
            <div class="search-wrap modal-search">
                <i class="bi bi-search search-ico"></i>
                <input id="bitacoraBuscar" type="text" class="field search-input"
                       placeholder="Filtrar por usuario, tipo, IP o detalle..." autocomplete="off">
            </div>
            <button id="bitacoraRefresh" class="btn-export" type="button" title="Actualizar">
                <i class="bi bi-arrow-clockwise"></i> Actualizar
            </button>
        </div>
        <div class="table-wrap">
            <table class="dt">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Usuario</th>
                        <th>IP</th>
                        <th>Tipo</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody id="bitacoraBody"></tbody>
            </table>
        </div>
        <footer class="modal-foot">
            <span id="bitacoraInfo" class="muted small"></span>
        </footer>
    </div>
</div>
