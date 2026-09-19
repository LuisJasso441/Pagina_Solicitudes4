<?php
/**
 * Funciones de Vueltas (slots de unidades por día)
 *
 * Ubicación: includes/salidas_envases/vueltas_funciones.php
 *
 * Modelo:
 *   sec_vueltas → una fila por cada slot de una unidad en una fecha.
 *   Numeración automática por (unidad, fecha) con UNIQUE.
 *   Al eliminar una vuelta intermedia, las posteriores se renumeran.
 *
 * Permisos:
 *   - Logística: CRUD completo.
 *   - Almacén de Residuos y Ventas: solo lectura.
 *
 * Convención de referencias desde SECs (Bloque 6):
 *   Una SEC futura tendrá vuelta_id → sec_vueltas.id.
 *   Por eso vuelta_en_uso() se irá extendiendo en el Bloque 6.
 */

require_once __DIR__ . '/../../config/database.php';

// =====================================================================
// LECTURA
// =====================================================================

/**
 * Vueltas dentro de un rango de fechas, opcionalmente filtradas por unidad.
 * Devuelve una fila por vuelta con datos del catálogo de la unidad.
 *
 * @param string      $fecha_desde  YYYY-MM-DD
 * @param string      $fecha_hasta  YYYY-MM-DD
 * @param int|null    $unidad_id    filtrar por unidad (opcional)
 * @return array
 */
function obtener_vueltas_rango($fecha_desde, $fecha_hasta, $unidad_id = null) {
    try {
        $pdo = conectarDB();
        $conds = ['v.fecha BETWEEN ? AND ?'];
        $params = [$fecha_desde, $fecha_hasta];
        if ($unidad_id !== null && (int) $unidad_id > 0) {
            $conds[] = 'v.unidad_id = ?';
            $params[] = (int) $unidad_id;
        }
        $where = 'WHERE ' . implode(' AND ', $conds);
        $sql = "
            SELECT
                v.*,
                u.nombre    AS unidad_nombre,
                u.matricula AS unidad_matricula,
                u.activo    AS unidad_activa
            FROM sec_vueltas v
            INNER JOIN unidades_transporte u ON u.id = v.unidad_id
            {$where}
            ORDER BY v.fecha ASC, u.nombre ASC, v.numero ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_vueltas_rango: ' . $e->getMessage());
        return [];
    }
}

/**
 * Vueltas de una unidad en una fecha específica, ordenadas por número.
 */
function obtener_vueltas_unidad_fecha($unidad_id, $fecha) {
    try {
        $pdo = conectarDB();
        $sql = "
            SELECT v.*, u.nombre AS unidad_nombre
            FROM sec_vueltas v
            INNER JOIN unidades_transporte u ON u.id = v.unidad_id
            WHERE v.unidad_id = ? AND v.fecha = ?
            ORDER BY v.numero ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int) $unidad_id, $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_vueltas_unidad_fecha: ' . $e->getMessage());
        return [];
    }
}

/**
 * Resumen agrupado por (unidad_id, fecha) → total_vueltas.
 * Usado por el endpoint de eventos de FullCalendar.
 */
function obtener_resumen_vueltas($fecha_desde, $fecha_hasta, $unidad_id = null) {
    try {
        $pdo = conectarDB();
        $conds = ['v.fecha BETWEEN ? AND ?'];
        $params = [$fecha_desde, $fecha_hasta];
        if ($unidad_id !== null && (int) $unidad_id > 0) {
            $conds[] = 'v.unidad_id = ?';
            $params[] = (int) $unidad_id;
        }
        $where = 'WHERE ' . implode(' AND ', $conds);
        $sql = "
            SELECT
                v.unidad_id,
                v.fecha,
                u.nombre AS unidad_nombre,
                u.activo AS unidad_activa,
                COUNT(*) AS total_vueltas,
                MAX(v.numero) AS max_numero
            FROM sec_vueltas v
            INNER JOIN unidades_transporte u ON u.id = v.unidad_id
            {$where}
            GROUP BY v.unidad_id, v.fecha, u.nombre, u.activo
            ORDER BY v.fecha ASC, u.nombre ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_resumen_vueltas: ' . $e->getMessage());
        return [];
    }
}

function obtener_vuelta_por_id($id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT * FROM sec_vueltas WHERE id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log('obtener_vuelta_por_id: ' . $e->getMessage());
        return null;
    }
}

// =====================================================================
// CREACIÓN
// =====================================================================

/**
 * Agrega N vueltas nuevas para una unidad en una fecha.
 * Se numeran automáticamente comenzando en (max_numero_actual + 1).
 * Transaccional.
 *
 * @param int    $unidad_id
 * @param string $fecha        YYYY-MM-DD
 * @param int    $cantidad     cuántas vueltas agregar (>= 1)
 * @param string $notas        opcional, se aplica a todas las nuevas vueltas
 * @param int    $usuario_id
 * @return array ['ok'=>bool, 'msg'=>string, 'creadas'=>int, 'desde_numero'=>int]
 */
