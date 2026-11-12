<?php
require_once __DIR__ . '/http.php';

// Lluvia pronosticada para las próximas 6 horas en cada colonia, con Open-Meteo.
// Devuelve [colonia_id => ['total' => mm, 'horas' => [mm por hora], 'etiquetas' => ['15:00', ...]]]
// o null si no se pudo consultar. Se guarda 10 minutos en cache/.
// $recienConsultado queda en true cuando el dato se acaba de pedir a Open-Meteo (no salió de la caché).

function lluviaPorColonia(array $colonias, bool &$recienConsultado = false): ?array {
    $recienConsultado = false;
    $archivo = __DIR__ . '/../cache/lluvia.json';
    if (file_exists($archivo) && time() - filemtime($archivo) < 600) {
        $guardado = json_decode(file_get_contents($archivo), true);
        if (is_array($guardado) && count($guardado) === count($colonias)) return $guardado;
    }

    $lats = implode(',', array_column($colonias, 'lat'));
    $lngs = implode(',', array_column($colonias, 'lng'));
    $url = "https://api.open-meteo.com/v1/forecast?latitude=$lats&longitude=$lngs"
         . "&hourly=precipitation&forecast_hours=6&timezone=America%2FMexico_City";

    $datos = httpJson($url);
    if ($datos === null) return null;
    // Con una sola coordenada Open-Meteo devuelve un objeto; con varias, una lista.
    if (isset($datos['hourly'])) $datos = [$datos];

    $resultado = [];
    foreach ($colonias as $i => $colonia) {
        $horas = $datos[$i]['hourly']['precipitation'] ?? null;
        $tiempos = $datos[$i]['hourly']['time'] ?? [];
        if (!is_array($horas)) return null;
        $resultado[$colonia['id']] = [
            'total' => round(array_sum($horas), 1),
            'horas' => array_map(fn($mm) => round((float) $mm, 1), $horas),
            'etiquetas' => array_map(fn($t) => substr((string) $t, 11, 5), $tiempos),
        ];
    }
    @file_put_contents($archivo, json_encode($resultado));
    $recienConsultado = true;
    return $resultado;
}

// Lluvia SIMULADA: reparte los mm indicados en 6 horas, igual para todas las colonias.
// Sirve para mostrar el sistema en un día sin lluvia; la pantalla lo indica como simulación.
function lluviaSimulada(array $colonias, float $mm): array {
    $reparto = [0.05, 0.15, 0.30, 0.25, 0.15, 0.10];
    $horaActual = (int) date('G');
    $resultado = [];
    foreach ($colonias as $colonia) {
        $resultado[$colonia['id']] = [
            'total' => round($mm, 1),
            'horas' => array_map(fn($parte) => round($mm * $parte, 1), $reparto),
            'etiquetas' => array_map(fn($i) => sprintf('%02d:00', ($horaActual + $i) % 24), range(0, 5)),
        ];
    }
    return $resultado;
}
