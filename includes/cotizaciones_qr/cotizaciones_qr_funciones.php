<?php
/**
 * Funciones para el m?dulo de Cotizaciones de Qu?micos y/o Residuos (CQR) v3.0
 * Ubicaci?n: includes/cotizaciones_qr/cotizaciones_qr_funciones.php
 * 
 * ACTUALIZACI?N 23/01/2026:
 * - Folio consecutivo global: CQR-DDMMYYYY-000x
 * - Campos renombrados: nombre_real_semarnat, nombre_ante_semarnat
 * - Nuevos campos: tipo_cliente, nombre_cliente
 * - Estados: enviada, en_revision, aceptada, rechazada
 * - Validaci?n de archivos: ficha=PDF, formato=PDF/XLSX
 * - Acceso: Ventas (creador), Normatividad (editor), Laboratorio/Direcci?n (lectores)
 */

// =====================================================
// FUNCIONES DE PERMISOS
// =====================================================

/**
 * Verificar permisos del usuario para el m?dulo CQR
 */
function verificar_permisos_cqr($usuario_id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT lector, creador, editor 
        FROM permisos_cqr 
        WHERE user_id = :usuario_id
    ");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $permisos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'tiene_acceso' => $permisos ? ($permisos['lector'] || $permisos['creador'] || $permisos['editor']) : false,
        'puede_leer' => $permisos['lector'] ?? false,
        'puede_crear' => $permisos['creador'] ?? false,  // Ventas
        'puede_editar' => $permisos['editor'] ?? false,  // Normatividad
        'puede_comentar' => $permisos ? ($permisos['lector'] || $permisos['creador'] || $permisos['editor']) : false
    ];
}

/**
 * Verificar si el usuario pertenece a un departamento espec?fico del m?dulo CQR
 */
function obtener_rol_cqr($departamento) {
    $depto = strtolower($departamento);
    
    if (strpos($depto, 'ventas') !== false) {
        return 'ventas';
    } elseif (strpos($depto, 'normatividad') !== false) {
        return 'normatividad';
    } elseif (strpos($depto, 'laboratorio') !== false) {
        return 'laboratorio';
    } elseif (strpos($depto, 'direccion') !== false || strpos($depto, 'direcci?n') !== false) {
        return 'direccion';
    }
    
    return 'otro';
}

// =====================================================
// FUNCIONES DE CONSULTA
// =====================================================

/**
 * Obtener cotizaciones seg?n ubicaci?n y filtros
 */
