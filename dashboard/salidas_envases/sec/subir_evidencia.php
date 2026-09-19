<?php
/**
 * Handler POST multipart: subir 1..5 evidencias a una SEC.
 * dashboard/salidas_envases/sec/subir_evidencia.php
 *
 * Espera $_FILES['evidencias'] con múltiples archivos.
 * Cada archivo se sube individualmente. Se acumulan errores y éxitos.
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_evidencias_funciones.php';

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

$sec_id     = (int) ($_POST['sec_id'] ?? 0);
$usuario_id = (int) $_SESSION['usuario_id'];

if ($sec_id <= 0) {
    establecer_alerta('error', 'SEC inválida.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

// Verificar que llegaron archivos
if (empty($_FILES['evidencias']) || !is_array($_FILES['evidencias']['name'])) {
    $_SESSION['sec_ver_errores'] = ['No se recibieron archivos. Verifica el tamaño (post_max_size / upload_max_filesize) en el servidor.'];
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id . '#evidencias');
}

// Reindexar $_FILES['evidencias'] a lista de arrays individuales
$archivos = [];
$total = count($_FILES['evidencias']['name']);
for ($i = 0; $i < $total; $i++) {
    // Ignorar entradas vacías
    if (($_FILES['evidencias']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        && empty($_FILES['evidencias']['name'][$i])) {
        continue;
    }
    $archivos[] = [
        'name'     => $_FILES['evidencias']['name'][$i],
        'type'     => $_FILES['evidencias']['type'][$i],
        'tmp_name' => $_FILES['evidencias']['tmp_name'][$i],
        'error'    => $_FILES['evidencias']['error'][$i],
        'size'     => $_FILES['evidencias']['size'][$i],
    ];
}

if (empty($archivos)) {
    $_SESSION['sec_ver_errores'] = ['No se seleccionaron archivos.'];
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id . '#evidencias');
}

if (count($archivos) > 5) {
    $_SESSION['sec_ver_errores'] = ['Máximo 5 archivos por subida.'];
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id . '#evidencias');
}

$exitos = 0;
$errores = [];
foreach ($archivos as $arch) {
    $res = subir_evidencia_sec($sec_id, $arch, $usuario_id);
    if ($res['ok']) {
        $exitos++;
    } else {
        $errores[] = ($arch['name'] ?: 'archivo') . ': ' . $res['msg'];
    }
}

if (!empty($errores)) {
    $_SESSION['sec_ver_errores'] = $errores;
}

$msg = $exitos > 0 ? '&msg=evidencia_subida' : '';
redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id . $msg . '#evidencias');