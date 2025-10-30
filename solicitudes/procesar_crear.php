<?php
/**
 * Procesar creación de solicitud (AJAX)
 * Devuelve respuesta en formato JSON
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Configurar respuesta JSON
header('Content-Type: application/json');

// Verificar sesión
if (!sesion_activa()) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesión expirada. Por favor, inicia sesión nuevamente.'
    ]);
    exit();
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit();
}

try {
    // Validar campos obligatorios
    $errores = [];
    
    if (empty($_POST['tipo_soporte'])) $errores[] = "El tipo de soporte es obligatorio";
    if (empty($_POST['descripcion'])) $errores[] = "La descripción es obligatoria";
    if (empty($_POST['prioridad'])) $errores[] = "La prioridad es obligatoria";
    
    // Validar campos condicionales
    if ($_POST['tipo_soporte'] == 'Apoyo' && empty($_POST['tipo_apoyo'])) {
        $errores[] = "Debe seleccionar el tipo de apoyo";
    }
    if ($_POST['tipo_soporte'] == 'Problema' && empty($_POST['tipo_problema'])) {
        $errores[] = "Debe seleccionar el tipo de problema";
    }
    
    if (!empty($errores)) {
        echo json_encode([
            'success' => false,
            'message' => implode('<br>', $errores)
        ]);
        exit();
    }
    
    // Generar folio único
    $folio = generar_folio('SOL');
    
    // Preparar datos
    $usuario_id = $_SESSION['usuario_id'];
    $departamento = $_SESSION['departamento'];
    $tipo_soporte = limpiar_dato($_POST['tipo_soporte']);
    $tipo_apoyo = isset($_POST['tipo_apoyo']) ? limpiar_dato($_POST['tipo_apoyo']) : null;
    $tipo_problema = isset($_POST['tipo_problema']) ? limpiar_dato($_POST['tipo_problema']) : null;
    $descripcion = limpiar_dato($_POST['descripcion']);
    $prioridad = limpiar_dato($_POST['prioridad']);
    $estado = ESTADO_PENDIENTE;
    
    // Conectar a BD
    $pdo = conectarDB();
    
    // Insertar solicitud
    $stmt = $pdo->prepare("
    INSERT INTO solicitudes_atencion (
        folio, usuario_id, departamento, tipo_soporte, tipo_apoyo, tipo_problema,
        descripcion, prioridad, estado, fecha_creacion
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $folio, $usuario_id, $departamento, $tipo_soporte, $tipo_apoyo, $tipo_problema,
        $descripcion, $prioridad, $estado
    ]);
    
    // ====================================
    // ENVIAR NOTIFICACIÓN A TI
    // ====================================
    require_once __DIR__ . '/../includes/notificaciones.php';
    
    notificar_nueva_solicitud(
        $folio,
        $_SESSION['nombre_completo'],
        $_SESSION['departamento_nombre'],
        $prioridad
    );
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => "¡Solicitud creada exitosamente!<br><strong>Folio: $folio</strong><br>El equipo de TI la atenderá pronto.",
        'folio' => $folio
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear la solicitud: ' . $e->getMessage()
    ]);
}
?>