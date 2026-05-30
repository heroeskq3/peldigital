# PEL Digital · Mapa de análisis poblacional de Costa Rica

Tablero interactivo de **analítica visual** sobre la población de Costa Rica, con
mapa de calor (choropleth) y drill-down por **provincia → cantón → distrito**.
Construido con **PHP + Leaflet**. Los datos son **simulados (no oficiales)**,
generados de forma determinista para fines de demostración.

## Características

- **Mapa de calor** con navegación jerárquica (provincia, cantón, distrito).
- **Métricas seleccionables**:
  - Padrón (domicilio electoral)
  - Residencia (residencia real, vía matriz origen-destino)
  - Saldo (diferencia padrón − residencia)
  - Abstención
  - Participación
  - Extranjero (inscritos que residen en el exterior)
- **Búsqueda** de regiones y selects encadenados.
- **Resumen del nivel**, ranking Top 10 y leyenda de escala.
- **Modal de resultados (padrón)** tipo DataTable con paginación, búsqueda
  insensible a acentos y **exportación a Excel (CSV)**. Incluye cédula, nombre,
  apellidos, edad, fecha de nacimiento, hijos, estado civil y lugar de votación
  (provincia, cantón, distrito y centro).
- **Login** con sesión PHP (`password_hash` / `password_verify`).
- **Tema claro / oscuro** con preferencia persistida.

## Stack

- PHP 8.x (servido vía XAMPP o `php -S`)
- [Leaflet 1.9.4](https://leafletjs.com/) para el mapa
- GeoJSON de fronteras (provincias, cantones, distritos)
- HTML/CSS/JS sin frameworks de build

## Estructura

```
.
├── index.php            # Tablero principal (protegido por login)
├── login.php            # Pantalla de ingreso
├── logout.php           # Cierre de sesión
├── auth.php             # Sesión y verificación de credenciales
├── api/
│   └── poblacion.php    # API JSON de población dummy determinista
├── assets/
│   ├── css/style.css
│   ├── js/app.js        # Lógica de mapa, métricas y padrón
│   └── img/             # Logos
└── data/
    ├── provincias.geojson
    ├── cantones.geojson
    └── distritos.geojson
```

## Cómo ejecutar

Con PHP integrado:

```bash
php -S localhost:8099
```

Luego abrir <http://localhost:8099/>. También funciona colocando la carpeta en
`htdocs` de XAMPP.

## Acceso (demo)

- Usuario: `demo`
- Contraseña: `demo1234`

Para agregar usuarios, generar un hash y añadirlo a `$USUARIOS` en `auth.php`:

```bash
php -r 'echo password_hash("tu-clave", PASSWORD_DEFAULT), PHP_EOL;'
```

## Nota sobre los datos

Toda la población, abstención, residencia, padrón y datos personales del listado
son **simulados** mediante un PRNG determinista (semilla por código de región).
No representan cifras oficiales ni personas reales.

Fronteras: `schweini/CR_distritos_geojson`.
