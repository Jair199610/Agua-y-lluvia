<?php
require_once __DIR__ . '/http.php';

// Análisis en lenguaje sencillo del riesgo de una colonia.
// Si hay llave de Gemini lo redacta la IA a partir de los datos; si no, se arma
// con una plantilla fija. 'metodo' dice cuál se usó ('ia' o 'plantilla').
// Se guarda 10 minutos en cache/ para no gastar la cuota gratuita.

function analisisColonia(array $c, bool $simulacion): array {
    $datos = [
        'colonia' => $c['nombre'],
        'nivel' => $c['nivel'],
        'puntaje' => $c['puntaje'],
        'lluvia_mm_6h' => $c['lluvia_mm_6h'],
        'vulnerabilidad' => $c['nivel_vulnerabilidad'],
        'reportes_anteriores_de_inundacion' => $c['antecedentes'],
        'reportes_de_inundacion_ultimas_3h' => $c['reportes_recientes'],
        'puntos_por_lluvia' => $c['desglose']['lluvia'],
        'puntos_por_antecedentes' => $c['desglose']['antecedentes'],
        'puntos_por_reportes' => $c['desglose']['reportes'],
        'lluvia_simulada' => $simulacion,
    ];

    $archivo = __DIR__ . '/../cache/analisis_' . md5(json_encode($datos)) . '.json';
    if (file_exists($archivo) && time() - filemtime($archivo) < 600) {
        $guardado = json_decode(file_get_contents($archivo), true);
        if (is_array($guardado)) return $guardado;
    }

    $instruccion = "Eres analista de Protección Civil en Veracruz, México. Con los datos siguientes "
        . "explica en máximo 3 oraciones, en español sencillo, por qué la colonia tiene ese nivel de "
        . "riesgo de encharcamiento y qué factor pesa más. Usa solo estos datos, no inventes cifras "
        . "ni lugares. Si lluvia_simulada es true, aclara que la lluvia es simulada.\n\n"
        . json_encode($datos, JSON_UNESCAPED_UNICODE);

    $texto = preguntarGemini($instruccion);
    if ($texto !== null) {
        $resultado = ['texto' => $texto, 'metodo' => 'ia'];
        @file_put_contents($archivo, json_encode($resultado, JSON_UNESCAPED_UNICODE));
        return $resultado;
    }
    return ['texto' => analisisConPlantilla($c, $simulacion), 'metodo' => 'plantilla'];
}

function analisisConPlantilla(array $c, bool $simulacion): string {
    $niveles = ['verde' => 'bajo', 'amarillo' => 'medio', 'rojo' => 'alto'];
    $partes = $c['desglose'];
    arsort($partes);
    $principal = array_key_first($partes);
    $nombres = ['lluvia' => 'la lluvia pronosticada', 'antecedentes' => 'los antecedentes de inundación', 'reportes' => 'los reportes de vecinos'];

    $lluvia = $c['lluvia_mm_6h'] === null ? 'sin dato de lluvia' : $c['lluvia_mm_6h'] . ' mm de lluvia' . ($simulacion ? ' simulada' : ' pronosticada') . ' en 6 horas';
    $texto = "{$c['nombre']} tiene riesgo {$niveles[$c['nivel']]} ({$c['puntaje']} de 100): $lluvia, "
           . "{$c['antecedentes']} reportes anteriores de inundación y {$c['reportes_recientes']} en las últimas 3 horas.";
    if ($c['puntaje'] > 0) $texto .= " Lo que más pesa: {$nombres[$principal]}.";
    return $texto;
}
