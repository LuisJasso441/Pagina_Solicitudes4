<?php
/**
 * Funciones para el módulo de Cotizaciones de Químicos y/o Residuos (CQR) v2.0
 * Ubicación: includes/cotizaciones_qr/cotizaciones_qr_funciones.php
 * 
 * Flujo:
 * 1. Ventas crea y envía solicitud → Normatividad recibe notificación
 * 2. Normatividad revisa y llena su formulario + Aceptada/Rechazada + Comentarios
 * 3. Ventas recibe notificación → FIN
 * 
 * Departamentos: Ventas (creador), Normatividad (editor), Laboratorio y Dirección (lectores)
 */

// =====================================================
// FUNCIONES DE PERMISOS
// =====================================================

/**
 * Verificar permisos del usuario para el módulo CQR
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
 * Verificar si el usuario pertenece a un departamento específico del módulo CQR
 */
function obtener_rol_cqr($departamento) {
    $depto = strtolower($departamento);
    
    if (strpos($depto, 'ventas') !== false) {
        return 'ventas';
    } elseif (strpos($depto, 'normatividad') !== false) {
        return 'normatividad';
    } elseif (strpos($depto, 'laboratorio') !== false) {
        return 'laboratorio';
    } elseif (strpos($depto, 'direccion') !== false || strpos($depto, 'dirección') !== false) {
        return 'direccion';
    }
    
    return 'otro';
}

// =====================================================
// FUNCIONES DE CONSULTA
// =====================================================

/**
 * Obtener cotizaciones según ubicación y filtros
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
    
    // Filtro por búsqueda
    if (!empty($filtros['busqueda'])) {
        $where[] = "(c.folio LIKE :busqueda OR c.nombre_amigable LIKE :busqueda OR c.nombre_tecnico LIKE :busqueda)";
        $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
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
 * Obtener una cotización por ID
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
 * Obtener una cotización por folio
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
 * Obtener estadísticas del módulo CQR
 */
function obtener_estadisticas_cqr($usuario_id = null, $departamento = null) {
    $pdo = conectarDB();
    
    $sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'enviada' THEN 1 ELSE 0 END) as enviadas,
            SUM(CASE WHEN estado = 'en_revision' THEN 1 ELSE 0 END) as en_revision,
            SUM(CASE WHEN estado = 'finalizada' THEN 1 ELSE 0 END) as finalizadas,
            SUM(CASE WHEN estado_normatividad = 'pendiente' OR estado_normatividad IS NULL THEN 1 ELSE 0 END) as pendientes_respuesta,
            SUM(CASE WHEN decision = 'aceptada' THEN 1 ELSE 0 END) as aceptadas,
            SUM(CASE WHEN decision = 'rechazada' THEN 1 ELSE 0 END) as rechazadas
        FROM cotizaciones_quimicos_residuos
        WHERE ubicacion = 'local'
    ";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// =====================================================
// FUNCIONES DE CREACIÓN Y ACTUALIZACIÓN
// =====================================================

/**
 * Crear nueva cotización (Ventas)
 */
function crear_cotizacion_qr($datos) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO cotizaciones_quimicos_residuos (
                folio, fecha_solicitud, nombre_amigable, nombre_tecnico, 
                categoria, comentarios_ventas,
                ficha_tecnica, formato_descripcion,
                usuario_creador_id, departamento_creador, departamento_id,
                estado, estado_normatividad, ubicacion, fecha_enviada
            ) VALUES (
                :folio, :fecha_solicitud, :nombre_amigable, :nombre_tecnico,
                :categoria, :comentarios_ventas,
                :ficha_tecnica, :formato_descripcion,
                :usuario_creador_id, :departamento_creador, :departamento_id,
                'enviada', 'pendiente', 'local', NOW()
            )
        ");
        
        $stmt->execute([
            ':folio' => $datos['folio'],
            ':fecha_solicitud' => $datos['fecha_solicitud'] ?? date('Y-m-d'),
            ':nombre_amigable' => $datos['nombre_amigable'],
            ':nombre_tecnico' => $datos['nombre_tecnico'] ?? null,
            ':categoria' => $datos['categoria'] ?? null,
            ':comentarios_ventas' => $datos['comentarios_ventas'] ?? null,
            ':ficha_tecnica' => $datos['ficha_tecnica'] ?? null,
            ':formato_descripcion' => $datos['formato_descripcion'] ?? null,
            ':usuario_creador_id' => $datos['usuario_creador_id'],
            ':departamento_creador' => $datos['departamento_creador'] ?? 'ventas',
            ':departamento_id' => $datos['departamento_id'] ?? null
        ]);
        
        $cotizacion_id = $pdo->lastInsertId();
        
        // Registrar en historial
        registrar_historial_cqr($cotizacion_id, $datos['usuario_creador_id'], 'creada', 'Solicitud creada y enviada a Normatividad');
        
        // Enviar notificaciones a usuarios de Normatividad
        $usuarios_normatividad = obtener_usuarios_normatividad();
        $creador_nombre = $datos['creador_nombre'] ?? 'Ventas';
        foreach ($usuarios_normatividad as $usuario) {
            crear_notificacion_cqr(
                $usuario['id'],
                'cqr_nueva',
                $cotizacion_id,
                "Nueva solicitud de cotización ({$datos['folio']}) enviada por {$creador_nombre}",
                $datos['folio']
            );
        }
        
        $pdo->commit();
        
        return ['success' => true, 'id' => $cotizacion_id];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Responder cotización (Normatividad)
 * Llena su formulario espejo + decisión + comentarios
 */
