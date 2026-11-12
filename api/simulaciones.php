<?php
// POST api/simulaciones.php {lluvia_mm}
// Guarda en la tabla simulaciones el resultado de simular esa lluvia (en 6 horas) en cada colonia.
// No genera alertas ni evaluaciones reales.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/estado.php';
require_once __DIR__ . '/../lib/analisis.php';

try {
    $yo = exigirPermiso('simulaciones');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') responderJson(['error' => 'Usa POST'], 405);
    $mm = cuerpoJson()['lluvia_mm'] ?? null;
    if (!is_numeric($mm) || $mm < 0 || $mm > 100) responderJson(['error' => 'La lluvia debe estar entre 0 y 100 mm'], 400);
    $mm = (float) $mm;

    $estado = estadoColonias($mm);
    $sql = db()->prepare(
        "INSERT INTO simulaciones (id_usuario, id_colonia, nombre, lluvia_estimada, duracion_horas,
                                   nivel_agua_estimado, resultado_riesgo, resultado_analisis, fecha_simulacion)
         VALUES (?, ?, ?, ?, 6, NULL, ?, ?, ?)"
    );
    foreach ($estado['colonias'] as $c) {
        $sql->execute([$yo['id'], $c['id'], "Simulación de $mm mm en 6 horas", $mm, nivelRiesgoBD($c['puntaje']),
                       analisisConPlantilla($c, true), ahora()]);
    }
    responderJson(['ok' => true, 'simulaciones' => count($estado['colonias'])], 201);
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudo guardar la simulación'], 500);
}
