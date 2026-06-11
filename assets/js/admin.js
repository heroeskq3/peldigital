(function () {
    'use strict';

    // ── Helpers ──────────────────────────────────────────────────────────────

    const $ = id => document.getElementById(id);
    const fmt = n => (n < 0 ? 'N/D' : Number(n).toLocaleString('es-CR'));

    async function api(url, opts = {}) {
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

    // ── Section routing ──────────────────────────────────────────────────────

    const sections = {
        usuarios:     { init: initUsuarios,     loaded: false },
        roles:        { init: initRoles,         loaded: false },
        reportes:     { init: initReportes,      loaded: false },
        bitacora:     { init: initBitacora,      loaded: false },
        configuracion:{ init: initConfiguracion, loaded: false },
        'cargar-datos':{ init: initDatos,        loaded: false },
        pipelines:    { init: initPipelines,     loaded: false },
    };

    function activarSeccion(slug) {
        // Update sidebar + mobile tabs
        document.querySelectorAll('.admin-sidebar-link').forEach(el => {
            el.classList.toggle('active', el.dataset.section === slug);
        });
        document.querySelectorAll('.admin-mob-tab').forEach(el => {
            el.classList.toggle('active', el.dataset.section === slug);
        });

        // Show/hide sections
        document.querySelectorAll('.admin-section').forEach(el => el.classList.remove('active'));
        const sectionEl = document.getElementById('admin' + slug.replace(/-([a-z])/g, (_, c) => c.toUpperCase())
            .replace(/^./, c => c.toUpperCase()));
        if (sectionEl) sectionEl.classList.add('active');

        // Update URL hash
        history.replaceState(null, '', '#' + slug);

        // Init once
        const s = sections[slug];
        if (s && !s.loaded) { s.init(); s.loaded = true; }
    }

    // Sidebar & mobile nav click
    document.querySelectorAll('.admin-sidebar-link, .admin-mob-tab').forEach(el => {
        el.addEventListener('click', () => activarSeccion(el.dataset.section));
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
                <td class="col-num" style="color:var(--text-muted)">${i + 1 + (d.page - 1) * 25}</td>
                <td style="font-weight:500">${esc(u.name)}</td>
                <td style="color:var(--text-muted)">${esc(u.email)}</td>
                <td><span class="badge badge-blue">${esc(u.role_name || '—')}</span></td>
                <td>${badge}</td>
                <td style="color:var(--text-muted);font-size:.8rem">${fecha}</td>
                <td class="col-actions">
                    <div class="btn-actions">
                        <button class="btn-icon btn-icon-blue"  onclick="adminEditUsu(${u.id})" title="Editar"><i class="bi bi-pencil"></i> Editar</button>
                        <button class="btn-icon btn-icon-amber" onclick="adminToggleUsu(${u.id},this)" title="${u.active == 1 ? 'Desactivar' : 'Activar'}">
                            <i class="bi bi-${u.active == 1 ? 'pause-circle' : 'play-circle'}"></i> ${u.active == 1 ? 'Desactivar' : 'Activar'}
                        </button>
                        <button class="btn-icon btn-icon-red"   onclick="adminDeleteUsu(${u.id},'${esc(u.name)}')" title="Eliminar"><i class="bi bi-trash3"></i></button>
                    </div>
                </td>
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
                <td class="col-num" style="color:var(--text-muted)">${r.id}</td>
                <td style="font-weight:600">${esc(r.name)}</td>
                <td style="color:var(--text-muted)">${esc(r.description || '—')}</td>
                <td class="col-num"><span class="badge badge-blue">${r.user_count}</span></td>
                <td class="col-actions">
                    <button class="btn-icon btn-icon-blue" onclick="adminEditRol(${r.id},'${esc(r.name)}','${esc(r.description || '')}')">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                </td>
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
                <td style="font-size:.82rem">${esc(r.user_name || 'Sistema')}</td>
                <td><span class="badge badge-blue">${esc(r.action || '')}</span></td>
                <td style="font-size:.82rem;color:var(--text-muted)">${esc(r.description || '—')}</td>
                <td style="font-size:.78rem;color:var(--text-muted);font-family:monospace">${esc(r.ip_address || '—')}</td>
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
        const voters   = d.sources.find(s => s.key === 'voters');
        const users    = d.sources.find(s => s.key === 'users');
        const audit    = d.sources.find(s => s.key === 'audit_logs');
        const reports  = d.sources.find(s => s.key === 'reports');
        const mig      = d.sources.find(s => s.key === 'migrations');

        $('configGrid').innerHTML = `
            <div class="admin-info-card">
                <div class="admin-info-card-head"><i class="bi bi-server"></i> Entorno</div>
                <ul class="admin-info-rows">
                    <li class="admin-info-row"><span class="admin-info-key">Servidor</span><span class="admin-info-val">${esc(d.server)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Base de datos</span><span class="admin-info-val">${esc(d.db)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Fecha del servidor</span><span class="admin-info-val">${esc(d.now)}</span></li>
                </ul>
            </div>
            <div class="admin-info-card">
                <div class="admin-info-card-head"><i class="bi bi-database"></i> Datos electorales</div>
                <ul class="admin-info-rows">
                    <li class="admin-info-row"><span class="admin-info-key">Padrón (voters)</span><span class="admin-info-val">${fmt(voters?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Juntas nacionales</span><span class="admin-info-val">${fmt(d.juntas)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Tamaño voters</span><span class="admin-info-val">${voters?.size_mb != null ? voters.size_mb + ' MB' : 'N/D'}</span></li>
                </ul>
            </div>
            <div class="admin-info-card">
                <div class="admin-info-card-head"><i class="bi bi-people"></i> Usuarios y acceso</div>
                <ul class="admin-info-rows">
                    <li class="admin-info-row"><span class="admin-info-key">Usuarios registrados</span><span class="admin-info-val">${fmt(users?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Registros de bitácora</span><span class="admin-info-val">${fmt(audit?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Reportes configurados</span><span class="admin-info-val">${fmt(reports?.count)}</span></li>
                </ul>
            </div>
            <div class="admin-info-card">
                <div class="admin-info-card-head"><i class="bi bi-sliders2"></i> Parámetros del sistema</div>
                <ul class="admin-info-rows">
                    <li class="admin-info-row"><span class="admin-info-key">Cache padrón TTL</span><span class="admin-info-val">1 hora</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Migraciones aplicadas</span><span class="admin-info-val">${fmt(mig?.count)}</span></li>
                    <li class="admin-info-row"><span class="admin-info-key">Tema predeterminado</span><span class="admin-info-val">Sistema (claro/oscuro)</span></li>
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
                <td style="color:var(--text-muted);font-size:.83rem">${esc(labels[s.key] || s.label)}</td>
                <td class="col-num" style="font-weight:600">${s.count >= 0 ? fmt(s.count) : '—'}</td>
                <td class="col-num" style="color:var(--text-muted)">${s.size_mb != null ? s.size_mb + ' MB' : '—'}</td>
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

        $('btnRefreshReportes').addEventListener('click', cargarReportes);
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
            <td><code style="font-size:.75rem;opacity:.7">${esc(c.slug)}</code></td>
            <td><code style="font-size:.75rem">${esc(c.icon)}</code></td>
            <td class="col-num">${counts[c.id] || 0}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-action" onclick="adminRepEditCat(${c.id})">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                    <button class="btn-action btn-danger" onclick="adminRepDeleteCat(${c.id})"
                        ${(counts[c.id] || 0) > 0 ? 'disabled title="Tiene reportes asignados"' : ''}>
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`).join('') || '<tr><td colspan="6" class="admin-empty">Sin categorías</td></tr>';
    }

    function renderReps() {
        const catMap = {};
        repData.categories.forEach(c => { catMap[c.id] = c.name; });
        const statusLabel = { active: 'Activo', partial: 'Parcial', pending: 'Pendiente' };
        const statusClass = { active: 'badge-green', partial: 'badge-amber', pending: 'badge-muted' };
        $('repBody').innerHTML = repData.reports.map(r => `
        <tr>
            <td class="col-num">${r.id}</td>
            <td>
                <i class="bi ${esc(r.icon)}" style="margin-right:.35rem;opacity:.7"></i>
                <strong>${esc(r.short_name)}</strong>
                <div style="font-size:.75rem;opacity:.55;margin-top:.1rem">${esc(r.name)}</div>
            </td>
            <td>${esc(catMap[r.category_id] ?? '—')}</td>
            <td class="col-num">${r.sort_order}</td>
            <td>
                <select class="form-input" style="padding:.25rem .5rem;font-size:.78rem;width:110px"
                    onchange="adminRepSetStatus(${r.id}, this.value)">
                    <option value="active"  ${r.status==='active'  ? 'selected':''}>Activo</option>
                    <option value="partial" ${r.status==='partial' ? 'selected':''}>Parcial</option>
                    <option value="pending" ${r.status==='pending' ? 'selected':''}>Pendiente</option>
                </select>
            </td>
            <td>
                <button class="btn-action" onclick="adminRepEdit(${r.id})">
                    <i class="bi bi-pencil"></i> Editar
                </button>
            </td>
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

    async function adminRepSetStatus(id, status) {
        try {
            await api('api/admin/reportes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'rep_status', id, status }),
            });
            const r = repData.reports.find(r => r.id === id);
            if (r) r.status = status;
        } catch (e) { alert(e.message); cargarReportes(); }
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

    // ── Tema (toggle claro/oscuro) ────────────────────────────────────────────

    function applyTheme(t) {
        document.documentElement.setAttribute('data-theme', t);
        localStorage.setItem('cr-theme', t);
        const isDark = t === 'dark';
        ['btnTheme', 'btnThemeM'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.querySelector('i').className = isDark ? 'bi bi-sun' : 'bi bi-moon';
            const lbl = el.querySelector('span');
            if (lbl) lbl.textContent = isDark ? 'Modo claro' : 'Modo oscuro';
        });
    }

    ['btnTheme', 'btnThemeM'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', () => {
            const cur = document.documentElement.getAttribute('data-theme') || 'light';
            applyTheme(cur === 'dark' ? 'light' : 'dark');
        });
    });

    applyTheme(localStorage.getItem('cr-theme') ||
        (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));

})();
