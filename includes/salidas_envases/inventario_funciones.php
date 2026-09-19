<?php
/**
 * Funciones del Inventario de Envases
 *
 * Ubicación: includes/salidas_envases/inventario_funciones.php
 *
 * Modelo:
 *   sec_inventario                → snapshot del stock por especificación
 *   sec_inventario_movimientos    → log inmutable de cada cambio
 *
 * Permisos:
 *   - Almacén de Residuos: registra movimientos.
 *   - Logística y Ventas: solo lectura.
 *
 * Movimientos automáticos (bloques futuros):
 *   - Salida por SEC aprobada  → tipo='salida' con referencia_tipo='SEC'
 *   - Devolución desde una SEC → tipo='devolucion' con referencia_tipo='DEVOLUCION_SEC'
 */

require_once __DIR__ . '/../../config/database.php';

// =====================================================================
// LECTURA
// =====================================================================

/**
 * Devuelve todas las especificaciones con su stock actual (LEFT JOIN,
 * las que no tienen registro en sec_inventario aparecen con stock = 0).
 *
 * @param array $filtros [
 *   'tipo_id'            => int    (opcional, filtrar por tipo)
 *   'busqueda'           => string (opcional, LIKE en nombres)
 *   'incluir_inactivas'  => bool   (por defecto false)
 * ]
 * @return array
 */
function obtener_inventario($filtros = []) {
    try {
        $pdo = conectarDB();
        $conds = [];
        $params = [];

        $incluir_inactivas = !empty($filtros['incluir_inactivas']);
        if (!$incluir_inactivas) {
            $conds[] = 'e.activo = 1';
            $conds[] = 't.activo = 1';
        }
        if (!empty($filtros['tipo_id'])) {
            $conds[] = 't.id = ?';
            $params[] = (int) $filtros['tipo_id'];
        }
        if (!empty($filtros['busqueda'])) {
            $conds[] = '(t.nombre LIKE ? OR e.nombre LIKE ?)';
            $b = '%' . $filtros['busqueda'] . '%';
            $params[] = $b;
            $params[] = $b;
        }
        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

        $sql = "
            SELECT
                e.id            AS especificacion_id,
                e.nombre        AS especificacion_nombre,
                e.activo        AS especificacion_activa,
                t.id            AS tipo_id,
                t.nombre        AS tipo_nombre,
                t.activo        AS tipo_activo,
                COALESCE(i.cantidad_actual, 0) AS cantidad_actual,
                i.actualizado_en,
                i.notas,
                u.nombre_completo AS actualizado_por_nombre,
                (i.id IS NOT NULL) AS tiene_registro
            FROM sec_especificaciones e
            INNER JOIN sec_tipos_envase t ON t.id = e.tipo_envase_id
            LEFT JOIN sec_inventario i ON i.especificacion_id = e.id
            LEFT JOIN usuarios u ON u.id = i.actualizado_por
            {$where}
            ORDER BY t.nombre ASC, e.nombre ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_inventario: ' . $e->getMessage());
        return [];
    }
}

/**
 * Stock actual de una especificación (0 si no tiene registro).
 */
