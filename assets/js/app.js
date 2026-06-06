/* Mapa de calor de poblacion de Costa Rica — drill-down con Leaflet.
   Niveles: provincia -> canton -> distrito.
   Poblacion dummy desde api/poblacion.php; fronteras desde data/*.geojson. */

(function () {
  "use strict";

  // ---- Estado ----
  const state = {
    nivel: "provincia",      // provincia | canton | distrito
    codProvincia: null,      // filtro activo
    codCanton: null,
    codDistrito: null,       // distrito resaltado (via buscador/select)
    metrica: "electoral",    // electoral | real | diferencia
  };

  // Cache de geojson y de poblacion
  const geoCache = {};       // { provincia: FC, canton: FC, distrito: FC }
  let POB = null;            // { provincias:{}, cantones:{}, distritos:{} }
  let INDICE = [];           // indice plano para el buscador

  let map, capa, baseLayer;  // capa = GeoJSON layer actual
  let escala = [];           // umbrales para la leyenda/colores
  let capasPorCodigo = {};   // codigo -> layer del nivel actual
  let featsActuales = [];    // features visibles del nivel actual
  let seleccionActual = null; // ultima region mostrada en el detalle
  let codigoSeleccionadoMapa = null;
  let capaDiaspora = null;   // layer de burbujas mundo (metrica extranjero)
  let miniMapControl = null;
  let miniMapEl = null;

  // Rampa secuencial: azul claro (poca poblacion) -> morado-indigo
  // (mucha poblacion). Misma rampa en light y dark.
  const RAMP = ["#dbeefb", "#aed8f2", "#7fb8e6", "#5793d6",
                "#4470c2", "#3650a6", "#262d7e", "#150857"];
  const CR_BOUNDS = L.latLngBounds([5.4, -87.4], [11.4, -82.0]);
  const WORLD_BOUNDS = L.latLngBounds([-85, -360], [85, 360]);
  const TILES = {
    light: "https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png",
    dark:  "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",
  };

  // Coordenadas aproximadas (centroide) de los países en el padrón exterior.
  const COORDS_PAIS = {
    "Alemania":               [51.2,  10.5],
    "Argentina":              [-34.6, -58.4],
    "Australia":              [-25.3, 133.8],
    "Austria":                [47.5,  14.5],
    "Belgica":                [50.5,   4.5],
    "Bolivia":                [-16.3, -63.6],
    "Brasil":                 [-14.2, -51.9],
    "Canada":                 [56.1, -106.3],
    "Chile":                  [-35.7, -71.5],
    "China":                  [35.9,  104.2],
    "Colombia":               [ 4.6,  -74.1],
    "Corea":                  [37.6,  127.0],
    "Cuba":                   [21.5,  -79.0],
    "Ecuador":                [-1.8,  -78.2],
    "El Salvador":            [13.8,  -88.9],
    "Emiratos Arabes Unidos": [23.4,   53.8],
    "España":                 [40.5,   -3.7],
    "Francia":                [46.2,    2.2],
    "Guatemala":              [15.8,  -90.2],
    "Honduras":               [15.2,  -86.2],
    "India":                  [20.6,   79.1],
    "Indonesia":              [-0.8,  113.9],
    "Israel":                 [31.0,   35.0],
    "Italia":                 [41.9,   12.6],
    "Jamaica":                [18.1,  -77.3],
    "Japon":                  [36.2,  138.3],
    "Kenia":                  [-0.0,   37.9],
    "Mexico":                 [23.6, -102.6],
    "Nicaragua":              [12.9,  -85.2],
    "Paises Bajos":           [52.1,    5.3],
    "Panama":                 [ 8.5,  -80.8],
    "Paraguay":               [-23.4, -58.4],
    "Peru":                   [-9.2,  -75.0],
    "Qatar":                  [25.4,   51.2],
    "Reino Unido":            [55.4,   -3.4],
    "Republica Dominicana":   [18.7,  -70.2],
    "Rusia":                  [61.5,  105.3],
    "Singapur":               [ 1.4,  103.8],
    "Suiza":                  [46.8,    8.2],
    "Turquia":                [38.9,   35.2],
    "Uruguay":                [-32.5, -55.8],
    "Estados Unidos":         [39.5,  -98.4],
  };

  const $ = (id) => document.getElementById(id);
  const fmt = (n) => n.toLocaleString("es-CR");
  const tema = () => document.documentElement.getAttribute("data-theme") || "light";
  const paleta = () => RAMP;
  const cssVar = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();

  // Valor de un registro segun la metrica activa.
  function valorDe(reg) {
    if (!reg) return 0;
    return state.metrica === "extranjero" ? (reg.extranjero || 0) : (reg.poblacion || 0);
  }

  const fmtV = (n) => fmt(n);
  const abreviarV = (n) => abreviar(n);
  const etiquetaValor = (v) => fmt(v) + " hab.";

  const fmtPct = (x) => (x * 100).toFixed(1).replace(".0", "") + "%";

  // Porcentaje contextual: cuota sobre el total del nivel mostrado.
  // Devuelve { pct, label } o null si no aplica.
  function pctInfo(reg, totalNivel, nivel) {
    if (!reg || nivel === "pais" || nivel === "diaspora" || !totalNivel) return null;
    const pct = valorDe(reg) / totalNivel;
    const donde = nivel === "provincia" ? "del total nacional"
      : nivel === "canton" ? "del total de la provincia"
      : "del total del cantón";
    return { pct, label: donde };
  }

  const regDe = (props) => POB[
    { provincia: "provincias", canton: "cantones", distrito: "distritos", pais: "paises", diaspora: "paises" }[props.nivel]
  ][props.codigo];
  const tooltipHTML = (p) =>
    `<div class="cr-tooltip"><strong>${p.nombre}</strong>${etiquetaValor(pobDe(p))}</div>`;

  // ---- Carga inicial ----
  async function init() {
    map = L.map("map", { zoomControl: true, minZoom: 6, maxZoom: 14 })
      .setView([9.75, -84.1], 8);

    restringirMapaNacional();
    setupMiniMap();
    actualizarIconoTema();
    setupMetrica();
    setupPadron();
    setupBitacora();
    setupNav();
    setupFilters();
    $("btnTheme").addEventListener("click", alternarTema);

    try {
      POB = await fetchJSON("api/poblacion.php");
      construirNacional();
      construirDiaspora();
      construirIndice();
      construirControles();
      await dibujarNivel("provincia");
    } catch (e) {
      alert("Error cargando datos: " + e.message);
      console.error(e);
    }
    ocultarLoader();

    $("btnReset").addEventListener("click", reiniciarVista);
  }

  function reiniciarVista() {
    navegarA("provincia", null, null, null);
    map.setView([9.75, -84.1], 8);
  }

  async function fetchJSON(url) {
    const r = await fetch(url);
    if (!r.ok) throw new Error(url + " -> " + r.status);
    return r.json();
  }

  // Registro sintético "Costa Rica" (agregado nacional) para que el panel
  // de detalle y "Mostrar resultados" funcionen también en la vista nacional.
  function construirNacional() {
    const acc = { nombre: "Costa Rica", poblacion: 0, extranjero: 0 };
    for (const v of Object.values(POB.provincias)) {
      acc.poblacion  += v.poblacion  || 0;
      acc.extranjero += v.extranjero || 0;
    }
    POB.paises = { CR: acc };
  }

  // Pobla POB.paises con registros para cada país de la diáspora (nivel "diaspora").
  // Permite que "Mostrar resultados" funcione en la vista mundial igual que en CR.
  function construirDiaspora() {
    const total = (POB.diaspora || []).reduce((s, d) => s + d.votantes, 0);
    POB.paises["ext:Exterior"] = { nombre: "Exterior", poblacion: total, extranjero: total };
    (POB.diaspora || []).forEach((d) => {
      POB.paises["ext:" + d.pais] = { nombre: d.pais, poblacion: d.votantes, extranjero: d.votantes };
    });
  }

  // ---- Bitácora: registro de interacciones (no bloquea la UI) ----
  function logEvento(tipo, detalle, meta) {
    try {
      const payload = JSON.stringify({ tipo, detalle: detalle || "", meta: meta || {} });
      if (navigator.sendBeacon) {
        navigator.sendBeacon("api/log.php", new Blob([payload], { type: "application/json" }));
      } else {
        fetch("api/log.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: payload,
          keepalive: true,
        });
      }
    } catch (e) { /* el registro nunca debe romper la experiencia */ }
  }

  async function getGeo(nivel) {
    if (geoCache[nivel]) return geoCache[nivel];
    const file = { provincia: "provincias", canton: "cantones", distrito: "distritos" }[nivel];
    geoCache[nivel] = await fetchJSON("data/" + file + ".geojson");
    return geoCache[nivel];
  }

  // Valor para una feature segun el nivel y la metrica activa.
  function pobDe(props) {
    const tabla = { provincia: "provincias", canton: "cantones", distrito: "distritos", pais: "paises" }[props.nivel];
    return valorDe(POB[tabla][props.codigo]);
  }

  // Valor con el que se colorea el mapa (absoluto segun la metrica activa).
  function valorMapa(props) {
    return pobDe(props);
  }

  // ---- Dibujo de un nivel ----
  async function dibujarNivel(nivel, resaltarCodigo) {
    limpiarDiaspora();
    state.nivel = nivel;
    mostrarLoader();

    const fc = await getGeo(nivel);
    let feats = fc.features;

    // Filtrar segun el drill-down activo
    if (nivel === "canton") {
      feats = feats.filter((f) => f.properties.cod_provincia === state.codProvincia);
    } else if (nivel === "distrito") {
      feats = feats.filter((f) => f.properties.cod_canton === state.codCanton);
    }

    featsActuales = feats;

    // Calcular escala de color sobre el conjunto visible
    const valores = feats.map((f) => valorMapa(f.properties)).sort((a, b) => a - b);
    escala = calcularEscala(valores);

    if (capa) { capa.remove(); capa = null; }
    capasPorCodigo = {};
    codigoSeleccionadoMapa = resaltarCodigo || null;

    capa = L.geoJSON({ type: "FeatureCollection", features: feats }, {
      style: estiloFeature,
      onEachFeature: enlazarFeature,
    }).addTo(map);

    if (feats.length) {
      // El contenedor puede tener tamano 0 si el CSS aun no se aplico al
      // crear el mapa; recalcular antes de ajustar evita un zoom erroneo.
      map.invalidateSize(false);
      map.fitBounds(capa.getBounds(), { padding: [20, 20] });
    }

    actualizarPanel(nivel, feats);
    actualizarLeyenda();
    await actualizarMiniMapa();

    // Resaltar una region concreta (buscador / select de distrito)
    if (resaltarCodigo && capasPorCodigo[resaltarCodigo]) {
      const lyr = capasPorCodigo[resaltarCodigo];
      lyr.setStyle({ weight: 3, color: cssVar("--stroke-hover"), fillOpacity: 1 });
      lyr.bringToFront();
      lyr.openTooltip();
      map.panTo(lyr.getBounds().getCenter());
      const p = lyr.feature.properties;
      mostrarDetalle(p, pobDe(p));
    }

    ocultarLoader();
  }

  function calcularEscala(valoresOrdenados) {
    if (!valoresOrdenados.length) return [];
    // Umbrales por cuantiles, deduplicados: con pocas regiones (p.ej. 7
    // provincias) los cuantiles pueden repetirse y generarian rangos vacios.
    const n = paleta().length;
    const q = [];
    for (let i = 1; i < n; i++) {
      const idx = Math.floor((i / n) * (valoresOrdenados.length - 1));
      q.push(valoresOrdenados[idx]);
    }
    return [...new Set(q)].sort((a, b) => a - b);
  }

  // Color para el bucket i de un total de "buckets", muestreando la paleta.
  function colorBucket(i, buckets) {
    const pal = paleta();
    if (buckets <= 1) return pal[pal.length - 1];
    const idx = Math.round((i / (buckets - 1)) * (pal.length - 1));
    return pal[idx];
  }

  function colorPara(valor) {
    let i = 0;
    while (i < escala.length && valor > escala[i]) i++;
    return colorBucket(i, escala.length + 1);
  }

  function estiloFeature(feature) {
    return {
      fillColor: colorPara(valorMapa(feature.properties)),
      weight: 0.5,
      opacity: 0.25,
      color: cssVar("--stroke"),
      fillOpacity: 0.92,
    };
  }

  // ---- Interaccion por feature ----
  function enlazarFeature(feature, layer) {
    const p = feature.properties;
    const pob = pobDe(p);
    capasPorCodigo[p.codigo] = layer;

    layer.bindTooltip(
      tooltipHTML(p),
      { sticky: true, direction: "top", className: "cr-tooltip-wrap", opacity: 1 }
    );

    layer.on({
      mouseover: (e) => {
        e.target.setStyle({ weight: 2.5, color: cssVar("--stroke-hover"), fillOpacity: 1 });
        e.target.bringToFront();
      },
      mouseout: (e) => { restaurarEstiloCapa(e.target); },
      click: () => drillDown(p),
    });
  }

  function drillDown(p) {
    if (p.nivel === "provincia") {
      navegarA("canton", p.codigo, null, null);
    } else if (p.nivel === "canton") {
      navegarA("distrito", p.cod_provincia, p.codigo, null);
    } else if (p.nivel === "distrito") {
      fijarSeleccionMapa(p.codigo);
      mostrarDetalle(p, pobDe(p));
    }
  }

  function fijarSeleccionMapa(codigo) {
    codigoSeleccionadoMapa = codigo || null;
    Object.entries(capasPorCodigo).forEach(([cod, layer]) => {
      if (cod === codigoSeleccionadoMapa) {
        layer.setStyle({ weight: 3, color: cssVar("--stroke-hover"), fillOpacity: 1 });
        layer.bringToFront();
      } else if (capa) {
        capa.resetStyle(layer);
      }
    });
  }

  function restaurarEstiloCapa(layer) {
    if (layer?.feature?.properties?.codigo === codigoSeleccionadoMapa) {
      layer.setStyle({ weight: 3, color: cssVar("--stroke-hover"), fillOpacity: 1 });
      layer.bringToFront();
      return;
    }
    if (capa) capa.resetStyle(layer);
  }

  // Navegacion programatica unificada (drill-down, buscador y selects).
  async function navegarA(nivel, codProvincia, codCanton, codDistrito) {
    state.codProvincia = codProvincia || null;
    state.codCanton = codCanton || null;
    state.codDistrito = codDistrito || null;
    await dibujarNivel(nivel, codDistrito);
    sincronizarSelects();

    const nombre = codDistrito ? POB.distritos[codDistrito]?.nombre
      : codCanton ? POB.cantones[codCanton]?.nombre
      : codProvincia ? POB.provincias[codProvincia]?.nombre : "Nacional";
    logEvento("navegacion", `${nivel}: ${nombre || ""}`,
      { nivel, codProvincia, codCanton, codDistrito, metrica: state.metrica });
  }

  // ---- Minimapa de contexto ----
  function setupMiniMap() {
    miniMapControl = L.control({ position: "topright" });
    miniMapControl.onAdd = () => {
      miniMapEl = L.DomUtil.create("button", "mini-map-control d-none");
      miniMapEl.type = "button";
      miniMapEl.title = "Volver a vista nacional";
      miniMapEl.setAttribute("aria-label", "Volver a vista nacional");
      miniMapEl.addEventListener("click", () => navegarA("provincia", null, null, null));
      L.DomEvent.disableClickPropagation(miniMapEl);
      L.DomEvent.disableScrollPropagation(miniMapEl);
      return miniMapEl;
    };
    miniMapControl.addTo(map);
  }

  function featurePath(feature, box, w, h, pad) {
    if (!feature || !feature.geometry) return "";
    const coords = feature.geometry.coordinates;
    const polygons = feature.geometry.type === "Polygon" ? [coords]
      : feature.geometry.type === "MultiPolygon" ? coords : [];
    const lonSpan = box.maxLon - box.minLon || 1;
    const latSpan = box.maxLat - box.minLat || 1;
    const scale = Math.min((w - pad * 2) / lonSpan, (h - pad * 2) / latSpan);
    const offsetX = pad + ((w - pad * 2) - lonSpan * scale) / 2;
    const offsetY = pad + ((h - pad * 2) - latSpan * scale) / 2;
    const project = ([lon, lat]) => [
      offsetX + (lon - box.minLon) * scale,
      h - offsetY - (lat - box.minLat) * scale,
    ];

    return polygons.map((poly) => poly.map((ring) => ring.map((pt, i) => {
      const [x, y] = project(pt);
      return `${i === 0 ? "M" : "L"}${x.toFixed(1)} ${y.toFixed(1)}`;
    }).join(" ") + " Z").join(" ")).join(" ");
  }

  function miniMapBox() {
    return {
      minLon: CR_BOUNDS.getWest(),
      maxLon: CR_BOUNDS.getEast(),
      minLat: CR_BOUNDS.getSouth(),
      maxLat: CR_BOUNDS.getNorth(),
    };
  }

  async function featureMiniMapa() {
    if (state.nivel === "canton" && state.codProvincia) {
      const fc = await getGeo("provincia");
      return fc.features.find((f) => f.properties.codigo === state.codProvincia) || null;
    }
    if (state.nivel === "distrito" && state.codDistrito) {
      const fc = await getGeo("distrito");
      return fc.features.find((f) => f.properties.codigo === state.codDistrito) || null;
    }
    if (state.nivel === "distrito" && state.codCanton) {
      const fc = await getGeo("canton");
      return fc.features.find((f) => f.properties.codigo === state.codCanton) || null;
    }
    return null;
  }

  async function actualizarMiniMapa() {
    if (!miniMapEl) return;
    if (state.metrica === "extranjero" || state.nivel === "provincia") {
      miniMapEl.classList.add("d-none");
      miniMapEl.innerHTML = "";
      return;
    }

    const provincias = await getGeo("provincia");
    const activo = await featureMiniMapa();
    const box = miniMapBox();
    const w = 190, h = 136, pad = 3;
    const base = provincias.features.map((f) =>
      `<path class="mini-map-base" d="${featurePath(f, box, w, h, pad)}"></path>`
    ).join("");
    const highlight = activo
      ? `<path class="mini-map-active" d="${featurePath(activo, box, w, h, pad)}"></path>`
      : "";
    const label = state.nivel === "canton" ? "Provincia activa" : "Cantón activo";
    miniMapEl.innerHTML =
      `<svg viewBox="0 0 ${w} ${h}" aria-hidden="true">${base}${highlight}</svg>` +
      `<span>${label}</span>`;
    miniMapEl.classList.remove("d-none");
  }

  // ---- Panel lateral ----
  function actualizarPanel(nivel, feats) {
    const titulos = {
      provincia: "Provincias",
      canton: "Cantones de " + (POB.provincias[state.codProvincia]?.nombre || ""),
      distrito: "Distritos de " + (POB.cantones[state.codCanton]?.nombre || ""),
    };
    const ayudas = {
      provincia: "Haz click en una provincia para ver sus cantones.",
      canton: "Haz click en un cantón para ver sus distritos.",
      distrito: "Nivel más detallado. Haz click en un distrito para fijar sus datos.",
    };
    $("nivelTitulo").textContent = titulos[nivel];
    $("nivelAyuda").textContent = ayudas[nivel];

    // Breadcrumb
    const bc = $("breadcrumb");
    bc.innerHTML = "";
    addCrumb(bc, "Costa Rica", () => navegarA("provincia", null, null, null), nivel === "provincia");
    if (state.codProvincia) {
      const nom = POB.provincias[state.codProvincia].nombre;
      addCrumb(bc, nom, () => navegarA("canton", state.codProvincia, null, null), nivel === "canton");
    }
    if (state.codCanton) {
      const nom = POB.cantones[state.codCanton].nombre;
      addCrumb(bc, nom, null, true);
    }

    // Estadisticas
    const valores = feats.map((f) => pobDe(f.properties));
    const total = valores.reduce((a, b) => a + b, 0);
    const prom = valores.length ? Math.round(total / valores.length) : 0;
    $("statTotal").textContent = abreviarV(total);
    $("statRegiones").textContent = feats.length;
    $("statProm").textContent = abreviarV(prom);

    // Top 5
    const ranking = feats
      .map((f) => ({ p: f.properties, pob: pobDe(f.properties) }))
      .sort((a, b) => b.pob - a.pob)
      .slice(0, 10);
    const ol = $("topList");
    ol.innerHTML = "";
    ranking.forEach((it) => {
      const pi = pctInfo(regDe(it.p), total, it.p.nivel);
      const pct = pi ? `<span class="rk-pct">${fmtPct(pi.pct)}</span>` : "";
      const li = document.createElement("li");
      li.innerHTML = `<span class="rk-name">${it.p.nombre}</span>` +
        `<span class="rk-meta"><span class="rk-val">${fmtV(it.pob)}</span>${pct}</span>`;
      li.addEventListener("click", () => drillDown(it.p));
      ol.appendChild(li);
    });

    mostrarContexto();
    renderDiaspora();
  }

  // Muestra en "Seleccionado" la región en la que se está navegando, de modo
  // que "Mostrar resultados" esté siempre disponible: el cantón cuando se ven
  // sus distritos, la provincia cuando se ven sus cantones, y Costa Rica en la
  // vista nacional. Un distrito resaltado tiene prioridad.
  function mostrarContexto() {
    let nivel, codigo;
    if (state.codDistrito) { nivel = "distrito"; codigo = state.codDistrito; }
    else if (state.nivel === "distrito" && state.codCanton) { nivel = "canton"; codigo = state.codCanton; }
    else if (state.nivel === "canton" && state.codProvincia) { nivel = "provincia"; codigo = state.codProvincia; }
    else { nivel = "pais"; codigo = "CR"; }

    const tabla = { provincia: "provincias", canton: "cantones", distrito: "distritos", pais: "paises" }[nivel];
    const rec = POB[tabla] && POB[tabla][codigo];
    if (!rec) { $("detalle").classList.add("d-none"); return; }
    const props = Object.assign({ nivel, codigo }, rec);
    mostrarDetalle(props, pobDe(props));
  }

  function addCrumb(container, texto, onClick, activo) {
    const li = document.createElement("li");
    li.className = "breadcrumb-item" + (activo ? " active" : "");
    if (activo || !onClick) {
      li.textContent = texto;
    } else {
      const a = document.createElement("a");
      a.textContent = texto;
      a.addEventListener("click", onClick);
      li.appendChild(a);
    }
    container.appendChild(li);
  }

  function mostrarDetalle(p, pob) {
    seleccionActual = p;
    $("detalle").classList.remove("d-none");
    $("detalleNombre").textContent = p.nombre;
    $("detallePob").textContent = fmtV(pob);
    $("detalleUnidad").textContent = state.metrica === "extranjero"
      ? "residen en el exterior" : "habitantes";

    const totalNivel = featsActuales.reduce((a, f) => a + pobDe(f.properties), 0);
    const pi = pctInfo(regDe(p), totalNivel, p.nivel);
    const dp = $("detallePorc");
    if (pi) {
      dp.innerHTML = `<strong>${fmtPct(pi.pct)}</strong> ${pi.label}`;
      dp.classList.remove("d-none");
    } else {
      dp.classList.add("d-none");
    }

    let extra = "";
    if (p.nivel === "diaspora") extra = "Diáspora · exterior";
    else if (p.nivel === "pais") extra = "Nacional · Costa Rica";
    else if (p.nivel === "canton") extra = "Cantón · " + p.provincia;
    else if (p.nivel === "distrito") extra = "Distrito · " + p.canton + ", " + p.provincia;
    else extra = "Provincia";
    $("detalleExtra").textContent = extra;
  }

  function actualizarLeyenda() {
    const cont = $("legend");
    cont.innerHTML = "";
    const buckets = escala.length + 1;
    for (let i = 0; i < buckets; i++) {
      const lo = i === 0 ? null : escala[i - 1];
      const hi = i < escala.length ? escala[i] : null;
      const desde = lo === null ? 0 : lo;
      const txt = hi === null ? `> ${abreviar(desde)}` : `${abreviar(desde)} – ${abreviar(hi)}`;
      const row = document.createElement("div");
      row.className = "row-leg";
      row.innerHTML = `<span class="swatch" style="background:${colorBucket(i, buckets)}"></span><span>${txt}</span>`;
      cont.appendChild(row);
    }
  }

  function abreviar(n) {
    if (n >= 1e6) return (n / 1e6).toFixed(1).replace(".0", "") + "M";
    if (n >= 1e3) return (n / 1e3).toFixed(1).replace(".0", "") + "k";
    return String(n);
  }

  // ---- Buscador + selects en cascada ----
  const norm = (s) => s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

  function construirIndice() {
    INDICE = [];
    for (const [c, v] of Object.entries(POB.provincias)) {
      INDICE.push({ codigo: c, nombre: v.nombre, nivel: "provincia", contexto: "" });
    }
    for (const [c, v] of Object.entries(POB.cantones)) {
      INDICE.push({ codigo: c, nombre: v.nombre, nivel: "canton",
                    cod_provincia: v.cod_provincia, contexto: v.provincia });
    }
    for (const [c, v] of Object.entries(POB.distritos)) {
      INDICE.push({ codigo: c, nombre: v.nombre, nivel: "distrito",
                    cod_provincia: v.cod_provincia, cod_canton: v.cod_canton,
                    contexto: v.canton + ", " + v.provincia });
    }
    INDICE.forEach((it) => { it._n = norm(it.nombre); });
  }

  function cantonesDe(codProv) {
    return Object.entries(POB.cantones)
      .filter(([, v]) => v.cod_provincia === codProv)
      .map(([codigo, v]) => ({ codigo, nombre: v.nombre }))
      .sort((a, b) => a.nombre.localeCompare(b.nombre));
  }

  function distritosDe(codCant) {
    return Object.entries(POB.distritos)
      .filter(([, v]) => v.cod_canton === codCant)
      .map(([codigo, v]) => ({ codigo, nombre: v.nombre }))
      .sort((a, b) => a.nombre.localeCompare(b.nombre));
  }

  function llenarSelect(sel, items, placeholder) {
    sel.innerHTML = "";
    const op = document.createElement("option");
    op.value = ""; op.textContent = placeholder;
    sel.appendChild(op);
    items.forEach((it) => {
      const o = document.createElement("option");
      o.value = it.codigo; o.textContent = it.nombre;
      sel.appendChild(o);
    });
  }

  // Lleva un resultado del buscador al mapa segun su nivel.
  function irAResultado(it) {
    if (it.nivel === "provincia") navegarA("canton", it.codigo, null, null);
    else if (it.nivel === "canton") navegarA("distrito", it.cod_provincia, it.codigo, null);
    else navegarA("distrito", it.cod_provincia, it.cod_canton, it.codigo);
  }

  function construirControles() {
    const prov = Object.entries(POB.provincias)
      .map(([codigo, v]) => ({ codigo, nombre: v.nombre }))
      .sort((a, b) => a.nombre.localeCompare(b.nombre));
    llenarSelect($("selProvincia"), prov, "Provincia…");

    $("selProvincia").addEventListener("change", (e) => {
      const c = e.target.value;
      if (c) navegarA("canton", c, null, null);
      else navegarA("provincia", null, null, null);
    });
    $("selCanton").addEventListener("change", (e) => {
      const c = e.target.value;
      if (c) navegarA("distrito", state.codProvincia, c, null);
      else navegarA("canton", state.codProvincia, null, null);
    });
    $("selDistrito").addEventListener("change", (e) => {
      const c = e.target.value;
      if (c) navegarA("distrito", state.codProvincia, state.codCanton, c);
    });

    // Buscador
    const input = $("buscador");
    const lista = $("resultados");
    input.addEventListener("input", () => renderResultados(input.value, lista));
    input.addEventListener("focus", () => { if (input.value) renderResultados(input.value, lista); });
    document.addEventListener("click", (e) => {
      if (!lista.contains(e.target) && e.target !== input) lista.classList.add("d-none");
    });
  }

  function renderResultados(texto, lista) {
    const q = norm(texto.trim());
    lista.innerHTML = "";
    if (q.length < 1) { lista.classList.add("d-none"); return; }
    const matches = INDICE.filter((it) => it._n.includes(q)).slice(0, 8);
    if (!matches.length) {
      lista.innerHTML = '<li class="list-group-item text-muted">Sin coincidencias</li>';
      lista.classList.remove("d-none");
      return;
    }
    matches.forEach((it) => {
      const li = document.createElement("li");
      li.className = "list-group-item";
      const ctx = it.contexto ? `<span class="res-ctx">${it.contexto}</span>` : "";
      li.innerHTML = `<span><strong>${it.nombre}</strong> ${ctx}</span>` +
                     `<span class="nivel-badge ${it.nivel}">${it.nivel}</span>`;
      li.addEventListener("click", () => {
        $("buscador").value = it.nombre;
        lista.classList.add("d-none");
        logEvento("busqueda", `${it.nivel}: ${it.nombre}`, { codigo: it.codigo, nivel: it.nivel });
        irAResultado(it);
      });
      lista.appendChild(li);
    });
    lista.classList.remove("d-none");
  }

  // Refleja el estado actual en los tres selects.
  function sincronizarSelects() {
    const sp = $("selProvincia"), sc = $("selCanton"), sd = $("selDistrito");
    sp.value = state.codProvincia || "";

    if (state.codProvincia) {
      llenarSelect(sc, cantonesDe(state.codProvincia), "Cantón…");
      sc.disabled = false;
      sc.value = state.codCanton || "";
    } else {
      llenarSelect(sc, [], "Cantón…");
      sc.disabled = true;
    }

    if (state.codCanton) {
      llenarSelect(sd, distritosDe(state.codCanton), "Distrito…");
      sd.disabled = false;
      sd.value = state.codDistrito || "";
    } else {
      llenarSelect(sd, [], "Distrito…");
      sd.disabled = true;
    }
  }

  // ---- Tema (light / dark) ----
  function setBasemap() {
    if (baseLayer) baseLayer.remove();
    baseLayer = L.tileLayer(TILES[tema()], {
      attribution: '&copy; OpenStreetMap, &copy; CARTO',
      subdomains: "abcd",
      noWrap: state.metrica !== "extranjero",
    }).addTo(map);
    baseLayer.bringToBack();
  }

  function restringirMapaNacional() {
    map.setMinZoom(6);
    map.setMaxBounds(CR_BOUNDS.pad(0.35));
    map.options.maxBoundsViscosity = 1;
    setBasemap();
  }

  function habilitarMapaMundial() {
    map.setMinZoom(2);
    map.setMaxBounds(WORLD_BOUNDS);
    map.options.maxBoundsViscosity = 0;
    setBasemap();
  }

  function actualizarIconoTema() {
    const oscuro = tema() === "dark";
    $("btnTheme").querySelector("i").className = oscuro ? "bi bi-sun" : "bi bi-moon";
    const icM = $("btnThemeM")?.querySelector("i");
    if (icM) icM.className = oscuro ? "bi bi-sun" : "bi bi-moon";
    const lbl = $("themeLabelM");
    if (lbl) lbl.textContent = oscuro ? "Modo claro" : "Modo oscuro";
  }

  function alternarTema() {
    const nuevo = tema() === "dark" ? "light" : "dark";
    document.documentElement.setAttribute("data-theme", nuevo);
    localStorage.setItem("cr-theme", nuevo);
    actualizarIconoTema();
    setBasemap();
    if (capaDiaspora) {
      capaDiaspora.eachLayer((l) => l.setStyle({ color: cssVar("--stroke") }));
    } else if (capa) {
      capa.setStyle(estiloFeature);
      if (state.codDistrito && capasPorCodigo[state.codDistrito]) {
        capasPorCodigo[state.codDistrito].setStyle({ weight: 3, color: cssVar("--stroke-hover"), fillOpacity: 1 });
      }
    }
    actualizarLeyenda();
  }

  // ---- Selector de metrica (padron / residencia / saldo) ----
  const AYUDA_METRICA = {
    electoral: "Inscritos según domicilio electoral (padrón real · TSE 2026).",
    extranjero: "Inscritos costarricenses residentes en el exterior (diáspora real · TSE 2026).",
  };

  function setupMetrica() {
    const seg = $("segMetrica");
    seg.addEventListener("click", (e) => {
      const b = e.target.closest(".seg-btn");
      if (!b || b.dataset.metrica === state.metrica) return;
      seleccionarMetrica(b.dataset.metrica);
    });
  }

  // Cambia la metrica activa y sincroniza el control segmentado.
  function seleccionarMetrica(m) {
    if (!m) return;
    state.metrica = m;
    $("segMetrica").querySelectorAll(".seg-btn")
      .forEach((x) => x.classList.toggle("active", x.dataset.metrica === m));
    $("metricaAyuda").textContent = AYUDA_METRICA[m];
    aplicarMetrica();
    logEvento("metrica", `Métrica: ${m}`, { metrica: m });
  }

  // ---- Menú de navegación (Análisis / Admin) + drawer móvil ----
  const ADMIN_LABEL = {
    bitacora: "Bitácora", configuracion: "Configuración", usuarios: "Usuarios",
    roles: "Roles de usuario", cargar: "Cargar Datos", pipelines: "Pipelines",
  };

  function setupNav() {
    const nav = $("mainNav");
    const toggle = $("btnMenu");
    const backdrop = $("navBackdrop");
    const mq = matchMedia("(max-width: 820px)");
    const esMovil = () => mq.matches;

    const cerrarDropdowns = () => {
      nav.querySelectorAll(".nav-item.open").forEach((it) => {
        it.classList.remove("open");
        it.querySelector(".nav-link").setAttribute("aria-expanded", "false");
      });
      nav.querySelectorAll(".dropdown-submenu.open").forEach((it) => {
        it.classList.remove("open");
        it.querySelector(".submenu-trigger")?.setAttribute("aria-expanded", "false");
      });
    };
    const cerrarSubmenusHermanos = (submenu) => {
      submenu.parentElement.querySelectorAll(":scope > .dropdown-submenu.open").forEach((it) => {
        if (it === submenu) return;
        it.classList.remove("open");
        it.querySelector(".submenu-trigger")?.setAttribute("aria-expanded", "false");
      });
    };
    const abrirDrawer = () => {
      nav.classList.add("open");
      backdrop.classList.remove("d-none");
      toggle.setAttribute("aria-expanded", "true");
      document.body.classList.add("nav-open");
      document.body.style.overflow = "hidden";
    };
    const cerrarDrawer = () => {
      nav.classList.remove("open");
      backdrop.classList.add("d-none");
      toggle.setAttribute("aria-expanded", "false");
      document.body.classList.remove("nav-open");
      document.body.style.overflow = "";
      cerrarDropdowns();
    };
    const cerrarTodo = () => { cerrarDropdowns(); if (esMovil()) cerrarDrawer(); };

    toggle.addEventListener("click", () => {
      nav.classList.contains("open") ? cerrarDrawer() : abrirDrawer();
    });
    $("btnMenuClose").addEventListener("click", cerrarDrawer);
    backdrop.addEventListener("click", cerrarDrawer);

    // Abrir/cerrar cada menú (acordeón en móvil, popover en desktop).
    nav.querySelectorAll(".nav-item.has-dropdown > .nav-link").forEach((link) => {
      link.addEventListener("click", (e) => {
        e.stopPropagation();
        const item = link.parentElement;
        const abierto = item.classList.contains("open");
        cerrarDropdowns();
        if (!abierto) {
          item.classList.add("open");
          link.setAttribute("aria-expanded", "true");
        }
      });
    });

    nav.querySelectorAll(".dropdown-submenu > .submenu-trigger").forEach((link) => {
      link.addEventListener("click", (e) => {
        e.stopPropagation();
        const item = link.parentElement;
        const abierto = item.classList.contains("open");
        cerrarSubmenusHermanos(item);
        item.classList.toggle("open", !abierto);
        link.setAttribute("aria-expanded", String(!abierto));
      });
    });

    // Click fuera cierra los popovers (desktop).
    document.addEventListener("click", (e) => {
      if (!nav.contains(e.target) && !toggle.contains(e.target)) cerrarDropdowns();
    });
    document.addEventListener("keydown", (e) => { if (e.key === "Escape") cerrarTodo(); });
    mq.addEventListener("change", (ev) => { if (!ev.matches) cerrarDrawer(); });

    // Análisis predisenados: aplican una métrica con vista nacional.
    nav.querySelectorAll("[data-analisis]").forEach((b) => {
      b.addEventListener("click", () => {
        activarReporte("padron-distribucion");
        navegarA("provincia", null, null, null);
        map.setView([9.75, -84.1], 8);
        seleccionarMetrica(b.dataset.analisis);
        cerrarTodo();
      });
    });

    // Reportes completos (reemplazan la vista del mapa).
    nav.querySelectorAll("[data-reporte]").forEach((b) => {
      b.addEventListener("click", () => {
        const id = b.dataset.reporte;
        cerrarTodo();
        if (id === "jrv-inscritos") abrirReporteJrv();
        else if (id === "jrv-analisis") abrirReporteJrvAnalisis();
      });
    });

    // Admin: la Bitácora abre su visor; el resto sigue en construcción.
    nav.querySelectorAll("[data-admin]").forEach((b) => {
      b.addEventListener("click", () => {
        const mod = b.dataset.admin;
        logEvento("admin_open", `Admin: ${ADMIN_LABEL[mod]}`, { modulo: mod });
        cerrarTodo();
        if (mod === "bitacora") abrirBitacora();
        else toast(`${ADMIN_LABEL[mod]}: módulo en construcción.`);
      });
    });

    // Acciones del drawer (tema / reiniciar) — solo móvil.
    $("btnThemeM").addEventListener("click", alternarTema);
    $("btnResetM").addEventListener("click", () => { reiniciarVista(); cerrarTodo(); });
  }

  // ---- Panel de filtros (bottom sheet en móvil) ----
  function setupFilters() {
    const btn = $("btnFilters");
    if (!btn) return;
    const side = document.querySelector(".app-side");
    const backdrop = $("filtersBackdrop");
    const handle = $("sideHandle");
    const mq = matchMedia("(max-width: 820px)");

    const abrir = () => {
      side.classList.add("open");
      backdrop.classList.remove("d-none");
      document.body.style.overflow = "hidden";
      map.invalidateSize();
    };
    const cerrar = () => {
      side.classList.remove("open");
      backdrop.classList.add("d-none");
      document.body.style.overflow = "";
      map.invalidateSize();
    };

    btn.addEventListener("click", () => side.classList.contains("open") ? cerrar() : abrir());
    backdrop.addEventListener("click", cerrar);
    if (handle) handle.addEventListener("click", cerrar);
    mq.addEventListener("change", (ev) => { if (!ev.matches) cerrar(); });
  }

  let _toastTimer = 0;
  function toast(msg) {
    let t = $("appToast");
    if (!t) {
      t = document.createElement("div");
      t.id = "appToast";
      t.className = "app-toast";
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add("show");
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => t.classList.remove("show"), 2600);
  }

  // ---- Vista mundo: diáspora por país ----

  function mostrarDetalleDiaspora(d) {
    seleccionActual = { nivel: "diaspora", codigo: "ext:" + d.pais, nombre: d.pais };
    $("detalle").classList.remove("d-none");
    $("detalleNombre").textContent = d.pais;
    $("detallePob").textContent = fmt(d.votantes);
    $("detalleUnidad").textContent = "electores residentes";
    const total = (POB.diaspora || []).reduce((s, x) => s + x.votantes, 0);
    const dp = $("detallePorc");
    dp.innerHTML = `<strong>${fmtPct(d.votantes / total)}</strong> de la diáspora total`;
    dp.classList.remove("d-none");
    $("detalleExtra").textContent = "Diáspora · país de residencia";
  }

  function limpiarDiaspora() {
    if (!capaDiaspora) return;
    capaDiaspora.remove();
    capaDiaspora = null;
    restringirMapaNacional();
  }

  function dibujarDiaspora() {
    limpiarDiaspora();
    habilitarMapaMundial();
    if (miniMapEl) miniMapEl.classList.add("d-none");
    if (capa) { capa.remove(); capa = null; }
    featsActuales = [];

    const datos = POB.diaspora || [];
    if (!datos.length) return;

    // Panel lateral
    $("nivelTitulo").textContent = "Diáspora · Mundo";
    $("nivelAyuda").textContent = "Costarricenses inscritos residentes fuera del país.";
    const bc = $("breadcrumb");
    bc.innerHTML = "";
    addCrumb(bc, "Costa Rica", () => navegarA("provincia", null, null, null), false);
    addCrumb(bc, "Exterior", null, true);

    const total = datos.reduce((s, d) => s + d.votantes, 0);
    $("statTotal").textContent  = abreviar(total);
    $("statRegiones").textContent = datos.length;
    $("statProm").textContent   = abreviar(Math.round(total / datos.length));

    const ol = $("topList");
    ol.innerHTML = "";
    datos.slice(0, 10).forEach((d) => {
      const pct = `<span class="rk-pct">${fmtPct(d.votantes / total)}</span>`;
      const li = document.createElement("li");
      li.innerHTML = `<span class="rk-name">${d.pais}</span>` +
        `<span class="rk-meta"><span class="rk-val">${fmt(d.votantes)}</span>${pct}</span>`;
      ol.appendChild(li);
    });

    // Escala de colores y radios de burbuja
    const valores = datos.map((d) => d.votantes).sort((a, b) => a - b);
    escala = calcularEscala(valores);
    const maxV = valores[valores.length - 1] || 1;
    const radio = (n) => 5 + 27 * Math.sqrt(n / maxV);

    // Cada país se coloca en 3 copias del mundo (-360, 0, +360) para que
    // las burbujas sean visibles al hacer drag horizontal infinito.
    const layers = [];
    datos.forEach((d) => {
      const coord = COORDS_PAIS[d.pais];
      if (!coord) return;
      [-360, 0, 360].forEach((offset) => {
        const circle = L.circleMarker([coord[0], coord[1] + offset], {
          radius:      radio(d.votantes),
          fillColor:   colorPara(d.votantes),
          color:       cssVar("--stroke"),
          weight:      0.8,
          opacity:     0.6,
          fillOpacity: 0.85,
        });
        circle.bindTooltip(
          `<div class="cr-tooltip"><strong>${d.pais}</strong>${fmt(d.votantes)} electores</div>`,
          { sticky: true, direction: "top", className: "cr-tooltip-wrap", opacity: 1 }
        );
        circle.on({
          mouseover: (e) => {
            e.target.setStyle({ weight: 2.5, color: cssVar("--stroke-hover"), fillOpacity: 1 });
            mostrarDetalleDiaspora(d);
          },
          mouseout: (e) => {
            e.target.setStyle({ weight: 0.8, color: cssVar("--stroke"), fillOpacity: 0.85 });
          },
        });
        layers.push(circle);
      });
    });

    capaDiaspora = L.layerGroup(layers).addTo(map);
    map.invalidateSize(false);
    map.setView([20, 0], 2);
    actualizarLeyenda();
    // Selección inicial: todos los votantes del exterior
    seleccionActual = { nivel: "diaspora", codigo: "ext:Exterior", nombre: "Exterior" };
    mostrarDetalle(seleccionActual, POB.paises["ext:Exterior"].poblacion);
    renderDiaspora();
  }

  // Panel de diáspora: visible solo en métrica "extranjero".
  function renderDiaspora() {
    const panel = $("diasporaPanel");
    if (state.metrica !== "extranjero") { panel.classList.add("d-none"); return; }
    const datos = POB.diaspora || [];
    if (!datos.length) { panel.classList.add("d-none"); return; }

    const total = datos.reduce((s, d) => s + d.votantes, 0);
    const padronNac = Object.values(POB.provincias).reduce((s, p) => s + (p.poblacion || 0), 0);
    $("diasporaTotal").textContent = abreviar(total);
    $("diasporaPaises").textContent = datos.length;
    $("diasporaPct").textContent = padronNac ? fmtPct(total / padronNac) : "–";

    const ol = $("diasporaList");
    ol.innerHTML = "";
    const frag = document.createDocumentFragment();
    datos.forEach((d) => {
      const pct = total ? `<span class="rk-pct">${fmtPct(d.votantes / total)}</span>` : "";
      const li = document.createElement("li");
      li.innerHTML = `<span class="rk-name">${d.pais}</span>` +
        `<span class="rk-meta"><span class="rk-val">${fmt(d.votantes)}</span>${pct}</span>`;
      frag.appendChild(li);
    });
    ol.appendChild(frag);
    panel.classList.remove("d-none");
  }

  // Recolorea el nivel actual sin reajustar el encuadre del mapa.
  function aplicarMetrica() {
    if (state.metrica === "extranjero") { dibujarDiaspora(); return; }
    // Al salir de extranjero, limpiar burbujas y repintar el nivel CR.
    if (capaDiaspora) { limpiarDiaspora(); dibujarNivel(state.nivel || "provincia"); return; }
    restringirMapaNacional();
    if (!featsActuales.length) return;
    escala = calcularEscala(featsActuales.map((f) => valorMapa(f.properties)).sort((a, b) => a - b));
    if (capa) {
      capa.setStyle(estiloFeature);
      capa.eachLayer((l) => l.setTooltipContent(tooltipHTML(l.feature.properties)));
    }
    actualizarLeyenda();
    actualizarPanel(state.nivel, featsActuales);
    if (state.codDistrito && capasPorCodigo[state.codDistrito]) {
      const lyr = capasPorCodigo[state.codDistrito];
      lyr.setStyle({ weight: 3, color: cssVar("--stroke-hover"), fillOpacity: 1 });
      lyr.bringToFront();
      mostrarDetalle(lyr.feature.properties, pobDe(lyr.feature.properties));
    }
  }

  // ---- Padrón real + modal (DataTable) ----
  function ctxRegion(p) {
    if (p.nivel === "diaspora") return "Diáspora · exterior";
    if (p.nivel === "pais") return "Nacional · Costa Rica";
    if (p.nivel === "distrito") return `${p.canton}, ${p.provincia}`;
    if (p.nivel === "canton") return p.provincia;
    return "Provincia";
  }

  function padronTotal(p) {
    const tabla = { provincia: "provincias", canton: "cantones", distrito: "distritos", pais: "paises", diaspora: "paises" }[p.nivel];
    return POB[tabla][p.codigo].poblacion;
  }

  const padron = { rows: [], page: 1, size: 25, total: 0, pages: 1, estimated: false, q: "", p: null, loading: false };

  function abrirPadron() {
    const p = seleccionActual;
    if (!p) return;
    padron.rows = [];
    padron.page = 1;
    padron.size = parseInt($("padronPageSize").value, 10) || 25;
    padron.total = padronTotal(p);
    padron.pages = 1;
    padron.estimated = false;
    padron.q = "";
    padron.p = p;

    $("padronTitulo").textContent = "Padrón · " + p.nombre;
    $("padronSub").textContent = ctxRegion(p) + " · cargando padrón real";
    $("padronBuscar").value = "";
    $("padronModal").classList.remove("d-none");
    renderPadronLoading();
    cargarPadron();
    logEvento("padron_abrir", `Padrón · ${p.nombre}`,
      { nivel: p.nivel, codigo: p.codigo, total: padron.total });
  }

  function cerrarPadron() { $("padronModal").classList.add("d-none"); }

  function filtrarPadron(q) {
    padron.q = q.trim();
    padron.page = 1;
    cargarPadron();
  }

  function padronParams() {
    const p = padron.p;
    const qs = new URLSearchParams({
      nivel: p.nivel,
      codigo: p.codigo,
      page: String(padron.page),
      size: String(padron.size),
    });
    if (padron.q) qs.set("q", padron.q);
    return qs;
  }

  async function cargarPadron() {
    if (!padron.p) return;
    padron.loading = true;
    renderPadronLoading();
    try {
      const r = await fetchJSON("api/padron.php?" + padronParams().toString());
      padron.rows = r.rows || [];
      padron.total = r.total || 0;
      padron.page = r.page || 1;
      padron.size = r.size || padron.size;
      padron.pages = r.pages || 1;
      padron.estimated = !!r.estimated;
      padron.loading = false;
      renderPadron();
    } catch (e) {
      padron.loading = false;
      renderPadronError("No se pudo cargar el padrón real.");
      console.error(e);
    }
  }

  function renderPadronLoading() {
    $("padronBody").innerHTML = `<tr><td colspan="8" class="bita-empty">Cargando padrón real…</td></tr>`;
    $("padronInfo").textContent = "Consultando base de datos…";
    $("pgNow").textContent = "";
    $("pgFirst").disabled = $("pgPrev").disabled = $("pgLast").disabled = $("pgNext").disabled = true;
  }

  function renderPadronError(msg) {
    $("padronBody").innerHTML = `<tr><td colspan="8" class="bita-empty">${esc(msg)}</td></tr>`;
    $("padronInfo").textContent = msg;
    $("pgNow").textContent = "";
    $("pgFirst").disabled = $("pgPrev").disabled = $("pgLast").disabled = $("pgNext").disabled = true;
  }

  function renderPadron() {
    const body = $("padronBody");
    body.innerHTML = "";
    if (!padron.rows.length) {
      body.innerHTML = `<tr><td colspan="8" class="bita-empty">Sin coincidencias.</td></tr>`;
    }
    const frag = document.createDocumentFragment();
    padron.rows.forEach((x) => {
      const tr = document.createElement("tr");
      tr.innerHTML =
        `<td class="mono">${esc(x.cedula || "")}</td>` +
        `<td>${esc(x.nombre || "")}</td>` +
        `<td>${esc([x.apellido1, x.apellido2].filter(Boolean).join(" "))}</td>` +
        `<td class="mono">${esc(x.fecha_caduc || "N/D")}</td>` +
        `<td class="mono">${esc(x.junta || "N/D")}</td>` +
        `<td>${esc(x.provincia || "N/D")}</td>` +
        `<td>${esc(x.canton || "N/D")}</td>` +
        `<td>${esc(x.distrito || "N/D")}</td>`;
      frag.appendChild(tr);
    });
    body.appendChild(frag);

    const start = padron.total ? ((padron.page - 1) * padron.size) + 1 : 0;
    const end = Math.min(start + padron.rows.length - 1, padron.total);
    const totalTxt = padron.estimated ? "más de " + fmt(Math.max(0, padron.total - 1)) : fmt(padron.total);
    $("padronSub").textContent = ctxRegion(padron.p) + " · " + totalTxt + " inscritos reales";
    $("padronInfo").textContent = padron.total
      ? `${fmt(start)}–${fmt(end)} de ${padron.estimated ? "más de " + fmt(Math.max(0, padron.total - 1)) : fmt(padron.total)}`
      : "Sin coincidencias";
    $("pgNow").textContent = `Pág. ${padron.page} / ${padron.pages}`;
    $("pgFirst").disabled = $("pgPrev").disabled = padron.page <= 1;
    $("pgLast").disabled = $("pgNext").disabled = padron.page >= padron.pages;
  }

  // Exporta la página visible a CSV (lo abre Excel directamente).
  function exportarPadron() {
    const rows = padron.rows;
    if (!rows.length) return;
    const head = ["Cédula", "Nombre", "Apellidos", "Vence cédula", "Junta", "Provincia", "Cantón", "Distrito"];
    const q = (s) => '"' + String(s).replace(/"/g, '""') + '"';
    const lineas = ["sep=,", head.map(q).join(",")];
    rows.forEach((x) => {
      lineas.push([
        x.cedula,
        x.nombre,
        [x.apellido1, x.apellido2].filter(Boolean).join(" "),
        x.fecha_caduc || "N/D",
        x.junta || "N/D",
        x.provincia || "N/D",
        x.canton || "N/D",
        x.distrito || "N/D",
      ].map(q).join(","));
    });
    // BOM para que Excel respete los acentos.
    const blob = new Blob(["﻿" + lineas.join("\r\n")], { type: "text/csv;charset=utf-8" });
    const slug = norm(padron.p?.nombre || "region").replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `padron-${slug}-pagina-${padron.page}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(a.href);
    logEvento("padron_export", `Exportó padrón · ${padron.p?.nombre || ""}`,
      { filas: rows.length });
  }

  function irPagina(n) {
    if (padron.loading) return;
    padron.page = Math.max(1, Math.min(n, padron.pages));
    cargarPadron();
  }

  function setupPadron() {
    $("btnPadron").addEventListener("click", abrirPadron);
    $("padronClose").addEventListener("click", cerrarPadron);
    $("padronModal").addEventListener("click", (e) => {
      if (e.target === $("padronModal")) cerrarPadron();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !$("padronModal").classList.contains("d-none")) cerrarPadron();
    });
    let t;
    $("padronBuscar").addEventListener("input", (e) => {
      clearTimeout(t);
      const v = e.target.value;
      t = setTimeout(() => filtrarPadron(v), 150);
    });
    $("padronPageSize").addEventListener("change", (e) => {
      padron.size = parseInt(e.target.value, 10) || 25;
      padron.page = 1;
      cargarPadron();
    });
    $("pgFirst").addEventListener("click", () => irPagina(1));
    $("pgPrev").addEventListener("click", () => irPagina(padron.page - 1));
    $("pgNext").addEventListener("click", () => irPagina(padron.page + 1));
    $("pgLast").addEventListener("click", () => irPagina(padron.pages));
    $("btnExport").addEventListener("click", exportarPadron);
  }

  // ---- Bitácora: visor de auditoría ----
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  const TIPO_LABEL = {
    login: "Ingreso", login_fallido: "Ingreso fallido", logout: "Salida",
    navegacion: "Navegación", metrica: "Métrica", analisis: "Análisis",
    busqueda: "Búsqueda", padron_abrir: "Abrió padrón", padron_export: "Exportó",
    admin_open: "Admin", reset: "Reinició", tema: "Tema", otro: "Otro",
  };

  function abrirBitacora() {
    $("bitacoraModal").classList.remove("d-none");
    cargarBitacora();
  }
  function cerrarBitacora() { $("bitacoraModal").classList.add("d-none"); }

  async function cargarBitacora() {
    const q = $("bitacoraBuscar").value.trim();
    $("bitacoraInfo").textContent = "Cargando…";
    try {
      const r = await fetchJSON("api/bitacora.php?n=300" + (q ? "&q=" + encodeURIComponent(q) : ""));
      renderBitacora(r.eventos || []);
    } catch (e) {
      $("bitacoraInfo").textContent = "No se pudo cargar la bitácora.";
    }
  }

  function renderBitacora(eventos) {
    const body = $("bitacoraBody");
    body.innerHTML = "";
    if (!eventos.length) {
      body.innerHTML = `<tr><td colspan="5" class="bita-empty">Sin registros.</td></tr>`;
      $("bitacoraInfo").textContent = "0 eventos";
      return;
    }
    const frag = document.createDocumentFragment();
    eventos.forEach((e) => {
      const fecha = e.ts ? new Date(e.ts).toLocaleString("es-CR") : "";
      const tipo = TIPO_LABEL[e.tipo] || e.tipo;
      const tr = document.createElement("tr");
      tr.innerHTML =
        `<td class="mono">${esc(fecha)}</td>` +
        `<td>${esc(e.usuario || "")}</td>` +
        `<td class="mono">${esc(e.ip || "")}</td>` +
        `<td><span class="bita-tag bita-${esc(e.tipo)}">${esc(tipo)}</span></td>` +
        `<td>${esc(e.detalle || "")}</td>`;
      frag.appendChild(tr);
    });
    body.appendChild(frag);
    $("bitacoraInfo").textContent =
      `${fmt(eventos.length)} evento${eventos.length === 1 ? "" : "s"} (más recientes primero)`;
  }

  function setupBitacora() {
    $("bitacoraClose").addEventListener("click", cerrarBitacora);
    $("bitacoraModal").addEventListener("click", (e) => {
      if (e.target === $("bitacoraModal")) cerrarBitacora();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !$("bitacoraModal").classList.contains("d-none")) cerrarBitacora();
    });
    $("bitacoraRefresh").addEventListener("click", cargarBitacora);
    let t;
    $("bitacoraBuscar").addEventListener("input", () => {
      clearTimeout(t);
      t = setTimeout(cargarBitacora, 250);
    });
  }

  // ---- Reporte Análisis Estratégico JRV ----

  const ja = {
    page: 1, size: 50, order: "desc",
    province_id: "", canton_id: "", district_id: "",
    total: 0, pages: 1,
    loading: false, _inicializado: false,
  };

  const CLASIF_LABEL = { alta: "Alta", media: "Media", baja: "Baja" };

  function jaParams() {
    const p = new URLSearchParams({ page: ja.page, size: ja.size, order: ja.order });
    if (ja.province_id) p.set("province_id", ja.province_id);
    if (ja.canton_id)   p.set("canton_id",   ja.canton_id);
    if (ja.district_id) p.set("geo5",        ja.district_id);
    return p;
  }

  async function cargarJrvAnalisis() {
    if (ja.loading) return;
    ja.loading = true;
    $("jaBody").innerHTML = `<tr><td colspan="12" class="bita-empty">Cargando…</td></tr>`;
    try {
      const r = await fetchJSON("api/jrv.php?" + jaParams());
      ja.total = r.total; ja.pages = r.pages; ja.page = r.page;

      $("jaStatJuntas").textContent    = fmt(r.stats.juntas);
      $("jaStatInscritos").textContent = fmt(r.stats.total_inscritos);

      renderJrvAnalisis(r.rows, r.page, r.size, r.stats.max_inscritos);
      renderJaPager(r.page, r.pages, r.total);
    } catch (e) {
      $("jaBody").innerHTML = `<tr><td colspan="12" class="bita-empty">Error al cargar datos.</td></tr>`;
      console.error(e);
    }
    ja.loading = false;
  }

  function renderJrvAnalisis(rows, page, size, maxInscritos) {
    const frag = document.createDocumentFragment();
    const base = (page - 1) * size;
    rows.forEach((r, i) => {
      const cl   = r.clasificacion || "baja";
      const pct  = Math.round((r.inscritos / (maxInscritos || 1)) * 100);
      const tr   = document.createElement("tr");
      tr.innerHTML =
        `<td class="mono rk-pos">${base + i + 1}</td>` +
        `<td class="mono">${esc(r.junta)}</td>` +
        `<td>${esc(r.provincia)}</td>` +
        `<td>${esc(r.canton)}</td>` +
        `<td>${esc(r.distrito)}</td>` +
        `<td class="col-num">${fmt(r.inscritos)}</td>` +
        `<td class="td-nd col-num">N/D</td>` +
        `<td class="td-nd col-num">N/D</td>` +
        `<td class="td-nd col-num">N/D</td>` +
        `<td class="td-nd col-num">N/D</td>` +
        `<td><span class="badge-clasif badge-${cl}">${CLASIF_LABEL[cl]}</span></td>` +
        `<td class="col-bar"><div class="part-bar part-bar-${cl}" style="width:${pct}%"></div></td>`;
      frag.appendChild(tr);
    });
    $("jaBody").innerHTML = "";
    $("jaBody").appendChild(frag);
  }

  function renderJaPager(page, pages, total) {
    $("jaInfo").textContent = `${fmt(total)} junta${total !== 1 ? "s" : ""}`;
    $("jaPage").textContent = `${page} / ${pages}`;
    $("jaFirst").disabled = $("jaPrev").disabled = page <= 1;
    $("jaNext").disabled  = $("jaLast").disabled = page >= pages;
  }

  function setupJaFiltros() {
    const selProv = $("jaProvincia");
    const selCant = $("jaCanton");
    const selDist = $("jaDistrito");

    Object.entries(POB.provincias || {}).forEach(([id, p]) => {
      const o = document.createElement("option");
      o.value = id; o.textContent = p.nombre;
      selProv.appendChild(o);
    });

    selProv.addEventListener("change", () => {
      ja.province_id = selProv.value; ja.canton_id = ""; ja.district_id = "";
      selCant.innerHTML = '<option value="">Todos los cantones</option>';
      selDist.innerHTML = '<option value="">Todos los distritos</option>';
      selCant.disabled = !selProv.value; selDist.disabled = true;
      if (selProv.value) {
        Object.entries(POB.cantones || {})
          .filter(([, c]) => c.cod_provincia === selProv.value)
          .sort((a, b) => a[1].nombre.localeCompare(b[1].nombre))
          .forEach(([id, c]) => {
            const o = document.createElement("option");
            o.value = id; o.textContent = c.nombre; selCant.appendChild(o);
          });
      }
      ja.page = 1; cargarJrvAnalisis();
    });

    selCant.addEventListener("change", () => {
      ja.canton_id = selCant.value; ja.district_id = "";
      selDist.innerHTML = '<option value="">Todos los distritos</option>';
      selDist.disabled = !selCant.value;
      if (selCant.value) {
        Object.entries(POB.distritos || {})
          .filter(([, d]) => d.cod_canton === selCant.value)
          .sort((a, b) => a[1].nombre.localeCompare(b[1].nombre))
          .forEach(([geo5, d]) => {
            const o = document.createElement("option");
            o.value = geo5; o.textContent = d.nombre; selDist.appendChild(o);
          });
      }
      ja.page = 1; cargarJrvAnalisis();
    });

    selDist.addEventListener("change", () => { ja.district_id = selDist.value; ja.page = 1; cargarJrvAnalisis(); });
    $("jaPageSize").addEventListener("change", () => { ja.size = parseInt($("jaPageSize").value, 10); ja.page = 1; cargarJrvAnalisis(); });
    $("jaBtnDesc").addEventListener("click", () => { ja.order = "desc"; $("jaBtnDesc").classList.add("active"); $("jaBtnAsc").classList.remove("active"); ja.page = 1; cargarJrvAnalisis(); });
    $("jaBtnAsc").addEventListener("click",  () => { ja.order = "asc";  $("jaBtnAsc").classList.add("active");  $("jaBtnDesc").classList.remove("active"); ja.page = 1; cargarJrvAnalisis(); });
    $("jaFirst").addEventListener("click", () => { ja.page = 1; cargarJrvAnalisis(); });
    $("jaPrev").addEventListener("click",  () => { ja.page--; cargarJrvAnalisis(); });
    $("jaNext").addEventListener("click",  () => { ja.page++; cargarJrvAnalisis(); });
    $("jaLast").addEventListener("click",  () => { ja.page = ja.pages; cargarJrvAnalisis(); });
  }

  function abrirReporteJrvAnalisis() {
    activarReporte("jrv-analisis");
    logEvento("reporte_abrir", "JRV: Análisis Estratégico", {});
    if (!ja._inicializado) { setupJaFiltros(); ja._inicializado = true; }
    cargarJrvAnalisis();
  }

  function mostrarLoader() { $("loader").classList.remove("hidden"); }
  function ocultarLoader() { $("loader").classList.add("hidden"); }

  // ---- Switching entre reportes ----

  let reporteActivo = "padron-distribucion"; // id del reporte visible

  function activarReporte(id) {
    // Oculta la vista del mapa o el reporte anterior
    const appBody = document.querySelector(".app-body");
    if (appBody) appBody.classList.toggle("d-none", id !== "padron-distribucion");

    // Oculta todos los reportes y muestra el solicitado
    document.querySelectorAll(".reporte-page").forEach((el) => el.classList.add("d-none"));
    if (id !== "padron-distribucion") {
      const panel = document.querySelector(`.reporte-page[data-report="${id}"]`);
      if (panel) panel.classList.remove("d-none");
    }

    reporteActivo = id;
  }

  // ---- Reporte JRV: Inscritos por Junta Receptora de Votos ----

  const jrv = {
    page: 1, size: 50, order: "desc",
    province_id: "", canton_id: "", district_id: "",
    total: 0, pages: 1, maxInscritos: 1,
    loading: false,
  };

  function jrvParams() {
    const p = new URLSearchParams({ page: jrv.page, size: jrv.size, order: jrv.order });
    if (jrv.province_id) p.set("province_id", jrv.province_id);
    if (jrv.canton_id)   p.set("canton_id",   jrv.canton_id);
    if (jrv.district_id) p.set("geo5",        jrv.district_id);
    return p;
  }

  async function cargarJrv() {
    if (jrv.loading) return;
    jrv.loading = true;
    $("jrvBody").innerHTML = `<tr><td colspan="7" class="bita-empty">Cargando…</td></tr>`;
    try {
      const r = await fetchJSON("api/jrv.php?" + jrvParams());
      jrv.total  = r.total;
      jrv.pages  = r.pages;
      jrv.page   = r.page;
      jrv.maxInscritos = r.stats.max_inscritos || 1;

      // Stats
      $("jrvStatJuntas").textContent = fmt(r.stats.juntas);
      $("jrvStatTotal").textContent  = fmt(r.stats.total_inscritos);
      $("jrvStatProm").textContent   = fmt(r.stats.promedio);
      $("jrvStatMax").textContent    = fmt(r.stats.max_inscritos);
      $("jrvStatMin").textContent    = fmt(r.stats.min_inscritos);

      renderJrv(r.rows, r.page, r.size);
      renderJrvPager(r.page, r.pages, r.total);
    } catch (e) {
      $("jrvBody").innerHTML = `<tr><td colspan="7" class="bita-empty">Error al cargar datos.</td></tr>`;
      console.error(e);
    }
    jrv.loading = false;
  }

  function renderJrv(rows, page, size) {
    const frag = document.createDocumentFragment();
    const base  = (page - 1) * size;
    rows.forEach((r, i) => {
      const pct  = Math.round((r.inscritos / jrv.maxInscritos) * 100);
      const tr   = document.createElement("tr");
      tr.innerHTML =
        `<td class="mono rk-pos">${base + i + 1}</td>` +
        `<td class="mono">${esc(r.junta)}</td>` +
        `<td>${esc(r.provincia)}</td>` +
        `<td>${esc(r.canton)}</td>` +
        `<td>${esc(r.distrito)}</td>` +
        `<td class="col-num"><strong>${fmt(r.inscritos)}</strong></td>` +
        `<td class="col-bar"><div class="jrv-bar" style="width:${pct}%"></div></td>`;
      frag.appendChild(tr);
    });
    $("jrvBody").innerHTML = "";
    $("jrvBody").appendChild(frag);
  }

  function renderJrvPager(page, pages, total) {
    $("jrvInfo").textContent = `${fmt(total)} junta${total !== 1 ? "s" : ""}`;
    $("jrvPage").textContent = `${page} / ${pages}`;
    $("jrvFirst").disabled = $("jrvPrev").disabled = page <= 1;
    $("jrvNext").disabled  = $("jrvLast").disabled = page >= pages;
  }

  function jrvSetOrder(order) {
    jrv.order = order;
    $("jrvBtnDesc").classList.toggle("active", order === "desc");
    $("jrvBtnAsc").classList.toggle("active",  order === "asc");
    jrv.page = 1;
    cargarJrv();
  }

  function setupJrvFiltros() {
    // Poblar provincias desde los datos ya cargados en POB
    const selProv = $("jrvProvincia");
    const selCant = $("jrvCanton");
    const selDist = $("jrvDistrito");

    Object.entries(POB.provincias || {}).forEach(([id, p]) => {
      const o = document.createElement("option");
      o.value = id; o.textContent = p.nombre;
      selProv.appendChild(o);
    });

    selProv.addEventListener("change", () => {
      jrv.province_id = selProv.value;
      jrv.canton_id = ""; jrv.district_id = "";
      selCant.innerHTML = '<option value="">Todos los cantones</option>';
      selDist.innerHTML = '<option value="">Todos los distritos</option>';
      selCant.disabled = !selProv.value;
      selDist.disabled = true;

      if (selProv.value) {
        Object.entries(POB.cantones || {})
          .filter(([, c]) => c.cod_provincia === selProv.value)
          .sort((a, b) => a[1].nombre.localeCompare(b[1].nombre))
          .forEach(([id, c]) => {
            const o = document.createElement("option");
            o.value = id; o.textContent = c.nombre;
            selCant.appendChild(o);
          });
      }
      jrv.page = 1; cargarJrv();
    });

    selCant.addEventListener("change", () => {
      jrv.canton_id = selCant.value;
      jrv.district_id = "";
      selDist.innerHTML = '<option value="">Todos los distritos</option>';
      selDist.disabled = !selCant.value;

      if (selCant.value) {
        Object.entries(POB.distritos || {})
          .filter(([, d]) => d.cod_canton === selCant.value)
          .sort((a, b) => a[1].nombre.localeCompare(b[1].nombre))
          .forEach(([geo5, d]) => {
            const o = document.createElement("option");
            // district_id real via subquery no disponible en POB; usamos geo5 solo para
            // filtrar en la API que acepta district_id numerico. Omitir filtro por ahora.
            o.value = geo5; o.textContent = d.nombre;
            selDist.appendChild(o);
          });
      }
      jrv.page = 1; cargarJrv();
    });

    selDist.addEventListener("change", () => {
      jrv.district_id = selDist.value;
      jrv.page = 1; cargarJrv();
    });

    $("jrvPageSize").addEventListener("change", () => {
      jrv.size = parseInt($("jrvPageSize").value, 10);
      jrv.page = 1; cargarJrv();
    });

    $("jrvBtnDesc").addEventListener("click", () => jrvSetOrder("desc"));
    $("jrvBtnAsc").addEventListener("click",  () => jrvSetOrder("asc"));

    $("jrvFirst").addEventListener("click", () => { jrv.page = 1; cargarJrv(); });
    $("jrvPrev").addEventListener("click",  () => { jrv.page--; cargarJrv(); });
    $("jrvNext").addEventListener("click",  () => { jrv.page++; cargarJrv(); });
    $("jrvLast").addEventListener("click",  () => { jrv.page = jrv.pages; cargarJrv(); });
  }

  function abrirReporteJrv() {
    activarReporte("jrv-inscritos");
    logEvento("reporte_abrir", "JRV: Inscritos por Junta", {});
    if (!jrv._inicializado) {
      setupJrvFiltros();
      jrv._inicializado = true;
    }
    cargarJrv();
  }

  document.addEventListener("DOMContentLoaded", init);
})();
