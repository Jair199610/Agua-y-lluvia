<?php
// GET  api/sesion.php                                   -> usuario con sesión abierta (o null)
// POST api/sesion.php {accion:"entrar", correo, contrasena}
// POST api/sesion.php {accion:"registrar", nombre, apellido?, correo, contrasena}
// POST api/sesion.php {accion:"salir"}
//
// POST registrar también acepta: telefono, id_colonia, rol y clave_autoridad.
//
// Cada persona crea su propia cuenta y elige su tipo (Ciudadano, Administrador, etc.).
//
// OJO, DECISIÓN DEL EQUIPO: por defecto el registro es LIBRE, es decir, cualquiera puede elegir
// "Administrador" al registrarse. Sirve para la demo, pero en un sistema real es un riesgo.
// Para cerrarlo basta poner REGISTRO_CLAVE=algo en el .env: desde ese momento, registrarse con
// un rol que no sea Ciudadano exige escribir esa clave. Háganlo antes de publicar el enlace.
// La primera cuenta de todas (tabla usuarios vacía) siempre queda como Administrador.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/correo.php';

try {
    $hayUsuarios = (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $u = usuarioActual();
        soltarSesion();
        $respuesta = ['usuario' => $u, 'hay_usuarios' => $hayUsuarios, 'puede_reportar' => puedeReportar()];
        if ($u === null) {
            // Datos para el formulario de registro: colonias y tipos de cuenta disponibles.
            $respuesta['colonias'] = [];
            if (usuariosTienenColonia()) {
                $respuesta['colonias'] = db()->query(
                    "SELECT id_colonia AS id, nombre FROM colonias WHERE estado_registro = 'activa' ORDER BY nombre"
                )->fetchAll();
            }
            $roles = db()->query('SELECT nombre FROM roles ORDER BY id_rol')->fetchAll(PDO::FETCH_COLUMN);
            // Tipos de cuenta que se pueden elegir. Si hay REGISTRO_CLAVE en el .env, los de
            // autoridad piden esa clave; si no, el registro es libre.
            $respuesta['roles_registro'] = $roles;
            $respuesta['clave_configurada'] = env('REGISTRO_CLAVE') !== '';
        }
        responderJson($respuesta);
    }

    $cuerpo = cuerpoJson();
    $accion = $cuerpo['accion'] ?? '';
    abrirSesion();

    if ($accion === 'salir') {
        $_SESSION = [];
        session_destroy();
        responderJson(['ok' => true]);
    }

    $correo = mb_strtolower(trim((string) ($cuerpo['correo'] ?? '')));
    $contrasena = (string) ($cuerpo['contrasena'] ?? '');

    if ($accion === 'entrar') {
        $sql = db()->prepare("SELECT id_usuario, contrasena FROM usuarios WHERE correo = ? AND estado = 'activo'");
        $sql->execute([$correo]);
        $fila = $sql->fetch();
        if (!$fila || !password_verify($contrasena, $fila['contrasena'])) {
            usleep(500000); // medio segundo de espera: frena los intentos de adivinar contraseñas
            // Mismo mensaje si falla el correo o la contraseña, para no revelar qué cuentas existen.
            responderJson(['error' => 'Correo o contraseña incorrectos'], 401);
        }
        session_regenerate_id(true); // identificador nuevo al entrar
        $_SESSION['id_usuario'] = (int) $fila['id_usuario'];
        $sql = db()->prepare('UPDATE usuarios SET ultimo_acceso = ? WHERE id_usuario = ?');
        $sql->execute([ahora(), $fila['id_usuario']]);
        responderJson(['ok' => true]);
    }

    if ($accion === 'registrar') {
        $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
        $apellido = mb_substr(trim((string) ($cuerpo['apellido'] ?? '')), 0, 100);
        $error = errorDeCuenta($nombre, $correo, $contrasena);
        if ($error !== null) responderJson(['error' => $error], 400);

        // Tipo de cuenta que pidió. Cualquier rol que no sea Ciudadano exige la clave de autoridad.
        $rolPedido = trim((string) ($cuerpo['rol'] ?? ROL_CIUDADANO));
        if (!$hayUsuarios) {
            $rolPedido = ROL_ADMINISTRADOR;
        } elseif ($rolPedido !== ROL_CIUDADANO) {
            // Solo se pide la clave si el equipo la configuró en el .env.
            $clave = env('REGISTRO_CLAVE');
            if ($clave !== '' && !hash_equals($clave, (string) ($cuerpo['clave_autoridad'] ?? ''))) {
                usleep(500000);
                responderJson(['error' => 'La clave de autoridad no es correcta'], 403);
            }
        }
        $rol = idDeRol($rolPedido);
        if ($rol === null) responderJson(['error' => 'Tipo de cuenta no válido'], 400);

        // Colonia donde vive (para recibir sus alertas). Solo si la base ya tiene esa columna.
        $idColonia = null;
        if (usuariosTienenColonia() && (int) ($cuerpo['id_colonia'] ?? 0) > 0) {
            $sql = db()->prepare("SELECT COUNT(*) FROM colonias WHERE id_colonia = ? AND estado_registro = 'activa'");
            $sql->execute([(int) $cuerpo['id_colonia']]);
            if ((int) $sql->fetchColumn() === 0) responderJson(['error' => 'Colonia no válida'], 400);
            $idColonia = (int) $cuerpo['id_colonia'];
        }
        $telefono = preg_replace('/[^0-9+ ()-]/', '', mb_substr((string) ($cuerpo['telefono'] ?? ''), 0, 20));

        $id = crearUsuario($rol, $nombre, $apellido, $correo, $contrasena, $telefono, $idColonia);
        session_regenerate_id(true);
        $_SESSION['id_usuario'] = $id;
        $sql = db()->prepare('UPDATE usuarios SET ultimo_acceso = ? WHERE id_usuario = ?');
        $sql->execute([ahora(), $id]);
        responderJson(['ok' => true], 201);
    }

    responderJson(['error' => 'Acción no válida'], 400);
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudo procesar la sesión'], 500);
}
