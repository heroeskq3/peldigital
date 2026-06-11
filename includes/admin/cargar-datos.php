<section id="adminCargarDatos" class="admin-section">

    <div class="admin-page-head">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-cloud-upload"></i> Fuentes de datos</h1>
            <p class="admin-page-sub">Estado de las tablas principales y conteos actuales</p>
        </div>
        <button class="btn-secondary" id="btnRefreshDatos" type="button">
            <i class="bi bi-arrow-clockwise"></i> Actualizar
        </button>
    </div>

    <!-- Stats grandes -->
    <div class="admin-stats" id="datosStats">
        <div class="admin-stat">
            <div class="admin-stat-lbl">Electorado</div>
            <div class="admin-stat-val blue" id="datoVoters">—</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-lbl">Juntas (nacionales)</div>
            <div class="admin-stat-val" id="datoJuntas">—</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-lbl">Distritos</div>
            <div class="admin-stat-val" id="datoDistricts">—</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-lbl">Usuarios</div>
            <div class="admin-stat-val" id="datoUsers">—</div>
        </div>
    </div>

    <!-- Tabla detalle de fuentes -->
    <div class="admin-card">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Tabla</th>
                        <th class="hide-mobile">Descripción</th>
                        <th class="col-num">Registros</th>
                        <th class="col-num hide-mobile">Tamaño</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="datosBody">
                    <tr><td colspan="5" class="admin-empty">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Instrucciones de carga -->
    <div class="admin-card" style="margin-top:1.25rem">
        <div class="admin-info-card-head" style="padding:.8rem 1rem;border-bottom:1px solid var(--border)">
            <i class="bi bi-terminal"></i> Comandos de importación (ejecutar en servidor)
        </div>
        <div style="padding:1rem 1.25rem">
            <pre style="margin:0;font-size:.78rem;color:var(--text-muted);line-height:1.7;overflow-x:auto;white-space:pre-wrap"># 1. Migraciones de BD
php scripts/migrate.php

# 2. Catálogo geográfico (prerequisito)
php scripts/import_distelec.php --file=raw/padron/distelec.txt

# 3. Padrón electoral (3.7 M registros — tarda ~10 min)
php scripts/import_padron.php --file=raw/padron/PADRON_COMPLETO.txt

# 4. Resultados electorales (en cualquier orden)
php scripts/import_resultados.php --json=raw/avr/avr2026.json --type=P --label="Presidencia 2026"
php scripts/import_resultados.php --json=raw/avr/avr2024.json --type=A --label="Municipal 2024"
php scripts/import_resultados.php --json=raw/avr/avr2022.json --type=P --label="Presidencial 2022 1ra"
php scripts/import_resultados.php --json=raw/avr/avr2022_ii.json --type=P --label="Presidencial 2022 2da"

# 5. Enriquecer sexo por nombre
php scripts/enrich_sexo.php --batch=0</pre>
        </div>
    </div>

</section>
