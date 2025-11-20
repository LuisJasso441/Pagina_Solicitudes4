<?php
/**
 * Procesador: Editar Apartado 1
 * Usuario edita su propia orden (notifica a Mantenimiento)
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

// Obtener datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si viene de formulario HTML
    $orden_id = $_POST['orden_id'] ?? null;
    $datos = $_POST;
} else {
    // Si viene como JSON
    $input = file_get_contents('php://input');
    $datos = json_decode($input, true);
    $orden_id = $datos['orden_id'] ?? null;
}

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
        throw new Exception("No tienes permiso para editar esta orden");
    }
    
    // Verificar que la orden no esté completada
    if ($orden['estado'] === 'completado') {
        throw new Exception("No se puede editar una orden completada");
    }
    
    $pdo->beginTransaction();
    
    // Preparar datos actualizados del Apartado 1
    $apartado1_actual = $orden['apartado1'] ?? [];
    
    $apartado1_actualizado = [
        'empresa' => $datos['empresa'] ?? $apartado1_actual['empresa'],
        'folio' => $datos['folio'] ?? $apartado1_actual['folio'],
        'area_solicitante' => $apartado1_actual['area_solicitante'], // No se cambia
        'fecha_entrada' => $apartado1_actual['fecha_entrada'], // No se cambia
        'hora_entrada' => $apartado1_actual['hora_entrada'], // No se cambia
        'unidad_equipo' => trim($datos['unidad_equipo'] ?? $apartado1_actual['unidad_equipo']),
        'nombre_solicitante' => $apartado1_actual['nombre_solicitante'], // No se cambia
        'prioridad' => $datos['prioridad'] ?? $apartado1_actual['prioridad'],
        'descripcion_falla' => trim($datos['descripcion_falla'] ?? $apartado1_actual['descripcion_falla']),
        'evidencia_archivos' => $apartado1_actual['evidencia_archivos'] ?? []
    ];
    
    // Actualizar orden
    $stmt = $pdo->prepare("
        UPDATE ordenes_servicio_mantenimiento 
        SET apartado1_data = :apartado1_data,
            fecha_ultima_modificacion = NOW(),
            usuario_ultima_modificacion_id = :usuario_id,
            usuario_ultima_modificacion_nombre = :usuario_nombre
        WHERE id = :orden_id
    ");
    
    $stmt->execute([
        ':apartado1_data' => json_encode($apartado1_actualizado, JSON_UNESCAPED_UNICODE),
        ':usuario_id' => $_SESSION['usuario_id'],
        ':usuario_nombre' => $_SESSION['nombre_completo'],
        ':orden_id' => $orden_id
    ]);
    
    $pdo->commit();
    
    // Registrar en log
    error_log("Usuario editó Apartado 1 - Orden ID: {$orden_id}, Usuario: {$_SESSION['nombre_completo']}");
    
    // Notificar a Mantenimiento que hubo cambios
    notificar_edicion_usuario($orden_id, $orden['folio'], $_SESSION['nombre_completo']);
    
    // Si viene de formulario HTML, redirigir
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_CONTENT_TYPE'])) {
        $_SESSION['success'] = "Orden actualizada correctamente";
        header("Location: " . URL_BASE . "dashboard/ver_orden_servicio.php?id={$orden_id}");
        exit;
    }
    
    // Si viene como JSON, responder JSON
    echo json_encode([
        'success' => true,
        'message' => 'Orden actualizada correctamente'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error al editar Apartado 1: " . $e->getMessage());
    
    // Si viene de formulario HTML
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_CONTENT_TYPE'])) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: " . URL_BASE . "dashboard/ver_orden_servicio.php?id={$orden_id}");
        exit;
    }
    
    // Si viene como JSON
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}