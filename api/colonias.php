<?php
// GET  api/colonias.php[?lluvia_simulada=20]
//        -> colonias con lluvia, puntaje, nivel, desglose y guía (funciones 1 y 2)
// POST api/colonias.php {nombre, municipio, lat, lng, nivel_vulnerabilidad}
//        -> registra una colonia nueva (permiso "colonias" y no ser Ciudadano)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/estado.php';

try {
    exigirPermiso('dashboard');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        exigirGestion('colonias');
        $cuerpo = cuerpoJson();
        $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
        $municipio = trim((string) ($cuerpo['municipio'] ?? ''));
        $lat = coordenada($cuerpo['lat'] ?? null, LAT_MIN, LAT_MAX);
        $lng = coordenada($cuerpo['lng'] ?? null, LNG_MIN, LNG_MAX);
        $vulnerabilidad = (string) ($cuerpo['nivel_vulnerabilidad'] ?? '');

        if ($nombre === '' || mb_strlen($nombre) > 150) responderJson(['error' => 'Escribe el nombre de la colonia'], 400);
        if ($municipio === '' || mb_strlen($municipio) > 120) responderJson(['error' => 'Escribe el municipio'], 400);
        if ($lat === null || $lng === null) responderJson(['error' => 'Marca en el mapa un punto dentro de la zona de Veracruz'], 400);
        if (!isset(VALOR_VULNERABILIDAD[$vulnerabilidad])) responderJson(['error' => 'Elige la vulnerabilidad'], 400);

        $sql = db()->prepare('SELECT COUNT(*) FROM colonias WHERE nombre = ? AND municipio = ?');
        $sql->execute([$nombre, $municipio]);
        if ((int) $sql->fetchColumn() > 0) responderJson(['error' => 'Esa colonia ya está registrada'], 400);

        $sql = db()->prepare(
            "INSERT INTO colonias (nombre, municipio, latitud, longitud, nivel_vulnerabilidad, estado_registro, fecha_registro)
             VALUES (?, ?, ?, ?, ?, 'activa', ?)"
        );
        $sql->execute([$nombre, $municipio, $lat, $lng, $vulnerabilidad, ahora()]);
        responderJson(['id' => (int) db()->lastInsertId()], 201);
    }

    responderJson(estadoColonias(lluviaSimuladaPermitida()));
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudieron cargar las colonias'], 500);
}
