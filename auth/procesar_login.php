<?php
/**
 * Procesar inicio de sesión
 * VERSIÓN CON BASE DE DATOS - CON REDIRECCIÓN ESPECÍFICA PARA MANTENIMIENTO
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Verificar que sea petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}

// Obtener datos del formulario
$usuario = limpiar_dato($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';
$recordar = isset($_POST['remember']);

// Validar campos vacíos
if (empty($usuario) || empty($password)) {
    establecer_alerta('error', 'Por favor, complete todos los campos');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}

// ====================================
// CONTROL DE INTENTOS FALLIDOS
// ====================================

// Inicializar control de intentos si no existe
if (!isset($_SESSION['intentos_login'])) {
    $_SESSION['intentos_login'] = 0;
    $_SESSION['tiempo_bloqueo'] = 0;
}

// Verificar si está bloqueado
if ($_SESSION['tiempo_bloqueo'] > time()) {
    $segundos_restantes = $_SESSION['tiempo_bloqueo'] - time();
    $minutos = floor($segundos_restantes / 60);
    $segundos = $segundos_restantes % 60;
    establecer_alerta('error', sprintf('Demasiados intentos fallidos. Intente nuevamente en %d:%02d', $minutos, $segundos));
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}

// ====================================
// VERIFICAR CREDENCIALES EN BASE DE DATOS
// ====================================

try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = conectarDB();
    
    // Buscar usuario
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
    $stmt->execute([$usuario]);
    $usuario_db = $stmt->fetch();
    
    // Verificar si existe el usuario y la contraseña es correcta
    if (!$usuario_db || !verificar_password($password, $usuario_db['password'])) {
        // Incrementar intentos fallidos
        $_SESSION['intentos_login']++;
        
        if ($_SESSION['intentos_login'] >= MAX_INTENTOS_LOGIN) {
            $_SESSION['tiempo_bloqueo'] = time() + TIEMPO_BLOQUEO_LOGIN;
            $minutos = TIEMPO_BLOQUEO_LOGIN / 60;
            establecer_alerta('error', 'Demasiados intentos fallidos. Cuenta bloqueada por ' . $minutos . ' minutos');
        } else {
            $intentos_restantes = MAX_INTENTOS_LOGIN - $_SESSION['intentos_login'];
            establecer_alerta('error', 'Usuario o contraseña incorrectos. Le quedan ' . $intentos_restantes . ' intentos');
        }
        
        redirigir(URL_BASE . 'auth/InicioSesion.php');
    }
    
    // ====================================
    // LOGIN EXITOSO
    // ====================================
    
    // Resetear intentos
    $_SESSION['intentos_login'] = 0;
    $_SESSION['tiempo_bloqueo'] = 0;
    
    // Actualizar último acceso
    $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
    $stmt->execute([$usuario_db['id']]);
    
    // Obtener datos del departamento normalizado (si existe la tabla)
    $stmt_depto = $pdo->prepare("
        SELECT d.codigo, d.nombre 
        FROM departamentos d 
        INNER JOIN usuarios u ON u.departamento_id = d.id 
        WHERE u.id = ?
    ");
    $stmt_depto->execute([$usuario_db['id']]);
    $departamento_data = $stmt_depto->fetch();
    
    // Si existe la tabla departamentos, usar código normalizado
    if ($departamento_data) {
        $usuario_db['departamento_codigo'] = $departamento_data['codigo'];
        $usuario_db['departamento_nombre'] = $departamento_data['nombre'];
    } else {
        // Fallback: usar el valor actual y normalizarlo
        $usuario_db['departamento_codigo'] = strtolower(trim($usuario_db['departamento']));
        $usuario_db['departamento_nombre'] = ucfirst($usuario_db['departamento']);
    }
    
    // Iniciar sesión del usuario
    iniciar_sesion_usuario($usuario_db);
    
    // Si marcó "Recordar", establecer cookie (opcional - implementar después)
    if ($recordar) {
        // TODO: Implementar sistema de "recordar sesión" con tokens
    }
    
    // Establecer mensaje de bienvenida
    establecer_alerta('success', '¡Bienvenido, ' . $usuario_db['nombre_completo'] . '!');
    
    // ⭐ REDIRIGIR AL DASHBOARD CORRESPONDIENTE CON DETECCIÓN DE MANTENIMIENTO
    $departamento_lower = strtolower($usuario_db['departamento']);
    
    // Determinar el dashboard según el tipo de usuario
    if ($departamento_lower === 'sistemas' || $departamento_lower === 'ti') {
        // Usuario de TI/Sistemas
        redirigir(URL_BASE . 'dashboard/sistemas/ti_sistemas.php');
    } 
    elseif ($departamento_lower === 'mantenimiento') {
        // ⭐ USUARIO DE MANTENIMIENTO - DASHBOARD ESPECÍFICO
        redirigir(URL_BASE . 'dashboard/ordenes_servicio/mantenimiento.php');
    }
    elseif (es_usuario_colaborativo()) {
        // Usuario colaborativo (Normatividad, Ventas, Laboratorio)
        redirigir(URL_BASE . 'dashboard/colaborativo/colaborativo.php');
    } 
    else {
        // Usuario normal
        redirigir(URL_BASE . 'dashboard/departamento.php');
    }
    
} catch (Exception $e) {
    // Error de conexión o consulta
    if (DEV_MODE) {
        establecer_alerta('error', 'Error en el login: ' . $e->getMessage());
    } else {
        establecer_alerta('error', 'Error al procesar el inicio de sesión. Por favor, intenta nuevamente');
    }
    
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}

?>