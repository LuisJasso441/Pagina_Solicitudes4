<?php
/**
 * Funciones del catálogo de Tipos de Envase + Especificaciones
 *
 * Ubicación: includes/salidas_envases/tipos_envase_funciones.php
 *
 * Modelo:
 *   sec_tipos_envase (Tambo, Tote, Garrafa, Jaula, …)
 *     └── sec_especificaciones (Tambo abierto, Tambo cerrado, Tote 1000L, …)
 *
 * Permisos:
 *   - Logística: CRUD completo (controlado en cada handler y en la vista).
 *   - Almacén de Residuos y Ventas: solo lectura.
 */

require_once __DIR__ . '/../../config/database.php';

// =====================================================================
// LECTURA
// =====================================================================

/**
 * Lista de tipos de envase con conteo de especificaciones.
 *
 * @param bool $incluir_inactivos
 * @return array
 */
function obtener_tipos_envase($incluir_inactivos = false) {
    try {
        $pdo = conectarDB();
        $where = $incluir_inactivos ? '' : 'WHERE t.activo = 1';
        $sql = "
            SELECT
                t.*,
                COUNT(DISTINCT e.id) AS total_especificaciones,
                SUM(CASE WHEN e.activo = 1 THEN 1 ELSE 0 END) AS especificaciones_activas
            FROM sec_tipos_envase t
            LEFT JOIN sec_especificaciones e ON e.tipo_envase_id = t.id
            {$where}
            GROUP BY t.id
            ORDER BY t.nombre ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_tipos_envase: ' . $e->getMessage());
        return [];
    }
}

function obtener_tipo_envase_por_id($id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT * FROM sec_tipos_envase WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log('obtener_tipo_envase_por_id: ' . $e->getMessage());
        return null;
    }
}

function obtener_tipo_envase_por_nombre($nombre) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT * FROM sec_tipos_envase WHERE nombre = ?");
        $stmt->execute([trim($nombre)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log('obtener_tipo_envase_por_nombre: ' . $e->getMessage());
        return null;
    }
}

/**
 * Lista de especificaciones (opcionalmente filtradas por tipo).
 * Devuelve también el nombre del tipo padre.
 */
function obtener_especificaciones($tipo_id = null, $incluir_inactivas = false) {
    try {
        $pdo = conectarDB();
        $conds = [];
        $params = [];
        if ($tipo_id !== null) {
            $conds[] = 'e.tipo_envase_id = ?';
            $params[] = $tipo_id;
        }
        if (!$incluir_inactivas) {
            $conds[] = 'e.activo = 1';
        }
        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
        $sql = "
            SELECT
                e.*,
                t.nombre AS tipo_nombre,
                t.activo AS tipo_activo
            FROM sec_especificaciones e
            INNER JOIN sec_tipos_envase t ON t.id = e.tipo_envase_id
            {$where}
            ORDER BY t.nombre ASC, e.nombre ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_especificaciones: ' . $e->getMessage());
        return [];
    }
}

function obtener_especificacion_por_id($id) {
    try {
        $pdo = conectarDB();
        $sql = "
            SELECT e.*, t.nombre AS tipo_nombre
            FROM sec_especificaciones e
            INNER JOIN sec_tipos_envase t ON t.id = e.tipo_envase_id
            WHERE e.id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log('obtener_especificacion_por_id: ' . $e->getMessage());
        return null;
    }
}

// =====================================================================
// VALIDACIÓN DE UNICIDAD
// =====================================================================

