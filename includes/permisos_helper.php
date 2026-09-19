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
 * Verifica si el usuario es del departamento de Mantenimiento
 * @return bool
 */
if (!function_exists('es_mantenimiento')) {
    function es_mantenimiento() {
        $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
        return $departamento === 'mantenimiento';
    }
}

/**
 * Verifica si el usuario es del departamento de Sistemas
 * @return bool
 */
if (!function_exists('es_sistemas')) {
    function es_sistemas() {
        $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
        return $departamento === 'sistemas';
    }
}

/**
 * Verifica si el usuario es del departamento de Sistemas
 * @return bool
 */
function es_sistemas() {
    $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
    return $departamento === 'sistemas';
}

// ====================================
// PERMISOS SEC (Salidas de Envases para Clientes)
// ====================================

/**
 * Obtener permisos SEC del usuario actual
 * @return array ['lector' => 0|1, 'creador' => 0|1, 'editor' => 0|1]
 */
function obtener_permisos_sec() {
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    if (!$usuario_id) {
        return ['lector' => 0, 'creador' => 0, 'editor' => 0];
    }
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT lector, creador, editor FROM permisos_sec WHERE user_id = ?");
        $stmt->execute([$usuario_id]);
        $permisos = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$permisos) {
            return ['lector' => 1, 'creador' => 0, 'editor' => 0];
        }
        return $permisos;
    } catch (Exception $e) {
        error_log("Error obtener_permisos_sec: " . $e->getMessage());
        return ['lector' => 1, 'creador' => 0, 'editor' => 0];
    }
}

/**
 * @return bool
 */
function puede_leer_sec() {
    $permisos = obtener_permisos_sec();
    return $permisos && $permisos['lector'] == 1;
}

/**
 * Pueden crear SEC: Logística y Ventas (con permiso de creador activo)
 * @return bool
 */
function puede_crear_sec() {
    $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
    if (!in_array($departamento, ['logistica', 'ventas'])) {
        return false;
    }
    $permisos = obtener_permisos_sec();
    return $permisos && $permisos['creador'] == 1;
}

/**
 * @return bool
 */
function puede_editar_sec() {
    $permisos = obtener_permisos_sec();
    return $permisos && $permisos['editor'] == 1;
}

/**
 * Solo Logística gestiona Unidades de Transporte y crea Disponibilidad
 * @return bool
 */
if (!function_exists('es_logistica')) {
    function es_logistica() {
        $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
        return $departamento === 'logistica';
    }
}

/**
 * Almacén de Residuos: firma Entrega/Recibe y sube evidencias
 * @return bool
 */
if (!function_exists('es_almacen_residuos')) {
    function es_almacen_residuos() {
        $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
        return $departamento === 'almacen_residuos';
    }
}

// ====================================
// PERMISOS SEC EXTENDIDOS (Bloque 9)
// Reparto de responsabilidades por depto:
//   - Tipos de Envase:      Almacén de Residuos administra; Log/Ventas solo leen
//   - Unidades Transporte:  Logística administra; Log/Ventas/Almacén pueden ver
// ====================================

/**
 * Solo Almacén de Residuos puede administrar el catálogo de Tipos de Envase.
 * Logística y Ventas solo tienen lectura (ven el listado pero sin botones CRUD).
 * @return bool
 */
if (!function_exists('puede_administrar_tipos_envase')) {
    function puede_administrar_tipos_envase() {
        $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
        return $departamento === 'almacen_residuos';
    }
}

/**
 * Ver el listado de Unidades de Transporte y sus capacidades:
 * Logística, Ventas y Almacén de Residuos.
 * @return bool
 */
if (!function_exists('puede_ver_unidades_transporte')) {
    function puede_ver_unidades_transporte() {
        $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
        return in_array($departamento, ['logistica', 'ventas', 'almacen_residuos'], true);
    }
}

/**
 * Administrar (crear/editar/eliminar) Unidades de Transporte y sus capacidades:
 * solo Logística.
 * @return bool
 */
if (!function_exists('puede_administrar_unidades_transporte')) {
    function puede_administrar_unidades_transporte() {
        $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
        return $departamento === 'logistica';
    }
}

/**
 * Ver Inventario y Movimientos de Inventario (solo lectura):
 * Logística, Ventas y Almacén de Residuos.
 * @return bool
 */
if (!function_exists('puede_ver_inventario')) {
    function puede_ver_inventario() {
        $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
        return in_array($departamento, ['logistica', 'ventas', 'almacen_residuos'], true);
    }
}

/**
 * Administrar Inventario (ajustar stock, registrar movimientos manuales,
 * subir/bajar inventario inicial): solo Almacén de Residuos.
 * Logística y Ventas solo consultan.
 * @return bool
 */
if (!function_exists('puede_administrar_inventario')) {
    function puede_administrar_inventario() {
        $departamento = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
        return $departamento === 'almacen_residuos';
    }
}