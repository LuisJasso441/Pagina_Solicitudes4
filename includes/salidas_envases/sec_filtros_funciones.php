<?php
/**
 * Funciones de filtros avanzados y consultas para el listado de SECs.
 * Ubicación: includes/salidas_envases/sec_filtros_funciones.php
 *
 * Sub-bloque 8.1 — Filtros + Export.
 *
 * Filtros soportados (todos opcionales):
 *   'fecha_desde' string YYYY-MM-DD
 *   'fecha_hasta' string YYYY-MM-DD
 *   'estados'     array de estados válidos (multi-select)
 *   'empresa'     string, coincidencia parcial en empresa_nombre
 *   'unidad_id'   int, unidad de transporte de la vuelta
 *   'creador_id'  int, usuario_creador_id
 *   'busqueda'    string, coincide en folio, solicita_nombre, chofer_nombre
 */

require_once __DIR__ . '/../../config/database.php';

// =====================================================================
// CONSULTAS DE FILTROS (para poblar dropdowns)
// =====================================================================

/**
 * Empresas destino que han aparecido en alguna línea de SEC.
 * Ordenadas alfabéticamente.
 */
function obtener_empresas_para_filtro() {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->query("
            SELECT DISTINCT empresa_nombre
            FROM sec_lineas_empresa
            WHERE empresa_nombre IS NOT NULL AND empresa_nombre <> ''
            ORDER BY empresa_nombre ASC
        ");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'empresa_nombre');
    } catch (Exception $e) {
        error_log('obtener_empresas_para_filtro: ' . $e->getMessage());
        return [];
    }
}

/**
 * Unidades de transporte que han sido asignadas a alguna SEC.
 */
function obtener_unidades_para_filtro() {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->query("
            SELECT DISTINCT ut.id, ut.nombre, ut.matricula
            FROM unidades_transporte ut
            INNER JOIN sec_salidas s ON s.unidad_id = ut.id
            ORDER BY ut.nombre ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_unidades_para_filtro: ' . $e->getMessage());
        return [];
    }
}

/**
 * Usuarios que han creado alguna SEC.
 */
function obtener_creadores_para_filtro() {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->query("
            SELECT DISTINCT u.id, u.nombre_completo
            FROM usuarios u
            INNER JOIN sec_salidas s ON s.usuario_creador_id = u.id
            ORDER BY u.nombre_completo ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_creadores_para_filtro: ' . $e->getMessage());
        return [];
    }
}

// =====================================================================
// CONSULTA PRINCIPAL DEL LISTADO
// =====================================================================

/**
 * Construye WHERE, ORDER BY y parámetros a partir del array de filtros.
 * Retorna ['where' => string, 'params' => array].
 * $t es el alias de la tabla sec_salidas.
 */
function _sec_filtros_where($filtros, $t = 's') {
    $where = ['1=1'];
    $params = [];

    if (!empty($filtros['fecha_desde'])) {
        $where[] = "$t.fecha_salida >= :fecha_desde";
        $params[':fecha_desde'] = $filtros['fecha_desde'];
    }
    if (!empty($filtros['fecha_hasta'])) {
        $where[] = "$t.fecha_salida <= :fecha_hasta";
        $params[':fecha_hasta'] = $filtros['fecha_hasta'];
    }

    // Estados: array (multi-select) o string (compatibilidad con URL antigua)
    $estados = $filtros['estados'] ?? null;
    if (!empty($estados)) {
        if (!is_array($estados)) $estados = [$estados];
        $estados = array_filter($estados, fn($e) => in_array($e, [
            'pendiente_firma_entrega', 'en_ruta', 'cerrada', 'cerrada_con_devolucion', 'cancelada'
        ], true));
        if (!empty($estados)) {
            $placeholders = [];
            foreach ($estados as $i => $e) {
                $ph = ":estado_$i";
                $placeholders[] = $ph;
                $params[$ph] = $e;
            }
            $where[] = "$t.estado IN (" . implode(',', $placeholders) . ")";
        }
    } elseif (!empty($filtros['estado'])) {
        // Compatibilidad: filtro simple 'estado' de una sola opción
        $where[] = "$t.estado = :estado";
        $params[':estado'] = $filtros['estado'];
    }

    if (!empty($filtros['unidad_id'])) {
        $where[] = "$t.unidad_id = :unidad_id";
        $params[':unidad_id'] = (int) $filtros['unidad_id'];
    }

    if (!empty($filtros['creador_id'])) {
        $where[] = "$t.usuario_creador_id = :creador_id";
        $params[':creador_id'] = (int) $filtros['creador_id'];
    }

    if (!empty($filtros['empresa'])) {
        $where[] = "$t.id IN (SELECT sec_id FROM sec_lineas_empresa WHERE empresa_nombre LIKE :empresa)";
        $params[':empresa'] = '%' . $filtros['empresa'] . '%';
    }

    if (!empty($filtros['busqueda'])) {
        $where[] = "($t.folio LIKE :busq OR $t.solicita_nombre LIKE :busq2 OR $t.chofer_nombre LIKE :busq3)";
        $params[':busq']  = '%' . $filtros['busqueda'] . '%';
        $params[':busq2'] = '%' . $filtros['busqueda'] . '%';
        $params[':busq3'] = '%' . $filtros['busqueda'] . '%';
    }

    return ['where' => implode(' AND ', $where), 'params' => $params];
}

/**
 * SECs planas que cumplen filtros. Retorna arreglo con líneas anidadas por SEC.
 * Ordenadas por fecha_salida DESC, folio DESC.
 */
