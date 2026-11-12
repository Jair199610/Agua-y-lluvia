<?php
// Inicio de sesión, roles y permisos, con las tablas usuarios, roles, permisos y rol_permiso.
//
// Cada ruta de api/ empieza con una de estas tres llamadas:
//   exigirSesion()            -> basta con haber entrado
//   exigirPermiso('alertas')  -> el rol del usuario debe tener ese permiso (tabla rol_permiso)
//   exigirGestion('reportes') -> además del permiso, el rol no puede ser Ciudadano
//                                (un ciudadano registra y consulta, pero no administra)
// Si no se cumple, la ruta responde 401 (sin sesión) o 403 (sin permiso) y termina.

const ROL_CIUDADANO = 'Ciudadano';
const ROL_ADMINISTRADOR = 'Administrador';

function abrirSesion(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('aqua_sesion');
    // httponly: el JavaScript de la página no puede leer la cookie.
    // samesite Lax: otros sitios no pueden mandar peticiones POST con ella.
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}

// Usuario que tiene la sesión abierta (con su rol y sus permisos), o null.
function usuarioActual(): ?array {
    static $cargado = false, $usuario = null;
    if ($cargado) return $usuario;
    $cargado = true;

    abrirSesion();
    $id = (int) ($_SESSION['id_usuario'] ?? 0);
    if ($id === 0) return null;

    $sql = db()->prepare(
        "SELECT u.id_usuario AS id, u.nombre, u.apellido, u.correo, u.id_rol, r.nombre AS rol
         FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
         WHERE u.id_usuario = ? AND u.estado = 'activo'"
    );
    $sql->execute([$id]);
    $fila = $sql->fetch();
    if (!$fila) return null;   // la cuenta se borró, se bloqueó o se desactivó

    $sql = db()->prepare(
        'SELECT p.nombre FROM rol_permiso rp JOIN permisos p ON p.id_permiso = rp.id_permiso WHERE rp.id_rol = ?'
    );
    $sql->execute([$fila['id_rol']]);
    $usuario = [
        'id' => (int) $fila['id'], 'nombre' => $fila['nombre'], 'apellido' => $fila['apellido'],
        'correo' => $fila['correo'], 'rol' => $fila['rol'],
        'permisos' => $sql->fetchAll(PDO::FETCH_COLUMN),
    ];
    return $usuario;
}

function tienePermiso(string $permiso): bool {
    $u = usuarioActual();
    return $u !== null && in_array($permiso, $u['permisos'], true);
}

function puedeGestionar(string $permiso): bool {
    $u = usuarioActual();
    return $u !== null && $u['rol'] !== ROL_CIUDADANO && in_array($permiso, $u['permisos'], true);
}

// Libera el archivo de sesión para que las demás peticiones de la página no tengan que esperar.
function soltarSesion(): void {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
}

function exigirSesion(): array {
    $u = usuarioActual();
    soltarSesion();
    if ($u === null) responderJson(['error' => 'Inicia sesión para continuar'], 401);
    return $u;
}

function exigirPermiso(string $permiso): array {
    $u = exigirSesion();
    if (!tienePermiso($permiso)) responderJson(['error' => 'Tu rol no tiene permiso para esto'], 403);
    return $u;
}

function exigirGestion(string $permiso): array {
    $u = exigirSesion();
    if (!puedeGestionar($permiso)) responderJson(['error' => 'Tu rol no tiene permiso para esto'], 403);
    return $u;
}

// La lluvia simulada solo se acepta de quien tiene el permiso "simulaciones".
function lluviaSimuladaPermitida(): ?float {
    return tienePermiso('simulaciones') ? lluviaSimuladaPedida() : null;
}

// Valida los datos de una cuenta nueva. Devuelve el mensaje de error o null si todo está bien.
function errorDeCuenta(string $nombre, string $correo, string $contrasena): ?string {
    if ($nombre === '' || mb_strlen($nombre) > 100) return 'Escribe tu nombre (máximo 100 letras)';
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150) return 'El correo no es válido';
    if (strlen($contrasena) < 8 || strlen($contrasena) > 72) return 'La contraseña debe tener entre 8 y 72 caracteres';
    $sql = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE correo = ?');
    $sql->execute([$correo]);
    if ((int) $sql->fetchColumn() > 0) return 'Ya existe una cuenta con ese correo';
    return null;
}

// Crea la cuenta. La contraseña se guarda cifrada con password_hash, nunca en texto.
// $idColonia (la colonia donde vive) solo se guarda si la base ya tiene esa columna (sql/correo.sql).
function crearUsuario(int $idRol, string $nombre, ?string $apellido, string $correo, string $contrasena,
                      ?string $telefono = null, ?int $idColonia = null): int {
    $sql = db()->prepare(
        "INSERT INTO usuarios (id_rol, nombre, apellido, correo, telefono, contrasena, estado, fecha_registro)
         VALUES (?, ?, ?, ?, ?, ?, 'activo', ?)"
    );
    $sql->execute([$idRol, $nombre, $apellido !== '' ? $apellido : null, $correo,
                   $telefono !== null && $telefono !== '' ? $telefono : null,
                   password_hash($contrasena, PASSWORD_DEFAULT), ahora()]);
    $id = (int) db()->lastInsertId();
    if ($idColonia !== null) {
        $sql = db()->prepare('UPDATE usuarios SET id_colonia = ? WHERE id_usuario = ?');
        $sql->execute([$idColonia, $id]);
    }
    return $id;
}

// ¿El ciudadano puede enviar reportes? Se decide en el .env (CIUDADANO_PUEDE_REPORTAR).
function ciudadanoPuedeReportar(): bool {
    return mb_strtolower(env('CIUDADANO_PUEDE_REPORTAR', 'si')) !== 'no';
}

// ¿Este usuario puede enviar reportes?
function puedeReportar(): bool {
    $u = usuarioActual();
    if ($u === null || !in_array('reportes', $u['permisos'], true)) return false;
    return $u['rol'] !== ROL_CIUDADANO || ciudadanoPuedeReportar();
}

function idDeRol(string $nombre): ?int {
    $sql = db()->prepare('SELECT id_rol FROM roles WHERE nombre = ?');
    $sql->execute([$nombre]);
    $id = $sql->fetchColumn();
    return $id === false ? null : (int) $id;
}
