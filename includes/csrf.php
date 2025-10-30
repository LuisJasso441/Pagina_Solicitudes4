<?php
/**
 * Sistema de protección CSRF (Cross-Site Request Forgery)
 * Previene ataques de falsificación de peticiones
 * 
 * Uso:
 * - En formularios: echo campo_csrf();
 * - En procesadores: if (!verificar_csrf()) { exit; }
 */

/**
 * Generar token CSRF único por sesión
 * El token se mantiene en la sesión hasta que se regenere
 * 
 * @return string Token CSRF de 64 caracteres hexadecimales
 */
function generar_token_csrf() {
    // Si no existe token en la sesión, crear uno nuevo
    if (!isset($_SESSION['csrf_token'])) {
        // Generar 32 bytes aleatorios y convertir a hexadecimal (64 caracteres)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Obtener campo hidden HTML con el token CSRF
 * Para incluir en formularios
 * 
 * @return string HTML del input hidden
 */
function campo_csrf() {
    $token = generar_token_csrf();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verificar que el token CSRF enviado es válido
 * Debe llamarse al inicio de todos los procesadores POST
 * 
 * @return bool True si el token es válido, False si no
 */
function verificar_csrf() {
    // Verificar que existan ambos tokens
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        error_log("CSRF: Token no encontrado en POST o SESSION");
        return false;
    }
    
    $token_post = $_POST['csrf_token'];
    $token_session = $_SESSION['csrf_token'];
    
    // Verificar que no estén vacíos
    if (empty($token_post) || empty($token_session)) {
        error_log("CSRF: Token vacío");
        return false;
    }
    
    // Comparación segura contra timing attacks
    // hash_equals() previene que un atacante determine el token correcto
    // midiendo el tiempo de respuesta
    $valido = hash_equals($token_session, $token_post);
    
    if (!$valido) {
        error_log("CSRF: Token inválido. POST: $token_post, SESSION: $token_session");
    }
    
    return $valido;
}

/**
 * Regenerar token CSRF
 * Útil después de acciones críticas (login, cambio de permisos)
 * 
 * @return string Nuevo token generado
 */
function regenerar_csrf() {
    unset($_SESSION['csrf_token']);
    return generar_token_csrf();
}

/**
 * Obtener el token actual sin regenerarlo
 * Útil para peticiones AJAX
 * 
 * @return string|null Token actual o null si no existe
 */
function obtener_token_csrf() {
    return $_SESSION['csrf_token'] ?? null;
}

/**
 * Verificar token CSRF para peticiones AJAX
 * Acepta el token en header o en body
 * 
 * @return bool True si el token es válido
 */
function verificar_csrf_ajax() {
    $token_enviado = null;
    
    // Buscar token en header X-CSRF-Token
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token_enviado = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    // Si no está en header, buscar en POST
    elseif (isset($_POST['csrf_token'])) {
        $token_enviado = $_POST['csrf_token'];
    }
    // Si no está en POST, buscar en JSON body
    elseif ($_SERVER['CONTENT_TYPE'] === 'application/json') {
        $json = json_decode(file_get_contents('php://input'), true);
        if (isset($json['csrf_token'])) {
            $token_enviado = $json['csrf_token'];
        }
    }
    
    if (!$token_enviado || !isset($_SESSION['csrf_token'])) {
        error_log("CSRF AJAX: Token no encontrado");
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token_enviado);
}