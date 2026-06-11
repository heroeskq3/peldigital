# Skill: Crear un nuevo reporte — PEL Digital

Guía de arquitectura para construir reportes en este sistema PHP/JS sin framework.

## Arquitectura del sistema de reportes

El sistema es un SPA ligero donde `reports.php?id=N` carga el reporte N.
Cada reporte tiene tres capas:

```
includes/reports/mi-reporte.php   ← HTML estático del reporte (estructura, IDs)
api/mi-reporte.php                ← API JSON que consulta la BD (PDO + JSON output)
assets/js/app.js                  ← Toda la lógica JS vive aquí (IIFE monolítico)
```

El menú se genera dinámicamente desde la tabla `reports` en la BD.

## Paso a paso para un reporte nuevo

### 1. Registrar en la BD

```sql
INSERT INTO reports (category_id, slug, name, short_name, description, icon, status, php_file, js_report_id, sort_order)
VALUES (1, 'mi-slug', 'Nombre Completo del Reporte', 'Nombre Corto',
        'Descripción breve', 'bi-graph-up', 'pending', 'mi-reporte.php', 'mi-reporte', 8);
-- status: 'pending' | 'partial' | 'active'
-- category_id: 1 = Análisis Electoral
```

### 2. Crear la vista HTML

`includes/reports/mi-reporte.php`:

```html
<div id="reporteMiReporte" data-report="mi-reporte" class="reporte-page d-none">
    <div class="rp-head">
        <div>
            <h1 class="rp-titulo">Título del Reporte</h1>
            <p class="rp-sub muted">Subtítulo descriptivo · Fuente de datos</p>
        </div>
        <div class="rp-head-actions">
            <button id="miRepExportar" class="btn-export" type="button">
                <i class="bi bi-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="rp-stats">
        <div class="rp-stat-card">
            <div id="miRepStat1" class="rp-stat-num">—</div>
            <div class="rp-stat-lbl">Etiqueta 1</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="rp-filtros">
        <!-- Botones de tab, selects de provincia/cantón, buscador, orden, paginación -->
    </div>

    <!-- Tabla -->
    <div class="padron-table-wrap">
        <table class="padron-table">
            <thead><tr>
                <th>#</th><th>Columna</th><th>Valor</th>
            </tr></thead>
            <tbody id="miRepBody">
                <tr><td colspan="3" class="bita-empty">Cargando…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <div class="padron-paginacion">
        <button id="miRepFirst" class="pag-btn">«</button>
        <button id="miRepPrev"  class="pag-btn">‹</button>
        <span   id="miRepPages" class="pag-info">—</span>
        <button id="miRepNext"  class="pag-btn">›</button>
        <button id="miRepLast"  class="pag-btn">»</button>
        <span   id="miRepTotal" class="pag-total muted"></span>
    </div>
</div>
```

### 3. Crear la API

`api/mi-reporte.php`:

```php
<?php
require __DIR__ . '/../auth.php';
requerirLoginApi();
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo   = dbConnect();
$nivel = in_array($_GET['nivel'] ?? '', ['province','canton','district']) ? $_GET['nivel'] : 'province';
$page  = max(1, (int)($_GET['page']  ?? 1));
$size  = max(10, min(200, (int)($_GET['size'] ?? 25)));

// ... query ...

echo json_encode([
    'rows'  => $rows,
    'total' => $total,
    'pages' => $pages,
    'page'  => $page,
    'stats' => [ /* KPIs */ ],
], JSON_UNESCAPED_UNICODE);
```

**Reglas de la API:**
- Siempre llamar `requerirLoginApi()` al inicio
- Devolver siempre `Content-Type: application/json`
- Para paginación: incluir `total`, `pages`, `page` en la respuesta
- Para CSV: detectar `$_GET['format'] === 'csv'` y cambiar headers

### 4. Agregar la lógica JS en app.js

En `assets/js/app.js`, dentro del IIFE, agregar al final de la función `setupNav()`:

```javascript
// En el manejador de reportes (cerca de línea ~946):
else if (id === "mi-reporte") abrirReporteMiReporte();
```

Añadir las funciones del reporte (al final del archivo, antes del cierre del IIFE):

