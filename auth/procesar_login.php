<?php
/**
 * Procesar inicio de sesión
 * VERSIÓN CON BASE DE DATOS
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
    
    // Iniciar sesión del usuario
    iniciar_sesion_usuario($usuario_db);
    
    // Si marcó "Recordar", establecer cookie (opcional - implementar después)
    if ($recordar) {
        // TODO: Implementar sistema de "recordar sesión" con tokens
    }
    
    // Establecer mensaje de bienvenida
    establecer_alerta('success', '¡Bienvenido, ' . $usuario_db['nombre_completo'] . '!');
    
    // Redirigir al dashboard correspondiente
    redirigir_dashboard();
    
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