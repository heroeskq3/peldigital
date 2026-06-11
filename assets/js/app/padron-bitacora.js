"use strict";
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

