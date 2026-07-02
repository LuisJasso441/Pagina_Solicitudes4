<?php
/**
 * Funciones del apartado de Comentarios del modulo SEC
 * Ubicacion: includes/salidas_envases/sec_comentarios_funciones.php
 *
 * Reglas:
 * - Cualquier usuario que pueda ver la SEC puede comentar.
 * - Se puede comentar en CUALQUIER estado (incluidos cerrada/cancelada).
 * - Solo el autor puede editar/eliminar sus propios comentarios.
 * - Hard delete al eliminar.
 * - Solo texto plano (sin adjuntos).
 */

require_once __DIR__ . '/../../config/database.php';
if (!defined('URL_BASE')) {
    require_once __DIR__ . '/../../config/config.php';
}

// ====================================================================
// CRUD
// ====================================================================

function crear_comentario_sec($sec_id, $usuario_id, $texto) {
    $texto = trim($texto);
    if ($texto === '')             return ['success' => false, 'errores' => ['El comentario no puede estar vacio.']];
    if (mb_strlen($texto) > 5000)  return ['success' => false, 'errores' => ['Comentario demasiado largo (max 5000 caracteres).']];

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            INSERT INTO sec_comentarios (sec_id, usuario_id, comentario, fecha_creacion)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([(int)$sec_id, (int)$usuario_id, $texto]);
        return ['success' => true, 'id' => (int)$pdo->lastInsertId(), 'errores' => []];
    } catch (Exception $e) {
        error_log("Error crear_comentario_sec: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al guardar comentario: ' . $e->getMessage()]];
    }
}

function editar_comentario_sec($comentario_id, $usuario_id, $texto_nuevo) {
    $texto_nuevo = trim($texto_nuevo);
    if ($texto_nuevo === '')              return ['success' => false, 'errores' => ['El comentario no puede estar vacio.']];
    if (mb_strlen($texto_nuevo) > 5000)   return ['success' => false, 'errores' => ['Comentario demasiado largo.']];

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT usuario_id, sec_id, comentario FROM sec_comentarios WHERE id = ?");
        $stmt->execute([(int)$comentario_id]);
        $com = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$com)                                              return ['success' => false, 'errores' => ['Comentario no encontrado.']];
        if ((int)$com['usuario_id'] !== (int)$usuario_id)       return ['success' => false, 'errores' => ['Solo el autor puede editar este comentario.']];
        if ($com['comentario'] === $texto_nuevo)                return ['success' => true, 'sec_id' => (int)$com['sec_id'], 'sin_cambios' => true, 'errores' => []];

        $texto_original = $com['comentario'];

        $stmt = $pdo->prepare("
            UPDATE sec_comentarios
            SET comentario = ?, fecha_edicion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$texto_nuevo, (int)$comentario_id]);

        return [
            'success' => true,
            'sec_id' => (int)$com['sec_id'],
            'texto_original' => $texto_original,
            'texto_nuevo' => $texto_nuevo,
            'errores' => []
        ];
    } catch (Exception $e) {
        error_log("Error editar_comentario_sec: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al editar: ' . $e->getMessage()]];
    }
}

function eliminar_comentario_sec($comentario_id, $usuario_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT usuario_id, sec_id, comentario FROM sec_comentarios WHERE id = ?");
        $stmt->execute([(int)$comentario_id]);
        $com = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$com)                                        return ['success' => false, 'errores' => ['Comentario no encontrado.']];
        if ((int)$com['usuario_id'] !== (int)$usuario_id) return ['success' => false, 'errores' => ['Solo el autor puede eliminar este comentario.']];

        $sec_id = (int)$com['sec_id'];
        $texto_original = $com['comentario'];

        $stmt = $pdo->prepare("DELETE FROM sec_comentarios WHERE id = ?");
        $stmt->execute([(int)$comentario_id]);

        return ['success' => true, 'sec_id' => $sec_id, 'texto_original' => $texto_original, 'errores' => []];
    } catch (Exception $e) {
        error_log("Error eliminar_comentario_sec: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al eliminar: ' . $e->getMessage()]];
    }
}

