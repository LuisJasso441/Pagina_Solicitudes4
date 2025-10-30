<?php
/**
 * Procesar completar documento
 * Mueve el documento a la base global y lo marca como completado
 * Solo Laboratorio puede completar
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
    echo json_encode(['success' => false, 'message' => 'Solo el departamento de Laboratorio puede completar documentos']);
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

// Verificar que no esté ya completado
if ($documento['estado'] == 'completado') {
    echo json_encode(['success' => false, 'message' => 'El documento ya está completado']);
    exit;
}

// Verificar que el Apartado 2 esté completo
$campos_apartado2 = [
    'recibe_solicitud' => 'Recibe solicitud',
    'resumen_resultados' => 'Resumen de resultados',
    'fecha_hora_entrega' => 'Fecha y hora de entrega'
];

$campos_faltantes = [];
foreach ($campos_apartado2 as $campo => $nombre) {
    if (empty($documento[$campo])) {
        $campos_faltantes[] = $nombre;
    }
}

if (!empty($campos_faltantes)) {
    echo json_encode([
        'success' => false,
        'message' => 'El Apartado 2 debe estar completo antes de finalizar. Faltan: ' . implode(', ', $campos_faltantes)
    ]);
    exit;
}

// Validar que el usuario de Laboratorio haya completado el Apartado 2
if ($documento['usuario_seguimiento_id'] != $usuario_id) {
    // Permitir que cualquier usuario de Laboratorio complete si ya hay datos
    // Comentar esta validación si quieres que sea más estricto
    error_log("Usuario {$usuario_id} completando documento creado por usuario {$documento['usuario_seguimiento_id']}");
}

// Log de la operación
error_log("Usuario {$usuario_id} (Laboratorio) completando documento {$documento_id} - Folio: {$documento['folio']}");

// Completar documento
$resultado = completar_documento($documento_id, $usuario_id);

// Si fue exitoso, agregar información adicional
if ($resultado['success']) {
    $resultado['folio'] = $documento['folio'];
    $resultado['redirect'] = '/Pagina_Solicitudes4/dashboard/documentos_colaborativos.php?ubicacion=global';
}

// Liberar sesión para evitar bloqueo con SSE
session_write_close();

// Enviar respuesta
echo json_encode($resultado);