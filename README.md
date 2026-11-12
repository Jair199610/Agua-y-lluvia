# Aqua Sentinel

**Hackathon Veracruz Tech Week 2026 · Reto 3: Agua y lluvia**

Plataforma web que junta pronóstico de lluvia, antecedentes y reportes de vecinos para anticipar encharcamientos por colonia, detectar posibles fugas y orientar a la población hacia puntos seguros.

## Equipo

| Integrante | Carrera | Parte del proyecto |
|---|---|---|
| [Nombre] | Ing. en Sistemas Computacionales | [ ] |
| [Nombre] | Ing. en Sistemas Computacionales | [ ] |
| [Nombre] | Ing. en Sistemas Computacionales | [ ] |
| [Nombre] | Ing. en Sistemas Computacionales | [ ] |
| [Nombre] | Gestión Empresarial | [ ] |

## El problema

[2 o 3 líneas: quién sufre el problema y por qué la información no le llega a tiempo.]

**Demo:** [enlace]
**Video de respaldo:** [enlace]

## Las 6 funciones y su estado real

Sean exactos aquí: presentar como funcional algo que no lo es descalifica. Actualicen la columna "Estado" después de probar cada función.

| # | Función | Qué hace hoy | Límite que hay que decir | Estado |
|---|---|---|---|---|
| 1 | Radar de riesgo | Mapa con un círculo por colonia en verde, amarillo o rojo según su puntaje | Son 10 colonias con coordenadas aproximadas | [ ] |
| 2 | Predicción | Puntaje de 0 a 100 con desglose (lluvia, antecedentes, reportes), lluvia por hora y un análisis en texto. Cada cambio se guarda en `evaluaciones_riesgo` | El puntaje es una fórmula, no un modelo de IA ni una probabilidad. La IA clasifica los reportes y redacta el análisis | [ ] |
| 3 | Alerta ciudadana | Las alertas se abren y se cierran solas en la tabla `alertas` según el riesgo. Las autoridades también pueden mandar **un solo aviso a varias colonias**. Cada usuario ve y recibe solo las alertas de su colonia (INNER JOIN entre `alertas` y `usuarios`), en pantalla, por notificación del navegador y por correo | Las notificaciones del navegador solo llegan con la página abierta. El correo depende de configurar una cuenta de envío. No hay SMS ni WhatsApp. Cada usuario tiene una sola colonia | [ ] |
| 4 | Detector de fugas | Agrupa reportes de fuga y falta de agua a menos de 300 m en 24 h y les da una confianza | Es una regla de coincidencia geográfica, no IA | [ ] |
| 5 | Centro de protección civil | Zonas por prioridad, incidentes con cambio de estado y disponibilidad de puntos seguros. Solo lo ven los roles que administran | Un Operador de Agua ve los incidentes pero no los puntos seguros, porque su rol no tiene ese permiso | [ ] |
| 6 | Rutas seguras | Ruta al punto seguro con estado "disponible" más conveniente, evitando zonas en riesgo | Las zonas son círculos aproximados, no calles inundadas reales. Las rutas vienen del servidor público de OSRM (para auto, solo demos). Los puntos seguros son de ejemplo | [ ] |

Además hay un **simulador de lluvia** para mostrar el sistema en un día seco. Cuando está activo, la pantalla lo indica con un aviso de "MODO SIMULACIÓN", el resultado se guarda en la tabla `simulaciones` y no se generan alertas reales.

## Inicio de sesión, roles y permisos

Para usar la página hay que entrar con correo y contraseña. Todo está en `lib/sesion.php`, `api/sesion.php` y `api/usuarios.php`, con las tablas `usuarios`, `roles`, `permisos` y `rol_permiso`.

- **Primera cuenta:** si la tabla `usuarios` está vacía, la primera cuenta que se cree queda como Administrador. Así no hay contraseñas de fábrica en el repositorio. Créenla en cuanto instalen el sistema.
- **Cada quien crea su cuenta** desde la pantalla de entrada, con su nombre, correo, teléfono, colonia donde vive y tipo de cuenta. El Administrador no tiene que registrar a nadie uno por uno.
- **Tipo de cuenta:** se elige al registrarse (Ciudadano, Administrador, Protección Civil u Operador de Agua).
- **Decisión del equipo, con su riesgo:** por defecto el registro es libre, así que cualquiera puede elegir "Administrador". Es cómodo para la demo, pero en un sistema real no debe quedar así. Para cerrarlo se pone `REGISTRO_CLAVE` en el `.env`: desde entonces, los roles que no son Ciudadano piden esa clave al registrarse.
- **Reportes del ciudadano:** con `CIUDADANO_PUEDE_REPORTAR=no` en el `.env`, el ciudadano solo consulta y recibe alertas; con `si` (por defecto) también puede enviar reportes.
- **Contraseñas:** se guardan cifradas con `password_hash`; nunca en texto.
- **Permisos:** cada ruta de `api/` comprueba el permiso del rol antes de responder. El menú solo esconde lo que no toca; quien autoriza es el servidor.

