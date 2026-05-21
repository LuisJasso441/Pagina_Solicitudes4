<?php
/**
 * Procesar Solicitudes de Mantenimiento (Nuevo Flujo)
 * dashboard/sistemas/ti_sistemas/mantenimientos/procesar_mantenimiento.php
 *
 * Acciones:
 *   - crear          (solo Sistemas)  -> estado inicial: pendiente
 *   - cambiar_estado (solo Sistemas)  -> en_proceso / finalizada / cancelada
 *
 * Las evidencias se gestionan por separado en procesar_evidencia_mantenimiento.php
 * desde la vista de la solicitud.
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';

// ------- Verificar sesion -------
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

$pdo = conectarDB();

$departamento_usuario = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_sistemas = in_array($departamento_usuario, ['ti', 'sistemas', 'ti_sistemas']);

// ------- Mapeo de grupos (identico a inventario.php / api_mantenimiento.php) -------
$GRUPOS_TIPOS = [
    'computo'     => ['all_in_one', 'pc', 'laptop', 'computadora'],
    'perifericos' => ['monitor', 'mouse', 'teclado'],
    'impresoras'  => ['impresora'],
    'red'         => ['access_point', 'switch'],
    'otros'       => ['camara', 'celular', 'telefono', 'pantalla_tv', 'nobreak'],
];

$accion = $_POST['accion'] ?? '';

switch ($accion) {
    case 'crear':
        if (!$es_sistemas) {
            establecer_alerta('error', 'Solo el departamento de Sistemas puede crear solicitudes de mantenimiento.');
            header('Location: ' . URL_BASE . 'index.php');
            exit;
        }
        crearSolicitud($pdo, $GRUPOS_TIPOS);
        break;

    case 'cambiar_estado':
        if (!$es_sistemas) {
            establecer_alerta('error', 'No tiene permisos para realizar esta accion.');
            header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
            exit;
        }
        cambiarEstado($pdo);
        break;

    default:
        establecer_alerta('error', 'Accion no valida.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
        exit;
}

// =====================================================================
// HELPERS
// =====================================================================

/**
 * Generar folio unico MANT-YYYYMMDD-NNNN
 */
function generarFolio($pdo) {
    $fecha = date('Ymd');
    $prefijo = "MANT-{$fecha}-";

    $sql = "SELECT folio FROM solicitudes_mantenimiento_ti
            WHERE folio LIKE ? ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$prefijo . '%']);
    $ultimo = $stmt->fetchColumn();

    $numero = $ultimo ? intval(substr($ultimo, -4)) + 1 : 1;
    return $prefijo . str_pad($numero, 4, '0', STR_PAD_LEFT);
}

