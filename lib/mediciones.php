<?php
// Guarda el pronóstico de lluvia en las tablas puntos_monitoreo y mediciones,
// para tener historial de lo que se pronosticó para cada colonia.
//
// OJO: no son sensores reales. Cada colonia tiene un "punto virtual" que representa el
// pronóstico de la API Open-Meteo para su centro. Por eso fuente = 'api' y la unidad dice
// "mm en 6 h": es lluvia PRONOSTICADA para las siguientes 6 horas, no lluvia medida.

const NOMBRE_PUNTO_VIRTUAL = 'Pronóstico Open-Meteo';

// $lluvia es lo que devuelve lluviaPorColonia(): [id_colonia => ['total' => mm, ...]].
function registrarMediciones(array $colonias, array $lluvia): void {
    $puntos = [];
    $sql = db()->prepare('SELECT id_punto, id_colonia FROM puntos_monitoreo WHERE nombre = ?');
    $sql->execute([NOMBRE_PUNTO_VIRTUAL]);
    foreach ($sql->fetchAll() as $p) $puntos[$p['id_colonia']] = (int) $p['id_punto'];

    $crearPunto = db()->prepare(
        "INSERT INTO puntos_monitoreo (id_colonia, nombre, tipo, latitud, longitud, ubicacion, estado, fecha_registro)
         VALUES (?, ?, 'estacion_meteorologica', ?, ?, ?, 'activo', ?)"
    );
    $medir = db()->prepare(
        "INSERT INTO mediciones (id_punto, tipo, valor, unidad, fuente, fecha_medicion, fecha_registro)
         VALUES (?, 'precipitacion', ?, 'mm en 6 h', 'api', ?, ?)"
    );

    foreach ($colonias as $c) {
        if (!isset($lluvia[$c['id']]['total'])) continue;
        if (!isset($puntos[$c['id']])) {
            $crearPunto->execute([$c['id'], NOMBRE_PUNTO_VIRTUAL, $c['lat'], $c['lng'],
                'Punto virtual: pronóstico de la API Open-Meteo para el centro de la colonia', ahora()]);
            $puntos[$c['id']] = (int) db()->lastInsertId();
        }
        $medir->execute([$puntos[$c['id']], $lluvia[$c['id']]['total'], ahora(), ahora()]);
    }
}

// Últimas lecturas guardadas de una colonia, de la más vieja a la más nueva.
function historialDeLluvia(int $idColonia, int $cuantas = 36): array {
    $sql = db()->prepare(
        "SELECT m.fecha_medicion AS fecha, m.valor
         FROM mediciones m JOIN puntos_monitoreo p ON p.id_punto = m.id_punto
         WHERE p.id_colonia = ? AND p.nombre = ? AND m.tipo = 'precipitacion'
         ORDER BY m.fecha_medicion DESC, m.id_medicion DESC LIMIT " . (int) $cuantas
    );
    $sql->execute([$idColonia, NOMBRE_PUNTO_VIRTUAL]);
    $filas = array_reverse($sql->fetchAll());
    foreach ($filas as &$f) $f['valor'] = (float) $f['valor'];
    return $filas;
}
