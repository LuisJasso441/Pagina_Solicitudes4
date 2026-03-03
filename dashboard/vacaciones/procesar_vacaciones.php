<?php
/**
 * Procesador de Solicitudes de Vacaciones
 * dashboard/vacaciones/procesar_vacaciones.php
 * 
 * Acciones:
 *   - crear            (Empleado)
 *   - cancelar          (Empleado)
 *   - aprobar_admin     (Admin de Área)
 *   - rechazar_admin    (Admin de Área)
 *   - aprobar_gth       (GTH / Contabilidad)
 *   - rechazar_gth      (GTH / Contabilidad)
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/vacaciones/vacaciones_funciones.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
    exit;
}

$accion = $_POST['accion'] ?? '';
$pdo = conectarDB();

// Helper: verificar si es GTH/Contabilidad
function es_usuario_gth_proc() {
    $depto = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
    return in_array($depto, ['gth', 'gestion_talento', 'contabilidad']);
}

// Helper: insertar notificación (silencioso si falla)
function notificar_vacaciones($pdo, $usuario_id, $mensaje, $solicitud_id) {
    try {
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'notificaciones'");
        if ($stmt_check->rowCount() > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO notificaciones (usuario_id, tipo, mensaje, url, leida, fecha_creacion)
                VALUES (?, 'vacaciones', ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $usuario_id,
                $mensaje,
                "dashboard/vacaciones/ver_solicitud_vacaciones.php?id={$solicitud_id}"
            ]);
        }
    } catch (Exception $e) {
        error_log("Error notificación vacaciones: " . $e->getMessage());
    }
}


// =====================================================
// ACCIÓN: CREAR SOLICITUD (Empleado)
// =====================================================
if ($accion === 'crear') {
    
    $fecha_inicio         = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin            = trim($_POST['fecha_fin'] ?? '');
    $fecha_regreso        = trim($_POST['fecha_regreso'] ?? '');
    $motivo               = trim($_POST['motivo'] ?? '');
    $firma_empleado       = $_POST['firma_empleado'] ?? '';
    $periodo_vacacional   = trim($_POST['periodo_vacacional'] ?? '');
    $dias_correspondientes_post = intval($_POST['dias_correspondientes'] ?? 0);
    $dias_pendientes_post = intval($_POST['dias_pendientes'] ?? 0);
    
    $_SESSION['form_data_vacaciones'] = [
        'fecha_inicio' => $fecha_inicio,
        'fecha_fin'    => $fecha_fin,
        'motivo'       => $motivo,
    ];
    
    // Validaciones
    if (empty($fecha_inicio) || empty($fecha_fin)) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Las fechas de inicio y fin son obligatorias.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_vacaciones.php');
        exit;
    }
    
    if ($fecha_inicio < date('Y-m-d')) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'La fecha de inicio no puede ser anterior a hoy.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_vacaciones.php');
        exit;
    }
    
    if ($fecha_fin < $fecha_inicio) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_vacaciones.php');
        exit;
    }
    
    $dias_solicitados = contar_dias_habiles($fecha_inicio, $fecha_fin);
    
    if ($dias_solicitados <= 0) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'El rango seleccionado no contiene días hábiles (L-S).'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_vacaciones.php');
        exit;
    }
    
    $resumen = obtener_resumen_vacaciones($pdo, $_SESSION['usuario_id']);
    
    if (!$resumen['tiene_fecha_ingreso']) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'No tienes fecha de ingreso registrada.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
        exit;
    }
    
    if ($dias_solicitados > $resumen['dias_disponibles']) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => "Solicitas $dias_solicitados días pero solo tienes {$resumen['dias_disponibles']} disponibles."];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_vacaciones.php');
        exit;
    }
    
    if (empty($firma_empleado)) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'La firma del empleado es obligatoria.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_vacaciones.php');
        exit;
    }
    
    // Verificar traslape
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM solicitudes_vacaciones 
        WHERE usuario_id = ? 
        AND estado NOT IN ('cancelada', 'rechazada_admin', 'rechazada_gth')
        AND ((fecha_inicio <= ? AND fecha_fin >= ?) OR (fecha_inicio <= ? AND fecha_fin >= ?) OR (fecha_inicio >= ? AND fecha_fin <= ?))
    ");
    $stmt->execute([$_SESSION['usuario_id'], $fecha_fin, $fecha_inicio, $fecha_inicio, $fecha_inicio, $fecha_inicio, $fecha_fin]);
    
    if ((int)$stmt->fetchColumn() > 0) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Ya tienes una solicitud activa que se traslapa con las fechas seleccionadas.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_vacaciones.php');
        exit;
    }
    
    $motivo = mb_substr($motivo, 0, 500);
    
    try {
        $pdo->beginTransaction();
        
        $folio = generar_folio_vacaciones($pdo);
        $departamento_id = $_SESSION['departamento_id'] ?? null;
        
        if (empty($departamento_id)) {
            $stmt = $pdo->prepare("SELECT departamento_id FROM usuarios WHERE id = ?");
            $stmt->execute([$_SESSION['usuario_id']]);
            $departamento_id = $stmt->fetchColumn();
        }
        
        $saldo_dias = $dias_pendientes_post - $dias_solicitados;
        
        $stmt = $pdo->prepare("
            INSERT INTO solicitudes_vacaciones (
                folio, usuario_id, departamento_id,
                fecha_solicitud, periodo_vacacional, dias_correspondientes, dias_pendientes,
                dias_solicitados, saldo_dias_pendientes,
                fecha_inicio, fecha_fin, fecha_regreso, motivo,
                firma_empleado, fecha_firma_empleado, estado, fecha_creacion
            ) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pendiente_admin', NOW())
        ");
        $stmt->execute([
            $folio, $_SESSION['usuario_id'], $departamento_id,
            $periodo_vacacional, $dias_correspondientes_post, $dias_pendientes_post,
            $dias_solicitados, $saldo_dias,
            $fecha_inicio, $fecha_fin, $fecha_regreso ?: null, $motivo ?: null,
            $firma_empleado
        ]);
        
        $solicitud_id = $pdo->lastInsertId();
        
        // Notificar Admin(s) de Área
        $admins = obtener_admins_area($pdo, $departamento_id);
        $nombre_empleado = $_SESSION['nombre_completo'];
        foreach ($admins as $admin) {
            notificar_vacaciones($pdo, $admin['id'], "{$nombre_empleado} ha solicitado vacaciones ({$folio})", $solicitud_id);
        }
        
        $pdo->commit();
        unset($_SESSION['form_data_vacaciones']);
        
        $_SESSION['alerta'] = ['tipo' => 'success', 'mensaje' => "Solicitud <strong>{$folio}</strong> creada exitosamente. Se ha enviado para aprobación del Admin. de Área."];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error crear solicitud vacaciones: " . $e->getMessage());
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Error al crear la solicitud. Inténtalo de nuevo.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_vacaciones.php');
        exit;
    }
}

// =====================================================
// ACCIÓN: CANCELAR SOLICITUD (Empleado)
// =====================================================
elseif ($accion === 'cancelar') {
    
    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    
    if ($solicitud_id <= 0) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'ID de solicitud inválido.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
        exit;
    }
    
    $solicitud = obtener_solicitud_vacaciones($pdo, $solicitud_id);
    
    if (!$solicitud || (int)$solicitud['usuario_id'] !== (int)$_SESSION['usuario_id']) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'No tienes permiso para cancelar esta solicitud.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
        exit;
    }
    
    if ($solicitud['estado'] !== 'pendiente_admin') {
        $_SESSION['alerta'] = ['tipo' => 'warning', 'mensaje' => 'Solo se pueden cancelar solicitudes pendientes de aprobación del Admin. de Área.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE solicitudes_vacaciones SET estado = 'cancelada', fecha_actualizacion = NOW() WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$solicitud_id, $_SESSION['usuario_id']]);
        $_SESSION['alerta'] = ['tipo' => 'success', 'mensaje' => "Solicitud <strong>{$solicitud['folio']}</strong> cancelada correctamente."];
    } catch (Exception $e) {
        error_log("Error cancelar solicitud vacaciones: " . $e->getMessage());
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Error al cancelar la solicitud.'];
    }
    
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
    exit;
}

// =====================================================
// ACCIÓN: APROBAR (Admin de Área)
// =====================================================
elseif ($accion === 'aprobar_admin') {
    
    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $firma_admin  = $_POST['firma_admin'] ?? '';
    $comentarios  = mb_substr(trim($_POST['comentarios_admin'] ?? ''), 0, 500);
    
    if (empty($_SESSION['es_admin_area'])) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'No tienes permisos de Admin de Área.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
        exit;
    }
    
    $solicitud = obtener_solicitud_vacaciones($pdo, $solicitud_id);
    
    if (!$solicitud || (int)$_SESSION['departamento_id'] !== (int)$solicitud['departamento_id']) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Solicitud no encontrada o no pertenece a tu departamento.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_admin.php');
        exit;
    }
    
    if ($solicitud['estado'] !== 'pendiente_admin') {
        $_SESSION['alerta'] = ['tipo' => 'warning', 'mensaje' => 'Esta solicitud ya no está pendiente de tu aprobación.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
    
    if (empty($firma_admin)) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'La firma es obligatoria para aprobar.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE solicitudes_vacaciones SET
                estado = 'pendiente_gth', admin_id = ?, firma_admin = ?,
                fecha_aprobacion_admin = NOW(), comentarios_admin = ?, fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['usuario_id'], $firma_admin, $comentarios ?: null, $solicitud_id]);
        
        $nombre_admin = $_SESSION['nombre_completo'];
        
        // Notificar al empleado
        notificar_vacaciones($pdo, $solicitud['usuario_id'],
            "Tu solicitud ({$solicitud['folio']}) fue aprobada por {$nombre_admin}. Pendiente de GTH.", $solicitud_id);
        
        // Notificar a GTH
        $stmt_gth = $pdo->prepare("
            SELECT u.id FROM usuarios u LEFT JOIN departamentos d ON u.departamento_id = d.id
            WHERE LOWER(d.codigo) IN ('gth', 'gestion_talento', 'contabilidad') AND u.activo = 1
        ");
        $stmt_gth->execute();
        foreach ($stmt_gth->fetchAll(PDO::FETCH_COLUMN) as $gth_id) {
            notificar_vacaciones($pdo, $gth_id,
                "Solicitud ({$solicitud['folio']}) aprobada por Admin, pendiente de GTH.", $solicitud_id);
        }
        
        $pdo->commit();
        
        $_SESSION['alerta'] = ['tipo' => 'success', 'mensaje' => "Solicitud <strong>{$solicitud['folio']}</strong> aprobada. Enviada a GTH para aprobación final."];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_admin.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error aprobar_admin vacaciones: " . $e->getMessage());
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Error al aprobar la solicitud.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
}

// =====================================================
// ACCIÓN: RECHAZAR (Admin de Área)
// =====================================================
elseif ($accion === 'rechazar_admin') {
    
    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $comentarios  = mb_substr(trim($_POST['comentarios_admin'] ?? ''), 0, 500);
    
    if (empty($_SESSION['es_admin_area'])) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'No tienes permisos de Admin de Área.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
        exit;
    }
    
    $solicitud = obtener_solicitud_vacaciones($pdo, $solicitud_id);
    
    if (!$solicitud || (int)$_SESSION['departamento_id'] !== (int)$solicitud['departamento_id']) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Solicitud no encontrada o no pertenece a tu departamento.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_admin.php');
        exit;
    }
    
    if ($solicitud['estado'] !== 'pendiente_admin') {
        $_SESSION['alerta'] = ['tipo' => 'warning', 'mensaje' => 'Esta solicitud ya no está pendiente de tu aprobación.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
    
    if (empty($comentarios)) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Debe indicar un motivo de rechazo.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE solicitudes_vacaciones SET
                estado = 'rechazada_admin', admin_id = ?,
                fecha_aprobacion_admin = NOW(), comentarios_admin = ?, fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['usuario_id'], $comentarios, $solicitud_id]);
        
        notificar_vacaciones($pdo, $solicitud['usuario_id'],
            "Tu solicitud ({$solicitud['folio']}) fue rechazada por " . $_SESSION['nombre_completo'] . ".", $solicitud_id);
        
        $pdo->commit();
        
        $_SESSION['alerta'] = ['tipo' => 'success', 'mensaje' => "Solicitud <strong>{$solicitud['folio']}</strong> rechazada. El empleado ha sido notificado."];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_admin.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error rechazar_admin vacaciones: " . $e->getMessage());
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Error al rechazar la solicitud.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
}

// =====================================================
// ACCIÓN: APROBAR (GTH)
// =====================================================
elseif ($accion === 'aprobar_gth') {
    
    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $firma_gth    = $_POST['firma_gth'] ?? '';
    $comentarios  = mb_substr(trim($_POST['comentarios_gth'] ?? ''), 0, 500);
    
    if (!es_usuario_gth_proc()) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'No tienes permisos de GTH.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
        exit;
    }
    
    $solicitud = obtener_solicitud_vacaciones($pdo, $solicitud_id);
    
    if (!$solicitud) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Solicitud no encontrada.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_gth.php');
        exit;
    }
    
    if ($solicitud['estado'] !== 'pendiente_gth') {
        $_SESSION['alerta'] = ['tipo' => 'warning', 'mensaje' => 'Esta solicitud no está pendiente de aprobación GTH.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
    
    if (empty($firma_gth)) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'La firma es obligatoria para aprobar.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE solicitudes_vacaciones SET
                estado = 'completada', gth_id = ?, firma_gth = ?,
                fecha_aprobacion_gth = NOW(), comentarios_gth = ?, fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['usuario_id'], $firma_gth, $comentarios ?: null, $solicitud_id]);
        
        $nombre_gth = $_SESSION['nombre_completo'];
        
        // Notificar al empleado
        notificar_vacaciones($pdo, $solicitud['usuario_id'],
            "Tu solicitud ({$solicitud['folio']}) fue aprobada por GTH ({$nombre_gth}). ¡Disfruta tus vacaciones!", $solicitud_id);
        
        // Notificar al Admin que aprobó
        if (!empty($solicitud['admin_id'])) {
            notificar_vacaciones($pdo, $solicitud['admin_id'],
                "Solicitud ({$solicitud['folio']}) completada. GTH aprobó.", $solicitud_id);
        }
        
        $pdo->commit();
        
        $_SESSION['alerta'] = ['tipo' => 'success', 'mensaje' => "Solicitud <strong>{$solicitud['folio']}</strong> aprobada y completada. El empleado ha sido notificado."];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_gth.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error aprobar_gth vacaciones: " . $e->getMessage());
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Error al aprobar la solicitud.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
}

// =====================================================
// ACCIÓN: RECHAZAR (GTH)
// =====================================================
elseif ($accion === 'rechazar_gth') {
    
    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $comentarios  = mb_substr(trim($_POST['comentarios_gth'] ?? ''), 0, 500);
    
    if (!es_usuario_gth_proc()) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'No tienes permisos de GTH.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
        exit;
    }
    
    $solicitud = obtener_solicitud_vacaciones($pdo, $solicitud_id);
    
    if (!$solicitud) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Solicitud no encontrada.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_gth.php');
        exit;
    }
    
    if ($solicitud['estado'] !== 'pendiente_gth') {
        $_SESSION['alerta'] = ['tipo' => 'warning', 'mensaje' => 'Esta solicitud no está pendiente de aprobación GTH.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
    
    if (empty($comentarios)) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Debe indicar un motivo de rechazo.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE solicitudes_vacaciones SET
                estado = 'rechazada_gth', gth_id = ?,
                fecha_aprobacion_gth = NOW(), comentarios_gth = ?, fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['usuario_id'], $comentarios, $solicitud_id]);
        
        $nombre_gth = $_SESSION['nombre_completo'];
        
        // Notificar al empleado
        notificar_vacaciones($pdo, $solicitud['usuario_id'],
            "Tu solicitud ({$solicitud['folio']}) fue rechazada por GTH ({$nombre_gth}).", $solicitud_id);
        
        // Notificar al Admin que aprobó
        if (!empty($solicitud['admin_id'])) {
            notificar_vacaciones($pdo, $solicitud['admin_id'],
                "Solicitud ({$solicitud['folio']}) rechazada por GTH.", $solicitud_id);
        }
        
        $pdo->commit();
        
        $_SESSION['alerta'] = ['tipo' => 'success', 'mensaje' => "Solicitud <strong>{$solicitud['folio']}</strong> rechazada por GTH. El empleado y Admin han sido notificados."];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_gth.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error rechazar_gth vacaciones: " . $e->getMessage());
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Error al rechazar la solicitud.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id);
        exit;
    }
}

// =====================================================
// ACCIÓN NO RECONOCIDA
// =====================================================
else {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Acción no reconocida.'];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
    exit;
}