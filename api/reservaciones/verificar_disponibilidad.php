<?php
/**
 * API: Verificar disponibilidad de horario (por lugar)
 * Ubicación: api/reservaciones/verificar_disponibilidad.php
 */
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/reservaciones/reservaciones_funciones.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) { http_response_code(401); echo json_encode(['error' => 'No autenticado']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Método no permitido']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$fecha      = $input['fecha'] ?? '';
$hora_inicio = $input['hora_inicio'] ?? '';
$hora_fin   = $input['hora_fin'] ?? '';
$lugar      = $input['lugar'] ?? 'sala_juntas';
$excluir_id = (int)($input['excluir_id'] ?? 0);

if (empty($fecha) || empty($hora_inicio) || empty($hora_fin)) {
    http_response_code(400); echo json_encode(['error' => 'Faltan campos requeridos']); exit;
}
if (strtotime($hora_fin) <= strtotime($hora_inicio)) {
    echo json_encode(['disponible' => false, 'error' => 'La hora de término debe ser mayor a la hora de inicio']); exit;
}

try {
    $resultado = verificar_disponibilidad($fecha, $hora_inicio, $hora_fin, $lugar, $excluir_id);
    echo json_encode($resultado);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}