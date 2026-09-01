<?php
/**
 * Funciones del Módulo Inventario de EPP
 * Ubicación: includes/inventario_epp/inventario_epp_funciones.php
 * 
 * VERSIÓN 2.3 - Tallas individual + agregar stock existente + nueva talla
 */

// =====================================================
// CONSTANTES DEL MÓDULO
// =====================================================
if (!defined('EPP_CATEGORIAS')) {
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
}

// =====================================================
// FUNCIONES DE PERMISOS
// =====================================================

function verificar_permisos_epp($user_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT lector, creador, editor FROM permisos_epp WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $permisos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $depto = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
    
    if (!$permisos) {
        return [
            'tiene_acceso' => false, 'puede_crear' => false, 'puede_editar' => false,
            'puede_editar_tabla' => false, 'puede_eliminar' => false,
            'lector' => 0, 'creador' => 0, 'editor' => 0
        ];
    }
    
    $es_editor = (bool) $permisos['editor'];
    
    return [
        'tiene_acceso' => (bool) $permisos['lector'],
        'puede_crear'  => (bool) $permisos['creador'],
        'puede_editar' => $es_editor,
        // Seguridad NO edita tabla general (articulo, unidad, etc.)
        'puede_editar_tabla' => $es_editor && ($depto !== 'seguridad'),
        // Solo Almacen de Refacciones puede eliminar
        'puede_eliminar' => $es_editor && ($depto === 'almacen_refacciones'),
        'lector' => (int) $permisos['lector'],
        'creador' => (int) $permisos['creador'],
        'editor' => (int) $permisos['editor']
    ];
}

// =====================================================
// FUNCIONES DE TALLAS
// =====================================================

