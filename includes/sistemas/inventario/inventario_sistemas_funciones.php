<?php
/**
 * Funciones del Módulo Inventario de Sistemas
 * Ubicación: includes/sistemas/inventario/inventario_sistemas_funciones.php
 */

// =====================================================
// CONSTANTES DEL MÓDULO
// =====================================================
if (!defined('INVENTARIO_SIS_CATEGORIAS')) {
    define('INVENTARIO_SIS_CATEGORIAS', [
        'almacenamiento'  => 'Almacenamiento',
        'baterias_pilas'  => 'Bater&iacute;as/Pilas',    
        'cableado'        => 'Cableado',
        'componentes'     => 'Componentes',
        'equipos'         => 'Equipos',
        'herramientas'    => 'Herramientas/Construcci&oacute;n',
        'impresion'       => 'Impresi&oacute;n',
        'licencias'       => 'Licencias',
        'perifericos'     => 'Perif&eacute;ricos',
        'redes'           => 'Redes',
        'otros'           => 'Otros'
    ]);
}

// =====================================================
// FUNCIONES DE ACCESO
// =====================================================

/**
 * Verificar si el usuario es de Sistemas (acceso fijo)
 */
function es_usuario_sistemas() {
    $depto = strtolower($_SESSION['departamento'] ?? '');
    return (strpos($depto, 'sistemas') !== false || strpos($depto, 'ti') !== false);
}

// =====================================================
// FUNCIONES CRUD
// =====================================================

/**
 * Obtener listado de inventario con filtros
 */
function obtener_inventario_sistemas($filtros = []) {
    $pdo = conectarDB();
    
    $where = ["activo = 1"];
    $params = [];
    
    if (!empty($filtros['categoria'])) {
        $where[] = "categoria = :categoria";
        $params[':categoria'] = $filtros['categoria'];
    }
    if (!empty($filtros['busqueda'])) {
        $where[] = "(nombre LIKE :busqueda OR ubicacion LIKE :busqueda2)";
        $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
        $params[':busqueda2'] = '%' . $filtros['busqueda'] . '%';
    }
    
    $sql = "SELECT * FROM inventario_sistemas WHERE " . implode(' AND ', $where) . " ORDER BY categoria ASC, nombre ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener un artículo por ID
 */
function obtener_articulo_sistemas($id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM inventario_sistemas WHERE id = :id AND activo = 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crear artículo
 */
function crear_articulo_sistemas($datos) {
    $pdo = conectarDB();
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO inventario_sistemas 
                (nombre, categoria, cantidad_total, cantidad_disponible, ubicacion, umbral_minimo, created_by, updated_by)
            VALUES 
                (:nombre, :categoria, :cantidad_total, :cantidad_disponible, :ubicacion, :umbral_minimo, :created_by, :updated_by)
        ");
        $stmt->execute([
            ':nombre'              => $datos['nombre'],
            ':categoria'           => $datos['categoria'],
            ':cantidad_total'      => (int)$datos['cantidad_total'],
            ':cantidad_disponible' => (int)$datos['cantidad_disponible'],
            ':ubicacion'           => $datos['ubicacion'] ?: null,
            ':umbral_minimo'       => (int)$datos['umbral_minimo'],
            ':created_by'          => $_SESSION['usuario_id'],
            ':updated_by'          => $_SESSION['usuario_id']
        ]);
        return ['success' => true, 'message' => 'Art&iacute;culo creado correctamente.', 'id' => $pdo->lastInsertId()];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error al crear: ' . $e->getMessage()];
    }
}

/**
 * Actualizar artículo
 */
function actualizar_articulo_sistemas($id, $datos) {
    $pdo = conectarDB();
    
    try {
        $stmt = $pdo->prepare("
            UPDATE inventario_sistemas SET
                nombre = :nombre,
                categoria = :categoria,
                cantidad_total = :cantidad_total,
                cantidad_disponible = :cantidad_disponible,
                ubicacion = :ubicacion,
                umbral_minimo = :umbral_minimo,
                updated_by = :updated_by
            WHERE id = :id AND activo = 1
        ");
        $stmt->execute([
            ':nombre'              => $datos['nombre'],
            ':categoria'           => $datos['categoria'],
            ':cantidad_total'      => (int)$datos['cantidad_total'],
            ':cantidad_disponible' => (int)$datos['cantidad_disponible'],
            ':ubicacion'           => $datos['ubicacion'] ?: null,
            ':umbral_minimo'       => (int)$datos['umbral_minimo'],
            ':updated_by'          => $_SESSION['usuario_id'],
            ':id'                  => $id
        ]);
        return ['success' => true, 'message' => 'Art&iacute;culo actualizado correctamente.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()];
    }
}

/**
 * Eliminar artículo (soft delete)
 */
function eliminar_articulo_sistemas($id) {
    $pdo = conectarDB();
    
    try {
        $stmt = $pdo->prepare("UPDATE inventario_sistemas SET activo = 0, updated_by = :uid WHERE id = :id");
        $stmt->execute([':uid' => $_SESSION['usuario_id'], ':id' => $id]);
        return ['success' => true, 'message' => 'Art&iacute;culo eliminado correctamente.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()];
    }
}

/**
 * Obtener estadísticas rápidas
 */
function obtener_estadisticas_inventario_sistemas() {
    $pdo = conectarDB();
    $stats = [];
    $stats['total_articulos'] = $pdo->query("SELECT COUNT(*) FROM inventario_sistemas WHERE activo = 1")->fetchColumn();
    $stats['sin_stock'] = $pdo->query("SELECT COUNT(*) FROM inventario_sistemas WHERE activo = 1 AND cantidad_disponible = 0")->fetchColumn();
    $stats['bajo_umbral'] = $pdo->query("SELECT COUNT(*) FROM inventario_sistemas WHERE activo = 1 AND cantidad_disponible <= umbral_minimo AND umbral_minimo > 0")->fetchColumn();
    $stats['categorias_activas'] = $pdo->query("SELECT COUNT(DISTINCT categoria) FROM inventario_sistemas WHERE activo = 1")->fetchColumn();
    $stats['en_prestamo'] = $pdo->query("SELECT COALESCE(SUM(cantidad_total - cantidad_disponible), 0) FROM inventario_sistemas WHERE activo = 1 AND cantidad_total > cantidad_disponible")->fetchColumn();
    return $stats;
}
?>