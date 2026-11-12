<?php
// GET api/ruta.php?lat=19.19&lng=-96.14[&lluvia_simulada=20]
// Función 6 (rutas seguras): ruta al punto seguro disponible más conveniente.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/estado.php';
require_once __DIR__ . '/../lib/rutas.php';
require_once __DIR__ . '/../lib/consultas.php';

try {
    exigirPermiso('puntos_seguros');
    $lat = coordenada($_GET['lat'] ?? null, LAT_MIN, LAT_MAX);
    $lng = coordenada($_GET['lng'] ?? null, LNG_MIN, LNG_MAX);
    if ($lat === null || $lng === null) responderJson(['error' => 'El punto de salida debe estar en la zona de Veracruz'], 400);

    $estado = estadoColonias(lluviaSimuladaPermitida());

    // Inundaciones y encharcamientos con ubicación exacta de las últimas 3 horas (urgencia media o mayor).
    $sql = db()->prepare(
        "SELECT r.latitud AS lat, r.longitud AS lng, c.nombre AS colonia
         FROM reportes r JOIN colonias c ON c.id_colonia = r.id_colonia
         WHERE r.tipo IN ('inundacion', 'encharcamiento') AND r.nivel_urgencia IN ('medio', 'alto', 'critico')
           AND r.latitud IS NOT NULL AND r.longitud IS NOT NULL
           AND r.estado IN ('pendiente', 'en_revision') AND r.fecha_reporte >= ?"
    );
    $sql->execute([haceHoras(3)]);

    $zonas = zonasAEvitar($estado['colonias'], $sql->fetchAll());
    $ruta = rutaSegura($lat, $lng, puntosSeguros(), $zonas);
    if ($ruta === null) responderJson(['error' => 'No hay puntos seguros disponibles en este momento'], 404);

    responderJson(['simulacion' => $estado['simulacion'], 'zonas' => $zonas, 'ruta' => $ruta]);
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudo calcular la ruta'], 500);
}
