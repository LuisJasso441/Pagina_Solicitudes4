<?php
/**
 * Funciones auxiliares generales del sistema
 * Portal de Solicitudes TI
 */

// ====================================
// FUNCIONES DE SEGURIDAD Y VALIDACIÓN
// ====================================

/**
 * Limpiar datos de entrada para prevenir XSS
 * @param string $dato - Dato a limpiar
 * @return string - Dato limpio
 */
function limpiar_dato($dato) {
    $dato = trim($dato);
    $dato = stripslashes($dato);
    $dato = htmlspecialchars($dato, ENT_QUOTES, 'UTF-8');
    return $dato;
}

/**
 * Validar email
 * @param string $email - Email a validar
 * @return bool
 */
function validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar longitud de contraseña
 * @param string $password - Contraseña a validar
 * @return bool
 */
function validar_password($password) {
    return strlen($password) >= PASSWORD_MIN_LENGTH;
}

/**
 * Generar hash seguro de contraseña
 * @param string $password - Contraseña en texto plano
 * @return string - Hash de la contraseña
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verificar contraseña contra hash
 * @param string $password - Contraseña en texto plano
 * @param string $hash - Hash almacenado
 * @return bool
 */
function verificar_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generar token aleatorio seguro
 * @param int $length - Longitud del token
 * @return string
 */
function generar_token($length = 32) {
    return bin2hex(random_bytes($length));
}

// ====================================
// FUNCIONES DE SESIÓN
// ====================================

/**
 * Iniciar sesión de usuario
 * @param array $usuario - Datos del usuario
 */
function iniciar_sesion_usuario($usuario) {
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario'] = $usuario['usuario'];
    $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
    $_SESSION['departamento'] = $usuario['departamento'];
    $_SESSION['departamento_nombre'] = obtener_nombre_departamento($usuario['departamento']);
    $_SESSION['es_ti'] = es_departamento_ti($usuario['departamento']);
    $_SESSION['es_colaborativo'] = es_departamento_colaborativo($usuario['departamento']);
    $_SESSION['ultimo_acceso'] = time();
    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
}

/**
 * Verificar si hay sesión activa
 * @return bool
 */
function sesion_activa() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario']);
}

/**
 * Verificar si la sesión ha expirado
 * @return bool
 */
function sesion_expirada() {
    if (!isset($_SESSION['ultimo_acceso'])) {
        return true;
    }
    
    $tiempo_transcurrido = time() - $_SESSION['ultimo_acceso'];
    return $tiempo_transcurrido > SESION_TIEMPO_EXPIRACION;
}

/**
 * Actualizar tiempo de última actividad
 */
function actualizar_sesion() {
    $_SESSION['ultimo_acceso'] = time();
}

/**
 * Destruir sesión completamente
 */
function destruir_sesion() {
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Verificar si el usuario es de TI/Sistemas
 * @return bool
 */
function es_usuario_ti() {
    return isset($_SESSION['es_ti']) && $_SESSION['es_ti'] === true;
}

/**
 * Verificar si el usuario pertenece a departamento colaborativo
 * @return bool
 */
function es_usuario_colaborativo() {
    return isset($_SESSION['es_colaborativo']) && $_SESSION['es_colaborativo'] === true;
}

// ====================================
// FUNCIONES DE REDIRECCIÓN
// ====================================

/**
 * Redirigir a una URL
 * @param string $url - URL de destino
 */
function redirigir($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Redirigir al login
 */
function redirigir_login() {
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}

/**
 * Redirigir al dashboard según tipo de usuario
 */
function redirigir_dashboard() {
    if (es_usuario_ti()) {
        redirigir(URL_BASE . 'dashboard/ti_sistemas.php');
    } elseif (es_usuario_colaborativo()) {
        redirigir(URL_BASE . 'dashboard/colaborativo.php');
    } else {
        redirigir(URL_BASE . 'dashboard/departamento.php');
    }
}

// ====================================
// FUNCIONES DE MENSAJES Y ALERTAS
// ====================================

/**
 * Establecer mensaje de alerta en sesión
 * @param string $tipo - Tipo: 'success', 'error', 'warning', 'info'
 * @param string $mensaje - Mensaje a mostrar
 */
function establecer_alerta($tipo, $mensaje) {
    $_SESSION['alerta'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje
    ];
}

/**
 * Obtener y limpiar mensaje de alerta
 * @return array|null - Array con tipo y mensaje, o null
 */
function obtener_alerta() {
    if (isset($_SESSION['alerta'])) {
        $alerta = $_SESSION['alerta'];
        unset($_SESSION['alerta']);
        return $alerta;
    }
    return null;
}

/**
 * Mostrar alerta HTML (Bootstrap)
 * @return string - HTML de la alerta
 */
function mostrar_alerta() {
    $alerta = obtener_alerta();
    if ($alerta === null) {
        return '';
    }
    
    $tipo_clase = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $clase = isset($tipo_clase[$alerta['tipo']]) ? $tipo_clase[$alerta['tipo']] : 'alert-info';
    
    return '<div class="alert ' . $clase . ' alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($alerta['mensaje']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

// ====================================
// FUNCIONES DE VALIDACIÓN DE ARCHIVOS
// ====================================

/**
 * Validar archivo subido
 * @param array $archivo - Archivo de $_FILES
 * @return array - ['valido' => bool, 'error' => string]
 */
function validar_archivo($archivo) {
    // Verificar si hay error en la subida
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['valido' => false, 'error' => 'Error al subir el archivo'];
    }
    
    // Verificar tamaño
    if ($archivo['size'] > MAX_FILE_SIZE) {
        $max_mb = MAX_FILE_SIZE / (1024 * 1024);
        return ['valido' => false, 'error' => 'El archivo excede el tamaño máximo permitido (' . $max_mb . 'MB)'];
    }
    
    // Verificar extensión
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        return ['valido' => false, 'error' => 'Tipo de archivo no permitido'];
    }
    
    // Verificar MIME type (seguridad adicional)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, ALLOWED_MIME_TYPES)) {
        return ['valido' => false, 'error' => 'Tipo de archivo no válido'];
    }
    
    return ['valido' => true, 'error' => ''];
}

