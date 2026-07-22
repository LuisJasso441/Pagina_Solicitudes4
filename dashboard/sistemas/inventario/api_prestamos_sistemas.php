<?php
/**
 * API de Préstamos del Inventario de Sistemas
 * Ubicación sugerida: dashboard/sistemas/inventario/api_prestamos_sistemas.php
 *
 * Acciones (JSON POST { "accion": "..." }):
 *   - crear_prestamo    { articulo_id, persona, departamento, cantidad, observaciones }
 *   - listar_prestamos  { estado?, articulo_id?, busqueda? }
 *   - devolver_prestamo { prestamo_id, cantidad }
 *
 * Respuesta: { success: bool, message?: string, data?: array, id?: int }
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../config/database.php';
// Define es_usuario_sistemas() y la constante de categorías del inventario
require_once __DIR__ . '/../../../includes/sistemas/inventario/inventario_sistemas_funciones.php';
require_once __DIR__ . '/../../../includes/sistemas/inventario/prestamos_sistemas_funciones.php';

header('Content-Type: application/json; charset=utf-8');

// Solo usuarios de Sistemas pueden operar préstamos
if (!es_usuario_sistemas()) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para esta acción.']);
    exit;
}

$input      = json_decode(file_get_contents('php://input'), true) ?: [];
$accion     = $input['accion'] ?? '';
$usuario_id = $_SESSION['usuario_id'] ?? null;

switch ($accion) {

    case 'crear_prestamo':
        echo json_encode(registrar_prestamo_sistemas(
            $input['articulo_id']   ?? 0,
            $input['persona']       ?? '',
            $input['departamento']  ?? '',
            $input['cantidad']      ?? 0,
            $input['observaciones'] ?? '',
            $usuario_id
        ));
        break;

    case 'listar_prestamos':
        $filtros = [
            'estado'      => $input['estado']      ?? '',
            'articulo_id' => $input['articulo_id'] ?? '',
            'busqueda'    => $input['busqueda']    ?? ''
        ];
        echo json_encode([
            'success' => true,
            'data'    => obtener_prestamos_sistemas($filtros)
        ]);
        break;

    case 'devolver_prestamo':
        echo json_encode(devolver_prestamo_sistemas(
            $input['prestamo_id'] ?? 0,
            $input['cantidad']    ?? 0,
            $usuario_id
        ));
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
}