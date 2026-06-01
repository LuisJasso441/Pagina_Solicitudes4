<?php
/**
 * Helper de Permisos
 * Funciones para verificar permisos de usuario en módulos SSC y OSM
 * 
 * Ubicación recomendada: includes/permisos_helper.php
 * 
 * USO:
 * require_once __DIR__ . '/includes/permisos_helper.php';
 * 
 * if (puede_crear_osm()) {
 *     // Mostrar botón de crear orden
 * }
 */

// Asegurar que la sesión está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Obtiene los permisos OSM del usuario actual
 * @return array|null Array con permisos [lector, creador, editor] o null si no tiene
 */
function obtener_permisos_osm() {
    static $permisos_osm = null;
    
    if ($permisos_osm !== null) {
        return $permisos_osm;
    }
    
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }
    
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT lector, creador, editor FROM permisos_osm WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $_SESSION['usuario_id']]);
        $permisos_osm = $stmt->fetch(PDO::FETCH_ASSOC);
        return $permisos_osm;
    } catch (Exception $e) {
        error_log("Error obteniendo permisos OSM: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtiene los permisos SSC del usuario actual
 * @return array|null Array con permisos [lector, creador, editor] o null si no tiene
 */
function obtener_permisos_ssc() {
    static $permisos_ssc = null;
    
    if ($permisos_ssc !== null) {
        return $permisos_ssc;
    }
    
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }
    
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT lector, creador, editor FROM permisos_ssc WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $_SESSION['usuario_id']]);
        $permisos_ssc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si no tiene registro, por defecto tiene permiso de lector
        if (!$permisos_ssc) {
            $permisos_ssc = ['lector' => 1, 'creador' => 0, 'editor' => 0];
        }
        
        return $permisos_ssc;
    } catch (Exception $e) {
        error_log("Error obteniendo permisos SSC: " . $e->getMessage());
        return ['lector' => 1, 'creador' => 0, 'editor' => 0];
    }
}

/**
 * Verifica si el usuario puede LEER órdenes OSM
 * @return bool
 */
function puede_leer_osm() {
    $permisos = obtener_permisos_osm();
    return $permisos && $permisos['lector'] == 1;
}

/**
 * Verifica si el usuario puede CREAR órdenes OSM
 * @return bool
 */
function puede_crear_osm() {
    // Mantenimiento no puede crear, solo procesar
    $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
    if ($departamento === 'mantenimiento') {
        return false;
    }
    
    $permisos = obtener_permisos_osm();
    return $permisos && $permisos['creador'] == 1;
}

/**
 * Verifica si el usuario puede EDITAR órdenes OSM
 * @return bool
 */
function puede_editar_osm() {
    $permisos = obtener_permisos_osm();
    return $permisos && $permisos['editor'] == 1;
}

/**
 * Verifica si el usuario puede LEER documentos SSC
 * @return bool
 */
function puede_leer_ssc() {
    $permisos = obtener_permisos_ssc();
    return $permisos && $permisos['lector'] == 1;
}

/**
 * Verifica si el usuario puede CREAR documentos SSC
 * @return bool
 */
function puede_crear_ssc() {
    // Solo Normatividad y Ventas pueden crear SSC
    $departamento = strtolower($_SESSION['departamento'] ?? '');
    if (!in_array($departamento, ['normatividad', 'ventas', 'ptar'])) {
        return false;
    }
    
    $permisos = obtener_permisos_ssc();
    return $permisos && $permisos['creador'] == 1;
}

/**
 * Verifica si el usuario puede EDITAR documentos SSC
 * @return bool
 */
function puede_editar_ssc() {
    $permisos = obtener_permisos_ssc();
    return $permisos && $permisos['editor'] == 1;
}

/**
 * Verifica si el usuario es del departamento de Mantenimiento
 * @return bool
 */
function es_mantenimiento() {
    $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
    return $departamento === 'mantenimiento';
}

/**
 * Verifica si el usuario es del departamento de Sistemas
 * @return bool
 */
function es_sistemas() {
    $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
    return $departamento === 'sistemas';
}