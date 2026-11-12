<?php
// GET  api/reportes.php                 -> últimos 100 reportes
// POST api/reportes.php {texto, colonia_id?, lat?, lng?}  -> crea un reporte y lo clasifica
// POST api/reportes.php {accion:"estado", id, estado}     -> cambia el estado (centro de protección civil)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/estado.php';
require_once __DIR__ . '/../lib/clasificador.php';
require_once __DIR__ . '/../lib/geo.php';

try {
    $yo = exigirPermiso('reportes');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cuerpo = cuerpoJson();

        if (($cuerpo['accion'] ?? '') === 'estado') {
            exigirGestion('reportes'); // un ciudadano registra reportes, pero no cambia su estado
            $estado = (string) ($cuerpo['estado'] ?? '');
            if (!in_array($estado, ESTADOS_REPORTE, true)) responderJson(['error' => 'Estado no válido'], 400);
            // Al marcarlo como atendido se guarda la fecha de atención.
            $sql = db()->prepare('UPDATE reportes SET estado = ?, fecha_atencion = ? WHERE id_reporte = ?');
            $sql->execute([$estado, $estado === 'atendido' ? ahora() : null, (int) ($cuerpo['id'] ?? 0)]);
            responderJson(['ok' => true]);
        }

        if (!puedeReportar()) responderJson(['error' => 'Tu tipo de cuenta solo puede consultar la información'], 403);
        $texto = trim((string) ($cuerpo['texto'] ?? ''));
        if ($texto === '' || mb_strlen($texto) > 500) {
            responderJson(['error' => 'El reporte debe tener entre 1 y 500 caracteres'], 400);
        }

        $colonias = coloniasActivas();
        $c = clasificarReporte($texto, $colonias);

        // Ubicación exacta solo si el vecino marcó un punto en el mapa.
        $lat = coordenada($cuerpo['lat'] ?? null, LAT_MIN, LAT_MAX);
        $lng = coordenada($cuerpo['lng'] ?? null, LNG_MIN, LNG_MAX);
        if ($lat === null || $lng === null) $lat = $lng = null;

        // Colonia: 1) la que eligió en el formulario, 2) la que menciona el texto, 3) la más cercana al punto marcado.
        $nombres = [];
        foreach ($colonias as $col) $nombres[(int) $col['id']] = $col['nombre'];
        $coloniaId = (int) ($cuerpo['colonia_id'] ?? 0);
        if (!isset($nombres[$coloniaId])) $coloniaId = $c['colonia_id'] ?? 0;
        if (!isset($nombres[$coloniaId]) && $lat !== null) {
            $cercana = coloniaMasCercana($lat, $lng, $colonias, 3000);
            $coloniaId = $cercana ? (int) $cercana['id'] : 0;
        }
        if (!isset($nombres[$coloniaId])) {
            responderJson(['error' => 'Elige tu colonia en la lista para poder registrar el reporte'], 400);
        }

        $titulo = NOMBRES_TIPO[$c['tipo']] . ' en ' . $nombres[$coloniaId];
        $sql = db()->prepare(
            "INSERT INTO reportes (id_usuario, id_colonia, tipo, titulo, descripcion, latitud, longitud, nivel_urgencia, estado, fecha_reporte)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)"
        );
        $sql->execute([$yo['id'], $coloniaId, $c['tipo'], $titulo, $texto, $lat, $lng, $c['urgencia'], ahora()]);

        responderJson([
            'id' => (int) db()->lastInsertId(), 'titulo' => $titulo, 'texto' => $texto,
            'tipo' => $c['tipo'], 'urgencia' => $c['urgencia'], 'metodo' => $c['metodo'],
            'colonia_id' => $coloniaId, 'colonia' => $nombres[$coloniaId],
        ], 201);
    }

    $reportes = db()->query(
        "SELECT r.id_reporte AS id, r.titulo, r.descripcion AS texto, r.tipo, r.nivel_urgencia AS urgencia, r.estado,
                r.fecha_reporte AS creado_en, r.latitud AS lat, r.longitud AS lng,
                r.id_colonia AS colonia_id, c.nombre AS colonia
         FROM reportes r JOIN colonias c ON c.id_colonia = r.id_colonia
         ORDER BY r.fecha_reporte DESC, r.id_reporte DESC LIMIT 100"
    )->fetchAll();
    foreach ($reportes as &$r) {
        $r['id'] = (int) $r['id'];
        $r['colonia_id'] = (int) $r['colonia_id'];
        $r['lat'] = $r['lat'] === null ? null : (float) $r['lat'];
        $r['lng'] = $r['lng'] === null ? null : (float) $r['lng'];
        $r['es_demo'] = strpos($r['titulo'], MARCA_DEMO) === 0 ? 1 : 0;
    }
    responderJson(['reportes' => $reportes]);
} catch (Throwable $e) {
    responderJson(['error' => 'Error al procesar el reporte'], 500);
}
