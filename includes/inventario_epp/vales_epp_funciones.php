<?php
/**
 * Funciones del Sistema de Vales de Entrega de EPP
 * Ubicación: includes/inventario_epp/vales_epp_funciones.php
 */

require_once __DIR__ . '/inventario_epp_funciones.php';

// =====================================================
// FUNCIONES DE VALES
// =====================================================

/**
 * Generar folio único para vale
 */
function generar_folio_vale() {
    $pdo = conectarDB();
    $anio = date('Y');
    $mes = date('m');
    
    $stmt = $pdo->prepare("SELECT COUNT(*) + 1 as siguiente FROM vales_epp WHERE YEAR(fecha_creacion) = :anio AND MONTH(fecha_creacion) = :mes");
    $stmt->execute([':anio' => $anio, ':mes' => $mes]);
    $num = $stmt->fetch(PDO::FETCH_ASSOC)['siguiente'];
    
    return 'VALE-' . $anio . $mes . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

/**
 * Crear un nuevo vale de entrega de EPP
 */
function crear_vale_epp($datos) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        $folio = generar_folio_vale();
        
        $stmt = $pdo->prepare("
            INSERT INTO vales_epp (
                folio, nombre_empleado, empleado_id, area, estado,
                creado_por_id, creado_por_nombre, observaciones
            ) VALUES (
                :folio, :nombre_empleado, :empleado_id, :area, 'Pendiente',
                :creado_por_id, :creado_por_nombre, :observaciones
            )
        ");
        $stmt->execute([
            ':folio' => $folio,
            ':nombre_empleado' => $datos['nombre_empleado'],
            ':empleado_id' => $datos['empleado_id'] ?? null,
            ':area' => $datos['area'],
            ':creado_por_id' => $datos['usuario_id'],
            ':creado_por_nombre' => $datos['usuario_nombre'],
            ':observaciones' => $datos['observaciones'] ?: null
        ]);
        
        $vale_id = $pdo->lastInsertId();
        
        // Insertar líneas
        $stmt_linea = $pdo->prepare("
            INSERT INTO vales_epp_lineas (
                vale_epp_id, inventario_epp_id, talla_id,
                descripcion, talla, cantidad, motivo, motivo_otro
            ) VALUES (
                :vale_id, :epp_id, :talla_id,
                :descripcion, :talla, :cantidad, :motivo, :motivo_otro
            )
        ");
        
        foreach ($datos['lineas'] as $linea) {
            $stmt_linea->execute([
                ':vale_id' => $vale_id,
                ':epp_id' => $linea['inventario_epp_id'] ?: null,
                ':talla_id' => $linea['talla_id'] ?: null,
                ':descripcion' => $linea['descripcion'],
                ':talla' => $linea['talla'] ?: null,
                ':cantidad' => (int) $linea['cantidad'],
                ':motivo' => $linea['motivo'],
                ':motivo_otro' => $linea['motivo'] === 'Otro' ? ($linea['motivo_otro'] ?? null) : null
            ]);
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => "Vale {$folio} creado correctamente.", 'id' => $vale_id, 'folio' => $folio];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error al crear vale: ' . $e->getMessage()];
    }
}

/**
 * Obtener vale por ID con sus líneas
 */
function obtener_vale_epp($id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("SELECT * FROM vales_epp WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $vale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vale) return null;
    
    $stmt = $pdo->prepare("
        SELECT l.*, 
               i.articulo as articulo_inventario, i.categoria, i.unidad,
               t.stock as stock_actual
        FROM vales_epp_lineas l
        LEFT JOIN inventario_epp i ON l.inventario_epp_id = i.id
        LEFT JOIN inventario_epp_tallas t ON l.talla_id = t.id
        WHERE l.vale_epp_id = :vale_id
        ORDER BY l.id ASC
    ");
    $stmt->execute([':vale_id' => $id]);
    $vale['lineas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $vale;
}

/**
 * Listar vales con filtros
 */
function obtener_vales_epp($filtros = []) {
    $pdo = conectarDB();
    
    $where = ["1=1"];
    $params = [];
    
    // Almacen de Residuos solo ve sus propios vales
    $depto_val = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
    if ($depto_val === 'almacen_residuos') {
        $where[] = "v.creado_por_id = :creador_id";
        $params[':creador_id'] = $_SESSION['usuario_id'];
    }
    
    if (!empty($filtros['estado'])) {
        $where[] = "v.estado = :estado";
        $params[':estado'] = $filtros['estado'];
    }
    if (!empty($filtros['busqueda'])) {
        $where[] = "(v.folio LIKE :b1 OR v.nombre_empleado LIKE :b2 OR v.area LIKE :b3)";
        $params[':b1'] = '%'.$filtros['busqueda'].'%';
        $params[':b2'] = '%'.$filtros['busqueda'].'%';
        $params[':b3'] = '%'.$filtros['busqueda'].'%';
    }
    if (!empty($filtros['fecha_desde'])) {
        $where[] = "v.fecha_creacion >= :fd";
        $params[':fd'] = $filtros['fecha_desde'] . ' 00:00:00';
    }
    if (!empty($filtros['fecha_hasta'])) {
        $where[] = "v.fecha_creacion <= :fh";
        $params[':fh'] = $filtros['fecha_hasta'] . ' 23:59:59';
    }
    
    $sql = "SELECT v.*, 
                   (SELECT COUNT(*) FROM vales_epp_lineas WHERE vale_epp_id = v.id) as total_lineas,
                   (SELECT SUM(cantidad) FROM vales_epp_lineas WHERE vale_epp_id = v.id) as total_piezas
            FROM vales_epp v
            WHERE " . implode(' AND ', $where) . "
            ORDER BY v.folio DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Confirmar entrega de vale (Almacén de Refacciones)
 * Descuenta stock automáticamente
 */
function confirmar_entrega_vale($vale_id, $datos) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        // Obtener vale
        $stmt = $pdo->prepare("SELECT * FROM vales_epp WHERE id = :id AND estado = 'Pendiente' FOR UPDATE");
        $stmt->execute([':id' => $vale_id]);
        $vale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vale) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Vale no encontrado o ya fue procesado.'];
        }
        
        // Verificar expiracion (72 horas)
        $fecha_creacion = new DateTime($vale['fecha_creacion']);
        $ahora = new DateTime();
        $horas_transcurridas = ($ahora->getTimestamp() - $fecha_creacion->getTimestamp()) / 3600;
        if ($horas_transcurridas > 72) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Este vale ha expirado (mas de 72 horas). Debe cancelarse y crear uno nuevo.'];
        }
        
        // Obtener líneas
        $stmt = $pdo->prepare("SELECT * FROM vales_epp_lineas WHERE vale_epp_id = :vale_id");
        $stmt->execute([':vale_id' => $vale_id]);
        $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Validar stock de cada línea antes de descontar
        foreach ($lineas as $linea) {
            if ($linea['talla_id']) {
                $talla = obtener_talla_por_id($linea['talla_id'], $pdo);
                if (!$talla) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => "Talla no encontrada para: {$linea['descripcion']}"];
                }
                if ((int)$talla['stock'] < (int)$linea['cantidad']) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => "Stock insuficiente para {$linea['descripcion']} (talla {$talla['talla']}). Disponible: {$talla['stock']}, solicitado: {$linea['cantidad']}"];
                }
            }
        }
        
        // Descontar stock y registrar movimientos
        foreach ($lineas as $linea) {
            if ($linea['talla_id']) {
                $talla = obtener_talla_por_id($linea['talla_id'], $pdo);
                $nuevo_stock = (int)$talla['stock'] - (int)$linea['cantidad'];
                
                // Actualizar stock talla
                $pdo->prepare("UPDATE inventario_epp_tallas SET stock = :stock WHERE id = :id")
                    ->execute([':stock' => $nuevo_stock, ':id' => $linea['talla_id']]);
                
                // Recalcular total
                recalcular_stock_epp($talla['inventario_epp_id'], $pdo);
                
                // Registrar movimiento de salida
                $pdo->prepare("
                    INSERT INTO movimientos_epp (
                        inventario_epp_id, talla_id, tipo_movimiento, fecha_movimiento,
                        categoria, articulo, talla, cantidad,
                        nombre_trabajador, observaciones, stock_resultante,
                        usuario_id, usuario_nombre, departamento, es_automatico
                    ) VALUES (
                        :epp_id, :talla_id, 'Salida', NOW(),
                        :categoria, :articulo, :talla, :cantidad,
                        :trabajador, :obs, :stock_resultante,
                        :usuario_id, :usuario_nombre, :departamento, 1
                    )
                ")->execute([
                    ':epp_id' => $talla['inventario_epp_id'],
                    ':talla_id' => $linea['talla_id'],
                    ':categoria' => $talla['categoria'],
                    ':articulo' => $talla['articulo'],
                    ':talla' => $talla['talla'],
                    ':cantidad' => $linea['cantidad'],
                    ':trabajador' => $vale['nombre_empleado'],
                    ':obs' => "Entrega por Vale {$vale['folio']}",
                    ':stock_resultante' => $nuevo_stock,
                    ':usuario_id' => $datos['usuario_id'],
                    ':usuario_nombre' => $datos['usuario_nombre'],
                    ':departamento' => $datos['departamento']
                ]);
            }
        }
        
        // Actualizar estado del vale
        $pdo->prepare("
            UPDATE vales_epp SET 
                estado = 'Entregado',
                fecha_entrega = NOW(),
                entregado_por_id = :uid,
                entregado_por_nombre = :uname,
                observaciones_entrega = :obs
            WHERE id = :id
        ")->execute([
            ':uid' => $datos['usuario_id'],
            ':uname' => $datos['usuario_nombre'],
            ':obs' => $datos['observaciones_entrega'] ?: null,
            ':id' => $vale_id
        ]);
        
        $pdo->commit();
        return ['success' => true, 'message' => "Vale {$vale['folio']} confirmado. Stock descontado correctamente."];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Cancelar vale (solo si está Pendiente)
 */