function obtener_cotizaciones_qr($ubicacion = 'local', $usuario_id = null, $departamento = null, $filtros = []) {
    $pdo = conectarDB();
    
    $where = ["c.ubicacion = :ubicacion"];
    $params = [':ubicacion' => $ubicacion];
    
    // Filtro por estado
    if (!empty($filtros['estado'])) {
        $where[] = "c.estado = :estado";
        $params[':estado'] = $filtros['estado'];
    }
    
    // Filtro por busqueda (actualizado con nuevos campos)
    if (!empty($filtros['busqueda'])) {
        $busqueda_valor = '%' . $filtros['busqueda'] . '%';
        $where[] = "(c.folio LIKE :busqueda1 OR c.nombre_real_semarnat LIKE :busqueda2 OR c.nombre_ante_semarnat LIKE :busqueda3 OR c.nombre_cliente LIKE :busqueda4)";
        $params[':busqueda1'] = $busqueda_valor;
        $params[':busqueda2'] = $busqueda_valor;
        $params[':busqueda3'] = $busqueda_valor;
        $params[':busqueda4'] = $busqueda_valor;
    }
    
    // Filtro por fecha
    if (!empty($filtros['fecha_desde'])) {
        $where[] = "DATE(c.fecha_creacion) >= :fecha_desde";
        $params[':fecha_desde'] = $filtros['fecha_desde'];
    }
    if (!empty($filtros['fecha_hasta'])) {
        $where[] = "DATE(c.fecha_creacion) <= :fecha_hasta";
        $params[':fecha_hasta'] = $filtros['fecha_hasta'];
    }
    
    // Filtro por estado de respuesta de Normatividad
    if (!empty($filtros['estado_normatividad'])) {
        $where[] = "c.estado_normatividad = :estado_normatividad";
        $params[':estado_normatividad'] = $filtros['estado_normatividad'];
    }
    
    // Filtro por tipo de cliente
    if (!empty($filtros['tipo_cliente'])) {
        $where[] = "c.tipo_cliente = :tipo_cliente";
        $params[':tipo_cliente'] = $filtros['tipo_cliente'];
    }
    
    $where_sql = implode(' AND ', $where);
    
    $sql = "
        SELECT 
            c.*,
            uc.nombre_completo as creador_nombre,
            un.nombre_completo as normatividad_nombre,
            d.nombre as departamento_nombre
        FROM cotizaciones_quimicos_residuos c
        INNER JOIN usuarios uc ON c.usuario_creador_id = uc.id
        LEFT JOIN usuarios un ON c.usuario_normatividad_id = un.id
        LEFT JOIN departamentos d ON c.departamento_id = d.id
        WHERE {$where_sql}
        ORDER BY c.fecha_creacion DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener una cotizaci?n por ID
 */
function obtener_cotizacion_qr_por_id($id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            uc.nombre_completo as creador_nombre,
            uc.departamento as creador_departamento,
            un.nombre_completo as normatividad_nombre,
            d.nombre as departamento_nombre
        FROM cotizaciones_quimicos_residuos c
        INNER JOIN usuarios uc ON c.usuario_creador_id = uc.id
        LEFT JOIN usuarios un ON c.usuario_normatividad_id = un.id
        LEFT JOIN departamentos d ON c.departamento_id = d.id
        WHERE c.id = :id
    ");
    $stmt->execute([':id' => $id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtener una cotizaci?n por folio
 */
function obtener_cotizacion_qr_por_folio($folio) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            uc.nombre_completo as creador_nombre,
            un.nombre_completo as normatividad_nombre
        FROM cotizaciones_quimicos_residuos c
        INNER JOIN usuarios uc ON c.usuario_creador_id = uc.id
        LEFT JOIN usuarios un ON c.usuario_normatividad_id = un.id
        WHERE c.folio = :folio
    ");
    $stmt->execute([':folio' => $folio]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtener estad?sticas del m?dulo CQR (actualizado con nuevos estados)
 */
function obtener_estadisticas_cqr($usuario_id = null, $departamento = null) {
    $pdo = conectarDB();
    
    $sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'enviada' THEN 1 ELSE 0 END) as enviadas,
            SUM(CASE WHEN estado = 'en_revision' THEN 1 ELSE 0 END) as en_revision,
            SUM(CASE WHEN estado = 'aceptada' THEN 1 ELSE 0 END) as aceptadas,
            SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
            SUM(CASE WHEN estado_normatividad IS NULL OR estado_normatividad = '' THEN 1 ELSE 0 END) as pendientes_respuesta
        FROM cotizaciones_quimicos_residuos
        WHERE ubicacion = 'local'
    ";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// =====================================================
// FUNCIONES DE CREACI?N Y ACTUALIZACI?N
// =====================================================

/**
 * Crear nueva cotizaci?n (Ventas) - ACTUALIZADO v3.1
 */
function crear_cotizacion_qr($datos) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        // Generar folio autom?tico consecutivo
        $folio_data = generar_folio_cqr();
        $folio = $folio_data['folio'];
        $numero_folio = $folio_data['numero'];
        
        $stmt = $pdo->prepare("
            INSERT INTO cotizaciones_quimicos_residuos (
                numero_folio, folio, fecha_solicitud, 
                nombre_real_semarnat, nombre_ante_semarnat, 
                tipo_cliente, nombre_cliente,
                comentarios_ventas,
                ficha_tecnica, formato_descripcion, imagenes_residuo,
                usuario_creador_id, departamento_creador, departamento_id,
                estado, ubicacion, fecha_enviada
            ) VALUES (
                :numero_folio, :folio, :fecha_solicitud,
                :nombre_real_semarnat, :nombre_ante_semarnat,
                :tipo_cliente, :nombre_cliente,
                :comentarios_ventas,
                :ficha_tecnica, :formato_descripcion, :imagenes_residuo,
                :usuario_creador_id, :departamento_creador, :departamento_id,
                'enviada', 'local', NOW()
            )
        ");
        
        $stmt->execute([
            ':numero_folio' => $numero_folio,
            ':folio' => $folio,
            ':fecha_solicitud' => $datos['fecha_solicitud'] ?? date('Y-m-d'),
            ':nombre_real_semarnat' => $datos['nombre_real_semarnat'],
            ':nombre_ante_semarnat' => $datos['nombre_ante_semarnat'] ?? null,
            ':tipo_cliente' => $datos['tipo_cliente'],
            ':nombre_cliente' => $datos['nombre_cliente'],
            ':comentarios_ventas' => $datos['comentarios_ventas'] ?? null,
            ':ficha_tecnica' => $datos['ficha_tecnica'] ?? null,
            ':formato_descripcion' => $datos['formato_descripcion'] ?? null,
            ':imagenes_residuo' => $datos['imagenes_residuo'] ?? null,
            ':usuario_creador_id' => $datos['usuario_creador_id'],
            ':departamento_creador' => $datos['departamento_creador'],
            ':departamento_id' => $datos['departamento_id'] ?? null
        ]);
        
        $cotizacion_id = $pdo->lastInsertId();
        
        // Registrar en historial
        registrar_historial_cqr(
            $cotizacion_id, 
            $folio,
            $datos['usuario_creador_id'], 
            $datos['creador_nombre'] ?? 'Usuario',
            $datos['departamento_creador'],
            'creada',
            null,
            'enviada',
            'Cotizacion creada y enviada a Normatividad'
        );
        
        // Notificar a Normatividad
        $usuarios_normatividad = obtener_usuarios_normatividad();
        foreach ($usuarios_normatividad as $usuario) {
            crear_notificacion_cqr(
                $usuario['id'],
                'cqr_nueva',
                $cotizacion_id,
                "Nueva solicitud de cotizacion {$folio} - Cliente: {$datos['nombre_cliente']}",
                $folio
            );
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'id' => $cotizacion_id,
            'folio' => $folio
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Responder cotizaci?n (Normatividad) - ACTUALIZADO v3.0
 */
function responder_cotizacion_qr($cotizacion_id, $datos, $usuario_id) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        // Obtener cotizaci?n actual
        $cotizacion = obtener_cotizacion_qr_por_id($cotizacion_id);
        if (!$cotizacion) {
            throw new Exception("Cotizacion no encontrada");
        }
        
        // Determinar el nuevo estado basado en la decisi?n de Normatividad
        $estado_normatividad = $datos['decision']; // aceptada, rechazada, en_revision
        $nuevo_estado = $datos['decision']; // El estado interno ahora refleja la decisi?n
        
        // Determinar fecha seg?n estado
        $fecha_campo = '';
        switch ($datos['decision']) {
            case 'aceptada':
                $fecha_campo = 'fecha_aceptada';
                break;
            case 'rechazada':
                $fecha_campo = 'fecha_rechazada';
                break;
            case 'en_revision':
                $fecha_campo = 'fecha_en_revision';
                break;
        }
        
        // Construir SQL din?mico
        $sql = "
            UPDATE cotizaciones_quimicos_residuos SET
                resultados = :resultados,
                estado_normatividad = :estado_normatividad,
                usuario_normatividad_id = :usuario_normatividad_id,
                estado = :estado,
                {$fecha_campo} = NOW()
            WHERE id = :id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':resultados' => $datos['resultados'] ?? null,
            ':estado_normatividad' => $estado_normatividad,
            ':usuario_normatividad_id' => $usuario_id,
            ':estado' => $nuevo_estado,
            ':id' => $cotizacion_id
        ]);
        
        // Si es estado final (aceptada o rechazada), mover a global
        if (in_array($datos['decision'], ['aceptada', 'rechazada'])) {
            $stmt = $pdo->prepare("UPDATE cotizaciones_quimicos_residuos SET ubicacion = 'global' WHERE id = :id");
            $stmt->execute([':id' => $cotizacion_id]);
        }
        
        // Obtener datos del usuario para historial
        $stmt = $pdo->prepare("SELECT nombre_completo, departamento FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $usuario_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Registrar en historial
        registrar_historial_cqr(
            $cotizacion_id,
            $cotizacion['folio'],
            $usuario_id,
            $usuario['nombre_completo'] ?? 'Usuario',
            $usuario['departamento'] ?? 'Normatividad',
            $datos['decision'],
            $cotizacion['estado'],
            $nuevo_estado,
            "Cotizacion marcada como " . ucfirst($datos['decision']) . " por Normatividad"
        );
        
        // Notificar al creador
        $creador = obtener_creador_cotizacion_cqr($cotizacion_id);
        if ($creador) {
            $mensaje_decision = $datos['decision'] === 'aceptada' ? 'Aceptada' : ($datos['decision'] === 'rechazada' ? 'Rechazada' : 'En Revision');
            crear_notificacion_cqr(
                $creador['id'],
                'cqr_respuesta',
                $cotizacion_id,
                "Tu cotizacion {$cotizacion['folio']} ha sido marcada como {$mensaje_decision}",
                $cotizacion['folio']
            );
        }
        
        $pdo->commit();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Marcar cotizaci?n como "en revisi?n" cuando Normatividad la abre
 */
function marcar_en_revision_cqr($cotizacion_id, $usuario_id) {
    $pdo = conectarDB();
    
    try {
        // Verificar estado actual
        $stmt = $pdo->prepare("SELECT estado, folio FROM cotizaciones_quimicos_residuos WHERE id = :id");
        $stmt->execute([':id' => $cotizacion_id]);
        $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cotizacion && $cotizacion['estado'] === 'enviada') {
            $stmt = $pdo->prepare("
                UPDATE cotizaciones_quimicos_residuos 
                SET estado = 'en_revision', fecha_en_revision = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $cotizacion_id]);
            
            // Obtener datos del usuario
            $stmt = $pdo->prepare("SELECT nombre_completo, departamento FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $usuario_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Registrar en historial
            registrar_historial_cqr(
                $cotizacion_id,
                $cotizacion['folio'],
                $usuario_id,
                $usuario['nombre_completo'] ?? 'Usuario',
                $usuario['departamento'] ?? 'Normatividad',
                'en_revision',
                'enviada',
                'en_revision',
                'Normatividad comenz? la revisi?n'
            );
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error al marcar en revisi?n CQR: " . $e->getMessage());
        return false;
    }
}

/**
 * Registrar evento en historial de cotizaci?n
 */
function registrar_historial_cqr($cotizacion_id, $folio, $usuario_id, $usuario_nombre, $departamento, $accion, $estado_anterior, $estado_nuevo, $detalles = null) {
    $pdo = conectarDB();
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO cotizaciones_qr_historial 
            (cotizacion_id, folio_cotizacion, usuario_id, usuario_nombre, departamento, accion, estado_anterior, estado_nuevo, detalles)
            VALUES 
            (:cotizacion_id, :folio, :usuario_id, :usuario_nombre, :departamento, :accion, :estado_anterior, :estado_nuevo, :detalles)
        ");
        
        $stmt->execute([
            ':cotizacion_id' => $cotizacion_id,
            ':folio' => $folio,
            ':usuario_id' => $usuario_id,
            ':usuario_nombre' => $usuario_nombre,
            ':departamento' => $departamento,
            ':accion' => $accion,
            ':estado_anterior' => $estado_anterior,
            ':estado_nuevo' => $estado_nuevo,
            ':detalles' => $detalles
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error al registrar historial CQR: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener historial de una cotizaci?n
 */
function obtener_historial_cqr($cotizacion_id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT * FROM cotizaciones_qr_historial 
        WHERE cotizacion_id = :cotizacion_id 
        ORDER BY fecha_hora DESC
    ");
    $stmt->execute([':cotizacion_id' => $cotizacion_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =====================================================
// FUNCIONES DE COMENTARIOS
// =====================================================

/**
 * Agregar comentario a cotizaci?n
 * Notifica a todos los usuarios de departamentos involucrados (Ventas, Normatividad, Laboratorio, Direcci?n)
 */
function agregar_comentario_cqr($cotizacion_id, $usuario_id, $departamento, $comentario) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        // Obtener datos del usuario que comenta
        $stmt = $pdo->prepare("SELECT nombre_completo FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $usuario_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Insertar comentario
        $stmt = $pdo->prepare("
            INSERT INTO cotizaciones_qr_comentarios 
            (cotizacion_id, usuario_id, usuario_nombre, departamento, comentario)
            VALUES 
            (:cotizacion_id, :usuario_id, :usuario_nombre, :departamento, :comentario)
        ");
        
        $stmt->execute([
            ':cotizacion_id' => $cotizacion_id,
            ':usuario_id' => $usuario_id,
            ':usuario_nombre' => $usuario['nombre_completo'] ?? 'Usuario',
            ':departamento' => $departamento,
            ':comentario' => $comentario
        ]);
        
        // Obtener datos de la cotizaci?n
        $cotizacion = obtener_cotizacion_qr_por_id($cotizacion_id);
        
        // Obtener todos los usuarios de los departamentos involucrados con permisos CQR
        $stmt = $pdo->query("
            SELECT DISTINCT u.id, u.nombre_completo, d.codigo as departamento_codigo
            FROM usuarios u
            INNER JOIN departamentos d ON u.departamento_id = d.id
            INNER JOIN permisos_cqr p ON u.id = p.user_id
            WHERE d.codigo IN ('ventas', 'normatividad', 'laboratorio', 'direccion')
            AND u.activo = 1
            AND (p.lector = 1 OR p.creador = 1 OR p.editor = 1)
        ");
        $usuarios_departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Notificar a todos excepto al que comenta
        foreach ($usuarios_departamentos as $usuario_destino) {
            if ($usuario_destino['id'] != $usuario_id) {
                crear_notificacion_cqr(
                    $usuario_destino['id'],
                    'cqr_comentario',
                    $cotizacion_id,
                    "Nuevo comentario en cotizacion {$cotizacion['folio']} por " . ($usuario['nombre_completo'] ?? 'Usuario') . " (" . ucfirst($departamento) . ")",
                    $cotizacion['folio']
                );
            }
        }
        
        // Registrar en historial
        registrar_historial_cqr(
            $cotizacion_id,
            $cotizacion['folio'],
            $usuario_id,
            $usuario['nombre_completo'] ?? 'Usuario',
            $departamento,
            'comentario',
            null,
            null,
            substr($comentario, 0, 100) . (strlen($comentario) > 100 ? '...' : '')
        );
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al agregar comentario CQR: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener comentarios de una cotizaci?n
 */
function obtener_comentarios_cqr($cotizacion_id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT * FROM cotizaciones_qr_comentarios 
        WHERE cotizacion_id = :cotizacion_id 
        ORDER BY fecha_creacion ASC
    ");
    $stmt->execute([':cotizacion_id' => $cotizacion_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =====================================================
// FUNCIONES DE NOTIFICACIONES
// =====================================================

/**
 * Obtener usuarios del departamento Normatividad con permisos CQR
 * MEJORADO: Busca por código o nombre del departamento y verifica permisos
 */
function obtener_usuarios_normatividad() {
    $pdo = conectarDB();
    
    // Buscar usuarios de Normatividad con permisos CQR activos
    $stmt = $pdo->query("
        SELECT DISTINCT u.id, u.nombre_completo, u.departamento
        FROM usuarios u
        INNER JOIN departamentos d ON u.departamento_id = d.id
        INNER JOIN permisos_cqr p ON u.id = p.user_id
        WHERE (LOWER(d.codigo) LIKE '%normatividad%' OR LOWER(d.nombre) LIKE '%normatividad%')
        AND u.activo = 1
        AND (p.lector = 1 OR p.editor = 1)
    ");
    
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log para diagnóstico
    if (empty($usuarios)) {
        error_log("[CQR] ADVERTENCIA: No se encontraron usuarios de Normatividad con permisos CQR");
    } else {
        error_log("[CQR] Usuarios Normatividad encontrados: " . count($usuarios));
    }
    
    return $usuarios;
}

/**
 * Obtener el creador de una cotizaci?n para notificarle
 */
function obtener_creador_cotizacion_cqr($cotizacion_id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.nombre_completo, u.departamento
        FROM cotizaciones_quimicos_residuos c
        INNER JOIN usuarios u ON c.usuario_creador_id = u.id
        WHERE c.id = :id
    ");
    $stmt->execute([':id' => $cotizacion_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crear notificacion para el modulo CQR
 * CORREGIDO: Funciona correctamente con la BD
 */
function crear_notificacion_cqr($usuario_destino_id, $tipo, $cotizacion_id, $mensaje, $folio = '') {
    $pdo = conectarDB();
    
    try {
        if (empty($usuario_destino_id)) {
            error_log("[CQR] Error: usuario_destino_id esta vacio");
            return false;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, leida, fecha_creacion)
            VALUES (:tipo, :titulo, :mensaje, :usuario_destino, :datos_json, 0, NOW())
        ");
        
        $url = URL_BASE . "dashboard/cotizaciones_qr/ver_cotizacion_qr.php?id=" . $cotizacion_id;
        
        $datos = json_encode([
            'cotizacion_id' => $cotizacion_id,
            'folio' => $folio,
            'url' => $url
        ]);
        
        $titulos = [
            'cqr_nueva' => 'Nueva Cotizacion QR',
            'cqr_respuesta' => 'Respuesta a Cotizacion QR',
            'cqr_comentario' => 'Nuevo Comentario CQR'
        ];
        $titulo = $titulos[$tipo] ?? 'Cotizacion QR';
        
        $stmt->execute([
            ':tipo' => $tipo,
            ':titulo' => $titulo,
            ':mensaje' => $mensaje,
            ':usuario_destino' => $usuario_destino_id,
            ':datos_json' => $datos
        ]);
        
        error_log("[CQR] Notificacion creada: tipo={$tipo}, usuario={$usuario_destino_id}, folio={$folio}");
        
        return true;
    } catch (Exception $e) {
        error_log("[CQR] Error al crear notificacion: " . $e->getMessage());
        return false;
    }
}

// =====================================================
// FUNCIONES DE ARCHIVOS
// =====================================================

/**
 * Procesar archivo subido para CQR - ACTUALIZADO v3.1
 * Ahora usa subcarpetas: Documentos/ e Imagenes/
 * @param array $archivo Datos del archivo $_FILES
 * @param string $tipo 'ficha', 'formato' o 'imagen'
 * @return string|array JSON con info del archivo o array con error
 */
function procesar_archivo_cqr($archivo, $tipo) {
    if (!isset($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
        return null;
    }
    
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    
    // Validaci?n espec?fica seg?n tipo de archivo
    if ($tipo === 'ficha') {
        // Ficha T?cnica: SOLO PDF
        $extensiones_permitidas = ['pdf'];
        $mensaje_error = 'La Ficha T?cnica solo permite archivos PDF';
        $subcarpeta = 'Documentos';
    } elseif ($tipo === 'formato') {
        // Formato Descripci?n: SOLO PDF y Excel
        $extensiones_permitidas = ['pdf', 'xlsx'];
        $mensaje_error = 'El Formato Descripci?n solo permite archivos PDF y Excel (.xlsx)';
        $subcarpeta = 'Documentos';
    } elseif ($tipo === 'imagen') {
        // Im?genes del Residuo: cualquier formato de imagen
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'tif'];
        $mensaje_error = 'Solo se permiten archivos de imagen';
        $subcarpeta = 'Imagenes';
    } else {
        // Para otros tipos
        $extensiones_permitidas = ['pdf', 'xlsx'];
        $mensaje_error = 'Tipo de archivo no permitido';
        $subcarpeta = 'Documentos';
    }
    
    if (!in_array($extension, $extensiones_permitidas)) {
        return ['error' => $mensaje_error];
    }
    
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($archivo['size'] > $max_size) {
        return ['error' => 'El archivo excede el tama?o m?ximo permitido (10MB)'];
    }
    
    // Directorio de destino con subcarpeta
    $directorio_base = __DIR__ . '/../../Imagenes_QR/';
    $directorio = $directorio_base . $subcarpeta . '/';
    
    // Crear directorios si no existen
    if (!is_dir($directorio_base)) {
        mkdir($directorio_base, 0755, true);
    }
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }
    
    // Nombre ?nico
    $nombre_guardado = 'cqr_' . $tipo . '_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $ruta_completa = $directorio . $nombre_guardado;
    
    if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
        return json_encode([
            'nombre_original' => $archivo['name'],
            'nombre_guardado' => $nombre_guardado,
            'ruta' => 'Imagenes_QR/' . $subcarpeta . '/' . $nombre_guardado,
            'tipo' => $archivo['type'],
            'tamanio' => $archivo['size'],
            'extension' => $extension,
            'fecha_subida' => date('Y-m-d H:i:s')
        ]);
    }
    
    return ['error' => 'Error al guardar el archivo'];
}

/**
 * Procesar m?ltiples im?genes del residuo
 * @param array $archivos Array de archivos $_FILES['imagenes_residuo']
 * @return string|null JSON con array de im?genes o null si no hay archivos
 */
function procesar_imagenes_residuo_cqr($archivos) {
    if (!isset($archivos['name']) || !is_array($archivos['name'])) {
        return null;
    }
    
    $imagenes = [];
    $errores = [];
    
    $count = count($archivos['name']);
    
    for ($i = 0; $i < $count; $i++) {
        // Saltar archivos vac?os
        if (empty($archivos['name'][$i]) || $archivos['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        // Crear estructura de archivo individual
        $archivo_individual = [
            'name' => $archivos['name'][$i],
            'type' => $archivos['type'][$i],
            'tmp_name' => $archivos['tmp_name'][$i],
            'error' => $archivos['error'][$i],
            'size' => $archivos['size'][$i]
        ];
        
        // Procesar archivo
        $resultado = procesar_archivo_cqr($archivo_individual, 'imagen');
        
        if (is_array($resultado) && isset($resultado['error'])) {
            $errores[] = $archivos['name'][$i] . ': ' . $resultado['error'];
        } elseif ($resultado) {
            $imagenes[] = json_decode($resultado, true);
        }
    }
    
    if (!empty($errores)) {
        return ['errores' => $errores, 'imagenes' => $imagenes];
    }
    
    if (empty($imagenes)) {
        return null;
    }
    
    return json_encode($imagenes);
}

/**
 * Procesar m?ltiples fichas t?cnicas (hasta 5 PDFs)
 * @param array $archivos Array de archivos $_FILES['ficha_tecnica']
 * @return string|null JSON con array de fichas o null si no hay archivos
 */
function procesar_fichas_tecnicas_cqr($archivos) {
    if (!isset($archivos['name']) || !is_array($archivos['name'])) {
        return null;
    }
    
    $fichas = [];
    $errores = [];
    
    $count = count($archivos['name']);
    
    for ($i = 0; $i < $count; $i++) {
        // Saltar archivos vac?os
        if (empty($archivos['name'][$i]) || $archivos['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        // Crear estructura de archivo individual
        $archivo_individual = [
            'name' => $archivos['name'][$i],
            'type' => $archivos['type'][$i],
            'tmp_name' => $archivos['tmp_name'][$i],
            'error' => $archivos['error'][$i],
            'size' => $archivos['size'][$i]
        ];
        
        // Procesar archivo
        $resultado = procesar_archivo_cqr($archivo_individual, 'ficha');
        
        if (is_array($resultado) && isset($resultado['error'])) {
            $errores[] = 'Ficha #' . ($i + 1) . ': ' . $resultado['error'];
        } elseif ($resultado) {
            $fichas[] = json_decode($resultado, true);
        }
    }
    
    if (!empty($errores)) {
        return ['errores' => $errores, 'fichas' => $fichas];
    }
    
    if (empty($fichas)) {
        return null;
    }
    
    return json_encode($fichas);
}

// =====================================================
// FUNCIONES DE UTILIDAD / HELPERS
// =====================================================

/**
 * Generar folio autom?tico consecutivo global - NUEVO v3.0
 * Formato: CQR-DDMMYYYY-000x
 * El n?mero es consecutivo global (no reinicia por d?a)
 */
function generar_folio_cqr() {
    $pdo = conectarDB();
    
    $fecha = date('dmY');
    
    // Obtener el ?ltimo n?mero de folio global
    $stmt = $pdo->query("SELECT MAX(numero_folio) as ultimo FROM cotizaciones_quimicos_residuos");
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $numero = ($resultado['ultimo'] ?? 0) + 1;
    
    // Formato: CQR-DDMMYYYY-0001
    $folio = 'CQR-' . $fecha . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    
    return [
        'folio' => $folio,
        'numero' => $numero
    ];
}

/**
 * Obtener etiqueta de estado con color - ACTUALIZADO v3.0
 */
function obtener_badge_estado_cqr($estado) {
    $badges = [
        'enviada' => '<span class="badge bg-primary">Enviada</span>',
        'en_revision' => '<span class="badge bg-warning text-dark">En Revisi?n</span>',
        'aceptada' => '<span class="badge bg-success">Aceptada</span>',
        'rechazada' => '<span class="badge bg-danger">Rechazada</span>'
    ];
    
    return $badges[$estado] ?? '<span class="badge bg-secondary">' . ucfirst($estado) . '</span>';
}

/**
 * Obtener etiqueta de tipo de cliente
 */
function obtener_badge_tipo_cliente($tipo) {
    if ($tipo === 'nuevo') {
        return '<span class="badge bg-info">Cliente Nuevo</span>';
    } elseif ($tipo === 'frecuente') {
        return '<span class="badge bg-success">Cliente Frecuente</span>';
    }
    return '<span class="badge bg-secondary">' . ucfirst($tipo) . '</span>';
}

/**
 * Obtener color del departamento para comentarios
 */
function obtener_color_departamento_cqr($departamento) {
    $depto = strtolower($departamento);
    
    if (strpos($depto, 'ventas') !== false) {
        return '#0d6efd'; // Azul
    } elseif (strpos($depto, 'normatividad') !== false) {
        return '#198754'; // Verde
    } elseif (strpos($depto, 'laboratorio') !== false) {
        return '#6f42c1'; // Purpura
    } elseif (strpos($depto, 'direccion') !== false || strpos($depto, 'direcci?n') !== false) {
        return '#dc3545'; // Rojo
    }
    
    return '#6c757d'; // Gris
}

/**
 * Verificar si Normatividad ya respondio con decision final
 */
function normatividad_respondio_cqr($cotizacion) {
    return in_array($cotizacion['estado'], ['aceptada', 'rechazada']);
}

// =====================================================
// FUNCIONES DE EDICION DE ARCHIVOS
// =====================================================

/**
 * Verificar si el usuario es el creador de la cotizacion
 */
function es_creador_cotizacion_cqr($cotizacion_id, $usuario_id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("SELECT usuario_creador_id FROM cotizaciones_quimicos_residuos WHERE id = :id");
    $stmt->execute([':id' => $cotizacion_id]);
    $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $cotizacion && $cotizacion['usuario_creador_id'] == $usuario_id;
}

/**
 * Eliminar un archivo especifico de la cotizacion
 * @param int $cotizacion_id ID de la cotizacion
 * @param string $tipo_archivo 'ficha_tecnica', 'formato_descripcion', 'imagenes_residuo'
 * @param int $indice Indice del archivo a eliminar (para arrays)
 * @param int $usuario_id ID del usuario que realiza la accion
 * @return array Resultado de la operacion
 */
function eliminar_archivo_cqr($cotizacion_id, $tipo_archivo, $indice, $usuario_id) {
    $pdo = conectarDB();
    
    try {
        // Verificar que el usuario es el creador
        if (!es_creador_cotizacion_cqr($cotizacion_id, $usuario_id)) {
            return ['success' => false, 'error' => 'No tienes permiso para editar esta cotizacion'];
        }
        
        // Obtener cotizacion actual
        $stmt = $pdo->prepare("SELECT ficha_tecnica, formato_descripcion, imagenes_residuo, folio FROM cotizaciones_quimicos_residuos WHERE id = :id");
        $stmt->execute([':id' => $cotizacion_id]);
        $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cotizacion) {
            return ['success' => false, 'error' => 'Cotizacion no encontrada'];
        }
        
        $archivo_eliminar = null;
        $nuevo_valor = null;
        
        if ($tipo_archivo === 'ficha_tecnica') {
            $fichas = json_decode($cotizacion['ficha_tecnica'], true);
            
            // Verificar si es formato antiguo (objeto) o nuevo (array)
            if (isset($fichas['nombre_original'])) {
                // Formato antiguo - un solo archivo
                $archivo_eliminar = $fichas;
                $nuevo_valor = null;
            } else if (is_array($fichas) && isset($fichas[$indice])) {
                // Formato nuevo - array de archivos
                $archivo_eliminar = $fichas[$indice];
                unset($fichas[$indice]);
                $fichas = array_values($fichas); // Reindexar
                $nuevo_valor = !empty($fichas) ? json_encode($fichas) : null;
            } else {
                return ['success' => false, 'error' => 'Archivo no encontrado'];
            }
            
            $stmt = $pdo->prepare("UPDATE cotizaciones_quimicos_residuos SET ficha_tecnica = :valor WHERE id = :id");
            $stmt->execute([':valor' => $nuevo_valor, ':id' => $cotizacion_id]);
            
        } elseif ($tipo_archivo === 'formato_descripcion') {
            $formato = json_decode($cotizacion['formato_descripcion'], true);
            if ($formato) {
                $archivo_eliminar = $formato;
                $stmt = $pdo->prepare("UPDATE cotizaciones_quimicos_residuos SET formato_descripcion = NULL WHERE id = :id");
                $stmt->execute([':id' => $cotizacion_id]);
            }
            
        } elseif ($tipo_archivo === 'imagenes_residuo') {
            $imagenes = json_decode($cotizacion['imagenes_residuo'], true);
            
            if (is_array($imagenes) && isset($imagenes[$indice])) {
                $archivo_eliminar = $imagenes[$indice];
                unset($imagenes[$indice]);
                $imagenes = array_values($imagenes);
                $nuevo_valor = !empty($imagenes) ? json_encode($imagenes) : null;
                
                $stmt = $pdo->prepare("UPDATE cotizaciones_quimicos_residuos SET imagenes_residuo = :valor WHERE id = :id");
                $stmt->execute([':valor' => $nuevo_valor, ':id' => $cotizacion_id]);
            } else {
                return ['success' => false, 'error' => 'Imagen no encontrada'];
            }
        } else {
            return ['success' => false, 'error' => 'Tipo de archivo no valido'];
        }
        
        // Eliminar archivo fisico si existe
        if ($archivo_eliminar && isset($archivo_eliminar['ruta'])) {
            $ruta_fisica = __DIR__ . '/../../' . $archivo_eliminar['ruta'];
            if (file_exists($ruta_fisica)) {
                unlink($ruta_fisica);
            }
        }
        
        // Registrar en historial
        $stmt = $pdo->prepare("SELECT nombre_completo, departamento FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $usuario_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        registrar_historial_cqr(
            $cotizacion_id,
            $cotizacion['folio'],
            $usuario_id,
            $usuario['nombre_completo'] ?? 'Usuario',
            $usuario['departamento'] ?? 'Ventas',
            'archivo_eliminado',
            null,
            null,
            "Archivo eliminado: " . ($archivo_eliminar['nombre_original'] ?? 'archivo')
        );
        
        return ['success' => true, 'mensaje' => 'Archivo eliminado correctamente'];
        
    } catch (Exception $e) {
        error_log("[CQR] Error al eliminar archivo: " . $e->getMessage());
        return ['success' => false, 'error' => 'Error al eliminar el archivo'];
    }
}

/**
 * Agregar archivos adicionales a una cotizacion existente
 * @param int $cotizacion_id ID de la cotizacion
 * @param string $tipo_archivo 'ficha_tecnica', 'formato_descripcion', 'imagenes_residuo'
 * @param array $archivos Array de archivos $_FILES
 * @param int $usuario_id ID del usuario
 * @return array Resultado de la operacion
 */
function agregar_archivos_cqr($cotizacion_id, $tipo_archivo, $archivos, $usuario_id) {
    $pdo = conectarDB();
    
    try {
        // Verificar que el usuario es el creador
        if (!es_creador_cotizacion_cqr($cotizacion_id, $usuario_id)) {
            return ['success' => false, 'error' => 'No tienes permiso para editar esta cotizacion'];
        }
        
        // Obtener cotizacion actual
        $stmt = $pdo->prepare("SELECT ficha_tecnica, formato_descripcion, imagenes_residuo, folio FROM cotizaciones_quimicos_residuos WHERE id = :id");
        $stmt->execute([':id' => $cotizacion_id]);
        $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cotizacion) {
            return ['success' => false, 'error' => 'Cotizacion no encontrada'];
        }
        
        $archivos_agregados = 0;
        $errores = [];
        
        if ($tipo_archivo === 'ficha_tecnica') {
            // Obtener fichas existentes
            $fichas_existentes = [];
            if (!empty($cotizacion['ficha_tecnica'])) {
                $fichas = json_decode($cotizacion['ficha_tecnica'], true);
                if (isset($fichas['nombre_original'])) {
                    // Formato antiguo - convertir a array
                    $fichas_existentes = [$fichas];
                } else {
                    $fichas_existentes = $fichas;
                }
            }
            
            // Procesar nuevos archivos
            foreach ($archivos['tmp_name'] as $key => $tmp_name) {
                if ($archivos['error'][$key] === UPLOAD_ERR_OK) {
                    $archivo_individual = [
                        'name' => $archivos['name'][$key],
                        'type' => $archivos['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $archivos['error'][$key],
                        'size' => $archivos['size'][$key]
                    ];
                    
                    $resultado = procesar_archivo_cqr($archivo_individual, 'ficha');
                    if (is_string($resultado)) {
                        $fichas_existentes[] = json_decode($resultado, true);
                        $archivos_agregados++;
                    } elseif (isset($resultado['error'])) {
                        $errores[] = $archivos['name'][$key] . ': ' . $resultado['error'];
                    }
                }
            }
            
            // Validar maximo 5 fichas
            if (count($fichas_existentes) > 5) {
                return ['success' => false, 'error' => 'Maximo 5 fichas tecnicas permitidas'];
            }
            
            $stmt = $pdo->prepare("UPDATE cotizaciones_quimicos_residuos SET ficha_tecnica = :valor WHERE id = :id");
            $stmt->execute([':valor' => json_encode($fichas_existentes), ':id' => $cotizacion_id]);
            
        } elseif ($tipo_archivo === 'formato_descripcion') {
            // Solo un archivo de formato - tomar el primero del array
            if (!empty($archivos['tmp_name'][0]) && $archivos['error'][0] === UPLOAD_ERR_OK) {
                $archivo_individual = [
                    'name' => $archivos['name'][0],
                    'type' => $archivos['type'][0],
                    'tmp_name' => $archivos['tmp_name'][0],
                    'error' => $archivos['error'][0],
                    'size' => $archivos['size'][0]
                ];
                
                $resultado = procesar_archivo_cqr($archivo_individual, 'formato');
                if (is_string($resultado)) {
                    $stmt = $pdo->prepare("UPDATE cotizaciones_quimicos_residuos SET formato_descripcion = :valor WHERE id = :id");
                    $stmt->execute([':valor' => $resultado, ':id' => $cotizacion_id]);
                    $archivos_agregados = 1;
                } elseif (isset($resultado['error'])) {
                    $errores[] = $resultado['error'];
                }
            }
            
        } elseif ($tipo_archivo === 'imagenes_residuo') {
            // Obtener imagenes existentes
            $imagenes_existentes = [];
            if (!empty($cotizacion['imagenes_residuo'])) {
                $imagenes_existentes = json_decode($cotizacion['imagenes_residuo'], true) ?? [];
            }
            
            // Procesar nuevas imagenes
            foreach ($archivos['tmp_name'] as $key => $tmp_name) {
                if ($archivos['error'][$key] === UPLOAD_ERR_OK) {
                    $archivo_individual = [
                        'name' => $archivos['name'][$key],
                        'type' => $archivos['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $archivos['error'][$key],
                        'size' => $archivos['size'][$key]
                    ];
                    
                    $resultado = procesar_archivo_cqr($archivo_individual, 'imagen');
                    if (is_string($resultado)) {
                        $imagenes_existentes[] = json_decode($resultado, true);
                        $archivos_agregados++;
                    } elseif (isset($resultado['error'])) {
                        $errores[] = $archivos['name'][$key] . ': ' . $resultado['error'];
                    }
                }
            }
            
            $stmt = $pdo->prepare("UPDATE cotizaciones_quimicos_residuos SET imagenes_residuo = :valor WHERE id = :id");
            $stmt->execute([':valor' => json_encode($imagenes_existentes), ':id' => $cotizacion_id]);
        }
        
        // Registrar en historial si se agregaron archivos
        if ($archivos_agregados > 0) {
            $stmt = $pdo->prepare("SELECT nombre_completo, departamento FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $usuario_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            registrar_historial_cqr(
                $cotizacion_id,
                $cotizacion['folio'],
                $usuario_id,
                $usuario['nombre_completo'] ?? 'Usuario',
                $usuario['departamento'] ?? 'Ventas',
                'archivos_agregados',
                null,
                null,
                "Se agregaron {$archivos_agregados} archivo(s) a " . str_replace('_', ' ', $tipo_archivo)
            );
        }
        
        if (!empty($errores)) {
            return [
                'success' => $archivos_agregados > 0,
                'archivos_agregados' => $archivos_agregados,
                'errores' => $errores
            ];
        }
        
        return ['success' => true, 'archivos_agregados' => $archivos_agregados];
        
    } catch (Exception $e) {
        error_log("[CQR] Error al agregar archivos: " . $e->getMessage());
        return ['success' => false, 'error' => 'Error al agregar archivos'];
    }
}