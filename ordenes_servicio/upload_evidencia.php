<?php
/**
 * Procesador: Subir Archivos de Evidencia
 * Maneja la subida de archivos (fotos, documentos) para evidencia de fallas
 * Versión corregida para devolver SIEMPRE JSON válido
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

// Configuración
define('UPLOAD_DIR', __DIR__ . '/../uploads/ordenes_servicio/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('MAX_FILES', 5);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);
define('ALLOWED_MIMES', [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);

try {
    // Verificar que se enviaron archivos
    if (empty($_FILES['archivos'])) {
        throw new Exception("No se enviaron archivos");
    }
    
    $archivos = $_FILES['archivos'];
    
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
    
    // Validar cantidad de archivos
    if ($total_archivos > MAX_FILES) {
        throw new Exception("Solo se permiten máximo " . MAX_FILES . " archivos");
    }
    
    // Crear estructura de directorios por año/mes
    $año = date('Y');
    $mes = date('m');
    $dir_destino = UPLOAD_DIR . $año . '/' . $mes . '/';
    
    if (!is_dir($dir_destino)) {
        if (!mkdir($dir_destino, 0755, true)) {
            throw new Exception("No se pudo crear el directorio de destino");
        }
    }
    
    // Verificar permisos de escritura
    if (!is_writable($dir_destino)) {
        throw new Exception("El directorio no tiene permisos de escritura");
    }
    
    $archivos_subidos = [];
    $errores = [];
    
    // Procesar cada archivo
    for ($i = 0; $i < $total_archivos; $i++) {
        try {
            $nombre_original = $archivos['name'][$i];
            $nombre_temporal = $archivos['tmp_name'][$i];
            $tamaño = $archivos['size'][$i];
            $error = $archivos['error'][$i];
            $tipo_mime = $archivos['type'][$i];
            
            // Verificar errores de subida
            if ($error !== UPLOAD_ERR_OK) {
                throw new Exception("Error al subir el archivo: {$nombre_original}");
            }
            
            // Validar tamaño
            if ($tamaño > MAX_FILE_SIZE) {
                throw new Exception("El archivo '{$nombre_original}' excede el tamaño máximo de 10 MB");
            }
            
            if ($tamaño === 0) {
                throw new Exception("El archivo '{$nombre_original}' está vacío");
            }
            
            // Obtener extensión
            $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
            
            // Validar extensión
            if (!in_array($extension, ALLOWED_EXTENSIONS)) {
                throw new Exception("Extensión no permitida para '{$nombre_original}'. Solo: " . implode(', ', ALLOWED_EXTENSIONS));
            }
            
            // Validar MIME type (doble verificación)
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_real = finfo_file($finfo, $nombre_temporal);
                finfo_close($finfo);
                
                if (!in_array($mime_real, ALLOWED_MIMES)) {
                    // Permitir también image/jpg que algunos navegadores usan
                    if ($mime_real !== 'image/jpg') {
                        throw new Exception("Tipo de archivo no permitido para '{$nombre_original}'");
                    }
                }
            } else {
                // Si finfo no está disponible, usar el tipo MIME del navegador
                $mime_real = $tipo_mime;
            }
            
            // Generar nombre único y seguro
            $nombre_limpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($nombre_original, PATHINFO_FILENAME));
            $nombre_limpio = substr($nombre_limpio, 0, 50); // Limitar longitud
            $hash = substr(md5(uniqid(rand(), true)), 0, 8);
            $nombre_guardado = $nombre_limpio . '_' . $hash . '.' . $extension;
            
            $ruta_completa = $dir_destino . $nombre_guardado;
            $ruta_relativa = 'uploads/ordenes_servicio/' . $año . '/' . $mes . '/' . $nombre_guardado;
            
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
                'ruta_relativa' => $ruta_relativa, // Alias para compatibilidad
                'tipo' => $mime_real,
                'size' => $tamaño,
                'tamano' => $tamaño, // Alias para compatibilidad
                'fecha_subida' => date('Y-m-d H:i:s')
            ];
            
            // Log exitoso
            error_log("Archivo subido exitosamente - Usuario: {$_SESSION['nombre_completo']}, Archivo: {$nombre_original}, Ruta: {$ruta_relativa}");
            
        } catch (Exception $e) {
            $errores[] = $e->getMessage();
            error_log("Error al subir archivo individual: " . $e->getMessage());
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
    error_log("Error general al subir archivos: " . $e->getMessage());
    
    // Limpiar buffer y enviar error en JSON
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// Terminar ejecución limpiamente
exit;