<?php
require_once __DIR__ . '/config.php';

$coloniasReporte = [];
$errorColonias = '';
$totalColonias = 0;

try {
    $pdo = db();

    $consultaTotal = $pdo->query("SELECT COUNT(*) FROM colonias");
    $totalColonias = (int) $consultaTotal->fetchColumn();

    $consulta = $pdo->query("
        SELECT id_colonia, nombre
        FROM colonias
        ORDER BY nombre ASC
    ");

    $coloniasReporte = $consulta->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $errorColonias = $e->getMessage();
    error_log('Error en colonias: ' . $errorColonias);
}
?>

<!--
=========================================================
* Soft UI Dashboard 3 - v1.1.0
=========================================================

* Product Page: https://www.creative-tim.com/product/soft-ui-dashboard
* Copyright 2024 Creative Tim (https://www.creative-tim.com)
* Licensed under MIT (https://www.creative-tim.com/license)
* Coded by Creative Tim

=========================================================

* The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
-->
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="icon" type="image/png" href="assets/img/favicon.png">
  <title>Aqua Sentinel</title>
  <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,800" rel="stylesheet" />
  <link href="assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="assets/css/nucleo-svg.css" rel="stylesheet" />
  <link href="assets/vendor/leaflet/leaflet.css" rel="stylesheet" />
  <link id="pagestyle" href="assets/css/soft-ui-dashboard.css?v=1.1.0" rel="stylesheet" />
  <link href="assets/css/app.css" rel="stylesheet" />
</head>

<body class="g-sidenav-show bg-gray-100">

  <!-- ===================== MENÚ LATERAL ===================== -->
  <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-3" id="sidenav-main">
    <div class="sidenav-header">
      <i class="ni ni-fat-remove p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
      <a class="navbar-brand m-0" href="index.php">
        <i class="ni ni-umbrella-13 text-info text-lg"></i>
        <span class="ms-1 font-weight-bold">Aqua Sentinel</span>
      </a>
    </div>
    <hr class="horizontal dark mt-0">
    <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link active" href="javascript:;" data-ir="radar" data-permiso="dashboard">
            <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center"><i class="ni ni-world-2 text-dark text-sm opacity-10"></i></div>
            <span class="nav-link-text ms-1">Radar de riesgo</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="javascript:;" data-ir="prediccion" data-permiso="riesgos">
            <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center"><i class="ni ni-chart-bar-32 text-dark text-sm opacity-10"></i></div>
            <span class="nav-link-text ms-1">Predicción</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="javascript:;" data-ir="alertas" data-permiso="alertas">
            <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center"><i class="ni ni-bell-55 text-dark text-sm opacity-10"></i></div>
            <span class="nav-link-text ms-1">Alerta ciudadana</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="javascript:;" data-ir="fugas" data-permiso="reportes">
            <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center"><i class="ni ni-zoom-split-in text-dark text-sm opacity-10"></i></div>
            <span class="nav-link-text ms-1">Detector de fugas</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="javascript:;" data-ir="rutas" data-permiso="puntos_seguros">
            <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center"><i class="ni ni-compass-04 text-dark text-sm opacity-10"></i></div>
            <span class="nav-link-text ms-1">Rutas seguras</span>
          </a>
        </li>
        <li class="nav-item">
  <a class="nav-link" href="javascript:;" data-ir="reportes" data-gestion="reportes">
    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center"><i class="ni ni-single-copy-04 text-dark text-sm opacity-10"></i></div>
    <span class="nav-link-text ms-1">Reportes</span>
  </a>
</li>
        <li class="nav-item mt-3" id="tituloAutoridades">
          <h6 class="ps-4 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">Autoridades</h6>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="javascript:;" data-ir="centro" data-gestion="reportes">
            <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center"><i class="ni ni-building text-dark text-sm opacity-10"></i></div>
            <span class="nav-link-text ms-1">Centro de protección civil</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="javascript:;" data-ir="usuarios" data-permiso="usuarios">
            <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center"><i class="ni ni-single-02 text-dark text-sm opacity-10"></i></div>
            <span class="nav-link-text ms-1">Usuarios</span>
          </a>
        </li>
      </ul>
    </div>
  </aside>

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">

    <!-- ===================== BARRA SUPERIOR ===================== -->
    <nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl" id="navbarBlur" navbar-scroll="true">
      <div class="container-fluid py-1 px-3">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
            <li class="breadcrumb-item text-sm text-dark opacity-5">Agua y lluvia</li>
            <li class="breadcrumb-item text-sm text-dark active" aria-current="page" id="migaSeccion">Radar de riesgo</li>
          </ol>
          <h6 class="font-weight-bolder mb-0" id="tituloSeccion">Radar de riesgo</h6>
        </nav>
        <ul class="navbar-nav ms-auto flex-row align-items-center">
          <li class="nav-item d-none d-md-flex align-items-center me-3">
            <span class="text-xs text-secondary" id="horaActualizacion"></span>
          </li>
          <li class="nav-item d-flex align-items-center me-3">
            <span class="text-sm font-weight-bold" id="nombreUsuario"></span>
            <span class="badge badge-sm bg-gradient-dark ms-2 d-none d-sm-inline" id="rolUsuario"></span>
          </li>
          <li class="nav-item d-flex align-items-center me-3">
            <button class="btn btn-outline-dark btn-sm mb-0" type="button" id="botonSalir">Salir</button>
          </li>
          <li class="nav-item dropdown pe-3 d-flex align-items-center">
            <a href="javascript:;" class="nav-link text-body p-0 position-relative" id="botonCampana" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Alertas vigentes">
              <i class="ni ni-bell-55 cursor-pointer"></i>
              <span class="contador-alertas d-none" id="contadorAlertas">0</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end px-2 py-3" aria-labelledby="botonCampana" id="listaCampana" style="min-width: 300px;"></ul>
          </li>
          <li class="nav-item d-xl-none d-flex align-items-center">
            <a href="javascript:;" class="nav-link text-body p-0" id="iconNavbarSidenav" aria-label="Abrir menú">
              <div class="sidenav-toggler-inner">
                <i class="sidenav-toggler-line"></i>
                <i class="sidenav-toggler-line"></i>
                <i class="sidenav-toggler-line"></i>
              </div>
            </a>
          </li>
        </ul>
      </div>
    </nav>

    <div class="container-fluid py-4">

      <!-- Avisos generales -->
      <div class="alert bg-gradient-dark d-none d-flex align-items-center justify-content-between flex-wrap" id="bannerSimulacion" role="alert">
        <span class="text-sm"><strong>MODO SIMULACIÓN.</strong> <span id="textoSimulacion"></span> No es el pronóstico real.</span>
        <button class="btn btn-sm btn-light mb-0 mt-2 mt-md-0" id="botonQuitarSimulacion" type="button">Volver al pronóstico real</button>
      </div>
      <div class="alert bg-gradient-warning d-none text-sm" id="bannerLluvia" role="alert">
        No se pudo consultar el pronóstico de lluvia. El riesgo mostrado solo considera los reportes de vecinos.
      </div>
      <div class="alert alert-danger text-white d-none text-sm" id="bannerError" role="alert">
        No se pudo conectar con el servidor. Revisa que Apache y MySQL estén encendidos y que abriste la página desde http://localhost.
      </div>

      <!-- Indicadores -->
      <div class="row">
        <div class="col-xl-3 col-sm-6 mb-4">
          <div class="card"><div class="card-body p-3"><div class="row">
            <div class="col-8"><div class="numbers">
              <p class="text-sm mb-0 font-weight-bold">Colonias en riesgo alto</p>
              <h5 class="font-weight-bolder mb-0" id="kpiRojas">–</h5>
            </div></div>
            <div class="col-4 text-end"><div class="icon icon-shape bg-gradient-danger shadow text-center border-radius-md"><i class="ni ni-world-2 text-lg opacity-10" aria-hidden="true"></i></div></div>
          </div></div></div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-4">
          <div class="card"><div class="card-body p-3"><div class="row">
            <div class="col-8"><div class="numbers">
              <p class="text-sm mb-0 font-weight-bold">Lluvia máxima en 6 h</p>
              <h5 class="font-weight-bolder mb-0" id="kpiLluvia">–</h5>
            </div></div>
            <div class="col-4 text-end"><div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md"><i class="ni ni-umbrella-13 text-lg opacity-10" aria-hidden="true"></i></div></div>
          </div></div></div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-4">
          <div class="card"><div class="card-body p-3"><div class="row">
            <div class="col-8"><div class="numbers">
              <p class="text-sm mb-0 font-weight-bold">Reportes sin resolver</p>
              <h5 class="font-weight-bolder mb-0" id="kpiReportes">–</h5>
            </div></div>
            <div class="col-4 text-end"><div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md"><i class="ni ni-chat-round text-lg opacity-10" aria-hidden="true"></i></div></div>
          </div></div></div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-4">
          <div class="card"><div class="card-body p-3"><div class="row">
            <div class="col-8"><div class="numbers">
              <p class="text-sm mb-0 font-weight-bold">Fugas probables</p>
              <h5 class="font-weight-bolder mb-0" id="kpiFugas">–</h5>
            </div></div>
            <div class="col-4 text-end"><div class="icon icon-shape bg-gradient-dark shadow text-center border-radius-md"><i class="ni ni-zoom-split-in text-lg opacity-10" aria-hidden="true"></i></div></div>
          </div></div></div>
        </div>
      </div>

      <!-- ===================== 1. RADAR ===================== -->
      <div class="seccion" data-seccion="radar" data-titulo="Radar de riesgo">
        <div class="row">
          <div class="col-lg-8 mb-4">
            <div class="card h-100">
              <div class="card-header pb-0">
                <h6 class="mb-0">Mapa de colonias por nivel de riesgo</h6>
                <p class="text-sm mb-0">
                  <span class="punto-nivel nivel-verde"></span>Bajo
                  <span class="punto-nivel nivel-amarillo ms-3"></span>Medio
                  <span class="punto-nivel nivel-rojo ms-3"></span>Alto
                  <span class="ms-3 text-secondary">Toca una colonia para ver el detalle.</span>
                </p>
              </div>
              <div class="card-body p-3"><div id="mapaRadar" class="mapa"></div></div>
            </div>
          </div>
          <div class="col-lg-4 mb-4">
            <div class="card mb-4">
              <div class="card-header pb-0"><h6 class="mb-0">Colonia seleccionada</h6></div>
              <div class="card-body pt-2" id="detalleColonia"><p class="text-sm text-secondary mb-0">Toca una colonia en el mapa.</p></div>
            </div>
            <div class="card">
              <div class="card-header pb-0"><h6 class="mb-0">Reportar un problema de agua</h6></div>
              <div class="card-body pt-2">
                <form id="formularioReporte">
                  <label for="textoReporte" class="form-label text-sm">¿Qué está pasando?</label>
                  <textarea id="textoReporte" class="form-control" rows="3" maxlength="500" required placeholder="Ejemplo: hay una fuga frente a mi casa en la colonia Centro"></textarea>
                  <label for="coloniaReporte" class="form-label text-sm mt-3">Colonia</label>
                  <select id="coloniaReporte" class="form-select"></select>
                  <button class="btn btn-outline-dark btn-sm w-100 mt-3 mb-1" type="button" id="botonMarcar">Marcar el lugar en el mapa (opcional)</button>
                  <p class="text-xs text-secondary mb-2" id="textoUbicacion">Sin lugar marcado en el mapa.</p>
                  <button class="btn bg-gradient-primary w-100 mb-0" type="submit" id="botonEnviar">Enviar reporte</button>
                </form>
                <p class="text-sm mt-3 mb-0" id="resultadoReporte" role="status"></p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ===================== 2. PREDICCIÓN ===================== -->
      <div class="seccion oculta" data-seccion="prediccion" data-titulo="Predicción de riesgo">
        <div class="row">
          <div class="col-lg-5 mb-4">
            <div class="card h-100">
              <div class="card-header pb-0">
                <h6 class="mb-2">Riesgo estimado por colonia</h6>
                <select class="form-select" id="selectorColonia" aria-label="Elegir colonia"></select>
              </div>
              <div class="card-body">
                <div id="resumenPrediccion"></div>
                <h6 class="text-sm mt-4 mb-1">Análisis</h6>
                <p class="text-sm mb-1" id="textoAnalisis">Elige una colonia.</p>
                <span class="badge bg-gradient-secondary d-none" id="metodoAnalisis"></span>
              </div>
            </div>
          </div>
          <div class="col-lg-7 mb-4">
            <div class="card mb-4">
              <div class="card-header pb-0"><h6 class="mb-0">Lluvia esperada por hora (mm)</h6></div>
              <div class="card-body p-3"><div class="chart"><canvas id="graficaLluvia" height="150"></canvas></div></div>
            </div>
            <div class="card mb-4">
              <div class="card-header pb-0">
                <h6 class="mb-0">Historial del pronóstico (mm en 6 h)</h6>
                <p class="text-sm mb-0" id="textoHistorial">Cada pronóstico que se consulta se guarda en la tabla de mediciones.</p>
              </div>
              <div class="card-body p-3"><div class="chart"><canvas id="graficaHistorial" height="110"></canvas></div></div>
            </div>
            <div class="card">
              <div class="card-header pb-0">
                <h6 class="mb-0">Simulador de lluvia</h6>
                <p class="text-sm mb-0">Para mostrar el sistema en un día sin lluvia. La pantalla avisa que es una simulación y no se generan alertas reales.</p>
              </div>
              <div class="card-body pt-2">
                <label for="deslizadorLluvia" class="form-label text-sm">Lluvia en 6 horas: <strong id="valorDeslizador">20</strong> mm</label>
                <input type="range" class="form-range" id="deslizadorLluvia" min="0" max="60" step="1" value="20">
                <button class="btn bg-gradient-info btn-sm mb-0" type="button" id="botonSimular">Simular esta lluvia</button>
              </div>
            </div>
          </div>
        </div>
        <div class="card mb-4">
          <div class="card-header pb-0"><h6 class="mb-0">Todas las colonias, de mayor a menor riesgo</h6></div>
          <div class="card-body px-0 pb-2">
            <div class="table-responsive"><table class="table align-items-center mb-0">
              <thead><tr>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Colonia</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nivel</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Puntaje</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Lluvia 6 h</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Antecedentes</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Reportes 3 h</th>
              </tr></thead>
              <tbody id="tablaPrediccion"></tbody>
            </table></div>
          </div>
        </div>
      </div>

      <!-- ===================== 3. ALERTA CIUDADANA ===================== -->
      <div class="seccion oculta" data-seccion="alertas" data-titulo="Alerta ciudadana">
        <div class="row">
          <div class="col-lg-4 mb-4">
            <div class="card">
              <div class="card-header pb-0"><h6 class="mb-0">Mis avisos</h6></div>
              <div class="card-body pt-2">
                <label for="miColonia" class="form-label text-sm">Mi colonia</label>
                <select class="form-select mb-3" id="miColonia"></select>

                <h6 class="text-sm mb-1">Por correo</h6>
                <p class="text-xs text-secondary mb-2" id="estadoCorreo"></p>
                <button class="btn btn-outline-dark btn-sm w-100 mb-1" type="button" id="botonPruebaCorreo">Enviarme un correo de prueba</button>
                <p class="text-xs mb-3" id="resultadoCorreo" role="status"></p>

                <h6 class="text-sm mb-1">En este navegador</h6>
                <button class="btn bg-gradient-primary w-100 mb-2" type="button" id="botonNotificaciones">Activar notificaciones</button>
                <p class="text-xs text-secondary mb-0" id="estadoNotificaciones"></p>
                <p class="text-xs text-secondary mt-2 mb-0" id="textoReglaAvisos"></p>
              </div>
            </div>

            <div class="card mt-4 d-none" id="tarjetaAviso">
              <div class="card-header pb-0">
                <h6 class="mb-0">Enviar un aviso</h6>
                <p class="text-sm mb-0">Un solo aviso para una o varias colonias. Les llega a los usuarios que viven en ellas.</p>
              </div>
              <div class="card-body pt-2">
                <form id="formularioAviso">
                  <label for="avisoTitulo" class="form-label text-sm">Título</label>
                  <input id="avisoTitulo" class="form-control mb-2" maxlength="180" required>
                  <label for="avisoMensaje" class="form-label text-sm">Mensaje</label>
                  <textarea id="avisoMensaje" class="form-control mb-2" rows="3" maxlength="1000" required></textarea>
                  <div class="row">
                    <div class="col-6">
                      <label for="avisoTipo" class="form-label text-sm">Tipo</label>
                      <select id="avisoTipo" class="form-select mb-2">
                        <option value="inundacion">Inundación</option>
                        <option value="lluvia">Lluvia</option>
                        <option value="fuga">Fuga</option>
                        <option value="falta_agua">Falta de agua</option>
                        <option value="prevencion">Prevención</option>
                        <option value="otro">Otro</option>
                      </select>
                    </div>
                    <div class="col-6">
                      <label for="avisoNivel" class="form-label text-sm">Nivel</label>
                      <select id="avisoNivel" class="form-select mb-2">
                        <option value="informativa">Informativa</option>
                        <option value="preventiva" selected>Preventiva</option>
                        <option value="urgente">Urgente</option>
                        <option value="emergencia">Emergencia</option>
                      </select>
                    </div>
                  </div>
                  <label for="avisoHoras" class="form-label text-sm">Vigencia</label>
                  <select id="avisoHoras" class="form-select mb-2">
                    <option value="3">3 horas</option>
                    <option value="6" selected>6 horas</option>
                    <option value="12">12 horas</option>
                    <option value="24">24 horas</option>
                  </select>
                  <fieldset class="mb-3">
                    <legend class="form-label text-sm mb-1">Colonias que reciben el aviso</legend>
                    <button class="btn btn-link text-dark text-xs p-0 mb-1" type="button" id="avisoTodas">Marcar todas</button>
                    <div id="avisoColonias"></div>
                  </fieldset>
                  <button class="btn bg-gradient-primary w-100 mb-0" type="submit">Enviar aviso</button>
                </form>
                <p class="text-sm mt-3 mb-0" id="resultadoAviso" role="status"></p>
              </div>
            </div>
          </div>
          <div class="col-lg-8 mb-4">
            <div class="card">
              <div class="card-header pb-0">
                <h6 class="mb-0" id="tituloListaAlertas">Alertas vigentes</h6>
                <p class="text-sm mb-0" id="textoListaAlertas"></p>
              </div>
              <div class="card-body pt-2" id="listaAlertas"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- ===================== 4. DETECTOR DE FUGAS ===================== -->
      <div class="seccion oculta" data-seccion="fugas" data-titulo="Detector colaborativo de fugas">
        <div class="row">
          <div class="col-lg-7 mb-4">
            <div class="card h-100">
              <div class="card-header pb-0">
                <h6 class="mb-0">Coincidencias de reportes</h6>
                <p class="text-sm mb-0">Cuando dos o más reportes de fuga o falta de agua quedan a menos de <span id="radioFuga">300</span> m entre sí en 24 horas, se marcan como fuga probable.</p>
              </div>
              <div class="card-body p-3"><div id="mapaFugas" class="mapa"></div></div>
            </div>
          </div>
          <div class="col-lg-5 mb-4">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0">Fugas detectadas</h6></div>
              <div class="card-body pt-2" id="listaFugas"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- ===================== 6. RUTAS SEGURAS ===================== -->
      <div class="seccion oculta" data-seccion="rutas" data-titulo="Rutas seguras">
        <div class="row">
          <div class="col-lg-8 mb-4">
            <div class="card h-100">
              <div class="card-header pb-0">
                <h6 class="mb-0">Ruta al punto seguro más conveniente</h6>
                <p class="text-sm mb-0">Toca el mapa donde estás, o usa tu ubicación. Las zonas en riesgo aparecen sombreadas.</p>
              </div>
              <div class="card-body p-3"><div id="mapaRutas" class="mapa"></div></div>
            </div>
          </div>
          <div class="col-lg-4 mb-4">
            <div class="card mb-4">
              <div class="card-body">
                <button class="btn bg-gradient-primary w-100 mb-2" type="button" id="botonUbicacion">Usar mi ubicación</button>
                <p class="text-xs text-secondary mb-0" id="estadoUbicacion"></p>
              </div>
            </div>
            <div class="card">
              <div class="card-header pb-0"><h6 class="mb-0">Tu ruta</h6></div>
              <div class="card-body pt-2" id="detalleRuta"><p class="text-sm text-secondary mb-0">Aún no has marcado tu punto de salida.</p></div>
            </div>
          </div>
        </div>
      </div>


      <!-- ===================== HISTORIAL DE REPORTES ===================== -->
      <div class="seccion oculta" data-seccion="reportes" data-titulo="Reportes">
        <div class="card mb-4">
          <div class="card-header pb-0">
            <h6 class="mb-1">Historial de incidentes por colonia</h6>
            <p class="text-sm text-secondary mb-0">Filtra por colonia y tipo de incidente. "Exportar a PDF" abre la impresión del navegador: elige "Guardar como PDF".</p>
          </div>
          <div class="card-body">
            <div class="row align-items-end">
              <div class="col-lg-4 col-md-6 mb-3">
                <label for="filtroColoniaReporte" class="form-label text-sm font-weight-bold">Colonia</label>
                <select id="filtroColoniaReporte" class="form-select">
                  <option value="">Todas las colonias</option>
<?php foreach ($coloniasReporte as $colonia): ?>
                  <option value="<?= (int) $colonia['id_colonia'] ?>"><?= htmlspecialchars($colonia['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
                </select>
<?php if ($errorColonias !== ''): ?>
                <div class="text-danger text-xs mt-2">No se pudieron cargar las colonias. Revisa api/diagnostico.php.</div>
<?php endif; ?>
              </div>
              <div class="col-lg-4 col-md-6 mb-3">
                <label for="filtroTipoReporte" class="form-label text-sm font-weight-bold">Tipo de incidente</label>
                <select id="filtroTipoReporte" class="form-select">
                  <option value="">Todos los tipos</option>
                </select>
              </div>
              <div class="col-lg-4 col-md-12 mb-3">
                <button class="btn bg-gradient-danger w-100 mb-0" type="button" id="botonDescargarPDF">Exportar a PDF</button>
              </div>
            </div>
            <hr class="horizontal dark my-3">
            <p class="text-sm text-secondary mb-3" id="resumenReportes" role="status" aria-live="polite"></p>
            <div class="table-responsive mb-4">
              <table class="table align-items-center mb-0">
                <caption class="text-sm font-weight-bold text-dark" style="caption-side: top;">Resumen por tipo</caption>
                <thead><tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tipo de incidente</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Reportes</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Primer reporte</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Último reporte</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Urgencia más alta</th>
                </tr></thead>
                <tbody id="tablaHistorial"></tbody>
              </table>
            </div>
            <div class="table-responsive">
              <table class="table align-items-center mb-0">
                <caption class="text-sm font-weight-bold text-dark" style="caption-side: top;">Reportes</caption>
                <thead><tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Fecha</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tipo</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Colonia</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Descripción</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Urgencia</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Estado</th>
                </tr></thead>
                <tbody id="tablaReportesDescargar"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- ===================== 5. CENTRO DE PROTECCIÓN CIVIL ===================== -->
      <div class="seccion oculta" data-seccion="centro" data-titulo="Centro de protección civil">
        <div class="row">
          <div class="col-xl-5 col-lg-12 mb-4">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0">Zonas por prioridad</h6></div>
              <div class="card-body px-0 pb-2">
                <div class="table-responsive"><table class="table align-items-center mb-0">
                  <thead><tr>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">#</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Colonia</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nivel</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Puntaje</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Reportes</th>
                  </tr></thead>
                  <tbody id="tablaPrioridad"></tbody>
                </table></div>
              </div>
            </div>
          </div>
          <div class="col-xl-7 col-lg-12 mb-4" id="tarjetaPuntos">
            <div class="card h-100">
              <div class="card-header pb-0">
                <h6 class="mb-0">Puntos seguros</h6>
                <p class="text-sm mb-0">Solo los puntos con estado "Disponible" se ofrecen a la ciudadanía en las rutas seguras.</p>
              </div>
              <div class="card-body px-0 pb-2">
                <div class="table-responsive"><table class="table align-items-center mb-0">
                  <thead><tr>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Lugar</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tipo</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Capacidad</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Estado</th>
                  </tr></thead>
                  <tbody id="tablaPuntos"></tbody>
                </table></div>
              </div>
            </div>
          </div>
        </div>
        <!-- Registro de colonias y puntos seguros (solo autoridades con ese permiso) -->
        <div class="row d-none" id="filaRegistros">
          <div class="col-lg-4 mb-4">
            <div class="card h-100">
              <div class="card-header pb-0">
                <h6 class="mb-0">Ubicación del registro</h6>
                <p class="text-sm mb-0">Toca el mapa en el lugar exacto. Sirve para los dos formularios.</p>
              </div>
              <div class="card-body p-3">
                <div id="mapaRegistro" class="mapa" style="height: 300px;"></div>
                <p class="text-xs text-secondary mt-2 mb-0" id="textoRegistro">Aún no has marcado un punto.</p>
              </div>
            </div>
          </div>
          <div class="col-lg-4 mb-4 d-none" id="tarjetaNuevaColonia">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0">Registrar colonia</h6></div>
              <div class="card-body pt-2">
                <form id="formularioColonia">
                  <label for="coloniaNombre" class="form-label text-sm">Nombre</label>
                  <input id="coloniaNombre" class="form-control mb-2" maxlength="150" required>
                  <label for="coloniaMunicipio" class="form-label text-sm">Municipio</label>
                  <input id="coloniaMunicipio" class="form-control mb-2" maxlength="120" value="Veracruz" required>
                  <label for="coloniaVulnerabilidad" class="form-label text-sm">Vulnerabilidad a inundaciones</label>
                  <select id="coloniaVulnerabilidad" class="form-select mb-3">
                    <option value="bajo">Baja</option>
                    <option value="medio" selected>Media</option>
                    <option value="alto">Alta</option>
                    <option value="muy_alto">Muy alta</option>
                  </select>
                  <button class="btn bg-gradient-primary w-100 mb-0" type="submit">Registrar colonia</button>
                </form>
                <p class="text-sm mt-3 mb-0" id="resultadoColonia" role="status"></p>
              </div>
            </div>
          </div>
          <div class="col-lg-4 mb-4 d-none" id="tarjetaNuevoPunto">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0">Registrar punto seguro</h6></div>
              <div class="card-body pt-2">
                <form id="formularioPunto">
                  <label for="puntoNombre" class="form-label text-sm">Nombre del lugar</label>
                  <input id="puntoNombre" class="form-control mb-2" maxlength="150" required>
                  <label for="puntoDireccion" class="form-label text-sm">Dirección</label>
                  <input id="puntoDireccion" class="form-control mb-2" maxlength="255" required>
                  <div class="row">
                    <div class="col-6">
                      <label for="puntoTipo" class="form-label text-sm">Tipo</label>
                      <select id="puntoTipo" class="form-select mb-2">
                        <option value="refugio">Refugio</option>
                        <option value="centro_salud">Centro de salud</option>
                        <option value="punto_reunion">Punto de reunión</option>
                        <option value="otro">Otro</option>
                      </select>
                    </div>
                    <div class="col-6">
                      <label for="puntoCapacidad" class="form-label text-sm">Capacidad</label>
                      <input id="puntoCapacidad" type="number" min="0" max="100000" class="form-control mb-2" value="50">
                    </div>
                  </div>
                  <label for="puntoColonia" class="form-label text-sm">Colonia</label>
                  <select id="puntoColonia" class="form-select mb-2" required></select>
                  <label for="puntoTelefono" class="form-label text-sm">Teléfono (opcional)</label>
                  <input id="puntoTelefono" class="form-control mb-3" maxlength="20">
                  <button class="btn bg-gradient-primary w-100 mb-0" type="submit">Registrar punto seguro</button>
                </form>
                <p class="text-sm mt-3 mb-0" id="resultadoPunto" role="status"></p>
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header pb-0 d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="mb-0">Incidentes reportados</h6>
            <div id="botonesDemo">
              <button class="btn btn-outline-dark btn-sm mb-0 me-2" type="button" id="botonCargarDemo">Cargar datos de demostración</button>
              <button class="btn btn-outline-dark btn-sm mb-0" type="button" id="botonBorrarDemo">Borrar datos de demostración</button>
            </div>
          </div>
          <div class="card-body px-0 pb-2">
            <div class="table-responsive"><table class="table align-items-center mb-0">
              <thead><tr>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Fecha</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Reporte</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tipo</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Urgencia</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Colonia</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Estado</th>
              </tr></thead>
              <tbody id="tablaIncidentes"></tbody>
            </table></div>
          </div>
        </div>
      </div>

      <!-- ===================== USUARIOS (solo Administrador) ===================== -->
      <div class="seccion oculta" data-seccion="usuarios" data-titulo="Usuarios">
        <div class="row">
          <div class="col-lg-4 mb-4">
            <div class="card">
              <div class="card-header pb-0">
                <h6 class="mb-0">Crear usuario</h6>
                <p class="text-sm mb-0">Para personal de protección civil u operadores de agua. Los ciudadanos crean su propia cuenta.</p>
              </div>
              <div class="card-body pt-2">
                <form id="formularioUsuario">
                  <label for="usuarioNombre" class="form-label text-sm">Nombre</label>
                  <input id="usuarioNombre" class="form-control mb-2" maxlength="100" required>
                  <label for="usuarioApellido" class="form-label text-sm">Apellido</label>
                  <input id="usuarioApellido" class="form-control mb-2" maxlength="100">
                  <label for="usuarioCorreo" class="form-label text-sm">Correo</label>
                  <input id="usuarioCorreo" type="email" class="form-control mb-2" maxlength="150" required autocomplete="off">
                  <label for="usuarioContrasena" class="form-label text-sm">Contraseña (mínimo 8 caracteres)</label>
                  <input id="usuarioContrasena" type="password" class="form-control mb-2" minlength="8" maxlength="72" required autocomplete="new-password">
                  <label for="usuarioRol" class="form-label text-sm">Rol</label>
                  <select id="usuarioRol" class="form-select mb-3" required></select>
                  <button class="btn bg-gradient-primary w-100 mb-0" type="submit">Crear usuario</button>
                </form>
                <p class="text-sm mt-3 mb-0" id="resultadoUsuario" role="status"></p>
              </div>
            </div>
          </div>
          <div class="col-lg-8 mb-4">
            <div class="card">
              <div class="card-header pb-0">
                <h6 class="mb-2">Ciudadanos registrados por colonia</h6>
                <div class="row">
                  <div class="col-md-5 mb-2">
                    <label for="buscarUsuario" class="form-label text-sm">Buscar</label>
                    <input id="buscarUsuario" type="search" class="form-control" placeholder="Nombre, correo o teléfono">
                  </div>
                  <div class="col-md-4 mb-2">
                    <label for="filtroColonia" class="form-label text-sm">Colonia</label>
                    <select id="filtroColonia" class="form-select"></select>
                  </div>
                  <div class="col-md-3 mb-2">
                    <label for="filtroRol" class="form-label text-sm">Rol</label>
                    <select id="filtroRol" class="form-select"></select>
                  </div>
                </div>
                <p class="text-sm mb-0" id="resumenUsuarios" role="status"></p>
              </div>
              <div class="card-body px-0 pb-2">
                <div class="table-responsive"><table class="table align-items-center mb-0">
                  <thead><tr>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nombre</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Teléfono</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Rol</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Último acceso</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Estado</th>
                  </tr></thead>
                  <tbody id="tablaUsuarios"></tbody>
                </table></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <footer class="footer pt-3">
        <p class="text-xs text-secondary mb-0">
          Hackathon Veracruz Tech Week 2026 · Reto 3: Agua y lluvia. Las colonias, su vulnerabilidad y los puntos seguros son datos de ejemplo.
          Plantilla Soft UI Dashboard de Creative Tim (MIT) · Mapas © colaboradores de OpenStreetMap · Lluvia: Open-Meteo · Rutas: OSRM.
        </p>
      </footer>
    </div>
  </main>

  <!-- ===================== PANTALLA DE INICIO DE SESIÓN ===================== -->
  <div id="pantallaSesion" class="d-none">
    <div class="card">
      <div class="card-body p-4">
        <h4 class="font-weight-bolder mb-0"><i class="ni ni-umbrella-13 text-info"></i> Aqua Sentinel</h4>
        <p class="text-sm mb-3">Riesgo de inundación, fugas y rutas seguras en Veracruz.</p>
        <div class="alert bg-gradient-info text-white text-sm d-none" id="avisoPrimerUsuario">
          Todavía no hay usuarios. La primera cuenta que se cree será la del <strong>Administrador</strong>.
        </div>

        <form id="formularioEntrar">
          <label for="entrarCorreo" class="form-label text-sm">Correo</label>
          <input id="entrarCorreo" type="email" class="form-control mb-2" required autocomplete="username">
          <label for="entrarContrasena" class="form-label text-sm">Contraseña</label>
          <input id="entrarContrasena" type="password" class="form-control mb-3" required autocomplete="current-password">
          <button class="btn bg-gradient-primary w-100 mb-2" type="submit">Entrar</button>
          <button class="btn btn-link text-dark w-100 mb-0" type="button" id="irARegistro">No tengo cuenta: crear una</button>
        </form>

        <form id="formularioRegistro" class="d-none">
          <label for="registroNombre" class="form-label text-sm">Nombre</label>
          <input id="registroNombre" class="form-control mb-2" maxlength="100" required autocomplete="given-name">
          <label for="registroApellido" class="form-label text-sm">Apellido</label>
          <input id="registroApellido" class="form-control mb-2" maxlength="100" autocomplete="family-name">
          <label for="registroCorreo" class="form-label text-sm">Correo</label>
          <input id="registroCorreo" type="email" class="form-control mb-2" maxlength="150" required autocomplete="username">
          <label for="registroTelefono" class="form-label text-sm">Teléfono (opcional)</label>
          <input id="registroTelefono" type="tel" class="form-control mb-2" maxlength="20" autocomplete="tel">
          <label for="registroContrasena" class="form-label text-sm">Contraseña (mínimo 8 caracteres)</label>
          <input id="registroContrasena" type="password" class="form-control mb-2" minlength="8" maxlength="72" required autocomplete="new-password">
          <div id="campoColonia" class="d-none">
            <label for="registroColonia" class="form-label text-sm">Colonia donde vives</label>
            <select id="registroColonia" class="form-select mb-1"></select>
            <p class="text-xs text-secondary mb-2">Recibirás las alertas de esta colonia, también en tu correo.</p>
          </div>
          <div id="campoRol" class="d-none">
            <label for="registroRol" class="form-label text-sm">Tipo de cuenta</label>
            <select id="registroRol" class="form-select mb-2"></select>
            <div id="campoClave" class="d-none">
              <label for="registroClave" class="form-label text-sm">Clave de autoridad</label>
              <input id="registroClave" type="password" class="form-control mb-1" autocomplete="off">
              <p class="text-xs text-secondary mb-2">Te la entrega tu dependencia. Sin ella solo puedes registrarte como ciudadano.</p>
            </div>

          </div>
          <div class="mb-3"></div>
          <button class="btn bg-gradient-primary w-100 mb-2" type="submit">Crear cuenta</button>
          <button class="btn btn-link text-dark w-100 mb-0" type="button" id="irAEntrar">Ya tengo cuenta: entrar</button>
        </form>

        <p class="text-sm text-danger mt-2 mb-0" id="errorSesion" role="alert"></p>
      </div>
    </div>
  </div>

  <div id="avisos" aria-live="polite"></div>

  <!-- Scripts de la plantilla -->
  <script src="assets/js/core/popper.min.js"></script>
  <script src="assets/js/core/bootstrap.min.js"></script>
  <script src="assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script src="assets/js/plugins/chartjs.min.js"></script>
  <script src="assets/vendor/leaflet/leaflet.js"></script>
  <script src="assets/js/soft-ui-dashboard.min.js?v=1.1.0"></script>
  <!-- Scripts del proyecto: uno por función -->
  <script src="assets/js/app/app.js"></script>
  <script src="assets/js/app/radar.js"></script>
  <script src="assets/js/app/prediccion.js"></script>
  <script src="assets/js/app/alertas.js"></script>
  <script src="assets/js/app/fugas.js"></script>
  <script src="assets/js/app/rutas.js"></script>
  <script src="assets/js/app/centro.js"></script>
  <script src="assets/js/app/reportes.js"></script>
  <script src="assets/js/app/usuarios.js"></script>
  <script>App.iniciar();</script>
</body>

</html>