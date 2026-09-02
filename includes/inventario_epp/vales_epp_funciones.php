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
        
        // Resolver el empleado seleccionado. Puede venir de empleados_epp ("epp:<id>")
        // o de un usuario de plataforma ("usr:<id>"), en cuyo caso se crea/vincula
        // su registro en empleados_epp dentro de esta misma transaccion.
        $empleado_id = null;
        if (preg_match('/^(epp|usr):(\d+)$/', $datos['empleado_sel'] ?? '', $m)) {
            $empleado_id = ($m[1] === 'epp')
                ? (int) $m[2]
                : obtener_o_crear_empleado_epp_desde_usuario($pdo, (int) $m[2]);
        }
        if (!$empleado_id) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Empleado no válido.'];
        }
        
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
            ':empleado_id' => $empleado_id,
            ':area' => $datos['area'],
            ':creado_por_id' => $datos['usuario_id'],
            ':creado_por_nombre' => $datos['usuario_nombre'],
            ':observaciones' => $datos['observaciones'] ?: null
        ]);
        
        $vale_id = $pdo->lastInsertId();
        
        // Validar que ninguna linea deje el stock bajo el umbral
        foreach ($datos['lineas'] as $linea) {
            if (!empty($linea['talla_id'])) {
                $chk = $pdo->prepare("
                    SELECT t.stock as stock_talla, i.stock as stock_total, i.stock_minimo, i.articulo
                    FROM inventario_epp_tallas t
                    JOIN inventario_epp i ON t.inventario_epp_id = i.id
                    WHERE t.id = :tid
                ");
                $chk->execute([':tid' => $linea['talla_id']]);
                $info = $chk->fetch(PDO::FETCH_ASSOC);
                
                if ($info) {
                    $stock_despues = (int)$info['stock_talla'] - (int)$linea['cantidad'];
                    $minimo = (int)$info['stock_minimo'];
                    
                    // Bloquear si no hay suficiente stock
                    if ((int)$info['stock_talla'] < (int)$linea['cantidad']) {
                        $pdo->rollBack();
                        return ['success' => false, 'message' => "Stock insuficiente para {$info['articulo']}. Disponible: {$info['stock_talla']}, solicitado: {$linea['cantidad']}"];
                    }
                    
                    // Bloquear si quedaria bajo umbral
                    if ($minimo > 0 && $stock_despues < $minimo) {
                        $pdo->rollBack();
                        return ['success' => false, 'message' => "No se puede crear el vale: {$info['articulo']} quedaria con {$stock_despues} unidades, por debajo del umbral minimo de {$minimo}."];
                    }
                }
            }
        }
        
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
 * Obtener empleados agrupados por departamento (para dropdown del vale)
 * Fuente: empleados_epp (incluye externos sin cuenta en la plataforma).
 * Devuelve TODOS los departamentos activos; los que aun no tienen
 * empleados regresan con 'empleados' vacio.
 */
function obtener_empleados_por_departamento() {
    $pdo = conectarDB();

    // Departamentos a ocultar (espacios, no areas de personal). Ajusta la lista.
    $deptos_excluidos = ['SJ', 'SC', 'otros'];

    // ---- 1. Empleados registrados en empleados_epp (externos, manuales, vinculados) ----
    $stmt = $pdo->query("
        SELECT d.codigo, d.nombre AS departamento_nombre,
               e.id AS emp_id, e.nombre_completo, e.no_nomina, e.usuario_id
        FROM departamentos d
        LEFT JOIN empleados_epp e
               ON e.departamento_id = d.id AND e.activo = 1
        WHERE d.activo = 1
        ORDER BY d.nombre ASC, e.nombre_completo ASC
    ");
    $rows_epp = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---- 2. Usuarios de la plataforma que tambien piden vales ----
    $stmt = $pdo->query("
        SELECT u.id AS usuario_id, u.nombre_completo, u.no_nomina,
               d.codigo, d.nombre AS departamento_nombre
        FROM usuarios u
        INNER JOIN departamentos d ON u.departamento_id = d.id
        WHERE u.activo = 1 AND d.activo = 1
        ORDER BY d.nombre ASC, u.nombre_completo ASC
    ");
    $rows_usr = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Usuarios ya representados en empleados_epp (para no duplicar)
    $usuarios_vinculados = [];
    $nominas_usadas = [];
    foreach ($rows_epp as $r) {
        if (!empty($r['usuario_id'])) $usuarios_vinculados[(int) $r['usuario_id']] = true;
        if (!empty($r['no_nomina']))  $nominas_usadas[$r['no_nomina']] = true;
    }

    // ---- 3. Armar estructura agrupada por departamento ----
    $agrupados = [];

    foreach ($rows_epp as $r) {
        if (in_array($r['codigo'], $deptos_excluidos, true)) continue;
        if (!isset($agrupados[$r['codigo']])) {
            $agrupados[$r['codigo']] = ['codigo' => $r['codigo'], 'nombre' => $r['departamento_nombre'], 'empleados' => []];
        }
        if (!empty($r['emp_id'])) {
            $agrupados[$r['codigo']]['empleados'][] = [
                'id'         => 'epp:' . $r['emp_id'],
                // Sin nombre -> se identifica por su ID/nomina
                'nombre'     => ($r['nombre_completo'] !== null && $r['nombre_completo'] !== '') ? $r['nombre_completo'] : ($r['no_nomina'] ?: ''),
                'id_display' => $r['no_nomina'] ?: ''
            ];
        }
    }

    foreach ($rows_usr as $r) {
        if (in_array($r['codigo'], $deptos_excluidos, true)) continue;
        if (isset($usuarios_vinculados[(int) $r['usuario_id']])) continue;
        if (!empty($r['no_nomina']) && isset($nominas_usadas[$r['no_nomina']])) continue;
        if (!isset($agrupados[$r['codigo']])) {
            $agrupados[$r['codigo']] = ['codigo' => $r['codigo'], 'nombre' => $r['departamento_nombre'], 'empleados' => []];
        }
        $agrupados[$r['codigo']]['empleados'][] = [
            'id'         => 'usr:' . $r['usuario_id'],
            'nombre'     => $r['nombre_completo'],
            'id_display' => $r['no_nomina'] ?: ''
        ];
    }

    // Ordenar empleados por nombre dentro de cada departamento
    foreach ($agrupados as $codigo => $grupo) {
        usort($agrupados[$codigo]['empleados'], function ($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });
    }

    return array_values($agrupados);
}

/**
 * Obtener (o crear) el registro en empleados_epp de un usuario de plataforma.
 * Usa la MISMA conexion/transaccion que se le pase (no abre otra, para no
 * provocar deadlocks). Devuelve el id de empleados_epp o null.
 */
function obtener_o_crear_empleado_epp_desde_usuario(PDO $pdo, $usuario_id) {
    $usuario_id = (int) $usuario_id;
    if ($usuario_id <= 0) return null;

    // 1. Ya vinculado?
    $stmt = $pdo->prepare("SELECT id FROM empleados_epp WHERE usuario_id = :uid AND activo = 1 LIMIT 1");
    $stmt->execute([':uid' => $usuario_id]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id;

    // 2. Datos del usuario
    $stmt = $pdo->prepare("SELECT id, nombre_completo, no_nomina, departamento_id FROM usuarios WHERE id = :uid");
    $stmt->execute([':uid' => $usuario_id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u || empty($u['departamento_id'])) return null; // sin depto no se puede (NOT NULL)

    // 3. Existe ya un registro manual con esa misma nomina? Vincularlo.
    if (!empty($u['no_nomina'])) {
        $stmt = $pdo->prepare("SELECT id FROM empleados_epp WHERE no_nomina = :nom AND activo = 1 LIMIT 1");
        $stmt->execute([':nom' => $u['no_nomina']]);
        $id_existente = $stmt->fetchColumn();
        if ($id_existente) {
            $pdo->prepare("UPDATE empleados_epp SET usuario_id = :uid WHERE id = :id")
                ->execute([':uid' => $usuario_id, ':id' => $id_existente]);
            return (int) $id_existente;
        }
    }

    // 4. Crear nuevo registro vinculado
    $stmt = $pdo->prepare("
        INSERT INTO empleados_epp (usuario_id, nombre_completo, no_nomina, departamento_id, origen, observaciones)
        VALUES (:uid, :nombre, :nomina, :depto, 'usuario', 'Vinculado desde cuenta de plataforma')
    ");
    $stmt->execute([
        ':uid'    => $usuario_id,
        ':nombre' => $u['nombre_completo'],
        ':nomina' => $u['no_nomina'] ?: null,
        ':depto'  => $u['departamento_id']
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Alta rapida de empleado desde el formulario del vale (origen = 'vale').
 * $datos: departamento_codigo, nombre_completo, no_nomina (opcional).
 * Es un INSERT autonomo (no entra en la transaccion del vale), asi que
 * usa su propia conexion sin riesgo de deadlock.
 */
function crear_empleado_epp_inline($datos) {
    $pdo = conectarDB();

    $nombre = trim($datos['nombre_completo'] ?? '');
    $nomina = trim($datos['no_nomina'] ?? '');
    $codigo = trim($datos['departamento_codigo'] ?? '');

    // Ahora la nomina (ID) es obligatoria; el nombre es opcional
    if ($nomina === '') return ['success' => false, 'message' => 'El ID / nómina es obligatorio.'];
    if ($codigo === '') return ['success' => false, 'message' => 'Seleccione primero un departamento.'];

    $stmt = $pdo->prepare("SELECT id FROM departamentos WHERE codigo = :cod AND activo = 1 LIMIT 1");
    $stmt->execute([':cod' => $codigo]);
    $depto_id = $stmt->fetchColumn();
    if (!$depto_id) return ['success' => false, 'message' => 'Departamento no válido.'];

    // La nomina debe ser unica (uk_no_nomina_epp)
    $chk = $pdo->prepare("SELECT id FROM empleados_epp WHERE no_nomina = :nom LIMIT 1");
    $chk->execute([':nom' => $nomina]);
    if ($chk->fetch()) {
        return ['success' => false, 'message' => "El ID/nómina '{$nomina}' ya está registrado en otro empleado."];
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO empleados_epp (nombre_completo, no_nomina, departamento_id, origen, created_by, observaciones)
            VALUES (:nombre, :nomina, :depto, 'vale', :creado_por, 'Alta rápida desde vale')
        ");
        $stmt->execute([
            ':nombre'     => $nombre, // puede ir vacio
            ':nomina'     => $nomina,
            ':depto'      => (int) $depto_id,
            ':creado_por' => $_SESSION['usuario_id'] ?? null
        ]);
        $nuevo_id = (int) $pdo->lastInsertId();

        return [
            'success'  => true,
            'message'  => 'Empleado agregado.',
            'empleado' => [
                'value'      => 'epp:' . $nuevo_id,
                'id_display' => $nomina,
                'nombre'     => $nombre !== '' ? $nombre : $nomina // fallback para el snapshot del vale
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
    }
}

/**
 * Listado de empleados EPP para administracion (con filtros).
 */
function obtener_empleados_epp_admin($filtros = []) {
    $pdo = conectarDB();
    $where = ["1=1"];
    $params = [];

    if (!empty($filtros['departamento_id'])) {
        $where[] = "e.departamento_id = :depto";
        $params[':depto'] = (int) $filtros['departamento_id'];
    }
    if (!empty($filtros['busqueda'])) {
        $where[] = "(e.nombre_completo LIKE :b1 OR e.no_nomina LIKE :b2)";
        $params[':b1'] = '%' . $filtros['busqueda'] . '%';
        $params[':b2'] = '%' . $filtros['busqueda'] . '%';
    }
    if (empty($filtros['incluir_inactivos'])) {
        $where[] = "e.activo = 1";
    }

    $sql = "SELECT e.*, d.nombre AS departamento_nombre
            FROM empleados_epp e
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.activo DESC, d.nombre ASC, e.nombre_completo ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Departamentos activos (para filtro y edicion).
 */
function obtener_departamentos_para_empleados() {
    $pdo = conectarDB();
    return $pdo->query("SELECT id, codigo, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre ASC")
               ->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Actualizar empleado EPP (nombre opcional, ID obligatorio, con nomina unica).
 */
function actualizar_empleado_epp($datos) {
    $pdo = conectarDB();
    $id       = (int) ($datos['id'] ?? 0);
    $nombre   = trim($datos['nombre_completo'] ?? '');
    $nomina   = trim($datos['no_nomina'] ?? '');
    $depto_id = (int) ($datos['departamento_id'] ?? 0);

    if ($id <= 0)        return ['success' => false, 'message' => 'Empleado no válido.'];
    if ($nomina === '')  return ['success' => false, 'message' => 'El ID / nómina es obligatorio.'];
    if ($depto_id <= 0)  return ['success' => false, 'message' => 'Seleccione un departamento.'];

    $chkd = $pdo->prepare("SELECT id FROM departamentos WHERE id = :did AND activo = 1 LIMIT 1");
    $chkd->execute([':did' => $depto_id]);
    if (!$chkd->fetch()) return ['success' => false, 'message' => 'Departamento no válido.'];

    // Nomina unica, excluyendose a si mismo (placeholders distintos por EMULATE_PREPARES=false)
    $chk = $pdo->prepare("SELECT id FROM empleados_epp WHERE no_nomina = :nom AND id <> :id_self LIMIT 1");
    $chk->execute([':nom' => $nomina, ':id_self' => $id]);
    if ($chk->fetch()) {
        return ['success' => false, 'message' => "El ID/nómina '{$nomina}' ya está en otro empleado."];
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE empleados_epp
            SET nombre_completo = :nombre, no_nomina = :nomina,
                departamento_id = :depto, updated_by = :upd
            WHERE id = :id
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':nomina' => $nomina,
            ':depto'  => $depto_id,
            ':upd'    => $_SESSION['usuario_id'] ?? null,
            ':id'     => $id
        ]);
        return ['success' => true, 'message' => 'Empleado actualizado.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()];
    }
}

/**
 * Activar / desactivar empleado (soft; nunca se borra para no romper vales historicos).
 */
function cambiar_estado_empleado_epp($id, $activo) {
    $pdo = conectarDB();
    $id = (int) $id;
    $activo = $activo ? 1 : 0;
    if ($id <= 0) return ['success' => false, 'message' => 'Empleado no válido.'];
    try {
        $stmt = $pdo->prepare("UPDATE empleados_epp SET activo = :act, updated_by = :upd WHERE id = :id");
        $stmt->execute([':act' => $activo, ':upd' => $_SESSION['usuario_id'] ?? null, ':id' => $id]);
        return ['success' => true, 'message' => $activo ? 'Empleado reactivado.' : 'Empleado desactivado.', 'activo' => $activo];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
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