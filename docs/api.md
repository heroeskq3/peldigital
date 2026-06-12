# API Reference — PEL Digital

Todos los endpoints requieren sesión activa (cookie de sesión PHP).
Las llamadas desde el frontend deben hacerse dentro de la sesión iniciada con login.

## Convenciones generales

| Convención | Detalle |
|---|---|
| Autenticación | Sesión PHP. Sin sesión: `401 {"error":"No autenticado"}` |
| Formato de respuesta | `Content-Type: application/json; charset=utf-8` |
| Paginación | Siempre incluye `page`, `pages`, `total` en la respuesta |
| CSV | Pasar `format=csv` para descarga. Cambia headers a `text/csv` |
| Cache | Solo `api/poblacion.php` tiene caché de archivo (1h TTL) |
| Errores | `{ "error": "Mensaje" }` con el código HTTP correspondiente |

---

## api/poblacion.php

Agrega conteos del padrón por provincia / cantón / distrito. Alimenta el mapa principal y el panel lateral.

**Caché:** `data/poblacion_cache.json` con TTL de 1 hora. Forzar regeneración: `?refresh=1`

### Respuesta

```json
{
  "provincias": [
    { "id": 1, "nombre": "San José", "poblacion": 1234567, "pct": 33.1 }
  ],
  "cantones": [ ... ],
  "distritos": [ ... ],
  "diaspora": [ ... ],
  "fuente": "Padrón Nacional Electoral · TSE 2026",
  "padron_actualizado": "2026-06-01 00:00:00",
  "generado": "2026-06-11T15:30:00+00:00"
}
```

---

## api/padron.php

Consulta paginada del padrón real. Soporta búsqueda FULLTEXT y prefijo por cédula/junta.

### Parámetros GET

| Param | Tipo | Default | Descripción |
|---|---|---|---|
| `nivel` | `provincia\|canton\|distrito\|pais` | — | Nivel geográfico para filtrar |
| `codigo` | string | — | Código geográfico del nivel (e.g. `1` para San José) |
| `q` | string | — | Búsqueda por nombre (FULLTEXT) o prefijo de cédula |
| `page` | int | `1` | Página |
| `size` | int | `25` | Filas por página (10–200) |

### Respuesta

```json
{
  "rows": [
    {
      "cedula": "101234567",
      "nombre": "JUAN",
      "apellido1": "PÉREZ",
      "apellido2": "MORA",
      "fecha_caduc": "2030-01-15",
      "junta": "01234",
      "provincia": "San José",
      "canton": "Central",
      "distrito": "Carmen"
    }
  ],
  "total": 45230,
  "pages": 1809,
  "page": 1
}
```

---

## api/participacion.php

Participación y abstención electoral por territorio. Usa `election_results` importado del AVR del TSE.

### Parámetros GET

| Param | Tipo | Default | Descripción |
|---|---|---|---|
| `nivel` | `province\|canton\|district` | `province` | Nivel territorial |
| `province_id` | int | — | Filtrar por provincia |
| `canton_id` | int | — | Filtrar por cantón (solo con `nivel=district`) |
| `run_id` | int | último completado | ID del `election_sync_runs` |
| `page` | int | `1` | Página |
| `size` | int | `25` | Filas (10–200) |
| `order` | `desc\|asc` | `desc` | Orden por % participación |
| `sort` | `participacion\|abstencion\|inscritos\|nombre` | `participacion` | Campo de orden |
| `q` | string | — | Búsqueda por nombre geográfico |
| `format` | `json\|csv` | `json` | Formato de respuesta |

### Respuesta

```json
{
  "nivel": "province",
  "run_id": 1,
  "meta": { "id": 1, "election_date": "2026-02-02", "election_label": "Presidencia 2026" },
  "elections": [ { "id": 1, "election_date": "...", "election_label": "..." }, ... ],
  "rows": [
    {
      "geo_id": 1,
      "geo_name": "San José",
      "inscritos": 956789,
      "votos_emitidos": 670000,
      "pct_participacion": 70.01,
      "pct_abstencion": 29.99
    }
  ],
  "total": 7,
  "pages": 1,
  "page": 1,
  "stats": {
    "total_inscritos": 3731788,
    "total_votos": 2609000,
    "pct_part_global": 69.93
  },
  "party_breakdown": [
    { "code": 1003, "votes": 850000, "abbrev": "PLN", "name": "Partido Liberación Nacional" }
  ]
}
```

---

## api/jrv.php

Inscritos por Junta Receptora de Votos. Usa `summary_jrv` (pre-agregada, 7,154 filas). Sin scan sobre los 3.7M de voters.

