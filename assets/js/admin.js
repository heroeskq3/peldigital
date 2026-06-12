(function () {
    'use strict';

    // ── Helpers ──────────────────────────────────────────────────────────────

    const $ = id => document.getElementById(id);
    const fmt = n => (n < 0 ? 'N/D' : Number(n).toLocaleString('es-CR'));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function api(url, opts = {}) {
        const method = (opts.method || 'GET').toUpperCase();
        if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
            opts.headers = Object.assign({}, opts.headers || {}, { 'X-CSRF-Token': csrf() });
        }
        const res = await fetch(url, opts);
        if (!res.ok) {
            const e = await res.json().catch(() => ({ error: 'Error ' + res.status }));
            throw new Error(e.error || 'Error del servidor');
        }
        return res.json();
    }

    function showModal(id) { $(id).classList.remove('d-none'); }
    function hideModal(id) { $(id).classList.add('d-none'); }

    function debounce(fn, ms = 350) {
        let t;
        return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
    }

    // Close modal clicking overlay
    document.querySelectorAll('.admin-overlay').forEach(ov => {
        ov.addEventListener('click', e => {
            if (e.target === ov) ov.classList.add('d-none');
        });
    });

    // ── Menú de acciones (tres puntos) ───────────────────────────────────────

    function menuAcciones(items) {
        const lis = items.map(it => {
            if (it.separator) return '<li class="crud-sep"></li>';
            const dis = it.disabled ? 'disabled' : '';
            const cls = it.danger ? 'crud-item--danger' : '';
            const icon = it.icon ? `<i class="bi ${it.icon}"></i>` : '';
            return `<li><button class="crud-item ${cls}" ${dis} onclick="${it.fn};closeCrudMenu(this)">${icon}${it.label}</button></li>`;
        }).join('');
        return `<div class="crud-actions"><button class="crud-dots" type="button" data-crud-toggle><i class="bi bi-three-dots-vertical"></i></button><ul class="crud-menu">${lis}</ul></div>`;
    }

    // Toggle al hacer clic en ⋮ — cierra otros menús abiertos
    document.addEventListener('click', e => {
        const toggle = e.target.closest('[data-crud-toggle]');
        if (toggle) {
            e.stopPropagation();
            const wrap = toggle.closest('.crud-actions');
            const wasOpen = wrap.classList.contains('open');
            document.querySelectorAll('.crud-actions.open').forEach(el => el.classList.remove('open'));
            if (!wasOpen) wrap.classList.add('open');
            return;
        }
        if (!e.target.closest('.crud-actions')) {
            document.querySelectorAll('.crud-actions.open').forEach(el => el.classList.remove('open'));
        }
    });

    window.closeCrudMenu = btn => btn.closest('.crud-actions')?.classList.remove('open');

    // ── Section routing ──────────────────────────────────────────────────────

    const sections = {
        usuarios:     { init: initUsuarios,     loaded: false },
        roles:        { init: initRoles,         loaded: false },
        reportes:     { init: initReportes,      loaded: false },
        bitacora:     { init: initBitacora,      loaded: false },
        configuracion:{ init: initConfiguracion, loaded: false },
        'cargar-datos':{ init: initDatos,        loaded: false },
        pipelines:    { init: initPipelines,     loaded: false },
        etl:          { init: initETL,           loaded: false },
    };

    function activarSeccion(slug) {
        // Marcar item activo en el dropdown Admin del header
        document.querySelectorAll('[data-admin]').forEach(el => {
            el.classList.toggle('report-active', el.dataset.admin === slug);
        });

        // Mostrar sección correspondiente
        document.querySelectorAll('.admin-section').forEach(el => el.classList.remove('active'));
        const sectionEl = document.getElementById('admin' + slug.replace(/-([a-z])/g, (_, c) => c.toUpperCase())
            .replace(/^./, c => c.toUpperCase()));
        if (sectionEl) sectionEl.classList.add('active');

        history.replaceState(null, '', '#' + slug);
        window.navCerrarTodo?.();

        const s = sections[slug];
        if (s && !s.loaded) { s.init(); s.loaded = true; }
    }

    // Interceptar links [data-admin] del header — navegar sin reload al cambiar de sección
    document.querySelectorAll('[data-admin]').forEach(el => {
        el.addEventListener('click', e => {
            e.preventDefault();
            activarSeccion(el.dataset.admin);
        });
    });

    // ── State (declared before activarSeccion to avoid TDZ) ─────────────────
    const usu = { page: 1, pages: 1, editId: null, roles: [] };
    const bit = { page: 1, pages: 1 };

    // Initial section from hash
    const hash = location.hash.slice(1);
    const initialSection = sections[hash] ? hash : 'usuarios';
    activarSeccion(initialSection);

    // ── USUARIOS ─────────────────────────────────────────────────────────────

    function initUsuarios() {
        cargarRolesSelect();
        cargarUsuarios();

        $('btnNuevoUsuario').addEventListener('click', () => abrirModalUsu(null));
        $('usuModalClose').addEventListener('click',  () => hideModal('usuModal'));
        $('usuModalCancel').addEventListener('click', () => hideModal('usuModal'));
        $('usuDeleteClose').addEventListener('click',  () => hideModal('usuDeleteModal'));
        $('usuDeleteCancel').addEventListener('click', () => hideModal('usuDeleteModal'));
        $('usuForm').addEventListener('submit', submitUsuario);
        $('usuFirst').addEventListener('click', () => { usu.page = 1;         cargarUsuarios(); });
        $('usuPrev').addEventListener('click',  () => { usu.page--;            cargarUsuarios(); });
        $('usuNext').addEventListener('click',  () => { usu.page++;            cargarUsuarios(); });
        $('usuLast').addEventListener('click',  () => { usu.page = usu.pages;  cargarUsuarios(); });
        $('usuQ').addEventListener('input', debounce(() => { usu.page = 1; cargarUsuarios(); }));
        $('usuRoleFilter').addEventListener('change', () => { usu.page = 1; cargarUsuarios(); });
    }

    async function cargarRolesSelect() {
        try {
            const d = await api('api/admin/roles.php');
            usu.roles = d.rows;
            // Fill role filter
            const rf = $('usuRoleFilter');
            d.rows.forEach(r => {
                const o = document.createElement('option');
                o.value = r.id; o.textContent = r.name;
                rf.appendChild(o);
            });
            // Fill modal select
            const rs = $('usuRole');
            rs.innerHTML = '<option value="">Selecciona un rol…</option>';
            d.rows.forEach(r => {
                const o = document.createElement('option');
                o.value = r.id; o.textContent = r.name;
                rs.appendChild(o);
            });
        } catch (e) { console.error(e); }
    }

    async function cargarUsuarios() {
        try {
            const q    = ($('usuQ') || {value:''}).value;
            const role = ($('usuRoleFilter') || {value:''}).value;
            const url  = `api/admin/usuarios.php?action=list&page=${usu.page}&q=${encodeURIComponent(q)}&role_id=${role}`;
            const d = await api(url);
            usu.pages = d.pages || 1;
            renderUsuarios(d);
        } catch (e) {
            console.error('[admin] cargarUsuarios error:', e.message, e.stack);
            const b = $('usuBody');
            if (b) b.innerHTML = `<tr><td colspan="7" class="admin-empty">${e.message}</td></tr>`;
        }
    }

    function renderUsuarios(d) {
        $('usuPages').textContent = `Pág. ${d.page} / ${d.pages || 1}`;
        $('usuTotal').textContent = `${fmt(d.total)} usuario(s)`;
        $('usuFirst').disabled = d.page <= 1;
        $('usuPrev').disabled  = d.page <= 1;
        $('usuNext').disabled  = d.page >= (d.pages || 1);
        $('usuLast').disabled  = d.page >= (d.pages || 1);

        if (!d.rows.length) {
            $('usuBody').innerHTML = '<tr><td colspan="7" class="admin-empty">Sin resultados</td></tr>';
            return;
        }

        $('usuBody').innerHTML = d.rows.map((u, i) => {
            const badge = u.active == 1
                ? '<span class="badge badge-green"><i class="bi bi-check-circle-fill"></i> Activo</span>'
                : '<span class="badge badge-gray"><i class="bi bi-dash-circle"></i> Inactivo</span>';
            const fecha = u.created_at ? u.created_at.slice(0, 10) : '—';
            return `<tr>
                <td class="col-num hide-mobile" style="color:var(--text-muted)">${i + 1 + (d.page - 1) * 25}</td>
                <td style="font-weight:500">${esc(u.name)}</td>
                <td class="hide-mobile" style="color:var(--text-muted)">${esc(u.email)}</td>
                <td><span class="badge badge-blue">${esc(u.role_name || '—')}</span></td>
                <td>${badge}</td>
                <td class="hide-mobile" style="color:var(--text-muted);font-size:.8rem">${fecha}</td>
                <td class="col-actions">${menuAcciones([
                        { icon:'bi-pencil',       label:'Editar',    fn:`adminEditUsu(${u.id})` },
                        { icon: u.active==1 ? 'bi-pause-circle':'bi-play-circle',
                          label: u.active==1 ? 'Desactivar':'Activar',
                          fn:`adminToggleUsu(${u.id},this)` },
                        { separator: true },
                        { icon:'bi-trash3',       label:'Eliminar',  fn:`adminDeleteUsu(${u.id},'${esc(u.name)}')`, danger:true },
                    ])}</td>
            </tr>`;
        }).join('');
    }

    function abrirModalUsu(row) {
        $('usuError').classList.add('d-none');
        if (row) {
            usu.editId = row.id;
            $('usuModalTitle').innerHTML = '<i class="bi bi-pencil"></i> Editar usuario';
            $('usuId').value    = row.id;
            $('usuName').value  = row.name;
            $('usuEmail').value = row.email;
            $('usuRole').value  = row.role_id;
            $('usuPass').value  = '';
            $('usuPassHint').style.display = '';
        } else {
            usu.editId = null;
            $('usuModalTitle').innerHTML = '<i class="bi bi-person-plus"></i> Nuevo usuario';
            $('usuId').value = '';
            $('usuForm').reset();
            $('usuPassHint').style.display = 'none';
        }
        showModal('usuModal');
        $('usuName').focus();
    }

    async function submitUsuario(e) {
        e.preventDefault();
        const id   = $('usuId').value;
        const body = {
            action:   id ? 'update' : 'create',
            id:       id || undefined,
            name:     $('usuName').value,
            email:    $('usuEmail').value,
            role_id:  $('usuRole').value,
            password: $('usuPass').value,
        };
        try {
            $('usuSubmit').disabled = true;
            await api('api/admin/usuarios.php?action=' + body.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            hideModal('usuModal');
            usu.page = 1;
            cargarUsuarios();
        } catch (err) {
            const el = $('usuError');
            el.innerHTML = '<i class="bi bi-exclamation-circle"></i> ' + esc(err.message);
            el.classList.remove('d-none');
        } finally {
            $('usuSubmit').disabled = false;
        }
    }

    window.adminEditUsu = function (id) {
        const rows = Array.from($('usuBody').querySelectorAll('tr'));
        // Re-fetch user from server
        api(`api/admin/usuarios.php?action=list&q=&page=1&size=100`)
            .then(d => {
                const u = d.rows.find(r => r.id == id);
                if (u) abrirModalUsu(u);
            });
    };

    window.adminToggleUsu = function (id) {
        api('api/admin/usuarios.php?action=toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle', id }),
        }).then(() => cargarUsuarios()).catch(e => alert(e.message));
    };

    let pendingDeleteId = null;
    window.adminDeleteUsu = function (id, name) {
        pendingDeleteId = id;
        $('usuDeleteName').textContent = name;
        showModal('usuDeleteModal');
    };

    $('usuDeleteConfirm') && $('usuDeleteConfirm').addEventListener('click', () => {
        if (!pendingDeleteId) return;
        api('api/admin/usuarios.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: pendingDeleteId }),
        }).then(() => {
            hideModal('usuDeleteModal');
            cargarUsuarios();
        }).catch(e => alert(e.message));
    });

    // ── ROLES ────────────────────────────────────────────────────────────────

    function initRoles() {
        cargarRoles();
        $('rolModalClose').addEventListener('click',  () => hideModal('rolModal'));
        $('rolModalCancel').addEventListener('click', () => hideModal('rolModal'));
        $('rolForm').addEventListener('submit', submitRol);
    }

    async function cargarRoles() {
        try {
            const d = await api('api/admin/roles.php');
            renderRoles(d.rows);
        } catch (e) {
            $('rolesBody').innerHTML = `<tr><td colspan="5" class="admin-empty">${e.message}</td></tr>`;
        }
    }

    function renderRoles(rows) {
        $('rolesBody').innerHTML = rows.map(r => `
            <tr>
                <td class="col-num hide-mobile" style="color:var(--text-muted)">${r.id}</td>
                <td style="font-weight:600">${esc(r.name)}</td>
                <td class="hide-mobile" style="color:var(--text-muted)">${esc(r.description || '—')}</td>
                <td class="col-num"><span class="badge badge-blue">${r.user_count}</span></td>
                <td class="col-actions">${menuAcciones([
                        { icon:'bi-pencil', label:'Editar', fn:`adminEditRol(${r.id},'${esc(r.name)}','${esc(r.description || '')}')` },
                    ])}</td>
            </tr>
        `).join('');
    }

    window.adminEditRol = function (id, name, desc) {
        $('rolId').value   = id;
        $('rolName').value = name;
        $('rolDesc').value = desc;
        $('rolError').classList.add('d-none');
        showModal('rolModal');
        $('rolDesc').focus();
    };

    async function submitRol(e) {
        e.preventDefault();
        try {
            await api('api/admin/roles.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: $('rolId').value, description: $('rolDesc').value }),
            });
            hideModal('rolModal');
            cargarRoles();
        } catch (err) {
            const el = $('rolError');
            el.innerHTML = '<i class="bi bi-exclamation-circle"></i> ' + esc(err.message);
            el.classList.remove('d-none');
        }
    }

    // ── BITÁCORA ─────────────────────────────────────────────────────────────

    function initBitacora() {
        cargarBitacora();
        $('bitFirst').addEventListener('click', () => { bit.page = 1;          cargarBitacora(); });
        $('bitPrev').addEventListener('click',  () => { bit.page--;             cargarBitacora(); });
        $('bitNext').addEventListener('click',  () => { bit.page++;             cargarBitacora(); });
        $('bitLast').addEventListener('click',  () => { bit.page = bit.pages;   cargarBitacora(); });
        $('bitQ').addEventListener('input', debounce(() => { bit.page = 1; cargarBitacora(); }));
        $('bitUserFilter').addEventListener('change',   () => { bit.page = 1; cargarBitacora(); });
        $('bitActionFilter').addEventListener('change', () => { bit.page = 1; cargarBitacora(); });
        $('bitSize').addEventListener('change', () => { bit.page = 1; cargarBitacora(); });
    }

    async function cargarBitacora() {
        const q      = $('bitQ').value;
        const userId = $('bitUserFilter').value;
        const action = $('bitActionFilter').value;
        const size   = $('bitSize').value;
        const url = `api/admin/bitacora.php?page=${bit.page}&size=${size}&q=${encodeURIComponent(q)}&user_id=${userId}&action_filter=${encodeURIComponent(action)}`;
        try {
            const d = await api(url);
            bit.pages = d.pages || 1;

            // Populate filter dropdowns once
            if ($('bitUserFilter').options.length <= 1) {
                d.users.forEach(u => {
                    const o = document.createElement('option');
                    o.value = u.id; o.textContent = u.name;
                    $('bitUserFilter').appendChild(o);
                });
            }
            if ($('bitActionFilter').options.length <= 1) {
                d.actions.forEach(a => {
                    const o = document.createElement('option');
                    o.value = a; o.textContent = a;
                    $('bitActionFilter').appendChild(o);
                });
            }

            renderBitacora(d);
        } catch (e) {
            $('bitBody').innerHTML = `<tr><td colspan="5" class="admin-empty">${e.message}</td></tr>`;
        }
    }

    function renderBitacora(d) {
        $('bitPages').textContent = `Pág. ${d.page} / ${d.pages || 1}`;
        $('bitTotal').textContent = `${fmt(d.total)} registro(s)`;
        $('bitFirst').disabled = d.page <= 1;
        $('bitPrev').disabled  = d.page <= 1;
        $('bitNext').disabled  = d.page >= (d.pages || 1);
        $('bitLast').disabled  = d.page >= (d.pages || 1);

        if (!d.rows.length) {
            $('bitBody').innerHTML = '<tr><td colspan="5" class="admin-empty">Sin registros</td></tr>';
            return;
        }

        $('bitBody').innerHTML = d.rows.map(r => `
            <tr>
                <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap">${r.created_at || '—'}</td>
                <td class="hide-mobile" style="font-size:.82rem">${esc(r.user_name || 'Sistema')}</td>
                <td><span class="badge badge-blue">${esc(r.action || '')}</span></td>
                <td style="font-size:.82rem;color:var(--text-muted)">${esc(r.description || '—')}</td>
                <td class="hide-mobile" style="font-size:.78rem;color:var(--text-muted);font-family:monospace">${esc(r.ip_address || '—')}</td>
            </tr>
        `).join('');
    }

    // ── CONFIGURACIÓN ────────────────────────────────────────────────────────

    function initConfiguracion() {
        cargarConfiguracion();
        $('btnRefreshConfig').addEventListener('click', cargarConfiguracion);
    }

    async function cargarConfiguracion() {
        try {
            const d = await api('api/admin/datos.php');
            renderConfiguracion(d);
        } catch (e) {
            $('configGrid').innerHTML = `<div class="admin-info-card"><div class="admin-info-card-head">${e.message}</div></div>`;
        }
    }

    function renderConfiguracion(d) {
        const src      = k => d.sources.find(s => s.key === k);
        const voters   = src('voters');
        const enriched = src('voter_enrichments');
        const polling  = src('polling');
        const elDist   = src('electoral_districts');
        const results  = src('results');
        const sjrv     = src('summary_jrv');
        const users    = src('users');
        const audit    = src('audit_logs');
        const reports  = src('reports');
        const repCats  = src('report_categories');
        const mig      = src('migrations');
        const pctVoters = voters?.count > 0 ? ('<span style="color:var(--success-color)">' + fmt(voters.count) + '</span>') : '<span style="color:var(--text-muted)">0</span>';

        $('configGrid').innerHTML = `
            <div class="admin-info-card">
                <div class="admin-info-card-head"><i class="bi bi-server"></i> Entorno</div>
                <ul class="admin-info-rows">
                    <li class="admin-info-row"><span class="admin-info-key">Servidor</span><span class="admin-info-val" style="font-size:.8rem">${esc(d.server)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">BD sistema</span><span class="admin-info-val"><code style="font-size:.75rem">${esc(d.sys_db)}</code></span></li>
                    <li class="admin-info-row"><span class="admin-info-key">BD datos (DW)</span><span class="admin-info-val"><code style="font-size:.75rem">${esc(d.dw_db)}</code></span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Tamaño DW</span><span class="admin-info-val">${d.dw_tables != null ? d.dw_tables + ' MB' : 'N/D'}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Fecha servidor</span><span class="admin-info-val">${esc(d.now)}</span></li>
                </ul>
            </div>
            <div class="admin-info-card">
                <div class="admin-info-card-head"><i class="bi bi-database-fill"></i> Padrón electoral (DW)</div>
                <ul class="admin-info-rows">
                    <li class="admin-info-row"><span class="admin-info-key">Electores (voters)</span><span class="admin-info-val">${pctVoters}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Juntas únicas</span><span class="admin-info-val">${fmt(d.juntas)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Centros votación</span><span class="admin-info-val">${fmt(polling?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Circunscripciones</span><span class="admin-info-val">${fmt(elDist?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Enriquecimientos sexo</span><span class="admin-info-val">${fmt(enriched?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Resultados electorales</span><span class="admin-info-val">${fmt(results?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Tamaño voters</span><span class="admin-info-val">${voters?.size_mb != null ? voters.size_mb + ' MB' : 'N/D'}</span></li>
                </ul>
            </div>
            <div class="admin-info-card">
                <div class="admin-info-card-head"><i class="bi bi-bar-chart-steps"></i> Resumen pre-agregado (Gold)</div>
                <ul class="admin-info-rows">
                    <li class="admin-info-row"><span class="admin-info-key">JRVs en summary_jrv</span><span class="admin-info-val">${fmt(sjrv?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Provincias</span><span class="admin-info-val">${fmt(src('provinces')?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Cantones</span><span class="admin-info-val">${fmt(src('cantons')?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Distritos</span><span class="admin-info-val">${fmt(src('districts')?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Partidos</span><span class="admin-info-val">${fmt(src('parties')?.count)}</span></li>
                </ul>
            </div>
            <div class="admin-info-card">
                <div class="admin-info-card-head"><i class="bi bi-people"></i> Sistema (pel_electoral)</div>
                <ul class="admin-info-rows">
                    <li class="admin-info-row"><span class="admin-info-key">Usuarios</span><span class="admin-info-val">${fmt(users?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Bitácora (eventos)</span><span class="admin-info-val">${fmt(audit?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Reportes</span><span class="admin-info-val">${fmt(reports?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Categorías de reportes</span><span class="admin-info-val">${fmt(repCats?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Migraciones sistema</span><span class="admin-info-val">${fmt(mig?.count)}</span></li>
                </ul>
            </div>
        `;
    }

    // ── CARGAR DATOS ─────────────────────────────────────────────────────────

    function initDatos() {
        cargarDatos();
        $('btnRefreshDatos').addEventListener('click', cargarDatos);
    }

    async function cargarDatos() {
        try {
            const d = await api('api/admin/datos.php');
            renderDatos(d);
        } catch (e) {
            $('datosBody').innerHTML = `<tr><td colspan="5" class="admin-empty">${e.message}</td></tr>`;
        }
    }

    function renderDatos(d) {
        const voters    = d.sources.find(s => s.key === 'voters');
        const districts = d.sources.find(s => s.key === 'districts');
        const users     = d.sources.find(s => s.key === 'users');

        $('datoVoters').textContent   = fmt(voters?.count ?? -1);
        $('datoJuntas').textContent   = fmt(d.juntas);
        $('datoDistricts').textContent = fmt(districts?.count ?? -1);
        $('datoUsers').textContent    = fmt(users?.count ?? -1);

        const labels = {
            voters:     'Padrón electoral completo TSE 2026',
            provinces:  'Catálogo de 7 provincias + exterior',
            cantons:    'Catálogo de cantones',
            districts:  'Catálogo de distritos con codelec',
            users:      'Usuarios de la plataforma',
            roles:      'Roles de acceso',
            audit_logs: 'Bitácora de actividad del sistema',
            reports:    'Catálogo de reportes configurados',
            migrations: 'Migraciones SQL aplicadas',
        };

        $('datosBody').innerHTML = d.sources.map(s => {
            const ok    = s.count > 0;
            const empty = s.count === 0;
            const badge = ok    ? '<span class="badge badge-green"><i class="bi bi-check-circle-fill"></i> Con datos</span>'
                        : empty ? '<span class="badge badge-amber"><i class="bi bi-exclamation-circle"></i> Vacía</span>'
                                : '<span class="badge badge-gray">N/D</span>';
            return `<tr>
                <td style="font-family:monospace;font-size:.8rem">${esc(s.table)}</td>
                <td class="hide-mobile" style="color:var(--text-muted);font-size:.83rem">${esc(labels[s.key] || s.label)}</td>
                <td class="col-num" style="font-weight:600">${s.count >= 0 ? fmt(s.count) : '—'}</td>
                <td class="col-num hide-mobile" style="color:var(--text-muted)">${s.size_mb != null ? s.size_mb + ' MB' : '—'}</td>
                <td>${badge}</td>
            </tr>`;
        }).join('');
    }

    // ── PIPELINES ────────────────────────────────────────────────────────────

    function initPipelines() {
        cargarPipelines();
        $('btnRefreshPipes').addEventListener('click', cargarPipelines);
    }

    async function cargarPipelines() {
        try {
            const d = await api('api/admin/pipelines.php');
            $('pipeTotal').textContent   = d.total;
            $('pipeApplied').textContent = d.applied;
            $('pipePending').textContent = d.pending;

            $('pipeList').innerHTML = d.migrations.map(m => {
                const icon  = m.orphaned  ? 'bi-question-circle text-amber'
                            : m.ran       ? 'bi-check-circle-fill'
                                          : 'bi-clock';
                const color = m.orphaned  ? '#f59e0b'
                            : m.ran       ? '#22c55e'
                                          : '#94a3b8';
                const date  = m.executed_at ? m.executed_at.slice(0, 16) : 'Pendiente';
                const note  = m.orphaned ? ' <span class="badge badge-amber" style="font-size:.65rem">huérfano</span>' : '';
                return `<li class="pipe-item">
                    <i class="bi ${icon} pipe-icon" style="color:${color}"></i>
                    <span class="pipe-name">${esc(m.file)}${note}</span>
                    <span class="pipe-date">${date}</span>
                </li>`;
            }).join('');
        } catch (e) {
            $('pipeList').innerHTML = `<li class="pipe-item" style="color:var(--text-muted)">${e.message}</li>`;
        }
    }


    // ── ETL PIPELINES ─────────────────────────────────────────────────────────

    const ETL_TIPO_LABEL = {
        dimension:       'Dimensión',
        hecho:           'Hecho',
        enriquecimiento: 'Enriquecimiento',
        agregado:        'Agregado Gold',
    };
    const ETL_TIPO_CLASS = {
        dimension:       'badge-gray',
        hecho:           'badge-blue',
        enriquecimiento: 'badge-purple',
        agregado:        'badge-green',
    };
    const ETL_ESTADO_ICON = {
        completado: '<i class="bi bi-check-circle-fill" style="color:#22c55e"></i>',
        parcial:    '<i class="bi bi-exclamation-circle-fill" style="color:#f59e0b"></i>',
        pendiente:  '<i class="bi bi-clock" style="color:#94a3b8"></i>',
        bloqueado:  '<i class="bi bi-lock-fill" style="color:#94a3b8"></i>',
        error:      '<i class="bi bi-x-circle-fill" style="color:#ef4444"></i>',
        nunca:      '<i class="bi bi-dash-circle" style="color:#94a3b8"></i>',
        failed:     '<i class="bi bi-x-circle-fill" style="color:#ef4444"></i>',
        processing: '<i class="bi bi-hourglass-split" style="color:#f59e0b"></i>',
    };
    const ETL_ESTADO_LABEL = {
        completado: 'Completado', completed: 'Completado',
        parcial:    'Parcial',
        pendiente:  'Pendiente',
        bloqueado:  'Bloqueado',
        error:      'Error',      failed: 'Error',
        nunca:      'Sin ejecutar',
        processing: 'Procesando',
    };

    function initETL() {
        cargarETL();
        $('btnRefreshEtl').addEventListener('click', cargarETL);
    }

    async function cargarETL() {
        $('etlBody').innerHTML = `<tr><td colspan="7" class="admin-empty"><i class="bi bi-hourglass-split"></i> Cargando…</td></tr>`;
        try {
            const d = await api('api/admin/etl.php');
            renderETL(d);
        } catch(e) {
            $('etlBody').innerHTML = `<tr><td colspan="7" class="admin-empty">${esc(e.message)}</td></tr>`;
        }
    }

    function renderETL(d) {
        const etls = d.etls || [];
        const ok   = etls.filter(e => e.estado === 'completado' || e.estado === 'completed').length;
        const pend = etls.filter(e => e.estado === 'pendiente'  || e.estado === 'nunca').length;
        const blk  = etls.filter(e => e.estado === 'bloqueado').length;
        $('etlTotal').textContent = etls.length;
        $('etlOk').textContent    = ok;
        $('etlPend').textContent  = pend;
        $('etlBlock').textContent = blk;

        $('etlBody').innerHTML = etls.map(e => {
            const icon  = ETL_ESTADO_ICON[e.estado]  || ETL_ESTADO_ICON['pendiente'];
            const label = ETL_ESTADO_LABEL[e.estado] || e.estado;
            const tipo  = ETL_TIPO_LABEL[e.tipo]  || e.tipo;
            const tipoCls = ETL_TIPO_CLASS[e.tipo] || 'badge-gray';
            const fecha = e.ultima_exec ? e.ultima_exec.slice(0, 16) : '—';
            const dur   = e.duracion || '—';
            const recs  = e.registros_ok > 0 ? fmt(e.registros_ok) : '—';
            const errs  = e.registros_err > 0 ? ` <span style="color:#ef4444;font-size:.75rem">(${e.registros_err} err)</span>` : '';
            return `<tr title="${esc(e.detalle || '')}">
                <td>
                    <div style="font-weight:500;font-size:.9rem">${esc(e.nombre)}</div>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px">${esc(e.descripcion)}</div>
                    <code style="font-size:.7rem;opacity:.55">${esc(e.script)}</code>
                </td>
                <td class="hide-mobile"><span class="badge ${tipoCls}" style="font-size:.68rem">${esc(tipo)}</span></td>
                <td class="hide-mobile" style="font-size:.78rem;color:var(--text-muted)">
                    <div>${esc(e.origen)}</div>
                    <div style="margin-top:2px">→ <span style="color:var(--color-text-primary)">${esc(e.destino)}</span></div>
                </td>
                <td style="white-space:nowrap">${icon} <span style="font-size:.82rem">${esc(label)}</span></td>
                <td class="col-num">${recs}${errs}</td>
                <td class="hide-mobile" style="font-size:.8rem;color:var(--text-muted)">${fecha}</td>
                <td class="hide-mobile" style="font-size:.8rem;color:var(--text-muted)">${dur}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="admin-empty">Sin ETLs</td></tr>';

        // Historial de runs
        let runsHtml = '';
        etls.filter(e => e.sync_runs && e.sync_runs.length > 0).forEach(e => {
            runsHtml += `<div style="margin-bottom:1rem">
                <div style="font-weight:500;font-size:.9rem;margin-bottom:.5rem">
                    <i class="bi bi-clock-history" style="margin-right:.4rem"></i>${esc(e.nombre)}
                </div>
                <div class="admin-card" style="padding:0;overflow-x:auto">
                <table class="admin-table">
                    <thead><tr>
                        <th>ID</th><th>Etiqueta / Archivo</th><th>Estado</th>
                        <th class="col-num">Registros</th><th>Inicio</th><th>Fin</th><th>Duración</th>
                    </tr></thead>
                    <tbody>`;
            e.sync_runs.forEach(r => {
                const icon2  = ETL_ESTADO_ICON[r.status]  || ETL_ESTADO_ICON['pendiente'];
                const label2 = ETL_ESTADO_LABEL[r.status] || r.status;
                const dur2   = (r.started_at && r.finished_at) ? calcDur(r.started_at, r.finished_at) : '—';
                const lbl    = r.election_label || r.zip_filename || r.filename || '—';
                const errs2  = r.records_error > 0 ? ` <span style="color:#ef4444;font-size:.75rem">(${r.records_error} err)</span>` : '';
                runsHtml += `<tr>
                    <td class="col-num muted">${r.id}</td>
                    <td style="font-size:.8rem">${esc(lbl)}</td>
                    <td style="white-space:nowrap">${icon2} <span style="font-size:.8rem">${esc(label2)}</span></td>
                    <td class="col-num">${fmt(r.records_ok)}${errs2}</td>
                    <td style="font-size:.78rem;white-space:nowrap">${(r.started_at||'').slice(0,16)}</td>
                    <td style="font-size:.78rem;white-space:nowrap">${(r.finished_at||'').slice(0,16) || '—'}</td>
                    <td style="font-size:.78rem">${dur2}</td>
                </tr>`;
            });
            runsHtml += '</tbody></table></div></div>';
        });

        // Import jobs
        if (d.import_jobs && d.import_jobs.length > 0) {
            runsHtml += `<div style="margin-bottom:1rem">
                <div style="font-weight:500;font-size:.9rem;margin-bottom:.5rem">
                    <i class="bi bi-cloud-upload" style="margin-right:.4rem"></i>Import Jobs (últimos 5)
                </div>
                <div class="admin-card" style="padding:0;overflow-x:auto">
                <table class="admin-table"><thead><tr>
                    <th>ID</th><th>Archivo</th><th>Estado</th><th class="col-num">Registros</th><th>Fecha</th>
                </tr></thead><tbody>`;
            d.import_jobs.forEach(j => {
                const icon3  = ETL_ESTADO_ICON[j.status]  || ETL_ESTADO_ICON['pendiente'];
                const label3 = ETL_ESTADO_LABEL[j.status] || j.status;
                runsHtml += `<tr>
                    <td class="col-num muted">${j.id}</td>
                    <td style="font-size:.8rem">${esc(j.filename)}</td>
                    <td>${icon3} <span style="font-size:.8rem">${esc(label3)}</span></td>
                    <td class="col-num">${fmt(j.records_ok)}</td>
                    <td style="font-size:.78rem">${(j.created_at||'').slice(0,16)}</td>
                </tr>`;
            });
            runsHtml += '</tbody></table></div></div>';
        }

        $('etlRuns').innerHTML = runsHtml || '<p class="muted" style="padding:1rem">Sin historial de ejecuciones registrado.</p>';
    }

    function calcDur(start, end) {
        const s = Math.floor((new Date(end) - new Date(start)) / 1000);
        if (s < 60)   return s + 's';
        if (s < 3600) return (s/60).toFixed(1) + 'min';
        return (s/3600).toFixed(1) + 'h';
    }

    // ── Escape HTML ──────────────────────────────────────────────────────────

    function esc(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ── REPORTES ─────────────────────────────────────────────────────────────

    let repData = { categories: [], reports: [] };

    function initReportes() {
        // Sub-tabs
        document.querySelectorAll('.rep-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.rep-tab').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const tab = btn.dataset.reptab;
                $('repPanelCategorias').classList.toggle('d-none', tab !== 'categorias');
                $('repPanelReportes').classList.toggle('d-none', tab !== 'reportes');
            });
        });

        $('btnNuevaCat').addEventListener('click', () => abrirModalCat(null));
        $('catModalClose').addEventListener('click', () => hideModal('catModal'));
        $('catModalCancel').addEventListener('click', () => hideModal('catModal'));
        $('repModalClose').addEventListener('click', () => hideModal('repModal'));
        $('repModalCancel').addEventListener('click', () => hideModal('repModal'));
        $('catModalSave').addEventListener('click', guardarCat);
        $('repModalSave').addEventListener('click', guardarRep);

        // Ícono preview en tiempo real
        $('catIcon').addEventListener('input', () => {
            $('catIconPreview').className = 'bi ' + $('catIcon').value;
        });
        $('repIcon').addEventListener('input', () => {
            $('repIconPreview').className = 'bi ' + $('repIcon').value;
        });

        cargarReportes();
    }

    async function cargarReportes() {
        try {
            repData = await api('api/admin/reportes.php');
            renderCats();
            renderReps();
        } catch (e) {
            $('catBody').innerHTML = `<tr><td colspan="6" class="admin-empty">${esc(e.message)}</td></tr>`;
        }
    }

    function renderCats() {
        const counts = {};
        repData.reports.forEach(r => { counts[r.category_id] = (counts[r.category_id] || 0) + 1; });
        $('catBody').innerHTML = repData.categories.map(c => `
        <tr>
            <td class="col-num">${esc(c.sort_order)}</td>
            <td><i class="bi ${esc(c.icon)}" style="margin-right:.4rem"></i>${esc(c.name)}</td>
            <td class="hide-mobile"><code style="font-size:.75rem;opacity:.7">${esc(c.slug)}</code></td>
            <td class="hide-mobile"><code style="font-size:.75rem">${esc(c.icon)}</code></td>
            <td class="col-num">${counts[c.id] || 0}</td>
            <td class="col-actions">${menuAcciones([
                    { icon:'bi-pencil', label:'Editar',   fn:`adminRepEditCat(${c.id})` },
                    { separator: true },
                    { icon:'bi-trash',  label:'Eliminar', fn:`adminRepDeleteCat(${c.id})`, danger:true,
                      disabled:(counts[c.id]||0)>0 },
                ])}</td>
        </tr>`).join('') || '<tr><td colspan="6" class="admin-empty">Sin categorías</td></tr>';
    }

    function renderReps() {
        const catMap = {};
        repData.categories.forEach(c => { catMap[c.id] = c.name; });
        const statusLabel = { active: 'Activo', partial: 'Parcial', pending: 'Pendiente' };
        const statusClass = { active: 'badge-green', partial: 'badge-amber', pending: 'badge-muted' };
        $('repBody').innerHTML = repData.reports.map(r => `
        <tr>
            <td class="col-num hide-mobile">${r.id}</td>
            <td>
                <i class="bi ${esc(r.icon)}" style="margin-right:.35rem;opacity:.7"></i>
                <strong>${esc(r.short_name)}</strong>
                <div style="font-size:.75rem;opacity:.55;margin-top:.1rem">${esc(r.name)}</div>
            </td>
            <td>${esc(catMap[r.category_id] ?? '—')}</td>
            <td class="col-num hide-mobile">${r.sort_order}</td>
            <td><span class="badge ${statusClass[r.status] || 'badge-muted'}">${statusLabel[r.status] || r.status}</span></td>
            <td class="col-actions">${menuAcciones([
                    { icon:'bi-pencil', label:'Editar', fn:`adminRepEdit(${r.id})` },
                ])}</td>
        </tr>`).join('') || '<tr><td colspan="6" class="admin-empty">Sin reportes</td></tr>';
    }

    function abrirModalCat(id) {
        const cat = id ? repData.categories.find(c => c.id === id) : null;
        $('catModalTitle').textContent = cat ? 'Editar categoría' : 'Nueva categoría';
        $('catId').value       = cat ? cat.id : '';
        $('catName').value     = cat ? cat.name : '';
        $('catIcon').value     = cat ? cat.icon : 'bi-folder';
        $('catSort').value     = cat ? cat.sort_order : 99;
        $('catIconPreview').className = 'bi ' + (cat ? cat.icon : 'bi-folder');
        showModal('catModal');
    }

    async function guardarCat() {
        const id   = $('catId').value;
        const body = {
            action:     id ? 'cat_update' : 'cat_create',
            id:         id ? parseInt(id) : undefined,
            name:       $('catName').value.trim(),
            icon:       $('catIcon').value.trim(),
            sort_order: parseInt($('catSort').value) || 99,
        };
        try {
            await api('api/admin/reportes.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
            hideModal('catModal');
            cargarReportes();
        } catch (e) { alert(e.message); }
    }

    function abrirModalRep(id) {
        const r = repData.reports.find(r => r.id === id);
        if (!r) return;
        $('repId').value        = r.id;
        $('repName').value      = r.name;
        $('repShortName').value = r.short_name;
        $('repIcon').value      = r.icon;
        $('repSort').value      = r.sort_order;
        $('repStatus').value    = r.status;
        $('repIconPreview').className = 'bi ' + r.icon;
        // Poblar select de categorías
        $('repCatId').innerHTML = repData.categories
            .map(c => `<option value="${c.id}" ${c.id === r.category_id ? 'selected':''}>${esc(c.name)}</option>`)
            .join('');
        showModal('repModal');
    }

    async function guardarRep() {
        const body = {
            action:      'rep_update',
            id:          parseInt($('repId').value),
            category_id: parseInt($('repCatId').value),
            name:        $('repName').value.trim(),
            short_name:  $('repShortName').value.trim(),
            icon:        $('repIcon').value.trim(),
            sort_order:  parseInt($('repSort').value) || 10,
            status:      $('repStatus').value,
        };
        try {
            await api('api/admin/reportes.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
            hideModal('repModal');
            cargarReportes();
        } catch (e) { alert(e.message); }
    }

    // Funciones globales llamadas desde onclick en la tabla
    window.adminRepEditCat    = id => abrirModalCat(id);
    window.adminRepDeleteCat  = async id => {
        if (!confirm('¿Eliminar esta categoría?')) return;
        try {
            await api('api/admin/reportes.php', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ action:'cat_delete', id }),
            });
            cargarReportes();
        } catch (e) { alert(e.message); }
    };
    window.adminRepEdit = id => abrirModalRep(id);

})();
