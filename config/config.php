<?php
/**
 * Archivo de configuración general del sistema
 * Portal de Solicitudes de Atención - TI/Sistemas
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ====================================
// CONFIGURACIÓN GENERAL
// ====================================

// Nombre del sistema
define('NOMBRE_SISTEMA', 'Portal de Solicitudes TI');
define('NOMBRE_EMPRESA', 'GrupoVerden');

// Versión del sistema
define('VERSION', '1.0.0');

// ====================================
// RUTAS DEL SISTEMA
// ====================================

// URL base del proyecto (ajusta según tu configuración de XAMPP)
// Ejemplo: http://localhost/PaginaSolicitudes/
define('URL_BASE', 'http://localhost/Pagina_Solicitudes4/');

// Rutas de directorios
define('DIR_ROOT', __DIR__ . '/../');
define('DIR_CONFIG', __DIR__ . '/');
define('DIR_INCLUDES', DIR_ROOT . 'includes/');
define('DIR_AUTH', DIR_ROOT . 'auth/');
define('DIR_DASHBOARD', DIR_ROOT . 'dashboard/');
define('DIR_SOLICITUDES', DIR_ROOT . 'solicitudes/');
define('DIR_COLABORATIVO', DIR_ROOT . 'colaborativo/');
define('DIR_UPLOADS', DIR_ROOT . 'uploads/');

// Rutas de uploads por tipo
define('DIR_UPLOADS_SOLICITUDES', DIR_UPLOADS . 'solicitudes/');
define('DIR_UPLOADS_DOCUMENTOS', DIR_UPLOADS . 'documentos/');
define('DIR_UPLOADS_COLABORATIVO', DIR_UPLOADS . 'colaborativo/');

// ====================================
// CONFIGURACIÓN DE SESIONES
// ====================================

// Tiempo de expiración de sesión (en segundos)
// 3600 = 1 hora, 7200 = 2 horas
define('SESION_TIEMPO_EXPIRACION', 7200);

// Nombre de la cookie de sesión
define('SESION_NOMBRE', 'solicitudes_ti_session');

// ====================================
// CONFIGURACIÓN DE SEGURIDAD
// ====================================

// Longitud mínima de contraseña
define('PASSWORD_MIN_LENGTH', 6);

// Número máximo de intentos de login
define('MAX_INTENTOS_LOGIN', 5);

// Tiempo de bloqueo después de exceder intentos (en segundos)
define('TIEMPO_BLOQUEO_LOGIN', 120); // 2 minutos

// ====================================
// CONFIGURACIÓN DE ARCHIVOS
// ====================================

// Tamaño máximo de archivo en bytes (10MB - aumentado para documentos)
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// Tipos de archivo permitidos para solicitudes
define('ALLOWED_FILE_TYPES', [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 
    'jpg', 'jpeg', 'png', 'gif', 'txt',
    'ppt', 'pptx', 'zip', 'rar'
]);

// Tipos MIME permitidos (para validación adicional)
define('ALLOWED_MIME_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'text/plain',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/zip',
    'application/x-rar-compressed'
]);

// ====================================
// ESTADOS DE SOLICITUDES
// ====================================

define('ESTADO_PENDIENTE', 'pendiente');
define('ESTADO_EN_PROCESO', 'en_proceso');
define('ESTADO_FINALIZADA', 'finalizada');
define('ESTADO_CANCELADA', 'cancelada');

// ====================================
// ZONA HORARIA
// ====================================

date_default_timezone_set('America/Mexico_City');

// ====================================
// MODO DE DESARROLLO
// ====================================

// Cambiar a false en producción
define('DEV_MODE', true);

// Mostrar errores en modo desarrollo
if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ====================================
// INCLUIR OTROS ARCHIVOS DE CONFIGURACIÓN
// ====================================

// Incluir configuración de departamentos (PRIMERO)
require_once DIR_CONFIG . 'departamentos.php';

// Incluir funciones generales (DESPUÉS)
require_once DIR_INCLUDES . 'functions.php';

?>