### Parámetros GET

| Param | Tipo | Default | Descripción |
|---|---|---|---|
| `province_id` | int | — | Filtrar por provincia |
| `canton_id` | int | — | Filtrar por cantón |
| `geo5` | string | — | Filtrar por distrito (código geo5 de 5 dígitos) |
| `page` | int | `1` | Página |
| `size` | int | `50` | Filas (10–200) |
| `order` | `desc\|asc` | `desc` | Orden por inscritos |
| `format` | `json\|csv` | `json` | — |

### Respuesta

```json
{
  "rows": [
    {
      "junta": "02475",
      "distrito": "Alajuela",
      "canton": "Alajuela",
      "provincia": "Alajuela",
      "province_id": 2,
      "canton_id": 201,
      "district_id": 20101,
      "inscritos": 886,
      "clasificacion": "alta"
    }
  ],
  "total": 7154,
  "pages": 144,
  "page": 1,
  "stats": {
    "total_juntas": 7154,
    "total_inscritos": 3643012,
    "promedio": 509,
    "max_inscritos": 886,
    "min_inscritos": 6
  }
}
```

---

## api/segmentacion.php

Distribución del padrón por territorio con desglose por sexo. Usa `summary_inscritos_*`.

### Parámetros GET

| Param | Tipo | Default | Descripción |
|---|---|---|---|
| `nivel` | `province\|canton\|district` | `province` | Nivel territorial |
| `province_id` | int | — | Filtrar por provincia |
| `canton_id` | int | — | Filtrar por cantón |
| `page` | int | `1` | Página |
| `size` | int | `25` | Filas (10–200) |
| `q` | string | — | Búsqueda por nombre |
| `order` | `desc\|asc` | `desc` | Orden por inscritos |
| `format` | `json\|csv` | `json` | — |

### Respuesta

```json
{
  "nivel": "province",
  "rows": [
    {
      "id": 1,
      "nombre": "San José",
      "inscritos": 956789,
      "inscritos_m": 458230,
      "inscritos_f": 398123,
      "inscritos_n": 100436,
      "pct_nacional": 25.640,
      "pct_m": 47.89,
      "pct_f": 41.61,
      "pct_n": 10.49
    }
  ],
  "total": 7,
  "pages": 1,
  "page": 1,
  "stats": {
    "total_inscritos": 3731788,
    "total_m": 1428900,
    "total_f": 1246161,
    "total_n": 1056727
  }
}
```

---

## api/analisis_territorial.php

Compara participación entre dos elecciones a nivel cantón o distrito. Calcula delta.

### Parámetros GET

| Param | Tipo | Default | Descripción |
|---|---|---|---|
| `nivel` | `canton\|district` | `canton` | Nivel de comparación |
| `province_id` | int | — | Filtrar por provincia |
| `canton_id` | int | — | Filtrar por cantón (solo `nivel=district`) |
| `run_a` | int | más reciente | Primera elección |
| `run_b` | int | anterior a `run_a` | Segunda elección para comparar |
| `sort` | `delta\|part_a\|part_b\|nombre\|inscritos` | `delta` | Campo de orden |
| `order` | `desc\|asc` | `desc` | Dirección de orden |
| `q` | string | — | Búsqueda por nombre |
| `page` | int | `1` | Página |
| `size` | int | `25` | Filas (10–200) |
| `format` | `json\|csv` | `json` | — |

### Respuesta

```json
{
  "nivel": "canton",
  "run_a": { "id": 1, "label": "Presidencia 2026", "date": "2026-02-02" },
  "run_b": { "id": 2, "label": "Municipal 2024",   "date": "2024-02-04" },
  "elections": [ ... ],
  "rows": [
    {
      "geo_id": 101,
      "geo_name": "San José Central",
      "provincia": "San José",
      "inscritos": 178234,
      "pct_part_a": 72.30,
      "pct_part_b": 68.10,
      "delta": 4.20
    }
  ],
  "total": 82,
  "pages": 4,
  "page": 1
}
```

---

## api/resultados.php

Resultados electorales por territorio y partido. Complementa a `participacion.php`.

### Parámetros GET

| Param | Tipo | Default | Descripción |
|---|---|---|---|
| `nivel` | `province\|canton\|district` | `province` | Nivel territorial |
| `province_id` | int | — | Filtrar por provincia |
| `canton_id` | int | — | Filtrar por cantón |
| `run_id` | int | último completado | ID de la elección |

### Respuesta

