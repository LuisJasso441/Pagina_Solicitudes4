<?php
/**
 * Procesar Solicitudes de Mantenimiento
 * dashboard/sistemas/ti_sistemas/procesar_mantenimiento.php
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
    exit;
}

// Conexión a BD
$pdo = conectarDB();

// Verificar si es usuario de Sistemas
$departamento_usuario = strtolower($_SESSION['departamento'] ?? '');
$es_sistemas = ($departamento_usuario === 'sistemas');

// Obtener acción
$accion = $_POST['accion'] ?? '';

switch ($accion) {
    case 'crear':
        crearSolicitud($pdo);
        break;
    case 'cambiar_estado':
        if (!$es_sistemas) {
            establecer_alerta('error', 'No tiene permisos para realizar esta acción.');
            header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
            exit;
        }
        cambiarEstado($pdo);
        break;
    default:
        establecer_alerta('error', 'Acción no válida.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
        exit;
}

/**
 * Generar folio único para solicitud
 */
function generarFolio($pdo) {
    $fecha = date('Ymd');
    $prefijo = "MANT-{$fecha}-";
    
    // Obtener el último folio del día
    $sql = "SELECT folio FROM solicitudes_mantenimiento_ti 
            WHERE folio LIKE ? ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$prefijo . '%']);
    $ultimo = $stmt->fetchColumn();
    
    if ($ultimo) {
        $numero = intval(substr($ultimo, -4)) + 1;
    } else {
        $numero = 1;
    }
    
    return $prefijo . str_pad($numero, 4, '0', STR_PAD_LEFT);
}

/**
 * Crear nueva solicitud de mantenimiento
 */
function crearSolicitud($pdo) {
    global $_SESSION;
    
    $errores = [];
    
    // Obtener y sanitizar datos
    $tipo_equipo = trim($_POST['tipo_equipo'] ?? '');
    $descripcion_problema = trim($_POST['descripcion_problema'] ?? '');
    $tipo_mantenimiento = $_POST['tipo_mantenimiento'] ?? 'correctivo';
    
    // ⭐ Obtener equipo_id (puede ser null si no se seleccionó equipo del inventario)
    $equipo_id = !empty($_POST['equipo_id']) ? intval($_POST['equipo_id']) : null;
    
    // Validaciones
    $tipos_validos = ['computadora', 'impresora', 'camara', 'telefono'];
    if (empty($tipo_equipo) || !in_array($tipo_equipo, $tipos_validos)) {
        $errores[] = 'Debe seleccionar un tipo de equipo válido.';
    }
    
    if (empty($descripcion_problema)) {
        $errores[] = 'La descripción del problema es obligatoria.';
    } elseif (strlen($descripcion_problema) < 20) {
        $errores[] = 'La descripción debe tener al menos 20 caracteres.';
    }
    
    $tipos_mant_validos = ['preventivo', 'correctivo'];
    if (!in_array($tipo_mantenimiento, $tipos_mant_validos)) {
        $tipo_mantenimiento = 'correctivo';
    }
    
    // Si hay errores, regresar al formulario
    if (!empty($errores)) {
        $_SESSION['form_errors'] = $errores;
        $_SESSION['form_data'] = $_POST;
        $redirect_url = URL_BASE . 'dashboard/sistemas/ti_sistemas/solicitar_mantenimiento.php';
        if ($equipo_id) {
            $redirect_url .= '?equipo_id=' . $equipo_id;
        }
        header('Location: ' . $redirect_url);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Generar folio
        $folio = generarFolio($pdo);
        
        // Obtener departamento_id del usuario
        $sql_depto = "SELECT departamento_id FROM usuarios WHERE id = ?";
        $stmt = $pdo->prepare($sql_depto);
        $stmt->execute([$_SESSION['usuario_id']]);
        $departamento_id = $stmt->fetchColumn();
        
        // ⭐ Insertar solicitud CON equipo_id
        $sql = "INSERT INTO solicitudes_mantenimiento_ti 
                (folio, tipo_equipo, equipo_id, tipo_mantenimiento, usuario_id, departamento_id, 
                 descripcion_problema, estado, fecha_solicitud) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $folio,
            $tipo_equipo,
            $equipo_id,
            $tipo_mantenimiento,
            $_SESSION['usuario_id'],
            $departamento_id,
            $descripcion_problema
        ]);
        
        $solicitud_id = $pdo->lastInsertId();
        
        // Registrar en historial
        $sql_hist = "INSERT INTO historial_mantenimientos_ti 
                     (solicitud_id, estado_anterior, estado_nuevo, comentario, usuario_id, fecha_cambio) 
                     VALUES (?, NULL, 'pendiente', 'Solicitud creada', ?, NOW())";
        $stmt = $pdo->prepare($sql_hist);
        $stmt->execute([$solicitud_id, $_SESSION['usuario_id']]);
        
        // Crear notificaciones para usuarios de Sistemas
        crearNotificacionesSistemas($pdo, $solicitud_id, $folio, $tipo_equipo, $_SESSION['nombre_completo'], $_SESSION['departamento']);
        
        $pdo->commit();
        
        establecer_alerta('success', "Solicitud {$folio} creada exitosamente. El departamento de Sistemas ha sido notificado.");
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error al crear solicitud de mantenimiento: " . $e->getMessage());
        $_SESSION['form_errors'] = ['Error al crear la solicitud. Por favor, intente nuevamente.'];
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/solicitar_mantenimiento.php');
        exit;
    }
}

