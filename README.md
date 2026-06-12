# PEL Digital

Plataforma interna de análisis electoral y territorial para el Partido Esperanza y Libertad de Costa Rica.  
Uso exclusivo interno del partido. No distribuir.

Desarrollado por [Oval](https://oval.co.cr).

---

## Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.1+ sin framework — funciones globales, includes directos |
| Base de datos | MySQL / MariaDB — InnoDB, FULLTEXT habilitado |
| Mapas | Leaflet 1.9.4 |
| Íconos | Bootstrap Icons 1.11.3 |
| Frontend | HTML / CSS / JS puro — sin bundler, sin npm |
| Servidor local | XAMPP (Apache + MySQL) |

---

## Arquitectura del layout

Todas las páginas autenticadas comparten los mismos **cuatro parciales** en esta cadena:

```
head.php → header.php → [contenido de la página] → footer.php → scripts.php
```

| Archivo | Responsabilidad |
|---|---|
| `includes/layout/head.php` | DOCTYPE, meta, anti-flash de tema, CSS, variables `$appBaseUrl` y `APP_BASE` |
| `includes/layout/header.php` | Barra superior con logo, navegación dinámica desde BD y menú de usuario |
| `includes/layout/footer.php` | Footer con atribución TSE + badge "Powered by Oval" |
| `includes/layout/scripts.php` | JS al final del body (nav.js siempre; leaflet+chart+app scripts por defecto o `$pageScripts`) |

El `<div class="app-shell">` lo abre `head.php` y lo cierra `footer.php`.  
El `</body></html>` los cierra siempre `scripts.php`.

### Variables de inyección

```php
$extraHeadLinks = ['assets/css/mi-pagina.css']; // CSS extra inyectado en <head>
$pageScripts    = ['assets/js/mi-pagina.js'];   // reemplaza los scripts por defecto
```

---

## Páginas principales

| Archivo | Ruta amigable | Descripción |
|---|---|---|
| `login.php` | `/login` | Acceso al sistema — reCAPTCHA v3, recordar sesión, ojito |
| `index.php` | `/` | Redirige a `/home` |
| `home.php` | `/home` | Hub de reportes — card con stats del padrón y catálogo de reportes |
| `reports.php` | `/reportes/{slug}` | Ensamblador de reportes — carga el reporte indicado desde BD |
| `admin.php` | `/admin` | Panel de administración (requiere rol admin) |
| `perfil.php` | `/perfil` | Mi perfil — editar nombre/email y cambiar contraseña |
| `logout.php` | `/logout` | Cierra sesión y limpia cookies de "recordar" |

---

## Seguridad

### Autenticación (`auth.php`)
- Login contra tabla `users` (email o nombre de usuario, contraseña con `password_hash`)
- Fallback `demo`/`demo1234` solo si `APP_ENV != production`
- CSRF token por sesión (`$_SESSION['csrf_token']`)
- Bitácora de intentos fallidos

### reCAPTCHA v3
- Invisible — sin checkbox visible, badge flotante en esquina
- Ejecuta en background al enviar el formulario de login
- Valida score ≥ 0.5 contra `siteverify` de Google
- Claves en `.env`: `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET`

### Recordar sesión ("Mantener sesión iniciada")
- Checkbox en el login que extiende la cookie de sesión a **30 días**
- Marca adicional `pel_rm` para restaurar el lifetime en visitas futuras
- `cerrarSesion()` limpia ambas cookies

---

## Menú de usuario (header)

El ícono `bi-person-circle` en el header despliega un panel con:
- Avatar (inicial del nombre), nombre completo y email
- **Editar perfil** → `/perfil`
- **Cambiar contraseña** → `/perfil#contrasena`
- **Cerrar sesión**

---

## Perfil de usuario (`perfil.php` + `api/profile.php`)

- Carga datos reales desde BD (`name`, `email`, `role`, `created_at`)
- **Editar info**: actualiza `name` y `email` en `users`, refresca la sesión
- **Cambiar contraseña**: verifica contraseña actual, exige mínimo 8 caracteres, hashea con `PASSWORD_DEFAULT`
- Rol mostrado como campo de solo lectura (no editable por el propio usuario)

---

## Tema (claro/oscuro)

- **Defecto:** light (independiente del OS)
- El usuario cambia el tema con el toggle en el header; su elección se persiste en `localStorage` (`cr-theme`)
- El snippet anti-flash en `head.php` aplica el tema antes de renderizar para evitar parpadeo

---

## Estructura de archivos

```
pel_02/
├── index.php                      # Redirige a /home
├── login.php                      # Login con reCAPTCHA v3 + recordar sesión
├── logout.php                     # Cierre de sesión
├── home.php                       # Hub de reportes
├── reports.php                    # Ensamblador de reportes
├── admin.php                      # Panel de administración
├── perfil.php                     # Perfil de usuario
├── auth.php                       # Autenticación, sesión, CSRF, helpers
│
├── includes/
│   ├── layout/
│   │   ├── head.php               # DOCTYPE, meta, CSS, anti-flash de tema
│   │   ├── header.php             # Barra superior + nav dinámica + user menu
│   │   ├── footer.php             # Footer TSE + Powered by Oval
│   │   ├── loader.php             # Spinner de carga
│   │   └── scripts.php           # JS al final del body
│   ├── modals/
│   │   ├── padron.php             # Modal de consulta del padrón
│   │   └── bitacora.php           # Modal de bitácora
│   ├── reports/
│   │   ├── padron-distribucion.php    # Reporte 1 — Distribución Territorial
│   │   ├── jrv-inscritos.php          # Reporte 2 — Distribución Padrón / JRV
│   │   ├── jrv-analisis.php           # Reporte 3 — Análisis Estratégico JRV
│   │   ├── participacion.php          # Reporte 4 — Participación Electoral
│   │   ├── segmentacion.php           # Reporte 5 — Segmentación Electoral
│   │   └── analisis-territorial.php   # Reporte 6 — Análisis Territorial
│   └── admin/
│       ├── usuarios.php           # CRUD de usuarios
│       ├── roles.php              # CRUD de roles
│       └── ...                    # Otros módulos admin
│
├── api/
│   ├── profile.php                # Actualizar perfil / cambiar contraseña
│   ├── poblacion.php              # Agregados territoriales del padrón (caché 1h)
│   ├── padron.php                 # Consulta paginada del padrón
│   ├── jrv.php                    # Datos JRV por territorio
│   ├── segmentacion.php           # Segmentación por sexo
│   ├── participacion.php          # Participación electoral
│   ├── analisis_territorial.php   # Comparativos territoriales
│   ├── parties.php                # Catálogo de partidos
│   ├── bitacora.php               # Lectura de bitácora
│   ├── log.php                    # Registro de eventos frontend
│   └── admin/
│       ├── usuarios.php           # API CRUD usuarios
│       ├── roles.php              # API CRUD roles
│       └── ...
│
├── assets/
│   ├── css/app/
│   │   ├── tokens.css             # Variables de tema (light/dark) y tipografía
│   │   ├── nav.css                # Header, navegación y user menu
│   │   ├── layout.css             # Estructura general, campos, perfil
│   │   ├── modals.css             # Modales, login card
│   │   ├── hub.css                # Hub de reportes (home.php)
│   │   ├── reports.css            # Panel lateral, tablas, paginación
│   │   ├── admin.css              # Panel de administración
│   │   └── responsive.css        # Breakpoints ≤820px
│   ├── js/
│   │   ├── nav.js                 # Drawer móvil, dropdowns, tema, user menu
│   │   └── app/
│   │       ├── core.js            # Estado global, fmt(), fmtPct(), abreviarV()
│   │       ├── map.js             # Mapa Leaflet, capas GeoJSON
│   │       ├── controls.js        # Buscador, selects en cascada, diáspora
│   │       ├── reports.js         # Lógica de reportes JRV / Juntas
│   │       └── padron-bitacora.js # Modal del padrón y bitácora
│   └── img/
│       └── logo02.png             # Logo del partido
│
├── lib/
│   ├── db.php                     # dbConnect(): PDO singleton
│   ├── env.php                    # Carga .env
│   ├── bitacora.php               # Registro de eventos en BD
│   └── parsers/
│       ├── PadronTSEParser.php    # Parser del padrón plano del TSE
│       └── AvrParser.php          # Parser de resultados AVR
│
├── data/
│   ├── provincias.geojson
│   ├── cantones.geojson
│   ├── distritos.geojson
│   └── poblacion_cache.json       # Caché auto-generado por api/poblacion.php (TTL 1h)
│
├── scripts/                       # ETL y migraciones (CLI, no accesibles por web)
├── migrations/                    # SQL versionadas
├── raw/                           # Archivos crudos TSE — NO están en git
└── docs/
    └── produccion.md              # Guía de despliegue
```

---

## Formateo de números en UI

Funciones centralizadas en `assets/js/app/core.js`:

```js
const fmt    = (n) => n.toLocaleString("es-CR");        // coma miles, punto decimales
const fmtPct = (x) => (x*100).toFixed(1).replace(".0","") + "%";
const abreviarV = (n) => abreviar(n);                   // 1.2k / 3.4M
```

Todos los valores numéricos mostrados en la UI deben pasar por `fmt()` o `abreviarV()`.

---

## Inventario de reportes

| # | Nombre | Estado | Fuente |
|---|---|---|---|
| 1 | Distribución Territorial | Activo | Padrón TSE 2026 |
| 2 | Distribución Padrón / JRV | Activo | Padrón TSE 2026 |
| 3 | Análisis Estratégico · JRV | Activo | Padrón TSE 2026 |
| 4 | Participación Electoral | Activo | AVR TSE 2026/2022 |
| 5 | Segmentación Electoral | Parcial | Padrón TSE 2026 (sexo enriquecido, fecha_nac pendiente) |
| 6 | Análisis Territorial | Activo | AVR 2026/2024/2022 |
| 7 | Indicadores Estratégicos | Pendiente | Requiere definir KPIs |

---

## Base de datos

Dos bases de datos:

| Variable `.env` | Base | Propósito |
|---|---|---|
| `DB_*` | `pel_electoral` | Sistema: users, roles, reports, audit_logs |
| `DW_*` | `peldigital_data` | Datos: voters, provinces, election_results, summaries |

### Tablas clave

| Tabla | Registros (12-jun-2026) |
|---|---|
| `voters` | 3,731,788 |
| `summary_jrv` | 7,154 |
| `users` | 3 |
| `roles` | 3 |
| `reports` | 7 |

### Campos en `voters` — estado actual

**Poblados:** `cedula`, `nombre`, `apellido1`, `apellido2`, `fecha_caduc`, `junta`, `province_id`, `canton_id`, `district_id`, `sexo` (M/F/N via ETL)

**Vacíos (NULL):** `fecha_nac` (bloquea segmentación por edad), `electoral_district_id`, `polling_place_id`

---

## Correr localmente

```bash
# Requisito: XAMPP con Apache y MySQL corriendo
# Proyecto en: /Applications/XAMPP/xamppfiles/htdocs/pel_02
# Acceso:      http://localhost/pel_02/
```

Copiar `.env.example` a `.env` y configurar las credenciales de BD.

---

## Pipeline ETL

```bash
php scripts/migrate.php                                          # 1. Migraciones
php scripts/import_distelec.php --file=raw/padron/distelec.txt  # 2. Catálogo geográfico
php scripts/import_padron.php --file=raw/padron/PADRON_COMPLETO.txt  # 3. Padrón (~20 min)
php scripts/enrich_sexo.php --batch=0                           # 4. Sexo (~51 seg)
php scripts/import_resultados.php --json=raw/avr/avr2026.json --type=P --label="Presidencia 2026"
php scripts/refresh_summaries.php                                # 5. Resúmenes
```

---

## Pendientes técnicos

| Item | Impacto |
|---|---|
| `fecha_nac` NULL en todos los registros | Bloquea segmentación por edad |
| `polling_places` sin catálogo oficial | Reporte de locales no publicable |
| Reporte #7 Indicadores Estratégicos | Requiere KPIs acordados con el cliente |
| Coordinar acceso oficial a `fecha_nac` con TSE | Requerido para segmentación por edad |

---

## Cumplimiento normativo

Los datos del padrón y resultados electorales son **fuentes públicas oficiales del TSE de Costa Rica**.  
Esta plataforma los reproduce para uso interno del partido — no modifica ni certifica datos electorales.  
El TSE es la única fuente autorizada de resultados (Art. 102 de la Constitución Política).

---

## Créditos

- Padrón y catálogo geográfico: [TSE Costa Rica](https://www.tse.go.cr)
- Fronteras distritales GeoJSON: `schweini/CR_distritos_geojson`
- Desarrollo: [Oval](https://oval.co.cr)
