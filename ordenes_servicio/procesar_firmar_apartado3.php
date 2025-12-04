<?php
/**
 * Procesador: Guardar Firma en Apartado 3
 * Guarda firma del solicitante o del responsable de mantenimiento
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
$tipo_firma = $datos['tipo_firma'] ?? null; // 'solicitante' o 'mantenimiento'
$nombre = $datos['nombre'] ?? null;
$firma = $datos['firma'] ?? null;

// Validar datos
if (!$orden_id || !$tipo_firma || !$nombre || !$firma) {
    echo json_encode([
        'success' => false,
        'error' => 'Datos incompletos'
    ]);
    exit;
}

// Validar tipo de firma
if (!in_array($tipo_firma, ['solicitante', 'mantenimiento'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Tipo de firma inválido'
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
    
    // Verificar que la orden esté en estado pendiente_usuario
    if ($orden['estado'] !== 'pendiente_usuario') {
        throw new Exception("Solo se pueden firmar órdenes que están pendientes de validación");
    }
    
    // Verificar permisos según tipo de firma
    if ($tipo_firma === 'solicitante') {
        // Solo el propietario puede firmar como solicitante
        if ($orden['usuario_id'] != $_SESSION['usuario_id']) {
            throw new Exception("No tienes permiso para firmar como solicitante");
        }
    } elseif ($tipo_firma === 'mantenimiento') {
        // Solo usuarios de Mantenimiento pueden firmar
        if (($_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento']))) !== 'mantenimiento') {
            throw new Exception("No tienes permiso para firmar como responsable de mantenimiento");
        }
    }
    
    // Validar firma (debe ser base64)
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $firma)) {
        throw new Exception("Formato de firma inválido");
    }
    
    $pdo->beginTransaction();
    
    // Obtener Apartado 3 actual o crear nuevo
    $apartado3 = $orden['apartado3'] ?? [];
    
    // Actualizar firma correspondiente
    if ($tipo_firma === 'solicitante') {
        $apartado3['nombre_solicitante'] = trim($nombre);
        $apartado3['firma_solicitante'] = $firma;
        $apartado3['fecha_firma_solicitante'] = date('Y-m-d H:i:s');
        $apartado3['usuario_firma_solicitante'] = $_SESSION['nombre_completo'];
    } elseif ($tipo_firma === 'mantenimiento') {
        $apartado3['nombre_responsable_mantenimiento'] = trim($nombre);
        $apartado3['firma_responsable_mantenimiento'] = $firma;
        $apartado3['fecha_firma_mantenimiento'] = date('Y-m-d H:i:s');
        $apartado3['usuario_firma_mantenimiento'] = $_SESSION['nombre_completo'];
    }
    
    // Actualizar orden
    $stmt = $pdo->prepare("
        UPDATE ordenes_servicio_mantenimiento 
        SET apartado3_data = :apartado3_data,
            fecha_ultima_modificacion = NOW()
        WHERE id = :orden_id
    ");
    
    $stmt->execute([
        ':apartado3_data' => json_encode($apartado3, JSON_UNESCAPED_UNICODE),
        ':orden_id' => $orden_id
    ]);
    
    $pdo->commit();
    
    // Registrar en log
    error_log("Firma guardada - Tipo: {$tipo_firma}, Orden ID: {$orden_id}, Usuario: {$_SESSION['nombre_completo']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Firma guardada correctamente',
        'tipo_firma' => $tipo_firma
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error al guardar firma: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}