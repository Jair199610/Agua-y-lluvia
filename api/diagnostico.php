<?php
// http://localhost/lluvia/api/diagnostico.php
// Revisa paso por paso por qué la página no conecta. Solo responde si se abre desde la
// misma computadora (localhost), para no dar información del servidor a extraños.
// No muestra contraseñas ni llaves: solo dice si están puestas o no.

header('Content-Type: text/plain; charset=utf-8');
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit("Este diagnóstico solo se puede abrir desde la misma computadora (http://localhost/...).\n");
}

function linea(bool $bien, string $texto, string $solucion = ''): void {
    echo ($bien ? '[ OK ]  ' : '[FALLA] ') . $texto . "\n";
    if (!$bien && $solucion !== '') echo "         -> $solucion\n";
}

echo "DIAGNÓSTICO DE AQUA SENTINEL\n============================\n\n";

linea(version_compare(PHP_VERSION, '7.4', '>='), 'Versión de PHP: ' . PHP_VERSION, 'Se necesita PHP 7.4 o más nuevo.');
foreach (['pdo_mysql' => 'conectar a MySQL', 'curl' => 'pedir la lluvia y usar la IA', 'mbstring' => 'manejar acentos', 'openssl' => 'enviar correos'] as $ext => $uso) {
    linea(extension_loaded($ext), "Extensión $ext (para $uso)", "Activa la extensión $ext en la configuración de PHP y reinicia Apache.");
}

$hayEnv = file_exists(__DIR__ . '/../.env');
linea($hayEnv, 'Archivo .env', 'No existe. La página funciona con los datos por defecto (base aqua_sentinel, usuario root sin contraseña), pero sin IA ni correo. Copia .env.example como .env.');

require_once __DIR__ . '/../config.php';
echo "\nDatos de conexión que se están usando:\n";
echo '   servidor: ' . env('DB_HOST', 'localhost') . "\n   base:     " . env('DB_NAME', 'aqua_sentinel') . "\n   usuario:  " . env('DB_USER', 'root')
   . "\n   contraseña: " . (env('DB_PASS') === '' ? '(vacía)' : '(puesta)') . "\n\n";

try {
    $pdo = db();
    linea(true, 'Conexión a MySQL y a la base ' . env('DB_NAME', 'aqua_sentinel'));
} catch (Throwable $e) {
    $m = $e->getMessage();
    if (strpos($m, 'Unknown database') !== false)      $s = 'La base no existe. Créala en phpMyAdmin con ese nombre exacto e importa sql/instalar_todo.sql.';
    elseif (strpos($m, 'Access denied') !== false)     $s = 'Usuario o contraseña incorrectos. Corrige DB_USER y DB_PASS en el .env.';
    elseif (strpos($m, 'refused') !== false || strpos($m, '2002') !== false) $s = 'MySQL está apagado. Enciéndelo en Laragon o XAMPP.';
    elseif (strpos($m, 'could not find driver') !== false) $s = 'Falta la extensión pdo_mysql de PHP.';
    else $s = 'Revisa los datos del .env.';
    linea(false, 'Conexión a MySQL: ' . $m, $s);
    exit("\nCorrige eso primero y vuelve a abrir esta página.\n");
}

$tablas = ['colonias', 'reportes', 'evaluaciones_riesgo', 'alertas', 'simulaciones', 'puntos_seguros',
           'usuarios', 'roles', 'permisos', 'rol_permiso', 'puntos_monitoreo', 'mediciones'];
$faltan = [];
foreach ($tablas as $t) {
    try { $pdo->query("SELECT 1 FROM $t LIMIT 1"); } catch (Throwable $e) { $faltan[] = $t; }
}
linea(!$faltan, 'Las 12 tablas existen' . ($faltan ? ' (faltan: ' . implode(', ', $faltan) . ')' : ''),
      'Entra a la base en phpMyAdmin e importa sql/instalar_todo.sql.');
if ($faltan) exit("\nCorrige eso primero y vuelve a abrir esta página.\n");

$contar = fn(string $t) => (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
linea($contar('roles') > 0 && $contar('rol_permiso') > 0, 'Roles y permisos cargados (' . $contar('roles') . ' roles)', 'Faltan los roles: importa sql/instalar_todo.sql.');
linea($contar('colonias') > 0, 'Colonias registradas: ' . $contar('colonias'), 'No hay colonias: importa sql/datos_ejemplo.sql.');
linea($contar('puntos_seguros') > 0, 'Puntos seguros registrados: ' . $contar('puntos_seguros'), 'No hay puntos seguros: importa sql/datos_ejemplo.sql.');
echo '        Usuarios registrados: ' . $contar('usuarios') . ($contar('usuarios') === 0 ? ' (la primera cuenta que se cree será Administrador)' : '') . "\n";

try { $pdo->query('SELECT id_colonia FROM usuarios LIMIT 1'); $columna = true; } catch (Throwable $e) { $columna = false; }
linea($columna, 'Columna usuarios.id_colonia (alertas por correo)', 'Opcional: importa sql/correo.sql para activar el correo.');

linea(is_writable(__DIR__ . '/../cache'), 'La carpeta cache se puede escribir', 'Da permisos de escritura a la carpeta cache.');
linea(env('GEMINI_API_KEY') !== '', 'Llave de Gemini puesta', 'Opcional: sin ella los reportes se clasifican por palabras clave.');
linea(env('SMTP_USER') !== '', 'Cuenta de correo puesta', 'Opcional: sin ella no se envían correos.');

echo "\nSi todo lo de arriba dice OK, abre http://localhost/lluvia/index.php y recarga con Ctrl+F5.\n";
