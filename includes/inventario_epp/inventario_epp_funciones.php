<?php
/**
 * Funciones del Módulo Inventario de EPP
 * Ubicación: includes/inventario_epp/inventario_epp_funciones.php
 * 
 * Funciones para gestionar el inventario de Equipo de Protección Personal:
 * - Permisos (lector, creador, editor)
 * - CRUD de artículos
 * - Registro de movimientos (Entrada/Salida)
 * - Consultas para dashboard (vista Inventario y Movimientos)
 */

// =====================================================
// CONSTANTES DEL MÓDULO
// =====================================================
define('EPP_CATEGORIAS', [
    'Arnés',
    'Audífonos de Seguridad',
    'Botas',
    'Careta',
    'Casco',
    'Chaleco',
    'Guantes',
    'Lentes',
    'Mascarilla',
    'Overol de Seguridad'
]);

// =====================================================
// FUNCIONES DE PERMISOS
// =====================================================

/**
 * Verificar permisos del usuario para el módulo EPP
 * @param int $user_id ID del usuario
 * @return array ['tiene_acceso', 'puede_crear', 'puede_editar']
 */
function verificar_permisos_epp($user_id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("SELECT lector, creador, editor FROM permisos_epp WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $permisos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permisos) {
        return [
            'tiene_acceso' => false,
            'puede_crear'  => false,
            'puede_editar' => false,
            'lector'       => 0,
            'creador'      => 0,
            'editor'       => 0
        ];
    }
    
    return [
        'tiene_acceso' => (bool) $permisos['lector'],
        'puede_crear'  => (bool) $permisos['creador'],
        'puede_editar' => (bool) $permisos['editor'],
        'lector'       => (int) $permisos['lector'],
        'creador'      => (int) $permisos['creador'],
        'editor'       => (int) $permisos['editor']
    ];
}

// =====================================================
// FUNCIONES DE INVENTARIO (CRUD)
// =====================================================

/**
 * Obtener todos los artículos del inventario con filtros
 * @param array $filtros ['categoria', 'busqueda', 'fecha_desde', 'fecha_hasta']
 * @return array
 */
