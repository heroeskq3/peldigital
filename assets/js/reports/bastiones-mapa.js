/* ── Mapa de Bastiones ──────────────────────────────────────────────────────── */
/* Requiere: Leaflet (ya incluido), fetchJSON(), window.POB del scope global    */

(function () {
    "use strict";

    // ── Colores ──────────────────────────────────────────────────────────────
    const CLASIF_COLORS = {
        bastion_fuerte:   '#15803d',
        bastion_moderado: '#4ade80',
        competitivo:      '#3b82f6',
        transicion:       '#f97316',
        volatil:          '#cbd5e1',
        _sin_datos:       '#1e293b',
    };
    const CLASIF_LABELS = {
        bastion_fuerte:   'Bastión fuerte',
        bastion_moderado: 'Bastión moderado',
        competitivo:      'Competitivo',
        transicion:       'En transición',
        volatil:          'Volátil / sin historial',
    };

    // Partido → color (se completa dinámicamente con datos de la API)
    const PARTY_COLORS = {
        4:   '#16a34a',  // PLN — verde
        373: '#f97316',  // PPSO — naranja
        428: '#6366f1',  // CAC — indigo
        73:  '#a21caf',  // FA — morado
        6:   '#2563eb',  // PUSC — azul
        _other: '#94a3b8',
    };

    // Rampa de oportunidad (frío → caliente)
    const OPORT_RAMP = ['#f0f9ff','#bae6fd','#38bdf8','#0284c7','#f97316','#ef4444','#991b1b'];

    function oportunidadColor(norm) {
        const idx = Math.min(Math.floor(norm / 100 * (OPORT_RAMP.length - 1)), OPORT_RAMP.length - 1);
        return OPORT_RAMP[idx];
    }

    // ── Estado ───────────────────────────────────────────────────────────────
    let bmMap       = null;
    let bmGeoLayer  = null;
    let bmInfoCtrl  = null;   // control Leaflet para el panel de detalle
    let bmData      = {};
    let bmParties   = {};
    let bmNivel     = 'distrito';
    let bmModo      = 'clasificacion';
    let bmInicializado = false;

    const $bm = id => document.getElementById(id);

    // ── Inicialización ────────────────────────────────────────────────────────
    window.initBastionesMapa = async function () {
        if (bmInicializado) {
            await cargarBM();
            return;
        }
        bmInicializado = true;

        poblarProvinciasBM();
        adjuntarEventosBM();
        inicializarMapaBM();
        await cargarBM();
    };

    function poblarProvinciasBM() {
        const sel = $bm('bmProv');
        if (!sel || !window.POB) return;
        Object.entries(window.POB.provincias || {}).forEach(([id, p]) => {
            const o = document.createElement('option');
            o.value = id;
            o.textContent = p.nombre;
            sel.appendChild(o);
        });
    }

    function adjuntarEventosBM() {
        $bm('bmNivel')?.addEventListener('change', () => {
            bmNivel = $bm('bmNivel').value;
            cargarBM();
        });
        $bm('bmModo')?.addEventListener('change', () => {
            bmModo = $bm('bmModo').value;
            aplicarColorBM();
            renderLeyendaBM();
        });
        $bm('bmProv')?.addEventListener('change', () => cargarBM());
    }

    function inicializarMapaBM() {
        if (bmMap) return;
        const tema = () => document.documentElement.getAttribute('data-theme') || 'light';
        const tiles = {
            light: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
            dark:  'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        };
        bmMap = L.map('bmMap', { zoomControl: true, minZoom: 6, maxZoom: 14 })
                 .setView([9.75, -84.1], 8);

        L.tileLayer(tiles[tema()], {
            attribution: '© OpenStreetMap · CartoDB',
            maxZoom: 19,
        }).addTo(bmMap);

        document.addEventListener('themechange', () => {
            bmMap.eachLayer(l => { if (l instanceof L.TileLayer) bmMap.removeLayer(l); });
            L.tileLayer(tiles[tema()], { attribution: '© OpenStreetMap · CartoDB', maxZoom: 19 }).addTo(bmMap);
            aplicarColorBM();
        });

        // Control de información (panel de detalle dentro del mapa)
        bmInfoCtrl = L.control({ position: 'topright' });
        bmInfoCtrl.onAdd = function () {
            this._div = L.DomUtil.create('div', 'bm-info-ctrl');
            this.update(null);
            return this._div;
        };
        bmInfoCtrl.update = function (feature) {
            if (!feature) {
                this._div.innerHTML = '<span class="bm-info-hint">Pasa el cursor sobre un territorio</span>';
                return;
            }
            const codigo = feature.properties.codigo;
            const d = bmData[codigo];
            const nombre = feature.properties.nombre || codigo;
            const fmtN = n => n != null ? Number(n).toLocaleString('es-CR') : '—';
            const fmtP = v => v != null ? parseFloat(v).toFixed(1) + '%' : '—';
            if (!d) {
                this._div.innerHTML = `<strong>${nombre}</strong><br><span class="bm-info-nd">Sin datos</span>`;
                return;
            }
            const partido = d.dom_partido_abbrev ? `<strong>${d.dom_partido_abbrev}</strong>` : '—';
            this._div.innerHTML = `
                <div class="bm-info-nombre">${nombre}</div>
                <table class="bm-tt-table">
                    <tr><td>Clasificación</td><td>${CLASIF_LABELS[d.clasif_predominante] || '—'}</td></tr>
                    <tr><td>Partido dom.</td><td>${partido}</td></tr>
                    <tr><td>JRVs</td><td>${fmtN(d.total_jrvs)}</td></tr>
                    <tr><td>Inscritos</td><td>${fmtN(d.inscritos)}</td></tr>
                    <tr><td>% bastiones</td><td>${fmtP(d.pct_bastion)}</td></tr>
                    <tr><td>% dom. prom.</td><td>${fmtP(d.pct_prom)}</td></tr>
                    <tr><td>Oportunidad</td><td>${d.oportunidad_total ?? '—'}</td></tr>
                </table>`;
        };
        bmInfoCtrl.addTo(bmMap);
    }

    // ── Carga de datos y GeoJSON ──────────────────────────────────────────────
    async function cargarBM() {
        $bm('bmLoadingBadge').classList.remove('d-none');
        $bm('bmError').classList.add('d-none');

        const prov = $bm('bmProv').value;
        const params = new URLSearchParams({ nivel: bmNivel });
        if (prov) params.set('province_id', prov);

        const geoFile = {
            distrito: 'data/distritos.geojson',
            canton:   'data/cantones.geojson',
            provincia:'data/provincias.geojson',
        }[bmNivel];

        try {
            const [apiResp, geojson] = await Promise.all([
                fetchJSON('api/bastiones-mapa.php?' + params.toString()),
                fetchJSON(geoFile),
            ]);

            bmData    = apiResp.data    || {};
            bmParties = apiResp.parties || {};

            // Si hay zoom de provincia, filtrar GeoJSON
            let features = geojson.features;
            if (prov && bmNivel !== 'provincia') {
                features = features.filter(f => {
                    const cp = f.properties.cod_provincia;
                    return cp == prov;
                });
            }

            renderCapaBM({ type: 'FeatureCollection', features });
            renderLeyendaBM();

            if (prov && features.length) {
                const bounds = L.geoJSON({ type: 'FeatureCollection', features }).getBounds();
                if (bounds.isValid()) bmMap.fitBounds(bounds, { padding: [20, 20] });
            } else {
                bmMap.setView([9.75, -84.1], 8);
            }

        } catch (err) {
            $bm('bmErrorMsg').textContent = err.message || 'Error cargando datos';
            $bm('bmError').classList.remove('d-none');
            console.error('[BastionesMapa]', err);
        } finally {
            $bm('bmLoadingBadge').classList.add('d-none');
        }
    }

    // ── Renderizar capa GeoJSON ───────────────────────────────────────────────
    function renderCapaBM(geojson) {
        if (bmGeoLayer) bmMap.removeLayer(bmGeoLayer);

        bmGeoLayer = L.geoJSON(geojson, {
            style: feature => estiloFeatureBM(feature),
            onEachFeature: (feature, layer) => {
                layer.on({
                    mouseover: e => {
                        e.target.setStyle({ weight: 2.5, fillOpacity: 0.95 });
                        if (bmInfoCtrl) bmInfoCtrl.update(feature);
                    },
                    mouseout: e => {
                        bmGeoLayer.resetStyle(e.target);
                        if (bmInfoCtrl) bmInfoCtrl.update(null);
                    },
                    click: e => {
                        bmMap.fitBounds(e.target.getBounds(), { padding: [30, 30] });
                    },
                });
            },
        }).addTo(bmMap);
    }

    function estiloFeatureBM(feature) {
        const codigo = feature.properties.codigo;
        const d = bmData[codigo];
        const color = d ? getColorBM(d) : CLASIF_COLORS._sin_datos;
        const tema = document.documentElement.getAttribute('data-theme') || 'light';
        return {
            fillColor:   color,
            weight:      0.8,
            color:       tema === 'dark' ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.2)',
            fillOpacity: d ? 0.82 : 0.25,
        };
    }

    function getColorBM(d) {
        if (bmModo === 'clasificacion') {
            return CLASIF_COLORS[d.clasif_predominante] || CLASIF_COLORS.volatil;
        }
        if (bmModo === 'partido') {
            const tc = d.dom_tse_code;
            return PARTY_COLORS[tc] || PARTY_COLORS._other;
        }
        // oportunidad
        return oportunidadColor(d.oportunidad_norm || 0);
    }

    function aplicarColorBM() {
        if (!bmGeoLayer) return;
        bmGeoLayer.setStyle(f => estiloFeatureBM(f));
    }

    // (tooltip reemplazado por bmInfoCtrl — control Leaflet nativo en topright)

    // ── Leyenda ───────────────────────────────────────────────────────────────
    function renderLeyendaBM() {
        const el = $bm('bmLeyenda');
        if (!el) return;

        if (bmModo === 'clasificacion') {
            el.innerHTML = Object.entries(CLASIF_LABELS).map(([k, lbl]) =>
                `<div class="bm-ley-item">
                    <span class="bm-ley-swatch" style="background:${CLASIF_COLORS[k]}"></span>
                    ${lbl}
                </div>`
            ).join('') + `<div class="bm-ley-item">
                <span class="bm-ley-swatch" style="background:${CLASIF_COLORS._sin_datos};opacity:.4"></span>
                Sin datos
            </div>`;

        } else if (bmModo === 'partido') {
            const parties = Object.entries(bmParties).slice(0, 8);
            el.innerHTML = parties.map(([code, p]) =>
                `<div class="bm-ley-item">
                    <span class="bm-ley-swatch" style="background:${PARTY_COLORS[code] || PARTY_COLORS._other}"></span>
                    ${p.abbrev}
                </div>`
            ).join('') + `<div class="bm-ley-item">
                <span class="bm-ley-swatch" style="background:${PARTY_COLORS._other}"></span>
                Otro
            </div>`;

        } else {
            // Oportunidad — gradiente
            const stops = OPORT_RAMP.map((c, i) =>
                `<span class="bm-ley-swatch" style="background:${c}"></span>`
            ).join('');
            el.innerHTML = `
                <div class="bm-ley-item">${stops}</div>
                <div class="bm-ley-item" style="gap:.25rem">
                    <span style="font-size:.7rem;color:var(--text-muted)">Baja</span>
                    <span style="flex:1"></span>
                    <span style="font-size:.7rem;color:var(--text-muted)">Alta</span>
                </div>
                <div class="bm-ley-item"><span class="muted small">Oportunidad de campaña</span></div>
            `;
        }
    }

})();
