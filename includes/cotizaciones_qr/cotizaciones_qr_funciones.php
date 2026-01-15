<?php
/**
 * Funciones para el módulo de Cotizaciones de Químicos y/o Residuos (CQR)
 * Ubicación: includes/cotizaciones_qr/cotizaciones_qr_funciones.php
 */

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
        'puede_crear' => $permisos['creador'] ?? false,
        'puede_editar' => $permisos['editor'] ?? false
    ];
}

/**
 * Obtener cotizaciones según ubicación (local/global) y rol del usuario
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
 * Crear nueva cotización
 */
function crear_cotizacion_qr($datos) {
    $pdo = conectarDB();
    
    // Verificar que el folio no exista
    $existe = obtener_cotizacion_qr_por_folio($datos['folio']);
    if ($existe) {
        return ['success' => false, 'error' => 'El folio ya existe en el sistema'];
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO cotizaciones_quimicos_residuos (
            folio, fecha_solicitud, nombre_amigable, nombre_tecnico,
            categoria, comentarios_ventas, ficha_tecnica, formato_descripcion,
            usuario_creador_id, departamento_creador, departamento_id,
            estado, ubicacion, fecha_creacion, fecha_enviada
        ) VALUES (
            :folio, :fecha_solicitud, :nombre_amigable, :nombre_tecnico,
            :categoria, :comentarios_ventas, :ficha_tecnica, :formato_descripcion,
            :usuario_creador_id, :departamento_creador, :departamento_id,
            'enviada', 'local', NOW(), NOW()
        )
    ");
    
    $resultado = $stmt->execute([
        ':folio' => $datos['folio'],
        ':fecha_solicitud' => $datos['fecha_solicitud'],
        ':nombre_amigable' => $datos['nombre_amigable'],
        ':nombre_tecnico' => $datos['nombre_tecnico'] ?? null,
        ':categoria' => $datos['categoria'] ?? null,
        ':comentarios_ventas' => $datos['comentarios_ventas'] ?? null,
        ':ficha_tecnica' => $datos['ficha_tecnica'] ?? null,
        ':formato_descripcion' => $datos['formato_descripcion'] ?? null,
        ':usuario_creador_id' => $datos['usuario_creador_id'],
        ':departamento_creador' => $datos['departamento_creador'],
        ':departamento_id' => $datos['departamento_id'] ?? null
    ]);
    
    if ($resultado) {
        $cotizacion_id = $pdo->lastInsertId();
        
        // Registrar en historial
        registrar_historial_cqr($cotizacion_id, $datos['folio'], $datos['usuario_creador_id'], 
            $datos['usuario_nombre'], $datos['departamento_creador'], 
            'creada', null, 'enviada', 'Cotización creada y enviada a Normatividad');
        
        return ['success' => true, 'id' => $cotizacion_id];
    }
    
    return ['success' => false, 'error' => 'Error al crear la cotización'];
}

/**
 * Actualizar cotización por Normatividad
 */
function actualizar_cotizacion_qr_normatividad($id, $datos) {
    $pdo = conectarDB();
    
    // Obtener cotización actual
    $cotizacion = obtener_cotizacion_qr_por_id($id);
    if (!$cotizacion) {
        return ['success' => false, 'error' => 'Cotización no encontrada'];
    }
    
    $estado_anterior = $cotizacion['estado'];
    $nuevo_estado = $datos['estado_normatividad'];
    
    // Determinar si se finaliza
    $ubicacion = ($nuevo_estado === 'finalizada') ? 'global' : 'local';
    $fecha_finalizada = ($nuevo_estado === 'finalizada') ? 'NOW()' : 'NULL';
    
    $sql = "
        UPDATE cotizaciones_quimicos_residuos SET
            resultados = :resultados,
            estado_normatividad = :estado_normatividad,
            estado = :estado,
            usuario_normatividad_id = :usuario_normatividad_id,
            ubicacion = :ubicacion,
            fecha_ultima_edicion = NOW()
    ";
    
    if ($nuevo_estado === 'en_revision' && $cotizacion['fecha_en_revision'] === null) {
        $sql .= ", fecha_en_revision = NOW()";
    }
    
    if ($nuevo_estado === 'finalizada') {
        $sql .= ", fecha_finalizada = NOW()";
    }
    
    $sql .= " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute([
        ':resultados' => $datos['resultados'],
        ':estado_normatividad' => $nuevo_estado,
        ':estado' => $nuevo_estado,
        ':usuario_normatividad_id' => $datos['usuario_normatividad_id'],
        ':ubicacion' => $ubicacion,
        ':id' => $id
    ]);
    
    if ($resultado) {
        // Registrar en historial
        $accion = ($nuevo_estado === 'finalizada') ? 'finalizada' : 'actualizada';
        registrar_historial_cqr($id, $cotizacion['folio'], $datos['usuario_normatividad_id'],
            $datos['usuario_nombre'], 'normatividad',
            $accion, $estado_anterior, $nuevo_estado, $datos['resultados']);
        
        return ['success' => true, 'estado_cambiado' => ($estado_anterior !== $nuevo_estado)];
    }
    
    return ['success' => false, 'error' => 'Error al actualizar la cotización'];
}

