<?php
/**
 * Procesar actualización del Apartado 2
 * Solo usuarios de Laboratorio
 * ⭐ VERSIÓN 2 - Preserva archivos existentes, reporta errores Y evita duplicados
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

    // ⭐ FUNCIÓN: Limpiar y normalizar formato de hora
    function limpiar_formato_hora($hora) {
        if (empty($hora)) return '';
        
        $hora = trim($hora);
        
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora)) {
            return $hora;
        }
        
        $es_pm = preg_match('/p\.?\s*m\.?/i', $hora);
        $es_am = preg_match('/a\.?\s*m\.?/i', $hora);
        
        if (preg_match('/(\d{1,2}):(\d{2})/', $hora, $matches)) {
            $horas = intval($matches[1]);
            $minutos = $matches[2];
            
            if ($es_pm && $horas < 12) {
                $horas += 12;
            } elseif ($es_am && $horas == 12) {
                $horas = 0;
            }
            
            return sprintf('%02d:%s', $horas, $minutos);
        }
        
        $timestamp = strtotime($hora);
        if ($timestamp !== false) {
            return date('H:i', $timestamp);
        }
        
        return $hora;
    }

    // ⭐ FUNCIÓN: Obtener mensaje de error de upload legible
    function obtener_mensaje_error_upload($error_code) {
        $mensajes = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo especificado en el formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo fue subido parcialmente. Intente de nuevo.',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Error del servidor: Falta carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error del servidor: No se pudo escribir el archivo',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo'
        ];
        return $mensajes[$error_code] ?? "Error desconocido (código: {$error_code})";
    }

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

    // Validar formato de fecha recibido
    $fecha_recibido = $_POST['fecha_recibido'];
    $hora_recibido = $_POST['hora_recibido'];

    if (!strtotime($fecha_recibido)) {
        echo json_encode(['success' => false, 'message' => 'Formato de fecha de recibido no válido']);
        exit;
    }

    $hora_recibido = limpiar_formato_hora($hora_recibido);
    
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora_recibido)) {
        echo json_encode(['success' => false, 'message' => 'Formato de hora de recibido no válido (HH:MM)']);
        exit;
    }

    $timestamp_fecha = strtotime($fecha_recibido);
    $hace_un_ano = strtotime('-1 year');
    if ($timestamp_fecha < $hace_un_ano) {
        echo json_encode(['success' => false, 'message' => 'La fecha de recibido no puede ser anterior a hace un año']);
        exit;
    }

    // Validar fecha y hora de entrega (opcionales)
    $fecha_entrega = null;
    $hora_entrega = null;
    
    if (!empty($_POST['fecha_entrega'])) {
        if (!strtotime($_POST['fecha_entrega'])) {
            echo json_encode(['success' => false, 'message' => 'Formato de fecha de entrega no válido']);
            exit;
        }
        $fecha_entrega = $_POST['fecha_entrega'];
    }
    
    if (!empty($_POST['hora_entrega'])) {
        $hora_entrega = limpiar_formato_hora($_POST['hora_entrega']);
        
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora_entrega)) {
            echo json_encode(['success' => false, 'message' => 'Formato de hora de entrega no válido (HH:MM)']);
            exit;
        }
    }

    // ===== MANEJO DE ARCHIVOS =====
    $archivos_nuevos = [];
    $errores_archivos = [];

    // ⭐ OBTENER ARCHIVOS EXISTENTES DE LA BASE DE DATOS
    $archivos_existentes = [];
    if (!empty($documento['archivos_apartado2'])) {
        $archivos_existentes = json_decode($documento['archivos_apartado2'], true);
        if (!is_array($archivos_existentes)) {
            $archivos_existentes = [];
        }
    }

    // ⭐ CREAR ÍNDICE DE ARCHIVOS EXISTENTES POR nombre_archivo (para evitar duplicados)
    $nombres_existentes = [];
    foreach ($archivos_existentes as $archivo) {
        if (!empty($archivo['nombre_archivo'])) {
            $nombres_existentes[$archivo['nombre_archivo']] = true;
        }
    }

    // Procesar nuevos archivos si se subieron
    if (!empty($_FILES['archivos_apartado2']['name'][0])) {
        $directorio_destino = __DIR__ . '/../Imagenes_SSC/';
        
        if (!is_dir($directorio_destino)) {
            if (!mkdir($directorio_destino, 0755, true)) {
                error_log("Error: No se pudo crear directorio {$directorio_destino}");
                echo json_encode(['success' => false, 'message' => 'No se pudo crear el directorio de archivos']);
                exit;
            }
        }
        
        $total_archivos = count($_FILES['archivos_apartado2']['name']);
        
        for ($i = 0; $i < $total_archivos; $i++) {
            $error_code = $_FILES['archivos_apartado2']['error'][$i];
            $nombre_original = basename($_FILES['archivos_apartado2']['name'][$i]);
            
            // Reportar errores de upload al usuario
            if ($error_code !== UPLOAD_ERR_OK) {
                if ($error_code !== UPLOAD_ERR_NO_FILE) {
                    $mensaje_error = obtener_mensaje_error_upload($error_code);
                    $errores_archivos[] = "Error en '{$nombre_original}': {$mensaje_error}";
                    error_log("Error de upload en archivo '{$nombre_original}': código {$error_code}");
                }
                continue;
            }
            
            $nombre_temporal = $_FILES['archivos_apartado2']['tmp_name'][$i];
            $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
            $tipo_mime = $_FILES['archivos_apartado2']['type'][$i];
            $tamanio = $_FILES['archivos_apartado2']['size'][$i];
            
            // Generar nombre único
            $nombre_unico = uniqid('ssc_' . $documento_id . '_', true) . '.' . $extension;
            $ruta_destino = $directorio_destino . $nombre_unico;
            
            // ⭐ VERIFICAR QUE NO EXISTA YA (por si acaso)
            if (isset($nombres_existentes[$nombre_unico])) {
                error_log("Archivo ya existe, saltando: {$nombre_unico}");
                continue;
            }
            
            // Mover archivo
            if (move_uploaded_file($nombre_temporal, $ruta_destino)) {
                $archivos_nuevos[] = [
                    'nombre_original' => $nombre_original,
                    'nombre_archivo' => $nombre_unico,
                    'extension' => $extension,
                    'tipo_mime' => $tipo_mime,
                    'tamanio' => $tamanio,
                    'fecha_subida' => date('Y-m-d H:i:s'),
                    'subido_por' => $nombre_usuario
                ];
                
                // Agregar al índice para evitar duplicados en el mismo request
                $nombres_existentes[$nombre_unico] = true;
            } else {
                $errores_archivos[] = "No se pudo guardar '{$nombre_original}' en el servidor";
                error_log("Error al mover archivo: {$nombre_original} a {$ruta_destino}");
            }
        }
    }

    // ⭐ COMBINAR: archivos existentes + solo los nuevos que realmente se subieron
    // NO habrá duplicados porque los nuevos tienen nombres únicos generados con uniqid()
    $todos_los_archivos = array_merge($archivos_existentes, $archivos_nuevos);

    // Preparar datos
    $datos = [
        'recibe_solicitud' => trim($_POST['recibe_solicitud']),
        'resumen_resultados' => trim($_POST['resumen_resultados']),
        'fecha_recibido' => $fecha_recibido,
        'hora_recibido' => $hora_recibido,
        'archivos_apartado2' => !empty($todos_los_archivos) ? json_encode($todos_los_archivos) : null,
        'fecha_entrega' => $fecha_entrega,
        'hora_entrega' => $hora_entrega
    ];

    // Log de la operación
    error_log("Usuario {$usuario_id} (Laboratorio) actualizando Apartado 2 del documento {$documento_id}");
    error_log("Archivos: existentes=" . count($archivos_existentes) . ", nuevos=" . count($archivos_nuevos) . ", total=" . count($todos_los_archivos));

    // Actualizar documento
    $resultado = actualizar_apartado2($documento_id, $datos, $usuario_id, $nombre_usuario);

    // Añadir info de archivos al resultado
    if ($resultado['success']) {
        $resultado['archivos_nuevos'] = count($archivos_nuevos);
        $resultado['archivos_totales'] = count($todos_los_archivos);
        
        if (!empty($errores_archivos)) {
            $resultado['advertencias'] = $errores_archivos;
            $resultado['message'] = ($resultado['message'] ?? 'Apartado 2 guardado') . 
                '. ADVERTENCIA: Algunos archivos no se subieron: ' . implode('; ', $errores_archivos);
        }
    }

    // Limpiar output buffer y enviar respuesta
    ob_end_clean();
    session_write_close();

    echo json_encode($resultado);

} catch (Exception $e) {
    ob_end_clean();
    error_log("Error en procesar_apartado2.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
    ]);
}