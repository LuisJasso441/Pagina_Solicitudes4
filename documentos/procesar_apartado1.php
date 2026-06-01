<?php
/**
 * Procesar actualización del Apartado 1
 * ⭐ ACTUALIZADO - Usuarios del mismo departamento con permiso EDITOR pueden editar
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/colaborativo/documentos_colaborativos.php';

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

// ========================================
// VALIDACIÓN DE PERMISOS ACTUALIZADA
// ========================================

// Verificar que sea de un departamento que puede editar Apartado 1
$deptos_permitidos = ['normatividad', 'ventas', 'direccion', 'dirección', 'ptar'];
if (!in_array($dept_lower, $deptos_permitidos)) {
    echo json_encode(['success' => false, 'message' => 'Su departamento no puede editar el Apartado 1']);
    exit;
}

// Verificar que sea del mismo departamento que creó el documento
$dept_documento = strtolower(trim($documento['departamento_creador']));
if ($dept_lower != $dept_documento) {
    echo json_encode([
        'success' => false, 
        'message' => 'Solo usuarios del departamento ' . $documento['departamento_creador'] . ' pueden editar este documento'
    ]);
    exit;
}

// Verificar permiso de EDITOR en permisos_ssc
$permisos_ssc = obtener_permisos_ssc_usuario($usuario_id);
if ($permisos_ssc['editor'] != 1) {
    echo json_encode([
        'success' => false, 
        'message' => 'No tiene permisos de Editor para modificar documentos SSC. Contacte al administrador.'
    ]);
    exit;
}

// Verificar que no esté completado
if ($documento['estado'] == 'completado') {
    echo json_encode(['success' => false, 'message' => 'El documento está completado y no puede editarse']);
    exit;
}

// ========================================
// VALIDACIÓN DE CAMPOS
// ========================================

// Validar campos requeridos
$campos_requeridos = [
    'solicitado_por' => 'Solicitado por',
    'nombre_cliente' => 'Nombre del Cliente',
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
$servicios_validos = ['tratamiento_agua', 'revision_productos', 'calibracion_equipos', 'otro'];
if (!in_array($_POST['servicio_solicitado'], $servicios_validos)) {
    echo json_encode(['success' => false, 'message' => 'Servicio no válido']);
    exit;
}

// Validar longitud de campos
if (strlen($_POST['solicitado_por']) > 200) {
    echo json_encode(['success' => false, 'message' => 'El nombre es demasiado largo (máximo 200 caracteres)']);
    exit;
}

if (strlen($_POST['nombre_cliente']) > 200) {
    echo json_encode(['success' => false, 'message' => 'El nombre del cliente es demasiado largo (máximo 200 caracteres)']);
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

// ========================================
// PROCESAR ACTUALIZACIÓN
// ========================================

// Preparar datos
$datos = [
    'solicitado_por' => trim($_POST['solicitado_por']),
    'nombre_cliente' => trim($_POST['nombre_cliente']),
    'area_proceso_solicitante' => trim($_POST['area_proceso_solicitante']),
    'servicio_solicitado' => $_POST['servicio_solicitado'],
    'servicio_otro_especificar' => isset($_POST['servicio_otro_especificar']) ? trim($_POST['servicio_otro_especificar']) : null,
    'prioridad' => $_POST['prioridad'],
    'descripcion_servicio' => trim($_POST['descripcion_servicio'])
];

// Log de la operación
error_log("Usuario {$usuario_id} ({$departamento}) actualizando Apartado 1 del documento {$documento_id}");

// Actualizar documento (la función ya valida permisos internamente también)
$nombre_usuario = $_SESSION['nombre_completo'];
$resultado = actualizar_apartado1($documento_id, $datos, $usuario_id, $nombre_usuario);

// Liberar sesión para evitar bloqueo con SSE
session_write_close();

// Enviar respuesta
echo json_encode($resultado);