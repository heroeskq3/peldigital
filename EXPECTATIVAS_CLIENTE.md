# Expectativas del cliente y brechas del proyecto

Documento de trabajo basado en la minuta del 04 de junio de 2026 y en la
revision tecnica realizada sobre el proyecto PEL Digital.

## Vision del cliente

PEL Digital se entiende como una plataforma interna para analisis, consulta y
explotacion de informacion electoral, territorial y estadistica del Partido
Esperanza y Libertad.

La plataforma no esta concebida como portal publico ni como herramienta de
interaccion ciudadana. La iniciativa de "Partido Politico Digital" corresponde a
un alcance complementario y futuro.

## Reporte actual conservado

El prototipo inicial queda formalizado como:

```text
Analisis -> Padron Electoral -> Distribucion Territorial
```

Este reporte cubre:

- Distribucion territorial del padron nacional.
- Navegacion por provincia, canton y distrito.
- Concentracion de inscritos por territorio.
- Consulta real del padron por region.
- Vista de inscritos en el extranjero.
- Minimapa de contexto para volver a vista nacional.

Este reporte pertenece al bloque de **Analisis Territorial** y tambien sirve
como base del bloque **Padron Electoral**.

## Expectativas priorizadas por la minuta

### Participacion electoral

Solicitado:

- Personas que votaron.
- Personas que no votaron.
- Comparativos historicos de participacion.
- Tendencias de participacion por territorio.

Estado actual:

- No implementado.
- El sistema tiene padron, pero no resultados de votacion ni abstencion real.

Datos requeridos:

- Resultados electorales historicos.
- Total de votos emitidos por eleccion.
- Abstencion por territorio.
- Idealmente resultados por JRV.

### Segmentacion electoral

Solicitado:

- Segmentacion por poblacion.
- Segmentacion por participacion electoral.
- Segmentacion por comportamiento electoral.
- Segmentacion por distrito electoral.

Estado actual:

- Parcial.
- Existe segmentacion territorial por padron.
- No existe segmentacion por participacion ni comportamiento electoral.
- `electoral_district_id` esta presente en la tabla `voters`, pero no esta
  poblado en los registros verificados.

Datos requeridos:

- Distritos electorales completamente mapeados.
- Variables demograficas confiables si se desean segmentos por edad/sexo.
- Resultados historicos si se desea comportamiento electoral.

### Analisis territorial

Solicitado:

- Resultados por distrito electoral.
- Comparativos entre distritos.
- Identificacion de zonas prioritarias.

Estado actual:

- Parcial.
- Ya existe mapa por provincia, canton y distrito.
- Ya existe ranking por poblacion inscrita.
- No hay resultados electorales ni priorizacion automatica.

Siguiente paso sugerido:

- Crear reportes comparativos de padron por provincia/canton/distrito.
- Definir formula de "zona prioritaria" antes de automatizarla.

### Juntas Receptoras de Votos

Solicitado:

- Juntas con mayor participacion.
- Juntas con menor participacion.
- Comparativos entre juntas.
- Identificacion de oportunidades territoriales.

Estado actual:

- Parcial a nivel de padron.
- La tabla `voters` tiene campo `junta`.
- Se verificaron `7,154` juntas distintas.
- No hay participacion real por junta porque falta informacion de votos
  emitidos/abstencion por JRV.

Primer reporte viable:

- Inscritos por junta.
- Juntas con mayor padron.
- Juntas con menor padron.
- Distribucion de juntas por provincia/canton/distrito.

Reporte pendiente de datos adicionales:

- Participacion por JRV.
- Abstencion por JRV.
- Oportunidad electoral por JRV.

### Indicadores estrategicos

Solicitado:

- Concentracion de votantes.
- Tendencias territoriales.
- Potencial de crecimiento electoral.
- Priorizacion de zonas para actividades de campana.

Estado actual:

- Concentracion de inscritos: implementada parcialmente.
- Tendencias territoriales: no implementado.
- Potencial de crecimiento: no implementado.
- Priorizacion de zonas: no implementado.

Datos requeridos:

- Historico electoral por territorio.
- Resultados por partido/candidato.
- Criterios politicos para ponderar oportunidad.

## Aspectos visuales y de identidad

Solicitado:

- Usar paleta institucional compartida por el partido.
- Consolidar identidad visual de PEL Digital.

Estado actual:

- Hay una identidad visual base con encabezado PEL Digital, logo y paleta morada.
- Falta validar contra la paleta oficial suministrada por el partido.

## Actualizacion de datos TSE

Solicitado:

- Incorporar informacion visible sobre la fecha de actualizacion oficial de los
  datos provenientes del TSE.

Estado actual:

- El API expone `fuente` y `generado`.
- El README documenta la ultima carga verificada.
- La interfaz todavia no muestra esta informacion de forma visible.

Pendiente:

- Mostrar fuente y fecha de actualizacion en el panel o footer.
- Reemplazar textos visibles que aun dicen "poblacion simulada/no oficial".

## Seguridad y acceso interno

Solicitado/implícito:

- Uso exclusivo interno del partido.

Estado actual:

- La aplicacion tiene login basico por sesion.
- El usuario demo esta hardcodeado en `auth.php`.
- La BD tiene tablas de usuarios, roles y permisos, pero no estan integradas al
  login actual.

Pendiente:

- Integrar autenticacion contra `users`.
- Activar roles/permisos por modulo.
- Revisar politicas de acceso antes de despliegue externo.

## Valoracion de avance

Segun la revision tecnica:

- Para el alcance de mapa territorial + consulta de padron: avance aproximado
  `55% - 65%`.
- Para las expectativas completas de la minuta: avance aproximado `30% - 35%`.
- Para la vision amplia de Partido Politico Digital: menos de `15%`, por ser un
  alcance distinto.

## Recomendacion de roadmap inmediato

1. Cerrar el reporte actual de Padron Electoral -> Distribucion Territorial.
2. Mostrar fuente y fecha de actualizacion TSE en la interfaz.
3. Crear reporte de inscritos por JRV.
4. Crear comparativos territoriales de padron.
5. Definir y cargar fuentes oficiales de resultados electorales.
6. Construir reportes de participacion y abstencion.
7. Definir criterios de priorizacion territorial con el equipo politico.

## Aclaracion de alcance

El sistema actual trabaja principalmente con padron. Para responder reportes de
participacion, abstencion, tendencias y comportamiento electoral se requieren
datos electorales adicionales. Sin esos insumos, cualquier indicador de
participacion seria incompleto o inferido.
