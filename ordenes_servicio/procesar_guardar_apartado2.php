<?php
/**
 * Procesador: Guardar Apartado 2 (Borrador)
 * Mantenimiento guarda sin enviar al usuario
 * 
 * NOTIFICACIÓN: Solo cuando es orden devuelta y es la primera vez que guarda → Usuario original
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
    
    // Guardar estado anterior para notificaciones
    $estado_anterior = $orden['estado'];
    
    $pdo->beginTransaction();
    
    // Verificar si es la primera vez que se guarda el Apartado 2
    $es_primera_vez = empty($orden['apartado2_data']) || $orden['apartado2_data'] === 'null' || $orden['apartado2_data'] === '[]';
    
    // Procesar código de equipo (si está vacío, guardar "NA")
    $codigo_equipo = trim($datos['codigo_equipo'] ?? '');
    $codigo_equipo = $codigo_equipo !== '' ? $codigo_equipo : 'NA';

    // Procesar horómetro (si está vacío, guardar "NA")
    $horometro = $datos['horometro'] ?? '';
    $horometro = ($horometro !== '' && $horometro !== null) ? $horometro : 'NA';
    
    // Preparar datos del Apartado 2
    $apartado2_data = [
        'fecha_atencion' => $datos['fecha_atencion'],
        'hora_inicio' => $datos['hora_inicio'],
        'fecha_termino' => $datos['fecha_termino'] ?? null,
        'hora_termino' => $datos['hora_termino'] ?? null,
        'descripcion_reparacion' => trim($datos['descripcion_reparacion'] ?? ''),
        'codigo_equipo' => $codigo_equipo,
        'horometro' => $horometro,
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
    
    // ========================================
    // NOTIFICACIONES
    // ========================================
    $datos_json = json_encode([
        'orden_id' => $orden_id,
        'folio' => $orden['folio'],
        'url' => URL_BASE . 'dashboard/ver_orden_servicio.php?id=' . $orden_id
    ]);
    
    // Primera vez en orden ORIGINAL (no devuelta)
    if ($es_primera_vez && $estado_anterior === 'pendiente_mantenimiento') {
        $stmt_notif = $pdo->prepare("
            INSERT INTO notificaciones 
            (tipo, titulo, mensaje, usuario_destino, datos_json, leida, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        
        $stmt_notif->execute([
            'orden_en_proceso',
            '⚙️ Orden en Proceso',
            "Mantenimiento está trabajando en tu orden {$orden['folio']}",
            $orden['usuario_id'],
            $datos_json
        ]);
    }
    
    // Primera vez en orden DEVUELTA
    if ($estado_anterior === 'devuelto') {
        $stmt_notif = $pdo->prepare("
            INSERT INTO notificaciones 
            (tipo, titulo, mensaje, usuario_destino, datos_json, leida, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        
        $stmt_notif->execute([
            'orden_corrigiendose',
            '🔄 Orden en Corrección',
            "Mantenimiento está corrigiendo tu orden {$orden['folio']}",
            $orden['usuario_id'],
            $datos_json
        ]);
    }
    
    $pdo->commit();
    
    error_log("Mantenimiento guardó Apartado 2 (borrador) - Orden ID: {$orden_id}, Usuario: {$_SESSION['nombre_completo']}, Estado anterior: {$estado_anterior}");
    
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