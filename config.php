<?php
// Configuración compartida: lee el .env, conecta a la base y responde en JSON.

date_default_timezone_set('America/Mexico_City');

function cargarEnv(string $ruta): void {
    if (!file_exists($ruta)) return;
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) continue;
        [$clave, $valor] = explode('=', $linea, 2);
        $_ENV[trim($clave)] = trim($valor);
    }
}
cargarEnv(__DIR__ . '/.env');

function env(string $clave, string $porDefecto = ''): string {
    $valor = $_ENV[$clave] ?? '';
    return $valor !== '' ? $valor : $porDefecto;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // DB_DSN es opcional (por ejemplo "sqlite:/ruta/archivo.db" para pruebas).
        $dsn = env('DB_DSN', 'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_NAME', 'aqua_sentinel') . ';charset=utf8mb4');
        $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Las columnas TIMESTAMP dependen de la zona horaria de la conexión:
        // se fija a la hora de México para que coincida con las fechas que calcula PHP.
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        }
    }
    return $pdo;
}

// Las fechas se calculan en PHP (hora de México) para no depender de la zona horaria de la base.
function ahora(): string {
    return date('Y-m-d H:i:s');
}
function haceHoras(int $horas): string {
    return date('Y-m-d H:i:s', time() - $horas * 3600);
}

function responderJson($datos, int $codigo = 200): void {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Cuerpo JSON de una petición POST, como arreglo.
function cuerpoJson(): array {
    $datos = json_decode(file_get_contents('php://input'), true);
    return is_array($datos) ? $datos : [];
}

// Lluvia simulada (mm en 6 horas) que llega por la URL, o null si no hay simulación.
function lluviaSimuladaPedida(): ?float {
    if (!isset($_GET['lluvia_simulada']) || !is_numeric($_GET['lluvia_simulada'])) return null;
    return max(0.0, min(100.0, (float) $_GET['lluvia_simulada']));
}

// Coordenada válida dentro de la zona de Veracruz, o null.
function coordenada($valor, float $min, float $max): ?float {
    if (!is_numeric($valor)) return null;
    $n = (float) $valor;
    return ($n >= $min && $n <= $max) ? $n : null;
}
const LAT_MIN = 18.9; const LAT_MAX = 19.4; const LNG_MIN = -96.4; const LNG_MAX = -95.9;

// Catálogos de la base aqua_sentinel (deben coincidir con los ENUM de las tablas).
const TIPOS_REPORTE = ['inundacion', 'encharcamiento', 'fuga', 'falta_agua', 'alcantarilla', 'otro'];
const NOMBRES_TIPO = ['inundacion' => 'Inundación', 'encharcamiento' => 'Encharcamiento', 'fuga' => 'Fuga',
                      'falta_agua' => 'Falta de agua', 'alcantarilla' => 'Alcantarilla', 'otro' => 'Otro'];
const PESO_URGENCIA = ['bajo' => 1, 'medio' => 2, 'alto' => 3, 'critico' => 4];       // reportes.nivel_urgencia
const ESTADOS_REPORTE = ['pendiente', 'en_revision', 'atendido', 'descartado'];
const ESTADOS_PUNTO = ['disponible', 'ocupado', 'cerrado'];
const VALOR_VULNERABILIDAD = ['bajo' => 2, 'medio' => 3, 'alto' => 4, 'muy_alto' => 5]; // colonias.nivel_vulnerabilidad
const MARCA_DEMO = '[DEMO] ';   // los reportes de demostración llevan esta marca al inicio del título
