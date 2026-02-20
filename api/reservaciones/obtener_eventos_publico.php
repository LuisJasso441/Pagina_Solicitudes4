<?php
/**
 * API: Obtener eventos del calendario (PÚBLICO - sin autenticación)
 * Ubicación: api/reservaciones/obtener_eventos_publico.php
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/reservaciones/reservaciones_funciones.php';

header('Content-Type: application/json; charset=utf-8');

$inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fin = $_GET['fin'] ?? date('Y-m-d', strtotime('+30 days'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) {
    http_response_code(400); echo json_encode(['error' => 'Formato de fecha inválido']); exit;
}

try {
    $eventos = obtener_eventos_calendario($inicio, $fin);
    foreach ($eventos as &$evento) {
        unset($evento['extendedProps']['usuario_id']);
        unset($evento['extendedProps']['estado']);
    }
    echo json_encode($eventos);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Error al obtener eventos']);
}