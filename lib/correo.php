<?php
// Alertas por correo.
//
// Cuándo se manda: en el momento en que se ABRE una alerta de inundación (lib/registro.php),
// una sola vez por alerta. Las simulaciones nunca mandan correos.
// A quién: a los usuarios activos que eligieron esa colonia como "mi colonia" y, si la alerta
// es urgente o de emergencia, también al personal de Protección Civil.
// Cómo: con la librería PHPMailer y la cuenta de correo configurada en el .env (SMTP_...).
//
// Requisitos: 1) importar sql/correo.sql (agrega usuarios.id_colonia) y 2) llenar SMTP_USER y
// SMTP_PASS en el .env. Si falta alguno, no se envía nada y la página lo dice.

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/vendor/phpmailer/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/SMTP.php';

// ¿Ya se importó sql/correo.sql? (se comprueba una vez por petición)
function usuariosTienenColonia(): bool {
    static $tiene = null;
    if ($tiene === null) {
        try { db()->query('SELECT id_colonia FROM usuarios LIMIT 1'); $tiene = true; }
        catch (Throwable $e) { $tiene = false; }
    }
    return $tiene;
}

function correoConfigurado(): bool {
    return env('SMTP_USER') !== '' && env('SMTP_HOST', 'smtp.gmail.com') !== '';
}

// Envía un correo. Los destinatarios van en copia oculta para que nadie vea los correos de los demás.
// Devuelve null si se envió, o el mensaje de error.
function enviarCorreo(array $destinatarios, string $asunto, string $texto): ?string {
    if (!correoConfigurado()) return 'Falta configurar la cuenta de correo en el .env (SMTP_USER y SMTP_PASS)';
    if (!$destinatarios) return null;
    try {
        $correo = new PHPMailer(true);
        $correo->isSMTP();
        $correo->Host = env('SMTP_HOST', 'smtp.gmail.com');
        $correo->Port = (int) env('SMTP_PORT', '587');
        $correo->Timeout = 10;
        $correo->CharSet = 'UTF-8';
        // SMTP_SEGURIDAD=ninguna solo se usa en las pruebas locales; con Gmail siempre va cifrado.
        if (env('SMTP_SEGURIDAD', 'tls') === 'ninguna') {
            $correo->SMTPAutoTLS = false;
        } else {
            $correo->SMTPSecure = $correo->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        }
        if (env('SMTP_PASS') !== '') {
            $correo->SMTPAuth = true;
            $correo->Username = env('SMTP_USER');
            $correo->Password = env('SMTP_PASS');
        }
        $correo->setFrom(env('SMTP_USER'), env('SMTP_NOMBRE', 'Aqua Sentinel'));
        $correo->addAddress(env('SMTP_USER'));               // el correo "para" es la propia cuenta
        foreach (array_unique($destinatarios) as $d) $correo->addBCC($d);
        $correo->Subject = $asunto;
        $correo->Body = $texto;                               // solo texto, sin HTML
        $correo->send();
        return null;
    } catch (Throwable $e) {
        error_log('Correo no enviado: ' . $e->getMessage());
        return 'No se pudo enviar el correo: ' . $e->getMessage();
    }
}

// Correos de los usuarios a los que les toca cada alerta: los que viven en la colonia de la alerta.
// Es un INNER JOIN entre alertas y usuarios por la colonia, así solo se avisa a las colonias en riesgo.
// Con $incluirProteccionCivil también se agrega al personal de Protección Civil.
function destinatariosDeAlertas(array $idsAlertas, bool $incluirProteccionCivil): array {
    if (!usuariosTienenColonia() || !$idsAlertas) return [];
    $marcas = implode(',', array_fill(0, count($idsAlertas), '?'));
    $sql = db()->prepare(
        "SELECT DISTINCT u.correo
         FROM alertas a
         INNER JOIN usuarios u ON u.id_colonia = a.id_colonia
         WHERE a.id_alerta IN ($marcas) AND u.estado = 'activo'"
    );
    $sql->execute(array_values($idsAlertas));
    $correos = $sql->fetchAll(PDO::FETCH_COLUMN);

    if ($incluirProteccionCivil) {
        $sql = db()->query(
            "SELECT u.correo FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.estado = 'activo' AND r.nombre = 'Proteccion Civil'"
        );
        $correos = array_merge($correos, $sql->fetchAll(PDO::FETCH_COLUMN));
    }
    return array_values(array_unique($correos));
}

