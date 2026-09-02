<?php
/**
 * API de Vales EPP - Operaciones AJAX propias de vales
 * Ubicacion: dashboard/inventario_epp/api_vales_epp.php
 * Separado de api_inventario_epp.php: aquel valida permisos de INVENTARIO;
 * los vales se rigen por verificar_permisos_vales().
 */
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/vales_epp_funciones.php';

header('Content-Type: application/json; charset=utf-8');

if (!sesion_activa()) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

$permisos = verificar_permisos_vales();
if (!$permisos['puede_crear']) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso para crear vales.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['accion'])) {
    echo json_encode(['success' => false, 'message' => 'Solicitud no válida.']);
    exit;
}

switch ($input['accion']) {
    case 'crear_empleado_epp':
        echo json_encode(crear_empleado_epp_inline($input));
        break;

    // --- Administracion de empleados: solo Seguridad ---
    case 'actualizar_empleado':
        if ($permisos['departamento'] !== 'seguridad') {
            echo json_encode(['success' => false, 'message' => 'Sin permiso.']);
            exit;
        }
        echo json_encode(actualizar_empleado_epp($input));
        break;

    case 'toggle_empleado':
        if ($permisos['departamento'] !== 'seguridad') {
            echo json_encode(['success' => false, 'message' => 'Sin permiso.']);
            exit;
        }
        echo json_encode(cambiar_estado_empleado_epp($input['id'] ?? 0, !empty($input['activo'])));
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
}