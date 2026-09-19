<?php
/**
 * Funciones de evidencias (imágenes) del módulo SEC
 * Ubicación: includes/salidas_envases/sec_evidencias_funciones.php
 *
 * Reglas:
 *  - Solo imágenes: JPG, PNG, WebP.
 *  - Validación MIME real con mime_content_type() (no confía en extensión).
 *  - Hasta 20 MB por archivo.
 *  - Filesystem: uploads/sec_evidencias/{sec_id}/{timestamp}_{uniqid}.ext
 *  - Solo el autor puede eliminar su evidencia.
 */

require_once __DIR__ . '/../../config/database.php';
if (!defined('URL_BASE')) {
    require_once __DIR__ . '/../../config/config.php';
}
require_once __DIR__ . '/sec_historial_funciones.php';

// Configuración
if (!defined('SEC_EVIDENCIAS_DIR')) {
    define('SEC_EVIDENCIAS_DIR', __DIR__ . '/../../uploads/sec_evidencias');
}
if (!defined('SEC_EVIDENCIAS_MAX_BYTES')) {
    define('SEC_EVIDENCIAS_MAX_BYTES', 20 * 1024 * 1024); // 20 MB
}
if (!defined('SEC_EVIDENCIAS_MIMES_OK')) {
    define('SEC_EVIDENCIAS_MIMES_OK', 'image/jpeg|image/png|image/webp');
}
if (!defined('SEC_EVIDENCIAS_EXTS_OK')) {
    define('SEC_EVIDENCIAS_EXTS_OK', 'jpg|jpeg|png|webp');
}

/**
 * Sube una evidencia a la SEC. Recibe una entrada individual de $_FILES.
 * Valida existencia de SEC, tamaño, MIME real, y guarda en filesystem + BD.
 */
function subir_evidencia_sec($sec_id, $archivo, $usuario_id) {
    $sec_id = (int) $sec_id;

    // Validar SEC existe
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT id, folio FROM sec_salidas WHERE id = ?");
        $stmt->execute([$sec_id]);
        $sec = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sec) return ['ok' => false, 'msg' => 'SEC no encontrada.'];
    } catch (Exception $e) {
        error_log('subir_evidencia_sec (sec check): ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al validar SEC.'];
    }

    // Validar entrada de $_FILES
    if (!is_array($archivo) || empty($archivo['tmp_name']) || ($archivo['error'] ?? -1) !== UPLOAD_ERR_OK) {
        $err = $archivo['error'] ?? -1;
        $msg = 'Error al subir el archivo (código ' . $err . ').';
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            $msg = 'El archivo excede el tamaño máximo permitido por el servidor.';
        } elseif ($err === UPLOAD_ERR_PARTIAL) {
            $msg = 'La subida fue interrumpida. Intenta de nuevo.';
        } elseif ($err === UPLOAD_ERR_NO_FILE) {
            $msg = 'No se recibió ningún archivo.';
        }
        return ['ok' => false, 'msg' => $msg];
    }

    // Validar tamaño
    if ($archivo['size'] > SEC_EVIDENCIAS_MAX_BYTES) {
        $max_mb = round(SEC_EVIDENCIAS_MAX_BYTES / (1024 * 1024));
        return ['ok' => false, 'msg' => "El archivo excede el tamaño máximo ({$max_mb} MB)."];
    }

    // Validar MIME real
    $mime = @mime_content_type($archivo['tmp_name']) ?: '';
    if (!in_array($mime, explode('|', SEC_EVIDENCIAS_MIMES_OK), true)) {
        return ['ok' => false, 'msg' => 'Solo se permiten imágenes JPG, PNG o WebP.'];
    }

    // Determinar extensión desde MIME (más confiable que del nombre)
    $ext_por_mime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $ext_por_mime[$mime];

    // Crear directorio si no existe
    $dir = SEC_EVIDENCIAS_DIR . '/' . $sec_id;
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            error_log('subir_evidencia_sec: no se pudo crear ' . $dir);
            return ['ok' => false, 'msg' => 'Error al preparar el destino en el servidor.'];
        }
    }

    // Nombre único
    $nombre_final = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $ruta_disco   = $dir . '/' . $nombre_final;
    $ruta_relativa = 'uploads/sec_evidencias/' . $sec_id . '/' . $nombre_final;

    if (!@move_uploaded_file($archivo['tmp_name'], $ruta_disco)) {
        return ['ok' => false, 'msg' => 'Error al guardar el archivo en el servidor.'];
    }
    @chmod($ruta_disco, 0644);

    // Registrar en BD
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            INSERT INTO sec_evidencias
                (sec_id, ruta_archivo, nombre_original, tamanio_bytes, mime_type, subido_por, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $sec_id,
            $ruta_relativa,
            mb_substr($archivo['name'], 0, 255),
            (int) $archivo['size'],
            $mime,
            (int) $usuario_id,
        ]);
        $evidencia_id = (int) $pdo->lastInsertId();

        registrar_historial_sec(
            $sec_id, $usuario_id, 'evidencia_subida',
            "Evidencia subida: " . $archivo['name'],
            ['evidencia_id' => $evidencia_id, 'nombre' => $archivo['name']]
        );

        return ['ok' => true, 'evidencia_id' => $evidencia_id];
    } catch (Exception $e) {
        // Si falla la BD, borrar el archivo huérfano
        @unlink($ruta_disco);
        error_log('subir_evidencia_sec (bd): ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al registrar la evidencia en la base de datos.'];
    }
}

/**
 * Elimina una evidencia. Solo el autor puede.
 */
function eliminar_evidencia_sec($evidencia_id, $usuario_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT id, sec_id, ruta_archivo, subido_por, nombre_original
            FROM sec_evidencias WHERE id = ?
        ");
        $stmt->execute([(int) $evidencia_id]);
        $ev = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ev) return ['ok' => false, 'msg' => 'Evidencia no encontrada.'];

        if ((int) $ev['subido_por'] !== (int) $usuario_id) {
            return ['ok' => false, 'msg' => 'Solo puedes eliminar tus propias evidencias.'];
        }

        $stmt = $pdo->prepare("DELETE FROM sec_evidencias WHERE id = ?");
        $stmt->execute([(int) $evidencia_id]);

        // Borrar archivo (best effort)
        $ruta_disco = __DIR__ . '/../../' . $ev['ruta_archivo'];
        @unlink($ruta_disco);

        registrar_historial_sec(
            (int) $ev['sec_id'], $usuario_id, 'evidencia_eliminada',
            "Evidencia eliminada: " . $ev['nombre_original']
        );

        return ['ok' => true, 'sec_id' => (int) $ev['sec_id']];
    } catch (Exception $e) {
        error_log('eliminar_evidencia_sec: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al eliminar la evidencia.'];
    }
}

function obtener_evidencias_sec($sec_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT e.*, u.nombre_completo AS subido_por_nombre
            FROM sec_evidencias e
            LEFT JOIN usuarios u ON u.id = e.subido_por
            WHERE e.sec_id = ?
            ORDER BY e.fecha_creacion ASC, e.id ASC
        ");
        $stmt->execute([(int) $sec_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_evidencias_sec: ' . $e->getMessage());
        return [];
    }
}

function contar_evidencias_sec($sec_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sec_evidencias WHERE sec_id = ?");
        $stmt->execute([(int) $sec_id]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function evidencias_formatear_bytes($bytes) {
    if ($bytes < 1024)             return $bytes . ' B';
    if ($bytes < 1024 * 1024)      return round($bytes / 1024, 1) . ' KB';
    return round($bytes / (1024 * 1024), 1) . ' MB';
}