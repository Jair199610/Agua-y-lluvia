<?php
// GET  api/alertas.php[?lluvia_simulada=20]  -> alertas vigentes
// POST api/alertas.php {accion:"crear", titulo, mensaje, tipo, nivel, horas, colonias:[1,2,3]}
//        -> una autoridad manda UN aviso a una o varias colonias
// POST api/alertas.php {accion:"finalizar", ids:[4,5]}  -> cierra un aviso hecho a mano
//
// Función 3 (alerta ciudadana).
// Con el pronóstico real: guarda la evaluación de cada colonia (evaluaciones_riesgo) y abre o
// cierra las alertas automáticas (alertas). En simulación no se guarda nada.
// Cada alerta lleva "para_mi": true si el usuario vive en esa colonia (INNER JOIN entre alertas
// y usuarios) o si es autoridad. Solo esas se le notifican.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/estado.php';
require_once __DIR__ . '/../lib/fugas.php';
require_once __DIR__ . '/../lib/registro.php';

const TIPOS_ALERTA = ['inundacion', 'lluvia', 'fuga', 'falta_agua', 'prevencion', 'otro'];
const NIVELES_ALERTA = ['informativa', 'preventiva', 'urgente', 'emergencia'];

try {
    $yo = exigirPermiso('alertas');
    $esAutoridad = puedeGestionar('alertas');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        exigirGestion('alertas'); // un ciudadano solo ve y recibe alertas; no las crea
        $cuerpo = cuerpoJson();
        $accion = $cuerpo['accion'] ?? '';

        if ($accion === 'finalizar') {
            $ids = array_values(array_filter(array_map('intval', (array) ($cuerpo['ids'] ?? []))));
            if (!$ids) responderJson(['error' => 'Falta indicar el aviso'], 400);
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            // Solo los avisos hechos a mano: las alertas automáticas se cierran solas.
            $sql = db()->prepare(
                "UPDATE alertas SET estado = 'finalizada', fecha_fin = ?
                 WHERE id_alerta IN ($marcas) AND id_usuario_creador IS NOT NULL AND estado = 'activa'"
            );
            $sql->execute(array_merge([ahora()], $ids));
            responderJson(['ok' => true]);
        }

        if ($accion !== 'crear') responderJson(['error' => 'Acción no válida'], 400);

        $titulo = trim((string) ($cuerpo['titulo'] ?? ''));
        $mensaje = trim((string) ($cuerpo['mensaje'] ?? ''));
        $tipo = (string) ($cuerpo['tipo'] ?? '');
        $nivel = (string) ($cuerpo['nivel'] ?? '');
        $horas = max(1, min(72, (int) ($cuerpo['horas'] ?? 6)));
        if ($titulo === '' || mb_strlen($titulo) > 180) responderJson(['error' => 'Escribe un título (máximo 180 letras)'], 400);
        if ($mensaje === '' || mb_strlen($mensaje) > 1000) responderJson(['error' => 'Escribe el mensaje (máximo 1000 letras)'], 400);
        if (!in_array($tipo, TIPOS_ALERTA, true) || !in_array($nivel, NIVELES_ALERTA, true)) responderJson(['error' => 'Tipo o nivel no válido'], 400);

        // Colonias elegidas: solo se aceptan las que existen y están activas.
        $nombres = array_column(coloniasActivas(), 'nombre', 'id');
        $elegidas = [];
        foreach ((array) ($cuerpo['colonias'] ?? []) as $id) {
            if (isset($nombres[(int) $id])) $elegidas[(int) $id] = $nombres[(int) $id];
        }
        if (!$elegidas) responderJson(['error' => 'Elige al menos una colonia'], 400);

        // Una fila por colonia, todas con el mismo título, mensaje y fecha: así forman un solo aviso.
        $inicio = ahora();
        $fin = date('Y-m-d H:i:s', time() + $horas * 3600);
        $sql = db()->prepare(
            "INSERT INTO alertas (id_colonia, id_evaluacion, id_usuario_creador, titulo, mensaje, tipo, nivel, estado, fecha_inicio, fecha_fin)
             VALUES (?, NULL, ?, ?, ?, ?, ?, 'activa', ?, ?)"
        );
        $ids = [];
        foreach ($elegidas as $idColonia => $nombre) {
            $sql->execute([$idColonia, $yo['id'], $titulo, $mensaje, $tipo, $nivel, $inicio, $fin]);
            $ids[] = (int) db()->lastInsertId();
        }

        // Un solo correo para los vecinos de todas las colonias elegidas.
        $correo = avisarAvisoPorCorreo($ids, array_values($elegidas), $titulo, $mensaje, $nivel, $yo['nombre'] . ' (' . $yo['rol'] . ')');
        responderJson(['ok' => true, 'colonias' => count($ids),
                       'correos' => is_int($correo) ? $correo : 0, 'aviso_correo' => is_int($correo) ? null : $correo], 201);
    }

    // ---------- Consulta ----------
    $estado = estadoColonias(lluviaSimuladaPermitida());
    $puntajes = array_column($estado['colonias'], 'puntaje', 'id');
    $miColonia = coloniaDelUsuario($yo['id']);
    $alertas = [];

    if ($estado['simulacion']) {
        foreach ($estado['colonias'] as $c) {
            if ($c['nivel'] === 'verde') continue;
            $alertas[] = [
                'id' => 'simulada-' . $c['id'] . '-' . $c['nivel'], 'grupo' => null, 'ids' => [], 'manual' => false,
                'clase' => 'inundacion', 'nivel' => $c['nivel'] === 'rojo' ? 'urgente' : 'preventiva',
                'color' => $c['nivel'], 'colonia_id' => $c['id'], 'colonia' => $c['nombre'],
                'titulo' => ($c['nivel'] === 'rojo' ? 'Riesgo alto de encharcamiento en ' : 'Precaución en ') . $c['nombre'],
                'mensaje' => $c['guia'][0] ?? '', 'puntaje' => $c['puntaje'], 'autor' => null,
                'para_mi' => $esAutoridad || $c['id'] === $miColonia,
            ];
        }
    } else {
        sincronizarAlertas($estado['colonias'], registrarEvaluaciones($estado['colonias']));

        // Alertas que le tocan a este usuario: INNER JOIN alertas-usuarios por la colonia.
        $mias = alertasDelUsuario($yo['id']);

        $sql = db()->prepare(
            "SELECT a.id_alerta, a.id_colonia, c.nombre AS colonia, a.titulo, a.mensaje, a.tipo, a.nivel,
                    a.fecha_inicio, a.id_usuario_creador, u.nombre AS autor
             FROM alertas a
             INNER JOIN colonias c ON c.id_colonia = a.id_colonia
             LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario_creador
             WHERE a.estado = 'activa' AND a.fecha_inicio <= ? AND (a.fecha_fin IS NULL OR a.fecha_fin > ?)
             ORDER BY a.id_alerta"
        );
        $sql->execute([ahora(), ahora()]);
        foreach ($sql->fetchAll() as $a) {
            $manual = $a['id_usuario_creador'] !== null;
            $alertas[] = [
                'id' => 'alerta-' . $a['id_alerta'],
                // Las filas de un mismo aviso manual comparten grupo: la página las muestra como una sola.
                'grupo' => $manual ? 'aviso-' . md5($a['id_usuario_creador'] . '|' . $a['fecha_inicio'] . '|' . $a['titulo']) : null,
                'ids' => [(int) $a['id_alerta']], 'manual' => $manual,
                'clase' => $a['tipo'], 'nivel' => $a['nivel'],
                'color' => in_array($a['nivel'], ['urgente', 'emergencia'], true) ? 'rojo' : 'amarillo',
                'colonia_id' => (int) $a['id_colonia'], 'colonia' => $a['colonia'],
                'titulo' => $a['titulo'], 'mensaje' => $a['mensaje'],
                'puntaje' => $manual ? null : ($puntajes[(int) $a['id_colonia']] ?? null),
                'autor' => $manual ? $a['autor'] : null,
                'para_mi' => $esAutoridad || in_array((int) $a['id_alerta'], $mias, true),
            ];
        }
    }

    // Las posibles fugas se calculan al momento a partir de los reportes.
    foreach (detectarFugas(reportesParaFugas()) as $f) {
        if ($f['confianza'] === 'aislado') continue;
        $alertas[] = [
            'id' => 'fuga-' . $f['reportes'][0]['id'] . '-' . $f['total_reportes'], 'grupo' => null, 'ids' => [], 'manual' => false,
            'clase' => 'fuga', 'nivel' => 'preventiva', 'color' => 'amarillo',
            'colonia_id' => $f['colonia_id'], 'colonia' => $f['colonia'],
            'titulo' => 'Posible fuga en ' . $f['colonia'],
            'mensaje' => $f['total_reportes'] . ' reportes coinciden en la misma zona (confianza ' . $f['confianza'] . ').',
            'puntaje' => null, 'autor' => null,
            'para_mi' => $esAutoridad || $f['colonia_id'] === $miColonia,
        ];
    }

    // Primero las rojas y, dentro de cada color, las de mayor puntaje.
    usort($alertas, fn($a, $b) => [$a['color'] !== 'rojo', -($a['puntaje'] ?? 0)] <=> [$b['color'] !== 'rojo', -($b['puntaje'] ?? 0)]);

    responderJson(['simulacion' => $estado['simulacion'], 'actualizado' => $estado['actualizado'],
                   'mi_colonia' => $miColonia, 'alertas' => $alertas]);
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudieron cargar las alertas'], 500);
}
