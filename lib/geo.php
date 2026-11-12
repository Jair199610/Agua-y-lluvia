<?php
// Utilidades geográficas.

// Distancia en metros entre dos puntos (fórmula de Haversine).
function distanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $radioTierra = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $radioTierra * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

// Colonia cuyo centro queda más cerca del punto (hasta $maxMetros), o null.
function coloniaMasCercana(float $lat, float $lng, array $colonias, float $maxMetros = 1500): ?array {
    $mejor = null;
    $mejorDistancia = $maxMetros;
    foreach ($colonias as $c) {
        $d = distanciaMetros($lat, $lng, (float) $c['lat'], (float) $c['lng']);
        if ($d <= $mejorDistancia) { $mejor = $c; $mejorDistancia = $d; }
    }
    return $mejor;
}
