"use strict";
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

  // ---- Panel de filtros (bottom sheet en móvil) ----
  function setupFilters() {
    const btn = $("btnFilters");
    if (!btn) return;
    // Solo mostrar el botón de filtros en el reporte 1 (padrón-distribución)
    if (window.ACTIVE_REPORT_DB === 1) document.body.classList.add("show-filters-btn");
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