function responder_cotizacion_qr($cotizacion_id, $datos, $usuario_id) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE cotizaciones_quimicos_residuos SET
                norm_nombre_amigable = :norm_nombre_amigable,
                norm_nombre_tecnico = :norm_nombre_tecnico,
                norm_categoria = :norm_categoria,
                norm_ficha_tecnica = :norm_ficha_tecnica,
                norm_formato_descripcion = :norm_formato_descripcion,
                decision = :decision,
                comentarios_normatividad = :comentarios_normatividad,
                usuario_normatividad_id = :usuario_normatividad_id,
                estado = 'finalizada',
                estado_normatividad = 'completado',
                ubicacion = 'global',
                fecha_respuesta_normatividad = NOW(),
                fecha_finalizada = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':norm_nombre_amigable' => $datos['norm_nombre_amigable'] ?? null,
            ':norm_nombre_tecnico' => $datos['norm_nombre_tecnico'] ?? null,
            ':norm_categoria' => $datos['norm_categoria'] ?? null,
            ':norm_ficha_tecnica' => $datos['norm_ficha_tecnica'] ?? null,
            ':norm_formato_descripcion' => $datos['norm_formato_descripcion'] ?? null,
            ':decision' => $datos['decision'],
            ':comentarios_normatividad' => $datos['comentarios_normatividad'] ?? null,
            ':usuario_normatividad_id' => $usuario_id,
            ':id' => $cotizacion_id
        ]);
        
        // Determinar acción para historial
        $accion = ($datos['decision'] === 'aceptada') ? 'aceptada' : 'rechazada';
        $descripcion = "Cotización {$accion} por Normatividad";
        if (!empty($datos['comentarios_normatividad'])) {
            $descripcion .= ": " . substr($datos['comentarios_normatividad'], 0, 100);
        }
        
        registrar_historial_cqr($cotizacion_id, $usuario_id, $accion, $descripcion);
        
        // Obtener creador para notificarle
        $creador = obtener_creador_cotizacion_cqr($cotizacion_id);
        if ($creador) {
            // Obtener folio
            $stmt = $pdo->prepare("SELECT folio FROM cotizaciones_quimicos_residuos WHERE id = :id");
            $stmt->execute([':id' => $cotizacion_id]);
            $cot = $stmt->fetch(PDO::FETCH_ASSOC);
            $folio = $cot['folio'] ?? '';
            
            $estado_texto = ($datos['decision'] === 'aceptada') ? 'ACEPTADA ✅' : 'RECHAZADA ❌';
            crear_notificacion_cqr(
                $creador['id'],
                'cqr_respuesta',
                $cotizacion_id,
                "Tu cotización ({$folio}) ha sido {$estado_texto} por Normatividad",
                $folio
            );
        }
        
        $pdo->commit();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Marcar cotización como "en revisión" (cuando Normatividad abre la cotización)
 */
