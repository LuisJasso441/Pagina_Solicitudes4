<?php
/**
 * Procesar creación de nuevo documento colaborativo
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/documentos_colaborativos.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// ✅ VERIFICAR TOKEN CSRF
if (!verificar_csrf()) {
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido. Por favor, recarga la página e intenta nuevamente.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$departamento = $_SESSION['departamento'];
$dept_lower = strtolower($departamento);

// Verificar permisos (solo Normatividad y Ventas pueden crear)
if (!in_array($dept_lower, ['normatividad', 'ventas'])) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para crear documentos']);
    exit;
}

// Validar campos requeridos
$campos_requeridos = [
    'solicitado_por',
    'area_proceso_solicitante',
    'servicio_solicitado',
    'prioridad',
    'descripcion_servicio'
];

foreach ($campos_requeridos as $campo) {
    if (empty($_POST[$campo])) {
        echo json_encode(['success' => false, 'message' => "El campo {$campo} es requerido"]);
        exit;
    }
}

// Validar servicio "otro"
if ($_POST['servicio_solicitado'] === 'otro' && empty($_POST['servicio_otro_especificar'])) {
    echo json_encode(['success' => false, 'message' => 'Debe especificar el servicio cuando selecciona "Otro"']);
    exit;
}

// Validar prioridad
$prioridades_validas = ['baja', 'media', 'alta'];
if (!in_array($_POST['prioridad'], $prioridades_validas)) {
    echo json_encode(['success' => false, 'message' => 'Prioridad no válida']);
    exit;
}

// Validar servicio
$servicios_validos = ['tratamiento_agua', 'evaluacion_productos', 'calibracion_equipos', 'otro'];
if (!in_array($_POST['servicio_solicitado'], $servicios_validos)) {
    echo json_encode(['success' => false, 'message' => 'Servicio no válido']);
    exit;
}

// Preparar datos
$datos = [
    'solicitado_por' => $_POST['solicitado_por'],
    'area_proceso_solicitante' => $_POST['area_proceso_solicitante'],
    'servicio_solicitado' => $_POST['servicio_solicitado'],
    'servicio_otro_especificar' => $_POST['servicio_otro_especificar'] ?? null,
    'prioridad' => $_POST['prioridad'],
    'descripcion_servicio' => $_POST['descripcion_servicio']
];

// Crear documento
$resultado = crear_documento_colaborativo($datos, $usuario_id, $departamento);

// Liberar sesión para evitar bloqueo con SSE
session_write_close();

echo json_encode($resultado);