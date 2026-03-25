<?php
/**
 * Funciones auxiliares generales del sistema
 * Portal de Solicitudes TI
 * 
 * ⭐ ACTUALIZADO: Campos de Vacaciones (es_admin_area, es_usuario_gth)
 */

// ====================================
// FUNCIONES DE SEGURIDAD Y VALIDACIÓN
// ====================================

function limpiar_dato($dato) {
    $dato = trim($dato);
    $dato = stripslashes($dato);
    $dato = htmlspecialchars($dato, ENT_QUOTES, 'UTF-8');
    return $dato;
}

function validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validar_password($password) {
    return strlen($password) >= PASSWORD_MIN_LENGTH;
}

function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verificar_password($password, $hash) {
    return password_verify($password, $hash);
}

function generar_token($length = 32) {
    return bin2hex(random_bytes($length));
}

// ====================================
// FUNCIONES DE SESIÓN
// ====================================

/**
 * Iniciar sesión de usuario
 * ⭐ ACTUALIZADO: Incluye campos de vacaciones
 */
function iniciar_sesion_usuario($usuario) {
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario'] = $usuario['usuario'];
    $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
    $_SESSION['departamento'] = $usuario['departamento'];
    $_SESSION['departamento_codigo'] = $usuario['departamento_codigo'] ?? strtolower(trim($usuario['departamento']));
    $_SESSION['departamento_nombre'] = $usuario['departamento_nombre'] ?? ucfirst($usuario['departamento']);
    $_SESSION['departamento_id'] = $usuario['departamento_id'] ?? null;
    $_SESSION['es_ti'] = (strtolower($_SESSION['departamento_codigo']) === 'sistemas');
    $_SESSION['es_colaborativo'] = es_departamento_colaborativo($_SESSION['departamento_codigo']);
    
    // ⭐ Campos de vacaciones
    $_SESSION['no_nomina'] = $usuario['no_nomina'] ?? null;
    $_SESSION['puesto'] = $usuario['puesto'] ?? null;
    $_SESSION['fecha_ingreso'] = $usuario['fecha_ingreso'] ?? null;
    $_SESSION['es_admin_area'] = intval($usuario['es_admin_area'] ?? 0);
    
    $_SESSION['ultimo_acceso'] = time();
    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
}

function sesion_activa() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario']);
}

function sesion_expirada() {
    if (!isset($_SESSION['ultimo_acceso'])) {
        return true;
    }
    $tiempo_transcurrido = time() - $_SESSION['ultimo_acceso'];
    return $tiempo_transcurrido > SESION_TIEMPO_EXPIRACION;
}

function actualizar_sesion() {
    $_SESSION['ultimo_acceso'] = time();
}

function destruir_sesion() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

function es_usuario_ti() {
    return isset($_SESSION['es_ti']) && $_SESSION['es_ti'] === true;
}

function es_usuario_colaborativo() {
    return isset($_SESSION['es_colaborativo']) && $_SESSION['es_colaborativo'] === true;
}

function es_usuario_epp() {
    $depto = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
    return in_array($depto, ['almacen_refacciones', 'seguridad']);
}

/**
 * ⭐ Verificar si el usuario es administrador de área (módulo Vacaciones)
 * Para determinar si puede aprobar solicitudes de vacaciones
 */
function es_admin_area() {
    return isset($_SESSION['es_admin_area']) && $_SESSION['es_admin_area'] == 1;
}

/**
 * ⭐ Verificar si el usuario es de GTH o Contabilidad (módulo Vacaciones)
 * Estos departamentos tienen acceso al Panel GTH para aprobar vacaciones
 */
function es_usuario_gth() {
    $depto = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
    return in_array($depto, ['gestion_talento', 'contabilidad']);
}

function es_mantenimiento() {
    $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
    return ($departamento === 'mantenimiento');
}

// ====================================
// FUNCIONES DE REDIRECCIÓN
// ====================================

function redirigir($url) {
    header("Location: " . $url);
    exit();
}

function redirigir_login() {
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}

/**
 * Redirigir al dashboard según tipo de usuario
 * ⭐ Contabilidad y GTH redirigen a vacaciones_gth
 */
