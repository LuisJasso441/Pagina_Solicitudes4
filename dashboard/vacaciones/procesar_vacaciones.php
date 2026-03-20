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
        $stmt = $pdo->prepare("
            INSERT INTO notificaciones 
            (tipo, titulo, mensaje, usuario_destino, datos_json, leida, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        
        $datos_json = json_encode([
            'solicitud_id' => $solicitud_id,
            'url' => URL_BASE . 'dashboard/vacaciones/ver_solicitud_vacaciones.php?id=' . $solicitud_id
        ], JSON_UNESCAPED_UNICODE);
        
        $stmt->execute([
            'vacaciones',
            'Vacaciones',
            $mensaje,
            $usuario_id,
            $datos_json
        ]);
    } catch (Exception $e) {
        error_log("Error notificacion vacaciones: " . $e->getMessage());
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
    $firma_empleado_manual = $_POST['firma_empleado_manual'] ?? '';
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
        
        // Notificar al empleado (si tiene cuenta)
        if (!empty($solicitud['usuario_id'])) {
            notificar_vacaciones($pdo, $solicitud['usuario_id'],
                "Tu solicitud ({$solicitud['folio']}) fue aprobada por {$nombre_admin}. Pendiente de GTH.", $solicitud_id);
        }
        
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
// ACCION: CREAR SOLICITUD MANUAL (Admin de Area)
// =====================================================
if ($accion === 'crear_manual') {
    
    // Verificar que sea Admin de Area
    if (empty($_SESSION['es_admin_area'])) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'No tienes permisos para crear solicitudes manuales.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_admin.php');
        exit;
    }
    
    $nombre_manual = trim($_POST['nombre_manual'] ?? '');
    $no_nomina_manual = trim($_POST['no_nomina_manual'] ?? '');
    $departamento_id_manual = intval($_POST['departamento_id_manual'] ?? 0);
    $puesto_manual = trim($_POST['puesto_manual'] ?? '');
    $fecha_ingreso_manual = trim($_POST['fecha_ingreso_manual'] ?? '');
    $periodo_pago_manual = trim($_POST['periodo_pago_manual'] ?? '');
    $empresa_manual = trim($_POST['empresa_manual'] ?? '');
    $dias_ya_tomados = intval($_POST['dias_ya_tomados'] ?? 0);
    
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
    $fecha_regreso = trim($_POST['fecha_regreso'] ?? '');
    $motivo = trim($_POST['motivo'] ?? '');
    $firma_admin = $_POST['firma_admin'] ?? '';
    $firma_empleado_manual = $_POST['firma_empleado_manual'] ?? '';
    
    // Validaciones
    if (empty($nombre_manual)) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'El nombre del empleado es obligatorio.'];
        $_SESSION['form_data_vacaciones_manual'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_manual.php');
        exit;
    }
    
    if (empty($fecha_ingreso_manual) || empty($fecha_inicio) || empty($fecha_fin)) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Las fechas son obligatorias.'];
        $_SESSION['form_data_vacaciones_manual'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_manual.php');
        exit;
    }
    
    if ($departamento_id_manual <= 0) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Debe seleccionar un departamento.'];
        $_SESSION['form_data_vacaciones_manual'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_manual.php');
        exit;
    }
    
    if (empty($firma_admin)) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'La firma del administrador es obligatoria.'];
        $_SESSION['form_data_vacaciones_manual'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_manual.php');
        exit;
    }
    
    // Calcular dias solicitados (L-S)
    $dias_solicitados = 0;
    $d = new DateTime($fecha_inicio);
    $fin_dt = new DateTime($fecha_fin);
    while ($d <= $fin_dt) {
        if ($d->format('N') <= 6) $dias_solicitados++;
        $d->modify('+1 day');
    }
    
    // Calcular dias LFT desde fecha_ingreso_manual
    $fi_dt = new DateTime($fecha_ingreso_manual);
    $hoy_dt = new DateTime();
    $diff_anios = max(1, (int)$fi_dt->diff($hoy_dt)->y);
    $dias_correspondientes = dias_vacaciones_lft($diff_anios);
    $dias_pendientes = max(0, $dias_correspondientes - $dias_ya_tomados);
    $saldo_dias = $dias_pendientes - $dias_solicitados;
    
    $periodo_vacacional = $diff_anios . json_decode('"\u00b0"') . ' a' . json_decode('"\u00f1"') . 'o';
    
    $motivo = mb_substr($motivo, 0, 500);
    
    try {
        $pdo->beginTransaction();
        
        $folio = generar_folio_vacaciones($pdo);
        
        $stmt = $pdo->prepare("
            INSERT INTO solicitudes_vacaciones (
                folio, usuario_id, departamento_id,
                fecha_solicitud, periodo_vacacional, dias_correspondientes, dias_pendientes,
                dias_solicitados, saldo_dias_pendientes,
                fecha_inicio, fecha_fin, fecha_regreso, motivo,
                firma_empleado, fecha_firma_empleado,
                firma_admin, fecha_firma_admin, admin_id,
                estado, fecha_creacion,
                es_manual, admin_creador_id,
                nombre_manual, no_nomina_manual, puesto_manual,
                fecha_ingreso_manual, periodo_pago_manual, empresa_manual
            ) VALUES (?, NULL, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), ?, 'pendiente_gth', NOW(), 1, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $folio, $departamento_id_manual,
            $periodo_vacacional, $dias_correspondientes, $dias_pendientes,
            $dias_solicitados, $saldo_dias,
            $fecha_inicio, $fecha_fin, $fecha_regreso ?: null, $motivo ?: null,
            $firma_empleado_manual,
            $firma_admin, $_SESSION['usuario_id'],
            $_SESSION['usuario_id'],
            $nombre_manual, $no_nomina_manual ?: null, $puesto_manual ?: null,
            $fecha_ingreso_manual, $periodo_pago_manual ?: null, $empresa_manual ?: null
        ]);
        
        $solicitud_id = $pdo->lastInsertId();
        
        // Notificar a GTH (salta aprobacion admin porque el admin ya firmo)
        $stmt_gth = $pdo->prepare("
            SELECT u.id FROM usuarios u
            INNER JOIN departamentos d ON u.departamento_id = d.id
            WHERE d.codigo IN ('gestion_talento', 'contabilidad') AND u.activo = 1
        ");
        $stmt_gth->execute();
        $usuarios_gth = $stmt_gth->fetchAll(PDO::FETCH_COLUMN);
        
        $nombre_admin = $_SESSION['nombre_completo'];
        foreach ($usuarios_gth as $gth_id) {
            notificar_vacaciones($pdo, $gth_id, "{$nombre_admin} ha creado solicitud manual de vacaciones ({$folio}) para {$nombre_manual}. Pendiente de GTH.", $solicitud_id);
        }
        
        $pdo->commit();
        
        $_SESSION['alerta'] = ['tipo' => 'success', 'mensaje' => "Solicitud manual creada exitosamente. Folio: <strong>{$folio}</strong>. Estado: Pendiente de GTH."];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_admin.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error crear solicitud manual vacaciones: " . $e->getMessage());
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Error al crear la solicitud: ' . $e->getMessage()];
        $_SESSION['form_data_vacaciones_manual'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/nueva_solicitud_manual.php');
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
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE solicitudes_vacaciones SET
                estado = 'rechazada_admin', admin_id = ?,
                fecha_aprobacion_admin = NOW(), comentarios_admin = ?, fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['usuario_id'], $comentarios, $solicitud_id]);
        
        if (!empty($solicitud['usuario_id'])) {
            notificar_vacaciones($pdo, $solicitud['usuario_id'],
                "Tu solicitud ({$solicitud['folio']}) fue rechazada por " . $_SESSION['nombre_completo'] . "." . ($comentarios ? " Motivo: {$comentarios}" : ""), $solicitud_id);
        }
        
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
        
        // Notificar al empleado (si tiene cuenta)
        if (!empty($solicitud['usuario_id'])) {
            notificar_vacaciones($pdo, $solicitud['usuario_id'],
                "Tu solicitud ({$solicitud['folio']}) fue aprobada por GTH ({$nombre_gth}). ¡Disfruta tus vacaciones!", $solicitud_id);
        }
        
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
        
        // Notificar al empleado (si tiene cuenta)
        if (!empty($solicitud['usuario_id'])) {
            notificar_vacaciones($pdo, $solicitud['usuario_id'],
                "Tu solicitud ({$solicitud['folio']}) fue rechazada por GTH." . ($comentarios ? " Motivo: {$comentarios}" : ""), $solicitud_id);
        }
        
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