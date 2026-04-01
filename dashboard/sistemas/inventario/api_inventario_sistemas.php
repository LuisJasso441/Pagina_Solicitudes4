<?php
/**
 * API Inventario de Sistemas
 * Maneja operaciones CRUD vía AJAX
 * Ubicación: dashboard/sistemas/inventario/api_inventario_sistemas.php
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/sistemas/inventario/inventario_sistemas_funciones.php';

// Verificar acceso: solo Sistemas
if (!es_usuario_sistemas()) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso de acceso.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? $_POST['accion'] ?? '';

switch ($accion) {
    
    // =====================================================
    // Crear artículo
    // =====================================================
    case 'crear':
        $datos = [
            'nombre'              => trim($input['nombre'] ?? ''),
            'categoria'           => trim($input['categoria'] ?? ''),
            'cantidad_total'      => $input['cantidad_total'] ?? 0,
            'cantidad_disponible' => $input['cantidad_disponible'] ?? 0,
            'ubicacion'           => trim($input['ubicacion'] ?? ''),
            'umbral_minimo'       => $input['umbral_minimo'] ?? 0
        ];
        
        // Validaciones
        if (empty($datos['nombre'])) {
            echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio.']);
            exit;
        }
        if (empty($datos['categoria']) || !array_key_exists($datos['categoria'], INVENTARIO_SIS_CATEGORIAS)) {
            echo json_encode(['success' => false, 'message' => 'Categor&iacute;a no v&aacute;lida.']);
            exit;
        }
        
        $resultado = crear_articulo_sistemas($datos);
        echo json_encode($resultado);
        break;
    
    // =====================================================
    // Actualizar artículo
    // =====================================================
    case 'actualizar':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no v&aacute;lido.']);
            exit;
        }
        
        $datos = [
            'nombre'              => trim($input['nombre'] ?? ''),
            'categoria'           => trim($input['categoria'] ?? ''),
            'cantidad_total'      => $input['cantidad_total'] ?? 0,
            'cantidad_disponible' => $input['cantidad_disponible'] ?? 0,
            'ubicacion'           => trim($input['ubicacion'] ?? ''),
            'umbral_minimo'       => $input['umbral_minimo'] ?? 0
        ];
        
        if (empty($datos['nombre'])) {
            echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio.']);
            exit;
        }
        
        $resultado = actualizar_articulo_sistemas($id, $datos);
        echo json_encode($resultado);
        break;
    
    // =====================================================
    // Eliminar artículo (soft delete)
    // =====================================================
    case 'eliminar':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no v&aacute;lido.']);
            exit;
        }
        
        $resultado = eliminar_articulo_sistemas($id);
        echo json_encode($resultado);
        break;
    
    // =====================================================
    // Obtener artículo (para edición)
    // =====================================================
    case 'obtener':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no v&aacute;lido.']);
            exit;
        }
        
        $articulo = obtener_articulo_sistemas($id);
        if ($articulo) {
            echo json_encode(['success' => true, 'data' => $articulo]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Art&iacute;culo no encontrado.']);
        }
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acci&oacute;n no reconocida.']);
}
?>