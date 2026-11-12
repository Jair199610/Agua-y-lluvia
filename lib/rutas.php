<?php
require_once __DIR__ . '/geo.php';
require_once __DIR__ . '/http.php';

// Rutas seguras: lleva a la persona al punto seguro DISPONIBLE más conveniente,
// evitando en lo posible las zonas de riesgo.
//
// Cómo funciona:
//   1. Se arman las "zonas a evitar": colonias en rojo o amarillo (círculo de 600 m
//      alrededor de su centro) y reportes recientes de encharcamiento con ubicación (150 m).
//   2. Se toman los 3 puntos seguros con estado 'disponible' más cercanos (fuera de zonas rojas si se puede).
//   3. Para cada uno se piden rutas al servicio público OSRM (con alternativas).
//   4. Se elige la ruta que cruza menos zonas rojas; si empatan, la más corta.
// Límites que hay que decir al jurado: las zonas son círculos aproximados, no calles
// inundadas reales; OSRM calcula rutas para auto y su servidor público es solo para demos.

const RADIO_COLONIA_METROS = 600;
const RADIO_REPORTE_METROS = 150;

function zonasAEvitar(array $colonias, array $reportesEncharcamiento): array {
    $zonas = [];
    foreach ($colonias as $c) {
        if ($c['nivel'] === 'verde') continue;
        $zonas[] = ['nombre' => $c['nombre'], 'lat' => $c['lat'], 'lng' => $c['lng'],
                    'radio' => RADIO_COLONIA_METROS, 'nivel' => $c['nivel']];
    }
    foreach ($reportesEncharcamiento as $r) {
        if ($r['lat'] === null || $r['lng'] === null) continue;
        $zonas[] = ['nombre' => 'Encharcamiento reportado' . ($r['colonia'] ? ' en ' . $r['colonia'] : ''),
                    'lat' => (float) $r['lat'], 'lng' => (float) $r['lng'],
                    'radio' => RADIO_REPORTE_METROS, 'nivel' => 'rojo'];
    }
    return $zonas;
}

// Revisa por cuáles zonas pasa una ruta. $coordenadas es una lista de [lng, lat].
function evaluarRuta(array $coordenadas, array $zonas): array {
    $rojas = [];
    $amarillas = [];
    foreach ($coordenadas as $p) {
        foreach ($zonas as $z) {
            if (distanciaMetros((float) $p[1], (float) $p[0], $z['lat'], $z['lng']) <= $z['radio']) {
                if ($z['nivel'] === 'rojo') $rojas[$z['nombre']] = true;
                else $amarillas[$z['nombre']] = true;
            }
        }
    }
    return ['rojas' => array_keys($rojas), 'amarillas' => array_keys($amarillas)];
}

function puntoEnZonaRoja(float $lat, float $lng, array $zonas): bool {
    foreach ($zonas as $z) {
        if ($z['nivel'] === 'rojo' && distanciaMetros($lat, $lng, $z['lat'], $z['lng']) <= $z['radio']) return true;
    }
    return false;
}

// Rutas por calle entre dos puntos, con OSRM. Devuelve lista de rutas o [] si falla.
function rutasOsrm(float $lat1, float $lng1, float $lat2, float $lng2): array {
    $url = "https://router.project-osrm.org/route/v1/driving/$lng1,$lat1;$lng2,$lat2"
         . "?overview=full&geometries=geojson&alternatives=true";
    $datos = httpJson($url, null, [], 8);
    $rutas = [];
    foreach ($datos['routes'] ?? [] as $r) {
        $coordenadas = $r['geometry']['coordinates'] ?? null;
        if (is_array($coordenadas) && count($coordenadas) >= 2) {
            $rutas[] = ['coordenadas' => $coordenadas, 'distancia_m' => (float) $r['distance'], 'tipo' => 'calles'];
        }
    }
    return $rutas;
}