function existe_tipo_envase($nombre, $excluir_id = null) {
    try {
        $pdo = conectarDB();
        $sql = "SELECT id FROM sec_tipos_envase WHERE nombre = ?";
        $params = [trim($nombre)];
        if ($excluir_id !== null) {
            $sql .= " AND id != ?";
            $params[] = $excluir_id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    } catch (Exception $e) {
        error_log('existe_tipo_envase: ' . $e->getMessage());
        return false;
    }
}

function existe_especificacion($tipo_id, $nombre, $excluir_id = null) {
    try {
        $pdo = conectarDB();
        $sql = "SELECT id FROM sec_especificaciones WHERE tipo_envase_id = ? AND nombre = ?";
        $params = [$tipo_id, trim($nombre)];
        if ($excluir_id !== null) {
            $sql .= " AND id != ?";
            $params[] = $excluir_id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    } catch (Exception $e) {
        error_log('existe_especificacion: ' . $e->getMessage());
        return false;
    }
}

// =====================================================================
// CREACIÓN
// =====================================================================

/**
 * Modo "Nuevo Tipo": crea un tipo nuevo + su primera especificación.
 * Transaccional (o se crean ambos, o ninguno).
 *
 * @return array ['ok' => bool, 'msg' => string, 'tipo_id' => ?int, 'especificacion_id' => ?int]
 */
function crear_tipo_con_especificacion($nombre_tipo, $nombre_espec, $usuario_id) {
    $nombre_tipo  = trim($nombre_tipo);
    $nombre_espec = trim($nombre_espec);

    if ($nombre_tipo === '') {
        return ['ok' => false, 'msg' => 'El nombre del tipo es obligatorio.'];
    }
    if ($nombre_espec === '') {
        return ['ok' => false, 'msg' => 'El nombre de la especificación es obligatorio.'];
    }
    if (mb_strlen($nombre_tipo) > 100) {
        return ['ok' => false, 'msg' => 'El nombre del tipo no puede exceder 100 caracteres.'];
    }
    if (mb_strlen($nombre_espec) > 150) {
        return ['ok' => false, 'msg' => 'El nombre de la especificación no puede exceder 150 caracteres.'];
    }
    if (existe_tipo_envase($nombre_tipo)) {
        return ['ok' => false, 'msg' => 'Ya existe un tipo de envase con ese nombre.'];
    }

    $pdo = null;
    try {
        $pdo = conectarDB();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO sec_tipos_envase (nombre, creado_por) VALUES (?, ?)");
        $stmt->execute([$nombre_tipo, $usuario_id]);
        $tipo_id = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO sec_especificaciones (tipo_envase_id, nombre, creado_por) VALUES (?, ?, ?)");
        $stmt->execute([$tipo_id, $nombre_espec, $usuario_id]);
        $espec_id = (int) $pdo->lastInsertId();

        $pdo->commit();
        return [
            'ok' => true,
            'msg' => 'Tipo y especificación creados correctamente.',
            'tipo_id' => $tipo_id,
            'especificacion_id' => $espec_id,
        ];
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        error_log('crear_tipo_con_especificacion: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al crear el tipo. Intente de nuevo.'];
    }
}

/**
 * Modo "Tipo Existente": agrega una nueva especificación a un tipo ya creado.
 */
function agregar_especificacion($tipo_id, $nombre_espec, $usuario_id) {
    $nombre_espec = trim($nombre_espec);

    if ($nombre_espec === '') {
        return ['ok' => false, 'msg' => 'El nombre de la especificación es obligatorio.'];
    }
    if (mb_strlen($nombre_espec) > 150) {
        return ['ok' => false, 'msg' => 'El nombre no puede exceder 150 caracteres.'];
    }
    if (!obtener_tipo_envase_por_id($tipo_id)) {
        return ['ok' => false, 'msg' => 'El tipo de envase no existe.'];
    }
    if (existe_especificacion($tipo_id, $nombre_espec)) {
        return ['ok' => false, 'msg' => 'Ya existe una especificación con ese nombre para este tipo.'];
    }

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("INSERT INTO sec_especificaciones (tipo_envase_id, nombre, creado_por) VALUES (?, ?, ?)");
        $stmt->execute([$tipo_id, $nombre_espec, $usuario_id]);
        return [
            'ok' => true,
            'msg' => 'Especificación agregada correctamente.',
            'especificacion_id' => (int) $pdo->lastInsertId(),
        ];
    } catch (Exception $e) {
        error_log('agregar_especificacion: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al agregar la especificación.'];
    }
}

// =====================================================================
// ACTUALIZACIÓN
// =====================================================================

function actualizar_tipo_envase($id, $nombre, $activo = null) {
    $nombre = trim($nombre);
    if ($nombre === '') return ['ok' => false, 'msg' => 'El nombre es obligatorio.'];
    if (mb_strlen($nombre) > 100) return ['ok' => false, 'msg' => 'El nombre no puede exceder 100 caracteres.'];
    if (existe_tipo_envase($nombre, $id)) {
        return ['ok' => false, 'msg' => 'Ya existe otro tipo con ese nombre.'];
    }

    try {
        $pdo = conectarDB();
        if ($activo === null) {
            $stmt = $pdo->prepare("UPDATE sec_tipos_envase SET nombre = ? WHERE id = ?");
            $stmt->execute([$nombre, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE sec_tipos_envase SET nombre = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $activo ? 1 : 0, $id]);
        }
        if ($stmt->rowCount() === 0) {
            return ['ok' => true, 'msg' => 'Sin cambios.'];
        }
        return ['ok' => true, 'msg' => 'Tipo actualizado.'];
    } catch (Exception $e) {
        error_log('actualizar_tipo_envase: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al actualizar.'];
    }
}

function actualizar_especificacion($id, $nombre, $activo = null) {
    $nombre = trim($nombre);
    if ($nombre === '') return ['ok' => false, 'msg' => 'El nombre es obligatorio.'];
    if (mb_strlen($nombre) > 150) return ['ok' => false, 'msg' => 'El nombre no puede exceder 150 caracteres.'];

    $espec = obtener_especificacion_por_id($id);
    if (!$espec) return ['ok' => false, 'msg' => 'La especificación no existe.'];

    if (existe_especificacion($espec['tipo_envase_id'], $nombre, $id)) {
        return ['ok' => false, 'msg' => 'Ya existe otra especificación con ese nombre para este tipo.'];
    }

    try {
        $pdo = conectarDB();
        if ($activo === null) {
            $stmt = $pdo->prepare("UPDATE sec_especificaciones SET nombre = ? WHERE id = ?");
            $stmt->execute([$nombre, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE sec_especificaciones SET nombre = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $activo ? 1 : 0, $id]);
        }
        return ['ok' => true, 'msg' => 'Especificación actualizada.'];
    } catch (Exception $e) {
        error_log('actualizar_especificacion: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al actualizar.'];
    }
}

// =====================================================================
// VALIDACIÓN DE USO (para bloquear eliminación)
// =====================================================================

/**
 * Devuelve un array con las razones por las que una especificación está en uso.
 * Array vacío significa que se puede eliminar.
 *
 * Verifica:
 *   - Bloque 2: sec_inventario (stock actual) y sec_inventario_movimientos (historial)
 *   - Bloque 4: unidades_capacidades (configuradas en unidades de transporte)
 *
 * Pendiente en bloques futuros:
 *   - Bloque 6 (líneas SEC)
 *
 * @param int $id
 * @return string[] razones (vacío si no está en uso)
 */
function especificacion_en_uso($id) {
    $razones = [];
    try {
        $pdo = conectarDB();

        // Inventario actual con stock
        $stmt = $pdo->prepare("SELECT cantidad_actual FROM sec_inventario WHERE especificacion_id = ? AND cantidad_actual > 0");
        $stmt->execute([$id]);
        $stock = $stmt->fetchColumn();
        if ($stock !== false) {
            $razones[] = "tiene {$stock} unidad(es) en inventario actual";
        }

        // Movimientos históricos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sec_inventario_movimientos WHERE especificacion_id = ?");
        $stmt->execute([$id]);
        $movs = (int) $stmt->fetchColumn();
        if ($movs > 0) {
            $razones[] = "tiene {$movs} movimiento(s) histórico(s) de inventario";
        }

        // Capacidades configuradas en unidades
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT c.unidad_id)
            FROM unidades_capacidades c
            WHERE c.especificacion_id = ?
        ");
        $stmt->execute([$id]);
        $unidades = (int) $stmt->fetchColumn();
        if ($unidades > 0) {
            $razones[] = "está configurada como capacidad en {$unidades} unidad(es) de transporte";
        }

        return $razones;
    } catch (Exception $e) {
        error_log('especificacion_en_uso: ' . $e->getMessage());
        return ['no se pudo validar el uso (error interno)']; // fail-safe
    }
}

