<?php
/**
 * Funciones del Módulo Bitácora de Aires Acondicionados
 * Ubicación: includes/sistemas/bitacora_aires/bitacora_aires_funciones.php
 */

if (!function_exists('conectarDB')) {
    require_once __DIR__ . '/../../../config/database.php';
}

// ============================================================
// PERMISOS / ROLES
// ============================================================

/**
 * ¿El usuario actual pertenece al departamento de Sistemas/TI?
 * Sistemas ve TODOS los registros y NO crea préstamos (es el receptor).
 */
function ba_es_sistemas() {
    $depto = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
    return in_array($depto, ['sistemas', 'ti', 'ti_sistemas']);
}

// ============================================================
// CONSULTAS
// ============================================================

/**
 * Obtener registros visibles según rol.
 * - Sistemas: todos los registros (ordenados por más recientes primero)
 * - Otro: solo los del usuario logueado
 *
 * @param int $usuario_id ID del usuario logueado
 * @return array
 */
function ba_obtener_registros($usuario_id) {
    $pdo = conectarDB();

    if (ba_es_sistemas()) {
        $sql = "SELECT b.*
                FROM bitacora_aires_acondicionados b
                ORDER BY b.estado = 'devuelto' ASC,
                         b.fecha_prestamo DESC,
                         b.hora_prestamo DESC,
                         b.id DESC";
        $stmt = $pdo->query($sql);
    } else {
        $sql = "SELECT b.*
                FROM bitacora_aires_acondicionados b
                WHERE b.usuario_id = :uid
                ORDER BY b.estado = 'devuelto' ASC,
                         b.fecha_prestamo DESC,
                         b.hora_prestamo DESC,
                         b.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $usuario_id]);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener un registro por su ID.
 */
