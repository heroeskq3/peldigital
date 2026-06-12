<section id="adminDocumentacion" class="admin-section">

    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-journal-code"></i> Documentación técnica</h1>
            <p class="admin-page-sub">Data Warehouse · Fuentes de datos · ETL · Análisis de reportes</p>
        </div>
        <a id="docsBtnEdit" class="btn-secondary d-none" href="#" target="_blank" title="Ver archivo fuente">
            <i class="bi bi-pencil-square"></i> Ver fuente .md
        </a>
    </div>

    <!-- Tabs -->
    <div class="rep-tabs" id="docsTabs" style="margin-bottom:1.25rem">
        <button class="rep-tab active" type="button" data-docs-tab="datawarehouse">
            <i class="bi bi-database"></i> Data Warehouse
        </button>
        <button class="rep-tab" type="button" data-docs-tab="fuentes-datos">
            <i class="bi bi-cloud-download"></i> Fuentes de datos
        </button>
        <button class="rep-tab" type="button" data-docs-tab="etl">
            <i class="bi bi-arrow-repeat"></i> ETL &amp; Pipelines
        </button>
        <button class="rep-tab" type="button" data-docs-tab="reportes">
            <i class="bi bi-bar-chart-line"></i> Análisis de reportes
        </button>
        <button class="rep-tab" type="button" data-docs-tab="topologia">
            <i class="bi bi-diagram-3"></i> Topología
        </button>
        <button class="rep-tab" type="button" data-docs-tab="changelog">
            <i class="bi bi-clock-history"></i> Changelog
        </button>
    </div>

    <!-- Contenido renderizado -->
    <div class="admin-card" style="padding:1.5rem 2rem;min-height:400px">
        <div id="docsLoading" style="display:flex;align-items:center;gap:.6rem;color:var(--text-muted);font-size:.88rem">
            <i class="bi bi-hourglass-split"></i> Cargando…
        </div>
        <div id="docsContent" class="docs-md d-none"></div>
        <div id="docsError" class="d-none" style="color:var(--color-danger,#e24b4a);font-size:.88rem">
            <i class="bi bi-exclamation-circle"></i> <span id="docsErrorMsg"></span>
        </div>
    </div>

    <p class="muted" style="font-size:.72rem;margin:.5rem 0 0">
        <i class="bi bi-info-circle"></i>
        Los archivos fuente están en <code>docs/</code> · Se actualizan con cada sprint.
    </p>

</section>
