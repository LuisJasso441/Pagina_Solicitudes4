<?php
/**
 * Sistema de Documentos Colaborativos
 * Funciones para gestión de documentos SSC
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/notificaciones.php';

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
    
    // Verificar si es el creador
    if ($documento['usuario_creador_id'] == $usuario_id) {
        $permisos['es_creador'] = true;
        
        // Solo puede editar Apartado 1 si no está completado
        if ($documento['estado'] != 'completado') {
            $permisos['apartado1'] = true;
        }
    }
    
    // Verificar si es de Laboratorio
    if (strtolower($departamento) == 'laboratorio') {
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
 */
function crear_documento_colaborativo($datos, $usuario_id, $departamento) {
    try {
        $pdo = conectarDB();
        
        // Verificar que el usuario tenga permiso (Normatividad o Ventas)
        $dept_lower = strtolower($departamento);
        if (!in_array($dept_lower, ['normatividad', 'ventas'])) {
            return [
                'success' => false,
                'message' => 'Solo Normatividad y Ventas pueden crear documentos colaborativos'
            ];
        }
        
        // Generar folio
        $folio = generar_folio_documento();
        
        // Validar servicio "otro"
        $servicio_otro = null;
        if ($datos['servicio_solicitado'] == 'otro' && !empty($datos['servicio_otro_especificar'])) {
            $servicio_otro = trim($datos['servicio_otro_especificar']);
        }
        
        // Insertar documento
        $stmt = $pdo->prepare("
            INSERT INTO documentos_colaborativos (
                folio, solicitado_por, fecha_solicitud, area_proceso_solicitante,
                servicio_solicitado, servicio_otro_especificar, prioridad, descripcion_servicio,
                usuario_creador_id, departamento_creador, estado, ubicacion,
                fecha_creacion, fecha_ultima_edicion
            ) VALUES (
                ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 'borrador', 'local', NOW(), NOW()
            )
        ");
        
        $resultado = $stmt->execute([
            $folio,
            trim($datos['solicitado_por']),
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
                $datos['solicitado_por'],
                $departamento,
                'creado',
                null,
                'borrador',
                'Documento creado'
            );
            
            // Notificar a Laboratorio
            notificar_laboratorio_nuevo_documento($documento_id, $folio, $departamento);
            
            return [
                'success' => true,
                'message' => 'Documento creado exitosamente',
                'folio' => $folio,
                'documento_id' => $documento_id
            ];
        }
        
        return ['success' => false, 'message' => 'Error al crear el documento'];
        
    } catch (Exception $e) {
        error_log("Error al crear documento colaborativo: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error del sistema: ' . $e->getMessage()];
    }
}

/**
 * Actualizar Apartado 1 (Normatividad/Ventas)
 */
function actualizar_apartado1($documento_id, $datos, $usuario_id) {
    try {
        $pdo = conectarDB();
        
        // Verificar permisos
        $documento = obtener_documento($documento_id);
        if (!$documento) {
            return ['success' => false, 'message' => 'Documento no encontrado'];
        }
        
        if ($documento['usuario_creador_id'] != $usuario_id) {
            return ['success' => false, 'message' => 'No tienes permiso para editar este documento'];
        }
        
        if ($documento['estado'] == 'completado') {
            return ['success' => false, 'message' => 'El documento ya está completado y no puede editarse'];
        }
        
        // Validar servicio "otro"
        $servicio_otro = null;
        if ($datos['servicio_solicitado'] == 'otro' && !empty($datos['servicio_otro_especificar'])) {
            $servicio_otro = trim($datos['servicio_otro_especificar']);
        }
        
        // Actualizar
        $stmt = $pdo->prepare("
            UPDATE documentos_colaborativos SET
                solicitado_por = ?,
                area_proceso_solicitante = ?,
                servicio_solicitado = ?,
                servicio_otro_especificar = ?,
                prioridad = ?,
                descripcion_servicio = ?,
                estado = 'enviado',
                fecha_ultima_edicion = NOW()
            WHERE id = ?
        ");
        
        $resultado = $stmt->execute([
            trim($datos['solicitado_por']),
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
                $documento['solicitado_por'],
                $documento['departamento_creador'],
                'editado_apartado1',
                $documento['estado'],
                'enviado',
                'Apartado 1 actualizado y enviado'
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
 */
function actualizar_apartado2($documento_id, $datos, $usuario_id, $nombre_usuario) {
    try {
        $pdo = conectarDB();
        
        // Verificar documento
        $documento = obtener_documento($documento_id);
        if (!$documento) {
            return ['success' => false, 'message' => 'Documento no encontrado'];
        }
        
        if ($documento['estado'] == 'completado') {
            return ['success' => false, 'message' => 'El documento ya está completado'];
        }
        
        // Actualizar
        $stmt = $pdo->prepare("
            UPDATE documentos_colaborativos SET
                recibe_solicitud = ?,
                fecha_hora_recibido = NOW(),
                resumen_resultados = ?,
                fecha_hora_entrega = ?,
                usuario_seguimiento_id = ?,
                estado = 'en_seguimiento',
                fecha_ultima_edicion = NOW()
            WHERE id = ?
        ");
        
        $resultado = $stmt->execute([
            $nombre_usuario,
            trim($datos['resumen_resultados']),
            $datos['fecha_hora_entrega'],
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
            
            // Notificar al creador
            notificar_creador_seguimiento($documento, $usuario_id);
            
            return ['success' => true, 'message' => 'Apartado 2 actualizado exitosamente'];
        }
        
        return ['success' => false, 'message' => 'Error al actualizar'];
        
    } catch (Exception $e) {
        error_log("Error al actualizar apartado 2: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error del sistema'];
    }
}

/**
 * Completar documento (Laboratorio)
 */
function completar_documento($documento_id, $usuario_id) {
    try {
        $pdo = conectarDB();
        
        $documento = obtener_documento($documento_id);
        if (!$documento) {
            return ['success' => false, 'message' => 'Documento no encontrado'];
        }
        
        // Verificar que el Apartado 2 esté completo
        if (empty($documento['resumen_resultados']) || empty($documento['fecha_hora_entrega'])) {
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
 * Listar documentos con filtros
 */
function listar_documentos($filtros = [], $usuario_id = null, $departamento = null) {
    try {
        $pdo = conectarDB();
        
        $where = [];
        $params = [];
        
        // Filtro por ubicación (local/global)
        if (isset($filtros['ubicacion'])) {
            $where[] = "ubicacion = ?";
            $params[] = $filtros['ubicacion'];
        }
        
        // Filtro por departamento creador
        if (isset($filtros['departamento'])) {
            $where[] = "departamento_creador = ?";
            $params[] = $filtros['departamento'];
        }
        
        // Filtro por estado
        if (isset($filtros['estado'])) {
            $where[] = "estado = ?";
            $params[] = $filtros['estado'];
        }
        
        // Filtro por rango de fechas
        if (isset($filtros['fecha_desde'])) {
            $where[] = "fecha_solicitud >= ?";
            $params[] = $filtros['fecha_desde'];
        }
        
        if (isset($filtros['fecha_hasta'])) {
            $where[] = "fecha_solicitud <= ?";
            $params[] = $filtros['fecha_hasta'];
        }
        
        // Filtro por usuario específico
        if (isset($filtros['usuario_id'])) {
            $where[] = "(usuario_creador_id = ? OR usuario_seguimiento_id = ?)";
            $params[] = $filtros['usuario_id'];
            $params[] = $filtros['usuario_id'];
        }
        
        $sql = "SELECT * FROM documentos_colaborativos";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
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
 * Registrar en historial
 */
function registrar_historial_documento($doc_id, $folio, $user_id, $user_nombre, $dept, $accion, $estado_ant, $estado_nuevo, $detalles) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            INSERT INTO documentos_historial 
            (documento_id, folio_documento, usuario_id, usuario_nombre, departamento, 
             accion, estado_anterior, estado_nuevo, detalles, fecha_hora)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $doc_id, $folio, $user_id, $user_nombre, $dept,
            $accion, $estado_ant, $estado_nuevo, $detalles
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