// =====================================================================
// CREAR SOLICITUD (estado inicial: pendiente)
// =====================================================================
function crearSolicitud($pdo, $GRUPOS_TIPOS) {
    $errores = [];

    // ---------- Recoger datos ----------
    $grupo_equipo         = trim($_POST['grupo_equipo'] ?? '');
    $departamento_id      = intval($_POST['departamento_id'] ?? 0);
    $usuario_id           = intval($_POST['usuario_id'] ?? 0);
    $equipo_id            = intval($_POST['equipo_id'] ?? 0);
    $tipo_mantenimiento   = trim($_POST['tipo_mantenimiento'] ?? '');
    $prioridad            = trim($_POST['prioridad'] ?? 'media');
    $fecha_deseada        = trim($_POST['fecha_deseada'] ?? '');
    $hora_deseada         = trim($_POST['hora_deseada'] ?? '');
    $descripcion_problema = trim($_POST['descripcion_problema'] ?? '');

    // ---------- Validaciones ----------
    if (!isset($GRUPOS_TIPOS[$grupo_equipo])) {
        $errores[] = 'Debe seleccionar un tipo de equipo valido (Computo, Perifericos, Impresoras, Red u Otros).';
    }
    if (!$departamento_id) {
        $errores[] = 'Debe seleccionar un departamento.';
    }
    if (!$usuario_id) {
        $errores[] = 'Debe seleccionar un usuario destinatario.';
    }
    if (!$equipo_id) {
        $errores[] = 'Debe seleccionar un equipo del inventario.';
    }
    if (!in_array($tipo_mantenimiento, ['logico', 'fisico'])) {
        $errores[] = 'Tipo de mantenimiento no valido.';
    }
    if (!in_array($prioridad, ['alta', 'media', 'baja'])) {
        $errores[] = 'Prioridad no valida.';
    }
    if (!$fecha_deseada || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_deseada)) {
        $errores[] = 'Fecha programada no valida.';
    }
    if (!$hora_deseada || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora_deseada)) {
        $errores[] = 'Hora programada no valida.';
    }
    if (strlen($descripcion_problema) < 10) {
        $errores[] = 'La descripcion del problema debe tener al menos 10 caracteres.';
    }
    if (strlen($descripcion_problema) > 2000) {
        $errores[] = 'La descripcion del problema no puede exceder 2000 caracteres.';
    }

    // Validar usuario destino
    $usuario_destino = null;
    if ($usuario_id && empty($errores)) {
        $stmt = $pdo->prepare("SELECT id, nombre_completo, departamento_id FROM usuarios WHERE id = ? AND activo = 1");
        $stmt->execute([$usuario_id]);
        $usuario_destino = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario_destino) {
            $errores[] = 'El usuario seleccionado no existe o esta inactivo.';
        } elseif ($usuario_destino['departamento_id'] != $departamento_id) {
            $errores[] = 'El usuario seleccionado no pertenece al departamento indicado.';
        }
    }

    // Validar equipo
    $equipo = null;
    if ($equipo_id && empty($errores)) {
        $stmt = $pdo->prepare("SELECT id, hostname, codigo_interno, tipo_equipo, departamento_id, estado FROM inventario_equipos WHERE id = ?");
        $stmt->execute([$equipo_id]);
        $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$equipo) {
            $errores[] = 'El equipo seleccionado no existe.';
        } else {
            if ($equipo['estado'] !== 'activo') {
                $errores[] = 'El equipo seleccionado no esta activo.';
            }
            if ($equipo['departamento_id'] != $departamento_id) {
                $errores[] = 'El equipo seleccionado no pertenece al departamento indicado.';
            }
            if (!in_array($equipo['tipo_equipo'], $GRUPOS_TIPOS[$grupo_equipo] ?? [])) {
                $errores[] = 'El equipo seleccionado no corresponde al tipo de equipo elegido.';
            }
        }
    }

    // ---------- Si hay errores, regresar al form ----------
    if (!empty($errores)) {
        $_SESSION['form_errors'] = $errores;
        $_SESSION['form_data']   = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/solicitar_mantenimiento.php');
        exit;
    }

    // ---------- Insertar ----------
    try {
        $pdo->beginTransaction();

        $folio = generarFolio($pdo);

        $sql = "INSERT INTO solicitudes_mantenimiento_ti
                (folio, tipo_equipo, grupo_equipo, equipo_id, tipo_mantenimiento, prioridad,
                 fecha_deseada, hora_deseada, usuario_id, departamento_id,
                 descripcion_problema, estado, atendido_por, fecha_solicitud)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, NOW())";

        // tipo_equipo (legacy) lo dejamos sincronizado con el tipo real del equipo, por compatibilidad
        $tipo_equipo_legacy = $equipo['tipo_equipo'] ?? null;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $folio,
            $tipo_equipo_legacy,
            $grupo_equipo,
            $equipo_id,
            $tipo_mantenimiento,
            $prioridad,
            $fecha_deseada,
            $hora_deseada,
            $usuario_id,
            $departamento_id,
            $descripcion_problema,
            $_SESSION['usuario_id'], // atendido_por = quien crea (Sistemas)
        ]);

        $solicitud_id = $pdo->lastInsertId();

        // ---------- Historial ----------
        $sql_hist = "INSERT INTO historial_mantenimientos_ti
                     (solicitud_id, estado_anterior, estado_nuevo, comentario, usuario_id, fecha_cambio)
                     VALUES (?, NULL, 'pendiente', 'Solicitud creada y enviada al usuario', ?, NOW())";
        $stmt = $pdo->prepare($sql_hist);
        $stmt->execute([$solicitud_id, $_SESSION['usuario_id']]);

        // ---------- Notificar al usuario destinatario ----------
        notificarUsuarioReceptor($pdo, $solicitud_id, $folio, $usuario_id, $_SESSION['nombre_completo']);

        $pdo->commit();

        establecer_alerta('success', "Solicitud {$folio} creada y enviada al usuario {$usuario_destino['nombre_completo']}.");
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al crear solicitud de mantenimiento: " . $e->getMessage());
        $_SESSION['form_errors'] = ['Error al crear la solicitud. Por favor, intente nuevamente.'];
        $_SESSION['form_data']   = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/solicitar_mantenimiento.php');
        exit;
    }
}

