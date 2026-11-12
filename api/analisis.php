<?php
// GET api/analisis.php?colonia_id=3[&lluvia_simulada=20]
// Función 2 (predicción): explicación del riesgo de una colonia, redactada por IA
// (o con plantilla si no hay llave). Devuelve {texto, metodo}.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/estado.php';
require_once __DIR__ . '/../lib/analisis.php';

try {
    exigirPermiso('riesgos');
    $id = (int) ($_GET['colonia_id'] ?? 0);
    $estado = estadoColonias(lluviaSimuladaPermitida());
    foreach ($estado['colonias'] as $c) {
        if ($c['id'] === $id) responderJson(analisisColonia($c, $estado['simulacion']));
    }
    responderJson(['error' => 'Colonia no encontrada'], 404);
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudo generar el análisis'], 500);
}
