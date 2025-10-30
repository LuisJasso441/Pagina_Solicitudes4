<?php
/**
 * Marcar notificación como leída
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/notificaciones.php';

header('Content-Type: application/json');

if (!sesion_activa()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$notificacion_id = $input['id'] ?? 0;

if (empty($notificacion_id)) {
    echo json_encode(['success' => false, 'error' => 'ID de notificación requerido']);
    exit();
}

$resultado = marcar_notificacion_leida($notificacion_id);

echo json_encode(['success' => $resultado]);
?>