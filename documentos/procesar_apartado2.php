<?php
/**
 * Procesar actualización del Apartado 2
 * Solo usuarios de Laboratorio
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
$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento'];
$dept_lower = strtolower($departamento);

// Verificar que es Laboratorio
if ($dept_lower !== 'laboratorio') {
    echo json_encode(['success' => false, 'message' => 'Solo el departamento de Laboratorio puede editar el Apartado 2']);
    exit;
}

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

// Verificar que no esté completado
if ($documento['estado'] == 'completado') {
    echo json_encode(['success' => false, 'message' => 'El documento está completado y no puede editarse']);
    exit;
}

// Validar campos requeridos del Apartado 2
$campos_requeridos = [
    'recibe_solicitud' => 'Recibe solicitud',
    'resumen_resultados' => 'Resumen de resultados',
    'fecha_hora_entrega' => 'Fecha y hora de entrega'
];

foreach ($campos_requeridos as $campo => $nombre) {
    if (empty($_POST[$campo])) {
        echo json_encode(['success' => false, 'message' => "El campo '{$nombre}' es requerido"]);
        exit;
    }
}

// Validar longitud de campos
if (strlen($_POST['recibe_solicitud']) > 200) {
    echo json_encode(['success' => false, 'message' => 'El nombre es demasiado largo (máximo 200 caracteres)']);
    exit;
}

if (strlen($_POST['resumen_resultados']) > 5000) {
    echo json_encode(['success' => false, 'message' => 'El resumen es demasiado largo (máximo 5000 caracteres)']);
    exit;
}

// Validar formato de fecha
$fecha_entrega = $_POST['fecha_hora_entrega'];
$timestamp = strtotime($fecha_entrega);

if (!$timestamp) {
    echo json_encode(['success' => false, 'message' => 'Formato de fecha no válido']);
    exit;
}

// Convertir a formato MySQL
$fecha_mysql = date('Y-m-d H:i:s', $timestamp);

// Validar que la fecha no sea muy antigua (opcional: más de 1 año atrás)
$hace_un_ano = strtotime('-1 year');
if ($timestamp < $hace_un_ano) {
    echo json_encode(['success' => false, 'message' => 'La fecha de entrega no puede ser anterior a hace un año']);
    exit;
}

// Preparar datos
$datos = [
    'recibe_solicitud' => trim($_POST['recibe_solicitud']),
    'resumen_resultados' => trim($_POST['resumen_resultados']),
    'fecha_hora_entrega' => $fecha_mysql
];

// Log de la operación
error_log("Usuario {$usuario_id} (Laboratorio) actualizando Apartado 2 del documento {$documento_id}");

// Actualizar documento
$resultado = actualizar_apartado2($documento_id, $datos, $usuario_id, $nombre_usuario);

// Liberar sesión para evitar bloqueo con SSE
session_write_close();

// Enviar respuesta
echo json_encode($resultado);