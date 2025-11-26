<?php
/**
 * Procesador: Guardar Apartado 2 (Borrador)
 * Mantenimiento guarda sin enviar al usuario
 * Notifica SOLO la primera vez
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

// Verificar que sea departamento de Mantenimiento
if (($_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento']))) !== 'mantenimiento') {
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

// Validar campos obligatorios mínimos
if (empty($datos['fecha_atencion']) || empty($datos['hora_inicio'])) {
    echo json_encode([
        'success' => false,
        'error' => 'La fecha de atención y hora de inicio son obligatorias'
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
    
    // Verificar que la orden esté en un estado editable
    if (!in_array($orden['estado'], ['pendiente_mantenimiento', 'en_proceso', 'devuelto'])) {
        throw new Exception("Esta orden no puede ser editada en su estado actual");
    }
    
    $pdo->beginTransaction();
    
    // Verificar si es la primera vez que se guarda el Apartado 2
    $es_primera_vez = empty($orden['apartado2_data']);
    
    // Preparar datos del Apartado 2
    $apartado2_data = [
        'fecha_atencion' => $datos['fecha_atencion'],
        'hora_inicio' => $datos['hora_inicio'],
        'fecha_termino' => $datos['fecha_termino'] ?? null,
        'hora_termino' => $datos['hora_termino'] ?? null,
        'descripcion_reparacion' => trim($datos['descripcion_reparacion'] ?? ''),
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
    
    // Preparar SQL dinámico
    $sql = "
        UPDATE ordenes_servicio_mantenimiento 
        SET apartado2_data = :apartado2_data,
            estado = 'en_proceso',
            fecha_ultima_modificacion = NOW(),
            usuario_ultima_modificacion_id = :usuario_id,
            usuario_ultima_modificacion_nombre = :usuario_nombre
    ";
    
    $params = [
        ':apartado2_data' => json_encode($apartado2_data, JSON_UNESCAPED_UNICODE),
        ':usuario_id' => $_SESSION['usuario_id'],
        ':usuario_nombre' => $_SESSION['nombre_completo'],
        ':orden_id' => $orden_id
    ];
    
    // Si es la primera vez, actualizar fecha de primer guardado
    if ($es_primera_vez) {
        $sql .= ", fecha_primer_guardado_mant = NOW()";
    }
    
    $sql .= " WHERE id = :orden_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $pdo->commit();
    
    // Registrar en log
    error_log("Mantenimiento guardó Apartado 2 (borrador) - Orden ID: {$orden_id}, Usuario: {$_SESSION['nombre_completo']}, Primera vez: " . ($es_primera_vez ? 'Sí' : 'No'));
    
    // ✅ NOTIFICAR AL USUARIO CADA VEZ QUE SE GUARDA
    notificar_orden_en_proceso($orden_id, $orden['folio'], $orden['usuario_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Cambios guardados correctamente',
        'es_primera_vez' => $es_primera_vez
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error al guardar Apartado 2: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}