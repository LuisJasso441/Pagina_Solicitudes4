<?php
/**
 * Endpoint JSON: eventos del calendario de disponibilidad
 *
 * Ubicación: dashboard/salidas_envases/api/eventos_disponibilidad.php
 *
 * Consumido por FullCalendar en disponibilidad_unidades.php
 *
 * Parámetros GET:
 *   desde (YYYY-MM-DD), hasta (YYYY-MM-DD)
 *   unidad_id (opcional)
 *
 * Retorna: array JSON de bloques con sus slots y metadata para el calendario.
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/disponibilidad_funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

verificar_sesion();

// Acceso: Logística, Ventas o Almacén de Residuos
$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
if (!in_array($dept, ['logistica', 'ventas', 'almacen_residuos'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin acceso']);
    exit;
}

// Validar parámetros de fecha
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros desde/hasta inválidos (formato YYYY-MM-DD)']);
    exit;
}

$unidad_id = isset($_GET['unidad_id']) && (int)$_GET['unidad_id'] > 0 ? (int)$_GET['unidad_id'] : null;

$disponibilidades = obtener_disponibilidades_rango($desde, $hasta, $unidad_id);

// Adjuntar color por unidad
$resultado = [];
foreach ($disponibilidades as $d) {
    $d['color'] = color_para_unidad($d['unidad_transporte_id']);
    $resultado[] = $d;
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);