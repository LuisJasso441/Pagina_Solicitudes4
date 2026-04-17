<?php
/**
 * API: Obtener dias festivos y jornada del empleado
 * api/vacaciones/dias_festivos.php
 * 
 * GET ?anio=2026 -> festivos del anio
 * GET ?anio=2026&usuario_id=9 -> festivos + jornada del usuario
 * GET ?anio=2026&empleado_gth_id=1 -> festivos + jornada del empleado GTH
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/vacaciones/vacaciones_funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$pdo = conectarDB();
$anio = intval($_GET['anio'] ?? date('Y'));
$usuario_id = intval($_GET['usuario_id'] ?? 0);
$empleado_gth_id = intval($_GET['empleado_gth_id'] ?? 0);

// Obtener festivos del anio (y el siguiente por si el rango cruza)
$festivos = [];
foreach ([$anio, $anio + 1] as $a) {
    foreach (obtener_festivos_anio_completo($pdo, $a) as $f) {
        $festivos[] = $f;
    }
}

// Obtener jornada
$jornada = 'lunes_sabado'; // default

if ($usuario_id > 0) {
    $stmt = $pdo->prepare("SELECT COALESCE(jornada, 'lunes_sabado') AS jornada FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $jornada = $row['jornada'];
} elseif ($empleado_gth_id > 0) {
    $stmt = $pdo->prepare("SELECT COALESCE(jornada, 'lunes_sabado') AS jornada FROM empleados_gth WHERE id = ?");
    $stmt->execute([$empleado_gth_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $jornada = $row['jornada'];
}

// Solo fechas como array de strings para facil lookup en JS
$festivos_fechas = array_map(function($f) { return $f['fecha']; }, $festivos);

echo json_encode([
    'anio' => $anio,
    'jornada' => $jornada,
    'festivos' => $festivos,
    'festivos_fechas' => array_values(array_unique($festivos_fechas))
]);