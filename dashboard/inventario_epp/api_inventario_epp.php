<?php
/**
 * API Inventario EPP - Operaciones AJAX
 * VERSIÓN 2.0 - Soporte de tallas
 * Ubicación: dashboard/inventario_epp/api_inventario_epp.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/inventario_epp_funciones.php';

header('Content-Type: application/json; charset=utf-8');

if (!sesion_activa()) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
if (!$permisos['tiene_acceso']) {
    echo json_encode(['success' => false, 'message' => 'Sin acceso al módulo.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['accion'])) {
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
    // Actualizar campo inline (artículo, unidad, etc.)
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
    // Actualizar stock de una talla específica
    // =====================================================
    case 'actualizar_stock_talla':
        if (!$permisos['puede_editar']) {
            echo json_encode(['success' => false, 'message' => 'Sin permiso de edición.']);
            exit;
        }
        
        $talla_id = (int) ($input['talla_id'] ?? 0);
        $stock = $input['stock'] ?? '';
        
        if (!$talla_id) {
            echo json_encode(['success' => false, 'message' => 'ID de talla no válido.']);
            exit;
        }
        
        $resultado = actualizar_stock_talla($talla_id, (int) $stock);
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
    // Obtener datos de un artículo (con tallas)
    // =====================================================
    case 'obtener_articulo':
        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no válido.']);
            exit;
        }
        
        $epp = obtener_epp_por_id($id);
        if ($epp) {
            $epp['tallas'] = obtener_tallas_epp($id);
            echo json_encode(['success' => true, 'data' => $epp]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Artículo no encontrado.']);
        }
        break;
    
    // =====================================================
    // Listar artículos con tallas para dropdown
    // =====================================================
    case 'listar_articulos':
        $articulos = obtener_articulos_dropdown_epp();
        echo json_encode(['success' => true, 'data' => $articulos]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
}