/**
 * Devuelve un array con las razones por las que un tipo está en uso
 * (alguna de sus especificaciones está en uso).
 * Array vacío significa que se puede eliminar.
 *
 * @param int $id
 * @return string[] razones (vacío si no está en uso)
 */
function tipo_envase_en_uso($id) {
    $razones = [];
    try {
        $pdo = conectarDB();

        // Especificaciones con stock actual
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT e.id)
            FROM sec_especificaciones e
            INNER JOIN sec_inventario i ON i.especificacion_id = e.id
            WHERE e.tipo_envase_id = ? AND i.cantidad_actual > 0
        ");
        $stmt->execute([$id]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) {
            $razones[] = "{$n} especificación(es) con stock actual";
        }

        // Especificaciones con movimientos históricos
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT e.id)
            FROM sec_especificaciones e
            INNER JOIN sec_inventario_movimientos m ON m.especificacion_id = e.id
            WHERE e.tipo_envase_id = ?
        ");
        $stmt->execute([$id]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) {
            $razones[] = "{$n} especificación(es) con historial de inventario";
        }

        // Especificaciones configuradas en unidades
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT e.id)
            FROM sec_especificaciones e
            INNER JOIN unidades_capacidades c ON c.especificacion_id = e.id
            WHERE e.tipo_envase_id = ?
        ");
        $stmt->execute([$id]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) {
            $razones[] = "{$n} especificación(es) configurada(s) en unidades de transporte";
        }

        return $razones;
    } catch (Exception $e) {
        error_log('tipo_envase_en_uso: ' . $e->getMessage());
        return ['no se pudo validar el uso (error interno)']; // fail-safe
    }
}

