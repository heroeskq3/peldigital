/* PEL Digital app core. Split from assets/js/app.js. */

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
    setupMetrica();
    setupPadron();
    setupBitacora();
    setupFilters();

    // nav.js maneja drawer/dropdowns/tema; app.js sólo actualiza el mapa al cambiar tema
    document.addEventListener("themechange", () => {
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
    });
    document.addEventListener("navreset", reiniciarVista);

    try {
      POB = await fetchJSON("api/poblacion.php");
      actualizarFooterFuente(POB);
      construirNacional();
      construirDiaspora();
      construirIndice();
      construirControles();
      await dibujarNivel("provincia");
    } catch (e) {
      alert("Error cargando datos: " + e.message);
      console.error(e);
    }
    // Activar el reporte indicado por el servidor (window.ACTIVE_REPORT_ID)
    const reportStatus = window.ACTIVE_REPORT_STATUS || "active";
    const targetReport = window.ACTIVE_REPORT_ID || "padron-distribucion";
    if ((reportStatus === "active" || reportStatus === "partial") && targetReport !== "padron-distribucion") {
      if (targetReport === "jrv-inscritos")           abrirReporteJrv();
      else if (targetReport === "jrv-analisis")       abrirReporteJrvAnalisis();
      else if (targetReport === "segmentacion")       abrirReporteSegmentacion();
      else if (targetReport === "participacion")       abrirReporteParticipacion();
      else if (targetReport === "analisis-territorial") abrirAnalisisTerritorial();
      else if (targetReport === "distritos-electorales") abrirDistritosElectorales();
      else if (targetReport === "juntas-padronal")      abrirJuntasPadronal();
      else if (targetReport === "locales-votacion")    abrirLocalesVotacion();
      else if (targetReport === "densidad-electoral")  abrirDensidadElectoral();
      else if (targetReport === "circunscripciones")   abrirCircunscripciones();
      else activarReporte(targetReport);
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

  function actualizarFooterFuente(pob) {
    const fuenteEl = document.getElementById("footerFuente");
    const fechaEl  = document.getElementById("footerFecha");
    if (fuenteEl && pob.fuente) fuenteEl.textContent = pob.fuente;
    if (fechaEl && pob.padron_actualizado) {
      const d = new Date(pob.padron_actualizado.replace(" ", "T"));
      fechaEl.textContent = "· Actualizado: " + d.toLocaleDateString("es-CR", { year: "numeric", month: "long", day: "numeric" });
    }
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