function obtener_stock_actual($especificacion_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT cantidad_actual FROM sec_inventario WHERE especificacion_id = ?");
        $stmt->execute([$especificacion_id]);
        $result = $stmt->fetchColumn();
        return $result === false ? 0 : (int) $result;
    } catch (Exception $e) {
        error_log('obtener_stock_actual: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Historial de movimientos con filtros y paginación.
 *
 * @param array $filtros [
 *   'especificacion_id' => int
 *   'tipo_movimiento'   => string
 *   'fecha_desde'       => 'YYYY-MM-DD'
 *   'fecha_hasta'       => 'YYYY-MM-DD'
 * ]
 * @param int $limite  (máx 500)
 * @param int $offset
 * @return array
 */
function obtener_movimientos($filtros = [], $limite = 50, $offset = 0) {
    try {
        $pdo = conectarDB();
        $conds = [];
        $params = [];

        if (!empty($filtros['especificacion_id'])) {
            $conds[] = 'm.especificacion_id = ?';
            $params[] = (int) $filtros['especificacion_id'];
        }
        if (!empty($filtros['tipo_movimiento'])) {
            $conds[] = 'm.tipo_movimiento = ?';
            $params[] = $filtros['tipo_movimiento'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $conds[] = 'DATE(m.creado_en) >= ?';
            $params[] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $conds[] = 'DATE(m.creado_en) <= ?';
            $params[] = $filtros['fecha_hasta'];
        }
        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

        $limite = max(1, min(500, (int) $limite));
        $offset = max(0, (int) $offset);

        $sql = "
            SELECT
                m.*,
                e.nombre AS especificacion_nombre,
                t.nombre AS tipo_nombre,
                u.nombre_completo AS usuario_nombre
            FROM sec_inventario_movimientos m
            INNER JOIN sec_especificaciones e ON e.id = m.especificacion_id
            INNER JOIN sec_tipos_envase t     ON t.id = e.tipo_envase_id
            LEFT  JOIN usuarios u             ON u.id = m.usuario_id
            {$where}
            ORDER BY m.creado_en DESC, m.id DESC
            LIMIT {$limite} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_movimientos: ' . $e->getMessage());
        return [];
    }
}

function contar_movimientos($filtros = []) {
    try {
        $pdo = conectarDB();
        $conds = [];
        $params = [];
        if (!empty($filtros['especificacion_id'])) {
            $conds[] = 'especificacion_id = ?';
            $params[] = (int) $filtros['especificacion_id'];
        }
        if (!empty($filtros['tipo_movimiento'])) {
            $conds[] = 'tipo_movimiento = ?';
            $params[] = $filtros['tipo_movimiento'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $conds[] = 'DATE(creado_en) >= ?';
            $params[] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $conds[] = 'DATE(creado_en) <= ?';
            $params[] = $filtros['fecha_hasta'];
        }
        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sec_inventario_movimientos {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('contar_movimientos: ' . $e->getMessage());
        return 0;
    }
}

// =====================================================================
// ESCRITURA (movimientos)
// =====================================================================

/**
 * Ejecutar un movimiento de inventario (entrada/salida/ajuste/devolucion/inicial).
 * Transaccional: actualiza sec_inventario + inserta el movimiento en un solo commit.
 * Usa FOR UPDATE para evitar race conditions.
 *
 * @param array $params [
 *   'especificacion_id' => int (obligatorio)
 *   'tipo_movimiento'   => 'entrada'|'salida'|'ajuste'|'devolucion'|'inicial'
 *   'cantidad'          => int (para entrada/salida/devolucion/inicial: cantidad a operar;
 *                              para ajuste: cantidad FINAL, no delta)
 *   'motivo'            => string (opcional)
 *   'usuario_id'        => int (obligatorio)
 *   'referencia_tipo'   => string|null (opcional, para vincular a SEC/devolución)
 *   'referencia_id'     => int|null (opcional)
 * ]
 * @return array ['ok' => bool, 'msg' => string, 'stock_anterior' => int, 'stock_actual' => int]
 */
function registrar_movimiento($params) {
    $espec_id   = (int) ($params['especificacion_id'] ?? 0);
    $tipo       = $params['tipo_movimiento'] ?? '';
    $cantidad   = (int) ($params['cantidad'] ?? 0);
    $motivo     = trim($params['motivo'] ?? '');
    $usuario_id = (int) ($params['usuario_id'] ?? 0);
    $ref_tipo   = $params['referencia_tipo'] ?? null;
    $ref_id     = isset($params['referencia_id']) ? (int) $params['referencia_id'] : null;

    if ($espec_id <= 0) {
        return ['ok' => false, 'msg' => 'Especificación inválida.'];
    }
    if (!in_array($tipo, ['entrada','salida','ajuste','devolucion','inicial'], true)) {
        return ['ok' => false, 'msg' => 'Tipo de movimiento inválido.'];
    }
    if ($cantidad < 0) {
        return ['ok' => false, 'msg' => 'La cantidad no puede ser negativa.'];
    }
    if (in_array($tipo, ['entrada','salida','devolucion','inicial'], true) && $cantidad <= 0) {
        return ['ok' => false, 'msg' => 'La cantidad debe ser mayor a 0.'];
    }
    if ($usuario_id <= 0) {
        return ['ok' => false, 'msg' => 'Usuario no identificado.'];
    }

    // Detectar si esta función se llama dentro de una transacción externa
    // (por ejemplo desde crear_sec, actualizar_sec, cancelar_sec).
    // Si es así, NO abrimos otra transacción anidada (PDO no las soporta
    // directamente y lanza "There is already an active transaction").
    // El caller externo se encarga del commit/rollback global.
    $pdo = null;
    $iniciada_aqui = false;
    try {
        $pdo = conectarDB();

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $iniciada_aqui = true;
        }

        $stmt = $pdo->prepare("SELECT id FROM sec_especificaciones WHERE id = ? FOR UPDATE");
        $stmt->execute([$espec_id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($iniciada_aqui) $pdo->rollBack();
            return ['ok' => false, 'msg' => 'La especificación no existe.'];
        }

        $stmt = $pdo->prepare("SELECT id, cantidad_actual FROM sec_inventario WHERE especificacion_id = ? FOR UPDATE");
        $stmt->execute([$espec_id]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        $stock_anterior  = $registro ? (int) $registro['cantidad_actual'] : 0;
        $tiene_registro  = ($registro !== false);

        switch ($tipo) {
            case 'entrada':
            case 'devolucion':
                $stock_nuevo         = $stock_anterior + $cantidad;
                $cantidad_movimiento = $cantidad;
                break;

            case 'salida':
                if ($cantidad > $stock_anterior) {
                    if ($iniciada_aqui) $pdo->rollBack();
                    return ['ok' => false, 'msg' => "Stock insuficiente. Actual: {$stock_anterior}, solicitado: {$cantidad}."];
                }
                $stock_nuevo         = $stock_anterior - $cantidad;
                $cantidad_movimiento = $cantidad;
                break;

            case 'ajuste':
                $stock_nuevo         = $cantidad;
                $cantidad_movimiento = abs($stock_nuevo - $stock_anterior);
                if ($cantidad_movimiento === 0) {
                    if ($iniciada_aqui) $pdo->rollBack();
                    return ['ok' => false, 'msg' => 'El ajuste no cambia el stock actual.'];
                }
                break;

            case 'inicial':
                if ($tiene_registro) {
                    if ($iniciada_aqui) $pdo->rollBack();
                    return ['ok' => false, 'msg' => 'Ya existe inventario para esta especificación. Use Entrada o Ajuste.'];
                }
                $stock_nuevo         = $cantidad;
                $cantidad_movimiento = $cantidad;
                break;

            default:
                if ($iniciada_aqui) $pdo->rollBack();
                return ['ok' => false, 'msg' => 'Tipo de movimiento no manejado.'];
        }

        if ($stock_nuevo < 0) {
            if ($iniciada_aqui) $pdo->rollBack();
            return ['ok' => false, 'msg' => 'La operación resultaría en stock negativo.'];
        }

        if ($tiene_registro) {
            $stmt = $pdo->prepare("
                UPDATE sec_inventario
                SET cantidad_actual = ?, actualizado_por = ?
                WHERE especificacion_id = ?
            ");
            $stmt->execute([$stock_nuevo, $usuario_id, $espec_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO sec_inventario (especificacion_id, cantidad_actual, actualizado_por)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$espec_id, $stock_nuevo, $usuario_id]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO sec_inventario_movimientos
                (especificacion_id, tipo_movimiento, cantidad_anterior, cantidad_movimiento,
                 cantidad_resultante, motivo, referencia_tipo, referencia_id, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $espec_id, $tipo, $stock_anterior, $cantidad_movimiento, $stock_nuevo,
            $motivo !== '' ? $motivo : null,
            $ref_tipo, $ref_id, $usuario_id,
        ]);

        if ($iniciada_aqui) {
            $pdo->commit();
        }

        return [
            'ok' => true,
            'msg' => 'Movimiento registrado correctamente.',
            'stock_anterior' => $stock_anterior,
            'stock_actual'   => $stock_nuevo,
        ];
    } catch (Exception $e) {
        // Solo hacemos rollBack si abrimos nosotros la transacción.
        // Si venía de fuera, el caller la maneja.
        if ($pdo && $iniciada_aqui && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('registrar_movimiento: ' . $e->getMessage());
        return [
            'ok' => false,
            'msg' => 'Error al registrar el movimiento: ' . $e->getMessage(),
        ];
    }
}

// =====================================================================
// VALIDACIONES PARA OTROS MÓDULOS (uso en Bloques 6 y 7)
// =====================================================================

/**
 * Verifica si hay stock disponible para una operación.
 * Usado por el módulo SEC (Bloque 6) al crear/validar una salida.
 */
function validar_stock_disponible($especificacion_id, $cantidad_requerida) {
    $stock = obtener_stock_actual($especificacion_id);
    $req   = (int) $cantidad_requerida;
    return [
        'stock_actual'       => $stock,
        'cantidad_requerida' => $req,
        'suficiente'         => $stock >= $req,
        'faltante'           => max(0, $req - $stock),
    ];
}

// =====================================================================
// DASHBOARD (Bloque 3) — métricas agregadas
// =====================================================================

/**
 * Métricas globales del inventario para el dashboard.
 * Solo considera especificaciones activas de tipos activos.
 *
 * @param int $umbral_bajo  stock < umbral (y > 0) se cuenta como "bajo"
 * @return array [
 *   total_especificaciones,
 *   total_envases,
 *   con_stock,
 *   sin_stock,
 *   stock_bajo,
 *   ultimo_movimiento (datetime|null),
 * ]
 */
function obtener_metricas_inventario($umbral_bajo = 10) {
    $default = [
        'total_especificaciones' => 0,
        'total_envases'          => 0,
        'con_stock'              => 0,
        'sin_stock'              => 0,
        'stock_bajo'             => 0,
        'ultimo_movimiento'      => null,
    ];
    try {
        $pdo = conectarDB();
        $umbral = max(1, (int) $umbral_bajo);
        $sql = "
            SELECT
                COUNT(DISTINCT e.id) AS total_especificaciones,
                COALESCE(SUM(i.cantidad_actual), 0) AS total_envases,
                SUM(CASE WHEN COALESCE(i.cantidad_actual, 0) > 0 THEN 1 ELSE 0 END) AS con_stock,
                SUM(CASE WHEN COALESCE(i.cantidad_actual, 0) = 0 THEN 1 ELSE 0 END) AS sin_stock,
                SUM(CASE WHEN COALESCE(i.cantidad_actual, 0) > 0
                          AND COALESCE(i.cantidad_actual, 0) < {$umbral} THEN 1 ELSE 0 END) AS stock_bajo
            FROM sec_especificaciones e
            INNER JOIN sec_tipos_envase t ON t.id = e.tipo_envase_id
            LEFT  JOIN sec_inventario i   ON i.especificacion_id = e.id
            WHERE e.activo = 1 AND t.activo = 1
        ";
        $stmt = $pdo->query($sql);
        $metricas = $stmt->fetch(PDO::FETCH_ASSOC) ?: $default;

        // Fecha del último movimiento (cualquier tipo)
        $stmt = $pdo->query("SELECT MAX(creado_en) FROM sec_inventario_movimientos");
        $metricas['ultimo_movimiento'] = $stmt->fetchColumn() ?: null;

        return array_merge($default, $metricas);
    } catch (Exception $e) {
        error_log('obtener_metricas_inventario: ' . $e->getMessage());
        return $default;
    }
}

/**
 * Stock total agrupado por Tipo de envase.
 * Cada elemento: {tipo_id, tipo_nombre, total_envases, total_especificaciones}
 */
function obtener_stock_por_tipo() {
    try {
        $pdo = conectarDB();
        $sql = "
            SELECT
                t.id                       AS tipo_id,
                t.nombre                   AS tipo_nombre,
                COALESCE(SUM(i.cantidad_actual), 0) AS total_envases,
                COUNT(DISTINCT e.id)       AS total_especificaciones
            FROM sec_tipos_envase t
            LEFT JOIN sec_especificaciones e ON e.tipo_envase_id = t.id AND e.activo = 1
            LEFT JOIN sec_inventario i       ON i.especificacion_id = e.id
            WHERE t.activo = 1
            GROUP BY t.id, t.nombre
            ORDER BY total_envases DESC, t.nombre ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_stock_por_tipo: ' . $e->getMessage());
        return [];
    }
}

/**
 * Top N especificaciones con más stock (solo las que tienen stock > 0).
 */
function obtener_top_especificaciones($limite = 10) {
    try {
        $pdo = conectarDB();
        $limite = max(1, min(100, (int) $limite));
        $sql = "
            SELECT
                e.id                   AS especificacion_id,
                e.nombre               AS especificacion_nombre,
                t.nombre               AS tipo_nombre,
                COALESCE(i.cantidad_actual, 0) AS cantidad_actual,
                i.actualizado_en
            FROM sec_especificaciones e
            INNER JOIN sec_tipos_envase t ON t.id = e.tipo_envase_id
            LEFT  JOIN sec_inventario i   ON i.especificacion_id = e.id
            WHERE e.activo = 1 AND t.activo = 1
              AND COALESCE(i.cantidad_actual, 0) > 0
            ORDER BY cantidad_actual DESC, e.nombre ASC
            LIMIT {$limite}
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_top_especificaciones: ' . $e->getMessage());
        return [];
    }
}

/**
 * Especificaciones con stock bajo (0 < stock < umbral).
 * NO incluye las que tienen stock = 0.
 */
function obtener_stock_bajo($umbral = 10) {
    try {
        $pdo = conectarDB();
        $umbral = max(1, (int) $umbral);
        $sql = "
            SELECT
                e.id             AS especificacion_id,
                e.nombre         AS especificacion_nombre,
                t.nombre         AS tipo_nombre,
                i.cantidad_actual,
                i.actualizado_en
            FROM sec_especificaciones e
            INNER JOIN sec_tipos_envase t ON t.id = e.tipo_envase_id
            INNER JOIN sec_inventario i   ON i.especificacion_id = e.id
            WHERE e.activo = 1 AND t.activo = 1
              AND i.cantidad_actual > 0
              AND i.cantidad_actual < ?
            ORDER BY i.cantidad_actual ASC, e.nombre ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$umbral]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_stock_bajo: ' . $e->getMessage());
        return [];
    }
}