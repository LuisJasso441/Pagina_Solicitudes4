<?php
/**
 * API: Cancelar reservación
 * Ubicación: api/reservaciones/cancelar_reservacion.php
 * Método: POST
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/reservaciones/reservaciones_funciones.php';

header('Content-Type: application/json; charset=utf-8');

if (!sesion_activa()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$id = (int)($input['id'] ?? 0);
$motivo = trim($input['motivo'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

// Verificar que existe
$reservacion = obtener_reservacion_por_id($id);
if (!$reservacion) {
    echo json_encode(['success' => false, 'error' => 'Reservación no encontrada']);
    exit;
}

// Verificar permisos
if (!puede_modificar_reservacion($reservacion, $_SESSION['usuario_id'], $_SESSION['departamento'])) {
    echo json_encode(['success' => false, 'error' => 'No tienes permiso para cancelar esta reservación']);
    exit;
}

$resultado = cancelar_reservacion($id, $_SESSION['usuario_id'], $motivo);
echo json_encode($resultado);
