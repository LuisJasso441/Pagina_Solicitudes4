<?php
/**
 * Funciones Backend - Órdenes de Servicio para Mantenimiento
 * Archivo: includes/ordenes_servicio/ordenes_servicio_funciones.php
 * 
 * ACTUALIZADO: Usa tabla permisos_osm para validar acceso
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * Obtener permisos OSM del usuario
 * @param int $usuario_id
 * @return array|null
 */
function obtener_permisos_osm_usuario($usuario_id) {
    static $cache = [];
    
    if (isset($cache[$usuario_id])) {
        return $cache[$usuario_id];
    }
    
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT lector, creador, editor FROM permisos_osm WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $usuario_id]);
        $permisos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si no tiene registro, por defecto tiene permiso de lector
        if (!$permisos) {
            $permisos = ['lector' => 1, 'creador' => 0, 'editor' => 0];
        }
        
        $cache[$usuario_id] = $permisos;
        return $permisos;
    } catch (Exception $e) {
        error_log("Error obteniendo permisos OSM: " . $e->getMessage());
        // Por defecto, permitir lectura
        return ['lector' => 1, 'creador' => 0, 'editor' => 0];
    }
}

/**
 * Obtener una orden por ID
 */
function obtener_orden_por_id($orden_id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT * FROM ordenes_servicio_mantenimiento 
        WHERE id = :id
    ");
    
    $stmt->execute([':id' => $orden_id]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($orden) {
        // Decodificar JSON
        $orden['apartado1'] = !empty($orden['apartado1_data']) ? json_decode($orden['apartado1_data'], true) : [];
        $orden['apartado2'] = !empty($orden['apartado2_data']) ? json_decode($orden['apartado2_data'], true) : [];
        $orden['apartado3'] = !empty($orden['apartado3_data']) ? json_decode($orden['apartado3_data'], true) : [];
    }
    
    return $orden;
}

/**
 * Verificar permisos de usuario sobre una orden
 * ACTUALIZADO: Usa tabla permisos_osm para validar acceso
 */
function verificar_permiso_orden($orden_id, $usuario_id, $departamento) {
    $orden = obtener_orden_por_id($orden_id);
    
    if (!$orden) {
        return ['puede_ver' => false, 'puede_editar' => false, 'es_propietario' => false];
    }
    
    $departamento_codigo = is_string($departamento) ? strtolower(trim($departamento)) : $departamento;
    $es_mantenimiento = ($departamento_codigo === 'mantenimiento');
    $es_propietario = ($orden['usuario_id'] == $usuario_id);
    
    // ========================================
    // OBTENER PERMISOS DE LA TABLA permisos_osm
    // ========================================
    $permisos_osm = obtener_permisos_osm_usuario($usuario_id);
    $tiene_permiso_lector = ($permisos_osm['lector'] == 1);
    $tiene_permiso_editor = ($permisos_osm['editor'] == 1);
    $tiene_permiso_creador = ($permisos_osm['creador'] == 1);
    
    // ========================================
    // DETERMINAR PERMISOS DE ACCESO
    // ========================================
    // puede_ver: Si tiene permiso de lector, es mantenimiento, o es propietario
    $puede_ver = $tiene_permiso_lector || $es_mantenimiento || $es_propietario;
    
    $permisos = [
        'puede_ver' => $puede_ver,
        'puede_editar' => false,
        'es_propietario' => $es_propietario,
        'es_mantenimiento' => $es_mantenimiento,
        'tiene_permiso_lector' => $tiene_permiso_lector,
        'tiene_permiso_editor' => $tiene_permiso_editor,
        'tiene_permiso_creador' => $tiene_permiso_creador
    ];
    
    // Determinar si puede editar según estado y permisos
    switch ($orden['estado']) {
        case 'pendiente_mantenimiento':
            // Mantenimiento puede editar Apartado 2
            $permisos['puede_editar'] = $es_mantenimiento;
            // Propietario puede editar Apartado 1 (si tiene permiso editor o es propietario)
            $permisos['puede_editar_apartado1'] = $es_propietario && ($tiene_permiso_editor || $es_propietario);
            break;
            
        case 'en_proceso':
            $permisos['puede_editar'] = $es_mantenimiento;
            $permisos['puede_editar_apartado1'] = $es_propietario && ($tiene_permiso_editor || $es_propietario);
            break;
            
        case 'pendiente_usuario':
            $permisos['puede_editar'] = false;
            $permisos['puede_firmar'] = true;
            $permisos['puede_devolver'] = $es_propietario;
            $permisos['puede_finalizar'] = $es_propietario;
            break;
            
        case 'devuelto':
            $permisos['puede_editar'] = $es_mantenimiento;
            break;
            
        case 'completado':
            $permisos['puede_editar'] = false;
            break;
    }
    
    return $permisos;
}

/**
 * Función auxiliar: Obtener usuarios de Mantenimiento
 */
function obtener_usuarios_mantenimiento() {
    try {
        $pdo = conectarDB();
        
        // Buscar con LOWER y TRIM para asegurar coincidencia
        $stmt = $pdo->prepare("
            SELECT id, nombre_completo, departamento 
            FROM usuarios 
            WHERE LOWER(TRIM(departamento)) = 'mantenimiento' 
            AND activo = 1
        ");
        $stmt->execute();
        $usuarios_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($usuarios_data)) {
            return array_column($usuarios_data, 'id');
        }
        
        // OPCIÓN 2: Buscar con LIKE por si tiene variaciones
        $stmt = $pdo->prepare("
            SELECT id, nombre_completo, departamento 
            FROM usuarios 
            WHERE LOWER(departamento) LIKE '%mantenimiento%'
            AND activo = 1
        ");
        $stmt->execute();
        $usuarios_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($usuarios_data)) {
            return array_column($usuarios_data, 'id');
        }
        
        error_log("⚠️ No se encontró ningún usuario de Mantenimiento");
        return [];
        
    } catch (Exception $e) {
        error_log("❌ Error al obtener usuarios de Mantenimiento: " . $e->getMessage());
        return [];
    }
}

/**
 * Crear nueva orden (Apartado 1)
 */
function crear_orden($datos_apartado1, $usuario_id, $usuario_nombre, $departamento, $empresa) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        // Verificar que el folio no exista
        $stmt = $pdo->prepare("SELECT id FROM ordenes_servicio_mantenimiento WHERE folio = :folio");
        $stmt->execute([':folio' => $datos_apartado1['folio']]);
        
        if ($stmt->fetch()) {
            throw new Exception("El folio ya existe. Por favor, use otro folio.");
        }
        
        // Insertar orden
        $stmt = $pdo->prepare("
            INSERT INTO ordenes_servicio_mantenimiento (
                folio, usuario_id, usuario_nombre, departamento, empresa,
                estado, apartado1_data, fecha_creacion
            ) VALUES (
                :folio, :usuario_id, :usuario_nombre, :departamento, :empresa,
                'pendiente_mantenimiento', :apartado1_data, NOW()
            )
        ");
        
        $stmt->execute([
            ':folio' => $datos_apartado1['folio'],
            ':usuario_id' => $usuario_id,
            ':usuario_nombre' => $usuario_nombre,
            ':departamento' => $departamento,
            ':empresa' => $empresa,
            ':apartado1_data' => json_encode($datos_apartado1, JSON_UNESCAPED_UNICODE)
        ]);
        
        $orden_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        return ['success' => true, 'orden_id' => $orden_id];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Editar Apartado 1 (Usuario puede editar su orden)
 */
function editar_apartado1($orden_id, $datos_apartado1, $usuario_id) {
    $pdo = conectarDB();
    
    try {
        // Verificar permisos
        $orden = obtener_orden_por_id($orden_id);
        if ($orden['usuario_id'] != $usuario_id) {
            throw new Exception("No tienes permiso para editar esta orden.");
        }
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE ordenes_servicio_mantenimiento 
            SET apartado1_data = :apartado1_data,
                fecha_ultima_modificacion = NOW(),
                usuario_ultima_modificacion_id = :usuario_id
            WHERE id = :orden_id
        ");
        
        $stmt->execute([
            ':apartado1_data' => json_encode($datos_apartado1, JSON_UNESCAPED_UNICODE),
            ':usuario_id' => $usuario_id,
            ':orden_id' => $orden_id
        ]);
        
        $pdo->commit();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Guardar Apartado 2 (Mantenimiento - sin enviar)
 */
function guardar_apartado2($orden_id, $datos_apartado2, $usuario_id, $usuario_nombre) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        $orden = obtener_orden_por_id($orden_id);
        
        // Si es la primera vez que se guarda apartado2, actualizar fecha
        $es_primera_vez = empty($orden['apartado2_data']);
        
        $sql = "
            UPDATE ordenes_servicio_mantenimiento 
            SET apartado2_data = :apartado2_data,
                estado = 'en_proceso',
                fecha_ultima_modificacion = NOW(),
                usuario_ultima_modificacion_id = :usuario_id,
                usuario_ultima_modificacion_nombre = :usuario_nombre
        ";
        
        if ($es_primera_vez) {
            $sql .= ", fecha_primer_guardado_mant = NOW()";
        }
        
        $sql .= " WHERE id = :orden_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':apartado2_data' => json_encode($datos_apartado2, JSON_UNESCAPED_UNICODE),
            ':usuario_id' => $usuario_id,
            ':usuario_nombre' => $usuario_nombre,
            ':orden_id' => $orden_id
        ]);
        
        $pdo->commit();
        
        return ['success' => true, 'es_primera_vez' => $es_primera_vez];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Enviar Apartado 2 a Usuario (Mantenimiento)
 */
function enviar_apartado2($orden_id, $datos_apartado2, $usuario_id, $usuario_nombre) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        $orden = obtener_orden_por_id($orden_id);
        
        $stmt = $pdo->prepare("
            UPDATE ordenes_servicio_mantenimiento 
            SET apartado2_data = :apartado2_data,
                estado = 'pendiente_usuario',
                fecha_enviado_usuario = NOW(),
                fecha_ultima_modificacion = NOW(),
                usuario_ultima_modificacion_id = :usuario_id,
                usuario_ultima_modificacion_nombre = :usuario_nombre
            WHERE id = :orden_id
        ");
        
        $stmt->execute([
            ':apartado2_data' => json_encode($datos_apartado2, JSON_UNESCAPED_UNICODE),
            ':usuario_id' => $usuario_id,
            ':usuario_nombre' => $usuario_nombre,
            ':orden_id' => $orden_id
        ]);
        
        $pdo->commit();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Devolver orden (Usuario)
 */
function devolver_orden($orden_id, $usuario_id) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        $orden = obtener_orden_por_id($orden_id);
        
        if ($orden['usuario_id'] != $usuario_id) {
            throw new Exception("No tienes permiso para devolver esta orden.");
        }
        
        $stmt = $pdo->prepare("
            UPDATE ordenes_servicio_mantenimiento 
            SET estado = 'devuelto',
                fecha_ultima_modificacion = NOW()
            WHERE id = :orden_id
        ");
        
        $stmt->execute([':orden_id' => $orden_id]);
        
        $pdo->commit();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Finalizar orden (Usuario)
 */
function finalizar_orden($orden_id, $datos_apartado3, $usuario_id) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        $orden = obtener_orden_por_id($orden_id);
        
        if ($orden['usuario_id'] != $usuario_id) {
            throw new Exception("No tienes permiso para finalizar esta orden.");
        }
        
        $stmt = $pdo->prepare("
            UPDATE ordenes_servicio_mantenimiento 
            SET apartado3_data = :apartado3_data,
                estado = 'completado',
                fecha_completado = NOW(),
                fecha_ultima_modificacion = NOW()
            WHERE id = :orden_id
        ");
        
        $stmt->execute([
            ':apartado3_data' => json_encode($datos_apartado3, JSON_UNESCAPED_UNICODE),
            ':orden_id' => $orden_id
        ]);
        
        $pdo->commit();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Guardar firma en Apartado 3
 */
function guardar_firma_apartado3($orden_id, $tipo_firma, $datos_firma, $usuario_id) {
    $pdo = conectarDB();
    
    try {
        $orden = obtener_orden_por_id($orden_id);
        $apartado3 = $orden['apartado3'] ?? [];
        
        // Actualizar firma correspondiente
        if ($tipo_firma === 'solicitante') {
            $apartado3['nombre_solicitante'] = $datos_firma['nombre'];
            $apartado3['firma_solicitante'] = $datos_firma['firma'];
            $apartado3['fecha_firma_solicitante'] = date('Y-m-d H:i:s');
        } else if ($tipo_firma === 'mantenimiento') {
            $apartado3['nombre_responsable_mantenimiento'] = $datos_firma['nombre'];
            $apartado3['firma_responsable_mantenimiento'] = $datos_firma['firma'];
            $apartado3['fecha_firma_mantenimiento'] = date('Y-m-d H:i:s');
        }
        
        $stmt = $pdo->prepare("
            UPDATE ordenes_servicio_mantenimiento 
            SET apartado3_data = :apartado3_data,
                fecha_ultima_modificacion = NOW()
            WHERE id = :orden_id
        ");
        
        $stmt->execute([
            ':apartado3_data' => json_encode($apartado3, JSON_UNESCAPED_UNICODE),
            ':orden_id' => $orden_id
        ]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtener órdenes por usuario
 */
function obtener_ordenes_usuario($usuario_id, $departamento, $estado_filtro = null) {
    $pdo = conectarDB();
    
    $sql = "
        SELECT 
            id, folio, estado, empresa, fecha_creacion,
            apartado1_data, apartado2_data, apartado3_data
        FROM ordenes_servicio_mantenimiento
        WHERE usuario_id = :usuario_id
    ";
    
    $params = [':usuario_id' => $usuario_id];
    
    if ($estado_filtro) {
        $sql .= " AND estado = :estado";
        $params[':estado'] = $estado_filtro;
    }
    
    $sql .= " ORDER BY fecha_creacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Contar órdenes por estado (para estadísticas)
 */
function contar_ordenes_por_estado($departamento = null, $usuario_id = null) {
    $pdo = conectarDB();
    
    $sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'pendiente_mantenimiento' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'pendiente_usuario' THEN 1 ELSE 0 END) as pendientes_usuario,
            SUM(CASE WHEN estado = 'devuelto' THEN 1 ELSE 0 END) as devueltas,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completadas
        FROM ordenes_servicio_mantenimiento
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($usuario_id) {
        $sql .= " AND usuario_id = :usuario_id";
        $params[':usuario_id'] = $usuario_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Genera el próximo folio LOG-### para órdenes de servicio de Logística.
 * 
 * IMPORTANTE: Debe llamarse DENTRO de una transacción activa.
 * Usa FOR UPDATE para bloquear la lectura del máximo actual y evitar
 * que dos altas simultáneas obtengan el mismo número.
 * 
 * El correlativo es continuo (no reinicia por año) y solo considera
 * folios con prefijo LOG-, ignorando los OM-... viejos capturados a mano.
 * 
 * Como última defensa contra race conditions, la columna 'folio' tiene
 * UNIQUE KEY y el caller debe reintentar si el INSERT falla con SQLSTATE 23000.
 * 
 * @param PDO $pdo Conexión con transacción ya iniciada
 * @return string Folio con formato LOG-### (3 dígitos con ceros a la izquierda)
 */
function generar_folio_logistica_osm(PDO $pdo): string {
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(CAST(SUBSTRING(folio, 5) AS UNSIGNED)), 0) AS max_num
        FROM ordenes_servicio_mantenimiento
        WHERE folio LIKE 'LOG-%'
        FOR UPDATE
    ");
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $siguiente_numero = ((int)($resultado['max_num'] ?? 0)) + 1;
    
    return sprintf('LOG-%03d', $siguiente_numero);
}
?>