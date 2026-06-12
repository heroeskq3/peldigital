<section id="adminExplorador" class="admin-section">

    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-table"></i> Explorador DW</h1>
            <p class="admin-page-sub">Consulta y filtrado en tiempo real de las tablas del Data Warehouse · Solo lectura</p>
        </div>
        <button class="btn-secondary" id="expBtnRefresh" type="button" title="Recargar lista de tablas">
            <i class="bi bi-arrow-clockwise"></i> Actualizar
        </button>
    </div>

    <!-- Selector de tabla -->
    <div class="admin-card" style="padding:1rem 1.25rem">
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
            <label for="expTablaSelect" style="font-size:.85rem;font-weight:600;white-space:nowrap">
                <i class="bi bi-database" style="margin-right:.3rem;opacity:.6"></i>Tabla:
            </label>
            <select id="expTablaSelect" class="admin-select" style="min-width:240px;font-size:.85rem">
                <option value="">— Seleccionar tabla —</option>
            </select>
            <span id="expTablaInfo" style="font-size:.78rem;color:var(--text-muted)"></span>
        </div>
    </div>

    <!-- Filtros dinámicos -->
    <div id="expFiltrosWrap" class="admin-card d-none" style="padding:1rem 1.25rem">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap;gap:.5rem">
            <span style="font-size:.82rem;font-weight:600;color:var(--text-muted)">
                <i class="bi bi-funnel"></i> Filtros
            </span>
            <div style="display:flex;gap:.5rem">
                <button id="expBtnLimpiar" class="btn-secondary" type="button">
                    <i class="bi bi-x-circle"></i> Limpiar
                </button>
                <button id="expBtnAplicar" class="btn-primary" type="button">
                    <i class="bi bi-search"></i> Aplicar
                </button>
            </div>
        </div>
        <div id="expFiltrosGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:.6rem .8rem"></div>
    </div>

    <!-- Barra superior de resultados -->
    <div id="expResultsBar" class="d-none" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:.5rem">
        <div style="display:flex;align-items:center;gap:.75rem">
            <span id="expTotal" style="font-size:.82rem;color:var(--text-muted)"></span>
            <select id="expSize" class="admin-select" style="font-size:.78rem">
                <option value="25">25 / pág.</option>
                <option value="50" selected>50 / pág.</option>
                <option value="100">100 / pág.</option>
                <option value="200">200 / pág.</option>
            </select>
        </div>
        <div class="admin-pag" style="margin:0">
            <button id="expFirst" class="admin-pag-btn" title="Primera">«</button>
            <button id="expPrev"  class="admin-pag-btn" title="Anterior">‹</button>
            <span   id="expPages" class="admin-pag-info">—</span>
            <button id="expNext"  class="admin-pag-btn" title="Siguiente">›</button>
            <button id="expLast"  class="admin-pag-btn" title="Última">»</button>
        </div>
    </div>

    <!-- Tabla de resultados -->
    <div id="expResultsWrap" class="admin-card d-none" style="padding:0;overflow-x:auto">
        <table class="admin-table" id="expTable" style="min-width:100%">
            <thead id="expThead"></thead>
            <tbody id="expTbody">
                <tr><td class="admin-empty">Selecciona una tabla para comenzar.</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Paginación inferior -->
    <div id="expResultsBar2" class="d-none" style="display:flex;justify-content:flex-end;margin-top:.5rem">
        <div class="admin-pag" style="margin:0">
            <button id="expFirst2" class="admin-pag-btn" title="Primera">«</button>
            <button id="expPrev2"  class="admin-pag-btn" title="Anterior">‹</button>
            <span   id="expPages2" class="admin-pag-info">—</span>
            <button id="expNext2"  class="admin-pag-btn" title="Siguiente">›</button>
            <button id="expLast2"  class="admin-pag-btn" title="Última">»</button>
        </div>
    </div>

</section>
