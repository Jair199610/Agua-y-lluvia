<?php
// POST api/demo.php {accion: "cargar" | "borrar"}
// Carga o borra reportes de DEMOSTRACIÓN para mostrar el sistema sin esperar reportes reales.
// Se reconocen porque su título empieza con "[DEMO] " y así aparecen en pantalla.
// Se reparten entre las colonias más vulnerables que haya en la base (A = la más vulnerable).
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/estado.php';

// [colonia (0 = la más vulnerable), tipo, urgencia, texto, desplazamiento lat, desplazamiento lng, minutos atrás]
const REPORTES_DEMO = [
    [0, 'inundacion',     'alto',  'El agua ya se metió a varias casas de la calle principal', 0.0010, 0.0008, 20],
    [0, 'encharcamiento', 'medio', 'La avenida está encharcada, el agua llega a la banqueta', -0.0012, 0.0015, 35],
    [1, 'encharcamiento', 'medio', 'Hay agua estancada hasta la banqueta', 0.0006, -0.0010, 50],
    [2, 'fuga',           'medio', 'Hay una fuga grande, brota agua del pavimento desde ayer', 0.0005, 0.0004, 180],
    [2, 'fuga',           'medio', 'Se está tirando el agua en la esquina, parece tubería rota', 0.0012, -0.0006, 150],
    [2, 'falta_agua',     'medio', 'Llevamos dos días sin agua en toda la cuadra', -0.0008, 0.0010, 120],
    [3, 'fuga',           'bajo',  'Fuga pequeña frente a la tienda', 0.0015, 0.0010, 240],
    [4, 'falta_agua',     'bajo',  'No llega el agua desde la mañana', 0.0004, 0.0003, 300],
    [3, 'alcantarilla',   'medio', 'La coladera de la esquina está tapada con basura', -0.0005, 0.0006, 90],
];
// Reportes viejos ya atendidos: sirven como antecedentes de inundación. [colonia, cuántos]
const ANTECEDENTES_DEMO = [[0, 5], [1, 4], [2, 2], [3, 3]];

try {
    exigirGestion('simulaciones');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') responderJson(['error' => 'Usa POST'], 405);
    $accion = cuerpoJson()['accion'] ?? '';

    $borrar = db()->prepare('DELETE FROM reportes WHERE titulo LIKE ?');
    $borrar->execute([MARCA_DEMO . '%']);
    if ($accion === 'borrar') responderJson(['ok' => true, 'reportes' => 0]);
    if ($accion !== 'cargar') responderJson(['error' => 'Acción no válida'], 400);

    $colonias = coloniasActivas();
    if (!$colonias) responderJson(['error' => 'Primero registra colonias con coordenadas (sql/datos_ejemplo.sql)'], 400);
    usort($colonias, fn($a, $b) => (VALOR_VULNERABILIDAD[$b['nivel_vulnerabilidad']] ?? 3) <=> (VALOR_VULNERABILIDAD[$a['nivel_vulnerabilidad']] ?? 3));

    $sql = db()->prepare(
        "INSERT INTO reportes (id_usuario, id_colonia, tipo, titulo, descripcion, latitud, longitud, nivel_urgencia, estado, fecha_reporte, fecha_atencion)
         VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insertados = 0;
    foreach (REPORTES_DEMO as [$i, $tipo, $urgencia, $texto, $dLat, $dLng, $minutos]) {
        $c = $colonias[$i % count($colonias)];
        $titulo = MARCA_DEMO . NOMBRES_TIPO[$tipo] . ' en ' . $c['nombre'];
        $sql->execute([$c['id'], $tipo, $titulo, $texto, $c['lat'] + $dLat, $c['lng'] + $dLng, $urgencia, 'pendiente',
                       date('Y-m-d H:i:s', time() - $minutos * 60), null]);
        $insertados++;
    }
    foreach (ANTECEDENTES_DEMO as [$i, $cuantos]) {
        $c = $colonias[$i % count($colonias)];
        for ($n = 1; $n <= $cuantos; $n++) {
            $fecha = date('Y-m-d H:i:s', time() - $n * 40 * 86400); // uno cada 40 días hacia atrás
            $sql->execute([$c['id'], 'inundacion', MARCA_DEMO . 'Inundación anterior en ' . $c['nombre'],
                           'Antecedente de demostración: inundación atendida en una lluvia anterior.',
                           null, null, 'alto', 'atendido', $fecha, $fecha]);
            $insertados++;
        }
    }
    responderJson(['ok' => true, 'reportes' => $insertados]);
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudieron cargar los datos de demostración'], 500);
}