function marcar_en_revision_cqr($cotizacion_id, $usuario_id) {
    $pdo = conectarDB();
    
    // Solo marcar si está en estado 'enviada'
    $stmt = $pdo->prepare("
        UPDATE cotizaciones_quimicos_residuos 
        SET estado = 'en_revision', fecha_en_revision = NOW()
        WHERE id = :id AND estado = 'enviada'
    ");
    $stmt->execute([':id' => $cotizacion_id]);
    
    if ($stmt->rowCount() > 0) {
        registrar_historial_cqr($cotizacion_id, $usuario_id, 'en_revision', 'Normatividad comenzó la revisión');
    }
}

// =====================================================
// FUNCIONES DE COMENTARIOS GENERALES
// =====================================================

/**
 * Agregar comentario general a una cotización
 * Disponible para: Ventas, Normatividad, Laboratorio, Dirección
 */
function agregar_comentario_cqr($cotizacion_id, $usuario_id, $departamento, $comentario) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        INSERT INTO comentarios_cqr (cotizacion_id, usuario_id, departamento, comentario)
        VALUES (:cotizacion_id, :usuario_id, :departamento, :comentario)
    ");
    
    $stmt->execute([
        ':cotizacion_id' => $cotizacion_id,
        ':usuario_id' => $usuario_id,
        ':departamento' => $departamento,
        ':comentario' => $comentario
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Obtener comentarios de una cotización
 */
function obtener_comentarios_cqr($cotizacion_id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.nombre_completo as usuario_nombre
        FROM comentarios_cqr c
        INNER JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.cotizacion_id = :cotizacion_id
        ORDER BY c.fecha_creacion ASC
    ");
    $stmt->execute([':cotizacion_id' => $cotizacion_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Contar comentarios de una cotización
 */
function contar_comentarios_cqr($cotizacion_id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios_cqr WHERE cotizacion_id = :id");
    $stmt->execute([':id' => $cotizacion_id]);
    
    return $stmt->fetchColumn();
}

// =====================================================
// FUNCIONES DE HISTORIAL
// =====================================================

/**
 * Registrar acción en historial
 */
function registrar_historial_cqr($cotizacion_id, $usuario_id, $accion, $descripcion = '') {
    $pdo = conectarDB();
    
    try {
        // Obtener info del usuario y cotización
        $stmt = $pdo->prepare("SELECT nombre_completo, departamento FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $usuario_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT folio, estado FROM cotizaciones_quimicos_residuos WHERE id = :id");
        $stmt->execute([':id' => $cotizacion_id]);
        $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            INSERT INTO cotizaciones_qr_historial (
                cotizacion_id, folio_cotizacion, usuario_id, usuario_nombre, 
                departamento, accion, estado_nuevo, detalles
            ) VALUES (
                :cotizacion_id, :folio, :usuario_id, :usuario_nombre,
                :departamento, :accion, :estado_nuevo, :detalles
            )
        ");
        
        $stmt->execute([
            ':cotizacion_id' => $cotizacion_id,
            ':folio' => $cotizacion['folio'] ?? '',
            ':usuario_id' => $usuario_id,
            ':usuario_nombre' => $usuario['nombre_completo'] ?? 'Desconocido',
            ':departamento' => $usuario['departamento'] ?? 'desconocido',
            ':accion' => $accion,
            ':estado_nuevo' => $cotizacion['estado'] ?? null,
            ':detalles' => $descripcion
        ]);
    } catch (Exception $e) {
        // Si falla el historial, no interrumpir el proceso principal
        error_log("Error al registrar historial CQR: " . $e->getMessage());
    }
}

/**
 * Obtener historial de una cotización
 */
function obtener_historial_cqr($cotizacion_id) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT *
        FROM cotizaciones_qr_historial
        WHERE cotizacion_id = :cotizacion_id
        ORDER BY fecha_hora DESC
    ");
    $stmt->execute([':cotizacion_id' => $cotizacion_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =====================================================
// FUNCIONES DE NOTIFICACIONES
// =====================================================

/**
 * Obtener usuarios de Normatividad para notificaciones
 */
function obtener_usuarios_normatividad() {
    $pdo = conectarDB();
    
    $stmt = $pdo->query("
        SELECT u.id, u.nombre_completo, u.departamento
        FROM usuarios u
        INNER JOIN departamentos d ON u.departamento_id = d.id
        WHERE LOWER(d.codigo) = 'normatividad'
        AND u.activo = 1
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener el creador de una cotización para notificarle
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
 * Crear notificación para el módulo CQR
 */
function crear_notificacion_cqr($usuario_destino_id, $tipo, $cotizacion_id, $mensaje, $folio = '') {
    $pdo = conectarDB();
    
    try {
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
        
        // Determinar título según tipo
        $titulos = [
            'cqr_nueva' => '📋 Nueva Cotización QR',
            'cqr_respuesta' => '✅ Respuesta a Cotización QR',
            'cqr_comentario' => '💬 Nuevo Comentario en Cotización QR'
        ];
        $titulo = $titulos[$tipo] ?? '📋 Cotización QR';
        
        $stmt->execute([
            ':tipo' => $tipo,
            ':titulo' => $titulo,
            ':mensaje' => $mensaje,
            ':usuario_destino' => $usuario_destino_id,
            ':datos_json' => $datos
        ]);
        
        return true;
    } catch (Exception $e) {
        // Si falla la notificación, no interrumpir el proceso principal
        error_log("Error al crear notificación CQR: " . $e->getMessage());
        return false;
    }
}

// =====================================================
// FUNCIONES DE ARCHIVOS
// =====================================================

/**
 * Procesar archivo subido para CQR
 */
function procesar_archivo_cqr($archivo, $tipo) {
    if (!isset($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
        return null;
    }
    
    $extensiones_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'rtf', 'jpg', 'jpeg', 'png', 'gif'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $extensiones_permitidas)) {
        return ['error' => 'Tipo de archivo no permitido'];
    }
    
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($archivo['size'] > $max_size) {
        return ['error' => 'El archivo excede el tamaño máximo permitido (10MB)'];
    }
    
    // Directorio de destino (en raíz del proyecto como Imagenes_SSC y Imagenes_OSM)
    $directorio = __DIR__ . '/../../Imagenes_QR/';
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }
    
    // Nombre único
    $nombre_guardado = 'cqr_' . $tipo . '_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $ruta_completa = $directorio . $nombre_guardado;
    
    if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
        return json_encode([
            'nombre_original' => $archivo['name'],
            'nombre_guardado' => $nombre_guardado,
            'ruta' => 'Imagenes_QR/' . $nombre_guardado,
            'tipo' => $archivo['type'],
            'tamanio' => $archivo['size'],
            'extension' => $extension,
            'fecha_subida' => date('Y-m-d H:i:s')
        ]);
    }
    
    return ['error' => 'Error al guardar el archivo'];
}

// =====================================================
// FUNCIONES DE UTILIDAD / HELPERS
// =====================================================

/**
 * Generar folio automático para cotización
 */
function generar_folio_cqr($departamento = 'VTS') {
    $pdo = conectarDB();
    
    $prefijo = strtoupper(substr($departamento, 0, 3));
    $fecha = date('dmY');
    
    // Obtener el último número del día
    $stmt = $pdo->prepare("
        SELECT folio FROM cotizaciones_quimicos_residuos 
        WHERE folio LIKE :patron 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([':patron' => $prefijo . '-%' . $fecha]);
    $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ultimo) {
        // Extraer número y aumentar
        preg_match('/(\d+)-' . $fecha . '$/', $ultimo['folio'], $matches);
        $numero = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $numero = 1;
    }
    
    return $prefijo . '-' . str_pad($numero, 3, '0', STR_PAD_LEFT) . '-' . $fecha;
}

/**
 * Obtener etiqueta de estado con color
 */
function obtener_badge_estado_cqr($estado) {
    $badges = [
        'enviada' => '<span class="badge bg-primary">Enviada</span>',
        'en_revision' => '<span class="badge bg-warning text-dark">En Revisión</span>',
        'finalizada' => '<span class="badge bg-success">Finalizada</span>'
    ];
    
    return $badges[$estado] ?? '<span class="badge bg-secondary">' . ucfirst($estado) . '</span>';
}

/**
 * Obtener etiqueta de decisión con color
 */
function obtener_badge_decision_cqr($decision) {
    if ($decision === 'aceptada') {
        return '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Aceptada</span>';
    } elseif ($decision === 'rechazada') {
        return '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rechazada</span>';
    }
    return '<span class="badge bg-secondary">Pendiente</span>';
}

/**
 * Obtener etiqueta de estado de respuesta de Normatividad
 */
function obtener_badge_estado_normatividad($estado_normatividad) {
    if ($estado_normatividad === 'completado') {
        return '<span class="badge bg-success">Completado</span>';
    }
    return '<span class="badge bg-warning text-dark">Pendiente</span>';
}

/**
 * Obtener nombre legible de categoría
 */
function obtener_nombre_categoria_cqr($categoria) {
    $categorias = [
        'en_espera_1' => 'Categoría 1',
        'en_espera_2' => 'Categoría 2',
        'en_espera_3' => 'Categoría 3',
        'en_espera_4' => 'Categoría 4'
    ];
    
    return $categorias[$categoria] ?? $categoria;
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
        return '#6f42c1'; // Púrpura
    } elseif (strpos($depto, 'direccion') !== false || strpos($depto, 'dirección') !== false) {
        return '#dc3545'; // Rojo
    }
    
    return '#6c757d'; // Gris
}

/**
 * Verificar si Normatividad ya respondió
 */
function normatividad_respondio_cqr($cotizacion) {
    return !empty($cotizacion['decision']) && $cotizacion['estado_normatividad'] === 'completado';
}