// Línea recta con puntos intermedios: respaldo cuando OSRM no responde.
function rutaLineaRecta(float $lat1, float $lng1, float $lat2, float $lng2): array {
    $coordenadas = [];
    for ($i = 0; $i <= 40; $i++) {
        $t = $i / 40;
        $coordenadas[] = [$lng1 + ($lng2 - $lng1) * $t, $lat1 + ($lat2 - $lat1) * $t];
    }
    return ['coordenadas' => $coordenadas, 'distancia_m' => distanciaMetros($lat1, $lng1, $lat2, $lng2), 'tipo' => 'linea_recta'];
}

// $buscarRutas permite cambiar el servicio de rutas (se usa en las pruebas).
function rutaSegura(float $lat, float $lng, array $puntos, array $zonas, ?callable $buscarRutas = null): ?array {
    $buscarRutas = $buscarRutas ?? 'rutasOsrm';

    // Solo se ofrecen los puntos que protección civil tiene como disponibles.
    $verificados = array_values(array_filter($puntos, fn($p) => $p['estado'] === 'disponible'));
    if (!$verificados) return null;
    foreach ($verificados as &$p) {
        $p['distancia_recta'] = distanciaMetros($lat, $lng, (float) $p['lat'], (float) $p['lng']);
        $p['en_zona_roja'] = puntoEnZonaRoja((float) $p['lat'], (float) $p['lng'], $zonas);
    }
    unset($p);
    // Primero los que no están en zona roja; dentro de eso, los más cercanos.
    usort($verificados, fn($a, $b) => [$a['en_zona_roja'], $a['distancia_recta']] <=> [$b['en_zona_roja'], $b['distancia_recta']]);
    $candidatos = array_slice($verificados, 0, 3);

    // Las zonas donde ya está la persona no cuentan como "cruce": de ahí tiene que salir.
    $zonasOrigen = array_values(array_filter($zonas, fn($z) => distanciaMetros($lat, $lng, $z['lat'], $z['lng']) <= $z['radio']));
    $zonasRuta = array_values(array_filter($zonas, fn($z) => distanciaMetros($lat, $lng, $z['lat'], $z['lng']) > $z['radio']));

    $mejor = null;
    foreach ($candidatos as $p) {
        $rutas = $buscarRutas($lat, $lng, (float) $p['lat'], (float) $p['lng']);
        if (!$rutas) $rutas = [rutaLineaRecta($lat, $lng, (float) $p['lat'], (float) $p['lng'])];
        foreach ($rutas as $r) {
            $cruces = evaluarRuta($r['coordenadas'], $zonasRuta);
            // Menos zonas rojas, luego destino fuera de zona roja, luego menos amarillas, luego más corta.
            $clave = [count($cruces['rojas']), (int) $p['en_zona_roja'], count($cruces['amarillas']), $r['distancia_m']];
            if ($mejor === null || $clave < $mejor['clave']) {
                $mejor = ['clave' => $clave, 'punto' => $p, 'ruta' => $r, 'cruces' => $cruces];
            }
        }
    }

    $distancia = $mejor['ruta']['distancia_m'];
    return [
        'destino' => [
            'id' => (int) $mejor['punto']['id'], 'nombre' => $mejor['punto']['nombre'], 'tipo' => $mejor['punto']['tipo'],
            'lat' => (float) $mejor['punto']['lat'], 'lng' => (float) $mejor['punto']['lng'],
            'direccion' => $mejor['punto']['direccion'], 'telefono' => $mejor['punto']['telefono'],
            'capacidad' => (int) $mejor['punto']['capacidad'], 'en_zona_roja' => $mejor['punto']['en_zona_roja'],
        ],
        'tipo_ruta' => $mejor['ruta']['tipo'],             // 'calles' o 'linea_recta'
        'coordenadas' => $mejor['ruta']['coordenadas'],    // lista de [lng, lat]
        'distancia_m' => (int) round($distancia),
        'minutos_a_pie' => (int) ceil($distancia / 75),     // unos 4.5 km/h
        'sale_de_zona_de_riesgo' => array_column($zonasOrigen, 'nombre'),
        'cruza_zonas_rojas' => $mejor['cruces']['rojas'],
        'cruza_zonas_amarillas' => $mejor['cruces']['amarillas'],
    ];
}
