<?php
/**
 * Procesador: Enviar Apartado 2 al Usuario
 * Mantenimiento envía la orden completa al usuario para validación
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ordenes_servicio_funciones.php';

// Verificar sesión
if (!sesion_activa()) {
    echo json_encode([
        'success' => false,
        'error' => 'Sesión no válida'
    ]);
    exit;
}

// Verificar que sea departamento de Mantenimiento
if (strtolower($_SESSION['departamento']) !== 'mantenimiento') {
    echo json_encode([
        'success' => false,
        'error' => 'Solo el departamento de Mantenimiento puede realizar esta acción'
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

if (!$orden_id) {
    echo json_encode([
        'success' => false,
        'error' => 'ID de orden no proporcionado'
    ]);
    exit;
}

// Validar campos obligatorios
$campos_requeridos = ['fecha_atencion', 'hora_inicio', 'descripcion_reparacion'];
foreach ($campos_requeridos as $campo) {
    if (empty($datos[$campo])) {
        echo json_encode([
            'success' => false,
            'error' => "El campo {$campo} es obligatorio para enviar la orden"
        ]);
        exit;
    }
}

try {
    $pdo = conectarDB();
    
    // Obtener orden actual
    $orden = obtener_orden_por_id($orden_id);
    
    if (!$orden) {
        throw new Exception("Orden no encontrada");
    }
    
    // Verificar que la orden esté en un estado editable
    if (!in_array($orden['estado'], ['pendiente_mantenimiento', 'en_proceso', 'devuelto'])) {
        throw new Exception("Esta orden no puede ser enviada en su estado actual");
    }
    
    $pdo->beginTransaction();
    
    // Preparar datos completos del Apartado 2
    $apartado2_data = [
        'fecha_atencion' => $datos['fecha_atencion'],
        'hora_inicio' => $datos['hora_inicio'],
        'fecha_termino' => $datos['fecha_termino'] ?? null,
        'hora_termino' => $datos['hora_termino'] ?? null,
        'descripcion_reparacion' => trim($datos['descripcion_reparacion']),
        'personal_asignado' => []
    ];
    
    // Procesar personal asignado
    if (!empty($datos['personal_asignado'])) {
        foreach ($datos['personal_asignado'] as $persona) {
            if (!empty($persona['nombre'])) {
                $apartado2_data['personal_asignado'][] = [
                    'nombre' => trim($persona['nombre']),
                    'firma' => $persona['firma'] ?? ''
                ];
            }
        }
    }
    
    // Validar que haya al menos descripción de reparación
    if (empty($apartado2_data['descripcion_reparacion'])) {
        throw new Exception("La descripción de reparación es obligatoria");
    }
    
    // Actualizar orden - cambiar estado a pendiente_usuario
    $stmt = $pdo->prepare("
        UPDATE ordenes_servicio_mantenimiento 
        SET apartado2_data = :apartado2_data,
            estado = 'pendiente_usuario',
            fecha_enviado_usuario = NOW(),
            fecha_ultima_modificacion = NOW(),
            usuario_ultima_modificacion_id = :usuario_id,
            usuario_ultima_modificacion_nombre = :usuario_nombre
        WHERE id = :orden_id
    ");
    
    $stmt->execute([
        ':apartado2_data' => json_encode($apartado2_data, JSON_UNESCAPED_UNICODE),
        ':usuario_id' => $_SESSION['usuario_id'],
        ':usuario_nombre' => $_SESSION['nombre_completo'],
        ':orden_id' => $orden_id
    ]);
    
    $pdo->commit();
    
    // Registrar en log
    error_log("Mantenimiento envió Apartado 2 al usuario - Orden ID: {$orden_id}, Folio: {$orden['folio']}, Usuario Mant: {$_SESSION['nombre_completo']}");
    
    // Notificar al usuario que la orden está lista
    if (function_exists('notificar_orden_finalizada_mantenimiento')) {
        notificar_orden_finalizada_mantenimiento($orden_id, $orden['folio'], $orden['usuario_id']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Orden enviada al usuario para validación',
        'nuevo_estado' => 'pendiente_usuario'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error al enviar Apartado 2: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}