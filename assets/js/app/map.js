"use strict";
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

