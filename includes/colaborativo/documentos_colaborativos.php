<?php
/**
 * Sistema de Documentos Colaborativos
 * Funciones para gestión de documentos SSC
 * VERSIÓN ACTUALIZADA - Incluye: fecha_entrega + hora_entrega en Apartado 2
 * ✅ CORREGIDO - Filtro de cliente implementado en listar_documentos()
 * ⭐ ACTUALIZADO - Permisos basados en permisos_ssc (lector, creador, editor)
 * ✅ CORREGIDO - Eliminada doble combinación de archivos en actualizar_apartado2()
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../notificaciones.php';

/**
 * ⭐ NUEVA FUNCIÓN - Obtener permisos SSC del usuario
 * @param int $usuario_id
 * @return array
 */
function obtener_permisos_ssc_usuario($usuario_id) {
    static $cache = [];
    
    if (isset($cache[$usuario_id])) {
        return $cache[$usuario_id];
    }
    
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT lector, creador, editor FROM permisos_ssc WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $usuario_id]);
        $permisos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si no tiene registro, por defecto tiene permiso de lector
        if (!$permisos) {
            $permisos = ['lector' => 1, 'creador' => 0, 'editor' => 0];
        }
        
        $cache[$usuario_id] = $permisos;
        return $permisos;
    } catch (Exception $e) {
        error_log("Error obteniendo permisos SSC: " . $e->getMessage());
        return ['lector' => 1, 'creador' => 0, 'editor' => 0];
    }
}

/**
 * Generar folio automático
 * Formato: SSC-DD/MM/YYYY-NNN
 */
function generar_folio_documento() {
    try {
        $pdo = conectarDB();
        
        // Obtener fecha actual
        $fecha_actual = date('d/m/Y');
        $prefijo = "SSC-{$fecha_actual}-";
        
        // Buscar el último folio de hoy
        $stmt = $pdo->prepare("
            SELECT folio FROM documentos_colaborativos 
            WHERE folio LIKE ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute(["{$prefijo}%"]);
        $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ultimo) {
            // Extraer el número y incrementar
            preg_match('/(\d+)$/', $ultimo['folio'], $matches);
            $numero = intval($matches[1]) + 1;
        } else {
            $numero = 1;
        }
        
        // Formato: 001, 002, etc.
        $numero_formateado = str_pad($numero, 3, '0', STR_PAD_LEFT);
        
        return $prefijo . $numero_formateado;
        
    } catch (Exception $e) {
        error_log("Error al generar folio: " . $e->getMessage());
        return "SSC-" . date('d/m/Y') . "-ERROR";
    }
}

/**
 * Verificar permisos de edición por apartado
 * ⭐ ACTUALIZADO - Usa tabla permisos_ssc para validar acceso
 * 
 * LÓGICA:
 * - Apartado 1: Usuarios del mismo departamento (Normatividad/Ventas) que creó el documento
 *               pueden editar SI tienen permiso de Editor en permisos_ssc
 * - Apartado 2: Solo Laboratorio puede editar
 * 
 * @param int $usuario_id ID del usuario actual
 * @param string $departamento Departamento del usuario
 * @param array $documento Datos del documento
 * @return array ['apartado1' => bool, 'apartado2' => bool]
 */
