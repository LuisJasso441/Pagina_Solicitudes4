<?php
/**
 * Procesador: Crear Nueva Orden de Servicio
 * Usuario crea Apartado 1
 * 
 * NOTIFICACIÓN: Al crear orden → Mantenimiento
 */

session_start();

// CRÍTICO: Limpiar cualquier output buffer
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ordenes_servicio/ordenes_servicio_funciones.php';

// Limpiar output buffer y establecer header JSON
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

// Verificar sesión
if (!sesion_activa()) {
    echo json_encode([
        'success' => false,
        'error' => 'Sesión no válida'
    ]);
    exit;
}

// Verificar que NO sea departamento de Mantenimiento
$departamento_codigo = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
if ($departamento_codigo === 'mantenimiento') {
    echo json_encode([
        'success' => false,
        'error' => 'El departamento de Mantenimiento no puede crear órdenes'
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

// Validar campos obligatorios
$campos_requeridos = ['empresa', 'folio', 'unidad_equipo', 'prioridad', 'descripcion_falla'];
foreach ($campos_requeridos as $campo) {
    if (empty($datos[$campo])) {
        echo json_encode([
            'success' => false,
            'error' => "El campo {$campo} es obligatorio"
        ]);
        exit;
    }
}

// VALIDACIÓN MEJORADA DEL FOLIO
$folio = trim($datos['folio']);

// Verificar que el folio no esté vacío DESPUÉS del trim
if (empty($folio)) {
    echo json_encode([
        'success' => false,
        'error' => 'El folio no puede estar vacío. Por favor, genera un folio válido.'
    ]);
    exit;
}

// Validar formato del folio (no debe contener caracteres especiales peligrosos)
if (!preg_match('/^[a-zA-Z0-9\-_\/]+$/', $folio)) {
    echo json_encode([
        'success' => false,
        'error' => 'El folio solo puede contener letras, números, guiones, guiones bajos y barras'
    ]);
    exit;
}

try {
    $pdo = conectarDB();
    $pdo->beginTransaction();
    
    // Verificar que el folio no exista
    $stmt = $pdo->prepare("SELECT id, folio FROM ordenes_servicio_mantenimiento WHERE folio = :folio");
    $stmt->execute([':folio' => $folio]);
    $existe = $stmt->fetch();
    
    if ($existe) {
        error_log("⚠️ FOLIO DUPLICADO - Folio: '$folio', ID existente: {$existe['id']}, Nuevo intento por: {$_SESSION['nombre_completo']}");
        throw new Exception("El folio '{$folio}' ya existe. Por favor, use otro folio o regenere uno nuevo.");
    }
    
    // Preparar datos del Apartado 1
    $apartado1_data = [
        'empresa' => $datos['empresa'],
        'folio' => $folio,
        'area_solicitante' => $datos['area_solicitante'] ?? $_SESSION['departamento_nombre'],
        'fecha_entrada' => $datos['fecha_entrada'] ?? date('Y-m-d'),
        'hora_entrada' => $datos['hora_entrada'] ?? date('H:i:s'),
        'unidad_equipo' => trim($datos['unidad_equipo']),
        'nombre_solicitante' => $datos['nombre_solicitante'] ?? $_SESSION['nombre_completo'],
        'prioridad' => $datos['prioridad'],
        'descripcion_falla' => trim($datos['descripcion_falla']),
        'evidencia_archivos' => $datos['evidencia_archivos'] ?? []
    ];
    
    // Insertar orden
    $stmt = $pdo->prepare("
        INSERT INTO ordenes_servicio_mantenimiento (
            folio,
            usuario_id,
            usuario_nombre,
            departamento,
            empresa,
            estado,
            apartado1_data,
            fecha_creacion
        ) VALUES (
            :folio,
            :usuario_id,
            :usuario_nombre,
            :departamento,
            :empresa,
            'pendiente_mantenimiento',
            :apartado1_data,
            NOW()
        )
    ");
    
    $stmt->execute([
        ':folio' => $folio,
        ':usuario_id' => $_SESSION['usuario_id'],
        ':usuario_nombre' => $_SESSION['nombre_completo'],
        ':departamento' => $_SESSION['departamento_nombre'],
        ':empresa' => $datos['empresa'],
        ':apartado1_data' => json_encode($apartado1_data, JSON_UNESCAPED_UNICODE)
    ]);
    
    $orden_id = $pdo->lastInsertId();
    
    // ========================================
    // NOTIFICACIÓN: Nueva orden → Mantenimiento
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
            'folio' => $folio,
            'url' => URL_BASE . 'dashboard/ordenes_servicio/ver_orden_servicio.php?id=' . $orden_id
        ]);
        
        $stmt_notif->execute([
            'nueva_orden_mantenimiento',
            '🔧 Nueva Orden de Servicio',
            "{$_SESSION['nombre_completo']} ha creado la orden $folio",
            $usuario_mant_id,
            $datos_json
        ]);
    }
    
    $pdo->commit();
    
    error_log("✅ Nueva orden creada - ID: {$orden_id}, Folio: {$folio}, Usuario: {$_SESSION['nombre_completo']}, Depto: {$_SESSION['departamento_nombre']}");
    
    echo json_encode([
        'success' => true,
        'orden_id' => $orden_id,
        'folio' => $folio,
        'message' => 'Orden creada exitosamente'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("❌ Error al crear orden: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}