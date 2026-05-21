<?php
/**
 * Procesar Firma del Usuario (Segundo Cierre)
 * dashboard/sistemas/ti_sistemas/mantenimientos/procesar_firmar_mantenimiento.php
 *
 * Recibe la firma del usuario destinatario sobre una solicitud en estado
 * 'finalizada' y la cierra (estado: 'cerrada').
 *
 * Solo el usuario destinatario (sm.usuario_id) puede firmar.
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

$pdo = conectarDB();

$solicitud_id        = intval($_POST['solicitud_id'] ?? 0);
$firma_data          = trim($_POST['firma_usuario'] ?? '');
$nombre_firma        = trim($_POST['nombre_firma'] ?? '');
$comentario_firma    = trim($_POST['comentario_firma'] ?? '');

// ---------- Validaciones basicas ----------
if (!$solicitud_id) {
    establecer_alerta('error', 'ID de solicitud no valido.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

if (empty($firma_data)) {
    establecer_alerta('error', 'La firma es obligatoria. Por favor, dibuje su firma antes de confirmar.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
    exit;
}

// Validar formato de firma (base64 PNG)
if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $firma_data)) {
    establecer_alerta('error', 'Formato de firma invalido.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
    exit;
}

if (empty($nombre_firma) || strlen($nombre_firma) < 3) {
    establecer_alerta('error', 'Debe escribir su nombre completo (mínimo 3 caracteres).');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
    exit;
}

if (strlen($nombre_firma) > 150) {
    establecer_alerta('error', 'El nombre es demasiado largo.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
    exit;
}

if (strlen($comentario_firma) > 1000) {
    establecer_alerta('error', 'El comentario es demasiado largo (máx. 1000 caracteres).');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
    exit;
}

// ---------- Cargar y validar la solicitud ----------
try {
    $sql = "SELECT sm.*, u.nombre_completo AS solicitante_nombre
            FROM solicitudes_mantenimiento_ti sm
            LEFT JOIN usuarios u ON sm.usuario_id = u.id
            WHERE sm.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$solicitud_id]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        establecer_alerta('error', 'Solicitud no encontrada.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
        exit;
    }

    // Validar permisos: solo el usuario destinatario puede firmar
    if ($solicitud['usuario_id'] != $_SESSION['usuario_id']) {
        establecer_alerta('error', 'Solo el usuario destinatario de la solicitud puede firmarla.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
    }

    // Validar estado: solo se puede firmar una solicitud finalizada
    if ($solicitud['estado'] !== 'finalizada') {
        establecer_alerta('error', 'Solo se pueden firmar solicitudes en estado "Finalizada". Estado actual: ' . $solicitud['estado']);
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
    }

    // Si ya fue firmada antes (defensa contra doble click)
    if (!empty($solicitud['firma_usuario'])) {
        establecer_alerta('warning', 'Esta solicitud ya fue firmada anteriormente.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
    }

    // ---------- Procesar ----------
    $pdo->beginTransaction();

    // Actualizar solicitud: guardar firma y cerrar
    $sql_update = "UPDATE solicitudes_mantenimiento_ti SET
                    firma_usuario        = :firma,
                    nombre_firma_usuario = :nombre,
                    fecha_firma_usuario  = NOW(),
                    estado               = 'cerrada'
                   WHERE id = :id";
    $stmt = $pdo->prepare($sql_update);
    $stmt->execute([
        ':firma'  => $firma_data,
        ':nombre' => $nombre_firma,
        ':id'     => $solicitud_id,
    ]);

    // Historial
    $comentario_hist = "Usuario firmó la conformidad. Solicitud cerrada.";
    if ($comentario_firma) {
        $comentario_hist .= "\nComentario del usuario: " . $comentario_firma;
    }

    $sql_hist = "INSERT INTO historial_mantenimientos_ti
                 (solicitud_id, estado_anterior, estado_nuevo, comentario, usuario_id, fecha_cambio)
                 VALUES (?, 'finalizada', 'cerrada', ?, ?, NOW())";
    $stmt = $pdo->prepare($sql_hist);
    $stmt->execute([$solicitud_id, $comentario_hist, $_SESSION['usuario_id']]);

    // Notificar a Sistemas (al que atendió + a todo el departamento Sistemas)
    notificarFirmaUsuario($pdo, $solicitud, $_SESSION['nombre_completo']);

    $pdo->commit();

    establecer_alerta('success', "Firma registrada. La solicitud {$solicitud['folio']} ha sido cerrada correctamente.");
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al firmar mantenimiento: " . $e->getMessage());
    establecer_alerta('error', 'Error al guardar la firma. Por favor, intente nuevamente.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
    exit;
}

// =====================================================================
// NOTIFICACIONES
// =====================================================================

/**
 * Notifica a Sistemas que el usuario firmo y la solicitud quedo cerrada
 */
function notificarFirmaUsuario($pdo, $solicitud, $usuario_nombre) {
    $titulo  = '✍️ Solicitud Firmada y Cerrada';
    $mensaje = "{$usuario_nombre} firmó la solicitud {$solicitud['folio']}. La solicitud está cerrada.";

    $datos = json_encode([
        'solicitud_id' => $solicitud['id'],
        'folio'        => $solicitud['folio'],
        'estado'       => 'cerrada',
        'url'          => URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud['id'],
    ]);

    $sql_notif = "INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, fecha_creacion)
                  VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql_notif);

    $usuarios_notificados = [];

    // 1) Notificar a quien atendio la solicitud
    if (!empty($solicitud['atendido_por'])) {
        $stmt->execute(['mantenimiento_firmado', $titulo, $mensaje, $solicitud['atendido_por'], $datos]);
        $usuarios_notificados[] = $solicitud['atendido_por'];
    }

    // 2) Notificar a todos los usuarios activos de Sistemas (excepto los ya notificados)
    $sql_sistemas = "SELECT u.id FROM usuarios u
                     INNER JOIN departamentos d ON u.departamento_id = d.id
                     WHERE LOWER(d.codigo) IN ('ti', 'sistemas', 'ti_sistemas')
                       AND u.activo = 1";
    $usuarios_sistemas = $pdo->query($sql_sistemas)->fetchAll(PDO::FETCH_COLUMN);

    foreach ($usuarios_sistemas as $uid) {
        if (!in_array($uid, $usuarios_notificados)) {
            $stmt->execute(['mantenimiento_firmado', $titulo, $mensaje, $uid, $datos]);
            $usuarios_notificados[] = $uid;
        }
    }
}