function redirigir_dashboard() {
    $depto_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento']));
    
    if ($depto_codigo === 'sistemas') {
        redirigir(URL_BASE . 'dashboard/sistemas/ti_sistemas.php');
    } elseif ($depto_codigo === 'mantenimiento') {
        redirigir(URL_BASE . 'dashboard/ordenes_servicio/mantenimiento.php');
    } elseif (in_array($depto_codigo, ['gestion_talento', 'contabilidad'])) {
        redirigir(URL_BASE . 'dashboard/gth/dashboard_gth.php');
    } elseif (es_usuario_colaborativo()) {
        redirigir(URL_BASE . 'dashboard/colaborativo/colaborativo.php');
    } else {
        redirigir(URL_BASE . 'dashboard/departamento.php');
    }
}

// ====================================
// FUNCIONES DE MENSAJES Y ALERTAS
// ====================================

function establecer_alerta($tipo, $mensaje) {
    $_SESSION['alerta'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje
    ];
}

function obtener_alerta() {
    if (isset($_SESSION['alerta'])) {
        $alerta = $_SESSION['alerta'];
        unset($_SESSION['alerta']);
        return $alerta;
    }
    return null;
}

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

function validar_archivo($archivo) {
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['valido' => false, 'error' => 'Error al subir el archivo'];
    }
    
    if ($archivo['size'] > MAX_FILE_SIZE) {
        $max_mb = MAX_FILE_SIZE / (1024 * 1024);
        return ['valido' => false, 'error' => 'El archivo excede el tamaño máximo permitido (' . $max_mb . 'MB)'];
    }
    
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        return ['valido' => false, 'error' => 'Tipo de archivo no permitido: ' . $extension];
    }
    
    return ['valido' => true, 'error' => ''];
}

function subir_archivo($archivo, $carpeta = 'solicitudes') {
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
    
    if (!file_exists($ruta_destino)) {
        mkdir($ruta_destino, 0755, true);
    }
    
    if (move_uploaded_file($archivo['tmp_name'], $ruta_destino . $nombre_unico)) {
        return $nombre_unico;
    }
    
    return false;
}

function archivo_existe($nombre_archivo, $carpeta) {
    $ruta = DIR_UPLOADS . $carpeta . '/' . $nombre_archivo;
    return file_exists($ruta);
}

function obtener_ruta_archivo($nombre_archivo, $carpeta) {
    $ruta = DIR_UPLOADS . $carpeta . '/' . $nombre_archivo;
    return file_exists($ruta) ? $ruta : false;
}

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

function eliminar_archivo($nombre_archivo, $carpeta) {
    $ruta = obtener_ruta_archivo($nombre_archivo, $carpeta);
    
    if (!$ruta) {
        return false;
    }
    
    return unlink($ruta);
}

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

function puede_descargar_archivo($archivo_id, $tipo_archivo = 'solicitud') {
    if (!sesion_activa()) {
        return false;
    }
    
    if ($tipo_archivo === 'colaborativo') {
        return es_usuario_colaborativo() || es_usuario_ti();
    }
    
    return true;
}

// ====================================
// FUNCIONES DE FORMATO Y UTILIDADES
// ====================================

function formatear_fecha($fecha, $incluir_hora = false) {
    $timestamp = strtotime($fecha);
    
    if ($incluir_hora) {
        return date('d/m/Y H:i', $timestamp);
    }
    
    return date('d/m/Y', $timestamp);
}

function obtener_clase_estado($estado) {
    $clases = [
        ESTADO_PENDIENTE => 'bg-warning text-dark',
        ESTADO_EN_PROCESO => 'bg-primary',
        ESTADO_FINALIZADA => 'bg-success',
        ESTADO_CANCELADA => 'bg-secondary'
    ];
    
    return isset($clases[$estado]) ? $clases[$estado] : 'bg-secondary';
}

function obtener_texto_estado($estado) {
    $textos = [
        ESTADO_PENDIENTE => 'Pendiente',
        ESTADO_EN_PROCESO => 'En Proceso',
        ESTADO_FINALIZADA => 'Finalizada',
        ESTADO_CANCELADA => 'Cancelada'
    ];
    
    return isset($textos[$estado]) ? $textos[$estado] : 'Desconocido';
}

function generar_folio($prefijo = 'SOL') {
    return $prefijo . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function sanitizar_nombre_archivo($nombre) {
    $nombre = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombre);
    $nombre = preg_replace('/_+/', '_', $nombre);
    return substr($nombre, 0, 100);
}

// ====================================
// FUNCIONES DE PAGINACIÓN
// ====================================

function calcular_offset($pagina, $por_pagina) {
    return ($pagina - 1) * $por_pagina;
}

function calcular_total_paginas($total_registros, $por_pagina) {
    return ceil($total_registros / $por_pagina);
}

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