<?php
/**
 * Procesador: Finalizar Orden de Servicio
 * Usuario finaliza la orden después de validar el trabajo de Mantenimiento
 * 
 * NOTIFICACIÓN: Finalizar orden → Mantenimiento
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ordenes_servicio/ordenes_servicio_funciones.php';

// Verificar sesión
if (!sesion_activa()) {
    echo json_encode([
        'success' => false,
        'error' => 'Sesión no válida'
    ]);
    exit;
}

// Obtener datos JSON
$input = file_get_contents('php://input');
$datos = json_decode($input, true);

if (!$datos) {
    echo json_encode([
        'success' => false,
        'error' => 'Datos inválidos'
    ]);
    exit;
}

$orden_id = $datos['orden_id'] ?? null;
$apartado3 = $datos['apartado3'] ?? null;

if (!$orden_id || !$apartado3) {
    echo json_encode([
        'success' => false,
        'error' => 'Datos incompletos'
    ]);
    exit;
}

// Validar campos obligatorios del Apartado 3
if (empty($apartado3['nombre_solicitante']) || empty($apartado3['firma_solicitante'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Debe completar su nombre y firma para finalizar la orden'
    ]);
    exit;
}

if (empty($apartado3['nombre_responsable_mantenimiento']) || empty($apartado3['firma_responsable_mantenimiento'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Faltan datos de Mantenimiento. Ambas firmas son requeridas.'
    ]);
    exit;
}

try {
    $pdo = conectarDB();
    
    // Obtener orden actual
    $orden = obtener_orden_por_id($orden_id);
    
    if (!$orden) {
        throw new Exception("Orden no encontrada");
    }
    
    // Verificar que el usuario sea el propietario
    if ($orden['usuario_id'] != $_SESSION['usuario_id']) {
        throw new Exception("No tienes permiso para finalizar esta orden");
    }
    
    // Verificar que la orden esté en estado pendiente_usuario
    if ($orden['estado'] !== 'pendiente_usuario') {
        throw new Exception("Solo se pueden finalizar órdenes que están pendientes de validación");
    }
    
    $pdo->beginTransaction();
    
    // Preparar datos completos del Apartado 3
    $apartado3_data = [
        'nombre_solicitante' => trim($apartado3['nombre_solicitante']),
        'firma_solicitante' => $apartado3['firma_solicitante'],
        'fecha_firma_solicitante' => date('Y-m-d H:i:s'),
        'nombre_responsable_mantenimiento' => trim($apartado3['nombre_responsable_mantenimiento']),
        'firma_responsable_mantenimiento' => $apartado3['firma_responsable_mantenimiento'],
        'fecha_firma_mantenimiento' => $apartado3['fecha_firma_mantenimiento'] ?? date('Y-m-d H:i:s'),
        'comentarios' => $apartado3['comentarios'] ?? null
    ];
    
    // Mantener historial de devoluciones si existe
    if (!empty($orden['apartado3']['historial_devoluciones'])) {
        $apartado3_data['historial_devoluciones'] = $orden['apartado3']['historial_devoluciones'];
    }
    
    // Actualizar orden - cambiar estado a completado
    $stmt = $pdo->prepare("
        UPDATE ordenes_servicio_mantenimiento 
        SET apartado3_data = :apartado3_data,
            estado = 'completado',
            fecha_completado = NOW(),
            fecha_ultima_modificacion = NOW(),
            usuario_ultima_modificacion_id = :usuario_id,
            usuario_ultima_modificacion_nombre = :usuario_nombre
        WHERE id = :orden_id
    ");
    
    $stmt->execute([
        ':apartado3_data' => json_encode($apartado3_data, JSON_UNESCAPED_UNICODE),
        ':usuario_id' => $_SESSION['usuario_id'],
        ':usuario_nombre' => $_SESSION['nombre_completo'],
        ':orden_id' => $orden_id
    ]);
    
    // ========================================
    // NOTIFICACIÓN: Finalizar orden → Mantenimiento
    // ========================================
    $usuarios_mantenimiento = obtener_usuarios_mantenimiento();
    
    foreach ($usuarios_mantenimiento as $usuario_mant_id) {
        $stmt_notif = $pdo->prepare("
            INSERT INTO notificaciones 
            (tipo, titulo, mensaje, usuario_destino, datos_json, leida, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        
        $datos_json = json_encode([
            'orden_id' => $orden_id,
            'folio' => $orden['folio'],
            'url' => URL_BASE . 'dashboard/ver_orden_servicio.php?id=' . $orden_id
        ]);
        
        $stmt_notif->execute([
            'orden_completada',
            '🎉 Orden Completada',
            "{$_SESSION['nombre_completo']} ha finalizado la orden {$orden['folio']}",
            $usuario_mant_id,
            $datos_json
        ]);
    }
    
    $pdo->commit();
    
    error_log("Orden finalizada - Orden ID: {$orden_id}, Folio: {$orden['folio']}, Usuario: {$_SESSION['nombre_completo']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Orden finalizada exitosamente',
        'nuevo_estado' => 'completado',
        'orden_id' => $orden_id,
        'folio' => $orden['folio']
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error al finalizar orden: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}