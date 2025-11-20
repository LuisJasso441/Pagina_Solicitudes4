<?php
/**
 * Eliminar imagen de evidencia del Apartado 2
 * Ruta actualizada: ordenes_servicio/evidencias/
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ordenes_servicio_funciones.php';

header('Content-Type: application/json');

if (!sesion_activa()) {
    echo json_encode(['success' => false, 'error' => 'Sesión no válida']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$orden_id = $data['orden_id'] ?? null;
$imagen_index = $data['imagen_index'] ?? null;

if (!$orden_id || $imagen_index === null) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// Obtener orden
$orden = obtener_orden_por_id($orden_id);

if (!$orden) {
    echo json_encode(['success' => false, 'error' => 'Orden no encontrada']);
    exit;
}

// Verificar permisos (solo Mantenimiento puede eliminar)
if (strtolower($_SESSION['departamento']) !== 'mantenimiento') {
    echo json_encode(['success' => false, 'error' => 'Sin permisos']);
    exit;
}

// Obtener apartado2
$apartado2 = $orden['apartado2'] ?? [];
$imagenes = $apartado2['evidencia_imagenes'] ?? [];

if (!isset($imagenes[$imagen_index])) {
    echo json_encode(['success' => false, 'error' => 'Imagen no encontrada']);
    exit;
}

// Eliminar archivo físico
// RUTA ACTUALIZADA: ordenes_servicio/evidencias/
$ruta_completa = __DIR__ . '/evidencias/' . $imagenes[$imagen_index]['nombre_archivo'];

if (file_exists($ruta_completa)) {
    if (!unlink($ruta_completa)) {
        echo json_encode(['success' => false, 'error' => 'No se pudo eliminar el archivo físico']);
        exit;
    }
}

// Eliminar de array
array_splice($imagenes, $imagen_index, 1);
$apartado2['evidencia_imagenes'] = $imagenes;

// Actualizar en BD
try {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("UPDATE ordenes_servicio_mantenimiento SET apartado2_data = :apartado2 WHERE id = :id");
    $stmt->execute([
        ':apartado2' => json_encode($apartado2, JSON_UNESCAPED_UNICODE),
        ':id' => $orden_id
    ]);
    
    echo json_encode(['success' => true, 'mensaje' => 'Imagen eliminada correctamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>