function obtener_comentarios_sec($sec_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT c.*,
                   u.nombre_completo AS autor_nombre,
                   d.nombre AS autor_departamento
            FROM sec_comentarios c
            LEFT JOIN usuarios u ON u.id = c.usuario_id
            LEFT JOIN departamentos d ON d.id = u.departamento_id
            WHERE c.sec_id = ?
            ORDER BY c.fecha_creacion ASC
        ");
        $stmt->execute([(int)$sec_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obtener_comentarios_sec: " . $e->getMessage());
        return [];
    }
}

function contar_comentarios_sec($sec_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sec_comentarios WHERE sec_id = ?");
        $stmt->execute([(int)$sec_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// ====================================================================
// NOTIFICACION
// Estrategia hibrida:
//  1) Usuarios que ya participaron en la SEC:
//     - creador, firmante Entrega, firmante Recibe, otros comentadores
//  2) Todos los usuarios activos de los tres departamentos (Logistica,
//     Ventas, Almacen de Residuos), excluyendo al autor del comentario.
//  Deduplicado antes de insertar.
// ====================================================================

function notificar_nuevo_comentario_sec($sec, $autor_id, $autor_nombre, $texto) {
    try {
        $pdo = conectarDB();

        $usuarios_notificar = [];

        // Estrategia 1: usuarios especificos de la SEC
        if (!empty($sec['usuario_creador_id']) && (int)$sec['usuario_creador_id'] !== (int)$autor_id) {
            $usuarios_notificar[] = (int)$sec['usuario_creador_id'];
        }
        if (!empty($sec['entrega_usuario_id']) && (int)$sec['entrega_usuario_id'] !== (int)$autor_id) {
            $usuarios_notificar[] = (int)$sec['entrega_usuario_id'];
        }
        if (!empty($sec['recibe_usuario_id']) && (int)$sec['recibe_usuario_id'] !== (int)$autor_id) {
            $usuarios_notificar[] = (int)$sec['recibe_usuario_id'];
        }
        // Otros comentadores
        $stmt = $pdo->prepare("
            SELECT DISTINCT usuario_id
            FROM sec_comentarios
            WHERE sec_id = ? AND usuario_id != ?
        ");
        $stmt->execute([(int)$sec['id'], (int)$autor_id]);
        $otros = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $usuarios_notificar = array_merge($usuarios_notificar, array_map('intval', $otros));

        // Estrategia 2: todos los usuarios activos de los 3 departamentos
        $stmt = $pdo->prepare("
            SELECT u.id
            FROM usuarios u
            INNER JOIN departamentos d ON u.departamento_id = d.id
            WHERE d.codigo IN ('logistica', 'ventas', 'almacen_residuos')
              AND u.activo = 1
              AND u.id != ?
        ");
        $stmt->execute([(int)$autor_id]);
        $deptos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $usuarios_notificar = array_merge($usuarios_notificar, array_map('intval', $deptos));

        // Deduplicar
        $usuarios_notificar = array_unique($usuarios_notificar);
        if (empty($usuarios_notificar)) return;

        // Preview del texto
        $preview = mb_substr($texto, 0, 80) . (mb_strlen($texto) > 80 ? '...' : '');

        $url_base = defined('URL_BASE') ? URL_BASE : '/Pagina_Solicitudes4/';
        $datos_json = json_encode([
            'sec_id' => (int)$sec['id'],
            'folio'  => $sec['folio'],
            'url'    => $url_base . 'dashboard/salidas_envases/ver_sec.php?id=' . (int)$sec['id']
        ], JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("
            INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        foreach ($usuarios_notificar as $uid) {
            try {
                $stmt->execute([
                    'sec_comentario',
                    "💬 Nuevo comentario en {$sec['folio']}",
                    "{$autor_nombre}: {$preview}",
                    (int)$uid,
                    $datos_json
                ]);
            } catch (Exception $e) {
                error_log("Error notif comentario SEC a usuario $uid: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log("Error notificar_nuevo_comentario_sec: " . $e->getMessage());
    }
}