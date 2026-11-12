<?php
// Alertas por correo del usuario que tiene la sesión abierta.
// GET  api/correo.php                              -> estado: ¿está activado?, ¿hay cuenta de envío?, mi colonia
// POST api/correo.php {accion:"colonia", id_colonia} -> guarda mi colonia (0 o null = no recibir correos)
// POST api/correo.php {accion:"prueba"}             -> me manda un correo de prueba (uno por minuto)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/correo.php';

try {
    $yo = usuarioActual();
    if ($yo === null) { soltarSesion(); responderJson(['error' => 'Inicia sesión para continuar'], 401); }
    $activado = usuariosTienenColonia();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cuerpo = cuerpoJson();
        $accion = $cuerpo['accion'] ?? '';

        if ($accion === 'colonia') {
            soltarSesion();
            if (!$activado) responderJson(['error' => 'Falta importar sql/correo.sql en la base de datos'], 400);
            $id = (int) ($cuerpo['id_colonia'] ?? 0);
            if ($id > 0) {
                $sql = db()->prepare("SELECT COUNT(*) FROM colonias WHERE id_colonia = ? AND estado_registro = 'activa'");
                $sql->execute([$id]);
                if ((int) $sql->fetchColumn() === 0) responderJson(['error' => 'Colonia no válida'], 400);
            }
            $sql = db()->prepare('UPDATE usuarios SET id_colonia = ? WHERE id_usuario = ?');
            $sql->execute([$id > 0 ? $id : null, $yo['id']]);
            responderJson(['ok' => true]);
        }

        if ($accion === 'prueba') {
            // Un correo de prueba por minuto, para que nadie use el botón para llenar un buzón.
            $ultimo = (int) ($_SESSION['ultima_prueba_correo'] ?? 0);
            if (time() - $ultimo < 60) { soltarSesion(); responderJson(['error' => 'Espera un minuto antes de pedir otra prueba'], 429); }
            $_SESSION['ultima_prueba_correo'] = time();
            soltarSesion();
            $error = enviarCorreo([$yo['correo']], '[Aqua Sentinel] Correo de prueba',
                "Hola {$yo['nombre']}:\n\nEste es un correo de prueba de Aqua Sentinel. Si lo recibiste, "
                . "las alertas de tu colonia te llegarán a esta dirección.\n\nHora: " . ahora());
            if ($error !== null) responderJson(['error' => $error], 502);
            responderJson(['ok' => true, 'enviado_a' => $yo['correo']]);
        }

        soltarSesion();
        responderJson(['error' => 'Acción no válida'], 400);
    }

    soltarSesion();
    $colonia = null;
    if ($activado) {
        $sql = db()->prepare('SELECT id_colonia FROM usuarios WHERE id_usuario = ?');
        $sql->execute([$yo['id']]);
        $valor = $sql->fetchColumn();
        $colonia = $valor === null || $valor === false ? null : (int) $valor;
    }
    responderJson([
        'activado' => $activado,                   // ¿se importó sql/correo.sql?
        'cuenta_configurada' => correoConfigurado(), // ¿hay SMTP_USER en el .env?
        'correo' => $yo['correo'],
        'colonia_id' => $colonia,
    ]);
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudo procesar la solicitud de correo'], 500);
}
