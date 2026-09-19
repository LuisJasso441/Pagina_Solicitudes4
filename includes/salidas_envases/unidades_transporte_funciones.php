<?php
/**
 * Funciones de Unidades de Transporte + Capacidades
 *
 * Ubicación: includes/salidas_envases/unidades_transporte_funciones.php
 *
 * Modelo:
 *   unidades_transporte      → datos base de la unidad
 *   unidades_capacidades     → capacidad máxima por especificación de cada unidad
 *
 * Permisos:
 *   - Logística: CRUD completo.
 *   - Almacén de Residuos y Ventas: solo lectura.
 */

require_once __DIR__ . '/../../config/database.php';

// =====================================================================
// LECTURA
// =====================================================================

function obtener_unidades_transporte($incluir_inactivas = false) {
    try {
        $pdo = conectarDB();
        $where = $incluir_inactivas ? '' : 'WHERE u.activo = 1';
        $sql = "
            SELECT
                u.*,
                COUNT(c.id) AS total_capacidades
            FROM unidades_transporte u
            LEFT JOIN unidades_capacidades c ON c.unidad_id = u.id
            {$where}
            GROUP BY u.id
            ORDER BY u.nombre ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_unidades_transporte: ' . $e->getMessage());
        return [];
    }
}

function obtener_unidad_transporte_por_id($id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT * FROM unidades_transporte WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log('obtener_unidad_transporte_por_id: ' . $e->getMessage());
        return null;
    }
}

function existe_unidad_transporte($nombre, $excluir_id = null) {
    try {
        $pdo = conectarDB();
        $sql = "SELECT id FROM unidades_transporte WHERE nombre = ?";
        $params = [trim($nombre)];
        if ($excluir_id !== null) {
            $sql .= " AND id != ?";
            $params[] = (int) $excluir_id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    } catch (Exception $e) {
        error_log('existe_unidad_transporte: ' . $e->getMessage());
        return false;
    }
}

// =====================================================================
// CAPACIDADES
// =====================================================================

function obtener_capacidades_unidad($unidad_id) {
    try {
        $pdo = conectarDB();
        $sql = "
            SELECT
                c.id,
                c.unidad_id,
                c.especificacion_id,
                c.capacidad_maxima,
                e.nombre AS especificacion_nombre,
                e.activo AS especificacion_activa,
                t.id     AS tipo_id,
                t.nombre AS tipo_nombre,
                t.activo AS tipo_activo
            FROM unidades_capacidades c
            INNER JOIN sec_especificaciones e ON e.id = c.especificacion_id
            INNER JOIN sec_tipos_envase t     ON t.id = e.tipo_envase_id
            WHERE c.unidad_id = ?
            ORDER BY t.nombre ASC, e.nombre ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$unidad_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_capacidades_unidad: ' . $e->getMessage());
        return [];
    }
}

function especificacion_usada_en_unidades($especificacion_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT id FROM unidades_capacidades WHERE especificacion_id = ? LIMIT 1");
        $stmt->execute([$especificacion_id]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        error_log('especificacion_usada_en_unidades: ' . $e->getMessage());
        return true;
    }
}

// =====================================================================
// CREACIÓN Y ACTUALIZACIÓN (un solo método para ambos)
// =====================================================================

function guardar_unidad_transporte($id, $datos, $capacidades, $usuario_id) {
    $nombre    = trim($datos['nombre'] ?? '');
    $matricula = trim($datos['matricula'] ?? '');
    $notas     = trim($datos['notas'] ?? '');
    $activo    = !empty($datos['activo']) ? 1 : 0;

    if ($nombre === '') {
        return ['ok' => false, 'msg' => 'El nombre es obligatorio.'];
    }
    if (mb_strlen($nombre) > 100) {
        return ['ok' => false, 'msg' => 'El nombre no puede exceder 100 caracteres.'];
    }
    if (mb_strlen($matricula) > 50) {
        return ['ok' => false, 'msg' => 'La matrícula no puede exceder 50 caracteres.'];
    }
    if (existe_unidad_transporte($nombre, $id)) {
        return ['ok' => false, 'msg' => 'Ya existe otra unidad con ese nombre.'];
    }

    if ($id !== null && !obtener_unidad_transporte_por_id($id)) {
        return ['ok' => false, 'msg' => 'La unidad no existe.'];
    }

    $caps_limpias = [];
    $vistas       = [];
    foreach ($capacidades as $idx => $cap) {
        $espec_id = (int) ($cap['especificacion_id'] ?? 0);
        $cant     = (int) ($cap['capacidad_maxima'] ?? 0);
        if ($espec_id <= 0) continue;
        if ($cant <= 0) {
            return ['ok' => false, 'msg' => "Capacidad #" . ($idx + 1) . ": la cantidad debe ser mayor a 0."];
        }
        if (isset($vistas[$espec_id])) {
            return ['ok' => false, 'msg' => "Capacidad #" . ($idx + 1) . ": la especificación se repite en el formulario."];
        }
        $vistas[$espec_id] = true;
        $caps_limpias[] = ['especificacion_id' => $espec_id, 'capacidad_maxima' => $cant];
    }

    if (!empty($caps_limpias)) {
        try {
            $pdo_check = conectarDB();
            $espec_ids = array_column($caps_limpias, 'especificacion_id');
            $ph = implode(',', array_fill(0, count($espec_ids), '?'));
            $stmt = $pdo_check->prepare("SELECT id FROM sec_especificaciones WHERE id IN ({$ph})");
            $stmt->execute($espec_ids);
            $existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $faltantes = array_diff($espec_ids, $existentes);
            if (!empty($faltantes)) {
                return ['ok' => false, 'msg' => 'Alguna especificación seleccionada no existe.'];
            }
        } catch (Exception $e) {
            error_log('guardar_unidad_transporte (validar specs): ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Error al validar capacidades.'];
        }
    }

    $pdo = null;
    try {
        $pdo = conectarDB();
        $pdo->beginTransaction();

        if ($id === null) {
            $stmt = $pdo->prepare("
                INSERT INTO unidades_transporte (nombre, matricula, notas, activo, creado_por)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nombre,
                $matricula !== '' ? $matricula : null,
                $notas !== '' ? $notas : null,
                $activo,
                $usuario_id,
            ]);
            $unidad_id = (int) $pdo->lastInsertId();
        } else {
            $unidad_id = (int) $id;
            $stmt = $pdo->prepare("
                UPDATE unidades_transporte
                SET nombre = ?, matricula = ?, notas = ?, activo = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $nombre,
                $matricula !== '' ? $matricula : null,
                $notas !== '' ? $notas : null,
                $activo,
                $unidad_id,
            ]);
            $stmt = $pdo->prepare("DELETE FROM unidades_capacidades WHERE unidad_id = ?");
            $stmt->execute([$unidad_id]);
        }

        if (!empty($caps_limpias)) {
            $stmt = $pdo->prepare("
                INSERT INTO unidades_capacidades (unidad_id, especificacion_id, capacidad_maxima)
                VALUES (?, ?, ?)
            ");
            foreach ($caps_limpias as $cap) {
                $stmt->execute([$unidad_id, $cap['especificacion_id'], $cap['capacidad_maxima']]);
            }
        }

        $pdo->commit();
        return [
            'ok' => true,
            'msg' => $id === null ? 'Unidad creada correctamente.' : 'Unidad actualizada correctamente.',
            'unidad_id' => $unidad_id,
        ];
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        error_log('guardar_unidad_transporte: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al guardar la unidad. Intente de nuevo.'];
    }
}

