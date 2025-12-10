<?php
/**
 * Procesar actualización del Apartado 2
 * Solo usuarios de Laboratorio
 * ⭐ CORREGIDO - Ruta de archivos corregida
 */

// Deshabilitar output de errores PHP para que no rompa el JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

// Evitar cualquier output antes del JSON
ob_start();

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../auth/verificar_sesion.php';
    require_once __DIR__ . '/../includes/colaborativo/documentos_colaborativos.php';

    // Limpiar cualquier output buffer antes de continuar
    ob_clean();

    // Verificar autenticación
    if (!sesion_activa()) {
        echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
        exit;
    }

    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    $usuario_id = $_SESSION['usuario_id'];
    $nombre_usuario = $_SESSION['nombre_completo'];
    $departamento = $_SESSION['departamento'];
    $dept_lower = strtolower($departamento);

    // Verificar que es Laboratorio
    if ($dept_lower !== 'laboratorio') {
        echo json_encode(['success' => false, 'message' => 'Solo el departamento de Laboratorio puede editar el Apartado 2']);
        exit;
    }

    // Verificar documento_id
    if (empty($_POST['documento_id'])) {
        echo json_encode(['success' => false, 'message' => 'ID de documento no especificado']);
        exit;
    }

    $documento_id = intval($_POST['documento_id']);

    // Obtener documento
    $documento = obtener_documento($documento_id);

    if (!$documento) {
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
        exit;
    }

    // Verificar que no esté completado
    if ($documento['estado'] == 'completado') {
        echo json_encode(['success' => false, 'message' => 'El documento está completado y no puede editarse']);
        exit;
    }

    // Validar campos requeridos del Apartado 2
    $campos_requeridos = [
        'recibe_solicitud' => 'Recibe solicitud',
        'resumen_resultados' => 'Resumen de resultados',
        'fecha_recibido' => 'Fecha de recibido',
        'hora_recibido' => 'Hora de recibido'
    ];

    foreach ($campos_requeridos as $campo => $nombre) {
        if (empty($_POST[$campo])) {
            echo json_encode(['success' => false, 'message' => "El campo '{$nombre}' es requerido"]);
            exit;
        }
    }

    // Validar longitud de campos
    if (strlen($_POST['recibe_solicitud']) > 200) {
        echo json_encode(['success' => false, 'message' => 'El nombre es demasiado largo (máximo 200 caracteres)']);
        exit;
    }

    if (strlen($_POST['resumen_resultados']) > 5000) {
        echo json_encode(['success' => false, 'message' => 'El resumen es demasiado largo (máximo 5000 caracteres)']);
        exit;
    }

    // Validar formato de fecha
    $fecha_recibido = $_POST['fecha_recibido'];
    $hora_recibido = $_POST['hora_recibido'];

    if (!strtotime($fecha_recibido)) {
        echo json_encode(['success' => false, 'message' => 'Formato de fecha no válido']);
        exit;
    }

    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora_recibido)) {
        echo json_encode(['success' => false, 'message' => 'Formato de hora no válido (HH:MM)']);
        exit;
    }

    // Validar que la fecha no sea muy antigua (opcional: más de 1 año atrás)
    $timestamp_fecha = strtotime($fecha_recibido);
    $hace_un_ano = strtotime('-1 year');
    if ($timestamp_fecha < $hace_un_ano) {
        echo json_encode(['success' => false, 'message' => 'La fecha de recibido no puede ser anterior a hace un año']);
        exit;
    }

    // ===== MANEJO DE ARCHIVOS =====
    $archivos_guardados = [];

    if (!empty($_FILES['archivos_apartado2']['name'][0])) {
        // ⭐ RUTA CORREGIDA - Carpeta en la raíz del proyecto
        $directorio_destino = __DIR__ . '/../Imagenes_SSC/';
        
        if (!is_dir($directorio_destino)) {
            if (!mkdir($directorio_destino, 0755, true)) {
                error_log("Error: No se pudo crear directorio {$directorio_destino}");
                echo json_encode(['success' => false, 'message' => 'No se pudo crear el directorio de archivos']);
                exit;
            }
        }
        
        // Procesar cada archivo (sin límite de cantidad ni tamaño)
        $total_archivos = count($_FILES['archivos_apartado2']['name']);
        
        for ($i = 0; $i < $total_archivos; $i++) {
            if ($_FILES['archivos_apartado2']['error'][$i] === UPLOAD_ERR_OK) {
                $nombre_original = basename($_FILES['archivos_apartado2']['name'][$i]);
                $nombre_temporal = $_FILES['archivos_apartado2']['tmp_name'][$i];
                $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
                $tipo_mime = $_FILES['archivos_apartado2']['type'][$i];
                $tamanio = $_FILES['archivos_apartado2']['size'][$i];
                
                // Generar nombre único
                $nombre_unico = uniqid('ssc_' . $documento_id . '_', true) . '.' . $extension;
                $ruta_destino = $directorio_destino . $nombre_unico;
                
                // Mover archivo
                if (move_uploaded_file($nombre_temporal, $ruta_destino)) {
                    $archivos_guardados[] = [
                        'nombre_original' => $nombre_original,
                        'nombre_archivo' => $nombre_unico,
                        'extension' => $extension,
                        'tipo_mime' => $tipo_mime,
                        'tamanio' => $tamanio,
                        'fecha_subida' => date('Y-m-d H:i:s'),
                        'subido_por' => $nombre_usuario
                    ];
                } else {
                    error_log("Error al mover archivo: {$nombre_original}");
                }
            } elseif ($_FILES['archivos_apartado2']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                // Log de error si no es "no file"
                error_log("Error en archivo {$i}: " . $_FILES['archivos_apartado2']['error'][$i]);
            }
        }
    }

    // Preparar datos
    $datos = [
        'recibe_solicitud' => trim($_POST['recibe_solicitud']),
        'resumen_resultados' => trim($_POST['resumen_resultados']),
        'fecha_recibido' => $fecha_recibido,
        'hora_recibido' => $hora_recibido,
        'archivos_apartado2' => !empty($archivos_guardados) ? json_encode($archivos_guardados) : null
    ];

    // Log de la operación
    error_log("Usuario {$usuario_id} (Laboratorio) actualizando Apartado 2 del documento {$documento_id}");

    // Actualizar documento (SIN NOTIFICACIÓN SSE)
    $resultado = actualizar_apartado2($documento_id, $datos, $usuario_id, $nombre_usuario);

    // Limpiar output buffer y enviar respuesta limpia
    ob_end_clean();
    
    // Liberar sesión para evitar bloqueo con SSE
    session_write_close();

    // Enviar respuesta
    echo json_encode($resultado);

} catch (Exception $e) {
    // Capturar cualquier error y devolver JSON válido
    ob_end_clean();
    error_log("Error en procesar_apartado2.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
    ]);
}