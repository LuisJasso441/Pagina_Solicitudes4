<?php
/**
 * API: Crear nueva reservación
 * Ubicación: api/reservaciones/crear_reservacion.php
 */
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/reservaciones/reservaciones_funciones.php';

if (!sesion_activa()) { header('Location: ' . URL_BASE . 'auth/InicioSesion.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . 'dashboard/reservaciones/nueva_reservacion.php'); exit; }

$solicitante     = trim($_POST['solicitante_nombre'] ?? '');
$departamento_id = (int)($_POST['departamento_id'] ?? 0);
$lugar           = $_POST['lugar'] ?? 'sala_juntas';
$motivo          = trim($_POST['motivo'] ?? '');
$fecha           = $_POST['fecha_reservacion'] ?? '';
$hora_input      = $_POST['hora_inicio'] ?? '';
$ampm_inicio     = $_POST['ampm_inicio'] ?? 'AM';
$hora_fin_input  = $_POST['hora_fin'] ?? '';
$ampm_fin        = $_POST['ampm_fin'] ?? 'AM';

$errores = [];
if (empty($solicitante)) $errores[] = 'El nombre del solicitante es obligatorio';
if ($departamento_id <= 0) $errores[] = 'Debe seleccionar un departamento';
if (empty($lugar)) $errores[] = 'Debe seleccionar un lugar';
if (empty($fecha)) $errores[] = 'La fecha es obligatoria';
if (empty($hora_input) || empty($hora_fin_input)) $errores[] = 'Las horas son obligatorias';

$hora_inicio_24 = '';
$hora_fin_24 = '';
if (!empty($hora_input) && !empty($hora_fin_input)) {
    $hora_inicio_24 = convertir_a_24h($hora_input, $ampm_inicio);
    $hora_fin_24 = convertir_a_24h($hora_fin_input, $ampm_fin);
    if (strtotime($hora_fin_24) <= strtotime($hora_inicio_24)) {
        $errores[] = 'La hora de término debe ser mayor a la hora de inicio';
    }
}

$pdo = conectarDB();
$stmt_depto = $pdo->prepare("SELECT codigo FROM departamentos WHERE id = :id");
$stmt_depto->execute([':id' => $departamento_id]);
$depto_data = $stmt_depto->fetch(PDO::FETCH_ASSOC);
$departamento_codigo = $depto_data['codigo'] ?? '';

if (!empty($errores)) {
    $_SESSION['form_errors'] = $errores;
    $_SESSION['form_data'] = $_POST;
    header('Location: ' . URL_BASE . 'dashboard/reservaciones/nueva_reservacion.php');
    exit;
}

$resultado = crear_reservacion([
    'solicitante_nombre' => $solicitante,
    'departamento'       => $departamento_codigo,
    'departamento_id'    => $departamento_id,
    'lugar'              => $lugar,
    'motivo'             => !empty($motivo) ? $motivo : null,
    'usuario_id'         => $_SESSION['usuario_id'],
    'fecha_reservacion'  => $fecha,
    'hora_inicio'        => $hora_inicio_24,
    'hora_fin'           => $hora_fin_24
]);

if ($resultado['success']) {
    $_SESSION['success'] = 'Reservación creada exitosamente';
    header('Location: ' . URL_BASE . 'dashboard/reservaciones/reservaciones_sala.php');
} else {
    if ($resultado['error'] === 'conflicto') {
        $c = $resultado['conflicto'];
        $_SESSION['form_errors'] = ["Horario no disponible en {$c['lugar']}. Ya existe una reservación de {$c['solicitante']} ({$c['departamento']}) de {$c['hora_inicio']} a {$c['hora_fin']}"];
    } else {
        $_SESSION['form_errors'] = ['Error: ' . $resultado['error']];
    }
    $_SESSION['form_data'] = $_POST;
    header('Location: ' . URL_BASE . 'dashboard/reservaciones/nueva_reservacion.php');
}
exit;