<?php
/**
 * Procesador AJAX para operaciones de archivos CQR
 * Ubicacion: dashboard/cotizaciones_qr/procesar_archivos_cqr.php
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/cotizaciones_qr/cotizaciones_qr_funciones.php';

// Verificar sesion
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Determinar la accion
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {
    case 'eliminar':
        $cotizacion_id = intval($_POST['cotizacion_id'] ?? 0);
        $tipo_archivo = $_POST['tipo_archivo'] ?? '';
        $indice = intval($_POST['indice'] ?? 0);
        
        if (!$cotizacion_id || !$tipo_archivo) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }
        
        $resultado = eliminar_archivo_cqr($cotizacion_id, $tipo_archivo, $indice, $usuario_id);
        echo json_encode($resultado);
        break;
        
    case 'agregar':
        $cotizacion_id = intval($_POST['cotizacion_id'] ?? 0);
        $tipo_archivo = $_POST['tipo_archivo'] ?? '';
        
        if (!$cotizacion_id || !$tipo_archivo) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }
        
        // Verificar que hay archivos
        if (!isset($_FILES['archivos']) || empty($_FILES['archivos']['name'][0])) {
            echo json_encode(['success' => false, 'error' => 'No se seleccionaron archivos']);
            exit;
        }
        
        $resultado = agregar_archivos_cqr($cotizacion_id, $tipo_archivo, $_FILES['archivos'], $usuario_id);
        echo json_encode($resultado);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Accion no valida']);
}