| Sección | Permiso que pide | Administrador | Protección Civil | Operador de Agua | Ciudadano |
|---|---|---|---|---|---|
| Radar y reportar | `dashboard`, `reportes` | Sí | Sí | Sí | Sí |
| Predicción y simulador | `riesgos`, `simulaciones`, `monitoreo` | Sí | Sí | No | No |
| Alerta ciudadana: ver y recibir | `alertas` | Todas | Todas | Todas | Solo las de su colonia |
| Enviar un aviso a una o varias colonias | `alertas` y no ser Ciudadano | Sí | Sí | Sí | No |
| Detector de fugas | `reportes` | Sí | Sí | Sí | Sí |
| Rutas seguras | `puntos_seguros` | Sí | Sí | No | Sí |
| Centro de protección civil | `reportes` y no ser Ciudadano | Sí | Sí | Solo incidentes | No |
| Registrar colonias | `colonias` y no ser Ciudadano | Sí | Sí | Sí | No |
| Registrar puntos seguros y cambiar su estado | `puntos_seguros` y no ser Ciudadano | Sí | Sí | No | No |
| Usuarios: lista por colonia con buscador, alta y cambio de estado | `usuarios` | Sí | No | No | No |

El Ciudadano consulta, recibe alertas y reporta incidentes (así lo define su rol en la base: "Registro y consulta de reportes"); no puede registrar ni administrar nada más.

Límites que hay que decir: no hay "olvidé mi contraseña" ni verificación del correo, y los registros se pueden crear pero todavía no editar ni borrar desde la página.

## Alertas por correo

Cuando se abre una alerta de inundación en una colonia, o una autoridad manda un aviso, el servidor manda un correo (una sola vez) a los usuarios activos que viven en las colonias afectadas. Los destinatarios salen de un `INNER JOIN` entre `alertas` y `usuarios` por `id_colonia` (función `destinatariosDeAlertas` en `lib/correo.php`), así que solo se avisa a las colonias en riesgo. Si la alerta es urgente o de emergencia, también le llega al personal de Protección Civil. El código está en `lib/correo.php` y `api/correo.php`.

Para activarlas hacen falta dos cosas:

1. **Importar `sql/correo.sql`** dentro de la base `aqua_sentinel`. Agrega la columna `id_colonia` a la tabla `usuarios` (es el único cambio que se hizo a la base del equipo). `instalar_todo.sql` ya lo incluye.
2. **Configurar la cuenta de envío en el `.env`**: `SMTP_USER` (el correo) y `SMTP_PASS`. Con Gmail hay que activar la verificación en dos pasos y crear una "contraseña de aplicación"; esa es la que va en `SMTP_PASS`.

Si falta cualquiera de las dos, no se envía nada y la pantalla lo dice. El botón "Enviarme un correo de prueba" sirve para comprobar la configuración.

Límites que hay que decir: las simulaciones nunca mandan correos; los destinatarios van en copia oculta; no se verifica que el correo del usuario exista; y una cuenta gratuita de Gmail tiene un tope diario de envíos, suficiente para una demo pero no para una ciudad.

## Cómo se calcula el riesgo

Todo está en `lib/riesgo.php`. El puntaje va de 0 a 100 y suma tres partes:

- **Lluvia (hasta 50 puntos):** 2.5 puntos por cada mm pronosticado en 6 horas, ajustado por la vulnerabilidad de la colonia (`nivel_vulnerabilidad`).
- **Antecedentes (hasta 15 puntos):** 3 puntos por cada reporte anterior de inundación o encharcamiento en la colonia durante el último año (máximo 5). Solo cuentan cuando hay lluvia pronosticada.
- **Reportes (hasta 60 puntos):** 10 puntos por cada punto de urgencia (baja 1, media 2, alta 3, crítica 4) de los reportes de inundación o encharcamiento de las últimas 3 horas que sigan pendientes o en revisión.