function verificar_permisos_edicion($usuario_id, $departamento, $documento) {
    $permisos = [
        'apartado1' => false,
        'apartado2' => false,
        'puede_comentar' => false,
        'es_creador' => false,
        'es_seguimiento' => false
    ];
    
    // Obtener permisos de la tabla permisos_ssc
    $permisos_ssc = obtener_permisos_ssc_usuario($usuario_id);
    $tiene_permiso_editor = ($permisos_ssc['editor'] == 1);
    
    // Normalizar departamentos para comparación
    $dept_usuario = strtolower(trim($departamento));
    $dept_documento = strtolower(trim($documento['departamento_creador']));
    
    // Verificar si es el creador
    if ($documento['usuario_creador_id'] == $usuario_id) {
        $permisos['es_creador'] = true;
    }
    
    // ========================================
    // PERMISO PARA EDITAR APARTADO 1
    // ========================================
    // Solo puede editar Apartado 1 si:
    // 1. El documento no está completado
    // 2. Es del mismo departamento que creó el documento (Normatividad o Ventas)
    // 3. Tiene permiso de EDITOR en permisos_ssc
    if ($documento['estado'] != 'completado') {
        // Departamentos que pueden editar Apartado 1
        $deptos_apartado1 = ['normatividad', 'ventas', 'direccion', 'dirección'];
        
        if (in_array($dept_usuario, $deptos_apartado1) && $dept_usuario == $dept_documento) {
            // Es del mismo departamento que creó el documento
            if ($tiene_permiso_editor) {
                // Tiene permiso de editor
                $permisos['apartado1'] = true;
            }
        }
    }
    
    // ========================================
    // PERMISO PARA EDITAR APARTADO 2 (Laboratorio)
    // ========================================
    if ($dept_usuario == 'laboratorio') {
        $permisos['apartado2'] = true;
        
        // Si ya está asignado a este usuario específico
        if ($documento['usuario_seguimiento_id'] == $usuario_id) {
            $permisos['es_seguimiento'] = true;
        }
    }
    
    // Todos pueden comentar excepto en documentos completados
    if ($documento['estado'] != 'completado') {
        $permisos['puede_comentar'] = true;
    }
    
    return $permisos;
}

/**
 * Crear nuevo documento colaborativo
 * ⭐ CORREGIDO - Incluye nombre_cliente y revision_productos
 */