function agregar_vueltas($unidad_id, $fecha, $cantidad, $notas, $usuario_id) {
    $unidad_id  = (int) $unidad_id;
    $cantidad   = (int) $cantidad;
    $usuario_id = (int) $usuario_id;
    $notas      = trim((string) $notas);

    if ($unidad_id <= 0) {
        return ['ok' => false, 'msg' => 'Unidad inválida.'];
    }
    if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return ['ok' => false, 'msg' => 'Fecha inválida.'];
    }
    if ($cantidad < 1 || $cantidad > 20) {
        return ['ok' => false, 'msg' => 'Debe agregar entre 1 y 20 vueltas por operación.'];
    }
    if ($usuario_id <= 0) {
        return ['ok' => false, 'msg' => 'Usuario no identificado.'];
    }

    $pdo = null;
    try {
        $pdo = conectarDB();
        $pdo->beginTransaction();

        // Verificar unidad
        $stmt = $pdo->prepare("SELECT id, activo FROM unidades_transporte WHERE id = ? FOR UPDATE");
        $stmt->execute([$unidad_id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            $pdo->rollBack();
            return ['ok' => false, 'msg' => 'La unidad no existe.'];
        }
        if ((int) $u['activo'] === 0) {
            $pdo->rollBack();
            return ['ok' => false, 'msg' => 'La unidad está inactiva.'];
        }

        // Obtener número más alto actual para (unidad, fecha)
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(numero), 0) FROM sec_vueltas WHERE unidad_id = ? AND fecha = ? FOR UPDATE");
        $stmt->execute([$unidad_id, $fecha]);
        $max_actual = (int) $stmt->fetchColumn();

        // Insertar las nuevas vueltas
        $stmt = $pdo->prepare("
            INSERT INTO sec_vueltas (unidad_id, fecha, numero, notas, creado_por)
            VALUES (?, ?, ?, ?, ?)
        ");
        $desde = $max_actual + 1;
        for ($i = 0; $i < $cantidad; $i++) {
            $stmt->execute([
                $unidad_id,
                $fecha,
                $max_actual + 1 + $i,
                $notas !== '' ? $notas : null,
                $usuario_id,
            ]);
        }

        $pdo->commit();
        return [
            'ok'           => true,
            'msg'          => $cantidad === 1
                                ? "Vuelta {$desde} agregada."
                                : "Vueltas {$desde} a " . ($desde + $cantidad - 1) . " agregadas.",
            'creadas'      => $cantidad,
            'desde_numero' => $desde,
        ];
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        error_log('agregar_vueltas: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al agregar las vueltas. Intente de nuevo.'];
    }
}

// =====================================================================
// ELIMINACIÓN + RENUMERACIÓN
// =====================================================================

/**
 * Devuelve razones por las que una vuelta no se puede eliminar (bloques futuros).
 * @return string[] vacío si se puede eliminar
 */
function vuelta_en_uso($id) {
    // TODO: Bloque 6 — verificar si tiene una SEC asignada
    return [];
}

/**
 * Elimina una vuelta y renumera las restantes de (misma unidad, misma fecha)
 * para que queden secuenciales desde 1.
 * Transaccional. La renumeración usa un offset temporal (+10000) para no
 * chocar con el UNIQUE (unidad_id, fecha, numero) mientras se re-asignan.
 *
 * @param int $id
 * @return array ['ok'=>bool, 'msg'=>string]
 */
function eliminar_vuelta($id) {
    $id = (int) $id;
    if ($id <= 0) {
        return ['ok' => false, 'msg' => 'ID inválido.'];
    }

    $razones = vuelta_en_uso($id);
    if (!empty($razones)) {
        return ['ok' => false, 'msg' => 'No se puede eliminar: ' . implode('; ', $razones)];
    }

    $pdo = null;
    try {
        $pdo = conectarDB();
        $pdo->beginTransaction();

        // Obtener la vuelta para saber unidad/fecha (necesarios para renumerar)
        $stmt = $pdo->prepare("SELECT unidad_id, fecha FROM sec_vueltas WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$v) {
            $pdo->rollBack();
            return ['ok' => false, 'msg' => 'La vuelta no existe.'];
        }
        $unidad_id = (int) $v['unidad_id'];
        $fecha     = $v['fecha'];

        // Eliminar la vuelta
        $stmt = $pdo->prepare("DELETE FROM sec_vueltas WHERE id = ?");
        $stmt->execute([$id]);

        // Renumerar las restantes:
        //   1) Mover todos los números fuera del rango típico para no chocar con el UNIQUE.
        //   2) Renumerar secuencialmente desde 1 en orden ascendente del número previo.
        $stmt = $pdo->prepare("
            UPDATE sec_vueltas
            SET numero = numero + 10000
            WHERE unidad_id = ? AND fecha = ?
        ");
        $stmt->execute([$unidad_id, $fecha]);

        $stmt = $pdo->prepare("
            SELECT id FROM sec_vueltas
            WHERE unidad_id = ? AND fecha = ?
            ORDER BY numero ASC
        ");
        $stmt->execute([$unidad_id, $fecha]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $upd = $pdo->prepare("UPDATE sec_vueltas SET numero = ? WHERE id = ?");
        foreach ($ids as $i => $vid) {
            $upd->execute([$i + 1, $vid]);
        }

        $pdo->commit();
        return ['ok' => true, 'msg' => 'Vuelta eliminada.'];
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        error_log('eliminar_vuelta: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al eliminar la vuelta.'];
    }
}