/**
 * Cambiar estado de solicitud (solo Sistemas)
 */
function cambiarEstado($pdo) {
    global $_SESSION;
    
    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $nuevo_estado = $_POST['nuevo_estado'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    if (!$solicitud_id) {
        establecer_alerta('error', 'ID de solicitud no válido.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
        exit;
    }
    
    $estados_validos = ['en_proceso', 'finalizado', 'cancelado'];
    if (!in_array($nuevo_estado, $estados_validos)) {
        establecer_alerta('error', 'Estado no válido.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Obtener información actual de la solicitud
        $sql = "SELECT sm.*, u.nombre_completo as solicitante_nombre 
                FROM solicitudes_mantenimiento_ti sm 
                LEFT JOIN usuarios u ON sm.usuario_id = u.id 
                WHERE sm.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$solicitud_id]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$solicitud) {
            throw new Exception('Solicitud no encontrada.');
        }
        
        $estado_anterior = $solicitud['estado'];
        
        // Actualizar solicitud
        $sql_update = "UPDATE solicitudes_mantenimiento_ti SET 
                       estado = ?, 
                       atendido_por = ?,
                       descripcion_solucion = CASE 
                           WHEN ? = 'finalizado' THEN ? 
                           ELSE descripcion_solucion 
                       END,
                       observaciones_sistemas = CASE 
                           WHEN ? != 'finalizado' THEN ? 
                           ELSE observaciones_sistemas 
                       END,
                       fecha_atencion = CASE 
                           WHEN fecha_atencion IS NULL AND ? = 'en_proceso' THEN NOW() 
                           ELSE fecha_atencion 
                       END,
                       fecha_finalizacion = CASE 
                           WHEN ? IN ('finalizado', 'cancelado') THEN NOW() 
                           ELSE fecha_finalizacion 
                       END
                       WHERE id = ?";
        
        $stmt = $pdo->prepare($sql_update);
        $stmt->execute([
            $nuevo_estado,
            $_SESSION['usuario_id'],
            $nuevo_estado,
            $descripcion,
            $nuevo_estado,
            $descripcion,
            $nuevo_estado,
            $nuevo_estado,
            $solicitud_id
        ]);
        
        // Registrar en historial
        $sql_hist = "INSERT INTO historial_mantenimientos_ti 
                     (solicitud_id, estado_anterior, estado_nuevo, comentario, usuario_id, fecha_cambio) 
                     VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql_hist);
        $stmt->execute([
            $solicitud_id,
            $estado_anterior,
            $nuevo_estado,
            $descripcion,
            $_SESSION['usuario_id']
        ]);
        
        // Notificar al usuario solicitante
        crearNotificacionSolicitante($pdo, $solicitud, $nuevo_estado, $descripcion);
        
        $pdo->commit();
        
        $nombres_estado = [
            'en_proceso' => 'En Proceso',
            'finalizado' => 'Finalizado',
            'cancelado' => 'Cancelado'
        ];
        
        establecer_alerta('success', "Solicitud {$solicitud['folio']} actualizada a: {$nombres_estado[$nuevo_estado]}");
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al cambiar estado de mantenimiento: " . $e->getMessage());
        establecer_alerta('error', 'Error al actualizar la solicitud.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
        exit;
    }
}

/**
 * Crear notificaciones para usuarios de Sistemas
 */
function crearNotificacionesSistemas($pdo, $solicitud_id, $folio, $tipo_equipo, $solicitante, $departamento) {
    $tipos_nombres = [
        'computadora' => 'Computadora',
        'impresora' => 'Impresora',
        'camara' => 'Cámara',
        'telefono' => 'Teléfono'
    ];
    
    $tipo_nombre = $tipos_nombres[$tipo_equipo] ?? $tipo_equipo;
    $titulo = '🔧 Nueva Solicitud de Mantenimiento';
    $mensaje = "{$solicitante} ({$departamento}) solicita mantenimiento de {$tipo_nombre}";
    
    // Obtener usuarios de Sistemas
    $sql = "SELECT u.id FROM usuarios u 
            INNER JOIN departamentos d ON u.departamento_id = d.id 
            WHERE d.codigo = 'sistemas' AND u.activo = 1";
    $usuarios = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    
    $sql_notif = "INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, fecha_creacion) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql_notif);
    
    $datos = json_encode([
        'solicitud_id' => $solicitud_id,
        'folio' => $folio,
        'tipo_equipo' => $tipo_equipo,
        'url' => URL_BASE . 'dashboard/sistemas/ti_sistemas/ver_mantenimiento.php?id=' . $solicitud_id
    ]);
    
    foreach ($usuarios as $usuario_id) {
        $stmt->execute([
            'mantenimiento_nuevo',
            $titulo,
            $mensaje,
            $usuario_id,
            $datos
        ]);
    }
}

/**
 * Crear notificación para el solicitante
 */
function crearNotificacionSolicitante($pdo, $solicitud, $nuevo_estado, $descripcion) {
    global $_SESSION;
    
    $iconos = [
        'en_proceso' => '⚙️',
        'finalizado' => '✅',
        'cancelado' => '❌'
    ];
    
    $nombres_estado = [
        'en_proceso' => 'En Proceso',
        'finalizado' => 'Finalizado',
        'cancelado' => 'Cancelado'
    ];
    
    $icono = $iconos[$nuevo_estado] ?? '🔔';
    $estado_nombre = $nombres_estado[$nuevo_estado] ?? $nuevo_estado;
    
    $titulo = "{$icono} Actualización de Solicitud";
    $mensaje = "Tu solicitud {$solicitud['folio']} cambió a: {$estado_nombre}";
    if ($descripcion) {
        $mensaje .= " - " . substr($descripcion, 0, 100);
    }
    
    $datos = json_encode([
        'solicitud_id' => $solicitud['id'],
        'folio' => $solicitud['folio'],
        'estado' => $nuevo_estado,
        'url' => URL_BASE . 'dashboard/sistemas/ti_sistemas/ver_mantenimiento.php?id=' . $solicitud['id']
    ]);
    
    $sql = "INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, fecha_creacion) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'mantenimiento_actualizado',
        $titulo,
        $mensaje,
        $solicitud['usuario_id'],
        $datos
    ]);
}