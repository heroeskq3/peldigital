<div id="padronModal" class="modal-overlay d-none">
    <div class="modal-card">
        <header class="modal-head">
            <div>
                <h3 id="padronTitulo" class="modal-title"></h3>
                <span id="padronSub" class="muted small"></span>
            </div>
            <button id="padronClose" class="icon-only" aria-label="Cerrar" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </header>
        <div class="modal-tools">
            <div class="search-wrap modal-search">
                <i class="bi bi-search search-ico"></i>
                <input id="padronBuscar" type="text" class="field search-input"
                       placeholder="Filtrar por cédula, nombre, apellidos o junta..." autocomplete="off">
            </div>
            <select id="padronPageSize" class="field page-size">
                <option value="25">25 / pág.</option>
                <option value="50">50 / pág.</option>
                <option value="100">100 / pág.</option>
            </select>
            <button id="btnExport" class="btn-export" type="button" title="Exportar a Excel">
                <i class="bi bi-file-earmark-spreadsheet"></i> Excel
            </button>
        </div>
        <div class="table-wrap">
            <table class="dt">
                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>Vence cédula</th>
                        <th>Junta</th>
                        <th>Provincia</th>
                        <th>Cantón</th>
                        <th>Distrito</th>
                    </tr>
                </thead>
                <tbody id="padronBody"></tbody>
            </table>
        </div>
        <footer class="modal-foot">
            <span id="padronInfo" class="muted small"></span>
            <div class="pager">
                <button id="pgFirst" class="pg-btn" title="Primera">«</button>
                <button id="pgPrev" class="pg-btn" title="Anterior">‹</button>
                <span id="pgNow" class="muted small"></span>
                <button id="pgNext" class="pg-btn" title="Siguiente">›</button>
                <button id="pgLast" class="pg-btn" title="Última">»</button>
            </div>
        </footer>
    </div>
</div>