function ba_obtener_registro($id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM bitacora_aires_acondicionados WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * ¿El usuario tiene un préstamo activo? (uno a la vez)
 * Sirve para evitar préstamos duplicados desde la UI.
 */
function ba_usuario_tiene_prestamo_activo($usuario_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bitacora_aires_acondicionados
        WHERE usuario_id = :uid AND estado = 'activo'
    ");
    $stmt->execute([':uid' => $usuario_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Obtener el nombre del departamento del usuario por su ID.
 * Devuelve solo el nombre limpio del departamento (sin oficina/sala).
 */
function ba_obtener_departamento_usuario($usuario_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT d.id, d.nombre, d.codigo
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE u.id = :uid
        LIMIT 1
    ");
    $stmt->execute([':uid' => $usuario_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ============================================================
// OPERACIONES (insert / update)
// ============================================================

/**
 * Registrar un nuevo préstamo de control.
 *
 * @param array $datos  [usuario_id, nombre_completo, departamento_id, departamento_nombre, firma_prestamo]
 * @return array        ['success' => bool, 'id' => int|null, 'message' => string]
 */
function ba_registrar_prestamo($datos) {
    $pdo = conectarDB();

    if (ba_usuario_tiene_prestamo_activo($datos['usuario_id'])) {
        return [
            'success' => false,
            'id' => null,
            'message' => 'Ya tienes un control en préstamo. Debes devolverlo antes de solicitar otro.'
        ];
    }

    try {
        $sql = "INSERT INTO bitacora_aires_acondicionados (
                    usuario_id, nombre_completo, departamento_id, departamento_nombre,
                    fecha_prestamo, hora_prestamo, firma_prestamo,
                    estado, created_at, updated_at
                ) VALUES (
                    :uid, :nombre, :did, :dnombre,
                    CURDATE(), CURTIME(), :firma,
                    'activo', NOW(), NOW()
                )";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid'     => $datos['usuario_id'],
            ':nombre'  => $datos['nombre_completo'],
            ':did'     => $datos['departamento_id'],
            ':dnombre' => $datos['departamento_nombre'],
            ':firma'   => $datos['firma_prestamo'],
        ]);

        return [
            'success' => true,
            'id' => (int)$pdo->lastInsertId(),
            'message' => 'Préstamo registrado correctamente.'
        ];
    } catch (Exception $e) {
        error_log("[Bitácora Aires] Error al registrar préstamo: " . $e->getMessage());
        return [
            'success' => false,
            'id' => null,
            'message' => 'No se pudo registrar el préstamo. Intenta de nuevo.'
        ];
    }
}

/**
 * Registrar la devolución de un control.
 * Solo el dueño del préstamo puede cerrarlo, salvo que sea usuario de Sistemas.
 *
 * @param int    $registro_id
 * @param int    $usuario_id  (usuario que ejecuta la acción)
 * @param string $firma_entrega base64 data:image/png
 * @return array
 */
function ba_registrar_devolucion($registro_id, $usuario_id, $firma_entrega) {
    $pdo = conectarDB();

    $registro = ba_obtener_registro($registro_id);
    if (!$registro) {
        return ['success' => false, 'message' => 'Registro no encontrado.'];
    }

    if ($registro['estado'] === 'devuelto') {
        return ['success' => false, 'message' => 'Este préstamo ya fue cerrado.'];
    }

    // Solo el dueño puede cerrarlo (Sistemas también, por excepción administrativa)
    if ((int)$registro['usuario_id'] !== (int)$usuario_id && !ba_es_sistemas()) {
        return ['success' => false, 'message' => 'No puedes cerrar un préstamo que no es tuyo.'];
    }

    try {
        $sql = "UPDATE bitacora_aires_acondicionados
                SET hora_entrega    = CURTIME(),
                    fecha_devolucion = CURDATE(),
                    firma_entrega   = :firma,
                    estado          = 'devuelto',
                    updated_at      = NOW()
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':firma' => $firma_entrega,
            ':id'    => $registro_id,
        ]);

        return ['success' => true, 'message' => 'Devolución registrada correctamente.'];
    } catch (Exception $e) {
        error_log("[Bitácora Aires] Error al registrar devolución: " . $e->getMessage());
        return ['success' => false, 'message' => 'No se pudo registrar la devolución.'];
    }
}

// ============================================================
// NOTIFICACIONES
// ============================================================

/**
 * Notificar a todos los usuarios de Sistemas/TI sobre un nuevo préstamo.
 */
function ba_notificar_sistemas_nuevo_prestamo($registro_id, $solicitante_nombre, $departamento_nombre) {
    if (!function_exists('crear_notificacion')) {
        $notif_file = __DIR__ . '/../../notificaciones.php';
        if (file_exists($notif_file)) {
            require_once $notif_file;
        } else {
            return; // sin sistema de notificaciones, nada que hacer
        }
    }

    try {
        $pdo = conectarDB();
        $sql = "SELECT u.id FROM usuarios u
                INNER JOIN departamentos d ON u.departamento_id = d.id
                WHERE LOWER(d.codigo) IN ('sistemas','ti','ti_sistemas')
                  AND u.activo = 1";
        $usuarios = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

        $url = (defined('URL_BASE') ? URL_BASE : '/') . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php';

        foreach ($usuarios as $uid) {
            crear_notificacion(
                'bitacora_aires_prestamo',
                '❄️ Préstamo de Control AC',
                "{$solicitante_nombre} ({$departamento_nombre}) solicitó el control de aire acondicionado.",
                $uid,
                [
                    'registro_id' => $registro_id,
                    'url' => $url
                ]
            );
        }
    } catch (Exception $e) {
        error_log("[Bitácora Aires] Error al notificar Sistemas: " . $e->getMessage());
    }
}

/**
 * Notificar a Sistemas que un control fue devuelto.
 */
function ba_notificar_sistemas_devolucion($registro_id, $usuario_nombre, $departamento_nombre) {
    if (!function_exists('crear_notificacion')) {
        $notif_file = __DIR__ . '/../../notificaciones.php';
        if (file_exists($notif_file)) {
            require_once $notif_file;
        } else {
            return;
        }
    }

    try {
        $pdo = conectarDB();
        $sql = "SELECT u.id FROM usuarios u
                INNER JOIN departamentos d ON u.departamento_id = d.id
                WHERE LOWER(d.codigo) IN ('sistemas','ti','ti_sistemas')
                  AND u.activo = 1";
        $usuarios = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

        $url = (defined('URL_BASE') ? URL_BASE : '/') . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php';

        foreach ($usuarios as $uid) {
            crear_notificacion(
                'bitacora_aires_devolucion',
                '✅ Control AC Devuelto',
                "{$usuario_nombre} ({$departamento_nombre}) devolvió el control de aire acondicionado.",
                $uid,
                [
                    'registro_id' => $registro_id,
                    'url' => $url
                ]
            );
        }
    } catch (Exception $e) {
        error_log("[Bitácora Aires] Error al notificar devolución: " . $e->getMessage());
    }
}