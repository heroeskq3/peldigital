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

  // Rampa secuencial: azul claro (poca poblacion) -> morado-indigo
  // (mucha poblacion). Misma rampa en light y dark.
  const RAMP = ["#dbeefb", "#aed8f2", "#7fb8e6", "#5793d6",
                "#4470c2", "#3650a6", "#262d7e", "#150857"];
  // Rampa divergente para el "saldo" (real - electoral):
  // rojo = pierde residentes (la gente migro), azul = los gana (dormitorio).
  const RAMP_DIV = ["#b2182b", "#ef8a62", "#fddbc7", "#f7f7f7",
                    "#d1e5f0", "#67a9cf", "#2166ac"];
  const TILES = {
    light: "https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png",
    dark:  "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",
  };

  const $ = (id) => document.getElementById(id);
  const fmt = (n) => n.toLocaleString("es-CR");
  const tema = () => document.documentElement.getAttribute("data-theme") || "light";
  const esDif = () => state.metrica === "diferencia";
  const paleta = () => (esDif() ? RAMP_DIV : RAMP);
  const cssVar = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();

  // Valor de un registro segun la metrica activa.
  function valorDe(reg) {
    if (!reg) return 0;
    const elec = reg.poblacion;
    const real = reg.pob_real != null ? reg.pob_real : elec;
    switch (state.metrica) {
      case "real":          return real;
      case "diferencia":    return real - elec;
      case "abstencion":    return reg.abstencion || 0;
      case "participacion": return reg.participacion || 0;
      case "extranjero":    return reg.extranjero || 0;
      default:              return elec;
    }
  }

  // Formato con signo para metrica "diferencia".
  const fmtV = (n) => (esDif() ? (n >= 0 ? "+" : "−") + fmt(Math.abs(n)) : fmt(n));
  const abreviarS = (n) => (n >= 0 ? "+" : "−") + abreviar(Math.abs(n));
  const abreviarV = (n) => (esDif() ? abreviarS(n) : abreviar(n));
  const etiquetaValor = (v) => (esDif() ? fmtV(v) + " migración" : fmt(v) + " hab.");

  const fmtPct = (x) => (x * 100).toFixed(1).replace(".0", "") + "%";

  // Porcentaje contextual segun la metrica:
  //  - padron/residencia/extranjero: cuota sobre el total del nivel mostrado.
  //  - abstencion/participacion: tasa sobre el padron de la propia region.
  // Devuelve { pct, label } o null si no aplica (p.ej. "saldo").
  function pctInfo(reg, totalNivel, nivel) {
    if (!reg || esDif()) return null;
    if (state.metrica === "abstencion" || state.metrica === "participacion") {
      const base = reg.poblacion || 0;
      if (!base) return null;
      const pct = (reg[state.metrica] || 0) / base;
      const txt = state.metrica === "abstencion" ? "de abstención" : "de participación";
      return { pct, label: `${txt} sobre el padrón` };
    }
    if (!totalNivel) return null;
    const pct = valorDe(reg) / totalNivel;
    const donde = nivel === "provincia" ? "del total nacional"
      : nivel === "canton" ? "del total de la provincia"
      : "del total del cantón";
    return { pct, label: donde };
  }

  const regDe = (props) => POB[
    { provincia: "provincias", canton: "cantones", distrito: "distritos" }[props.nivel]
  ][props.codigo];
  const tooltipHTML = (p) =>
    `<div class="cr-tooltip"><strong>${p.nombre}</strong>${etiquetaValor(pobDe(p))}</div>`;

  // ---- Carga inicial ----
  async function init() {
    map = L.map("map", { zoomControl: true, minZoom: 6, maxZoom: 14 })
      .setView([9.75, -84.1], 8);

    setBasemap();
    actualizarIconoTema();
    setupMetrica();
    setupPadron();
    setupNav();
    setupFilters();
    $("btnTheme").addEventListener("click", alternarTema);

    try {
      POB = await fetchJSON("api/poblacion.php");
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

  async function getGeo(nivel) {
    if (geoCache[nivel]) return geoCache[nivel];
    const file = { provincia: "provincias", canton: "cantones", distrito: "distritos" }[nivel];
    geoCache[nivel] = await fetchJSON("data/" + file + ".geojson");
    return geoCache[nivel];
  }

  // Valor para una feature segun el nivel y la metrica activa.
  function pobDe(props) {
    const tabla = { provincia: "provincias", canton: "cantones", distrito: "distritos" }[props.nivel];
    return valorDe(POB[tabla][props.codigo]);
  }

  const esTasa = () => state.metrica === "abstencion" || state.metrica === "participacion";

  // Valor con el que se COLOREA el mapa. En abstención/participación es la
  // tasa sobre el padrón (0..1); en el resto, el valor absoluto.
  function valorMapa(props) {
    if (!esTasa()) return pobDe(props);
    const reg = regDe(props);
    const base = reg && reg.poblacion ? reg.poblacion : 0;
    return base ? (reg[state.metrica] || 0) / base : 0;
  }

  // ---- Dibujo de un nivel ----
  async function dibujarNivel(nivel, resaltarCodigo) {
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

    // Metrica "diferencia": escala divergente, simetrica alrededor de 0.
    if (esDif()) {
      const M = Math.max(1, ...valoresOrdenados.map((v) => Math.abs(v)));
      const n = paleta().length;          // numero de colores
      const t = [];
      for (let i = 1; i < n; i++) t.push(Math.round(-M + (2 * M) * (i / n)));
      return t;                            // n-1 umbrales, incluye el 0
    }

    // Umbrales por cuantiles, deduplicados: con pocas regiones (p.ej. 7
    // provincias) los cuantiles pueden repetirse y generarian rangos
    // vacios en la leyenda.
    const n = paleta().length;
    const q = [];
    for (let i = 1; i < n; i++) {
      const idx = Math.floor((i / n) * (valoresOrdenados.length - 1));
      q.push(valoresOrdenados[idx]);
    }
    return [...new Set(q)].sort((a, b) => a - b); // umbrales unicos
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
        mostrarDetalle(p, pob);
      },
      mouseout: (e) => { capa.resetStyle(e.target); },
      click: () => drillDown(p),
    });
  }

  function drillDown(p) {
    if (p.nivel === "provincia") {
      navegarA("canton", p.codigo, null, null);
    } else if (p.nivel === "canton") {
      navegarA("distrito", p.cod_provincia, p.codigo, null);
    }
    // distrito = nivel mas profundo: solo resalta el detalle (ya mostrado)
  }

  // Navegacion programatica unificada (drill-down, buscador y selects).
  async function navegarA(nivel, codProvincia, codCanton, codDistrito) {
    state.codProvincia = codProvincia || null;
    state.codCanton = codCanton || null;
    state.codDistrito = codDistrito || null;
    await dibujarNivel(nivel, codDistrito);
    sincronizarSelects();
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
      distrito: "Nivel más detallado. Pasa el cursor para ver cada distrito.",
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

    $("detalle").classList.add("d-none");
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
    const UNIDAD = {
      electoral: "habitantes",
      real: "residentes",
      diferencia: "migración (residencia − padrón)",
      abstencion: "abstenciones (estim.)",
      participacion: "votos (estim.)",
      extranjero: "residen en el exterior",
    };
    $("detalleUnidad").textContent = UNIDAD[state.metrica];

    // Porcentaje contextual (cuota del total o tasa sobre el padrón).
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
    if (p.nivel === "canton") extra = "Cantón · " + p.provincia;
    else if (p.nivel === "distrito") extra = "Distrito · " + p.canton + ", " + p.provincia;
    else extra = "Provincia";
    $("detalleExtra").textContent = extra;
    renderFlujo(p);
  }

  // Flujos del cruce padron/residencia (solo a nivel canton).
  function renderFlujo(p) {
    const flu = $("detalleFlujo");
    const reg = p.nivel === "canton" ? POB.cantones[p.codigo] : null;
    const sal = reg?.flujo_salida || [];
    const ent = reg?.flujo_entrada || [];
    if (!reg || (!sal.length && !ent.length)) {
      flu.classList.add("d-none");
      flu.innerHTML = "";
      return;
    }
    const chips = (arr) => arr
      .map((x) => `<span class="flujo-it">${x.nombre} · ${fmt(x.n)}</span>`).join("");
    let html = "";
    if (sal.length) {
      html += `<div class="flujo-line"><span class="flujo-lbl">Inscritos aquí, residen en</span>` +
              `<div class="flujo-items">${chips(sal)}</div></div>`;
    }
    if (ent.length) {
      html += `<div class="flujo-line"><span class="flujo-lbl">Residen aquí, inscritos en</span>` +
              `<div class="flujo-items">${chips(ent)}</div></div>`;
    }
    flu.innerHTML = html;
    flu.classList.remove("d-none");
  }

  function actualizarLeyenda() {
    const cont = $("legend");
    cont.innerHTML = "";
    const buckets = escala.length + 1;
    for (let i = 0; i < buckets; i++) {
      const lo = i === 0 ? null : escala[i - 1];
      const hi = i < escala.length ? escala[i] : null;
      let txt;
      if (esDif()) {
        if (lo === null) txt = `< ${abreviarS(hi)}`;
        else if (hi === null) txt = `> ${abreviarS(lo)}`;
        else txt = `${abreviarS(lo)} – ${abreviarS(hi)}`;
      } else if (esTasa()) {
        const desde = lo === null ? 0 : lo;
        txt = hi === null ? `> ${fmtPct(desde)}` : `${fmtPct(desde)} – ${fmtPct(hi)}`;
      } else {
        const desde = lo === null ? 0 : lo;
        txt = hi === null ? `> ${abreviar(desde)}` : `${abreviar(desde)} – ${abreviar(hi)}`;
      }
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
    }).addTo(map);
    baseLayer.bringToBack();
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
    if (capa) {
      capa.setStyle(estiloFeature);
      // reaplica el resaltado activo si lo hay
      if (state.codDistrito && capasPorCodigo[state.codDistrito]) {
        capasPorCodigo[state.codDistrito].setStyle({ weight: 3, color: cssVar("--stroke-hover"), fillOpacity: 1 });
      }
      actualizarLeyenda();
    }
  }

  // ---- Selector de metrica (padron / residencia / saldo) ----
  const AYUDA_METRICA = {
    electoral: "Población según el domicilio electoral (dónde está inscrita).",
    real: "Población según la residencia real simulada (dónde vive).",
    diferencia: "Migración = residencia − padrón. Azul: atrae residentes (dormitorio); rojo: los pierde.",
    abstencion: "Abstención estimada en elecciones pasadas (quienes no votaron).",
    participacion: "Participación estimada: inscritos que sí ejercieron el voto.",
    extranjero: "Inscritos que residen en el extranjero (diáspora, simulado).",
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

    // Click fuera cierra los popovers (desktop).
    document.addEventListener("click", (e) => {
      if (!nav.contains(e.target) && !toggle.contains(e.target)) cerrarDropdowns();
    });
    document.addEventListener("keydown", (e) => { if (e.key === "Escape") cerrarTodo(); });
    mq.addEventListener("change", (ev) => { if (!ev.matches) cerrarDrawer(); });

    // Análisis predisenados: aplican una métrica con vista nacional.
    nav.querySelectorAll("[data-analisis]").forEach((b) => {
      b.addEventListener("click", () => {
        navegarA("provincia", null, null, null);
        map.setView([9.75, -84.1], 8);
        seleccionarMetrica(b.dataset.analisis);
        cerrarTodo();
      });
    });

    // Admin: módulos aún sin backend → aviso.
    nav.querySelectorAll("[data-admin]").forEach((b) => {
      b.addEventListener("click", () => {
        toast(`${ADMIN_LABEL[b.dataset.admin]}: módulo en construcción.`);
        cerrarTodo();
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

  // Recolorea el nivel actual sin reajustar el encuadre del mapa.
  function aplicarMetrica() {
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

  // ---- Padron dummy + modal (DataTable) ----
  const NOMBRES = ["José", "María", "Luis", "Ana", "Carlos", "Marta", "Juan", "Laura",
    "Andrés", "Sofía", "Diego", "Carmen", "Manuel", "Rosa", "Pedro", "Elena", "Jorge",
    "Patricia", "Roberto", "Gabriela", "Francisco", "Daniela", "Miguel", "Adriana",
    "Rafael", "Natalia", "Fernando", "Paola", "Alberto", "Lucía", "Ricardo", "Verónica",
    "Eduardo", "Andrea", "Mauricio", "Karla", "Esteban", "Tatiana", "Óscar", "Melissa"];
  const APELLIDOS = ["Rodríguez", "González", "Jiménez", "Vargas", "Hernández", "Mora",
    "Castro", "Rojas", "Solís", "Araya", "Ramírez", "Chinchilla", "Quesada", "Villalobos",
    "Soto", "Brenes", "Alfaro", "Cordero", "Sánchez", "Fernández", "Camacho", "Arias",
    "Salas", "Núñez", "Calderón", "Fonseca", "Madrigal", "Vega", "Picado", "Murillo",
    "Ureña", "Barrantes", "Zúñiga", "Cambronero", "Montero", "Segura", "Bonilla",
    "Aguilar", "Campos", "Méndez"];

  const CAP_PADRON = 25000; // tope de filas generadas por region

  function hashStr(s) {
    let h = 2166136261 >>> 0;
    for (let i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 16777619); }
    return h >>> 0;
  }
  function mulberry32(a) {
    return function () {
      a |= 0; a = (a + 0x6D2B79F5) | 0;
      let t = Math.imul(a ^ (a >>> 15), 1 | a);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  function ctxRegion(p) {
    if (p.nivel === "distrito") return `${p.canton}, ${p.provincia}`;
    if (p.nivel === "canton") return p.provincia;
    return "Provincia";
  }

  const SECTORES = ["CENTRO", "NORTE", "SUR", "ESTE", "OESTE"];

  // Distritos que pertenecen a la region seleccionada (para el lugar de votacion).
  function distritosDeRegion(p) {
    if (p.nivel === "distrito") {
      const d = POB.distritos[p.codigo];
      return d ? [d] : [];
    }
    const campo = p.nivel === "canton" ? "cod_canton" : "cod_provincia";
    return Object.values(POB.distritos).filter((d) => d[campo] === p.codigo);
  }

  function generarPersona(p, i, distritos) {
    const rnd = mulberry32(hashStr(p.codigo + "-" + i));
    const pick = (arr) => arr[Math.floor(rnd() * arr.length)];
    let nombre = pick(NOMBRES);
    if (rnd() < 0.28) nombre += " " + pick(NOMBRES);
    const ap1 = pick(APELLIDOS), ap2 = pick(APELLIDOS);
    const edad = 18 + Math.floor(rnd() * 72);          // 18..89
    const anio = new Date().getFullYear() - edad;
    const mes = 1 + Math.floor(rnd() * 12);
    const dia = 1 + Math.floor(rnd() * 28);
    const provD = String(p.cod_provincia || p.codigo)[0];
    const c1 = 1000 + Math.floor(rnd() * 9000);
    const c2 = 1000 + Math.floor(rnd() * 9000);
    const fnac = `${String(dia).padStart(2, "0")}/${String(mes).padStart(2, "0")}/${anio}`;
    let estado;
    const e = rnd();
    if (edad < 22)        estado = e < 0.92 ? "Soltero/a" : "Casado/a";
    else if (edad < 35)   estado = e < 0.50 ? "Soltero/a" : (e < 0.88 ? "Casado/a" : (e < 0.96 ? "Divorciado/a" : "Viudo/a"));
    else if (edad < 60)   estado = e < 0.25 ? "Soltero/a" : (e < 0.70 ? "Casado/a" : (e < 0.90 ? "Divorciado/a" : "Viudo/a"));
    else                  estado = e < 0.15 ? "Soltero/a" : (e < 0.55 ? "Casado/a" : (e < 0.72 ? "Divorciado/a" : "Viudo/a"));
    const casado = estado === "Casado/a";
    const hijos = casado
      ? Math.floor(rnd() * 5)            // casados: 0..4
      : (rnd() < 0.7 ? 0 : 1 + Math.floor(rnd() * 2)); // resto: en su mayoria 0
    const d = distritos.length ? distritos[Math.floor(rnd() * distritos.length)] : null;
    const vProv    = (d ? d.provincia : p.provincia || p.nombre || "").toUpperCase();
    const vCanton  = (d ? d.canton : p.canton || p.nombre || "").toUpperCase();
    const vDistrito = (d ? d.nombre : p.nombre || "").toUpperCase();
    const vCentro  = d ? `${d.nombre} ${pick(SECTORES)}`.toUpperCase() : vDistrito;
    return {
      cedula: `${provD}-${c1}-${c2}`,
      nombre, ap1, ap2, edad, fnac, hijos, estado,
      vProv, vCanton, vDistrito, vCentro,
      _n: norm(`${provD}${c1}${c2} ${nombre} ${ap1} ${ap2}`),
    };
  }

  function padronTotal(p) {
    const tabla = { provincia: "provincias", canton: "cantones", distrito: "distritos" }[p.nivel];
    return POB[tabla][p.codigo].poblacion;   // el padron = inscritos (electoral)
  }

  const padron = { data: [], filtrado: [], page: 0, size: 25, total: 0, truncado: false, p: null };

  function abrirPadron() {
    const p = seleccionActual;
    if (!p) return;
    const total = padronTotal(p);
    const n = Math.min(total, CAP_PADRON);
    const distritos = distritosDeRegion(p);
    const data = new Array(n);
    for (let i = 0; i < n; i++) data[i] = generarPersona(p, i, distritos);

    padron.data = data;
    padron.filtrado = data;
    padron.page = 0;
    padron.size = parseInt($("padronPageSize").value, 10) || 25;
    padron.total = total;
    padron.truncado = total > CAP_PADRON;
    padron.p = p;

    $("padronTitulo").textContent = "Padrón · " + p.nombre;
    $("padronSub").textContent = ctxRegion(p) + " · " + fmt(total) + " inscritos" +
      (padron.truncado ? ` (mostrando primeros ${fmt(n)})` : "");
    $("padronBuscar").value = "";
    renderPadron();
    $("padronModal").classList.remove("d-none");
  }

  function cerrarPadron() { $("padronModal").classList.add("d-none"); }

  function filtrarPadron(q) {
    const n = norm(q.trim());
    padron.filtrado = n ? padron.data.filter((x) => x._n.includes(n)) : padron.data;
    padron.page = 0;
    renderPadron();
  }

  function renderPadron() {
    const { filtrado, size } = padron;
    const pages = Math.max(1, Math.ceil(filtrado.length / size));
    const pg = Math.min(padron.page, pages - 1);
    padron.page = pg;
    const start = pg * size;
    const slice = filtrado.slice(start, start + size);

    const body = $("padronBody");
    body.innerHTML = "";
    const frag = document.createDocumentFragment();
    slice.forEach((x) => {
      const tr = document.createElement("tr");
      tr.innerHTML =
        `<td class="mono">${x.cedula}</td><td>${x.nombre}</td>` +
        `<td>${x.ap1} ${x.ap2}</td><td>${x.edad}</td>` +
        `<td class="mono">${x.fnac}</td>` +
        `<td>${x.hijos}</td><td>${x.estado}</td>` +
        `<td>${x.vProv}</td><td>${x.vCanton}</td>` +
        `<td>${x.vDistrito}</td><td>${x.vCentro}</td>`;
      frag.appendChild(tr);
    });
    body.appendChild(frag);

    $("padronInfo").textContent = filtrado.length
      ? `${fmt(start + 1)}–${fmt(start + slice.length)} de ${fmt(filtrado.length)}`
      : "Sin coincidencias";
    $("pgNow").textContent = `Pág. ${pg + 1} / ${pages}`;
    $("pgFirst").disabled = $("pgPrev").disabled = pg === 0;
    $("pgLast").disabled = $("pgNext").disabled = pg >= pages - 1;
  }

  // Exporta el conjunto filtrado a CSV (lo abre Excel directamente).
  function exportarPadron() {
    const rows = padron.filtrado;
    if (!rows.length) return;
    const head = ["Cédula", "Nombre", "Apellidos", "Edad", "Fecha de nacimiento", "Hijos", "Estado civil", "Provincia", "Cantón", "Distrito", "Centro de votación"];
    const q = (s) => '"' + String(s).replace(/"/g, '""') + '"';
    const lineas = ["sep=,", head.map(q).join(",")];
    rows.forEach((x) => {
      lineas.push([x.cedula, x.nombre, `${x.ap1} ${x.ap2}`, x.edad, x.fnac, x.hijos, x.estado, x.vProv, x.vCanton, x.vDistrito, x.vCentro].map(q).join(","));
    });
    // BOM para que Excel respete los acentos.
    const blob = new Blob(["﻿" + lineas.join("\r\n")], { type: "text/csv;charset=utf-8" });
    const slug = norm(padron.p?.nombre || "region").replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `padron-${slug}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(a.href);
  }

  function irPagina(n) {
    const pages = Math.max(1, Math.ceil(padron.filtrado.length / padron.size));
    padron.page = Math.max(0, Math.min(n, pages - 1));
    renderPadron();
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
      padron.page = 0;
      renderPadron();
    });
    $("pgFirst").addEventListener("click", () => irPagina(0));
    $("pgPrev").addEventListener("click", () => irPagina(padron.page - 1));
    $("pgNext").addEventListener("click", () => irPagina(padron.page + 1));
    $("pgLast").addEventListener("click", () => irPagina(Infinity));
    $("btnExport").addEventListener("click", exportarPadron);
  }

  function mostrarLoader() { $("loader").classList.remove("hidden"); }
  function ocultarLoader() { $("loader").classList.add("hidden"); }

  document.addEventListener("DOMContentLoaded", init);
})();