function obtener_tallas_epp($inventario_epp_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM inventario_epp_tallas WHERE inventario_epp_id = :id ORDER BY talla ASC");
    $stmt->execute([':id' => $inventario_epp_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_talla_por_id($talla_id, $pdo = null) {
    if (!$pdo) $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT t.*, i.articulo, i.categoria, i.unidad FROM inventario_epp_tallas t JOIN inventario_epp i ON t.inventario_epp_id = i.id WHERE t.id = :id");
    $stmt->execute([':id' => $talla_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function recalcular_stock_epp($inventario_epp_id, $pdo = null) {
    if (!$pdo) $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(stock), 0) as total FROM inventario_epp_tallas WHERE inventario_epp_id = :id");
    $stmt->execute([':id' => $inventario_epp_id]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $pdo->prepare("UPDATE inventario_epp SET stock = :stock WHERE id = :id")->execute([':stock' => $total, ':id' => $inventario_epp_id]);
    return (int) $total;
}

// =====================================================
// FUNCIONES DE INVENTARIO (CRUD)
// =====================================================

/**
 * Obtener inventario COMPACTO (1 fila por artículo + tallas como sub-array)
 */
function obtener_inventario_epp_compacto($filtros = []) {
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
    
    $sql = "SELECT i.* FROM inventario_epp i WHERE {$where_sql} ORDER BY i.articulo ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_tallas = $pdo->prepare("SELECT id, talla, stock FROM inventario_epp_tallas WHERE inventario_epp_id = :id ORDER BY talla ASC");
    
    foreach ($articulos as &$art) {
        $stmt_tallas->execute([':id' => $art['id']]);
        $art['tallas_data'] = $stmt_tallas->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($art['tallas_data'])) {
            $art['tallas_data'] = [['id' => 0, 'talla' => 'Única', 'stock' => (int)$art['stock']]];
        }
    }
    
    return $articulos;
}

/**
 * Obtener inventario con tallas expandidas (para otros usos)
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
    
    $sql = "SELECT i.id, i.categoria, i.articulo, i.unidad, i.stock as stock_total,
                   i.precio, i.nombre_proveedor, i.observaciones, i.lote_identificador,
                   i.usuario_creador_nombre, i.fecha_creacion, i.fecha_ultima_edicion,
                   t.id as talla_id, t.talla, t.stock as talla_stock
            FROM inventario_epp i
            LEFT JOIN inventario_epp_tallas t ON i.id = t.inventario_epp_id
            WHERE {$where_sql}
            ORDER BY i.articulo ASC, t.talla ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_epp_por_id($id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM inventario_epp WHERE id = :id AND activo = 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Agregar nuevo artículo + tallas + movimientos automáticos
 */
function agregar_epp($datos) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        $tallas = $datos['tallas'] ?? [];
        $stock_total = 0;
        foreach ($tallas as $t) { $stock_total += (int) ($t['cantidad'] ?? 0); }
        
        if (empty($tallas)) {
            $tallas = [['talla' => 'Única', 'cantidad' => (int) ($datos['stock'] ?? 0)]];
            $stock_total = (int) ($datos['stock'] ?? 0);
        }
        
        $tallas_resumen = implode(', ', array_column($tallas, 'talla'));
        
        $stmt = $pdo->prepare("
            INSERT INTO inventario_epp (
                categoria, articulo, unidad, lote_identificador, stock, stock_minimo, talla,
                precio, nombre_proveedor, observaciones,
                usuario_creador_id, usuario_creador_nombre, departamento_creador
            ) VALUES (
                :categoria, :articulo, :unidad, :lote_identificador, :stock, :stock_minimo, :talla,
                :precio, :nombre_proveedor, :observaciones,
                :usuario_creador_id, :usuario_creador_nombre, :departamento_creador
            )
        ");
        
        $stmt->execute([
            ':categoria'            => $datos['categoria'],
            ':articulo'             => $datos['articulo'],
            ':unidad'               => $datos['unidad'],
            ':lote_identificador'   => $datos['lote_identificador'] ?: null,
            ':stock'                => $stock_total,
            ':stock_minimo'         => (int)($datos['stock_minimo'] ?? 0),
            ':talla'                => $tallas_resumen ?: null,
            ':precio'               => $datos['precio'] ? (float) $datos['precio'] : null,
            ':nombre_proveedor'     => $datos['nombre_proveedor'] ?: null,
            ':observaciones'        => $datos['observaciones'] ?: null,
            ':usuario_creador_id'   => $datos['usuario_id'],
            ':usuario_creador_nombre' => $datos['usuario_nombre'],
            ':departamento_creador' => $datos['departamento']
        ]);
        
        $inventario_id = $pdo->lastInsertId();
        
        $stmt_talla = $pdo->prepare("INSERT INTO inventario_epp_tallas (inventario_epp_id, talla, stock) VALUES (:epp_id, :talla, :stock)");
        $stmt_mov = $pdo->prepare("
            INSERT INTO movimientos_epp (
                inventario_epp_id, talla_id, tipo_movimiento, fecha_movimiento,
                categoria, articulo, talla, cantidad,
                observaciones, stock_resultante,
                usuario_id, usuario_nombre, departamento, es_automatico
            ) VALUES (
                :epp_id, :talla_id, 'Entrada', :fecha,
                :categoria, :articulo, :talla, :cantidad,
                'Entrada automática al agregar nuevo EPP', :stock_resultante,
                :usuario_id, :usuario_nombre, :departamento, 1
            )
        ");
        
        foreach ($tallas as $t) {
            $talla_nombre = trim($t['talla']);
            $talla_stock = (int) ($t['cantidad'] ?? 0);
            if (empty($talla_nombre)) continue;
            
            $stmt_talla->execute([':epp_id' => $inventario_id, ':talla' => $talla_nombre, ':stock' => $talla_stock]);
            $talla_id = $pdo->lastInsertId();
            
            if ($talla_stock > 0) {
                $stmt_mov->execute([
                    ':epp_id' => $inventario_id, ':talla_id' => $talla_id,
                    ':fecha' => $datos['fecha_movimiento'] ?? date('Y-m-d H:i:s'),
                    ':categoria' => $datos['categoria'], ':articulo' => $datos['articulo'],
                    ':talla' => $talla_nombre, ':cantidad' => $talla_stock,
                    ':stock_resultante' => $talla_stock,
                    ':usuario_id' => $datos['usuario_id'], ':usuario_nombre' => $datos['usuario_nombre'],
                    ':departamento' => $datos['departamento']
                ]);
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'EPP agregado correctamente al inventario.', 'id' => $inventario_id];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al agregar EPP: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al agregar EPP: ' . $e->getMessage()];
    }
}

/**
 * Agregar stock a un artículo EXISTENTE (por talla existente o nueva talla)
 */
function agregar_stock_existente($datos) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        $cantidad = (int) $datos['cantidad'];
        $inventario_epp_id = (int) $datos['inventario_epp_id'];
        
        // ¿Es nueva talla o existente?
        if (!empty($datos['nueva_talla_nombre'])) {
            // === CREAR NUEVA TALLA ===
            $nueva_talla = trim($datos['nueva_talla_nombre']);
            
            // Verificar que no exista ya
            $check = $pdo->prepare("SELECT id FROM inventario_epp_tallas WHERE inventario_epp_id = :eid AND talla = :talla");
            $check->execute([':eid' => $inventario_epp_id, ':talla' => $nueva_talla]);
            if ($check->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'message' => "La talla '{$nueva_talla}' ya existe en este artículo. Selecciónala del dropdown."];
            }
            
            // Insertar nueva talla con el stock
            $pdo->prepare("INSERT INTO inventario_epp_tallas (inventario_epp_id, talla, stock) VALUES (:eid, :talla, :stock)")
                ->execute([':eid' => $inventario_epp_id, ':talla' => $nueva_talla, ':stock' => $cantidad]);
            $talla_id = $pdo->lastInsertId();
            
            // Obtener datos del artículo padre
            $art = $pdo->prepare("SELECT * FROM inventario_epp WHERE id = :id");
            $art->execute([':id' => $inventario_epp_id]);
            $articulo_data = $art->fetch(PDO::FETCH_ASSOC);
            
            $talla_nombre = $nueva_talla;
            $articulo_nombre = $articulo_data['articulo'];
            $categoria_nombre = $articulo_data['categoria'];
            $nuevo_stock = $cantidad;
            
        } else {
            // === TALLA EXISTENTE ===
            $talla_id = (int) $datos['talla_id'];
            $talla = obtener_talla_por_id($talla_id, $pdo);
            if (!$talla) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Talla no encontrada.'];
            }
            
            $stock_anterior = (int) $talla['stock'];
            $nuevo_stock = $stock_anterior + $cantidad;
            
            // Actualizar stock de la talla existente
            $pdo->prepare("UPDATE inventario_epp_tallas SET stock = :stock WHERE id = :id")
                ->execute([':stock' => $nuevo_stock, ':id' => $talla_id]);
            
            $talla_nombre = $talla['talla'];
            $articulo_nombre = $talla['articulo'];
            $categoria_nombre = $talla['categoria'];
            $inventario_epp_id = $talla['inventario_epp_id'];
        }
        
        // Recalcular stock total del artículo
        recalcular_stock_epp($inventario_epp_id, $pdo);
        
        // Registrar movimiento de entrada
        $pdo->prepare("
            INSERT INTO movimientos_epp (
                inventario_epp_id, talla_id, tipo_movimiento, fecha_movimiento,
                categoria, articulo, talla, cantidad,
                observaciones, stock_resultante,
                usuario_id, usuario_nombre, departamento, es_automatico
            ) VALUES (
                :epp_id, :talla_id, 'Entrada', :fecha,
                :categoria, :articulo, :talla, :cantidad,
                :obs, :stock_resultante,
                :usuario_id, :usuario_nombre, :departamento, 0
            )
        ")->execute([
            ':epp_id' => $inventario_epp_id, ':talla_id' => $talla_id,
            ':fecha' => $datos['fecha_movimiento'] ?? date('Y-m-d H:i:s'),
            ':categoria' => $categoria_nombre, ':articulo' => $articulo_nombre,
            ':talla' => $talla_nombre, ':cantidad' => $cantidad,
            ':obs' => $datos['observaciones'] ?: "Ingreso de stock a artículo existente",
            ':stock_resultante' => $nuevo_stock,
            ':usuario_id' => $datos['usuario_id'],
            ':usuario_nombre' => $datos['usuario_nombre'],
            ':departamento' => $datos['departamento']
        ]);
        
        $pdo->commit();
        return ['success' => true, 'message' => "Se agregaron {$cantidad} unidades a {$articulo_nombre} (talla {$talla_nombre}). Stock actual: {$nuevo_stock}"];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Actualizar campo inline (NO stock)
 */
function actualizar_campo_epp($id, $campo, $valor) {
    $pdo = conectarDB();
        $campos_permitidos = ['articulo', 'unidad', 'precio', 'nombre_proveedor', 'lote_identificador', 'observaciones', 'categoria', 'stock_minimo'];
    if (!in_array($campo, $campos_permitidos)) {
        return ['success' => false, 'message' => 'Campo no permitido para edicion.'];
    }
    try {
        // Obtener valor anterior para historial
        $stmt_prev = $pdo->prepare("SELECT `{$campo}` as valor_anterior FROM inventario_epp WHERE id = :id");
        $stmt_prev->execute([':id' => $id]);
        $anterior = $stmt_prev->fetch(PDO::FETCH_ASSOC);
        $valor_anterior = $anterior ? ($anterior['valor_anterior'] ?? '') : '';
        
        // Actualizar
        $stmt = $pdo->prepare("UPDATE inventario_epp SET `{$campo}` = :valor WHERE id = :id AND activo = 1");
        $stmt->execute([':valor' => $valor, ':id' => $id]);
        
        // Registrar en historial si el valor cambio
        if ($valor_anterior !== $valor) {
            $pdo->prepare("
                INSERT INTO historial_articulos_epp (inventario_epp_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre)
                VALUES (:epp_id, :campo, :anterior, :nuevo, :uid, :uname)
            ")->execute([
                ':epp_id' => $id,
                ':campo' => $campo,
                ':anterior' => $valor_anterior,
                ':nuevo' => $valor,
                ':uid' => $_SESSION['usuario_id'],
                ':uname' => $_SESSION['nombre_completo']
            ]);
        }
        
        return ['success' => true, 'message' => 'Campo actualizado correctamente.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Obtener historial de cambios de articulos EPP
 */
function obtener_historial_articulos_epp($filtros = []) {
    $pdo = conectarDB();
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filtros['busqueda'])) {
        $where[] = "(h.valor_anterior LIKE :b1 OR h.valor_nuevo LIKE :b2 OR h.usuario_nombre LIKE :b3)";
        $params[':b1'] = '%'.$filtros['busqueda'].'%';
        $params[':b2'] = '%'.$filtros['busqueda'].'%';
        $params[':b3'] = '%'.$filtros['busqueda'].'%';
    }
    if (!empty($filtros['fecha_desde'])) {
        $where[] = "h.fecha_cambio >= :fd";
        $params[':fd'] = $filtros['fecha_desde'] . ' 00:00:00';
    }
    if (!empty($filtros['fecha_hasta'])) {
        $where[] = "h.fecha_cambio <= :fh";
        $params[':fh'] = $filtros['fecha_hasta'] . ' 23:59:59';
    }
    
    $sql = "SELECT h.*, i.articulo as articulo_actual, i.categoria
            FROM historial_articulos_epp h
            LEFT JOIN inventario_epp i ON h.inventario_epp_id = i.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY h.fecha_cambio DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Actualizar stock de una talla específica
 */
function actualizar_stock_talla($talla_id, $nuevo_stock) {
    $pdo = conectarDB();
    try {
        $talla = obtener_talla_por_id($talla_id, $pdo);
        if (!$talla) return ['success' => false, 'message' => 'Talla no encontrada.'];
        
        $stock_anterior = (int) $talla['stock'];
        $nuevo_stock = (int) $nuevo_stock;
        $diferencia = $nuevo_stock - $stock_anterior;
        
        $pdo->prepare("UPDATE inventario_epp_tallas SET stock = :stock WHERE id = :id")
            ->execute([':stock' => $nuevo_stock, ':id' => $talla_id]);
        
        $stock_total = recalcular_stock_epp($talla['inventario_epp_id'], $pdo);
        
        if ($diferencia !== 0) {
            $tipo = $diferencia > 0 ? 'Entrada' : 'Salida';
            $pdo->prepare("
                INSERT INTO movimientos_epp (
                    inventario_epp_id, talla_id, tipo_movimiento, fecha_movimiento,
                    categoria, articulo, talla, cantidad,
                    observaciones, stock_resultante,
                    usuario_id, usuario_nombre, departamento, es_automatico
                ) VALUES (
                    :epp_id, :talla_id, :tipo, NOW(),
                    :categoria, :articulo, :talla, :cantidad,
                    :obs, :stock_resultante,
                    :usuario_id, :usuario_nombre, :departamento, 1
                )
            ")->execute([
                ':epp_id' => $talla['inventario_epp_id'], ':talla_id' => $talla_id,
                ':tipo' => $tipo, ':categoria' => $talla['categoria'],
                ':articulo' => $talla['articulo'], ':talla' => $talla['talla'],
                ':cantidad' => abs($diferencia),
                ':obs' => "Ajuste directo (de {$stock_anterior} a {$nuevo_stock})",
                ':stock_resultante' => $nuevo_stock,
                ':usuario_id' => $_SESSION['usuario_id'],
                ':usuario_nombre' => $_SESSION['nombre_completo'],
                ':departamento' => $_SESSION['departamento']
            ]);
        }
        
        return ['success' => true, 'message' => 'Stock actualizado.', 'stock_total' => $stock_total];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function eliminar_epp($id) {
    $pdo = conectarDB();
    try {
        $pdo->prepare("UPDATE inventario_epp SET activo = 0 WHERE id = :id")->execute([':id' => $id]);
        return ['success' => true, 'message' => 'Artículo eliminado del inventario.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// =====================================================
// FUNCIONES DE MOVIMIENTOS
// =====================================================

function registrar_movimiento_epp($datos) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        // Las salidas se gestionan exclusivamente mediante Vales de Entrega
        if (($datos['tipo_movimiento'] ?? '') === 'Salida') {
            return ['success' => false, 'message' => 'Las salidas se registran mediante Vales de Entrega de EPP.'];
        }
        
        $talla_id = (int) ($datos['talla_id'] ?? 0);
        if (!$talla_id) return ['success' => false, 'message' => 'Debe seleccionar una talla.'];
        
        $talla = obtener_talla_por_id($talla_id, $pdo);
        if (!$talla) return ['success' => false, 'message' => 'Talla no encontrada.'];
        
        $stock_actual = (int) $talla['stock'];
        $cantidad = (int) $datos['cantidad'];
        
        if ($datos['tipo_movimiento'] === 'Entrada') {
            $nuevo_stock = $stock_actual + $cantidad;
        } else {
            if ($cantidad > $stock_actual) {
                return ['success' => false, 'message' => "Stock insuficiente (talla {$talla['talla']}). Actual: {$stock_actual}, solicitado: {$cantidad}"];
            }
            $nuevo_stock = $stock_actual - $cantidad;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO movimientos_epp (
                inventario_epp_id, talla_id, tipo_movimiento, fecha_movimiento,
                categoria, articulo, talla, cantidad,
                nombre_trabajador, observaciones, stock_resultante,
                usuario_id, usuario_nombre, departamento, es_automatico
            ) VALUES (
                :epp_id, :talla_id, :tipo, :fecha,
                :categoria, :articulo, :talla, :cantidad,
                :trabajador, :obs, :stock_resultante,
                :usuario_id, :usuario_nombre, :departamento, 0
            )
        ");
        $stmt->execute([
            ':epp_id' => $talla['inventario_epp_id'], ':talla_id' => $talla_id,
            ':tipo' => $datos['tipo_movimiento'], ':fecha' => $datos['fecha_movimiento'],
            ':categoria' => $talla['categoria'], ':articulo' => $talla['articulo'],
            ':talla' => $talla['talla'], ':cantidad' => $cantidad,
            ':trabajador' => $datos['nombre_trabajador'] ?: null,
            ':obs' => $datos['observaciones'] ?: null,
            ':stock_resultante' => $nuevo_stock,
            ':usuario_id' => $datos['usuario_id'],
            ':usuario_nombre' => $datos['usuario_nombre'],
            ':departamento' => $datos['departamento']
        ]);
        
        $pdo->prepare("UPDATE inventario_epp_tallas SET stock = :stock WHERE id = :id")
            ->execute([':stock' => $nuevo_stock, ':id' => $talla_id]);
        
        recalcular_stock_epp($talla['inventario_epp_id'], $pdo);
        
        $pdo->commit();
        return ['success' => true, 'message' => "Movimiento registrado. Talla {$talla['talla']}: stock {$nuevo_stock}"];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function obtener_movimientos_epp($filtros = []) {
    $pdo = conectarDB();
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filtros['categoria'])) { $where[] = "m.categoria = :cat"; $params[':cat'] = $filtros['categoria']; }
    if (!empty($filtros['tipo_movimiento'])) { $where[] = "m.tipo_movimiento = :tipo"; $params[':tipo'] = $filtros['tipo_movimiento']; }
    if (!empty($filtros['busqueda'])) {
        $where[] = "(m.articulo LIKE :b1 OR m.nombre_trabajador LIKE :b2 OR m.observaciones LIKE :b3)";
        $params[':b1'] = '%'.$filtros['busqueda'].'%'; $params[':b2'] = '%'.$filtros['busqueda'].'%'; $params[':b3'] = '%'.$filtros['busqueda'].'%';
    }
    if (!empty($filtros['fecha_desde'])) { $where[] = "m.fecha_movimiento >= :fd"; $params[':fd'] = $filtros['fecha_desde'] . ' 00:00:00'; }
    if (!empty($filtros['fecha_hasta'])) { $where[] = "m.fecha_movimiento <= :fh"; $params[':fh'] = $filtros['fecha_hasta'] . ' 23:59:59'; }
    if (!empty($filtros['inventario_epp_id'])) { $where[] = "m.inventario_epp_id = :eid"; $params[':eid'] = $filtros['inventario_epp_id']; }
    
    $sql = "SELECT m.*, i.stock as stock_actual, i.unidad FROM movimientos_epp m LEFT JOIN inventario_epp i ON m.inventario_epp_id = i.id WHERE " . implode(' AND ', $where) . " ORDER BY m.fecha_movimiento DESC, m.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_movimiento_por_id($id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT m.*, i.stock as stock_actual, i.unidad, i.nombre_proveedor, i.precio, i.lote_identificador FROM movimientos_epp m LEFT JOIN inventario_epp i ON m.inventario_epp_id = i.id WHERE m.id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// =====================================================
// ESTADÍSTICAS
// =====================================================

function obtener_estadisticas_epp() {
    $pdo = conectarDB();
    $stats = [];
    $stats['total_articulos'] = $pdo->query("SELECT COUNT(*) FROM inventario_epp WHERE activo = 1")->fetchColumn();
    $stats['total_stock'] = $pdo->query("SELECT COALESCE(SUM(t.stock), 0) FROM inventario_epp_tallas t JOIN inventario_epp i ON t.inventario_epp_id = i.id WHERE i.activo = 1")->fetchColumn();
    $stats['sin_stock'] = $pdo->query("SELECT COUNT(*) FROM inventario_epp WHERE activo = 1 AND stock = 0")->fetchColumn();
    $stats['bajo_umbral'] = $pdo->query("SELECT COUNT(*) FROM inventario_epp WHERE activo = 1 AND stock_minimo > 0 AND stock <= stock_minimo")->fetchColumn();
    $stats['total_movimientos'] = $pdo->query("SELECT COUNT(*) FROM movimientos_epp")->fetchColumn();
    $stats['movimientos_mes'] = $pdo->query("SELECT COUNT(*) FROM movimientos_epp WHERE MONTH(fecha_movimiento) = MONTH(NOW()) AND YEAR(fecha_movimiento) = YEAR(NOW())")->fetchColumn();
    
    $stmt = $pdo->query("SELECT tipo_movimiento, COUNT(*) as total FROM movimientos_epp WHERE MONTH(fecha_movimiento) = MONTH(NOW()) AND YEAR(fecha_movimiento) = YEAR(NOW()) GROUP BY tipo_movimiento");
    $movs_mes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $stats['entradas_mes'] = $movs_mes['Entrada'] ?? 0;
    $stats['salidas_mes'] = $movs_mes['Salida'] ?? 0;
    return $stats;
}

/**
 * Obtener articulos en alerta de umbral
 */
function obtener_articulos_bajo_umbral() {
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT id, articulo, categoria, unidad, stock, stock_minimo
        FROM inventario_epp 
        WHERE activo = 1 AND stock_minimo > 0 AND stock <= stock_minimo
        ORDER BY (stock_minimo - stock) DESC, articulo ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_articulos_dropdown_epp() {
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT i.id, i.categoria, i.articulo, i.unidad, i.stock as stock_total,
               COALESCE(t.id, 0) as talla_id, 
               COALESCE(t.talla, 'Unica') as talla, 
               COALESCE(t.stock, i.stock) as talla_stock
        FROM inventario_epp i
        LEFT JOIN inventario_epp_tallas t ON i.id = t.inventario_epp_id
        WHERE i.activo = 1 
        ORDER BY i.categoria ASC, i.articulo ASC, t.talla ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}