// Alertas activas que le tocan a un usuario (las de su colonia), también con INNER JOIN.
function alertasDelUsuario(int $idUsuario): array {
    if (!usuariosTienenColonia()) return [];
    $sql = db()->prepare(
        "SELECT a.id_alerta
         FROM alertas a
         INNER JOIN usuarios u ON u.id_colonia = a.id_colonia
         WHERE u.id_usuario = ? AND a.estado = 'activa'"
    );
    $sql->execute([$idUsuario]);
    return array_map('intval', $sql->fetchAll(PDO::FETCH_COLUMN));
}

function coloniaDelUsuario(int $idUsuario): ?int {
    if (!usuariosTienenColonia()) return null;
    $sql = db()->prepare('SELECT id_colonia FROM usuarios WHERE id_usuario = ?');
    $sql->execute([$idUsuario]);
    $valor = $sql->fetchColumn();
    return $valor === null || $valor === false ? null : (int) $valor;
}

const PIE_DE_CORREO = "En una emergencia llama al 911.\n\n"
    . "Recibes este correo porque elegiste tu colonia en Aqua Sentinel o porque eres personal de "
    . "Protección Civil. Para dejar de recibirlos, entra a la plataforma y en Alerta ciudadana elige "
    . "\"Sin elegir\".\nAqua Sentinel es un proyecto estudiantil de hackathon; sigue siempre los avisos oficiales.";

// Avisa por correo de una alerta automática recién abierta. $c es la colonia como la entrega estadoColonias().
function avisarAlertaPorCorreo(array $c, string $titulo, string $nivel, int $idAlerta): void {
    if (!correoConfigurado()) return;
    $destinatarios = destinatariosDeAlertas([$idAlerta], in_array($nivel, ['urgente', 'emergencia'], true));
    if (!$destinatarios) return;

    $lluvia = $c['lluvia_mm_6h'] === null ? 'sin dato' : $c['lluvia_mm_6h'] . ' mm';
    $texto = "$titulo\n"
        . "Nivel de la alerta: $nivel\n"
        . "Hora: " . ahora() . "\n\n"
        . "Puntaje de riesgo: {$c['puntaje']} de 100\n"
        . "Lluvia pronosticada en 6 horas: $lluvia\n"
        . "Reportes de vecinos en las últimas 3 horas: {$c['reportes_recientes']}\n\n"
        . "Qué hacer:\n- " . implode("\n- ", $c['guia']) . "\n\n" . PIE_DE_CORREO;
    enviarCorreo($destinatarios, '[Aqua Sentinel] ' . $titulo, $texto);
}

// Avisa por correo de un aviso que una autoridad mandó a una o varias colonias: UN solo correo
// para todos los vecinos de esas colonias. Devuelve cuántos destinatarios tuvo, o el error.
function avisarAvisoPorCorreo(array $idsAlertas, array $nombresColonias, string $titulo, string $mensaje, string $nivel, string $autor) {
    if (!correoConfigurado()) return 'El servidor no tiene cuenta de correo configurada';
    $destinatarios = destinatariosDeAlertas($idsAlertas, false);
    if (!$destinatarios) return 0;
    $texto = "$titulo\n"
        . "Nivel del aviso: $nivel\n"
        . "Colonias: " . implode(', ', $nombresColonias) . "\n"
        . "Hora: " . ahora() . "\n"
        . "Enviado por: $autor\n\n"
        . "$mensaje\n\n" . PIE_DE_CORREO;
    $error = enviarCorreo($destinatarios, '[Aqua Sentinel] ' . $titulo, $texto);
    return $error ?? count($destinatarios);
}
