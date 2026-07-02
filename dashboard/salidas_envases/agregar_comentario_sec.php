<?php
/**
 * Handler: agregar comentario a una SEC
 * dashboard/salidas_envases/agregar_comentario_sec.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/permisos_helper.php';
require_once __DIR__ . '/../../includes/salidas_envases/sec_funciones.php';
require_once __DIR__ . '/../../includes/salidas_envases/sec_comentarios_funciones.php';
require_once __DIR__ . '/../../includes/salidas_envases/sec_historial_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesion ha expirado. Inicia sesion nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!puede_leer_sec()) {
    establecer_alerta('error', 'No tienes acceso al modulo de Salidas de Envases.');
    redirigir(URL_BASE . 'dashboard/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$sec_id     = (int)($_POST['sec_id'] ?? 0);
$texto      = $_POST['comentario'] ?? '';
$usuario_id = (int)$_SESSION['usuario_id'];

if ($sec_id <= 0) {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$sec = obtener_sec_por_id($sec_id);
if (!$sec) {
    establecer_alerta('error', 'La SEC no existe.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$resultado = crear_comentario_sec($sec_id, $usuario_id, $texto);

if ($resultado['success']) {
    // Historial
    registrar_historial_sec(
        $sec_id, $usuario_id, 'comentario_agregado',
        'Agrego un comentario',
        ['comentario_id' => (int)$resultado['id']]
    );
    // Notificacion
    notificar_nuevo_comentario_sec(
        $sec,
        $usuario_id,
        $_SESSION['nombre_completo'] ?? 'Usuario',
        trim($texto)
    );
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=comentario_agregado#comentarios");
} else {
    $_SESSION['sec_errores'] = $resultado['errores'];
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=error_validacion#comentarios");
}