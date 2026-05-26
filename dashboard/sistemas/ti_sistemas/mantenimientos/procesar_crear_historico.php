<?php
/**
 * Procesar Crear Mantenimiento Histórico
 * dashboard/sistemas/ti_sistemas/mantenimientos/procesar_crear_historico.php
 *
 * v5 - tipo_equipo (NOT NULL) del equipo + fecha_solicitud default + creado_por
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_ti = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_ti) {
    establecer_alerta('error', 'No tiene permisos para realizar esta acción.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

$grupo_equipo       = trim($_POST['grupo_equipo'] ?? '');
$departamento_id    = (int) ($_POST['departamento_id'] ?? 0);
$usuario_id         = (int) ($_POST['usuario_id'] ?? 0);
$equipo_id          = (int) ($_POST['equipo_id'] ?? 0);
$tipo_mantenimiento = trim($_POST['tipo_mantenimiento'] ?? '');
$fecha_realizado    = trim($_POST['fecha_realizado'] ?? '');
$duracion_horas     = (int) ($_POST['duracion_horas'] ?? 0);
$duracion_minutos   = (int) ($_POST['duracion_minutos'] ?? 0);
$descripcion        = trim($_POST['descripcion_trabajo'] ?? '');
$total_minutos      = $duracion_horas * 60 + $duracion_minutos;

$errores = [];
if (!in_array($grupo_equipo, ['computo', 'perifericos', 'impresoras', 'red', 'otros'])) $errores[] = 'Tipo de equipo inválido.';
if ($departamento_id <= 0) $errores[] = 'Debes seleccionar un departamento.';
if ($usuario_id <= 0) $errores[] = 'Debes seleccionar un usuario.';
if ($equipo_id <= 0) $errores[] = 'Debes seleccionar un equipo.';
if (!in_array($tipo_mantenimiento, ['fisico', 'logico'])) $errores[] = 'Tipo de mantenimiento inválido.';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_realizado)) {
    $errores[] = 'Fecha en que se realizó inválida.';
} else {
    $ts = strtotime($fecha_realizado);
    if ($ts === false) $errores[] = 'Fecha en que se realizó inválida.';
    elseif ($ts > strtotime(date('Y-m-d') . ' 23:59:59')) $errores[] = 'La fecha no puede ser futura.';
}
if (strlen($descripcion) > 2000) $errores[] = 'La descripción no puede exceder 2000 caracteres.';
if ($duracion_horas < 0 || $duracion_horas > 23) $errores[] = 'Horas de duración inválidas (0-23).';
if ($duracion_minutos < 0 || $duracion_minutos > 59) $errores[] = 'Minutos de duración inválidos (0-59).';
if ($total_minutos < 1) $errores[] = 'La duración debe ser de al menos 1 minuto.';

if (!empty($errores)) {
    establecer_alerta('error', implode(' | ', $errores));
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/crear_mantenimiento_historico.php');
    exit;
}

$pdo = conectarDB();

$stmt = $pdo->prepare("SELECT id FROM departamentos WHERE id = ?");
$stmt->execute([$departamento_id]);
if (!$stmt->fetch()) {
    establecer_alerta('error', 'El departamento seleccionado no existe.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/crear_mantenimiento_historico.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND activo = 1");
$stmt->execute([$usuario_id]);
if (!$stmt->fetch()) {
    establecer_alerta('error', 'El usuario seleccionado no existe o no está activo.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/crear_mantenimiento_historico.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, tipo_equipo FROM inventario_equipos WHERE id = ?");
$stmt->execute([$equipo_id]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$equipo) {
    establecer_alerta('error', 'El equipo seleccionado no existe.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/crear_mantenimiento_historico.php');
    exit;
}
$tipo_equipo = $equipo['tipo_equipo'];

$folio = generarFolioHistorico($pdo);

try {
    $sql = "
        INSERT INTO solicitudes_mantenimiento_ti (
            folio, tipo_equipo, grupo_equipo, tipo_mantenimiento,
            usuario_id, departamento_id, equipo_id,
            descripcion_problema, descripcion_solucion,
            prioridad, estado, es_historico,
            fecha_deseada, fecha_atencion, fecha_finalizacion, creado_por
        ) VALUES (
            :folio, :tipo_equipo, :grupo_equipo, :tipo_mant,
            :usuario_id, :departamento_id, :equipo_id,
            :desc_problema, :desc_solucion,
            'media', 'cerrada', 1,
            :fecha_deseada, :fecha_atencion, :fecha_finalizacion, :creado_por
        )
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':folio'              => $folio,
        ':tipo_equipo'        => $tipo_equipo,
        ':grupo_equipo'       => $grupo_equipo,
        ':tipo_mant'          => $tipo_mantenimiento,
        ':usuario_id'         => $usuario_id,
        ':departamento_id'    => $departamento_id,
        ':equipo_id'          => $equipo_id,
        ':desc_problema'      => 'Registro de mantenimiento histórico',
        ':desc_solucion'      => $descripcion ?: null,
        ':fecha_deseada'      => $fecha_realizado,
        ':fecha_atencion'     => $fecha_realizado . ' 09:00:00',
        ':fecha_finalizacion' => date('Y-m-d H:i:s', strtotime($fecha_realizado . ' 09:00:00') + $total_minutos * 60),
        ':creado_por'         => $_SESSION['usuario_id'],
    ]);

    $id_nuevo = $pdo->lastInsertId();
    establecer_alerta('success', "Registro histórico creado exitosamente con folio {$folio}.");
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento_historico.php?id=' . $id_nuevo);
    exit;

} catch (PDOException $e) {
    error_log("Error creando histórico: " . $e->getMessage());
    establecer_alerta('error', 'Error al guardar el registro: ' . $e->getMessage());
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/crear_mantenimiento_historico.php');
    exit;
}

function generarFolioHistorico($pdo) {
    $fecha = date('Ymd');
    $prefijo = "HIST-{$fecha}-";
    $stmt = $pdo->prepare("SELECT folio FROM solicitudes_mantenimiento_ti WHERE folio LIKE :prefijo ORDER BY folio DESC LIMIT 1");
    $stmt->execute([':prefijo' => $prefijo . '%']);
    $ultimo = $stmt->fetchColumn();
    $secuencia = 1;
    if ($ultimo) { $partes = explode('-', $ultimo); $secuencia = (int) end($partes) + 1; }
    return $prefijo . str_pad($secuencia, 4, '0', STR_PAD_LEFT);
}