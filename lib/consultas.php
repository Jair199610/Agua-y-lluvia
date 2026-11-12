<?php
// Consultas que usan varias rutas del backend.

// Puntos seguros con el nombre de su colonia.
function puntosSeguros(): array {
    $puntos = db()->query(
        "SELECT p.id_punto_seguro AS id, p.nombre, p.tipo, p.direccion, p.latitud AS lat, p.longitud AS lng,
                p.capacidad, p.telefono, p.estado, c.nombre AS colonia
         FROM puntos_seguros p JOIN colonias c ON c.id_colonia = p.id_colonia
         ORDER BY p.id_punto_seguro"
    )->fetchAll();
    foreach ($puntos as &$p) {
        $p['id'] = (int) $p['id'];
        $p['lat'] = (float) $p['lat'];
        $p['lng'] = (float) $p['lng'];
        $p['capacidad'] = (int) $p['capacidad'];
    }
    return $puntos;
}
