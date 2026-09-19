<?php
/**
 * Handler POST: crea o actualiza una SEC.
 * dashboard/salidas_envases/sec/guardar_sec.php
 *
 * Un solo handler para crear (modo=crear) y editar (modo=editar, id).
 * En caso de error, guarda los datos en sesión para que el formulario
 * los pueda repopular.
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!puede_crear_sec()) {
    establecer_alerta('error', 'No tienes permisos para crear/editar Salidas de Envases.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$usuario_id = (int) $_SESSION['usuario_id'];
$modo       = $_POST['modo']  ?? 'crear';
$sec_id_ed  = (int) ($_POST['id'] ?? 0);

// Recolectar datos
$datos = [
    'unidad_id'         => (int) ($_POST['unidad_id'] ?? 0),
    'vuelta_id'         => (int) ($_POST['vuelta_id'] ?? 0),
    'fecha_salida'      => $_POST['fecha_salida']      ?? '',
    'hora_inicio_ruta'  => $_POST['hora_inicio_ruta']  ?? '',
    'hora_termino_ruta' => $_POST['hora_termino_ruta'] ?? '',
    'chofer_nombre'     => $_POST['chofer_nombre']     ?? '',
    'solicita_nombre'   => $_POST['solicita_nombre']   ?? '',
    'notas_generales'   => $_POST['notas_generales']   ?? '',
];

// Recolectar líneas: array asociativo indexado
$lineas_raw = $_POST['lineas'] ?? [];
$lineas = [];
if (is_array($lineas_raw)) {
    foreach ($lineas_raw as $l) {
        if (!is_array($l)) continue;
        $lineas[] = [
            'empresa_nombre'     => $l['empresa_nombre']     ?? '',
            'especificacion_id'  => (int) ($l['especificacion_id'] ?? 0),
            'cantidad'           => (int) ($l['cantidad']    ?? 0),
            'condiciones_envase' => $l['condiciones_envase'] ?? '',
        ];
    }
}

// Ejecutar
if ($modo === 'editar' && $sec_id_ed > 0) {
    $res = actualizar_sec($sec_id_ed, $datos, $lineas, $usuario_id);
    $redirect_error = URL_BASE . 'dashboard/salidas_envases/sec/editar_sec.php?id=' . $sec_id_ed;
    $msg_flash = 'actualizada';
} else {
    $res = crear_sec($datos, $lineas, $usuario_id);
    $redirect_error = URL_BASE . 'dashboard/salidas_envases/sec/nueva_sec.php';
    $msg_flash = 'creada';
}

if (!$res['success']) {
    // Repopular formulario con datos ingresados
    $_SESSION['sec_errores'] = $res['errores'];
    $_SESSION['sec_datos_previos'] = array_merge($datos, ['lineas' => $lineas]);
    redirigir($redirect_error);
}

// Éxito → notificar y redirigir al listado
$sec_creada = obtener_sec_por_id($res['sec_id']);
if ($sec_creada) {
    if ($modo === 'crear') {
        notificar_sec_creada($sec_creada);
    }
}

$folio = $res['folio'] ?? ($sec_creada['folio'] ?? '');
redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php?msg=' . $msg_flash . '&folio=' . urlencode($folio));