Menos de 30 es riesgo bajo, de 30 a 59 es medio y de 60 en adelante es alto (85 o más se guarda como crítico).

En `evaluaciones_riesgo`, la columna `probabilidad` guarda este puntaje de 0 a 100 y `modelo_ia` guarda `formula_puntaje_v1`. No es una probabilidad calculada por un modelo.

[Anoten aquí si cambian los pesos y por qué.]

## Dónde participa la IA

- **Clasificación de reportes** (`lib/clasificador.php`): Gemini lee el texto libre del vecino y devuelve tipo, gravedad y colonia. Si no hay llave o falla, se usa un clasificador por palabras clave.
- **Análisis del riesgo** (`lib/analisis.php`): Gemini redacta la explicación a partir de los datos de la colonia. Si no hay llave, se usa una plantilla de texto.

En ambos casos la pantalla muestra cuál método se usó.

## Estructura

```
index.php               Página principal (plantilla Soft UI Dashboard)
assets/js/app/          Un archivo por función: radar, prediccion, alertas, fugas, rutas, centro, usuarios
api/                    Rutas del backend (devuelven JSON)
lib/                    Lógica: riesgo, lluvia, clasificador, fugas, rutas, análisis
sql/aqua_sentinel.sql   Base de datos del equipo (estructura)
sql/datos_ejemplo.sql   Colonias y puntos seguros de ejemplo
sql/instalar_todo.sql   Todo en un solo archivo (borra y vuelve a crear las tablas)
sql/correo.sql          Agrega usuarios.id_colonia para las alertas por correo (no borra nada)
```

| Ruta | Para qué |
|---|---|
| `GET` / `POST api/colonias.php` | Colonias con lluvia, puntaje, nivel y desglose; registrar una colonia |
| `GET api/analisis.php?colonia_id=` | Explicación del riesgo de una colonia |
| `GET` / `POST api/reportes.php` | Listar reportes, crear uno o cambiar su estado |
| `GET api/fugas.php` | Grupos de reportes que apuntan a una fuga |
| `GET` / `POST api/alertas.php` | Alertas vigentes (y guarda evaluaciones y alertas); enviar o finalizar un aviso a varias colonias |
| `POST api/simulaciones.php` | Guarda una simulación de lluvia |
| `GET` / `POST api/puntos.php` | Puntos seguros, su estado y registrar uno nuevo |
| `GET api/ruta.php?lat=&lng=` | Ruta segura desde un punto |
| `POST api/demo.php` | Cargar o borrar reportes de demostración |
| `GET` / `POST api/sesion.php` | Entrar, crear cuenta, salir y saber quién tiene la sesión |
| `GET` / `POST api/usuarios.php` | Administrar usuarios (solo Administrador) |
| `GET api/mediciones.php?colonia_id=` | Historial del pronóstico de lluvia |
| `GET` / `POST api/correo.php` | Mi colonia para las alertas por correo y correo de prueba |

## Base de datos `aqua_sentinel`

| Tabla | Cómo la usa la aplicación |
|---|---|
| `colonias` | Lee las colonias activas que tienen latitud y longitud |
| `reportes` | Guarda cada reporte ciudadano ya clasificado; el centro cambia su estado |
| `evaluaciones_riesgo` | Guarda una fila cada vez que cambia el riesgo de una colonia |
| `alertas` | Abre y cierra alertas automáticas de inundación; también muestra las que se creen a mano |
| `simulaciones` | Guarda cada simulación de lluvia, una fila por colonia |
| `puntos_seguros` | Lee los puntos; el centro cambia su estado (disponible, ocupado, cerrado) |
| `usuarios`, `roles`, `permisos`, `rol_permiso` | Inicio de sesión y permisos de cada rol. Los reportes y simulaciones guardan quién los hizo. `usuarios.id_colonia` (agregada con `sql/correo.sql`) guarda la colonia para las alertas por correo |
| `puntos_monitoreo`, `mediciones` | Historial del pronóstico de lluvia: un punto virtual por colonia y una lectura cada 10 minutos |

Las 12 tablas de la base se usan. En `mediciones` se guarda lluvia **pronosticada** por Open-Meteo (`fuente = 'api'`, unidad "mm en 6 h"), no lecturas de sensores reales.

Los reportes de demostración se reconocen porque su título empieza con `[DEMO]`.

## Datos

