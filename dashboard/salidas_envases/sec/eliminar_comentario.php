<?php
/**
 * Handler POST: eliminar comentario propio.
 * dashboard/salidas_envases/sec/eliminar_comentario.php
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_comentarios_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_historial_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!puede_leer_sec()) {
    establecer_alerta('error', 'No tienes acceso al módulo.');
    redirigir(URL_BASE . 'dashboard/inicio.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$comentario_id = (int) ($_POST['comentario_id'] ?? 0);
$sec_id        = (int) ($_POST['sec_id']         ?? 0);
$usuario_id    = (int) $_SESSION['usuario_id'];

$res = eliminar_comentario_sec($comentario_id, $usuario_id);

if (!$res['success']) {
    $_SESSION['sec_ver_errores'] = $res['errores'];
} else {
    // Historial con el texto eliminado guardado en datos_json para renderizar como sub-cita
    registrar_historial_sec(
        (int) $res['sec_id'],
        $usuario_id,
        'comentario_eliminado',
        'Eliminó su comentario',
        [
            'comentario_id'  => $comentario_id,
            'texto_original' => $res['texto_original'] ?? '',
        ]
    );
}

$destino = $res['success'] ? ((int) ($res['sec_id'] ?? $sec_id)) : $sec_id;
redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $destino . '#comentarios');