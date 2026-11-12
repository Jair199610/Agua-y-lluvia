<?php
require_once __DIR__ . '/riesgo.php';
require_once __DIR__ . '/analisis.php';
require_once __DIR__ . '/correo.php';

// Guarda en la base lo que calcula el sistema, usando las tablas del equipo:
//   - evaluaciones_riesgo: una fila cada vez que cambia el riesgo de una colonia.
//   - alertas: se abren y se cierran solas según el nivel de cada colonia.
// Solo se llama con el pronóstico REAL; las simulaciones nunca generan alertas.

// Devuelve [id_colonia => id_evaluacion] con la evaluación vigente de cada colonia.
function registrarEvaluaciones(array $colonias): array {
    $ultimas = [];
    $filas = db()->query(
        "SELECT e.id_evaluacion, e.id_colonia, e.nivel_riesgo, e.probabilidad
         FROM evaluaciones_riesgo e
         JOIN (SELECT id_colonia, MAX(id_evaluacion) AS ultima FROM evaluaciones_riesgo
               WHERE id_simulacion IS NULL GROUP BY id_colonia) u ON u.ultima = e.id_evaluacion"
    )->fetchAll();
    foreach ($filas as $f) $ultimas[$f['id_colonia']] = $f;

    $insertar = db()->prepare(
        "INSERT INTO evaluaciones_riesgo (id_colonia, id_simulacion, modelo_ia, probabilidad, nivel_riesgo,
                                          lluvia_acumulada, nivel_agua, cantidad_reportes, explicacion, fecha_evaluacion)
         VALUES (?, NULL, ?, ?, ?, ?, NULL, ?, ?, ?)"
    );

    $vigentes = [];
    foreach ($colonias as $c) {
        $nivel = nivelRiesgoBD($c['puntaje']);
        $ultima = $ultimas[$c['id']] ?? null;
        // Se guarda una evaluación nueva solo si cambió el nivel o el puntaje se movió 5 puntos o más.
        $cambio = $ultima === null || $ultima['nivel_riesgo'] !== $nivel
               || abs((float) $ultima['probabilidad'] - $c['puntaje']) >= 5;
        if (!$cambio) { $vigentes[$c['id']] = (int) $ultima['id_evaluacion']; continue; }

        // OJO: la columna se llama "probabilidad" pero guarda el puntaje de 0 a 100 de la fórmula.
        $insertar->execute([$c['id'], NOMBRE_MODELO, $c['puntaje'], $nivel, $c['lluvia_mm_6h'],
                            $c['reportes_recientes'], analisisConPlantilla($c, false), ahora()]);
        $vigentes[$c['id']] = (int) db()->lastInsertId();
    }
    return $vigentes;
}

// Abre, cambia o cierra la alerta automática de inundación de cada colonia.
// Las alertas creadas por una persona (id_usuario_creador con valor) no se tocan.
function sincronizarAlertas(array $colonias, array $evaluaciones): void {
    $activas = [];
    $filas = db()->query(
        "SELECT id_alerta, id_colonia, nivel FROM alertas
         WHERE estado = 'activa' AND tipo = 'inundacion' AND id_usuario_creador IS NULL"
    )->fetchAll();
    foreach ($filas as $f) $activas[$f['id_colonia']][] = $f;

    $cerrar = db()->prepare("UPDATE alertas SET estado = 'finalizada', fecha_fin = ? WHERE id_alerta = ?");
    $abrir = db()->prepare(
        "INSERT INTO alertas (id_colonia, id_evaluacion, id_usuario_creador, titulo, mensaje, tipo, nivel, estado, fecha_inicio)
         VALUES (?, ?, NULL, ?, ?, 'inundacion', ?, 'activa', ?)"
    );

    foreach ($colonias as $c) {
        // Nivel de alerta que le toca a la colonia ahora (null = ninguna).
        if ($c['puntaje'] >= 85)          $deseado = 'emergencia';
        elseif ($c['nivel'] === 'rojo')   $deseado = 'urgente';
        elseif ($c['nivel'] === 'amarillo') $deseado = 'preventiva';
        else                              $deseado = null;

        $yaExiste = false;
        foreach ($activas[$c['id']] ?? [] as $a) {
            if ($a['nivel'] === $deseado && !$yaExiste) { $yaExiste = true; continue; }
            $cerrar->execute([ahora(), $a['id_alerta']]);
        }
        if ($deseado === null || $yaExiste) continue;

        $titulo = ($deseado === 'preventiva' ? 'Precaución en ' : 'Riesgo alto de encharcamiento en ') . $c['nombre'];
        $abrir->execute([$c['id'], $evaluaciones[$c['id']] ?? null, $titulo, $c['guia'][0] ?? '', $deseado, ahora()]);
        // Alerta recién abierta: se avisa por correo a quienes viven en esa colonia (una sola vez).
        avisarAlertaPorCorreo($c, $titulo, $deseado, (int) db()->lastInsertId());
    }
}