- Pronóstico de lluvia: Open-Meteo (dato público).
- Colonias: 10 colonias de Veracruz con **coordenadas aproximadas**. [Indicar la fuente cuando las verifiquen.]
- Vulnerabilidad por colonia: **dato sintético** creado para la demo. Los antecedentes salen de los reportes guardados en el propio sistema.
- Puntos seguros: **datos de ejemplo**, no son refugios reales. [Cambiar si consiguen una fuente pública de Protección Civil.]
- Reportes: los escribe quien usa la página. Los de demostración llevan `[DEMO]` en el título. No se usan datos personales reales.

## Código previo, librerías y plantillas

Todo el código propio se escribió después de las 13:00 h del miércoles 11 de noviembre.

| Librería o plantilla | Licencia | Para qué |
|---|---|---|
| Soft UI Dashboard 3 (Creative Tim) | MIT | Plantilla visual: estilos, menú, tarjetas y tablas |
| Bootstrap 5, Popper, Perfect Scrollbar | MIT | Incluidos en la plantilla |
| Chart.js | MIT | Gráfica de lluvia por hora |
| Leaflet | BSD de 2 cláusulas | Mapas |
| PHPMailer 6.9.3 | LGPL 2.1 | Envío de correos (`lib/vendor/phpmailer/`) |
| OpenStreetMap | ODbL | Imágenes del mapa |
| Open-Meteo | API pública | Pronóstico de lluvia |
| OSRM (servidor público de demostración) | API pública | Rutas por calle |
| Gemini API (nivel gratuito) | API | Clasificación de reportes y análisis |

## Bitácora de IA

Llénenla conforme avanzan, no al final.

| Hora | Herramienta | Para qué se usó | Decisión del equipo |
|---|---|---|---|
| Mié 13:40 | Claude | Proponer la idea y el alcance | [Qué aceptaron y qué cambiaron] |
| Mié 14:40 | Claude | Código base del backend en PHP | [ ] |
| Mié 15:00 | Claude | Integrar la plantilla y generar el código de las 6 funciones que definió el equipo | Las 6 funciones las definió el equipo. [Qué revisaron y qué cambiaron] |
| Mié 16:00 | Claude | Adaptar el código a la base de datos `aqua_sentinel` | La base de datos la diseñó el equipo. [Qué revisaron y qué cambiaron] |
| Mié 18:40 | Claude | Inicio de sesión con roles y permisos, y registro del pronóstico en `mediciones` | Los roles y permisos los definió el equipo en la base. [Qué revisaron y qué cambiaron] |
| Mié 19:15 | Claude | Aviso a las autoridades cuando llega un reporte y alertas por correo | El equipo decidió que el canal fuera el correo. [Qué revisaron y qué cambiaron] |
| Mié 20:05 | Claude | Registro con tipo de cuenta, colonia y clave de autoridad | El equipo pidió que cada persona registre su propio perfil. [Qué revisaron y qué cambiaron] |
| Mié 19:50 | Claude | Avisos a varias colonias, alertas solo para la colonia del usuario (INNER JOIN) y registro de colonias y puntos seguros | El equipo definió las reglas: solo autoridades registran; el ciudadano ve y recibe. [Qué revisaron y qué cambiaron] |
| [ ] | Gemini | Clasifica reportes y redacta el análisis dentro de la aplicación | [ ] |
| [ ] | [ ] | [ ] | [ ] |

**Decisiones clave que tomamos nosotros:**

- [Ejemplo: elegimos las 6 funciones y el orden de prioridad.]
- [Ejemplo: ajustamos los pesos de la fórmula de riesgo.]
- [Ejemplo: revisamos y corregimos el código generado antes de integrarlo.]

## Cómo ejecutarlo

1. Copiar la carpeta a `C:\laragon\www\` (o `htdocs` en XAMPP) y encender Apache y MySQL.
2. En phpMyAdmin crear la base `aqua_sentinel`, entrar a ella e importar `sql/instalar_todo.sql`. Ese archivo borra las tablas y las vuelve a crear con la estructura del equipo más 10 colonias y 5 puntos seguros de ejemplo.
3. Copiar `.env.example` como `.env` y poner los datos de MySQL y la llave de Gemini.
4. Abrir `http://localhost/lluvia/index.php` y crear la primera cuenta, que queda como Administrador.
5. En Centro de protección civil, usar "Cargar datos de demostración" para tener reportes de ejemplo.
