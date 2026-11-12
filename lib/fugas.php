<?php
require_once __DIR__ . '/geo.php';

// Detector colaborativo de fugas.
// Agrupa los reportes de "fuga" y "falta de agua" que quedan cerca entre sí
// (hasta 300 metros) y que se hicieron en las últimas 24 horas.
// Reglas (decisión del equipo, ajústenlas):
//   - Un grupo necesita al menos un reporte de fuga.
//   - 1 reporte          -> reporte aislado (no se considera fuga todavía)
//   - 2 reportes         -> fuga probable, confianza media
//   - 3 o más reportes, o fuga + falta de agua en la misma zona -> confianza alta
// Es una regla de coincidencia geográfica, no un modelo de IA.

const RADIO_FUGA_METROS = 300;

function detectarFugas(array $reportes): array {
    $grupos = [];
    foreach ($reportes as $r) {
        if ($r['lat'] === null || $r['lng'] === null) continue;
        $lat = (float) $r['lat'];
        $lng = (float) $r['lng'];
        $asignado = false;
        foreach ($grupos as &$g) {
            if (distanciaMetros($lat, $lng, $g['lat'], $g['lng']) <= RADIO_FUGA_METROS) {
                $g['reportes'][] = $r;
                // El centro del grupo es el promedio de sus reportes.
                $n = count($g['reportes']);
                $g['lat'] = ($g['lat'] * ($n - 1) + $lat) / $n;
                $g['lng'] = ($g['lng'] * ($n - 1) + $lng) / $n;
                $asignado = true;
                break;
            }
        }
        unset($g);
        if (!$asignado) $grupos[] = ['lat' => $lat, 'lng' => $lng, 'reportes' => [$r]];
    }

    $salida = [];
    foreach ($grupos as $g) {
        $tipos = array_column($g['reportes'], 'tipo');
        $fugas = count(array_keys($tipos, 'fuga'));
        $faltas = count(array_keys($tipos, 'falta_agua'));
        if ($fugas === 0) continue; // solo falta de agua: puede ser tandeo, no fuga

        $total = count($g['reportes']);
        if ($total === 1)                          $confianza = 'aislado';
        elseif ($total >= 3 || $faltas > 0)        $confianza = 'alta';
        else                                       $confianza = 'media';

        $salida[] = [
            'lat' => round($g['lat'], 6),
            'lng' => round($g['lng'], 6),
            'confianza' => $confianza,
            'total_reportes' => $total,
            'reportes_fuga' => $fugas,
            'reportes_falta_de_agua' => $faltas,
            'colonia_id' => (int) $g['reportes'][0]['colonia_id'],
            'colonia' => $g['reportes'][0]['colonia'],
            'reportes' => array_map(fn($r) => [
                'id' => (int) $r['id'], 'texto' => $r['texto'], 'tipo' => $r['tipo'],
                'creado_en' => $r['creado_en'], 'es_demo' => strpos($r['titulo'], MARCA_DEMO) === 0 ? 1 : 0,
            ], $g['reportes']),
        ];
    }

    // Primero las de mayor confianza y más reportes.
    $orden = ['alta' => 0, 'media' => 1, 'aislado' => 2];
    usort($salida, fn($a, $b) => [$orden[$a['confianza']], -$a['total_reportes']] <=> [$orden[$b['confianza']], -$b['total_reportes']]);
    return $salida;
}

// Reportes que entran al detector: fuga o falta de agua, últimas 24 h, sin atender.
// Si el vecino no marcó el punto en el mapa, se usa el centro de su colonia.
function reportesParaFugas(): array {
    $sql = db()->prepare(
        "SELECT r.id_reporte AS id, r.titulo, r.descripcion AS texto, r.tipo, r.fecha_reporte AS creado_en,
                COALESCE(r.latitud, c.latitud) AS lat, COALESCE(r.longitud, c.longitud) AS lng,
                r.id_colonia AS colonia_id, c.nombre AS colonia
         FROM reportes r JOIN colonias c ON c.id_colonia = r.id_colonia
         WHERE r.tipo IN ('fuga', 'falta_agua') AND r.estado IN ('pendiente', 'en_revision') AND r.fecha_reporte >= ?
         ORDER BY r.fecha_reporte"
    );
    $sql->execute([haceHoras(24)]);
    return $sql->fetchAll();
}
