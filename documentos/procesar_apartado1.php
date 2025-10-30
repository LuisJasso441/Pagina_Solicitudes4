<?php
/**
 * Procesar actualización del Apartado 1
 * Solo usuarios de Normatividad y Ventas (creadores)
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../includes/documentos_colaborativos.php';

// Verificar autenticación
if (!sesion_activa()) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$departamento = $_SESSION['departamento'];
$dept_lower = strtolower($departamento);

// Verificar documento_id
if (empty($_POST['documento_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de documento no especificado']);
    exit;
}

$documento_id = intval($_POST['documento_id']);

// Obtener documento
$documento = obtener_documento($documento_id);

if (!$documento) {
    echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
    exit;
}

// Verificar permisos: solo el creador puede editar Apartado 1
if ($documento['usuario_creador_id'] != $usuario_id) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para editar este documento']);
    exit;
}

// Verificar que no esté completado
if ($documento['estado'] == 'completado') {
    echo json_encode(['success' => false, 'message' => 'El documento está completado y no puede editarse']);
    exit;
}

// Validar campos requeridos
$campos_requeridos = [
    'solicitado_por' => 'Solicitado por',
    'area_proceso_solicitante' => 'Área o proceso solicitante',
    'servicio_solicitado' => 'Servicio solicitado',
    'prioridad' => 'Prioridad',
    'descripcion_servicio' => 'Descripción del servicio'
];

foreach ($campos_requeridos as $campo => $nombre) {
    if (empty($_POST[$campo])) {
        echo json_encode(['success' => false, 'message' => "El campo '{$nombre}' es requerido"]);
        exit;
    }
}

// Validar servicio "otro"
if ($_POST['servicio_solicitado'] === 'otro') {
    if (empty($_POST['servicio_otro_especificar'])) {
        echo json_encode(['success' => false, 'message' => 'Debe especificar el servicio cuando selecciona "Otro"']);
        exit;
    }
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

// Validar longitud de campos
if (strlen($_POST['solicitado_por']) > 200) {
    echo json_encode(['success' => false, 'message' => 'El nombre es demasiado largo (máximo 200 caracteres)']);
    exit;
}

if (strlen($_POST['area_proceso_solicitante']) > 200) {
    echo json_encode(['success' => false, 'message' => 'El área es demasiado larga (máximo 200 caracteres)']);
    exit;
}

if (strlen($_POST['descripcion_servicio']) > 2000) {
    echo json_encode(['success' => false, 'message' => 'La descripción es demasiado larga (máximo 2000 caracteres)']);
    exit;
}

// Preparar datos
$datos = [
    'solicitado_por' => trim($_POST['solicitado_por']),
    'area_proceso_solicitante' => trim($_POST['area_proceso_solicitante']),
    'servicio_solicitado' => $_POST['servicio_solicitado'],
    'servicio_otro_especificar' => isset($_POST['servicio_otro_especificar']) ? trim($_POST['servicio_otro_especificar']) : null,
    'prioridad' => $_POST['prioridad'],
    'descripcion_servicio' => trim($_POST['descripcion_servicio'])
];

// Log de la operación (opcional)
error_log("Usuario {$usuario_id} actualizando Apartado 1 del documento {$documento_id}");

// Actualizar documento
$resultado = actualizar_apartado1($documento_id, $datos, $usuario_id);

// Liberar sesión para evitar bloqueo con SSE
session_write_close();

// Enviar respuesta
echo json_encode($resultado);