function obtener_inventario_epp($filtros = []) {
    $pdo = conectarDB();
    
    $where = ["i.activo = 1"];
    $params = [];
    
    if (!empty($filtros['categoria'])) {
        $where[] = "i.categoria = :categoria";
        $params[':categoria'] = $filtros['categoria'];
    }
    
    if (!empty($filtros['busqueda'])) {
        $where[] = "(i.articulo LIKE :busqueda OR i.categoria LIKE :busqueda2 OR i.nombre_proveedor LIKE :busqueda3)";
        $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
        $params[':busqueda2'] = '%' . $filtros['busqueda'] . '%';
        $params[':busqueda3'] = '%' . $filtros['busqueda'] . '%';
    }
    
    if (!empty($filtros['fecha_desde'])) {
        $where[] = "i.fecha_creacion >= :fecha_desde";
        $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
    }
    
    if (!empty($filtros['fecha_hasta'])) {
        $where[] = "i.fecha_creacion <= :fecha_hasta";
        $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
    }
    
    $where_sql = implode(' AND ', $where);
    
    $sql = "SELECT i.*, 
                   (SELECT m.fecha_movimiento 
                    FROM movimientos_epp m 
                    WHERE m.inventario_epp_id = i.id 
                    ORDER BY m.fecha_movimiento DESC LIMIT 1) as ultimo_movimiento_fecha,
                   (SELECT m.tipo_movimiento 
                    FROM movimientos_epp m 
                    WHERE m.inventario_epp_id = i.id 
                    ORDER BY m.fecha_movimiento DESC LIMIT 1) as ultimo_movimiento_tipo
            FROM inventario_epp i
            WHERE {$where_sql}
            ORDER BY i.fecha_ultima_edicion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener un artículo del inventario por ID
 * @param int $id
 * @return array|false
 */
function obtener_epp_por_id($id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("SELECT * FROM inventario_epp WHERE id = :id AND activo = 1");
    $stmt->execute([':id' => $id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Agregar nuevo artículo al inventario + movimiento automático de Entrada
 * @param array $datos Datos del formulario
 * @return array ['success', 'message', 'id']
 */
function agregar_epp($datos) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        // 1. Insertar artículo en inventario
        $stmt = $pdo->prepare("
            INSERT INTO inventario_epp (
                categoria, articulo, unidad, lote_identificador, stock, talla,
                precio, nombre_proveedor, observaciones,
                usuario_creador_id, usuario_creador_nombre, departamento_creador
            ) VALUES (
                :categoria, :articulo, :unidad, :lote_identificador, :stock, :talla,
                :precio, :nombre_proveedor, :observaciones,
                :usuario_creador_id, :usuario_creador_nombre, :departamento_creador
            )
        ");
        
        $stmt->execute([
            ':categoria'            => $datos['categoria'],
            ':articulo'             => $datos['articulo'],
            ':unidad'               => $datos['unidad'],
            ':lote_identificador'   => $datos['lote_identificador'] ?: null,
            ':stock'                => (int) $datos['stock'],
            ':talla'                => $datos['talla'] ?: null,
            ':precio'               => $datos['precio'] ? (float) $datos['precio'] : null,
            ':nombre_proveedor'     => $datos['nombre_proveedor'] ?: null,
            ':observaciones'        => $datos['observaciones'] ?: null,
            ':usuario_creador_id'   => $datos['usuario_id'],
            ':usuario_creador_nombre' => $datos['usuario_nombre'],
            ':departamento_creador' => $datos['departamento']
        ]);
        
        $inventario_id = $pdo->lastInsertId();
        
        // 2. Crear movimiento automático de Entrada
        $stmt_mov = $pdo->prepare("
            INSERT INTO movimientos_epp (
                inventario_epp_id, tipo_movimiento, fecha_movimiento,
                categoria, articulo, talla, cantidad,
                observaciones, stock_resultante,
                usuario_id, usuario_nombre, departamento, es_automatico
            ) VALUES (
                :inventario_epp_id, 'Entrada', :fecha_movimiento,
                :categoria, :articulo, :talla, :cantidad,
                :observaciones, :stock_resultante,
                :usuario_id, :usuario_nombre, :departamento, 1
            )
        ");
        
        $stmt_mov->execute([
            ':inventario_epp_id'  => $inventario_id,
            ':fecha_movimiento'   => $datos['fecha_movimiento'] ?? date('Y-m-d H:i:s'),
            ':categoria'          => $datos['categoria'],
            ':articulo'           => $datos['articulo'],
            ':talla'              => $datos['talla'] ?: null,
            ':cantidad'           => (int) $datos['stock'],
            ':observaciones'      => 'Entrada automática al agregar nuevo EPP al inventario',
            ':stock_resultante'   => (int) $datos['stock'],
            ':usuario_id'         => $datos['usuario_id'],
            ':usuario_nombre'     => $datos['usuario_nombre'],
            ':departamento'       => $datos['departamento']
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'EPP agregado correctamente al inventario.',
            'id'      => $inventario_id
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al agregar EPP: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al agregar EPP: ' . $e->getMessage()
        ];
    }
}

/**
 * Actualizar artículo del inventario (edición inline)
 * @param int $id ID del artículo
 * @param string $campo Nombre del campo a actualizar
 * @param mixed $valor Nuevo valor
 * @return array ['success', 'message']
 */
function actualizar_campo_epp($id, $campo, $valor) {
    $pdo = conectarDB();
    
    // Campos permitidos para edición inline
    $campos_permitidos = ['articulo', 'unidad', 'stock', 'talla', 'precio', 'nombre_proveedor', 'lote_identificador', 'observaciones', 'categoria'];
    
    if (!in_array($campo, $campos_permitidos)) {
        return ['success' => false, 'message' => 'Campo no permitido para edición.'];
    }
    
    try {
        // Si se edita stock directamente, registrar el cambio
        if ($campo === 'stock') {
            $epp_actual = obtener_epp_por_id($id);
            if (!$epp_actual) {
                return ['success' => false, 'message' => 'Artículo no encontrado.'];
            }
            
            $stock_anterior = (int) $epp_actual['stock'];
            $stock_nuevo = (int) $valor;
            $diferencia = $stock_nuevo - $stock_anterior;
            
            if ($diferencia !== 0) {
                // Crear movimiento automático por ajuste de stock
                $tipo = $diferencia > 0 ? 'Entrada' : 'Salida';
                $cantidad = abs($diferencia);
                
                $stmt_mov = $pdo->prepare("
                    INSERT INTO movimientos_epp (
                        inventario_epp_id, tipo_movimiento, fecha_movimiento,
                        categoria, articulo, talla, cantidad,
                        observaciones, stock_resultante,
                        usuario_id, usuario_nombre, departamento, es_automatico
                    ) VALUES (
                        :inventario_epp_id, :tipo, NOW(),
                        :categoria, :articulo, :talla, :cantidad,
                        :observaciones, :stock_resultante,
                        :usuario_id, :usuario_nombre, :departamento, 1
                    )
                ");
                
                $stmt_mov->execute([
                    ':inventario_epp_id'  => $id,
                    ':tipo'               => $tipo,
                    ':categoria'          => $epp_actual['categoria'],
                    ':articulo'           => $epp_actual['articulo'],
                    ':talla'              => $epp_actual['talla'],
                    ':cantidad'           => $cantidad,
                    ':observaciones'      => "Ajuste de stock directo en tabla de inventario (de {$stock_anterior} a {$stock_nuevo})",
                    ':stock_resultante'   => $stock_nuevo,
                    ':usuario_id'         => $_SESSION['usuario_id'],
                    ':usuario_nombre'     => $_SESSION['nombre_completo'],
                    ':departamento'       => $_SESSION['departamento']
                ]);
            }
        }
        
        $stmt = $pdo->prepare("UPDATE inventario_epp SET `{$campo}` = :valor WHERE id = :id AND activo = 1");
        $stmt->execute([':valor' => $valor, ':id' => $id]);
        
        return ['success' => true, 'message' => 'Campo actualizado correctamente.'];
        
    } catch (Exception $e) {
        error_log("Error al actualizar EPP: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()];
    }
}

/**
 * Eliminar artículo del inventario (soft delete)
 * @param int $id
 * @return array ['success', 'message']
 */
function eliminar_epp($id) {
    $pdo = conectarDB();
    
    try {
        $stmt = $pdo->prepare("UPDATE inventario_epp SET activo = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        return ['success' => true, 'message' => 'Artículo eliminado del inventario.'];
    } catch (Exception $e) {
        error_log("Error al eliminar EPP: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()];
    }
}

// =====================================================
// FUNCIONES DE MOVIMIENTOS
// =====================================================

/**
 * Registrar un movimiento de Entrada o Salida
 * @param array $datos Datos del movimiento
 * @return array ['success', 'message']
 */
function registrar_movimiento_epp($datos) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        // Obtener artículo actual
        $epp = obtener_epp_por_id($datos['inventario_epp_id']);
        if (!$epp) {
            return ['success' => false, 'message' => 'Artículo no encontrado en el inventario.'];
        }
        
        $stock_actual = (int) $epp['stock'];
        $cantidad = (int) $datos['cantidad'];
        
        // Calcular nuevo stock
        if ($datos['tipo_movimiento'] === 'Entrada') {
            $nuevo_stock = $stock_actual + $cantidad;
        } else {
            // Salida - verificar que haya suficiente stock
            if ($cantidad > $stock_actual) {
                return ['success' => false, 'message' => "Stock insuficiente. Stock actual: {$stock_actual}, cantidad solicitada: {$cantidad}"];
            }
            $nuevo_stock = $stock_actual - $cantidad;
        }
        
        // 1. Insertar movimiento
        $stmt = $pdo->prepare("
            INSERT INTO movimientos_epp (
                inventario_epp_id, tipo_movimiento, fecha_movimiento,
                categoria, articulo, talla, cantidad,
                nombre_trabajador, observaciones, stock_resultante,
                usuario_id, usuario_nombre, departamento, es_automatico
            ) VALUES (
                :inventario_epp_id, :tipo_movimiento, :fecha_movimiento,
                :categoria, :articulo, :talla, :cantidad,
                :nombre_trabajador, :observaciones, :stock_resultante,
                :usuario_id, :usuario_nombre, :departamento, 0
            )
        ");
        
        $stmt->execute([
            ':inventario_epp_id'  => $datos['inventario_epp_id'],
            ':tipo_movimiento'    => $datos['tipo_movimiento'],
            ':fecha_movimiento'   => $datos['fecha_movimiento'],
            ':categoria'          => $datos['categoria'] ?: $epp['categoria'],
            ':articulo'           => $datos['articulo'] ?: $epp['articulo'],
            ':talla'              => $datos['talla'] ?: $epp['talla'],
            ':cantidad'           => $cantidad,
            ':nombre_trabajador'  => $datos['nombre_trabajador'] ?: null,
            ':observaciones'      => $datos['observaciones'] ?: null,
            ':stock_resultante'   => $nuevo_stock,
            ':usuario_id'         => $datos['usuario_id'],
            ':usuario_nombre'     => $datos['usuario_nombre'],
            ':departamento'       => $datos['departamento']
        ]);
        
        // 2. Actualizar stock en inventario
        $stmt_stock = $pdo->prepare("UPDATE inventario_epp SET stock = :stock WHERE id = :id");
        $stmt_stock->execute([':stock' => $nuevo_stock, ':id' => $datos['inventario_epp_id']]);
        
        $pdo->commit();
        
        $tipo_label = $datos['tipo_movimiento'] === 'Entrada' ? 'Entrada' : 'Salida';
        return [
            'success' => true,
            'message' => "Movimiento de {$tipo_label} registrado correctamente. Nuevo stock: {$nuevo_stock}"
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al registrar movimiento EPP: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al registrar movimiento: ' . $e->getMessage()];
    }
}

/**
 * Obtener movimientos con filtros
 * @param array $filtros ['categoria', 'tipo_movimiento', 'fecha_desde', 'fecha_hasta', 'busqueda']
 * @return array
 */
function obtener_movimientos_epp($filtros = []) {
    $pdo = conectarDB();
    
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filtros['categoria'])) {
        $where[] = "m.categoria = :categoria";
        $params[':categoria'] = $filtros['categoria'];
    }
    
    if (!empty($filtros['tipo_movimiento'])) {
        $where[] = "m.tipo_movimiento = :tipo_movimiento";
        $params[':tipo_movimiento'] = $filtros['tipo_movimiento'];
    }
    
    if (!empty($filtros['busqueda'])) {
        $where[] = "(m.articulo LIKE :busqueda OR m.nombre_trabajador LIKE :busqueda2 OR m.observaciones LIKE :busqueda3)";
        $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
        $params[':busqueda2'] = '%' . $filtros['busqueda'] . '%';
        $params[':busqueda3'] = '%' . $filtros['busqueda'] . '%';
    }
    
    if (!empty($filtros['fecha_desde'])) {
        $where[] = "m.fecha_movimiento >= :fecha_desde";
        $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
    }
    
    if (!empty($filtros['fecha_hasta'])) {
        $where[] = "m.fecha_movimiento <= :fecha_hasta";
        $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
    }
    
    if (!empty($filtros['inventario_epp_id'])) {
        $where[] = "m.inventario_epp_id = :inventario_epp_id";
        $params[':inventario_epp_id'] = $filtros['inventario_epp_id'];
    }
    
    $where_sql = implode(' AND ', $where);
    
    $sql = "SELECT m.*, i.stock as stock_actual, i.unidad
            FROM movimientos_epp m
            LEFT JOIN inventario_epp i ON m.inventario_epp_id = i.id
            WHERE {$where_sql}
            ORDER BY m.fecha_movimiento DESC, m.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener un movimiento por ID
 * @param int $id
 * @return array|false
 */
function obtener_movimiento_por_id($id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT m.*, i.stock as stock_actual, i.unidad, i.nombre_proveedor, i.precio, i.lote_identificador
        FROM movimientos_epp m
        LEFT JOIN inventario_epp i ON m.inventario_epp_id = i.id
        WHERE m.id = :id
    ");
    $stmt->execute([':id' => $id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// =====================================================
// FUNCIONES DE ESTADÍSTICAS
// =====================================================

/**
 * Obtener estadísticas del inventario de EPP
 * @return array
 */
function obtener_estadisticas_epp() {
    $pdo = conectarDB();
    
    $stats = [];
    
    // Total artículos activos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM inventario_epp WHERE activo = 1");
    $stats['total_articulos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total stock
    $stmt = $pdo->query("SELECT COALESCE(SUM(stock), 0) as total FROM inventario_epp WHERE activo = 1");
    $stats['total_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Artículos sin stock
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM inventario_epp WHERE activo = 1 AND stock = 0");
    $stats['sin_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total movimientos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM movimientos_epp");
    $stats['total_movimientos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Movimientos del mes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM movimientos_epp WHERE MONTH(fecha_movimiento) = MONTH(NOW()) AND YEAR(fecha_movimiento) = YEAR(NOW())");
    $stats['movimientos_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Entradas vs Salidas del mes
    $stmt = $pdo->query("
        SELECT tipo_movimiento, COUNT(*) as total 
        FROM movimientos_epp 
        WHERE MONTH(fecha_movimiento) = MONTH(NOW()) AND YEAR(fecha_movimiento) = YEAR(NOW())
        GROUP BY tipo_movimiento
    ");
    $movs_mes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $stats['entradas_mes'] = $movs_mes['Entrada'] ?? 0;
    $stats['salidas_mes'] = $movs_mes['Salida'] ?? 0;
    
    return $stats;
}

/**
 * Obtener artículos para dropdown de selección (para formulario de movimientos)
 * @return array
 */
function obtener_articulos_dropdown_epp() {
    $pdo = conectarDB();
    
    $stmt = $pdo->query("
        SELECT id, categoria, articulo, unidad, stock, talla 
        FROM inventario_epp 
        WHERE activo = 1 
        ORDER BY categoria ASC, articulo ASC
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}