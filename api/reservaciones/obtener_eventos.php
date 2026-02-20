<?php
/**
 * API: Obtener eventos del calendario
 * Ubicación: api/reservaciones/obtener_eventos.php
 * Método: GET
 * Parámetros: ?inicio=YYYY-MM-DD&fin=YYYY-MM-DD
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/reservaciones/reservaciones_funciones.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

// Validar parámetros
$inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fin = $_GET['fin'] ?? date('Y-m-d', strtotime('+30 days'));

// Validar formato de fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
    exit;
}

try {
    $eventos = obtener_eventos_calendario($inicio, $fin);
    echo json_encode($eventos);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener eventos: ' . $e->getMessage()]);
}
