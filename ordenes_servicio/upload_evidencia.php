<?php
/**
 * Procesador: Subir Archivos de Evidencia para Órdenes de Servicio
 * ⭐ CORREGIDO - Guarda archivos en Imagenes_OSM/ en la raíz del proyecto
 * Sin límite de archivos ni tamaño
 */

session_start();

// ⭐ CRÍTICO: Asegurar que SIEMPRE se devuelva JSON
header('Content-Type: application/json; charset=utf-8');

// Capturar y limpiar cualquier output inesperado
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Limpiar buffer de salida para evitar HTML/text antes del JSON
ob_clean();

// Verificar sesión
if (!sesion_activa()) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Sesión no válida'
    ]);
    exit;
}

// ⭐ RUTA CORREGIDA - Carpeta Imagenes_OSM en la raíz del proyecto
define('UPLOAD_DIR', __DIR__ . '/../Imagenes_OSM/');

// Extensiones permitidas (ampliado)
define('ALLOWED_EXTENSIONS', [
    // Imágenes
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg',
    // Documentos
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
    // Videos
    'mp4', 'avi', 'mov', 'mkv', 'wmv', 'webm',
    // Audio
    'mp3', 'wav', 'ogg', 'aac',
    // Comprimidos
    'zip', 'rar', '7z'
]);

// MIME types permitidos (ampliado)
define('ALLOWED_MIMES', [
    // Imágenes
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml',
    // Documentos
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain',
    'text/csv',
    // Videos
    'video/mp4', 'video/avi', 'video/quicktime', 'video/x-matroska', 'video/x-ms-wmv', 'video/webm',
    // Audio
    'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/aac',
    // Comprimidos
    'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
    // Genérico para archivos que el navegador no detecta bien
    'application/octet-stream'
]);

