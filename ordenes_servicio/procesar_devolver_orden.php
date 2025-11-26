<?php
/**
 * Procesador: Devolver Orden para Corrección
 * Usuario devuelve la orden a Mantenimiento para que corrijan algo
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ordenes_servicio_funciones.php';
require_once __DIR__ . '/../includes/notificaciones.php'; // ⭐ AGREGADO: Sistema de notificaciones

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
$motivo_devolucion = $datos['motivo'] ?? null;

if (!$orden_id) {
    echo json_encode([
        'success' => false,
        'error' => 'ID de orden no proporcionado'
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
        throw new Exception("No tienes permiso para devolver esta orden");
    }
    
    // Verificar que la orden esté en estado pendiente_usuario
    if ($orden['estado'] !== 'pendiente_usuario') {
        throw new Exception("Solo se pueden devolver órdenes que están pendientes de validación");
    }
    
    $pdo->beginTransaction();
    
    // Preparar datos adicionales si hay motivo
    $datos_devolucion = [];
    if ($motivo_devolucion) {
        $datos_devolucion = [
            'motivo_devolucion' => trim($motivo_devolucion),
            'fecha_devolucion' => date('Y-m-d H:i:s'),
            'devuelto_por' => $_SESSION['nombre_completo']
        ];
    }
    
    // Actualizar orden - cambiar estado a devuelto
    $stmt = $pdo->prepare("
        UPDATE ordenes_servicio_mantenimiento 
        SET estado = 'devuelto',
            fecha_ultima_modificacion = NOW(),
            usuario_ultima_modificacion_id = :usuario_id,
            usuario_ultima_modificacion_nombre = :usuario_nombre
        WHERE id = :orden_id
    ");
    
    $stmt->execute([
        ':usuario_id' => $_SESSION['usuario_id'],
        ':usuario_nombre' => $_SESSION['nombre_completo'],
        ':orden_id' => $orden_id
    ]);
    
    // Si hay motivo, guardarlo en apartado3 temporal
    if (!empty($datos_devolucion)) {
        $apartado3 = $orden['apartado3'] ?? [];
        $apartado3['historial_devoluciones'] = $apartado3['historial_devoluciones'] ?? [];
        $apartado3['historial_devoluciones'][] = $datos_devolucion;
        
        $stmt = $pdo->prepare("
            UPDATE ordenes_servicio_mantenimiento 
            SET apartado3_data = :apartado3_data
            WHERE id = :orden_id
        ");
        
        $stmt->execute([
            ':apartado3_data' => json_encode($apartado3, JSON_UNESCAPED_UNICODE),
            ':orden_id' => $orden_id
        ]);
    }
    
    $pdo->commit();
    
    // Registrar en log
    error_log("Usuario devolvió orden para corrección - Orden ID: {$orden_id}, Folio: {$orden['folio']}, Usuario: {$_SESSION['nombre_completo']}");
    
    // ✅ NOTIFICAR A MANTENIMIENTO
    notificar_orden_devuelta($orden_id, $orden['folio'], $_SESSION['nombre_completo']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Orden devuelta a Mantenimiento para corrección',
        'nuevo_estado' => 'devuelto'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error al devolver orden: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}