```json
{
  "rows": [
    {
      "geo_id": 1,
      "geo_name": "San José",
      "votos_emitidos": 670000,
      "votos_por_partido": { "1003": 189000, "1001": 145000 }
    }
  ]
}
```

---

## api/parties.php

Catálogo de partidos políticos del TSE.

### Parámetros GET

Ninguno.

### Respuesta

```json
{
  "rows": [
    { "id": 1, "tse_code": 1003, "name": "Partido Liberación Nacional", "abbrev": "PLN" }
  ]
}
```

---

## api/bitacora.php

Lectura de la bitácora de actividad (lectura). Requiere rol administrador.

### Parámetros GET

| Param | Tipo | Default | Descripción |
|---|---|---|---|
| `page` | int | `1` | Página |
| `size` | int | `25` | Filas (10–100) |
| `q` | string | — | Búsqueda en descripción |

### Respuesta

```json
{
  "rows": [
    {
      "id": 1234,
      "usuario": "demo",
      "accion": "view_report",
      "descripcion": "Reporte: Distribución Territorial",
      "ip": "127.0.0.1",
      "created_at": "2026-06-11 10:30:00"
    }
  ],
  "total": 890,
  "pages": 36,
  "page": 1
}
```

---

## api/log.php

Registro de eventos desde el frontend. Sin respuesta de datos.

### Parámetros POST (JSON)

| Campo | Tipo | Descripción |
|---|---|---|
| `accion` | string | Nombre del evento (e.g. `view_report`) |
| `descripcion` | string | Descripción legible |
| `meta` | object | Datos adicionales opcionales (se serializa como JSON) |

### Respuesta

```json
{ "ok": true }
```

---

## APIs del panel Admin

Todos requieren rol **administrador** (`role_id = 1`). Sin ese rol: `403 {"error":"Acceso denegado"}`.

### api/admin/usuarios.php

CRUD de usuarios.

| Action | Método | Descripción |
|---|---|---|
| `?action=list` | GET | Lista paginada. Params: `q`, `role_id`, `page`, `size` |
| `?action=create` | POST (JSON) | Crea usuario. Body: `name`, `email`, `password`, `role_id` |
| `?action=update` | POST (JSON) | Actualiza usuario. Body: `id`, `name`, `email`, `role_id`, `password` (opcional) |
| `?action=toggle` | POST (JSON) | Activa / desactiva. Body: `id` |
| `?action=delete` | POST (JSON) | Elimina. Body: `id` |

Todos los POST requieren header `X-CSRF-Token`.

### api/admin/roles.php

| Método | Descripción |
|---|---|
| GET | Lista de roles con conteo de usuarios |
| POST (JSON) | Actualiza descripción. Body: `id`, `description` |

### api/admin/reportes.php

Gestión del catálogo de reportes y categorías.

| Action | Método | Descripción |
|---|---|---|
| GET | — | Lista categorías y reportes |
| `cat_create` | POST | Nueva categoría |
| `cat_update` | POST | Editar categoría |
| `cat_delete` | POST | Eliminar categoría (solo si está vacía) |
| `rep_update` | POST | Editar reporte (nombre, ícono, estado, categoría, orden) |

### api/admin/bitacora.php

| Param | Descripción |
|---|---|
| `page`, `size` | Paginación |
| `q` | Búsqueda en descripción |
| `user_id` | Filtrar por usuario |
| `action_filter` | Filtrar por tipo de acción |

Respuesta incluye campos `users` y `actions` para poblar los filtros del dropdown.

### api/admin/datos.php

Devuelve conteo y tamaño en MB de todas las tablas principales.

```json
{
  "server": "Apache/2.4 PHP/8.2.7",
  "db": "pel_electoral",
  "now": "2026-06-11 15:30:00",
  "juntas": 7154,
  "sources": [
    { "key": "voters", "table": "voters", "count": 3731788, "size_mb": 1240.5 }
  ]
}
```

### api/admin/pipelines.php

Estado de las migraciones SQL aplicadas.

```json
{
  "total": 15,
  "applied": 15,
  "pending": 0,
  "migrations": [
    {
      "file": "20260601_000001_base_schema.sql",
      "ran": true,
      "executed_at": "2026-06-01 10:00:00",
      "orphaned": false
    }
  ]
}
```

---

## Códigos de error

| Código | Significado |
|---|---|
| `400` | Parámetros inválidos |
| `401` | Sin sesión activa |
| `403` | Sin permisos (requiere administrador) |
| `405` | Método no permitido |
| `419` | Token CSRF inválido o expirado |
| `500` | Error interno del servidor |
