<?php
require_once __DIR__ . '/lluvia.php';
require_once __DIR__ . '/riesgo.php';
require_once __DIR__ . '/mediciones.php';

// Colonias activas que tienen coordenadas (las demás no se pueden poner en el mapa).
function coloniasActivas(): array {
    return db()->query(
        "SELECT id_colonia AS id, nombre, municipio, latitud AS lat, longitud AS lng, nivel_vulnerabilidad
         FROM colonias
         WHERE estado_registro = 'activa' AND latitud IS NOT NULL AND longitud IS NOT NULL
         ORDER BY id_colonia"
    )->fetchAll();
}

// Estado actual de todas las colonias: lluvia, reportes recientes, puntaje y nivel.
// Lo usan el radar, la predicción, las alertas, el centro y las rutas.
// Si $simulada trae un número, se usa esa lluvia en lugar del pronóstico real.
function estadoColonias(?float $simulada = null): array {
    $colonias = coloniasActivas();

    // Reportes de inundación o encharcamiento de las últimas 3 horas, sin atender.
    $sql = db()->prepare(
        "SELECT id_colonia, nivel_urgencia FROM reportes
         WHERE tipo IN ('inundacion', 'encharcamiento') AND estado IN ('pendiente', 'en_revision')
           AND fecha_reporte >= ?"
    );
    $sql->execute([haceHoras(3)]);
    $urgencia = [];
    $total = [];
    foreach ($sql->fetchAll() as $r) {
        $id = $r['id_colonia'];
        $urgencia[$id] = ($urgencia[$id] ?? 0) + (PESO_URGENCIA[$r['nivel_urgencia']] ?? 2);
        $total[$id] = ($total[$id] ?? 0) + 1;
    }

    // Antecedentes: reportes anteriores (del último año, con más de un día) que no se descartaron.
    $sql = db()->prepare(
        "SELECT id_colonia, COUNT(*) AS total FROM reportes
         WHERE tipo IN ('inundacion', 'encharcamiento') AND estado <> 'descartado'
           AND fecha_reporte < ? AND fecha_reporte >= ?
         GROUP BY id_colonia"
    );
    $sql->execute([haceHoras(24), haceHoras(24 * 365)]);
    $antecedentes = [];
    foreach ($sql->fetchAll() as $f) $antecedentes[$f['id_colonia']] = min(5, (int) $f['total']);

    $lluvia = null;
    if ($colonias && $simulada !== null) {
        $lluvia = lluviaSimulada($colonias, $simulada);
    } elseif ($colonias) {
        $nuevo = false;
        $lluvia = lluviaPorColonia($colonias, $nuevo);
        // Cada pronóstico nuevo (uno cada 10 minutos) se guarda en la tabla mediciones.
        if ($nuevo && $lluvia !== null) registrarMediciones($colonias, $lluvia);
    }

    $salida = [];
    foreach ($colonias as $c) {
        $id = $c['id'];
        $pronostico = $lluvia[$id] ?? null;
        $mm = $pronostico['total'] ?? null;
        $vulnerabilidad = VALOR_VULNERABILIDAD[$c['nivel_vulnerabilidad']] ?? 3;
        $riesgo = calcularRiesgo($mm, $vulnerabilidad, $antecedentes[$id] ?? 0, $urgencia[$id] ?? 0);
        $salida[] = [
            'id' => (int) $id,
            'nombre' => $c['nombre'],
            'municipio' => $c['municipio'],
            'lat' => (float) $c['lat'],
            'lng' => (float) $c['lng'],
            'nivel_vulnerabilidad' => $c['nivel_vulnerabilidad'],
            'vulnerabilidad' => $vulnerabilidad,
            'antecedentes' => $antecedentes[$id] ?? 0,
            'lluvia_mm_6h' => $mm,
            'lluvia_horas' => $pronostico['horas'] ?? [],
            'lluvia_etiquetas' => $pronostico['etiquetas'] ?? [],
            'reportes_recientes' => $total[$id] ?? 0,
            'puntaje' => $riesgo['puntaje'],
            'nivel' => $riesgo['nivel'],
            'desglose' => $riesgo['desglose'],
            'guia' => guiaPorNivel($riesgo['nivel']),
        ];
    }

    return [
        'lluvia_disponible' => $lluvia !== null,
        'simulacion' => $simulada !== null,
        'actualizado' => ahora(),
        'colonias' => $salida,
    ];
}