function cancelar_vale_epp($vale_id, $datos) {
    $pdo = conectarDB();
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM vales_epp WHERE id = :id AND estado = 'Pendiente'");
        $stmt->execute([':id' => $vale_id]);
        $vale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vale) {
            return ['success' => false, 'message' => 'Vale no encontrado o ya fue procesado.'];
        }
        
        $pdo->prepare("
            UPDATE vales_epp SET 
                estado = 'Cancelado',
                fecha_cancelacion = NOW(),
                cancelado_por_id = :uid,
                cancelado_por_nombre = :uname,
                motivo_cancelacion = :motivo
            WHERE id = :id
        ")->execute([
            ':uid' => $datos['usuario_id'],
            ':uname' => $datos['usuario_nombre'],
            ':motivo' => $datos['motivo_cancelacion'] ?? 'Cancelado por usuario',
            ':id' => $vale_id
        ]);
        
        return ['success' => true, 'message' => "Vale {$vale['folio']} cancelado."];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Estadísticas de vales
 */
function obtener_estadisticas_vales() {
    $pdo = conectarDB();
    $stats = [];
    
    $depto_st = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
    $filtro_creador = '';
    $params_st = [];
    if ($depto_st === 'almacen_residuos') {
        $filtro_creador = ' AND creado_por_id = :uid';
        $params_st[':uid'] = $_SESSION['usuario_id'];
    }
    
    $s1 = $pdo->prepare("SELECT COUNT(*) FROM vales_epp WHERE 1=1" . $filtro_creador);
    $s1->execute($params_st);
    $stats['total'] = $s1->fetchColumn();
    
    $s2 = $pdo->prepare("SELECT COUNT(*) FROM vales_epp WHERE estado = 'Pendiente'" . $filtro_creador);
    $s2->execute($params_st);
    $stats['pendientes'] = $s2->fetchColumn();
    
    $s3 = $pdo->prepare("SELECT COUNT(*) FROM vales_epp WHERE estado = 'Entregado'" . $filtro_creador);
    $s3->execute($params_st);
    $stats['entregados'] = $s3->fetchColumn();
    
    $s4 = $pdo->prepare("SELECT COUNT(*) FROM vales_epp WHERE estado = 'Cancelado'" . $filtro_creador);
    $s4->execute($params_st);
    $stats['cancelados'] = $s4->fetchColumn();
    
    $s5 = $pdo->prepare("SELECT COUNT(*) FROM vales_epp WHERE estado = 'Entregado' AND MONTH(fecha_entrega) = MONTH(NOW()) AND YEAR(fecha_entrega) = YEAR(NOW())" . $filtro_creador);
    $s5->execute($params_st);
    $stats['entregados_mes'] = $s5->fetchColumn();
    
    return $stats;
}

