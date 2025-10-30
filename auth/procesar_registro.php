<?php
/**
 * Procesar registro de nuevo usuario
 * VERSIÓN CON BASE DE DATOS
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Verificar que sea petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'auth/Registrarse.php');
}

// Obtener y limpiar datos del formulario
$nombre_completo = limpiar_dato($_POST['nombre_completo'] ?? '');
$departamento = limpiar_dato($_POST['departamento'] ?? '');
$usuario = limpiar_dato($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// Array para almacenar errores
$errores = [];

// ====================================
// VALIDACIONES
// ====================================

// Validar nombre completo
if (empty($nombre_completo)) {
    $errores[] = 'El nombre completo es obligatorio';
} elseif (strlen($nombre_completo) < 3) {
    $errores[] = 'El nombre completo debe tener al menos 3 caracteres';
}

// Validar departamento
if (empty($departamento)) {
    $errores[] = 'Debe seleccionar un departamento';
} else {
    // Verificar que el departamento existe
    global $departamentos;
    if (!isset($departamentos[$departamento])) {
        $errores[] = 'Departamento no válido';
    }
}

// Validar nombre de usuario
if (empty($usuario)) {
    $errores[] = 'El nombre de usuario es obligatorio';
} elseif (strlen($usuario) < 4) {
    $errores[] = 'El nombre de usuario debe tener al menos 4 caracteres';
} elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $usuario)) {
    $errores[] = 'El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos';
}

// Validar contraseña
if (empty($password)) {
    $errores[] = 'La contraseña es obligatoria';
} elseif (!validar_password($password)) {
    $errores[] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
}

// Validar confirmación de contraseña
if ($password !== $password_confirm) {
    $errores[] = 'Las contraseñas no coinciden';
}

// ====================================
// SI HAY ERRORES, REGRESAR AL FORMULARIO
// ====================================

if (!empty($errores)) {
    $_SESSION['errores_registro'] = $errores;
    $_SESSION['datos_registro'] = [
        'nombre_completo' => $nombre_completo,
        'departamento' => $departamento,
        'usuario' => $usuario
    ];
    redirigir(URL_BASE . 'auth/Registrarse.php');
}

// ====================================
// REGISTRAR USUARIO EN BASE DE DATOS
// ====================================

try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = conectarDB();
    
    // Verificar si el usuario ya existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $stmt->execute([$usuario]);
    
    if ($stmt->fetch()) {
        establecer_alerta('error', 'El nombre de usuario ya está en uso. Por favor, elige otro');
        $_SESSION['datos_registro'] = [
            'nombre_completo' => $nombre_completo,
            'departamento' => $departamento
        ];
        redirigir(URL_BASE . 'auth/Registrarse.php');
    }
    
    // Insertar nuevo usuario
    $password_hash = hash_password($password);
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nombre_completo, departamento, usuario, password, fecha_registro) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $resultado = $stmt->execute([
        $nombre_completo,
        $departamento,
        $usuario,
        $password_hash
    ]);
    
    if ($resultado) {
        // Registro exitoso
        establecer_alerta('success', '¡Registro exitoso! Ya puedes iniciar sesión con tu usuario: ' . $usuario);
        redirigir(URL_BASE . 'auth/InicioSesion.php');
    } else {
        throw new Exception('Error al registrar el usuario');
    }
    
} catch (Exception $e) {
    // Error en el registro
    if (DEV_MODE) {
        establecer_alerta('error', 'Error al registrar: ' . $e->getMessage());
    } else {
        establecer_alerta('error', 'Hubo un error al procesar el registro. Por favor, intenta nuevamente');
    }
    
    $_SESSION['datos_registro'] = [
        'nombre_completo' => $nombre_completo,
        'departamento' => $departamento,
        'usuario' => $usuario
    ];
    
    redirigir(URL_BASE . 'auth/Registrarse.php');
}

?>