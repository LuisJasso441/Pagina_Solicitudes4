<?php
/**
 * Handler POST: crear comentario en una SEC.
 * dashboard/salidas_envases/sec/guardar_comentario.php
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_funciones.php';
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
    establecer_alerta('error', 'No tienes acceso al módulo de Salidas de Envases.');
    redirigir(URL_BASE . 'dashboard/inicio.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$sec_id     = (int) ($_POST['sec_id'] ?? 0);
$texto      = $_POST['comentario'] ?? '';
$usuario_id = (int) $_SESSION['usuario_id'];

$sec = obtener_sec_por_id($sec_id);
if (!$sec) {
    establecer_alerta('error', 'SEC inválida.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$res = crear_comentario_sec($sec_id, $usuario_id, $texto);

if (!$res['success']) {
    $_SESSION['sec_ver_errores'] = $res['errores'];
} else {
    // Historial
    $preview = mb_strlen($texto) > 200 ? mb_substr($texto, 0, 200) . '…' : $texto;
    registrar_historial_sec(
        $sec_id,
        $usuario_id,
        'comentario_agregado',
        'Agregó un comentario: "' . $preview . '"',
        ['comentario_id' => $res['id'] ?? null, 'texto' => $texto]
    );
    // Notificar
    notificar_nuevo_comentario_sec($sec, $usuario_id, $_SESSION['nombre_completo'], $texto);
}

redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id . '#comentarios');