/**
 * Verificar permisos de vales según departamento
 */
function verificar_permisos_vales() {
    $depto = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
    
    return [
        'puede_crear' => in_array($depto, ['seguridad', 'almacen_residuos']),
        'puede_confirmar' => ($depto === 'almacen_refacciones'),
        'puede_cancelar' => ($depto === 'seguridad'),
        'puede_ver' => in_array($depto, ['seguridad', 'almacen_refacciones', 'contabilidad', 'almacen_residuos']),
        'es_modo_tyvek' => ($depto === 'almacen_residuos'),
        'departamento' => $depto
    ];
}

/**
 * Obtener empleados agrupados por departamento (para dropdown)
 */
function obtener_empleados_por_departamento() {
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT u.id, u.nombre_completo, u.usuario,
               COALESCE(d.nombre, u.departamento) as departamento_nombre,
               COALESCE(d.codigo, LOWER(u.departamento)) as departamento_codigo
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE u.activo = 1
        ORDER BY departamento_nombre ASC, u.nombre_completo ASC
    ");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $agrupados = [];
    foreach ($usuarios as $u) {
        $depto = $u['departamento_codigo'];
        if (!isset($agrupados[$depto])) {
            $agrupados[$depto] = [
                'codigo' => $depto,
                'nombre' => $u['departamento_nombre'],
                'empleados' => []
            ];
        }
        $agrupados[$depto]['empleados'][] = [
            'id' => $u['id'],
            'nombre' => $u['nombre_completo'],
            'usuario' => $u['usuario']
        ];
    }
    
    return array_values($agrupados);
}

/**
 * Verificar si un vale esta expirado (72 horas)
 */
function verificar_expiracion_vale($fecha_creacion) {
    $fecha = new DateTime($fecha_creacion);
    $ahora = new DateTime();
    $horas_transcurridas = ($ahora->getTimestamp() - $fecha->getTimestamp()) / 3600;
    $horas_restantes = 72 - $horas_transcurridas;
    
    if ($horas_restantes <= 0) {
        return ['expirado' => true, 'horas_restantes' => 0, 'texto' => 'Expirado'];
    }
    if ($horas_restantes < 1) {
        return ['expirado' => false, 'horas_restantes' => $horas_restantes, 'texto' => (int)ceil($horas_restantes * 60) . ' min restantes'];
    }
    if ($horas_restantes < 24) {
        return ['expirado' => false, 'horas_restantes' => $horas_restantes, 'texto' => (int)floor($horas_restantes) . 'h restantes'];
    }
    $dias = (int)floor($horas_restantes / 24);
    $hrs = (int)floor($horas_restantes - ($dias * 24));
    return ['expirado' => false, 'horas_restantes' => $horas_restantes, 'texto' => $dias . 'd ' . $hrs . 'h restantes'];
}

?>