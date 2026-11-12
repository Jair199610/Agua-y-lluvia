<?php
// Administración de usuarios (permiso "usuarios": solo el Administrador).
// GET  api/usuarios.php                                         -> usuarios y roles
// POST api/usuarios.php {nombre, apellido?, correo, contrasena, id_rol} -> crea un usuario con ese rol
// POST api/usuarios.php {accion:"estado", id, estado}           -> activo, inactivo o bloqueado
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/correo.php';

try {
    $yo = exigirPermiso('usuarios');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cuerpo = cuerpoJson();

        if (($cuerpo['accion'] ?? '') === 'estado') {
            $estado = (string) ($cuerpo['estado'] ?? '');
            $id = (int) ($cuerpo['id'] ?? 0);
            if (!in_array($estado, ['activo', 'inactivo', 'bloqueado'], true)) responderJson(['error' => 'Estado no válido'], 400);
            if ($id === $yo['id']) responderJson(['error' => 'No puedes desactivar tu propia cuenta'], 400);
            $sql = db()->prepare('UPDATE usuarios SET estado = ? WHERE id_usuario = ?');
            $sql->execute([$estado, $id]);
            responderJson(['ok' => true]);
        }

        $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
        $apellido = mb_substr(trim((string) ($cuerpo['apellido'] ?? '')), 0, 100);
        $correo = mb_strtolower(trim((string) ($cuerpo['correo'] ?? '')));
        $contrasena = (string) ($cuerpo['contrasena'] ?? '');
        $idRol = (int) ($cuerpo['id_rol'] ?? 0);

        $sql = db()->prepare('SELECT COUNT(*) FROM roles WHERE id_rol = ?');
        $sql->execute([$idRol]);
        if ((int) $sql->fetchColumn() === 0) responderJson(['error' => 'Elige un rol'], 400);
        $error = errorDeCuenta($nombre, $correo, $contrasena);
        if ($error !== null) responderJson(['error' => $error], 400);

        responderJson(['id' => crearUsuario($idRol, $nombre, $apellido, $correo, $contrasena)], 201);
    }

    // Nunca se devuelve la columna contrasena.
    // La colonia de cada usuario solo existe si ya se importó sql/correo.sql.
    $conColonia = usuariosTienenColonia();
    $usuarios = db()->query(
        "SELECT u.id_usuario AS id, u.nombre, u.apellido, u.correo, u.telefono, u.estado, u.ultimo_acceso, r.nombre AS rol"
        . ($conColonia ? ", u.id_colonia AS colonia_id, c.nombre AS colonia" : ", NULL AS colonia_id, NULL AS colonia")
        . " FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol"
        . ($conColonia ? " LEFT JOIN colonias c ON c.id_colonia = u.id_colonia" : "")
        . " ORDER BY u.id_usuario"
    )->fetchAll();
    foreach ($usuarios as &$u) {
        $u['id'] = (int) $u['id'];
        $u['colonia_id'] = $u['colonia_id'] === null ? null : (int) $u['colonia_id'];
    }
    unset($u);
    $roles = db()->query('SELECT id_rol AS id, nombre, descripcion FROM roles ORDER BY id_rol')->fetchAll();
    foreach ($roles as &$r) $r['id'] = (int) $r['id'];
    responderJson(['usuarios' => $usuarios, 'roles' => $roles, 'yo' => $yo['id']]);
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudieron administrar los usuarios'], 500);
}
