<?php
/**
 * Sistema de notificaciones en tiempo real
 * Maneja la creación y envío de notificaciones
 */

/**
 * Crear una notificación
 * 
 * @param string $tipo Tipo de notificación (nueva_solicitud, cambio_estado, etc)
 * @param string $titulo Título de la notificación
 * @param string $mensaje Mensaje descriptivo
 * @param int $usuario_destino ID del usuario que recibirá la notificación
 * @param array $datos_adicionales Datos extra en formato array
 * @return bool
 */
function crear_notificacion($tipo, $titulo, $mensaje, $usuario_destino, $datos_adicionales = []) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarDB();
        
        $stmt = $pdo->prepare("
            INSERT INTO notificaciones 
            (tipo, titulo, mensaje, usuario_destino, datos_json, leida, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        
        $datos_json = !empty($datos_adicionales) ? json_encode($datos_adicionales) : null;
        
        return $stmt->execute([
            $tipo,
            $titulo,
            $mensaje,
            $usuario_destino,
            $datos_json
        ]);
        
    } catch (Exception $e) {
        error_log("Error al crear notificación: " . $e->getMessage());
        return false;
    }
}

/**
 * Notificar a todos los usuarios de TI sobre una nueva solicitud
 * 
 * @param string $folio Folio de la solicitud
 * @param string $solicitante Nombre del solicitante
 * @param string $departamento Departamento del solicitante
 * @param string $prioridad Prioridad de la solicitud
 */
function notificar_nueva_solicitud($folio, $solicitante, $departamento, $prioridad) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarDB();
        
        // Obtener todos los usuarios de TI
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE departamento = 'sistemas' AND activo = 1");
        $stmt->execute();
        $usuarios_ti = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Crear notificación para cada usuario de TI
        foreach ($usuarios_ti as $usuario_ti_id) {
            crear_notificacion(
                'nueva_solicitud',
                '🔔 Nueva Solicitud',
                "Nueva solicitud de $solicitante ($departamento) - Prioridad: " . ucfirst($prioridad),
                $usuario_ti_id,
                [
                    'folio' => $folio,
                    'solicitante' => $solicitante,
                    'departamento' => $departamento,
                    'prioridad' => $prioridad,
                    'url' => URL_BASE . 'solicitudes/ver.php?folio=' . urlencode($folio)
                ]
            );
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error al notificar nueva solicitud: " . $e->getMessage());
        return false;
    }
}

/**
 * Notificar al solicitante sobre cambio de estado
 * 
 * @param string $folio Folio de la solicitud
 * @param int $usuario_solicitante ID del usuario que creó la solicitud
 * @param string $nuevo_estado Nuevo estado de la solicitud
 * @param string $comentario Comentario del técnico (opcional)
 */
function notificar_cambio_estado($folio, $usuario_solicitante, $nuevo_estado, $comentario = '') {
    $estados_texto = [
        'pendiente' => 'Pendiente',
        'en_proceso' => 'En Proceso',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada'
    ];
    
    $estado_texto = $estados_texto[$nuevo_estado] ?? $nuevo_estado;
    
    $mensaje = "Tu solicitud $folio cambió a: $estado_texto";
    if (!empty($comentario)) {
        $mensaje .= " - " . substr($comentario, 0, 100);
    }
    
    crear_notificacion(
        'cambio_estado',
        '📝 Actualización de Solicitud',
        $mensaje,
        $usuario_solicitante,
        [
            'folio' => $folio,
            'estado' => $nuevo_estado,
            'url' => URL_BASE . 'solicitudes/ver.php?folio=' . urlencode($folio)
        ]
    );
}

/**
 * Obtener notificaciones no leídas de un usuario
 * 
 * @param int $usuario_id ID del usuario
 * @param int $limite Cantidad máxima de notificaciones a retornar
 * @return array
 */
function obtener_notificaciones_pendientes($usuario_id, $limite = 10) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarDB();
        
        $stmt = $pdo->prepare("
            SELECT * FROM notificaciones 
            WHERE usuario_destino = ? AND leida = 0
            ORDER BY fecha_creacion DESC
            LIMIT ?
        ");
        
        $stmt->execute([$usuario_id, $limite]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error al obtener notificaciones: " . $e->getMessage());
        return [];
    }
}

/**
 * Marcar notificación como leída
 * 
 * @param int $notificacion_id ID de la notificación
 * @return bool
 */
function marcar_notificacion_leida($notificacion_id) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarDB();
        
        $stmt = $pdo->prepare("
            UPDATE notificaciones 
            SET leida = 1, fecha_leida = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$notificacion_id]);
        
    } catch (Exception $e) {
        error_log("Error al marcar notificación: " . $e->getMessage());
        return false;
    }
}

/**
 * Marcar todas las notificaciones de un usuario como leídas
 * 
 * @param int $usuario_id ID del usuario
 * @return bool
 */
function marcar_todas_leidas($usuario_id) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarDB();
        
        $stmt = $pdo->prepare("
            UPDATE notificaciones 
            SET leida = 1, fecha_leida = NOW()
            WHERE usuario_destino = ? AND leida = 0
        ");
        
        return $stmt->execute([$usuario_id]);
        
    } catch (Exception $e) {
        error_log("Error al marcar todas como leídas: " . $e->getMessage());
        return false;
    }
}
?>