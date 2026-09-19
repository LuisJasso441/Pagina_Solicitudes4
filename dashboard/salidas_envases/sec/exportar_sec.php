<?php
/**
 * Handler: exporta las SECs filtradas a XLSX.
 * dashboard/salidas_envases/sec/exportar_sec.php
 *
 * Recibe los mismos filtros que el listado por GET.
 * Devuelve el archivo XLSX con headers de descarga.
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_export_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!puede_leer_sec()) {
    http_response_code(403);
    exit('Sin acceso al módulo.');
}

// Recoger filtros de la URL (mismos que el listado)
$filtros = [
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'estados'     => $_GET['estados']     ?? [],   // array (multi-select)
    'estado'      => $_GET['estado']      ?? '',   // fallback compatibilidad
    'empresa'     => $_GET['empresa']     ?? '',
    'unidad_id'   => $_GET['unidad_id']   ?? '',
    'creador_id'  => $_GET['creador_id']  ?? '',
    'busqueda'    => $_GET['busqueda']    ?? '',
];

// Generar archivo en memoria
try {
    $contenido = generar_xlsx_secs($filtros);
    $nombre    = nombre_archivo_export_secs();
} catch (Exception $e) {
    error_log('exportar_sec.php: ' . $e->getMessage());
    http_response_code(500);
    exit('Error al generar el archivo. Revisa el log del servidor.');
}

// Enviar al navegador
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . strlen($contenido));
header('Cache-Control: max-age=0, no-cache, must-revalidate');
header('Pragma: no-cache');
echo $contenido;
exit;