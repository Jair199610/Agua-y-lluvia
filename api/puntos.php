<?php
// GET  api/puntos.php               -> puntos seguros
// POST api/puntos.php {id, estado}  -> cambia el estado: disponible, ocupado o cerrado
// POST api/puntos.php {accion:"crear", nombre, tipo, direccion, id_colonia, lat, lng, capacidad, telefono?} -> registra uno nuevo
// Función 5 y 6: solo los puntos "disponibles" se ofrecen a la ciudadanía en las rutas.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/consultas.php';

try {
    exigirPermiso('puntos_seguros');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        exigirGestion('puntos_seguros'); // un ciudadano los consulta, pero no los cambia
        $cuerpo = cuerpoJson();

        if (($cuerpo['accion'] ?? '') === 'crear') {
            $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
            $direccion = trim((string) ($cuerpo['direccion'] ?? ''));
            $tipo = (string) ($cuerpo['tipo'] ?? '');
            $telefono = mb_substr(trim((string) ($cuerpo['telefono'] ?? '')), 0, 20);
            $capacidad = max(0, min(100000, (int) ($cuerpo['capacidad'] ?? 0)));
            $lat = coordenada($cuerpo['lat'] ?? null, LAT_MIN, LAT_MAX);
            $lng = coordenada($cuerpo['lng'] ?? null, LNG_MIN, LNG_MAX);

            if ($nombre === '' || mb_strlen($nombre) > 150) responderJson(['error' => 'Escribe el nombre del lugar'], 400);
            if ($direccion === '' || mb_strlen($direccion) > 255) responderJson(['error' => 'Escribe la dirección'], 400);
            if (!in_array($tipo, ['refugio', 'centro_salud', 'punto_reunion', 'otro'], true)) responderJson(['error' => 'Elige el tipo de lugar'], 400);
            if ($lat === null || $lng === null) responderJson(['error' => 'Marca en el mapa un punto dentro de la zona de Veracruz'], 400);
            $sql = db()->prepare("SELECT COUNT(*) FROM colonias WHERE id_colonia = ? AND estado_registro = 'activa'");
            $sql->execute([(int) ($cuerpo['id_colonia'] ?? 0)]);
            if ((int) $sql->fetchColumn() === 0) responderJson(['error' => 'Elige la colonia'], 400);

            $sql = db()->prepare(
                "INSERT INTO puntos_seguros (id_colonia, nombre, tipo, direccion, latitud, longitud, capacidad, telefono, estado, fecha_registro)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'disponible', ?)"
            );
            $sql->execute([(int) $cuerpo['id_colonia'], $nombre, $tipo, $direccion, $lat, $lng, $capacidad,
                           $telefono !== '' ? $telefono : null, ahora()]);
            responderJson(['id' => (int) db()->lastInsertId()], 201);
        }

        $estado = (string) ($cuerpo['estado'] ?? '');
        if (!in_array($estado, ESTADOS_PUNTO, true)) responderJson(['error' => 'Estado no válido'], 400);
        $sql = db()->prepare('UPDATE puntos_seguros SET estado = ? WHERE id_punto_seguro = ?');
        $sql->execute([$estado, (int) ($cuerpo['id'] ?? 0)]);
        responderJson(['ok' => true]);
    }
    responderJson(['puntos' => puntosSeguros()]);
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudieron cargar los puntos seguros'], 500);
}
