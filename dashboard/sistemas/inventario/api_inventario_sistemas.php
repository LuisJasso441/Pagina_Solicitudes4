<?php
/**
 * API Inventario de Sistemas (Insumos)
 * CRUD simple vía AJAX
 * Ubicación: dashboard/sistemas/inventario/api_inventario_sistemas.php
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/sistemas/inventario/inventario_sistemas_funciones.php';

if (!es_usuario_sistemas()) { echo json_encode(['success' => false, 'message' => 'Sin permiso de acceso.']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? $_POST['accion'] ?? '';

switch ($accion) {
    case 'crear':
        $datos = ['nombre' => trim($input['nombre'] ?? ''), 'categoria' => trim($input['categoria'] ?? ''), 'cantidad_total' => $input['cantidad_total'] ?? 0, 'cantidad_disponible' => $input['cantidad_disponible'] ?? 0, 'ubicacion' => trim($input['ubicacion'] ?? ''), 'umbral_minimo' => $input['umbral_minimo'] ?? 0];
        if (empty($datos['nombre'])) { echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio.']); exit; }
        if (empty($datos['categoria']) || !array_key_exists($datos['categoria'], INVENTARIO_SIS_CATEGORIAS)) { echo json_encode(['success' => false, 'message' => 'Categor&iacute;a no v&aacute;lida.']); exit; }
        echo json_encode(crear_articulo_sistemas($datos)); break;

    case 'actualizar':
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ID no v&aacute;lido.']); exit; }
        $datos = ['nombre' => trim($input['nombre'] ?? ''), 'categoria' => trim($input['categoria'] ?? ''), 'cantidad_total' => $input['cantidad_total'] ?? 0, 'cantidad_disponible' => $input['cantidad_disponible'] ?? 0, 'ubicacion' => trim($input['ubicacion'] ?? ''), 'umbral_minimo' => $input['umbral_minimo'] ?? 0];
        if (empty($datos['nombre'])) { echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio.']); exit; }
        echo json_encode(actualizar_articulo_sistemas($id, $datos)); break;

    case 'eliminar':
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ID no v&aacute;lido.']); exit; }
        echo json_encode(eliminar_articulo_sistemas($id)); break;

    case 'obtener':
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ID no v&aacute;lido.']); exit; }
        $articulo = obtener_articulo_sistemas($id);
        echo $articulo ? json_encode(['success' => true, 'data' => $articulo]) : json_encode(['success' => false, 'message' => 'Art&iacute;culo no encontrado.']); break;

    case 'verificar_duplicado':
        $nombre = trim($input['nombre'] ?? ''); $categoria = trim($input['categoria'] ?? ''); $excluir_id = (int)($input['excluir_id'] ?? 0);
        if (empty($nombre) || empty($categoria)) { echo json_encode(['success' => true, 'duplicado' => false]); exit; }
        $duplicado = verificar_duplicado_sistemas($nombre, $categoria, $excluir_id);
        echo json_encode(['success' => true, 'duplicado' => (bool)$duplicado, 'mensaje' => $duplicado ? 'Ya existe: "' . htmlspecialchars($duplicado['nombre']) . '" (ID #' . $duplicado['id'] . ')' : '']); break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acci&oacute;n no reconocida.']);
}
?>