<?php
/**
 * Handler: eliminar comentario propio
 * dashboard/salidas_envases/eliminar_comentario_sec.php
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

$comentario_id = (int)($_POST['comentario_id'] ?? 0);
$sec_id        = (int)($_POST['sec_id'] ?? 0);
$usuario_id    = (int)$_SESSION['usuario_id'];

if ($comentario_id <= 0 || $sec_id <= 0) {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$resultado = eliminar_comentario_sec($comentario_id, $usuario_id);

if ($resultado['success']) {
    registrar_historial_sec(
        $resultado['sec_id'], $usuario_id, 'comentario_eliminado',
        'Elimino un comentario',
        ['texto_original' => $resultado['texto_original'] ?? '']
    );
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=comentario_eliminado#comentarios");
} else {
    $_SESSION['sec_errores'] = $resultado['errores'];
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=error_validacion#comentarios");
}