// =====================================================================
// CAMBIAR ESTADO
// =====================================================================
function cambiarEstado($pdo) {
    $solicitud_id  = intval($_POST['solicitud_id'] ?? 0);
    $nuevo_estado  = $_POST['nuevo_estado'] ?? '';
    $descripcion   = trim($_POST['descripcion'] ?? '');

    if (!$solicitud_id) {
        establecer_alerta('error', 'ID de solicitud no valido.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
        exit;
    }

    // Sistemas solo puede cambiar a estos estados (cerrada se hace por firma del usuario en Fase 3)
    $estados_validos = ['en_proceso', 'finalizada', 'cancelada'];
    if (!in_array($nuevo_estado, $estados_validos)) {
        establecer_alerta('error', 'Estado no valido.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $sql = "SELECT sm.*, u.nombre_completo AS solicitante_nombre
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

        // Validar transiciones permitidas
        $transiciones_permitidas = [
            'pendiente'  => ['en_proceso', 'cancelada'],
            'en_proceso' => ['finalizada', 'cancelada'],
            'finalizada' => ['cancelada'],
        ];
        $permitidos = $transiciones_permitidas[$estado_anterior] ?? [];
        if (!in_array($nuevo_estado, $permitidos)) {
            throw new Exception("No se puede cambiar de '{$estado_anterior}' a '{$nuevo_estado}'.");
        }

        $sql_update = "UPDATE solicitudes_mantenimiento_ti SET
                        estado = :nuevo_estado,
                        atendido_por = :user_id,
                        descripcion_solucion = CASE
                            WHEN :ne1 = 'finalizada' THEN :desc1
                            ELSE descripcion_solucion
                        END,
                        observaciones_sistemas = CASE
                            WHEN :ne2 != 'finalizada' THEN :desc2
                            ELSE observaciones_sistemas
                        END,
                        fecha_atencion = CASE
                            WHEN fecha_atencion IS NULL AND :ne3 = 'en_proceso' THEN NOW()
                            ELSE fecha_atencion
                        END,
                        fecha_finalizacion = CASE
                            WHEN :ne4 IN ('finalizada', 'cancelada') THEN NOW()
                            ELSE fecha_finalizacion
                        END
                       WHERE id = :id";

        $stmt = $pdo->prepare($sql_update);
        $stmt->execute([
            ':nuevo_estado' => $nuevo_estado,
            ':user_id'      => $_SESSION['usuario_id'],
            ':ne1'          => $nuevo_estado,
            ':desc1'        => $descripcion,
            ':ne2'          => $nuevo_estado,
            ':desc2'        => $descripcion,
            ':ne3'          => $nuevo_estado,
            ':ne4'          => $nuevo_estado,
            ':id'           => $solicitud_id,
        ]);

        // Historial
        $sql_hist = "INSERT INTO historial_mantenimientos_ti
                     (solicitud_id, estado_anterior, estado_nuevo, comentario, usuario_id, fecha_cambio)
                     VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql_hist);
        $stmt->execute([$solicitud_id, $estado_anterior, $nuevo_estado, $descripcion, $_SESSION['usuario_id']]);

        // Notificar al usuario destinatario
        notificarCambioEstado($pdo, $solicitud, $nuevo_estado, $descripcion);

        $pdo->commit();

        $nombres_estado = [
            'en_proceso' => 'En Proceso',
            'finalizada' => 'Finalizada',
            'cancelada'  => 'Cancelada',
        ];
        establecer_alerta('success', "Solicitud {$solicitud['folio']} actualizada a: {$nombres_estado[$nuevo_estado]}");
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al cambiar estado de mantenimiento: " . $e->getMessage());
        establecer_alerta('error', 'Error al actualizar la solicitud: ' . $e->getMessage());
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
    }
}

// =====================================================================
// NOTIFICACIONES
// =====================================================================

/**
 * Notificacion al usuario destinatario cuando Sistemas crea y envia la solicitud
 */
function notificarUsuarioReceptor($pdo, $solicitud_id, $folio, $usuario_id, $sistemas_nombre) {
    $titulo  = 'Nueva Solicitud de Mantenimiento';
    $mensaje = "{$sistemas_nombre} (Sistemas) ha programado un mantenimiento para tu equipo. Folio: {$folio}";

    $datos = json_encode([
        'solicitud_id' => $solicitud_id,
        'folio'        => $folio,
        'url'          => URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id,
    ]);

    $sql = "INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['mantenimiento_nuevo', $titulo, $mensaje, $usuario_id, $datos]);
}

/**
 * Notificacion al usuario destinatario cuando Sistemas cambia el estado
 */
function notificarCambioEstado($pdo, $solicitud, $nuevo_estado, $descripcion) {
    $nombres = [
        'en_proceso' => 'En Proceso',
        'finalizada' => 'Finalizada (pendiente de tu firma)',
        'cancelada'  => 'Cancelada',
    ];

    $tipo_notif = ($nuevo_estado === 'finalizada') ? 'mantenimiento_finalizado' : 'mantenimiento_actualizado';

    $titulo  = 'Actualizacion de Solicitud';
    $mensaje = "Tu solicitud {$solicitud['folio']} cambio a: " . ($nombres[$nuevo_estado] ?? $nuevo_estado);
    if ($descripcion) {
        $mensaje .= ' - ' . substr($descripcion, 0, 100);
    }

    $datos = json_encode([
        'solicitud_id' => $solicitud['id'],
        'folio'        => $solicitud['folio'],
        'estado'       => $nuevo_estado,
        'url'          => URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud['id'],
    ]);

    $sql = "INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tipo_notif, $titulo, $mensaje, $solicitud['usuario_id'], $datos]);
}