/**
 * Subir archivo y retornar nombre único
 * @param array $archivo - Archivo de $_FILES
 * @param string $carpeta - Carpeta de destino dentro de uploads/
 * @return string|false - Nombre del archivo o false si falla
 */
function subir_archivo($archivo, $carpeta) {
    $validacion = validar_archivo($archivo);
    
    if (!$validacion['valido']) {
        establecer_alerta('error', $validacion['error']);
        return false;
    }
    
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $nombre_original = pathinfo($archivo['name'], PATHINFO_FILENAME);
    $nombre_original = sanitizar_nombre_archivo($nombre_original);
    $nombre_unico = $nombre_original . '_' . uniqid() . '_' . time() . '.' . $extension;
    
    $ruta_destino = DIR_UPLOADS . $carpeta . '/';
    
    // Crear carpeta si no existe
    if (!file_exists($ruta_destino)) {
        mkdir($ruta_destino, 0755, true);
    }
    
    if (move_uploaded_file($archivo['tmp_name'], $ruta_destino . $nombre_unico)) {
        return $nombre_unico;
    }
    
    return false;
}

/**
 * Verificar si un archivo existe en el sistema
 * @param string $nombre_archivo - Nombre del archivo
 * @param string $carpeta - Carpeta donde buscar
 * @return bool
 */
function archivo_existe($nombre_archivo, $carpeta) {
    $ruta = DIR_UPLOADS . $carpeta . '/' . $nombre_archivo;
    return file_exists($ruta);
}

/**
 * Obtener ruta completa del archivo
 * @param string $nombre_archivo - Nombre del archivo
 * @param string $carpeta - Carpeta donde está el archivo
 * @return string|false - Ruta completa o false si no existe
 */
function obtener_ruta_archivo($nombre_archivo, $carpeta) {
    $ruta = DIR_UPLOADS . $carpeta . '/' . $nombre_archivo;
    return file_exists($ruta) ? $ruta : false;
}

/**
 * Obtener tamaño del archivo en formato legible
 * @param string $nombre_archivo - Nombre del archivo
 * @param string $carpeta - Carpeta donde está el archivo
 * @return string - Tamaño formateado
 */
