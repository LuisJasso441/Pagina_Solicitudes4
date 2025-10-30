<?php
/**
 * Marcar todas las notificaciones como leídas
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

$resultado = marcar_todas_leidas($_SESSION['usuario_id']);

echo json_encode(['success' => $resultado]);
?>