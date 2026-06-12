/* ── Análisis de Bastiones ──────────────────────────────────────────────────── */
/* Requiere: fetchJSON(), POB, activarReporte(), logEvento() del scope global */

(function () {
    "use strict";

    // ── Referencias DOM ──────────────────────────────────────────────────────
    const $b = id => document.getElementById(id);

    let partiesCache = {};
    let bInicializado = false;

    const CLASIF_LABELS = {
        bastion_fuerte:   'Bastión fuerte',
        bastion_moderado: 'Bastión moderado',
        competitivo:      'Competitivo',
        transicion:       'En transición',
        volatil:          'Volátil',
    };

    const TEND_ICONS = {
        subiendo: '<i class="bi bi-arrow-up-circle-fill" style="color:var(--color-success,#22c55e)" title="Subiendo"></i>',
        bajando:  '<i class="bi bi-arrow-down-circle-fill" style="color:var(--color-danger,#ef4444)" title="Bajando"></i>',
        estable:  '<i class="bi bi-dash-circle-fill" style="color:var(--text-muted,#94a3b8)" title="Estable"></i>',
    };

    function clasifBadge(c) {
        const cls = {
            bastion_fuerte:   'b-badge--fuerte',
            bastion_moderado: 'b-badge--moderado',
            competitivo:      'b-badge--competitivo',
            transicion:       'b-badge--transicion',
            volatil:          'b-badge--volatil',
        };
        return `<span class="b-badge ${cls[c] || ''}">${CLASIF_LABELS[c] || c}</span>`;
    }

    function fmtP(v)  { return v != null ? parseFloat(v).toFixed(1) + '%' : '—'; }
    function fmtN(v)  { return v != null ? Number(v).toLocaleString('es-CR') : '—'; }
    function fmtO(v)  { return v != null ? parseFloat(v).toFixed(1) : '—'; }

    // ── Inicialización ────────────────────────────────────────────────────────
    window.initBastiones = async function () {
        if (bInicializado) {
            await cargarB();
            return;
        }
        bInicializado = true;
        poblarProvincias();
        adjuntarEventosB();
        await cargarB();
    };

    function poblarProvincias() {
        const sel = $b('bFiltProv');
        if (!sel || !window.POB) return;
        Object.entries(window.POB.provincias || {}).forEach(([id, p]) => {
            const o = document.createElement('option');
            o.value = id;
            o.textContent = p.nombre;
            sel.appendChild(o);
        });
    }

    function poblarCantones(provId) {
        const sel = $b('bFiltCanton');
        if (!sel) return;
        sel.innerHTML = '<option value="">Todos</option>';
        if (!provId || !window.POB) return;
        Object.entries(window.POB.cantones || {})
            .filter(([, c]) => c.cod_provincia === provId)
            .sort((a, b) => a[1].nombre.localeCompare(b[1].nombre))
            .forEach(([id, c]) => {
                const o = document.createElement('option');
                o.value = id;
                o.textContent = c.nombre;
                sel.appendChild(o);
            });
    }

    function adjuntarEventosB() {
        ['bFiltNivel','bFiltMode','bFiltClasif','bFiltPartido','bFiltCanton'].forEach(id => {
            $b(id)?.addEventListener('change', () => cargarB());
        });
        $b('bFiltProv')?.addEventListener('change', () => {
            poblarCantones($b('bFiltProv').value);
            cargarB();
        });
        $b('bExportar')?.addEventListener('click', exportarCsvB);
    }

    function buildParamsB() {
        const p = new URLSearchParams();
        p.set('nivel',   $b('bFiltNivel').value);
        p.set('mode',    $b('bFiltMode').value);
        const clasif = $b('bFiltClasif').value;
        const part   = $b('bFiltPartido').value;
        const prov   = $b('bFiltProv').value;
        const cant   = $b('bFiltCanton').value;
        if (clasif) p.set('clasificacion', clasif);
        if (part)   p.set('partido', part);
        if (prov)   p.set('province_id', prov);
        if (cant)   p.set('canton_id', cant);
        return p;
    }

    async function cargarB() {
        $b('bLoading').classList.remove('d-none');
        $b('bTableWrap').style.visibility = 'hidden';
        $b('bError').classList.add('d-none');

        try {
            const d = await fetchJSON('api/bastiones.php?' + buildParamsB().toString());

            // Llenar select de partidos en la primera carga
            if (Object.keys(partiesCache).length === 0 && d.parties) {
                partiesCache = d.parties;
                const sel = $b('bFiltPartido');
                if (sel) {
                    Object.entries(d.parties).forEach(([code, p]) => {
                        const o = document.createElement('option');
                        o.value = code;
                        o.textContent = `${p.abbrev} — ${p.name}`;
                        sel.appendChild(o);
                    });
                }
            }

            renderKpisB(d.kpi);
            renderTablaB(d.rows, d.nivel, d.mode);
            const lbl = $b('bTotalLabel');
            if (lbl) lbl.textContent = `${fmtN(d.total)} registros mostrados`;

        } catch (err) {
            const msg = $b('bErrorMsg');
            if (msg) msg.textContent = err.message || 'Error al cargar datos';
            $b('bError')?.classList.remove('d-none');
            console.error('[Bastiones]', err);
        } finally {
            $b('bLoading')?.classList.add('d-none');
            $b('bTableWrap').style.visibility = 'visible';
        }
    }

    function renderKpisB(k) {
        if (!k) return;
        const bastiones = (parseInt(k.bastion_fuerte || 0) + parseInt(k.bastion_moderado || 0));
        const total     = parseInt(k.total_jrvs || 0);
        $b('bStatTotalJrvs').textContent   = fmtN(total);
        $b('bStatBastiones').textContent   = fmtN(bastiones) +
            (total > 0 ? ' (' + ((bastiones / total) * 100).toFixed(1) + '%)' : '');
        $b('bStatCompetitivo').textContent = fmtN(k.competitivo);
        $b('bStatTransicion').textContent  = fmtN(k.transicion);
        $b('bStatPctProm').textContent     = fmtP(k.pct_dom_prom);
        $b('bStatPartProm').textContent    = fmtP(k.part_prom_2026);
    }

    function renderTablaB(rows, nivel, mode) {
        const thead = $b('bThead');
        const tbody = $b('bTbody');
        if (!thead || !tbody) return;

        if (nivel === 'jrv') {
            thead.innerHTML = `<tr>
                <th>JRV</th>
                <th>Provincia</th><th>Cantón</th><th>Distrito</th>
                <th>Local</th>
                <th>Clasificación</th>
                <th title="Tendencia del partido dominante entre 2022 y 2026">Tend.</th>
                <th>Partido dom.</th>
                <th title="Victorias sobre 3 elecciones presidenciales">Vict.</th>
                <th>% prom.</th>
                <th>% 2026</th>
                <th>% 2022-1a</th>
                <th>% 2022-2a</th>
                <th>Inscritos</th>
                ${mode === 'oportunidades' ? '<th title="Índice de oportunidad: rentabilidad de campaña">Índice</th><th title="Votos para voltear la JRV en 2026">Conquista</th>' : ''}
            </tr>`;

            tbody.innerHTML = rows.map(r => `
                <tr>
                    <td class="mono">${r.junta}</td>
                    <td>${r.provincia}</td>
                    <td>${r.canton}</td>
                    <td>${r.distrito}</td>
                    <td class="small muted">${r.local_nombre || '—'}</td>
                    <td>${clasifBadge(r.clasificacion)}</td>
                    <td style="text-align:center">${TEND_ICONS[r.tendencia] || '—'}</td>
                    <td title="${r.dom_partido_nombre || ''}">${r.dom_partido_abbrev || '—'}</td>
                    <td class="num">${r.dom_wins || 0}/3</td>
                    <td class="num">${fmtP(r.dom_pct_avg)}</td>
                    <td class="num">${fmtP(r.e4_pct)}</td>
                    <td class="num">${fmtP(r.e6_pct)}</td>
                    <td class="num">${fmtP(r.e7_pct)}</td>
                    <td class="num">${fmtN(r.inscritos)}</td>
                    ${mode === 'oportunidades'
                        ? `<td class="num">${fmtO(r.indice_oportunidad)}</td><td class="num">${fmtN(r.votos_conquista)}</td>`
                        : ''}
                </tr>
            `).join('');
        } else {
            thead.innerHTML = `<tr>
                <th>Territorio</th>
                <th>Inscritos</th>
                <th>JRVs</th>
                <th>B. fuerte</th>
                <th>B. moderado</th>
                <th>Competitivo</th>
                <th>Transición</th>
                <th>Volátil</th>
                <th>% bastiones</th>
                <th>% dom. prom.</th>
                <th>Oport. total</th>
            </tr>`;
            tbody.innerHTML = rows.map(r => `
                <tr>
                    <td>${r.geo_nombre}</td>
                    <td class="num">${fmtN(r.inscritos)}</td>
                    <td class="num">${fmtN(r.total_jrvs)}</td>
                    <td class="num">${fmtN(r.cnt_bastion_fuerte)}</td>
                    <td class="num">${fmtN(r.cnt_bastion_moderado)}</td>
                    <td class="num">${fmtN(r.cnt_competitivo)}</td>
                    <td class="num">${fmtN(r.cnt_transicion)}</td>
                    <td class="num">${fmtN(r.cnt_volatil)}</td>
                    <td class="num">${fmtP(r.pct_bastion)}</td>
                    <td class="num">${fmtP(r.pct_prom)}</td>
                    <td class="num">${fmtO(r.oportunidad_total)}</td>
                </tr>
            `).join('');
        }

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="20" class="bita-empty">Sin resultados para los filtros seleccionados.</td></tr>';
        }
    }

    function exportarCsvB() {
        const params = buildParamsB();
        params.set('format', 'csv');
        params.set('limit', '2000');
        window.location.href = (window.APP_BASE || '') + 'api/bastiones.php?' + params.toString();
    }

})();