// =====================================================================
// VALIDACIÓN DE USO (para bloquear eliminación)
// =====================================================================

/**
 * Devuelve un array con las razones por las que una unidad está en uso.
 * Array vacío significa que se puede eliminar.
 *
 * Verifica:
 *   - Bloque 5: sec_vueltas (vueltas programadas)
 *
 * Pendiente en bloques futuros:
 *   - Bloque 6 (SECs)
 *
 * @param int $id
 * @return string[] razones (vacío si no está en uso)
 */
function unidad_en_uso($id) {
    $razones = [];
    try {
        $pdo = conectarDB();

        // Vueltas programadas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sec_vueltas WHERE unidad_id = ?");
        $stmt->execute([$id]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) {
            $razones[] = "tiene {$n} vuelta(s) programada(s)";
        }

        return $razones;
    } catch (Exception $e) {
        error_log('unidad_en_uso: ' . $e->getMessage());
        return ['no se pudo validar el uso (error interno)'];
    }
}

// =====================================================================
// ELIMINACIÓN
// =====================================================================

function eliminar_unidad_transporte($id) {
    $razones = unidad_en_uso($id);
    if (!empty($razones)) {
        return [
            'ok' => false,
            'msg' => 'No se puede eliminar la unidad: ' . implode('; ', $razones)
                   . '. Considere desactivarla en lugar de eliminarla.',
        ];
    }
    try {
        $pdo = conectarDB();
        // FK ON DELETE CASCADE se encarga de eliminar las capacidades asociadas
        $stmt = $pdo->prepare("DELETE FROM unidades_transporte WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'msg' => 'La unidad no existe.'];
        }
        return ['ok' => true, 'msg' => 'Unidad eliminada (con sus capacidades).'];
    } catch (Exception $e) {
        error_log('eliminar_unidad_transporte: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al eliminar.'];
    }
}