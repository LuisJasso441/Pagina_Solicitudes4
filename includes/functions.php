<?php
/**
 * Funciones auxiliares generales del sistema
 * Portal de Solicitudes TI
 */

// ====================================
// FUNCIONES DE SEGURIDAD Y VALIDACION
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
// FUNCIONES DE SESION
// ====================================

/**
 * Iniciar sesion de usuario
 * Incluye campos de vacaciones: no_nomina, puesto, fecha_ingreso, es_admin_area
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
    // Campos de vacaciones
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
 * Verificar si el usuario es administrador de area (modulo Vacaciones)
 * Para determinar si puede aprobar solicitudes de vacaciones
 */
function es_admin_area() {
    return isset($_SESSION['es_admin_area']) && $_SESSION['es_admin_area'] == 1;
}

/**
 * Verificar si el usuario es de GTH o Contabilidad (modulo Vacaciones)
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
// FUNCIONES DE REDIRECCION
// ====================================

function redirigir($url) {
    header("Location: " . $url);
    exit();
}

function redirigir_login() {
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}

/**
 * Redirigir al dashboard segun tipo de usuario
 * Contabilidad y GTH redirigen a vacaciones_gth
 */
function redirigir_dashboard() {
    $depto_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento']));
    
    if ($depto_codigo === 'sistemas') {
        redirigir(URL_BASE . 'dashboard/sistemas/ti_sistemas.php');
    } elseif ($depto_codigo === 'mantenimiento') {
        redirigir(URL_BASE . 'dashboard/ordenes_servicio/mantenimiento.php');
    } elseif (es_usuario_colaborativo()) {
        redirigir(URL_BASE . 'dashboard/colaborativo/colaborativo.php');
    } elseif (in_array($depto_codigo, ['almacen_refacciones', 'seguridad'])) {
        redirigir(URL_BASE . 'dashboard/inventario_epp/dashboard_epp.php');
    } elseif (in_array($depto_codigo, ['gestion_talento', 'contabilidad'])) {
        redirigir(URL_BASE . 'dashboard/vacaciones/vacaciones_gth.php');
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
    if (!$alerta) return '';
    
    $tipo = $alerta['tipo'] === 'error' ? 'danger' : $alerta['tipo'];
    $icono = match($tipo) {
        'success' => 'check-circle',
        'danger'  => 'exclamation-circle',
        'warning' => 'exclamation-triangle',
        'info'    => 'info-circle',
        default   => 'info-circle'
    };
    
    return '<div class="alert alert-' . $tipo . ' alert-dismissible fade show" role="alert">
        <i class="bi bi-' . $icono . ' me-2"></i>' . $alerta['mensaje'] . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}

// ====================================
// FUNCIONES DE FORMATO
// ====================================

function formatear_fecha($fecha) {
    if (empty($fecha)) return '-';
    
    $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $timestamp = strtotime($fecha);
    if ($timestamp === false) return $fecha;
    
    $dia = date('d', $timestamp);
    $mes = $meses[date('n', $timestamp) - 1];
    $anio = date('Y', $timestamp);
    $hora = date('H:i', $timestamp);
    
    return "$dia $mes $anio, $hora";
}

function formatear_fecha_corta($fecha) {
    if (empty($fecha)) return '-';
    return date('d/m/Y', strtotime($fecha));
}

function tiempo_relativo($fecha) {
    if (empty($fecha)) return '';
    
    $timestamp = strtotime($fecha);
    $diferencia = time() - $timestamp;
    
    if ($diferencia < 60) return 'Hace un momento';
    if ($diferencia < 3600) return 'Hace ' . floor($diferencia / 60) . ' min';
    if ($diferencia < 86400) return 'Hace ' . floor($diferencia / 3600) . ' hrs';
    if ($diferencia < 604800) return 'Hace ' . floor($diferencia / 86400) . ' dias';
    
    return formatear_fecha_corta($fecha);
}

/**
 * Obtener fecha actual en formato español legible
 * Usado en todos los dashboards del sistema
 * @return string Ejemplo: "Martes, 03 de Marzo de 2026"
 */
function obtener_fecha_actual_espanol() {
    $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    
    $dia_semana = $dias[date('w')];
    $dia = date('d');
    $mes = $meses[date('n') - 1];
    $anio = date('Y');
    
    return "$dia_semana, $dia de $mes de $anio";
}