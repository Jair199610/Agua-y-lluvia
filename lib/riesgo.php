<?php
// TODO el cálculo de riesgo vive aquí. Si el giro del jueves cambia las reglas,
// este es el único archivo que hay que tocar.
//
// Puntaje de 0 a 100, suma de tres partes:
//   - Lluvia (hasta 50): 2.5 puntos por cada mm pronosticado en 6 horas,
//     ajustado por la vulnerabilidad de la colonia (bajo=2, medio=3, alto=4, muy_alto=5).
//   - Antecedentes (hasta 15): 3 puntos por cada reporte anterior de inundación o
//     encharcamiento en la colonia (último año, máximo 5). Solo cuentan cuando hay
//     lluvia pronosticada (completo a partir de 10 mm).
//   - Reportes (hasta 60): 10 puntos por cada punto de urgencia (bajo=1, medio=2, alto=3,
//     critico=4) de los reportes de inundación o encharcamiento de las últimas 3 horas
//     que sigan pendientes o en revisión.
// Es una fórmula de puntaje, NO un modelo de IA ni una probabilidad calibrada.
// La IA participa clasificando los reportes (que alimentan el puntaje) y redactando
// el análisis (lib/analisis.php). Los pesos son decisión del equipo: ajústenlos y
// anótenlo en la bitácora.

const NOMBRE_MODELO = 'formula_puntaje_v1';   // se guarda en evaluaciones_riesgo.modelo_ia

function calcularRiesgo(?float $lluviaMm, int $vulnerabilidad, int $antecedentes, int $urgenciaReportes): array {
    $mm = $lluviaMm ?? 0.0;
    $puntosLluvia = min(50, $mm * 2.5) * (0.6 + $vulnerabilidad * 0.08);
    $puntosAntecedentes = min(15, $antecedentes * 3) * min(1, $mm / 10);
    $puntosReportes = min(60, $urgenciaReportes * 10);
    $puntaje = (int) round(min(100, $puntosLluvia + $puntosAntecedentes + $puntosReportes));

    if ($puntaje >= 60)      $nivel = 'rojo';
    elseif ($puntaje >= 30)  $nivel = 'amarillo';
    else                     $nivel = 'verde';

    return [
        'puntaje' => $puntaje,
        'nivel' => $nivel,
        'desglose' => [
            'lluvia' => (int) round($puntosLluvia),
            'antecedentes' => (int) round($puntosAntecedentes),
            'reportes' => (int) round($puntosReportes),
        ],
    ];
}

// Nivel como lo guarda la base (evaluaciones_riesgo.nivel_riesgo y simulaciones.resultado_riesgo).
function nivelRiesgoBD(int $puntaje): string {
    if ($puntaje >= 85) return 'critico';
    if ($puntaje >= 60) return 'alto';
    if ($puntaje >= 30) return 'medio';
    return 'bajo';
}

// BORRADOR: quien lleva Producto debe reescribir estos textos con base en
// recomendaciones públicas de Protección Civil.
function guiaPorNivel(string $nivel): array {
    $guias = [
        'verde' => ['Sin riesgo por ahora. Mantén limpias las coladeras frente a tu casa.'],
        'amarillo' => [
            'Sube documentos y aparatos eléctricos a un lugar alto.',
            'Evita dejar tu vehículo en calles que suelen encharcarse.',
            'Mantente atento a los avisos oficiales.',
        ],
        'rojo' => [
            'No cruces calles inundadas a pie ni en vehículo.',
            'Desconecta la luz si el agua entra a tu casa.',
            'Ubica tu punto seguro más cercano y llama al 911 en caso de emergencia.',
        ],
    ];
    return $guias[$nivel] ?? [];
}