try {
    // Verificar que se enviaron archivos
    // Puede venir como 'archivos', 'evidencia_archivos' o 'evidencia_archivos[]'
    $campo_archivos = null;
    
    if (!empty($_FILES['archivos'])) {
        $campo_archivos = 'archivos';
    } elseif (!empty($_FILES['evidencia_archivos'])) {
        $campo_archivos = 'evidencia_archivos';
    }
    
    if ($campo_archivos === null) {
        throw new Exception("No se enviaron archivos");
    }
    
    $archivos = $_FILES[$campo_archivos];
    
    // Si es un solo archivo, convertir a array
    if (!is_array($archivos['name'])) {
        $archivos = [
            'name' => [$archivos['name']],
            'type' => [$archivos['type']],
            'tmp_name' => [$archivos['tmp_name']],
            'error' => [$archivos['error']],
            'size' => [$archivos['size']]
        ];
    }
    
    $total_archivos = count($archivos['name']);
    
    // ⭐ SIN LÍMITE DE ARCHIVOS - Comentado
    // if ($total_archivos > MAX_FILES) {
    //     throw new Exception("Solo se permiten máximo " . MAX_FILES . " archivos");
    // }
    
    // ⭐ Crear directorio Imagenes_OSM si no existe
    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            throw new Exception("No se pudo crear el directorio Imagenes_OSM");
        }
    }
    
    // Verificar permisos de escritura
    if (!is_writable(UPLOAD_DIR)) {
        throw new Exception("El directorio Imagenes_OSM no tiene permisos de escritura");
    }
    
    $archivos_subidos = [];
    $errores = [];
    
    // Obtener orden_id si se envió
    $orden_id = $_POST['orden_id'] ?? 'osm';
    
    // Procesar cada archivo
    for ($i = 0; $i < $total_archivos; $i++) {
        try {
            $nombre_original = $archivos['name'][$i];
            $nombre_temporal = $archivos['tmp_name'][$i];
            $tamaño = $archivos['size'][$i];
            $error = $archivos['error'][$i];
            $tipo_mime = $archivos['type'][$i];
            
            // Saltar archivos vacíos
            if (empty($nombre_original) || $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            // Verificar errores de subida
            if ($error !== UPLOAD_ERR_OK) {
                $errores_upload = [
                    UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
                    UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido por el formulario',
                    UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                    UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
                    UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
                    UPLOAD_ERR_EXTENSION => 'Extensión PHP detuvo la subida'
                ];
                throw new Exception($errores_upload[$error] ?? "Error desconocido al subir: {$nombre_original}");
            }
            
            // ⭐ SIN LÍMITE DE TAMAÑO - Comentado
            // if ($tamaño > MAX_FILE_SIZE) {
            //     throw new Exception("El archivo '{$nombre_original}' excede el tamaño máximo");
            // }
            
            if ($tamaño === 0) {
                throw new Exception("El archivo '{$nombre_original}' está vacío");
            }
            
            // Obtener extensión
            $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
            
            // Validar extensión
            if (!in_array($extension, ALLOWED_EXTENSIONS)) {
                throw new Exception("Extensión no permitida para '{$nombre_original}'");
            }
            
            // Validar MIME type (doble verificación)
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_real = finfo_file($finfo, $nombre_temporal);
                finfo_close($finfo);
                
                // Permitir application/octet-stream como fallback
                if (!in_array($mime_real, ALLOWED_MIMES) && $mime_real !== 'application/octet-stream') {
                    // Log pero no bloquear para ser más permisivo
                    error_log("MIME type inusual para '{$nombre_original}': {$mime_real}");
                }
            } else {
                $mime_real = $tipo_mime;
            }
            
            // Generar nombre único y seguro
            $nombre_limpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($nombre_original, PATHINFO_FILENAME));
            $nombre_limpio = substr($nombre_limpio, 0, 50); // Limitar longitud
            $hash = substr(md5(uniqid(rand(), true)), 0, 8);
            $nombre_guardado = 'osm_' . $orden_id . '_' . $nombre_limpio . '_' . $hash . '.' . $extension;
            
            $ruta_completa = UPLOAD_DIR . $nombre_guardado;
            
            // ⭐ RUTA RELATIVA CORREGIDA - Siempre Imagenes_OSM/
            $ruta_relativa = 'Imagenes_OSM/' . $nombre_guardado;
            
            // Mover archivo
            if (!move_uploaded_file($nombre_temporal, $ruta_completa)) {
                throw new Exception("No se pudo guardar el archivo '{$nombre_original}'");
            }
            
            // Establecer permisos
            @chmod($ruta_completa, 0644);
            
            // Agregar a lista de archivos subidos
            $archivos_subidos[] = [
                'nombre_original' => $nombre_original,
                'nombre_guardado' => $nombre_guardado,
                'ruta' => $ruta_relativa,
                'tipo' => $mime_real,
                'tamanio' => $tamaño,
                'extension' => $extension,
                'fecha_subida' => date('Y-m-d H:i:s')
            ];
            
            // Log exitoso
            error_log("OSM - Archivo subido: Usuario: {$_SESSION['nombre_completo']}, Archivo: {$nombre_original}, Ruta: {$ruta_relativa}");
            
        } catch (Exception $e) {
            $errores[] = $e->getMessage();
            error_log("OSM - Error al subir archivo: " . $e->getMessage());
        }
    }
    
    // Verificar si hubo al menos un archivo exitoso
    if (empty($archivos_subidos) && !empty($errores)) {
        throw new Exception("No se pudo subir ningún archivo. Errores: " . implode(', ', $errores));
    }
    
    // Respuesta exitosa
    $response = [
        'success' => true,
        'archivos' => $archivos_subidos,
        'total' => count($archivos_subidos),
        'mensaje' => 'Archivos subidos correctamente'
    ];
    
    if (!empty($errores)) {
        $response['errores'] = $errores;
        $response['mensaje'] = 'Algunos archivos se subieron con errores';
    }
    
    // Limpiar buffer y enviar JSON
    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("OSM - Error general al subir archivos: " . $e->getMessage());
    
    // Limpiar buffer y enviar error en JSON
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// Terminar ejecución limpiamente
exit;