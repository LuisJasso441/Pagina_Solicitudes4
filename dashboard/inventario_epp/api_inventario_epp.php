<?php
/**
 * API Inventario EPP - Operaciones AJAX
 * Ubicación: dashboard/inventario_epp/api_inventario_epp.php
 * 
 * Endpoints:
 * - actualizar_campo: Edición inline de campos
 * - eliminar: Soft delete de artículo
 * - obtener_articulo: Obtener datos de un artículo (para formulario de movimientos)
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/inventario_epp_funciones.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar sesión
if (!sesion_activa()) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

// Verificar permisos
$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
if (!$permisos['tiene_acceso']) {
    echo json_encode(['success' => false, 'message' => 'Sin acceso al módulo.']);
    exit;
}

// Obtener datos del request
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['accion'])) {
    // También aceptar GET para obtener_articulo
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion'])) {
        $input = $_GET;
    } else {
        echo json_encode(['success' => false, 'message' => 'Solicitud no válida.']);
        exit;
    }
}

$accion = $input['accion'];

switch ($accion) {
    
    // =====================================================
    // Actualizar campo inline
    // =====================================================
    case 'actualizar_campo':
        if (!$permisos['puede_editar']) {
            echo json_encode(['success' => false, 'message' => 'Sin permiso de edición.']);
            exit;
        }
        
        $id = (int) ($input['id'] ?? 0);
        $campo = $input['campo'] ?? '';
        $valor = $input['valor'] ?? '';
        
        if (!$id || !$campo) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            exit;
        }
        
        $resultado = actualizar_campo_epp($id, $campo, $valor);
        echo json_encode($resultado);
        break;
    
    // =====================================================
    // Eliminar artículo (soft delete)
    // =====================================================
    case 'eliminar':
        if (!$permisos['puede_editar']) {
            echo json_encode(['success' => false, 'message' => 'Sin permiso para eliminar.']);
            exit;
        }
        
        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no válido.']);
            exit;
        }
        
        $resultado = eliminar_epp($id);
        echo json_encode($resultado);
        break;
    
    // =====================================================
    // Obtener datos de un artículo (para formulario movimientos)
    // =====================================================
    case 'obtener_articulo':
        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no válido.']);
            exit;
        }
        
        $epp = obtener_epp_por_id($id);
        if ($epp) {
            echo json_encode(['success' => true, 'data' => $epp]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Artículo no encontrado.']);
        }
        break;
    
    // =====================================================
    // Obtener lista de artículos para dropdown
    // =====================================================
    case 'listar_articulos':
        $articulos = obtener_articulos_dropdown_epp();
        echo json_encode(['success' => true, 'data' => $articulos]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
}