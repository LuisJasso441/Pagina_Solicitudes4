<?php
/**
 * Procesar comentarios de documentos colaborativos
 * Permite agregar y eliminar comentarios
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/documentos_colaborativos.php';
require_once __DIR__ . '/../includes/documentos_comentarios.php';

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

// ⭐ VERIFICAR TOKEN CSRF
if (!verificar_csrf()) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento'];

// Determinar acción
$accion = $_POST['accion'] ?? 'agregar';

// ============================================
// ACCIÓN: AGREGAR COMENTARIO
// ============================================
if ($accion === 'agregar' || !isset($_POST['accion'])) {
    
    // Validar campos requeridos
    if (empty($_POST['documento_id']) || empty($_POST['folio']) || empty($_POST['texto_comentario'])) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
        exit;
    }
    
    $documento_id = intval($_POST['documento_id']);
    $folio = trim($_POST['folio']);
    $texto = trim($_POST['texto_comentario']);
    $tipo = isset($_POST['tipo_mensaje']) ? trim($_POST['tipo_mensaje']) : 'normal';
    
    // Validar documento existe
    $documento = obtener_documento($documento_id);
    
    if (!$documento) {
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
        exit;
    }
    
    // Verificar permisos de comentario
    $permisos = verificar_permisos_edicion($usuario_id, $departamento, $documento);
    
    if (!$permisos['puede_comentar']) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para comentar en este documento']);
        exit;
    }
    
    // Validar longitud del texto
    if (strlen($texto) < 5) {
        echo json_encode(['success' => false, 'message' => 'El comentario debe tener al menos 5 caracteres']);
        exit;
    }
    
    if (strlen($texto) > 1000) {
        echo json_encode(['success' => false, 'message' => 'El comentario es demasiado largo (máximo 1000 caracteres)']);
        exit;
    }
    
    // Validar tipo de mensaje
    $tipos_validos = ['normal', 'aclaracion', 'correccion', 'solicitud'];
    if (!in_array($tipo, $tipos_validos)) {
        $tipo = 'normal';
    }
    
    // Sanitizar texto (eliminar HTML/scripts)
    $texto = strip_tags($texto);
    
    // Log de la operación
    error_log("Usuario {$usuario_id} ({$departamento}) agregando comentario al documento {$documento_id}");
    
    // Agregar comentario
    $resultado = agregar_comentario_documento(
        $documento_id,
        $folio,
        $usuario_id,
        $nombre_usuario,
        $departamento,
        $texto,
        $tipo
    );
    
    // Liberar sesión para SSE
    session_write_close();
    
    echo json_encode($resultado);
    exit;
}

// ============================================
// ACCIÓN: ELIMINAR COMENTARIO
// ============================================
elseif ($accion === 'eliminar') {
    
    // Validar comentario_id
    if (empty($_POST['comentario_id'])) {
        echo json_encode(['success' => false, 'message' => 'ID de comentario no especificado']);
        exit;
    }
    
    $comentario_id = intval($_POST['comentario_id']);
    
    // Verificar si es admin (TI Sistemas)
    $es_admin = (strtolower($departamento) === 'ti_sistemas');
    
    // Log de la operación
    error_log("Usuario {$usuario_id} eliminando comentario {$comentario_id}");
    
    // Eliminar comentario
    $resultado = eliminar_comentario($comentario_id, $usuario_id, $es_admin);
    
    // Liberar sesión para SSE
    session_write_close();
    
    echo json_encode($resultado);
    exit;
}

// ============================================
// ACCIÓN NO VÁLIDA
// ============================================
else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}