"use strict";
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

  const JRV_STAT_IDS = ["jrvStatJuntas","jrvStatTotal","jrvStatProm","jrvStatMax","jrvStatMin"];

  function jrvStatLoading(on) {
    JRV_STAT_IDS.forEach(id => {
      const el = $(id);
      if (!el) return;
      el.classList.toggle("stat-loading", on);
      if (on) el.textContent = "—";
    });
  }

  async function cargarJrv() {
    if (jrv.loading) return;
    jrv.loading = true;
    jrvStatLoading(true);
    $("jrvBody").innerHTML = `<tr class="tbl-spinner-row"><td colspan="7"><span class="tbl-spinner"></span>Consultando base de datos…</td></tr>`;
    try {
      const r = await fetchJSON("api/jrv.php?" + jrvParams());
      jrv.total  = r.total;
      jrv.pages  = r.pages;
      jrv.page   = r.page;
      jrv.maxInscritos = r.stats.max_inscritos || 1;

      jrvStatLoading(false);
      $("jrvStatJuntas").textContent = fmt(r.stats.juntas);
      $("jrvStatTotal").textContent  = fmt(r.stats.total_inscritos);
      $("jrvStatProm").textContent   = fmt(r.stats.promedio);
      $("jrvStatMax").textContent    = fmt(r.stats.max_inscritos);
      $("jrvStatMin").textContent    = fmt(r.stats.min_inscritos);

      renderJrv(r.rows, r.page, r.size);
      renderJrvPager(r.page, r.pages, r.total);
    } catch (e) {
      jrvStatLoading(false);
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

    const jrvExp = $("jrvExportar");
    if (jrvExp) {
      jrvExp.addEventListener("click", () => {
        window.open("api/jrv.php?" + jrvParams() + "&format=csv");
        logEvento("reporte_exportar", "JRV CSV", { filtros: jrvParams() });
      });
    }
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // SEGMENTACIÓN TERRITORIAL — TSE-style (api/segmentacion.php stats_only)
  // ─────────────────────────────────────────────────────────────────────────────
  const seg = {
    provinceId: null, cantonId: null, districtGeo5: null,
    _inicializado: false, _sexChart: null,
  };

  function abrirReporteSegmentacion() {
    activarReporte("segmentacion");
    logEvento("reporte_abrir", "Segmentación Electoral", {});
    if (!seg._inicializado) { setupSegmentacion(); seg._inicializado = true; }
    cargarSegmentacion();
  }

  function setupSegmentacion() {
    const provList = $("sfProvList");
    if (POB && POB.provincias) {
      Object.entries(POB.provincias)
        .sort((a, b) => parseInt(a[0]) - parseInt(b[0]))
        .forEach(([id, p]) => {
          const li = document.createElement("li");
          li.className = "tse-filter-item";
          li.dataset.id = id;
          li.innerHTML = `<span class="tse-chk"></span>${id} ${p.nombre}`;
          li.addEventListener("click", () => sfSelectProvincia(id, li));
          provList.appendChild(li);
        });
    }
    $("sfBorrarFiltros").addEventListener("click", () => {
      seg.provinceId = null; seg.cantonId = null; seg.districtGeo5 = null;
      document.querySelectorAll("#sfProvList .tse-filter-item").forEach(el => el.classList.remove("tse-sel"));
      $("sfCantList").innerHTML = '<li class="tse-filter-item-empty">Selecciona provincia</li>';
      $("sfDistList").innerHTML = '<li class="tse-filter-item-empty">Selecciona cantón</li>';
      cargarSegmentacion();
    });
    $("segExportar").addEventListener("click", () => {
      const nivel = seg.districtGeo5 ? "district" : seg.cantonId ? "district" : seg.provinceId ? "canton" : "province";
      const p = new URLSearchParams({ nivel, format: "csv" });
      if (seg.provinceId)   p.set("province_id",  String(seg.provinceId));
      if (seg.cantonId)     p.set("canton_id",     String(seg.cantonId));
      if (seg.districtGeo5) p.set("district_geo5", seg.districtGeo5);
      window.open("api/segmentacion.php?" + p.toString());
      logEvento("reporte_exportar", "Segmentación CSV", {});
    });
  }

  function sfSelectProvincia(id, li) {
    const was = li.classList.contains("tse-sel");
    document.querySelectorAll("#sfProvList .tse-filter-item").forEach(el => el.classList.remove("tse-sel"));
    $("sfCantList").innerHTML = '<li class="tse-filter-item-empty">Selecciona provincia</li>';
    $("sfDistList").innerHTML = '<li class="tse-filter-item-empty">Selecciona cantón</li>';
    seg.cantonId = null; seg.districtGeo5 = null;
    seg.provinceId = was ? null : (li.classList.add("tse-sel"), parseInt(id));
    if (!was) sfPoblarCantones(id);
    cargarSegmentacion();
  }

  function sfSelectCanton(cantonId, li) {
    const was = li.classList.contains("tse-sel");
    document.querySelectorAll("#sfCantList .tse-filter-item").forEach(el => el.classList.remove("tse-sel"));
    $("sfDistList").innerHTML = '<li class="tse-filter-item-empty">Selecciona cantón</li>';
    seg.districtGeo5 = null;
    seg.cantonId = was ? null : (li.classList.add("tse-sel"), parseInt(cantonId));
    if (!was) sfPoblarDistritos(cantonId);
    cargarSegmentacion();
  }

  function sfSelectDistrito(geo5, li) {
    const was = li.classList.contains("tse-sel");
    document.querySelectorAll("#sfDistList .tse-filter-item").forEach(el => el.classList.remove("tse-sel"));
    seg.districtGeo5 = was ? null : geo5;
    if (!was) li.classList.add("tse-sel");
    cargarSegmentacion();
  }

  function sfPoblarCantones(provinceId) {
    const list = $("sfCantList");
    list.innerHTML = "";
    if (!POB || !POB.cantones) return;
    Object.entries(POB.cantones)
      .filter(([, c]) => String(c.cod_provincia) === String(provinceId))
      .sort((a, b) => a[1].nombre.localeCompare(b[1].nombre))
      .forEach(([id, c]) => {
        const li = document.createElement("li");
        li.className = "tse-filter-item";
        li.innerHTML = `<span class="tse-chk"></span>${id} ${c.nombre}`;
        li.addEventListener("click", () => sfSelectCanton(id, li));
        list.appendChild(li);
      });
    if (!list.children.length) list.innerHTML = '<li class="tse-filter-item-empty">Sin cantones</li>';
  }

  function sfPoblarDistritos(cantonId) {
    const list = $("sfDistList");
    list.innerHTML = "";
    if (!POB || !POB.distritos) return;
    Object.entries(POB.distritos)
      .filter(([, d]) => String(d.cod_canton) === String(cantonId))
      .sort((a, b) => a[1].nombre.localeCompare(b[1].nombre))
      .forEach(([geo5, d]) => {
        const li = document.createElement("li");
        li.className = "tse-filter-item";
        li.innerHTML = `<span class="tse-chk"></span>${geo5} ${d.nombre}`;
        li.addEventListener("click", () => sfSelectDistrito(geo5, li));
        list.appendChild(li);
      });
    if (!list.children.length) list.innerHTML = '<li class="tse-filter-item-empty">Sin distritos</li>';
  }

  async function cargarSegmentacion() {
    $("sfElectorado").textContent = "…";
    const nivel = seg.districtGeo5 ? "district" : seg.cantonId ? "district" : seg.provinceId ? "canton" : "province";
    const p = new URLSearchParams({ nivel, stats_only: "1" });
    if (seg.provinceId)   p.set("province_id",  String(seg.provinceId));
    if (seg.cantonId)     p.set("canton_id",     String(seg.cantonId));
    if (seg.districtGeo5) p.set("district_geo5", seg.districtGeo5);
    try {
      const data = await fetchJSON("api/segmentacion.php?" + p.toString());
      renderSegmentacion(data.stats || {});
    } catch(e) {
      $("sfElectorado").textContent = "Error";
      console.error(e);
    }
  }

  function renderSegmentacion(s) {
    const m   = s.filtered_m ?? s.total_m ?? 0;
    const f   = s.filtered_f ?? s.total_f ?? 0;
    const n   = s.filtered_n ?? s.total_n ?? 0;
    const tot = s.total_inscritos || (m + f + n) || 1;
    const pctM = s.pct_filtered_m ?? s.pct_m ?? 0;
    const pctF = s.pct_filtered_f ?? s.pct_f ?? 0;

    $("sfElectorado").textContent = fmt(tot);
    $("sfHombre").textContent     = fmt(m);
    $("sfMujer").textContent      = fmt(f);
    $("sfSinDato").textContent    = fmt(n);
    $("sfPctH").textContent       = pctM + "%";
    $("sfPctM").textContent       = pctF + "%";

    renderSegCharts(s);
  }

  function renderSegCharts(stats) {
    const m = stats.filtered_m ?? stats.total_m ?? 0;
    const f = stats.filtered_f ?? stats.total_f ?? 0;
    const n = stats.filtered_n ?? stats.total_n ?? 0;

    const canvas = document.getElementById("segSexChart");
    if (!canvas || typeof Chart === "undefined") return;
    const isDark = document.documentElement.dataset.theme === "dark";

    if (seg._sexChart) {
      seg._sexChart.data.datasets[0].data = [m, f, n];
      seg._sexChart.data.datasets[0].borderColor = isDark ? "#141416" : "#ffffff";
      seg._sexChart.update("none");
    } else {
      seg._sexChart = new Chart(canvas.getContext("2d"), {
        type: "doughnut",
        data: {
          labels: ["Hombre", "Mujer", "Sin clasificar"],
          datasets: [{
            data: [m, f, n],
            backgroundColor: ["#3b82f6", "#ec4899", "#9ca3af"],
            borderWidth: 2,
            borderColor: isDark ? "#141416" : "#ffffff",
            hoverOffset: 5,
          }],
        },
        options: {
          cutout: "65%",
          responsive: true,
          maintainAspectRatio: true,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: ctx => " " + fmt(ctx.raw) + " (" + ((ctx.raw / ((m+f+n)||1))*100).toFixed(1) + "%)",
              },
            },
          },
        },
      });
    }
  }

  function abrirPadronConFiltro(nivel, codigo, nombre, totalConocido, ctx) {
    const p = { nivel, codigo, nombre };
    seleccionActual = p;
    padron.rows = []; padron.page = 1;
    padron.size = parseInt($("padronPageSize").value, 10) || 25;
    padron.total = totalConocido || 0;
    padron.pages = 1; padron.estimated = false; padron.q = ""; padron.p = p;
    $("padronTitulo").textContent = "Padrón · " + nombre;
    $("padronSub").textContent = (ctx || ctxRegion(p)) + " · cargando padrón real";
    $("padronBuscar").value = "";
    $("padronModal").classList.remove("d-none");
    renderPadronLoading();
    cargarPadron();
    logEvento("padron_abrir", "Padrón · " + nombre, { nivel, codigo, total: totalConocido });
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

  // ─────────────────────────────────────────────────────────────────────────────
  // PARTICIPACIÓN ELECTORAL — server-side (api/participacion.php)
  // ─────────────────────────────────────────────────────────────────────────────
  const part = {
    nivel: "province", provinceId: null, cantonId: null,
    runId: null, // null = usar el más reciente
    page: 1, pages: 1, total: 0, size: 25, order: "desc", q: "",
    _inicializado: false, _qTimer: null,
  };

  const PART_STAT_IDS = ["partStatPart","partStatAbs","partStatVotos","partStatInscritos","partStatMax","partStatMin"];

  function partStatLoading(on) {
    PART_STAT_IDS.forEach(id => {
      const el = $(id); if (!el) return;
      el.classList.toggle("stat-loading", on);
      if (on) el.textContent = "—";
    });
  }

  function abrirReporteParticipacion() {
    activarReporte("participacion");
    logEvento("reporte_abrir", "Participación Electoral", {});
    if (!part._inicializado) { setupParticipacion(); part._inicializado = true; }
    cargarParticipacion();
  }

  function setupParticipacion() {
    document.querySelectorAll("[data-part-tab]").forEach(btn => {
      btn.addEventListener("click", () => {
        document.querySelectorAll("[data-part-tab]").forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        part.nivel = btn.dataset.partTab;
        part.provinceId = null; part.cantonId = null; part.page = 1; part.q = "";
        $("partBuscador").value = "";
        $("partFiltProv").value = "";
        $("partFiltCant").value = "";
        $("partFiltCant").disabled = true;
        cargarParticipacion();
      });
    });

    // Filtro provincia → cantones en cascada
    const selProv = $("partFiltProv");
    if (POB && POB.provincias) {
      Object.entries(POB.provincias)
        .sort((a, b) => a[1].nombre.localeCompare(b[1].nombre))
        .forEach(([id, p]) => {
          const o = document.createElement("option");
          o.value = id; o.textContent = p.nombre;
          selProv.appendChild(o);
        });
    }
    selProv.addEventListener("change", () => {
      part.provinceId = selProv.value ? parseInt(selProv.value) : null;
      part.cantonId = null; part.page = 1;
      $("partFiltCant").value = "";
      poblarCantonesReporte("partFiltCant", part.provinceId);
      cargarParticipacion();
    });
    $("partFiltCant").addEventListener("change", () => {
      part.cantonId = $("partFiltCant").value ? parseInt($("partFiltCant").value) : null;
      part.page = 1; cargarParticipacion();
    });
    $("partBuscador").addEventListener("input", () => {
      clearTimeout(part._qTimer);
      part._qTimer = setTimeout(() => { part.q = $("partBuscador").value.trim(); part.page = 1; cargarParticipacion(); }, 300);
    });
    $("partOrdDesc").addEventListener("click", () => {
      $("partOrdDesc").classList.add("active"); $("partOrdAsc").classList.remove("active");
      part.order = "desc"; part.page = 1; cargarParticipacion();
    });
    $("partOrdAsc").addEventListener("click", () => {
      $("partOrdAsc").classList.add("active"); $("partOrdDesc").classList.remove("active");
      part.order = "asc"; part.page = 1; cargarParticipacion();
    });
    $("partPageSize").addEventListener("change", () => {
      part.size = parseInt($("partPageSize").value) || 25; part.page = 1; cargarParticipacion();
    });
    $("partExportar").addEventListener("click", () => {
      const p = partParams(); p.set("format","csv");
      window.open("api/participacion.php?" + p.toString());
      logEvento("reporte_exportar", "Participación CSV", { nivel: part.nivel });
    });
    $("partFiltEleccion").addEventListener("change", () => {
      const v = $("partFiltEleccion").value;
      part.runId = v ? parseInt(v) : null;
      part.page = 1; part.provinceId = null; part.cantonId = null;
      $("partFiltProv").value = ""; $("partFiltCant").value = "";
      $("partFiltCant").disabled = true;
      cargarParticipacion();
    });
    $("partFirst").addEventListener("click", () => { part.page = 1; cargarParticipacion(); });
    $("partPrev").addEventListener("click",  () => { part.page--; cargarParticipacion(); });
    $("partNext").addEventListener("click",  () => { part.page++; cargarParticipacion(); });
    $("partLast").addEventListener("click",  () => { part.page = part.pages; cargarParticipacion(); });
    $("partTogglePartidos")?.addEventListener("click", () => {
      const panel = $("partPartidosPanel");
      panel?.classList.toggle("d-none");
      const btn = $("partTogglePartidos");
      if (btn) btn.innerHTML = panel?.classList.contains("d-none")
        ? `<i class="bi bi-bar-chart-fill"></i> Ver desglose`
        : `<i class="bi bi-chevron-up"></i> Ocultar`;
    });
  }

  function poblarCantonesReporte(selectId, provinceId) {
    const sel = $(selectId);
    sel.innerHTML = '<option value="">Todos los cantones</option>';
    sel.disabled = !provinceId;
    if (!provinceId || !POB || !POB.cantones) return;
    const pId = String(provinceId);
    Object.entries(POB.cantones)
      .filter(([, c]) => String(c.cod_provincia) === pId)
      .sort((a, b) => a[1].nombre.localeCompare(b[1].nombre))
      .forEach(([id, c]) => {
        const o = document.createElement("option");
        o.value = id; o.textContent = c.nombre;
        sel.appendChild(o);
      });
  }

  function partParams() {
    const p = new URLSearchParams({ nivel: part.nivel, page: String(part.page), size: String(part.size), order: part.order });
    if (part.runId)      p.set("run_id",      String(part.runId));
    if (part.provinceId) p.set("province_id", String(part.provinceId));
    if (part.cantonId)   p.set("canton_id",   String(part.cantonId));
    if (part.q)          p.set("q", part.q);
    return p;
  }

  function actualizarCabecerasPart() {
    const th1 = $("partThSub1"), th2 = $("partThSub2");
    if (part.nivel === "province") {
      $("partThNombre").textContent = "Provincia";
      th1.classList.add("d-none"); th2.classList.add("d-none");
    } else if (part.nivel === "canton") {
      $("partThNombre").textContent = "Cantón";
      th1.textContent = "Provincia"; th1.classList.remove("d-none"); th2.classList.add("d-none");
    } else {
      $("partThNombre").textContent = "Distrito";
      th1.textContent = "Cantón"; th1.classList.remove("d-none");
      th2.textContent = "Provincia"; th2.classList.remove("d-none");
    }
    const lblMap = { province: "Mayor provincia", canton: "Mayor cantón", district: "Mayor distrito" };
    $("partStatMaxLbl").textContent = lblMap[part.nivel] || "Mayor";
    $("partStatMinLbl").textContent = lblMap[part.nivel]?.replace("Mayor","Menor") || "Menor";
  }

  async function cargarParticipacion() {
    partStatLoading(true);
    $("partBody").innerHTML = `<tr class="tbl-spinner-row"><td colspan="9"><span class="tbl-spinner"></span>Consultando resultados electorales…</td></tr>`;
    try {
      const data = await fetchJSON("api/participacion.php?" + partParams().toString());
      if (data.error) {
        partStatLoading(false);
        $("partBody").innerHTML = `<tr><td colspan="9" class="bita-empty">${esc(data.error)}</td></tr>`;
        return;
      }
      part.pages = data.pages || 1; part.page = data.page || 1; part.total = data.total || 0;
      if (data.meta) $("partMetaLabel").textContent = data.meta.election_label || "";
      // Poblar selector de elecciones (solo la primera vez)
      if (data.elections?.length && !part._eleccionesOk) {
        const sel = $("partFiltEleccion");
        sel.innerHTML = "";
        data.elections.forEach(e => {
          const o = document.createElement("option");
          o.value = e.id; o.textContent = e.election_label + " (" + (e.election_date || "—") + ")";
          if (parseInt(e.id) === data.run_id) o.selected = true;
          sel.appendChild(o);
        });
        part._eleccionesOk = true;
      }
      partStatLoading(false);
      renderParticipacion(data);
    } catch(e) {
      partStatLoading(false);
      $("partBody").innerHTML = `<tr><td colspan="9" class="bita-empty">Error al cargar datos.</td></tr>`;
      console.error(e);
    }
  }

  function renderParticipacion(data) {
    actualizarCabecerasPart();
    const s = data.stats || {};
    $("partStatPart").textContent     = s.pct_part_global ? s.pct_part_global + "%" : "—";
    $("partStatAbs").textContent      = s.pct_part_global ? (100 - parseFloat(s.pct_part_global)).toFixed(2) + "%" : "—";
    $("partStatVotos").textContent    = fmt(parseInt(s.total_votos)     || 0);
    $("partStatInscritos").textContent= fmt(parseInt(s.total_inscritos) || 0);
    $("partStatMax").textContent      = s.max_part ? s.max_part + "%" : "—";
    $("partStatMin").textContent      = s.min_part ? s.min_part + "%" : "—";

    const rows   = data.rows || [];
    const offset = (part.page - 1) * part.size;
    const body   = $("partBody");

    if (!rows.length) {
      body.innerHTML = `<tr><td colspan="9" class="bita-empty">Sin resultados.</td></tr>`;
    } else {
      const maxPart = rows.reduce((m, r) => Math.max(m, r.pct_participacion), 0) || 1;
      body.innerHTML = rows.map((r, i) => {
        const idx    = offset + i + 1;
        const barW   = Math.round(r.pct_participacion / 100 * 100);
        const barCls = r.pct_participacion >= 70 ? "jrv-bar part-high"
                     : r.pct_participacion >= 50 ? "jrv-bar part-mid"
                     : "jrv-bar part-low";
        const drillNivel  = part.nivel === "province" ? "provincia" : part.nivel === "canton" ? "canton" : "distrito";
        const drillCodigo = part.nivel === "district" ? (r.geo5 || String(r.geo_id)) : String(r.geo_id);
        const drillCtx    = part.nivel === "canton" ? esc(r.provincia)
                          : part.nivel === "district" ? esc(r.canton) + ", " + esc(r.provincia)
                          : "Provincia";
        const sub1 = part.nivel === "canton"   ? `<td class="muted" style="font-size:.8rem">${esc(r.provincia)}</td>` : "";
        const sub2 = part.nivel === "district" ? `<td class="muted" style="font-size:.8rem">${esc(r.canton)}</td><td class="muted" style="font-size:.8rem">${esc(r.provincia)}</td>` : "";
        return `<tr>
          <td class="col-num muted">${idx}</td>
          <td><strong>${esc(r.geo_name)}</strong></td>
          ${sub1}${sub2}
          <td class="col-num"><strong style="color:${r.pct_participacion>=60?'var(--accent)':'var(--text)'}">${r.pct_participacion}%</strong></td>
          <td class="col-num muted">${r.pct_abstencion}%</td>
          <td class="col-num seg-col-drill">
            <a href="#" data-drill-nivel="${drillNivel}" data-drill-codigo="${drillCodigo}"
               data-drill-nombre="${esc(r.geo_name)}" data-drill-total="${r.inscritos}"
               data-drill-ctx="${drillCtx}" title="Ver inscritos en padrón">
              <strong>${fmt(r.inscritos)}</strong>
            </a>
          </td>
          <td class="col-num muted">${fmt(r.votos_emitidos)}</td>
          <td class="col-bar"><div class="${barCls}" style="width:${barW}%"></div></td>
        </tr>`;
      }).join("");
      body.querySelectorAll("a[data-drill-nivel]").forEach(a => {
        a.addEventListener("click", e => {
          e.preventDefault();
          abrirPadronConFiltro(a.dataset.drillNivel, a.dataset.drillCodigo,
            a.dataset.drillNombre, parseInt(a.dataset.drillTotal,10), a.dataset.drillCtx);
        });
      });
    }
    $("partFirst").disabled = part.page <= 1;
    $("partPrev").disabled  = part.page <= 1;
    $("partNext").disabled  = part.page >= part.pages;
    $("partLast").disabled  = part.page >= part.pages;
    $("partPages").textContent = "Pág. " + part.page + " / " + part.pages;
    $("partTotal").textContent = fmt(part.total) + " registros";

    // Desglose por partido
    renderPartPartidos(data);
  }

  function renderPartPartidos(data) {
    const breakdown = data.party_breakdown;
    const wrap = $("partPartidosWrap");
    if (!breakdown?.length) { if (wrap) wrap.classList.add("d-none"); return; }
    if (wrap) wrap.classList.remove("d-none");
    const totalVotos = breakdown.reduce((s, p) => s + p.votes, 0) || 1;
    const lbl = $("partPartidosLabel");
    if (lbl) lbl.textContent = `· ${fmt(breakdown.length)} partidos · ${fmt(totalVotos)} votos válidos`;
    const panel = $("partPartidosPanel");
    const body  = $("partPartidosBody");
    if (!panel || !body) return;
    body.innerHTML = breakdown.map(p => {
      const pct = (p.votes / totalVotos * 100).toFixed(1);
      const isPEL = p.code === 249;
      const barColor = isPEL ? "var(--brand-yellow)" : "var(--accent)";
      return `<div style="display:grid;grid-template-columns:80px 1fr 70px 90px;gap:.4rem;align-items:center;font-size:.82rem">
        <span title="${esc(p.name)}" style="font-weight:600;${isPEL?'color:var(--brand-navy);background:var(--brand-yellow);padding:0 4px;border-radius:3px;':''}">${esc(p.abbrev)}</span>
        <div style="background:var(--border-color);border-radius:3px;height:14px;overflow:hidden">
          <div style="background:${barColor};height:100%;width:${pct}%;min-width:2px"></div>
        </div>
        <span class="muted" style="text-align:right">${pct}%</span>
        <span class="muted" style="text-align:right">${fmt(p.votes)}</span>
      </div>`;
    }).join("");
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // ANÁLISIS TERRITORIAL — comparativa entre elecciones (api/analisis_territorial.php)
  // ─────────────────────────────────────────────────────────────────────────────
  const at = {
    nivel: "canton", provinceId: null, cantonId: null,
    runA: null, runB: null,
    sort: "delta", order: "desc",
    page: 1, pages: 1, total: 0, size: 25, q: "",
    _inicializado: false, _qTimer: null,
  };

  const AT_STAT_IDS = ["atStatPartA","atStatPartB","atStatDelta","atStatTerritorios","atStatMaxDelta","atStatMinDelta"];

  function atStatLoading(on) {
    AT_STAT_IDS.forEach(id => {
      const el = $(id); if (!el) return;
      el.classList.toggle("stat-loading", on);
      if (on) el.textContent = "—";
    });
  }

  function abrirAnalisisTerritorial() {
    activarReporte("analisis-territorial");
    logEvento("reporte_abrir", "Análisis Territorial", {});
    if (!at._inicializado) { setupAnalisisTerritorial(); at._inicializado = true; }
    cargarAnalisisTerritorial();
  }

  function setupAnalisisTerritorial() {
    document.querySelectorAll("[data-at-tab]").forEach(btn => {
      btn.addEventListener("click", () => {
        document.querySelectorAll("[data-at-tab]").forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        at.nivel = btn.dataset.atTab;
        at.provinceId = null; at.cantonId = null; at.page = 1; at.q = "";
        $("atBuscador").value = "";
        $("atFiltProv").value = "";
        $("atFiltCant").value = "";
        $("atFiltCant").disabled = true;
        cargarAnalisisTerritorial();
      });
    });

    const selProv = $("atFiltProv");
    if (POB && POB.provincias) {
      Object.entries(POB.provincias)
        .sort((a, b) => a[1].nombre.localeCompare(b[1].nombre))
        .forEach(([id, p]) => {
          const o = document.createElement("option");
          o.value = id; o.textContent = p.nombre;
          selProv.appendChild(o);
        });
    }
    selProv.addEventListener("change", () => {
      at.provinceId = selProv.value ? parseInt(selProv.value) : null;
      at.cantonId = null; at.page = 1;
      $("atFiltCant").value = "";
      poblarCantonesReporte("atFiltCant", at.provinceId);
      cargarAnalisisTerritorial();
    });
    $("atFiltCant").addEventListener("change", () => {
      at.cantonId = $("atFiltCant").value ? parseInt($("atFiltCant").value) : null;
      at.page = 1; cargarAnalisisTerritorial();
    });
    $("atFiltEleccionA").addEventListener("change", () => {
      at.runA = $("atFiltEleccionA").value ? parseInt($("atFiltEleccionA").value) : null;
      at.page = 1; cargarAnalisisTerritorial();
    });
    $("atFiltEleccionB").addEventListener("change", () => {
      at.runB = $("atFiltEleccionB").value ? parseInt($("atFiltEleccionB").value) : null;
      at.page = 1; cargarAnalisisTerritorial();
    });
    $("atBuscador").addEventListener("input", () => {
      clearTimeout(at._qTimer);
      at._qTimer = setTimeout(() => { at.q = $("atBuscador").value.trim(); at.page = 1; cargarAnalisisTerritorial(); }, 300);
    });
    $("atOrdDesc").addEventListener("click", () => {
      $("atOrdDesc").classList.add("active"); $("atOrdAsc").classList.remove("active");
      at.order = "desc"; at.page = 1; cargarAnalisisTerritorial();
    });
    $("atOrdAsc").addEventListener("click", () => {
      $("atOrdAsc").classList.add("active"); $("atOrdDesc").classList.remove("active");
      at.order = "asc"; at.page = 1; cargarAnalisisTerritorial();
    });
    ["atSortPartA","atSortPartB","atSortDelta"].forEach(id => {
      $(id)?.addEventListener("click", e => {
        e.preventDefault();
        const sortMap = {atSortPartA:"part_a", atSortPartB:"part_b", atSortDelta:"delta"};
        at.sort = sortMap[id]; at.page = 1;
        document.querySelectorAll("#atSortPartA,#atSortPartB,#atSortDelta").forEach(el => el.classList.remove("active"));
        $(id).classList.add("active");
        cargarAnalisisTerritorial();
      });
    });
    $("atPageSize").addEventListener("change", () => {
      at.size = parseInt($("atPageSize").value) || 25; at.page = 1; cargarAnalisisTerritorial();
    });
    $("atExportar").addEventListener("click", () => {
      const p = atParams(); p.set("format","csv");
      window.open("api/analisis_territorial.php?" + p.toString());
      logEvento("reporte_exportar", "Análisis Territorial CSV", { nivel: at.nivel });
    });
    $("atFirst").addEventListener("click", () => { at.page = 1; cargarAnalisisTerritorial(); });
    $("atPrev").addEventListener("click",  () => { at.page--; cargarAnalisisTerritorial(); });
    $("atNext").addEventListener("click",  () => { at.page++; cargarAnalisisTerritorial(); });
    $("atLast").addEventListener("click",  () => { at.page = at.pages; cargarAnalisisTerritorial(); });
  }

  function atParams() {
    const p = new URLSearchParams({ nivel: at.nivel, page: String(at.page), size: String(at.size), order: at.order, sort: at.sort });
    if (at.runA) p.set("run_a", String(at.runA));
    if (at.runB) p.set("run_b", String(at.runB));
    if (at.provinceId) p.set("province_id", String(at.provinceId));
    if (at.cantonId && at.nivel === "district") p.set("canton_id", String(at.cantonId));
    if (at.q) p.set("q", at.q);
    return p;
  }

  async function cargarAnalisisTerritorial() {
    atStatLoading(true);
    $("atBody").innerHTML = `<tr class="tbl-spinner-row"><td colspan="9"><span class="tbl-spinner"></span>Comparando resultados electorales…</td></tr>`;
    try {
      const data = await fetchJSON("api/analisis_territorial.php?" + atParams().toString());
      if (data.error) {
        atStatLoading(false);
        $("atBody").innerHTML = `<tr><td colspan="9" class="bita-empty">${esc(data.error)}</td></tr>`;
        return;
      }
      at.pages = data.pages || 1; at.page = data.page || 1; at.total = data.total || 0;

      // Actualizar etiquetas de las elecciones
      const lblA = data.meta_a?.election_label || "Elección A";
      const lblB = data.meta_b?.election_label || "Elección B";
      $("atMetaLabel").textContent = lblA + " vs " + lblB;
      $("atStatPartALbl").textContent = "Part. " + (data.meta_a?.election_date?.substring(0,4) || "A");
      $("atStatPartBLbl").textContent = "Part. " + (data.meta_b?.election_date?.substring(0,4) || "B");
      $("atSortPartA").textContent = "% " + (data.meta_a?.election_date?.substring(0,4) || "A");
      $("atSortPartB").textContent = "% " + (data.meta_b?.election_date?.substring(0,4) || "B");

      // Poblar selectores de elección (solo primera vez)
      if (data.elections?.length && !at._eleccionesOk) {
        const selA = $("atFiltEleccionA"), selB = $("atFiltEleccionB");
        selA.innerHTML = ""; selB.innerHTML = "";
        data.elections.forEach(e => {
          const lbl = e.election_label + " (" + (e.election_date?.substring(0,4) || "—") + ")";
          const oA = document.createElement("option"); oA.value = e.id; oA.textContent = lbl;
          const oB = document.createElement("option"); oB.value = e.id; oB.textContent = lbl;
          if (parseInt(e.id) === data.run_a) oA.selected = true;
          if (parseInt(e.id) === data.run_b) oB.selected = true;
          selA.appendChild(oA); selB.appendChild(oB);
        });
        at._eleccionesOk = true;
      }

      atStatLoading(false);
      renderAnalisisTerritorial(data);
    } catch(e) {
      atStatLoading(false);
      $("atBody").innerHTML = `<tr><td colspan="9" class="bita-empty">Error al cargar datos.</td></tr>`;
      console.error(e);
    }
  }

  function renderAnalisisTerritorial(data) {
    const s = data.stats || {};
    $("atStatPartA").textContent     = s.pct_global_a ? s.pct_global_a + "%" : "—";
    $("atStatPartB").textContent     = s.pct_global_b ? s.pct_global_b + "%" : "—";
    const delta = parseFloat(s.delta_global);
    $("atStatDelta").textContent     = s.delta_global ? (delta > 0 ? "+" : "") + delta.toFixed(2) + " pp" : "—";
    $("atStatDelta").style.color     = delta > 0 ? "#22c55e" : delta < 0 ? "#ef4444" : "";
    $("atStatTerritorios").textContent = fmt(parseInt(s.territorios) || 0);
    $("atStatMaxDelta").textContent  = s.max_delta ? "+" + s.max_delta + " pp" : "—";
    $("atStatMinDelta").textContent  = s.min_delta ? s.min_delta + " pp" : "—";
    const terrLblMap = { canton: "Cantones", district: "Distritos" };
    $("atStatTerrLbl").textContent   = terrLblMap[at.nivel] || "Territorios";
    $("atStatMaxLbl").textContent    = "Mayor diferencia";
    $("atStatMinLbl").textContent    = "Menor diferencia";

    // Cabeceras de tabla
    if (at.nivel === "canton") {
      $("atThNombre").textContent = "Cantón";
      $("atThSub1").classList.add("d-none"); $("atThSub2").classList.add("d-none");
    } else {
      $("atThNombre").textContent = "Distrito";
      $("atThSub1").textContent = "Cantón"; $("atThSub1").classList.remove("d-none");
      $("atThSub2").textContent = "Provincia"; $("atThSub2").classList.remove("d-none");
    }

    const rows   = data.rows || [];
    const offset = (at.page - 1) * at.size;
    const body   = $("atBody");

    if (!rows.length) {
      body.innerHTML = `<tr><td colspan="9" class="bita-empty">Sin resultados para esta comparación.</td></tr>`;
    } else {
      const maxAbsDelta = rows.reduce((m, r) => Math.max(m, Math.abs(r.delta)), 0) || 1;
      body.innerHTML = rows.map((r, i) => {
        const idx     = offset + i + 1;
        const deltaV  = r.delta;
        const deltaSign = deltaV > 0 ? "+" : "";
        const deltaColor = deltaV >= 10 ? "#22c55e" : deltaV >= 0 ? "var(--text-muted)" : "#ef4444";
        const barW    = Math.round(Math.abs(deltaV) / maxAbsDelta * 100);
        const barCls  = deltaV >= 10 ? "jrv-bar part-high"
                      : deltaV >= 0  ? "jrv-bar part-mid"
                      :                "jrv-bar part-low";
        const drillNivel  = at.nivel === "canton" ? "canton" : "distrito";
        const drillCodigo = at.nivel === "district" ? (r.geo5 || String(r.geo_id)) : String(r.geo_id);
        const drillCtx    = at.nivel === "canton" ? esc(r.provincia) : esc(r.canton) + ", " + esc(r.provincia);
        const sub1 = at.nivel === "canton"   ? `<td class="muted" style="font-size:.8rem">${esc(r.provincia)}</td>` : "";
        const sub2 = at.nivel === "district" ? `<td class="muted" style="font-size:.8rem">${esc(r.canton)}</td><td class="muted" style="font-size:.8rem">${esc(r.provincia)}</td>` : "";
        return `<tr>
          <td class="col-num muted">${idx}</td>
          <td><strong>${esc(r.nombre)}</strong></td>
          ${sub1}${sub2}
          <td class="col-num">${r.pct_a}%</td>
          <td class="col-num muted">${r.pct_b}%</td>
          <td class="col-num"><strong style="color:${deltaColor}">${deltaSign}${deltaV} pp</strong></td>
          <td class="col-num seg-col-drill">
            <a href="#" data-drill-nivel="${drillNivel}" data-drill-codigo="${drillCodigo}"
               data-drill-nombre="${esc(r.nombre)}" data-drill-total="${r.inscritos}"
               data-drill-ctx="${drillCtx}" title="Ver inscritos en padrón">
              <strong>${fmt(r.inscritos)}</strong>
            </a>
          </td>
          <td class="col-bar"><div class="${barCls}" style="width:${barW}%"></div></td>
        </tr>`;
      }).join("");
      body.querySelectorAll("a[data-drill-nivel]").forEach(a => {
        a.addEventListener("click", e => {
          e.preventDefault();
          abrirPadronConFiltro(a.dataset.drillNivel, a.dataset.drillCodigo,
            a.dataset.drillNombre, parseInt(a.dataset.drillTotal,10), a.dataset.drillCtx);
        });
      });
    }
    $("atFirst").disabled = at.page <= 1;
    $("atPrev").disabled  = at.page <= 1;
    $("atNext").disabled  = at.page >= at.pages;
    $("atLast").disabled  = at.page >= at.pages;
    $("atPages").textContent = "Pág. " + at.page + " / " + at.pages;
    $("atTotal").textContent = fmt(at.total) + " registros";
  }

  // ── DISTRITOS ELECTORALES ─────────────────────────────────────────────────────
  const distEl = {
    page: 1, pages: 1, total: 0, size: 25, order: "desc", q: "",
    provinceId: null, cantonId: null, _inicializado: false, _qTimer: null,
  };

  function abrirDistritosElectorales() {
    activarReporte("distritos-electorales");
    logEvento("reporte_abrir", "Distritos Electorales", {});
    if (!distEl._inicializado) { setupDistritosElectorales(); distEl._inicializado = true; }
    cargarDistritosElectorales();
  }

  function setupDistritosElectorales() {
    const provList = $("deProvList");
    if (POB && POB.provincias) {
      Object.entries(POB.provincias)
        .sort((a, b) => parseInt(a[0]) - parseInt(b[0]))
        .forEach(([id, p]) => {
          const li = document.createElement("li");
          li.className = "tse-filter-item";
          li.innerHTML = `<span class="tse-chk"></span>${id} ${p.nombre}`;
          li.addEventListener("click", () => deSelectProvincia(id, li));
          provList.appendChild(li);
        });
    }
    $("deBorrarFiltros").addEventListener("click", () => {
      distEl.provinceId = null; distEl.cantonId = null; distEl.page = 1;
      document.querySelectorAll("#deProvList .tse-filter-item, #deCantList .tse-filter-item").forEach(el => el.classList.remove("tse-sel"));
      $("deCantList").innerHTML = '<li class="tse-filter-item-empty">Selecciona provincia</li>';
      cargarDistritosElectorales();
    });
    $("distElBuscador").addEventListener("input", () => {
      clearTimeout(distEl._qTimer);
      distEl._qTimer = setTimeout(() => {
        distEl.q = $("distElBuscador").value.trim(); distEl.page = 1; cargarDistritosElectorales();
      }, 300);
    });
    $("distElOrdDesc").addEventListener("click", () => {
      $("distElOrdDesc").classList.add("active"); $("distElOrdAsc").classList.remove("active");
      distEl.order = "desc"; distEl.page = 1; cargarDistritosElectorales();
    });
    $("distElOrdAsc").addEventListener("click", () => {
      $("distElOrdAsc").classList.add("active"); $("distElOrdDesc").classList.remove("active");
      distEl.order = "asc"; distEl.page = 1; cargarDistritosElectorales();
    });
    $("distElPageSize").addEventListener("change", () => {
      distEl.size = parseInt($("distElPageSize").value) || 25; distEl.page = 1; cargarDistritosElectorales();
    });
    $("distElFirst").addEventListener("click", () => { distEl.page = 1; cargarDistritosElectorales(); });
    $("distElPrev").addEventListener("click",  () => { distEl.page--; cargarDistritosElectorales(); });
    $("distElNext").addEventListener("click",  () => { distEl.page++; cargarDistritosElectorales(); });
    $("distElLast").addEventListener("click",  () => { distEl.page = distEl.pages; cargarDistritosElectorales(); });
    $("distElExportar").addEventListener("click", () => {
      const p = distElParams(); p.set("format", "csv");
      window.open("api/distritos_electorales.php?" + p.toString());
      logEvento("reporte_exportar", "Distritos Electorales CSV", {});
    });
  }

  function deSelectProvincia(id, li) {
    const was = li.classList.contains("tse-sel");
    document.querySelectorAll("#deProvList .tse-filter-item").forEach(el => el.classList.remove("tse-sel"));
    document.querySelectorAll("#deCantList .tse-filter-item").forEach(el => el.classList.remove("tse-sel"));
    $("deCantList").innerHTML = '<li class="tse-filter-item-empty">Selecciona provincia</li>';
    distEl.cantonId = null;
    distEl.provinceId = was ? null : (li.classList.add("tse-sel"), parseInt(id));
    if (!was) dePoblarCantones(id);
    distEl.page = 1; cargarDistritosElectorales();
  }

  function deSelectCanton(cantonId, li) {
    const was = li.classList.contains("tse-sel");
    document.querySelectorAll("#deCantList .tse-filter-item").forEach(el => el.classList.remove("tse-sel"));
    distEl.cantonId = was ? null : (li.classList.add("tse-sel"), parseInt(cantonId));
    distEl.page = 1; cargarDistritosElectorales();
  }

  function dePoblarCantones(provinceId) {
    const list = $("deCantList");
    list.innerHTML = "";
    if (!POB || !POB.cantones) return;
    Object.entries(POB.cantones)
      .filter(([, c]) => String(c.cod_provincia) === String(provinceId))
      .sort((a, b) => a[1].nombre.localeCompare(b[1].nombre))
      .forEach(([id, c]) => {
        const li = document.createElement("li");
        li.className = "tse-filter-item";
        li.innerHTML = `<span class="tse-chk"></span>${id} ${c.nombre}`;
        li.addEventListener("click", () => deSelectCanton(id, li));
        list.appendChild(li);
      });
    if (!list.children.length) list.innerHTML = '<li class="tse-filter-item-empty">Sin cantones</li>';
  }

  function distElParams() {
    const p = new URLSearchParams({ page: String(distEl.page), size: String(distEl.size), order: distEl.order });
    if (distEl.provinceId) p.set("province_id", String(distEl.provinceId));
    if (distEl.cantonId)   p.set("canton_id",   String(distEl.cantonId));
    if (distEl.q)          p.set("q", distEl.q);
    return p;
  }

  async function cargarDistritosElectorales() {
    $("distElBody").innerHTML = `<tr class="tbl-spinner-row"><td colspan="9"><span class="tbl-spinner"></span>Consultando base de datos…</td></tr>`;
    try {
      const data = await fetchJSON("api/distritos_electorales.php?" + distElParams().toString());
      distEl.pages = data.pages || 1;
      distEl.page  = data.page  || 1;
      distEl.total = data.total || 0;
      renderDistritosElectorales(data);
    } catch(e) {
      $("distElBody").innerHTML = `<tr><td colspan="9" class="bita-empty">Error al cargar datos.</td></tr>`;
      console.error(e);
    }
  }

  function renderDistritosElectorales(data) {
    const s = data.stats || {};
    $("distElStatTotal").textContent     = fmt(s.total_inscritos || 0);
    $("distElStatDistritos").textContent = fmt(s.total_distritos || 0);
    $("distElStatJuntas").textContent    = fmt(s.total_juntas    || 0);
    $("distElStatM").textContent         = fmt(s.total_m         || 0);
    $("distElStatF").textContent         = fmt(s.total_f         || 0);
    $("distElStatMPct").textContent      = (s.pct_m || 0) + "%";
    $("distElStatFPct").textContent      = (s.pct_f || 0) + "%";

    const rows = data.rows || [];
    const off  = (distEl.page - 1) * distEl.size;
    if (!rows.length) {
      $("distElBody").innerHTML = `<tr><td colspan="9" class="bita-empty">Sin resultados.</td></tr>`;
    } else {
      $("distElBody").innerHTML = rows.map((r, i) => `<tr>
        <td class="col-num muted">${off + i + 1}</td>
        <td><strong>${esc(r.nombre)}</strong></td>
        <td class="muted" style="font-size:.85rem">${esc(r.canton)}</td>
        <td class="muted" style="font-size:.85rem">${esc(r.provincia)}</td>
        <td class="col-num" style="color:#3b82f6;font-size:.85rem">${fmt(r.inscritos_m)} <span class="muted" style="font-size:.75rem">${r.pct_m}%</span></td>
        <td class="col-num" style="color:#ec4899;font-size:.85rem">${fmt(r.inscritos_f)} <span class="muted" style="font-size:.75rem">${r.pct_f}%</span></td>
        <td class="col-num"><strong>${fmt(r.inscritos)}</strong></td>
        <td class="col-num">${r.num_juntas}</td>
        <td class="col-bar"><div class="jrv-bar" style="width:${r.barra_pct}%"></div></td>
      </tr>`).join("");
    }

    $("distElFirst").disabled = distEl.page <= 1;
    $("distElPrev").disabled  = distEl.page <= 1;
    $("distElNext").disabled  = distEl.page >= distEl.pages;
    $("distElLast").disabled  = distEl.page >= distEl.pages;
    $("distElPages").textContent = "Pág. " + distEl.page + " / " + distEl.pages;
    $("distElTotal").textContent = fmt(distEl.total) + " registros";
  }

  // ── JUNTAS ELECTORALES ────────────────────────────────────────────────────────
  const juntas = {
    nivel: "province", provinceId: null, cantonId: null, districtId: null,
    juntaMin: 1, juntaMax: 7063,
    page: 1, pages: 1, total: 0, size: 50, order: "asc",
    _inicializado: false, _sliderTimer: null,
  };

  function abrirJuntasPadronal() {
    activarReporte("juntas-padronal");
    logEvento("reporte_abrir", "Juntas Electorales", {});
    if (!juntas._inicializado) { setupJuntasPadronal(); juntas._inicializado = true; }
    cargarJuntasPadronal();
  }

  function setupJuntasPadronal() {
    // Province list (left panel)
    const provList = $("jpProvList");
    if (POB && POB.provincias) {
      Object.entries(POB.provincias)
        .sort((a, b) => parseInt(a[0]) - parseInt(b[0]))
        .forEach(([id, p]) => {
          const li = document.createElement("li");
          li.className = "tse-filter-item";
          li.innerHTML = `<span class="tse-chk"></span>${id} ${p.nombre}`;
          li.addEventListener("click", () => jpSelectProvincia(id, li));
          provList.appendChild(li);
        });
    }

    // Tabs de nivel (right panel)
    document.querySelectorAll("[data-juntas-tab]").forEach(btn => {
      btn.addEventListener("click", () => {
        document.querySelectorAll("[data-juntas-tab]").forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        juntas.nivel = btn.dataset.juntasTab;
        juntas.page = 1;
        actualizarCabeceraJuntas();
        juntas.order = (juntas.nivel === "junta") ? "asc" : "desc";
        $("juntasOrdAsc").classList.toggle("active",  juntas.order === "asc");
        $("juntasOrdDesc").classList.toggle("active", juntas.order === "desc");
        cargarJuntasPadronal();
      });
    });

    $("juntasOrdAsc").addEventListener("click", () => {
      $("juntasOrdAsc").classList.add("active"); $("juntasOrdDesc").classList.remove("active");
      juntas.order = "asc"; juntas.page = 1; cargarJuntasPadronal();
    });
    $("juntasOrdDesc").addEventListener("click", () => {
      $("juntasOrdDesc").classList.add("active"); $("juntasOrdAsc").classList.remove("active");
      juntas.order = "desc"; juntas.page = 1; cargarJuntasPadronal();
    });
    $("juntasPageSize").addEventListener("change", () => {
      juntas.size = parseInt($("juntasPageSize").value) || 50; juntas.page = 1; cargarJuntasPadronal();
    });
    $("juntasFirst").addEventListener("click", () => { juntas.page = 1; cargarJuntasPadronal(); });
    $("juntasPrev").addEventListener("click",  () => { juntas.page--; cargarJuntasPadronal(); });
    $("juntasNext").addEventListener("click",  () => { juntas.page++; cargarJuntasPadronal(); });
    $("juntasLast").addEventListener("click",  () => { juntas.page = juntas.pages; cargarJuntasPadronal(); });
    $("juntasExportar").addEventListener("click", () => {
      const p = juntasParams(); p.set("format", "csv");
      window.open("api/juntas_padronal.php?" + p.toString());
      logEvento("reporte_exportar", "Juntas CSV", { nivel: juntas.nivel });
    });
    $("jpBorrarFiltros").addEventListener("click", () => {
      juntas.provinceId = null; juntas.juntaMin = 1; juntas.juntaMax = 7063; juntas.page = 1;
      document.querySelectorAll("#jpProvList .tse-filter-item").forEach(el => el.classList.remove("tse-sel"));
      const sMin = $("juntaSliderMin"), sMax = $("juntaSliderMax");
      const nMin = $("juntaNumMin"),   nMax = $("juntaNumMax");
      const fill = $("juntaTrackFill");
      if (sMin) sMin.value = 1; if (sMax) sMax.value = 7063;
      if (nMin) nMin.value = 1; if (nMax) nMax.value = 7063;
      if (fill) { fill.style.left = "0%"; fill.style.width = "100%"; }
      cargarJuntasPadronal();
    });

    setupJuntasSlider();
  }

  function jpSelectProvincia(id, li) {
    const was = li.classList.contains("tse-sel");
    document.querySelectorAll("#jpProvList .tse-filter-item").forEach(el => el.classList.remove("tse-sel"));
    juntas.provinceId = was ? null : (li.classList.add("tse-sel"), parseInt(id));
    juntas.cantonId = null; juntas.districtId = null; juntas.page = 1;
    cargarJuntasPadronal();
  }

  function setupJuntasSlider() {
    const sMin = $("juntaSliderMin"), sMax = $("juntaSliderMax");
    const nMin = $("juntaNumMin"),   nMax = $("juntaNumMax");
    const fill = $("juntaTrackFill");
    const TOTAL = 7063;

    function updateFill() {
      const lo = (juntas.juntaMin - 1) / TOTAL * 100;
      const hi = juntas.juntaMax / TOTAL * 100;
      fill.style.left  = lo + "%";
      fill.style.width = (hi - lo) + "%";
    }

    function syncFromSliders() {
      let lo = parseInt(sMin.value), hi = parseInt(sMax.value);
      if (lo > hi) { const t = lo; lo = hi; hi = t; }
      juntas.juntaMin = lo; juntas.juntaMax = hi;
      nMin.value = lo; nMax.value = hi;
      updateFill();
      clearTimeout(juntas._sliderTimer);
      juntas._sliderTimer = setTimeout(() => { juntas.page = 1; cargarJuntasPadronal(); }, 400);
    }

    sMin.addEventListener("input", syncFromSliders);
    sMax.addEventListener("input", syncFromSliders);

    nMin.addEventListener("change", () => {
      let v = Math.max(1, Math.min(TOTAL, parseInt(nMin.value) || 1));
      if (v > juntas.juntaMax) v = juntas.juntaMax;
      juntas.juntaMin = v; sMin.value = v; nMin.value = v;
      updateFill(); juntas.page = 1; cargarJuntasPadronal();
    });
    nMax.addEventListener("change", () => {
      let v = Math.max(1, Math.min(TOTAL, parseInt(nMax.value) || TOTAL));
      if (v < juntas.juntaMin) v = juntas.juntaMin;
      juntas.juntaMax = v; sMax.value = v; nMax.value = v;
      updateFill(); juntas.page = 1; cargarJuntasPadronal();
    });

    updateFill();
  }

  function juntasParams() {
    const p = new URLSearchParams({
      nivel: juntas.nivel, page: String(juntas.page), size: String(juntas.size),
      order: juntas.order, junta_min: String(juntas.juntaMin), junta_max: String(juntas.juntaMax),
    });
    if (juntas.provinceId) p.set("province_id", String(juntas.provinceId));
    if (juntas.cantonId)   p.set("canton_id",   String(juntas.cantonId));
    if (juntas.districtId) p.set("district_id", String(juntas.districtId));
    return p;
  }

  function actualizarCabeceraJuntas() {
    const lblMap = { province: "Provincias", canton: "Cantones", district: "Distritos", junta: "Juntas" };
    const th1 = $("juntasThSub1"), th2 = $("juntasThSub2"), th3 = $("juntasThSub3");
    $("juntasThNombre").textContent = lblMap[juntas.nivel] || "Territorio";
    $("juntasStatTerrLbl").textContent = lblMap[juntas.nivel] || "Territorios";
    th1.classList.add("d-none"); th2.classList.add("d-none"); th3.classList.add("d-none");
    if (juntas.nivel === "canton")   { th1.textContent = "Provincia"; th1.classList.remove("d-none"); }
    if (juntas.nivel === "district") { th1.textContent = "Cantón";    th1.classList.remove("d-none");
                                       th2.textContent = "Provincia"; th2.classList.remove("d-none"); }
    if (juntas.nivel === "junta")    { th1.textContent = "Distrito";  th1.classList.remove("d-none");
                                       th2.textContent = "Cantón";    th2.classList.remove("d-none");
                                       th3.textContent = "Provincia"; th3.classList.remove("d-none"); }
  }

  async function cargarJuntasPadronal() {
    $("juntasBody").innerHTML = `<tr class="tbl-spinner-row"><td colspan="10"><span class="tbl-spinner"></span>Consultando base de datos…</td></tr>`;
    try {
      const data = await fetchJSON("api/juntas_padronal.php?" + juntasParams().toString());
      juntas.pages = data.pages || 1;
      juntas.page  = data.page  || 1;
      juntas.total = data.total || 0;
      renderJuntasPadronal(data);
    } catch(e) {
      $("juntasBody").innerHTML = `<tr><td colspan="10" class="bita-empty">Error al cargar datos.</td></tr>`;
      console.error(e);
    }
  }

  function renderJuntasPadronal(data) {
    actualizarCabeceraJuntas();
    const s = data.stats || {};
    $("juntasStatJuntas").textContent    = fmt(s.total_juntas      || 0);
    $("juntasStatTerr").textContent      = fmt(s.total_territorios || 0);
    $("juntasStatInscritos").textContent = fmt(s.total_inscritos   || 0);

    const rows   = data.rows || [];
    const offset = (juntas.page - 1) * juntas.size;
    if (!rows.length) {
      $("juntasBody").innerHTML = `<tr><td colspan="10" class="bita-empty">Sin resultados.</td></tr>`;
    } else {
      $("juntasBody").innerHTML = rows.map((r, i) => {
        let subs = "";
        if (juntas.nivel === "canton")   subs = `<td class="muted" style="font-size:.82rem">${esc(r.provincia)}</td>`;
        if (juntas.nivel === "district") subs = `<td class="muted" style="font-size:.82rem">${esc(r.canton)}</td><td class="muted" style="font-size:.82rem">${esc(r.provincia)}</td>`;
        if (juntas.nivel === "junta")    subs = `<td class="muted" style="font-size:.82rem">${esc(r.distrito)}</td><td class="muted" style="font-size:.82rem">${esc(r.canton)}</td><td class="muted" style="font-size:.82rem">${esc(r.provincia)}</td>`;
        const jMenor = r.num_juntas === 1 ? `<span class="mono">${String(r.junta_menor).padStart(5,"0")}</span>` : String(r.junta_menor);
        const jMayor = r.num_juntas === 1 ? `<span class="mono">${String(r.junta_mayor).padStart(5,"0")}</span>` : String(r.junta_mayor);
        return `<tr>
          <td class="col-num muted">${offset + i + 1}</td>
          <td><strong>${esc(r.nombre)}</strong></td>
          ${subs}
          <td class="col-num">${fmt(r.num_juntas)}</td>
          <td class="col-num">${jMenor}</td>
          <td class="col-num">${jMayor}</td>
          <td class="col-num"><strong>${fmt(r.inscritos)}</strong></td>
          <td class="col-bar"><div class="jrv-bar" style="width:${r.barra_pct}%"></div></td>
        </tr>`;
      }).join("");
    }

    $("juntasFirst").disabled = juntas.page <= 1;
    $("juntasPrev").disabled  = juntas.page <= 1;
    $("juntasNext").disabled  = juntas.page >= juntas.pages;
    $("juntasLast").disabled  = juntas.page >= juntas.pages;
    $("juntasPages").textContent = "Pág. " + juntas.page + " / " + juntas.pages;
    $("juntasTotal").textContent = fmt(juntas.total) + " registros";
  }

  document.addEventListener("DOMContentLoaded", init);
