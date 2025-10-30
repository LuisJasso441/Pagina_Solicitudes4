<?php
/**
 * Sistema de Comentarios para Documentos Colaborativos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notificaciones.php';

/**
 * Agregar comentario a un documento
 */
function agregar_comentario_documento($documento_id, $folio, $usuario_id, $nombre_usuario, $departamento, $texto, $tipo = 'normal') {
    try {
        $pdo = conectarDB();
        
        // Verificar que el documento existe y no está completado
        $stmt = $pdo->prepare("SELECT id, estado, usuario_creador_id, usuario_seguimiento_id FROM documentos_colaborativos WHERE id = ?");
        $stmt->execute([$documento_id]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$documento) {
            return ['success' => false, 'message' => 'Documento no encontrado'];
        }
        
        if ($documento['estado'] == 'completado') {
            return ['success' => false, 'message' => 'No se pueden agregar comentarios a documentos completados'];
        }
        
        // Validar tipo de mensaje
        $tipos_validos = ['normal', 'aclaracion', 'correccion', 'solicitud'];
        if (!in_array($tipo, $tipos_validos)) {
            $tipo = 'normal';
        }
        
        // Insertar comentario
        $stmt = $pdo->prepare("
            INSERT INTO documentos_comentarios (
                documento_id, folio_documento, usuario_autor_id, usuario_autor_nombre,
                departamento_autor, texto_comentario, tipo_mensaje, fecha_hora_publicacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $resultado = $stmt->execute([
            $documento_id,
            $folio,
            $usuario_id,
            $nombre_usuario,
            $departamento,
            trim($texto),
            $tipo
        ]);
        
        if ($resultado) {
            $comentario_id = $pdo->lastInsertId();
            
            // Registrar en historial del documento
            registrar_historial_documento(
                $documento_id,
                $folio,
                $usuario_id,
                $nombre_usuario,
                $departamento,
                'comentario_agregado',
                null,
                null,
                "Comentario tipo: {$tipo}"
            );
            
            // ⭐ Notificar a usuarios involucrados (excepto al autor del comentario)
            notificar_nuevo_comentario($documento, $usuario_id, $nombre_usuario, $departamento, $folio, $texto, $tipo);
            
            return [
                'success' => true,
                'message' => 'Comentario agregado exitosamente',
                'comentario_id' => $comentario_id
            ];
        }
        
        return ['success' => false, 'message' => 'Error al agregar comentario'];
        
    } catch (Exception $e) {
        error_log("Error al agregar comentario: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error del sistema: ' . $e->getMessage()];
    }
}

/**
 * Obtener comentarios de un documento
 */
function obtener_comentarios_documento($documento_id) {
    try {
        $pdo = conectarDB();
        
        $stmt = $pdo->prepare("
            SELECT * FROM documentos_comentarios 
            WHERE documento_id = ? 
            ORDER BY fecha_hora_publicacion ASC
        ");
        
        $stmt->execute([$documento_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error al obtener comentarios: " . $e->getMessage());
        return [];
    }
}

/**
 * Eliminar comentario (solo el autor o admin)
 */
function eliminar_comentario($comentario_id, $usuario_id, $es_admin = false) {
    try {
        $pdo = conectarDB();
        
        // Verificar que el comentario existe y pertenece al usuario
        $stmt = $pdo->prepare("SELECT usuario_autor_id, documento_id FROM documentos_comentarios WHERE id = ?");
        $stmt->execute([$comentario_id]);
        $comentario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$comentario) {
            return ['success' => false, 'message' => 'Comentario no encontrado'];
        }
        
        // Solo el autor o un admin pueden eliminar
        if ($comentario['usuario_autor_id'] != $usuario_id && !$es_admin) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar este comentario'];
        }
        
        // Eliminar
        $stmt = $pdo->prepare("DELETE FROM documentos_comentarios WHERE id = ?");
        $resultado = $stmt->execute([$comentario_id]);
        
        if ($resultado) {
            return ['success' => true, 'message' => 'Comentario eliminado'];
        }
        
        return ['success' => false, 'message' => 'Error al eliminar'];
        
    } catch (Exception $e) {
        error_log("Error al eliminar comentario: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error del sistema'];
    }
}

/**
 * Contar comentarios de un documento
 */
function contar_comentarios_documento($documento_id) {
    try {
        $pdo = conectarDB();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM documentos_comentarios WHERE documento_id = ?");
        $stmt->execute([$documento_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado['total'];
        
    } catch (Exception $e) {
        error_log("Error al contar comentarios: " . $e->getMessage());
        return 0;
    }
}

/**
 * ⭐ MEJORADO: Notificar nuevo comentario a usuarios involucrados Y departamentos colaborativos
 */
function notificar_nuevo_comentario($documento, $autor_id, $autor_nombre, $autor_dept, $folio, $texto, $tipo) {
    try {
        // Validar que $documento tenga 'id'
        if (!isset($documento['id'])) {
            error_log("Error: documento sin ID en notificar_nuevo_comentario");
            return;
        }
        
        $pdo = conectarDB();
        
        // ========================================
        // ESTRATEGIA 1: Usuarios específicos del documento
        // ========================================
        $usuarios_notificar = [];
        
        // Agregar creador del documento
        if (isset($documento['usuario_creador_id']) && $documento['usuario_creador_id'] && $documento['usuario_creador_id'] != $autor_id) {
            $usuarios_notificar[] = $documento['usuario_creador_id'];
        }
        
        // Agregar usuario de seguimiento (laboratorio)
        if (isset($documento['usuario_seguimiento_id']) && $documento['usuario_seguimiento_id'] && $documento['usuario_seguimiento_id'] != $autor_id) {
            $usuarios_notificar[] = $documento['usuario_seguimiento_id'];
        }
        
        // Obtener usuarios que han comentado (excepto el autor actual)
        $stmt = $pdo->prepare("
            SELECT DISTINCT usuario_autor_id 
            FROM documentos_comentarios 
            WHERE documento_id = ? AND usuario_autor_id != ?
        ");
        $stmt->execute([$documento['id'], $autor_id]);
        $otros_comentadores = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $usuarios_notificar = array_merge($usuarios_notificar, $otros_comentadores);
        
        // ========================================
        // ⭐ ESTRATEGIA 2: TODOS los usuarios de departamentos colaborativos
        // ========================================
        
        // Definir departamentos colaborativos (SSC)
        $departamentos_colaborativos = ['ventas', 'normatividad', 'laboratorio'];
        
        // Crear placeholders para IN clause
        $placeholders = str_repeat('?,', count($departamentos_colaborativos) - 1) . '?';
        
        // Obtener TODOS los usuarios de departamentos colaborativos (excepto el autor)
        $stmt = $pdo->prepare("
            SELECT id 
            FROM usuarios 
            WHERE departamento IN ($placeholders) 
            AND id != ? 
            AND activo = 1
        ");
        
        $params = array_merge($departamentos_colaborativos, [$autor_id]);
        $stmt->execute($params);
        $usuarios_colaborativos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Combinar ambas listas y eliminar duplicados
        $usuarios_notificar = array_unique(array_merge($usuarios_notificar, $usuarios_colaborativos));
        
        // ========================================
        // CREAR NOTIFICACIONES
        // ========================================
        
        // Crear preview del texto
        $texto_preview = mb_substr($texto, 0, 50) . (mb_strlen($texto) > 50 ? '...' : '');
        
        // Iconos según tipo de comentario
        $tipo_icon = [
            'normal' => '💬',
            'aclaracion' => '❓',
            'correccion' => '✏️',
            'solicitud' => '📋'
        ];
        
        $icono = $tipo_icon[$tipo] ?? '💬';
        
        // Nombres amigables de tipos de comentario
        $tipo_nombres = [
            'normal' => 'comentario',
            'aclaracion' => 'aclaración',
            'correccion' => 'corrección',
            'solicitud' => 'solicitud'
        ];
        
        $tipo_texto = $tipo_nombres[$tipo] ?? 'comentario';
        
        // Log para debug
        error_log("Notificando comentario {$tipo_texto} en {$folio} a " . count($usuarios_notificar) . " usuarios");
        
        // Crear notificación para cada usuario
        foreach ($usuarios_notificar as $usuario_id) {
            crear_notificacion(
                'documento_comentario',
                "{$icono} Nuevo {$tipo_texto} en {$folio}",
                "{$autor_nombre} ({$autor_dept}): {$texto_preview}",
                $usuario_id,
                [
                    'documento_id' => $documento['id'],
                    'folio' => $folio,
                    'autor' => $autor_nombre,
                    'departamento' => $autor_dept,
                    'tipo' => $tipo
                ]
            );
        }
        
    } catch (Exception $e) {
        error_log("Error al notificar comentario: " . $e->getMessage());
    }
}

/**
 * Obtener estadísticas de comentarios por documento
 */
function obtener_estadisticas_comentarios($documento_id) {
    try {
        $pdo = conectarDB();
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(DISTINCT usuario_autor_id) as usuarios_unicos,
                MAX(fecha_hora_publicacion) as ultimo_comentario,
                tipo_mensaje,
                COUNT(*) as cantidad
            FROM documentos_comentarios 
            WHERE documento_id = ?
            GROUP BY tipo_mensaje
        ");
        
        $stmt->execute([$documento_id]);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'stats' => $stats,
            'total' => array_sum(array_column($stats, 'cantidad'))
        ];
        
    } catch (Exception $e) {
        error_log("Error al obtener estadísticas: " . $e->getMessage());
        return ['stats' => [], 'total' => 0];
    }
}