function obtener_tamano_archivo($nombre_archivo, $carpeta) {
    $ruta = obtener_ruta_archivo($nombre_archivo, $carpeta);
    
    if (!$ruta) {
        return '0 B';
    }
    
    $bytes = filesize($ruta);
    
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

/**
 * Eliminar archivo del sistema
 * @param string $nombre_archivo - Nombre del archivo
 * @param string $carpeta - Carpeta donde está el archivo
 * @return bool
 */
function eliminar_archivo($nombre_archivo, $carpeta) {
    $ruta = obtener_ruta_archivo($nombre_archivo, $carpeta);
    
    if (!$ruta) {
        return false;
    }
    
    return unlink($ruta);
}

/**
 * Obtener icono según tipo de archivo
 * @param string $nombre_archivo - Nombre del archivo
 * @return string - Clase de icono Bootstrap
 */
function obtener_icono_archivo($nombre_archivo) {
    $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
    
    $iconos = [
        'pdf' => 'bi-file-pdf text-danger',
        'doc' => 'bi-file-word text-primary',
        'docx' => 'bi-file-word text-primary',
        'xls' => 'bi-file-excel text-success',
        'xlsx' => 'bi-file-excel text-success',
        'ppt' => 'bi-file-ppt text-warning',
        'pptx' => 'bi-file-ppt text-warning',
        'jpg' => 'bi-file-image text-info',
        'jpeg' => 'bi-file-image text-info',
        'png' => 'bi-file-image text-info',
        'gif' => 'bi-file-image text-info',
        'zip' => 'bi-file-zip text-secondary',
        'rar' => 'bi-file-zip text-secondary',
        'txt' => 'bi-file-text text-muted'
    ];
    
    return isset($iconos[$extension]) ? $iconos[$extension] : 'bi-file-earmark text-muted';
}

/**
 * Verificar si el usuario tiene permiso para descargar el archivo
 * @param int $archivo_id - ID del archivo (para consultar BD en futuro)
 * @param string $tipo_archivo - 'solicitud' o 'colaborativo'
 * @return bool
 */
function puede_descargar_archivo($archivo_id, $tipo_archivo = 'solicitud') {
    // Por ahora, permitir descarga si hay sesión activa
    if (!sesion_activa()) {
        return false;
    }
    
    // Si es archivo colaborativo, solo departamentos colaborativos pueden descargar
    if ($tipo_archivo === 'colaborativo') {
        return es_usuario_colaborativo() || es_usuario_ti();
    }
    
    // Para solicitudes, el usuario puede descargar sus propios archivos
    // TODO: Cuando tengamos BD, verificar que el archivo pertenezca al usuario o a TI
    return true;
}

// ====================================
// FUNCIONES DE FORMATO Y UTILIDADES
// ====================================

/**
 * Formatear fecha en español
 * @param string $fecha - Fecha en formato MySQL
 * @param bool $incluir_hora - Incluir hora
 * @return string
 */
function formatear_fecha($fecha, $incluir_hora = false) {
    $timestamp = strtotime($fecha);
    
    if ($incluir_hora) {
        return date('d/m/Y H:i', $timestamp);
    }
    
    return date('d/m/Y', $timestamp);
}

/**
 * Obtener clase de badge según estado
 * @param string $estado - Estado de la solicitud
 * @return string - Clase CSS
 */
function obtener_clase_estado($estado) {
    $clases = [
        ESTADO_PENDIENTE => 'bg-warning text-dark',
        ESTADO_EN_PROCESO => 'bg-primary',
        ESTADO_FINALIZADA => 'bg-success',
        ESTADO_CANCELADA => 'bg-secondary'
    ];
    
    return isset($clases[$estado]) ? $clases[$estado] : 'bg-secondary';
}

/**
 * Obtener texto legible del estado
 * @param string $estado - Estado de la solicitud
 * @return string
 */
function obtener_texto_estado($estado) {
    $textos = [
        ESTADO_PENDIENTE => 'Pendiente',
        ESTADO_EN_PROCESO => 'En Proceso',
        ESTADO_FINALIZADA => 'Finalizada',
        ESTADO_CANCELADA => 'Cancelada'
    ];
    
    return isset($textos[$estado]) ? $textos[$estado] : 'Desconocido';
}

/**
 * Generar número de folio único
 * @param string $prefijo - Prefijo del folio
 * @return string
 */
function generar_folio($prefijo = 'SOL') {
    return $prefijo . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Sanitizar nombre de archivo
 * @param string $nombre - Nombre del archivo
 * @return string
 */
function sanitizar_nombre_archivo($nombre) {
    // Eliminar caracteres especiales y espacios
    $nombre = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombre);
    // Eliminar guiones bajos múltiples
    $nombre = preg_replace('/_+/', '_', $nombre);
    // Limitar longitud
    return substr($nombre, 0, 100);
}

// ====================================
// FUNCIONES DE PAGINACIÓN
// ====================================

/**
 * Calcular offset para paginación
 * @param int $pagina - Página actual
 * @param int $por_pagina - Registros por página
 * @return int - Offset
 */
function calcular_offset($pagina, $por_pagina) {
    return ($pagina - 1) * $por_pagina;
}

/**
 * Calcular total de páginas
 * @param int $total_registros - Total de registros
 * @param int $por_pagina - Registros por página
 * @return int - Total de páginas
 */
function calcular_total_paginas($total_registros, $por_pagina) {
    return ceil($total_registros / $por_pagina);
}

/**
 * Obtener fecha actual en español (formato completo)
 * Reemplazo moderno de strftime()
 * @return string - Fecha en formato: "Jueves, 02 de Octubre de 2025"
 */
function obtener_fecha_actual_espanol() {
    $dias = [
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sábado',
        'Sunday' => 'Domingo'
    ];
    
    $meses = [
        'January' => 'Enero',
        'February' => 'Febrero',
        'March' => 'Marzo',
        'April' => 'Abril',
        'May' => 'Mayo',
        'June' => 'Junio',
        'July' => 'Julio',
        'August' => 'Agosto',
        'September' => 'Septiembre',
        'October' => 'Octubre',
        'November' => 'Noviembre',
        'December' => 'Diciembre'
    ];
    
    $fecha = new DateTime();
    $dia_semana = $dias[$fecha->format('l')];
    $dia = $fecha->format('d');
    $mes = $meses[$fecha->format('F')];
    $anio = $fecha->format('Y');
    
    return "$dia_semana, $dia de $mes de $anio";
}

?>