function crear_documento_colaborativo($datos, $usuario_id, $departamento) {
    try {
        $pdo = conectarDB();
        
        // Verificar que el usuario tenga permiso (Normatividad, Ventas o Dirección)
        $dept_lower = strtolower($departamento);
        if (!in_array($dept_lower, ['normatividad', 'ventas', 'direccion', 'dirección'])) {
            return [
                'success' => false,
                'message' => 'Solo Normatividad, Ventas y Dirección pueden crear documentos colaborativos'
            ];
        }
        
        // Generar folio
        $folio = generar_folio_documento();
        
        // Validar servicio "otro"
        $servicio_otro = null;
        if ($datos['servicio_solicitado'] == 'otro' && !empty($datos['servicio_otro_especificar'])) {
            $servicio_otro = trim($datos['servicio_otro_especificar']);
        }
        
        // Validar servicios válidos (incluyendo revision_productos)
        $servicios_validos = ['tratamiento_agua', 'revision_productos', 'calibracion_equipos', 'otro'];
        if (!in_array($datos['servicio_solicitado'], $servicios_validos)) {
            return ['success' => false, 'message' => 'Servicio no válido'];
        }
        
        // Insertar documento (⭐ INCLUYE nombre_cliente)
        $stmt = $pdo->prepare("
            INSERT INTO documentos_colaborativos (
                folio, solicitado_por, nombre_cliente, fecha_solicitud, area_proceso_solicitante,
                servicio_solicitado, servicio_otro_especificar, prioridad, descripcion_servicio,
                usuario_creador_id, departamento_creador, estado, ubicacion,
                fecha_creacion, fecha_ultima_edicion
            ) VALUES (
                ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 'enviado', 'local', NOW(), NOW()
            )
        ");
        
        $resultado = $stmt->execute([
            $folio,
            trim($datos['solicitado_por']),
            trim($datos['nombre_cliente']),  // ⭐ NUEVO CAMPO
            trim($datos['area_proceso_solicitante']),
            $datos['servicio_solicitado'],
            $servicio_otro,
            $datos['prioridad'],
            trim($datos['descripcion_servicio']),
            $usuario_id,
            $departamento
        ]);
        
        if ($resultado) {
            $documento_id = $pdo->lastInsertId();
            
            // Registrar en historial
            registrar_historial_documento(
                $documento_id,
                $folio,
                $usuario_id,
                $_SESSION['nombre_completo'] ?? 'Usuario',
                $departamento,
                'creado',
                null,
                'enviado',
                'Documento creado y enviado a Laboratorio'
            );
            
            // ⭐ NOTIFICAR A LABORATORIO
            notificar_laboratorio_nuevo_documento($documento_id, $folio, $departamento);
            
            return [
                'success' => true,
                'message' => 'Documento creado y enviado a Laboratorio',
                'documento_id' => $documento_id,
                'folio' => $folio
            ];
        }
        
        return ['success' => false, 'message' => 'Error al crear documento'];
        
    } catch (Exception $e) {
        error_log("Error al crear documento: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error del sistema'];
    }
}

/**
 * Obtener documento por ID
 */
function obtener_documento($documento_id) {
    try {
        $pdo = conectarDB();
        
        $stmt = $pdo->prepare("SELECT * FROM documentos_colaborativos WHERE id = ?");
        $stmt->execute([$documento_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error al obtener documento: " . $e->getMessage());
        return null;
    }
}

/**
 * Listar documentos según filtros
 * ⭐ ACTUALIZADO - Incluye filtro por cliente
 */
function listar_documentos($filtros = [], $usuario_id = null, $departamento = null) {
    try {
        $pdo = conectarDB();
        
        $sql = "SELECT * FROM documentos_colaborativos WHERE 1=1";
        $params = [];
        
        // Filtro por ubicación
        if (!empty($filtros['ubicacion'])) {
            $sql .= " AND ubicacion = ?";
            $params[] = $filtros['ubicacion'];
        }
        
        // Filtro por estado
        if (!empty($filtros['estado'])) {
            $sql .= " AND estado = ?";
            $params[] = $filtros['estado'];
        }
        
        // ⭐ Filtro por cliente
        if (!empty($filtros['cliente'])) {
            $sql .= " AND nombre_cliente = ?";
            $params[] = $filtros['cliente'];
        }
        
        // Filtro por departamento
        if (!empty($filtros['departamento'])) {
            $sql .= " AND departamento_creador = ?";
            $params[] = $filtros['departamento'];
        }
        
        // Filtro por fecha desde
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND fecha_creacion >= ?";
            $params[] = $filtros['fecha_desde'];
        }
        
        // Filtro por fecha hasta
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND fecha_creacion <= ?";
            $params[] = $filtros['fecha_hasta'];
        }
        
        $sql .= " ORDER BY fecha_creacion DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error al listar documentos: " . $e->getMessage());
        return [];
    }
}

/**
 * Enviar documento a Laboratorio
 */
function enviar_documento_laboratorio($documento_id, $usuario_id, $nombre_usuario) {
    try {
        $pdo = conectarDB();
        
        $documento = obtener_documento($documento_id);
        if (!$documento) {
            return ['success' => false, 'message' => 'Documento no encontrado'];
        }
        
        if ($documento['estado'] != 'borrador') {
            return ['success' => false, 'message' => 'Solo se pueden enviar documentos en borrador'];
        }
        
        $stmt = $pdo->prepare("
            UPDATE documentos_colaborativos SET
                estado = 'enviado',
                fecha_ultima_edicion = NOW()
            WHERE id = ?
        ");
        
        $resultado = $stmt->execute([$documento_id]);
        
        if ($resultado) {
            registrar_historial_documento(
                $documento_id,
                $documento['folio'],
                $usuario_id,
                $nombre_usuario,
                $documento['departamento_creador'],
                'enviado',
                'borrador',
                'enviado',
                'Documento enviado a Laboratorio'
            );
            
            // Notificar a Laboratorio
            notificar_laboratorio_nuevo_documento($documento_id, $documento['folio'], $documento['departamento_creador']);
            
            return ['success' => true, 'message' => 'Documento enviado a Laboratorio'];
        }
        
        return ['success' => false, 'message' => 'Error al enviar documento'];
        
    } catch (Exception $e) {
        error_log("Error al enviar documento: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error del sistema'];
    }
}

/**
 * Actualizar Apartado 1 (Normatividad/Ventas)
 */
function actualizar_apartado1($documento_id, $datos, $usuario_id, $nombre_usuario) {
    try {
        $pdo = conectarDB();
        
        $documento = obtener_documento($documento_id);
        if (!$documento) {
            return ['success' => false, 'message' => 'Documento no encontrado'];
        }
        
        if ($documento['estado'] == 'completado') {
            return ['success' => false, 'message' => 'El documento ya está completado'];
        }
        
        // Validar servicio "otro"
        $servicio_otro = null;
        if ($datos['servicio_solicitado'] == 'otro' && !empty($datos['servicio_otro_especificar'])) {
            $servicio_otro = trim($datos['servicio_otro_especificar']);
        }
        
        $stmt = $pdo->prepare("
            UPDATE documentos_colaborativos SET
                solicitado_por = ?,
                nombre_cliente = ?,
                area_proceso_solicitante = ?,
                servicio_solicitado = ?,
                servicio_otro_especificar = ?,
                prioridad = ?,
                descripcion_servicio = ?,
                fecha_ultima_edicion = NOW()
            WHERE id = ?
        ");
        
        $resultado = $stmt->execute([
            trim($datos['solicitado_por']),
            trim($datos['nombre_cliente']),
            trim($datos['area_proceso_solicitante']),
            $datos['servicio_solicitado'],
            $servicio_otro,
            $datos['prioridad'],
            trim($datos['descripcion_servicio']),
            $documento_id
        ]);
        
        if ($resultado) {
            registrar_historial_documento(
                $documento_id,
                $documento['folio'],
                $usuario_id,
                $nombre_usuario,
                $documento['departamento_creador'],
                'editado_apartado1',
                $documento['estado'],
                $documento['estado'],
                'Apartado 1 actualizado'
            );
            
            return ['success' => true, 'message' => 'Apartado 1 actualizado exitosamente'];
        }
        
        return ['success' => false, 'message' => 'Error al actualizar'];
        
    } catch (Exception $e) {
        error_log("Error al actualizar apartado 1: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error del sistema'];
    }
}

/**
 * Actualizar Apartado 2 (Laboratorio)
 * ⭐ ACTUALIZADO - fecha_recibido + hora_recibido + archivos + fecha_entrega + hora_entrega
 * ✅ CORREGIDO - Ya no duplica archivos (usa directamente el JSON de procesar_apartado2.php)
 */
function actualizar_apartado2($documento_id, $datos, $usuario_id, $nombre_usuario) {
    try {
        $pdo = conectarDB();
        
        $documento = obtener_documento($documento_id);
        if (!$documento) {
            return ['success' => false, 'message' => 'Documento no encontrado'];
        }
        
        if ($documento['estado'] == 'completado') {
            return ['success' => false, 'message' => 'El documento ya está completado'];
        }
        
        // ✅ CORREGIDO - Usar directamente el JSON que viene de procesar_apartado2.php
        // (ya viene combinado con los existentes, no volver a combinar para evitar duplicados)
        $archivos_json = $datos['archivos_apartado2'] ?? null;
        
        // ⭐ ACTUALIZADO - Incluye fecha_entrega y hora_entrega
        $stmt = $pdo->prepare("
            UPDATE documentos_colaborativos SET
                recibe_solicitud = ?,
                resumen_resultados = ?,
                fecha_recibido = ?,
                hora_recibido = ?,
                archivos_apartado2 = ?,
                fecha_entrega = ?,
                hora_entrega = ?,
                estado = 'en_seguimiento',
                usuario_seguimiento_id = ?,
                fecha_ultima_edicion = NOW()
            WHERE id = ?
        ");
        
        $resultado = $stmt->execute([
            trim($datos['recibe_solicitud']),
            trim($datos['resumen_resultados']),
            $datos['fecha_recibido'],
            $datos['hora_recibido'],
            $archivos_json,
            $datos['fecha_entrega'] ?? null,    // ⭐ NUEVO CAMPO (opcional)
            $datos['hora_entrega'] ?? null,     // ⭐ NUEVO CAMPO (opcional)
            $usuario_id,
            $documento_id
        ]);
        
        if ($resultado) {
            registrar_historial_documento(
                $documento_id,
                $documento['folio'],
                $usuario_id,
                $nombre_usuario,
                'laboratorio',
                'editado_apartado2',
                $documento['estado'],
                'en_seguimiento',
                'Apartado 2 actualizado'
            );
            
            // ⚠️ NOTIFICACIÓN SSE ELIMINADA (por solicitud del usuario)
            // notificar_creador_seguimiento($documento, $usuario_id);
            
            return [
                'success' => true, 
                'message' => 'Apartado 2 actualizado exitosamente'
            ];
        }
        
        return ['success' => false, 'message' => 'Error al actualizar'];
        
    } catch (Exception $e) {
        error_log("Error al actualizar apartado 2: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error del sistema: ' . $e->getMessage()];
    }
}

/**
 * Completar documento (Laboratorio)
 * ⭐ CORREGIDO - Valida fecha_recibido y hora_recibido
 */
function completar_documento($documento_id, $usuario_id) {
    try {
        $pdo = conectarDB();
        
        $documento = obtener_documento($documento_id);
        if (!$documento) {
            return ['success' => false, 'message' => 'Documento no encontrado'];
        }
        
        // Verificar que el Apartado 2 esté completo (⭐ CAMPOS ACTUALIZADOS)
        if (empty($documento['resumen_resultados']) || 
            empty($documento['fecha_recibido']) || 
            empty($documento['hora_recibido'])) {
            return ['success' => false, 'message' => 'Debe completar el Apartado 2 antes de finalizar'];
        }
        
        // Marcar como completado y mover a global
        $stmt = $pdo->prepare("
            UPDATE documentos_colaborativos SET
                estado = 'completado',
                ubicacion = 'global',
                fecha_completado = NOW(),
                fecha_ultima_edicion = NOW()
            WHERE id = ?
        ");
        
        $resultado = $stmt->execute([$documento_id]);
        
        if ($resultado) {
            registrar_historial_documento(
                $documento_id,
                $documento['folio'],
                $usuario_id,
                $documento['recibe_solicitud'],
                'laboratorio',
                'completado',
                'en_seguimiento',
                'completado',
                'Documento completado y movido a base global'
            );
            
            // Notificar a todos
            notificar_documento_completado($documento);
            
            return ['success' => true, 'message' => 'Documento completado y movido a la base global'];
        }
        
        return ['success' => false, 'message' => 'Error al completar'];
        
    } catch (Exception $e) {
        error_log("Error al completar documento: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error del sistema'];
    }
}

/**
 * Registrar historial de documento
 */
function registrar_historial_documento($documento_id, $folio, $usuario_id, $usuario_nombre, $departamento, $accion, $estado_anterior, $estado_nuevo, $detalles = null) {
    try {
        $pdo = conectarDB();
        
        $stmt = $pdo->prepare("
            INSERT INTO documentos_historial (
                documento_id, folio, usuario_id, usuario_nombre, departamento,
                accion, estado_anterior, estado_nuevo, detalles, fecha_hora
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $documento_id,
            $folio,
            $usuario_id,
            $usuario_nombre,
            $departamento,
            $accion,
            $estado_anterior,
            $estado_nuevo,
            $detalles
        ]);
        
    } catch (Exception $e) {
        error_log("Error al registrar historial: " . $e->getMessage());
        return false;
    }
}

/**
 * Notificaciones SSE
 */
function notificar_laboratorio_nuevo_documento($doc_id, $folio, $dept_creador) {
    try {
        $pdo = conectarDB();
        
        // Obtener todos los usuarios de Laboratorio
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(departamento) = 'laboratorio'");
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($usuarios as $usuario_id) {
            crear_notificacion(
                'documento_nuevo',
                'Nuevo documento colaborativo',
                "Nuevo documento {$folio} creado por {$dept_creador}",
                $usuario_id,
                ['documento_id' => $doc_id, 'folio' => $folio]
            );
        }
    } catch (Exception $e) {
        error_log("Error al notificar laboratorio: " . $e->getMessage());
    }
}

function notificar_creador_seguimiento($documento, $usuario_lab_id) {
    crear_notificacion(
        'documento_seguimiento',
        'Documento en seguimiento',
        "El documento {$documento['folio']} está siendo atendido por Laboratorio",
        $documento['usuario_creador_id'],
        ['documento_id' => $documento['id'], 'folio' => $documento['folio']]
    );
}

function notificar_documento_completado($documento) {
    try {
        $pdo = conectarDB();
        
        // Notificar a creador y a laboratorio
        $usuarios = [$documento['usuario_creador_id']];
        if ($documento['usuario_seguimiento_id']) {
            $usuarios[] = $documento['usuario_seguimiento_id'];
        }
        
        foreach ($usuarios as $usuario_id) {
            crear_notificacion(
                'documento_completado',
                'Documento completado',
                "El documento {$documento['folio']} ha sido completado",
                $usuario_id,
                ['documento_id' => $documento['id'], 'folio' => $documento['folio']]
            );
        }
    } catch (Exception $e) {
        error_log("Error al notificar completado: " . $e->getMessage());
    }
}