/**
 * Registrar en historial de cambios
 */
function registrar_historial_cqr($cotizacion_id, $folio, $usuario_id, $usuario_nombre, $departamento, $accion, $estado_anterior, $estado_nuevo, $detalles = null) {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        INSERT INTO cotizaciones_qr_historial (
            cotizacion_id, folio_cotizacion, usuario_id, usuario_nombre,
            departamento, accion, estado_anterior, estado_nuevo, detalles, fecha_hora
        ) VALUES (
            :cotizacion_id, :folio, :usuario_id, :usuario_nombre,
            :departamento, :accion, :estado_anterior, :estado_nuevo, :detalles, NOW()
        )
    ");
    
    return $stmt->execute([
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
}

/**
 * Obtener historial de una cotización
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

/**
 * Obtener estadísticas del módulo CQR
 */
function obtener_estadisticas_cqr($usuario_id = null, $departamento = null) {
    $pdo = conectarDB();
    
    $stats = [
        'total' => 0,
        'enviadas' => 0,
        'en_revision' => 0,
        'finalizadas' => 0
    ];
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'enviada' THEN 1 ELSE 0 END) as enviadas,
            SUM(CASE WHEN estado = 'en_revision' THEN 1 ELSE 0 END) as en_revision,
            SUM(CASE WHEN estado = 'finalizada' THEN 1 ELSE 0 END) as finalizadas
        FROM cotizaciones_quimicos_residuos
    ");
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = array_merge($stats, $result);
    }
    
    return $stats;
}

/**
 * Enviar notificación CQR
 */
function enviar_notificacion_cqr($tipo, $cotizacion, $usuario_destino_id, $datos_extra = []) {
    $pdo = conectarDB();
    
    $titulos = [
        'nueva_cotizacion' => '🧪 Nueva Cotización de Químicos',
        'cotizacion_en_revision' => '📋 Cotización en Revisión',
        'cotizacion_finalizada' => '✅ Cotización Finalizada',
        'cotizacion_actualizada' => '🔄 Cotización Actualizada'
    ];
    
    $mensajes = [
        'nueva_cotizacion' => "Nueva cotización {$cotizacion['folio']} de Ventas requiere revisión",
        'cotizacion_en_revision' => "Tu cotización {$cotizacion['folio']} está siendo revisada por Normatividad",
        'cotizacion_finalizada' => "Tu cotización {$cotizacion['folio']} ha sido finalizada",
        'cotizacion_actualizada' => "Tu cotización {$cotizacion['folio']} ha sido actualizada"
    ];
    
    $datos_json = json_encode([
        'cotizacion_id' => $cotizacion['id'],
        'folio' => $cotizacion['folio'],
        'url' => URL_BASE . "dashboard/cotizaciones_qr/ver_cotizacion_qr.php?id={$cotizacion['id']}"
    ]);
    
    $stmt = $pdo->prepare("
        INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, fecha_creacion)
        VALUES (:tipo, :titulo, :mensaje, :usuario_destino, :datos_json, NOW())
    ");
    
    return $stmt->execute([
        ':tipo' => $tipo,
        ':titulo' => $titulos[$tipo] ?? 'Notificación CQR',
        ':mensaje' => $mensajes[$tipo] ?? 'Actualización en cotización',
        ':usuario_destino' => $usuario_destino_id,
        ':datos_json' => $datos_json
    ]);
}

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

/**
 * Obtener etiqueta de estado con color
 */
function obtener_badge_estado_cqr($estado) {
    $badges = [
        'enviada' => '<span class="badge bg-primary">Enviada</span>',
        'en_revision' => '<span class="badge bg-warning text-dark">En Revisión</span>',
        'finalizada' => '<span class="badge bg-success">Finalizada</span>'
    ];
    
    return $badges[$estado] ?? '<span class="badge bg-secondary">Desconocido</span>';
}

/**
 * Obtener etiqueta de categoría
 */
function obtener_nombre_categoria_cqr($categoria) {
    $categorias = [
        'en_espera_1' => 'En espera',
        'en_espera_2' => 'En espera',
        'en_espera_3' => 'En espera',
        'en_espera_4' => 'En espera'
    ];
    
    return $categorias[$categoria] ?? 'Sin categoría';
}