```javascript
// ─── MI REPORTE ──────────────────────────────────────────────────────────────
const miRep = { nivel: "province", page: 1, pages: 1, total: 0, size: 25 };

function abrirReporteMiReporte() {
  activarReporte("mi-reporte");
  if (!miRep._init) { setupMiReporte(); miRep._init = true; }
  cargarMiReporte();
}

function setupMiReporte() {
  $("miRepFirst").addEventListener("click", () => { miRep.page = 1;          cargarMiReporte(); });
  $("miRepPrev" ).addEventListener("click", () => { miRep.page--;             cargarMiReporte(); });
  $("miRepNext" ).addEventListener("click", () => { miRep.page++;             cargarMiReporte(); });
  $("miRepLast" ).addEventListener("click", () => { miRep.page = miRep.pages; cargarMiReporte(); });
}

async function cargarMiReporte() {
  $("miRepBody").innerHTML = `<tr class="tbl-spinner-row"><td colspan="3"><span class="tbl-spinner"></span>Cargando…</td></tr>`;
  try {
    const p = new URLSearchParams({ nivel: miRep.nivel, page: miRep.page, size: miRep.size });
    const data = await fetchJSON("api/mi-reporte.php?" + p.toString());
    miRep.pages = data.pages || 1;
    miRep.page  = data.page  || 1;
    miRep.total = data.total || 0;
    renderMiReporte(data);
  } catch(e) {
    $("miRepBody").innerHTML = `<tr><td colspan="3" class="bita-empty">Error al cargar.</td></tr>`;
    console.error(e);
  }
}

function renderMiReporte(data) {
  const s = data.stats || {};
  $("miRepStat1").textContent = fmt(s.mi_kpi || 0);

  const rows = data.rows || [];
  $("miRepBody").innerHTML = rows.length
    ? rows.map((r, i) => `<tr>
        <td class="col-num muted">${(miRep.page - 1) * miRep.size + i + 1}</td>
        <td>${esc(r.nombre)}</td>
        <td class="col-num">${fmt(r.valor)}</td>
      </tr>`).join("")
    : `<tr><td colspan="3" class="bita-empty">Sin resultados.</td></tr>`;

  $("miRepFirst").disabled = miRep.page <= 1;
  $("miRepPrev" ).disabled = miRep.page <= 1;
  $("miRepNext" ).disabled = miRep.page >= miRep.pages;
  $("miRepLast" ).disabled = miRep.page >= miRep.pages;
  $("miRepPages").textContent = "Pág. " + miRep.page + " / " + miRep.pages;
  $("miRepTotal").textContent = fmt(miRep.total) + " registros";
}
```

### 5. Incluir la vista en index.php

Verificar que `index.php` incluye el directorio `includes/reports/` de forma dinámica, o agregar:
```php
<?php include 'includes/reports/mi-reporte.php'; ?>
```

## Funciones JS disponibles en app.js

| Función | Uso |
|---|---|
| `$(id)` | `document.getElementById(id)` |
| `fmt(n)` | Formatea número con separador de miles |
| `esc(s)` | Escapa HTML para insertar en el DOM |
| `fetchJSON(url)` | fetch + JSON.parse, lanza error si falla |
| `activarReporte(slug)` | Oculta todos los reportes, muestra el indicado |
| `logEvento(accion, desc, meta)` | Registra evento en la bitácora |
| `toast(msg, tipo)` | Muestra notificación temporal ('success'|'error'|'info') |

## Convenciones de CSS

Clases disponibles en `assets/css/style.css`:
- `.rp-head` / `.rp-titulo` / `.rp-sub` → cabecera del reporte
- `.rp-stats` / `.rp-stat-card` / `.rp-stat-num` / `.rp-stat-lbl` → KPI cards
- `.rp-filtros` / `.rp-order-wrap` → barra de filtros
- `.padron-table-wrap` / `.padron-table` → tabla con scroll horizontal
- `.padron-paginacion` / `.pag-btn` / `.pag-info` / `.pag-total` → paginación
- `.col-num` → celda numérica (alineada a la derecha)
- `.col-bar` → celda de barra de concentración
- `.bita-empty` → mensaje de celda vacía / cargando
- `.muted` → color secundario / atenuado
- `.coming-soon-requires` → bloque de "pendientes de datos"
- `.tbl-spinner-row` / `.tbl-spinner` → spinner de carga en tabla
- `.seg-btn` → botón de tab activo/inactivo (toggle)
- `.d-none` → oculto

## Reportes existentes como referencia

| Reporte | Vista | API | Patrón |
|---|---|---|---|
| Distribución Territorial | `padron-distribucion.php` | `api/poblacion.php` | Mapa + panel lateral |
| Segmentación Electoral | `segmentacion.php` | `api/segmentacion.php` | Tabla paginada + sexo |
| Participación Electoral | `participacion.php` | `api/participacion.php` | Tabla + votos por partido |
| Análisis Territorial | `analisis-territorial.php` | `api/analisis_territorial.php` | Tabla + selector elección |
| JRV Inscritos | `jrv-inscritos.php` | `api/jrv.php` | Tabla paginada + ranking |
| JRV Análisis Estratégico | `jrv-analisis.php` | `api/jrv.php` + `api/resultados.php` | Tabla + votos PEL |