function obtener_sec_filtradas($filtros) {
    try {
        $pdo = conectarDB();
        $f = _sec_filtros_where($filtros, 's');

        $sql = "
            SELECT s.*,
                   uc.nombre_completo  AS creador_nombre,
                   v.numero            AS vuelta_numero,
                   ut.nombre           AS unidad_nombre,
                   ut.matricula        AS unidad_matricula
            FROM sec_salidas s
            LEFT JOIN usuarios uc            ON uc.id = s.usuario_creador_id
            LEFT JOIN sec_vueltas v          ON v.id  = s.vuelta_id
            LEFT JOIN unidades_transporte ut ON ut.id = s.unidad_id
            WHERE {$f['where']}
            ORDER BY s.fecha_salida DESC, s.folio DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($f['params']);
        $secs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($secs)) return [];

        // Cargar líneas de todas las SECs en una sola consulta
        $sec_ids = array_map(fn($s) => (int) $s['id'], $secs);
        $placeholders = implode(',', array_fill(0, count($sec_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT l.*,
                   e.nombre AS especificacion_nombre,
                   t.nombre AS tipo_nombre
            FROM sec_lineas_empresa l
            INNER JOIN sec_especificaciones e ON e.id = l.especificacion_id
            INNER JOIN sec_tipos_envase t     ON t.id = e.tipo_envase_id
            WHERE l.sec_id IN ($placeholders)
            ORDER BY l.sec_id ASC, l.id ASC
        ");
        $stmt->execute($sec_ids);
        $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar líneas por sec_id
        $lineas_por_sec = [];
        foreach ($lineas as $l) {
            $lineas_por_sec[(int) $l['sec_id']][] = $l;
        }

        foreach ($secs as &$sec) {
            $sec['lineas'] = $lineas_por_sec[(int) $sec['id']] ?? [];
        }
        unset($sec);

        return $secs;
    } catch (Exception $e) {
        error_log('obtener_sec_filtradas: ' . $e->getMessage());
        return [];
    }
}

/**
 * Mismo dataset que obtener_sec_filtradas, agrupado por fecha_salida
 * para el listado del dashboard (accordion por día).
 */
function obtener_sec_filtradas_agrupadas($filtros) {
    $secs = obtener_sec_filtradas($filtros);
    $agrupadas = [];
    foreach ($secs as $s) {
        $fecha = $s['fecha_salida'];
        $agrupadas[$fecha][] = $s;
    }
    return $agrupadas;
}

/**
 * Solo cuenta SECs para el contador del header.
 */
function contar_sec_filtradas($filtros) {
    try {
        $pdo = conectarDB();
        $f = _sec_filtros_where($filtros, 's');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sec_salidas s WHERE {$f['where']}");
        $stmt->execute($f['params']);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Devoluciones asociadas a las SECs filtradas (para la hoja "Devoluciones" del export).
 */
function obtener_devoluciones_de_sec_filtradas($filtros) {
    try {
        $pdo = conectarDB();
        $f = _sec_filtros_where($filtros, 's');
        $sql = "
            SELECT d.*,
                   s.folio AS sec_folio,
                   s.fecha_salida AS sec_fecha,
                   e.nombre AS especificacion_nombre,
                   t.nombre AS tipo_nombre,
                   u.nombre_completo AS registrado_por_nombre
            FROM sec_devoluciones d
            INNER JOIN sec_salidas s          ON s.id = d.sec_id
            INNER JOIN sec_especificaciones e ON e.id = d.especificacion_id
            INNER JOIN sec_tipos_envase t     ON t.id = e.tipo_envase_id
            LEFT  JOIN usuarios u             ON u.id = d.usuario_id
            WHERE {$f['where']}
            ORDER BY d.creado_en ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($f['params']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_devoluciones_de_sec_filtradas: ' . $e->getMessage());
        return [];
    }
}

// =====================================================================
// HELPERS DE UI
// =====================================================================

/**
 * Cuenta cuántos filtros están activos (para el badge del botón "Filtros").
 */
function contar_filtros_activos($filtros) {
    $n = 0;
    if (!empty($filtros['fecha_desde']))  $n++;
    if (!empty($filtros['fecha_hasta']))  $n++;
    if (!empty($filtros['busqueda']))     $n++;
    if (!empty($filtros['empresa']))      $n++;
    if (!empty($filtros['unidad_id']))    $n++;
    if (!empty($filtros['creador_id']))   $n++;
    $estados = $filtros['estados'] ?? [];
    if (!is_array($estados)) $estados = $estados ? [$estados] : [];
    if (!empty($estados)) $n++;
    return $n;
}

/**
 * Devuelve etiqueta legible y clase Bootstrap para cada estado.
 */
function estado_sec_meta($estado) {
    $map = [
        'pendiente_firma_entrega' => ['label' => 'Pendiente firma', 'clase' => 'warning',   'icono' => 'hourglass-split'],
        'en_ruta'                 => ['label' => 'En ruta',         'clase' => 'primary',   'icono' => 'truck'],
        'cerrada'                 => ['label' => 'Cerrada',         'clase' => 'success',   'icono' => 'check-circle-fill'],
        'cerrada_con_devolucion'  => ['label' => 'C/devolución',    'clase' => 'warning',   'icono' => 'arrow-return-left'],
        'cancelada'               => ['label' => 'Cancelada',       'clase' => 'danger',    'icono' => 'x-circle'],
    ];
    return $map[$estado] ?? ['label' => $estado, 'clase' => 'secondary', 'icono' => 'circle'];
}