// =====================================================================
// ELIMINACIÓN
// =====================================================================

function eliminar_tipo_envase($id) {
    $razones = tipo_envase_en_uso($id);
    if (!empty($razones)) {
        return [
            'ok' => false,
            'msg' => 'No se puede eliminar el tipo: ' . implode('; ', $razones)
                   . '. Considere desactivarlo en lugar de eliminarlo.',
        ];
    }
    try {
        $pdo = conectarDB();
        // FK ON DELETE CASCADE elimina las especificaciones asociadas automáticamente
        $stmt = $pdo->prepare("DELETE FROM sec_tipos_envase WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'msg' => 'El tipo no existe.'];
        }
        return ['ok' => true, 'msg' => 'Tipo eliminado (con sus especificaciones).'];
    } catch (Exception $e) {
        error_log('eliminar_tipo_envase: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al eliminar.'];
    }
}

function eliminar_especificacion($id) {
    $razones = especificacion_en_uso($id);
    if (!empty($razones)) {
        return [
            'ok' => false,
            'msg' => 'No se puede eliminar la especificación: ' . implode('; ', $razones)
                   . '. Considere desactivarla en lugar de eliminarla.',
        ];
    }
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("DELETE FROM sec_especificaciones WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'msg' => 'La especificación no existe.'];
        }
        return ['ok' => true, 'msg' => 'Especificación eliminada.'];
    } catch (Exception $e) {
        error_log('eliminar_especificacion: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al eliminar.'];
    }
}