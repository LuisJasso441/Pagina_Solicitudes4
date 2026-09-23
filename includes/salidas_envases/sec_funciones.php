<?php
/**
 * Funciones del módulo SEC — Salidas de Envases (refactor v2)
 * Ubicación: includes/salidas_envases/sec_funciones.php
 *
 * Estados:
 *   pendiente_firma_entrega      Creada, stock DESCONTADO, esperando firma Entrega
 *   en_ruta                      Firmada Entrega
 *   cerrada                      Cerrada (con o sin firma Recibe)
 *   cerrada_con_devolucion       Cerrada + devolución (Bloque 7)
 *   cancelada                    Cancelada (stock revertido)
 *
 * Reglas:
 *   Crear:   descuenta stock. Valida contra inventario Y contra capacidad de unidad.
 *   Editar:  solo en pendiente_firma_entrega. Revierte stock viejo + descuenta stock nuevo.
 *   Firma Entrega:  Almacén, mueve a en_ruta.
 *   Firma Recibe:   opcional, se puede firmar en en_ruta / cerrada / cerrada_con_devolucion.
 *                   NO cambia el estado. Se puede firmar antes o después del cierre.
 *   Cerrar:  solo en_ruta. Cierre manual sin firma de recibe.
 *   Cancelar: solo pendiente_firma_entrega. Revierte stock.
 */

require_once __DIR__ . '/../../config/database.php';
if (!defined('URL_BASE')) {
    require_once __DIR__ . '/../../config/config.php';
}
require_once __DIR__ . '/sec_historial_funciones.php';
require_once __DIR__ . '/inventario_funciones.php';

// =====================================================================
// FOLIO
// =====================================================================

function generar_folio_sec($fecha_documento) {
    try {
        $pdo = conectarDB();
        $ts = strtotime($fecha_documento);
        $prefijo = 'SEC-' . date('Ymd', $ts) . '-';

        $stmt = $pdo->prepare("
            SELECT folio FROM sec_salidas
            WHERE folio LIKE ?
            ORDER BY folio DESC LIMIT 1
        ");
        $stmt->execute([$prefijo . '%']);
        $ultimo = $stmt->fetchColumn();

        $siguiente = 1;
        if ($ultimo) {
            $partes = explode('-', $ultimo);
            $siguiente = (int) end($partes) + 1;
        }
        return $prefijo . str_pad($siguiente, 3, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log('generar_folio_sec: ' . $e->getMessage());
        return 'SEC-' . date('Ymd', strtotime($fecha_documento)) . '-001';
    }
}

// =====================================================================
// UTILIDADES DE ESTADO Y FORMATO
// =====================================================================

function info_estado_sec($estado) {
    $map = [
        'pendiente_firma_entrega' => ['bg-primary',             'Pendiente firma'],
        'en_ruta'                 => ['bg-info text-dark',      'En ruta'],
        'devoluciones_parciales'  => ['bg-warning text-dark',   'Devoluciones parciales'],
        'cerrada'                 => ['bg-success',             'Cerrada'],
        'cerrada_con_devolucion'  => ['bg-secondary',           'Cerrada c/devolución'],
        'cancelada'               => ['bg-dark',                'Cancelada'],
    ];
    return $map[$estado] ?? ['bg-light text-dark', $estado];
}

function sec_es_editable($sec) {
    return is_array($sec) && $sec['estado'] === 'pendiente_firma_entrega';
}

function sec_puede_firmar_entrega($sec) {
    return is_array($sec) && $sec['estado'] === 'pendiente_firma_entrega';
}

/**
 * Recibe se puede firmar en en_ruta, cerrada o cerrada_con_devolucion.
 * Solo si aún no está firmada. La firma NO cambia el estado.
 */
function sec_puede_firmar_recibe($sec) {
    if (!is_array($sec)) return false;
    if (!empty($sec['recibe_firma_svg'])) return false; // ya firmada
    return in_array($sec['estado'], ['en_ruta', 'devoluciones_parciales', 'cerrada', 'cerrada_con_devolucion'], true);
}

function sec_puede_cerrarse($sec) {
    return is_array($sec) && in_array($sec['estado'], ['en_ruta', 'devoluciones_parciales'], true);
}

function sec_es_cancelable($sec) {
    return is_array($sec) && $sec['estado'] === 'pendiente_firma_entrega';
}

function sec_fecha_larga_es($fecha) {
    $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $ts = strtotime($fecha);
    return $dias[(int) date('w', $ts)] . ', ' . (int) date('j', $ts)
         . ' de ' . $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

// =====================================================================
// CONSULTAS
// =====================================================================

function obtener_sec_por_id($id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT s.*,
                   u.nombre        AS unidad_nombre,
                   u.matricula     AS unidad_matricula,
                   v.numero        AS vuelta_numero,
                   uc.nombre_completo  AS creador_nombre,
                   ueg.nombre_completo AS entrega_user_nombre,
                   urc.nombre_completo AS recibe_user_nombre,
                   ucl.nombre_completo AS cerrada_por_nombre,
                   uca.nombre_completo AS cancelada_por_nombre
            FROM sec_salidas s
            INNER JOIN unidades_transporte u ON u.id  = s.unidad_id
            INNER JOIN sec_vueltas v         ON v.id  = s.vuelta_id
            LEFT  JOIN usuarios uc           ON uc.id = s.usuario_creador_id
            LEFT  JOIN usuarios ueg          ON ueg.id = s.entrega_usuario_id
            LEFT  JOIN usuarios urc          ON urc.id = s.recibe_usuario_id
            LEFT  JOIN usuarios ucl          ON ucl.id = s.cerrada_por
            LEFT  JOIN usuarios uca          ON uca.id = s.cancelada_por
            WHERE s.id = ?
        ");
        $stmt->execute([(int) $id]);
        $sec = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sec) return false;
        $sec['lineas'] = obtener_lineas_sec($id);
        return $sec;
    } catch (Exception $e) {
        error_log('obtener_sec_por_id: ' . $e->getMessage());
        return false;
    }
}

function obtener_lineas_sec($sec_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT l.*,
                   e.nombre AS especificacion_nombre,
                   e.activo AS especificacion_activa,
                   t.id     AS tipo_id,
                   t.nombre AS tipo_nombre
            FROM sec_lineas_empresa l
            INNER JOIN sec_especificaciones e ON e.id = l.especificacion_id
            INNER JOIN sec_tipos_envase t     ON t.id = e.tipo_envase_id
            WHERE l.sec_id = ?
            ORDER BY l.orden ASC, l.id ASC
        ");
        $stmt->execute([(int) $sec_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_lineas_sec: ' . $e->getMessage());
        return [];
    }
}

function obtener_sec_agrupadas_por_dia($filtros = []) {
    try {
        $pdo = conectarDB();
        $conds = [];
        $params = [];

        if (!empty($filtros['fecha_desde'])) { $conds[] = 's.fecha_salida >= ?'; $params[] = $filtros['fecha_desde']; }
        if (!empty($filtros['fecha_hasta'])) { $conds[] = 's.fecha_salida <= ?'; $params[] = $filtros['fecha_hasta']; }
        if (!empty($filtros['estado']))      { $conds[] = 's.estado = ?';        $params[] = $filtros['estado']; }
        if (!empty($filtros['busqueda'])) {
            $conds[] = '(s.folio LIKE ? OR s.solicita_nombre LIKE ? OR s.chofer_nombre LIKE ?)';
            $b = '%' . $filtros['busqueda'] . '%';
            $params[] = $b; $params[] = $b; $params[] = $b;
        }
        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

        $sql = "
            SELECT s.*,
                   u.nombre    AS unidad_nombre,
                   u.matricula AS unidad_matricula,
                   v.numero    AS vuelta_numero,
                   (SELECT COUNT(*) FROM sec_lineas_empresa WHERE sec_id = s.id) AS total_lineas
            FROM sec_salidas s
            INNER JOIN unidades_transporte u ON u.id = s.unidad_id
            INNER JOIN sec_vueltas v         ON v.id = s.vuelta_id
            {$where}
            ORDER BY s.fecha_salida DESC, s.creado_en DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $agrupadas = [];
        foreach ($filas as $sec) {
            $sec['lineas'] = obtener_lineas_sec($sec['id']);
            $agrupadas[$sec['fecha_salida']][] = $sec;
        }
        return $agrupadas;
    } catch (Exception $e) {
        error_log('obtener_sec_agrupadas_por_dia: ' . $e->getMessage());
        return [];
    }
}

function obtener_vueltas_disponibles($unidad_id, $fecha) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT v.id, v.numero, v.notas,
                   (SELECT COUNT(*) FROM sec_salidas s
                    WHERE s.vuelta_id = v.id
                      AND s.estado NOT IN ('cancelada')) AS total_secs
            FROM sec_vueltas v
            WHERE v.unidad_id = ? AND v.fecha = ?
            ORDER BY v.numero ASC
        ");
        $stmt->execute([(int) $unidad_id, $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_vueltas_disponibles: ' . $e->getMessage());
        return [];
    }
}

// =====================================================================
// VALIDACIÓN
// =====================================================================

function validar_stock_para_lineas($lineas) {
    $sumas = [];
    foreach ($lineas as $l) {
        $eid = (int) ($l['especificacion_id'] ?? 0);
        $cant = (int) ($l['cantidad'] ?? 0);
        if ($eid <= 0 || $cant <= 0) continue;
        $sumas[$eid] = ($sumas[$eid] ?? 0) + $cant;
    }

    $errores = [];
    $detalle = [];
    foreach ($sumas as $eid => $req) {
        $v = validar_stock_disponible($eid, $req);
        $detalle[$eid] = $v;
        if (!$v['suficiente']) {
            $errores[] = "Especificación #{$eid}: se piden {$req}, stock actual {$v['stock_actual']} (faltan {$v['faltante']}).";
        }
    }
    return ['ok' => empty($errores), 'errores' => $errores, 'detalle' => $detalle];
}

function validar_stock_edicion($lineas_nuevas, $lineas_viejas) {
    $sumas_viejas = [];
    foreach ($lineas_viejas as $l) {
        $eid = (int) $l['especificacion_id'];
        $sumas_viejas[$eid] = ($sumas_viejas[$eid] ?? 0) + (int) $l['cantidad'];
    }
    $sumas_nuevas = [];
    foreach ($lineas_nuevas as $l) {
        $eid = (int) ($l['especificacion_id'] ?? 0);
        $cant = (int) ($l['cantidad'] ?? 0);
        if ($eid <= 0 || $cant <= 0) continue;
        $sumas_nuevas[$eid] = ($sumas_nuevas[$eid] ?? 0) + $cant;
    }
    $errores = [];
    foreach ($sumas_nuevas as $eid => $req) {
        $stock_actual = obtener_stock_actual($eid);
        $stock_efectivo = $stock_actual + ($sumas_viejas[$eid] ?? 0);
        if ($req > $stock_efectivo) {
            $faltante = $req - $stock_efectivo;
            $errores[] = "Especificación #{$eid}: se piden {$req}, disponibles {$stock_efectivo} (faltan {$faltante}).";
        }
    }
    return ['ok' => empty($errores), 'errores' => $errores];
}

/**
 * Valida capacidades de la unidad seleccionada.
 * - Suma por especificación entre líneas de esta SEC (opción B).
 * - Si especificación NO está en unidades_capacidades → rechaza (opción A).
 */
function validar_capacidades_para_lineas($lineas, $unidad_id) {
    if (empty($lineas)) return ['ok' => true, 'errores' => []];

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT uc.especificacion_id, uc.capacidad_maxima,
                   e.nombre AS especificacion_nombre,
                   t.nombre AS tipo_nombre
            FROM unidades_capacidades uc
            INNER JOIN sec_especificaciones e ON e.id = uc.especificacion_id
            INNER JOIN sec_tipos_envase t     ON t.id = e.tipo_envase_id
            WHERE uc.unidad_id = ?
        ");
        $stmt->execute([(int) $unidad_id]);
        $capacidades = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $capacidades[(int) $row['especificacion_id']] = [
                'capacidad' => (int) $row['capacidad_maxima'],
                'label'     => $row['tipo_nombre'] . ' · ' . $row['especificacion_nombre'],
            ];
        }
        $sumas = [];
        foreach ($lineas as $l) {
            $eid  = (int) ($l['especificacion_id'] ?? 0);
            $cant = (int) ($l['cantidad'] ?? 0);
            if ($eid <= 0 || $cant <= 0) continue;
            $sumas[$eid] = ($sumas[$eid] ?? 0) + $cant;
        }
        $errores = [];
        foreach ($sumas as $eid => $total) {
            if (!isset($capacidades[$eid])) {
                $errores[] = "Especificación #{$eid}: la unidad no está configurada para transportar este envase. Configúrala en Unidades de Transporte.";
                continue;
            }
            $cap = $capacidades[$eid];
            if ($total > $cap['capacidad']) {
                $exceso = $total - $cap['capacidad'];
                $errores[] = "{$cap['label']}: se piden {$total}, capacidad de la unidad {$cap['capacidad']} (excede en {$exceso}).";
            }
        }
        return ['ok' => empty($errores), 'errores' => $errores];
    } catch (Exception $e) {
        error_log('validar_capacidades_para_lineas: ' . $e->getMessage());
        return ['ok' => false, 'errores' => ['Error al validar capacidades de la unidad.']];
    }
}

function validar_lineas_sec($lineas) {
    $errores = [];
    $limpias = [];
    if (!is_array($lineas) || count($lineas) === 0) {
        return ['ok' => false, 'errores' => ['Debe agregar al menos una línea (empresa destino).']];
    }
    $orden = 0;
    foreach ($lineas as $idx => $l) {
        $empresa = trim($l['empresa_nombre'] ?? '');
        $eid     = (int) ($l['especificacion_id'] ?? 0);
        $cant    = (int) ($l['cantidad'] ?? 0);
        $cond    = trim($l['condiciones_envase'] ?? '');
        $n       = $idx + 1;
        if ($empresa === '')      { $errores[] = "Línea #{$n}: la empresa destino es obligatoria."; continue; }
        if (mb_strlen($empresa) > 200) { $errores[] = "Línea #{$n}: nombre de empresa demasiado largo (máx 200)."; continue; }
        if ($eid <= 0)            { $errores[] = "Línea #{$n}: falta seleccionar la especificación."; continue; }
        if ($cant <= 0)           { $errores[] = "Línea #{$n}: la cantidad debe ser mayor a 0."; continue; }
        $limpias[] = [
            'orden'              => $orden++,
            'empresa_nombre'     => $empresa,
            'especificacion_id'  => $eid,
            'cantidad'           => $cant,
            'condiciones_envase' => $cond !== '' ? $cond : null,
        ];
    }
    return ['ok' => empty($errores), 'errores' => $errores, 'lineas_limpias' => $limpias];
}

// =====================================================================
// CREAR
// =====================================================================

function crear_sec($datos, $lineas, $usuario_id) {
    $unidad_id    = (int) ($datos['unidad_id'] ?? 0);
    $vuelta_id    = (int) ($datos['vuelta_id'] ?? 0);
    $fecha_salida = trim($datos['fecha_salida'] ?? '');

    if ($unidad_id <= 0)  return ['success' => false, 'errores' => ['Debe seleccionar una unidad.']];
    if ($vuelta_id <= 0)  return ['success' => false, 'errores' => ['Debe seleccionar una vuelta.']];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_salida)) {
        return ['success' => false, 'errores' => ['Fecha inválida.']];
    }

    try {
        $pdo_check = conectarDB();
        $stmt = $pdo_check->prepare("SELECT unidad_id, fecha FROM sec_vueltas WHERE id = ?");
        $stmt->execute([$vuelta_id]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$v) return ['success' => false, 'errores' => ['La vuelta seleccionada no existe.']];
        if ((int) $v['unidad_id'] !== $unidad_id) return ['success' => false, 'errores' => ['La vuelta no pertenece a la unidad seleccionada.']];
        if ($v['fecha'] !== $fecha_salida)        return ['success' => false, 'errores' => ['La vuelta no corresponde a la fecha seleccionada.']];
    } catch (Exception $e) {
        error_log('crear_sec (verificar vuelta): ' . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al validar la vuelta.']];
    }

    $v_lineas = validar_lineas_sec($lineas);
    if (!$v_lineas['ok']) return ['success' => false, 'errores' => $v_lineas['errores']];
    $lineas_limpias = $v_lineas['lineas_limpias'];

    $v_stock = validar_stock_para_lineas($lineas_limpias);
    if (!$v_stock['ok']) return ['success' => false, 'errores' => $v_stock['errores']];

    $v_cap = validar_capacidades_para_lineas($lineas_limpias, $unidad_id);
    if (!$v_cap['ok']) return ['success' => false, 'errores' => $v_cap['errores']];

    $sumas = [];
    foreach ($lineas_limpias as $l) {
        $eid = (int) $l['especificacion_id'];
        $sumas[$eid] = ($sumas[$eid] ?? 0) + (int) $l['cantidad'];
    }

    $pdo = null;
    $intentos = 0;
    $max_intentos = 5;
    while ($intentos < $max_intentos) {
        $intentos++;
        try {
            $pdo = conectarDB();
            $pdo->beginTransaction();

            $folio = generar_folio_sec($fecha_salida);
            $stmt = $pdo->prepare("
                INSERT INTO sec_salidas (
                    folio, unidad_id, vuelta_id, fecha_salida,
                    hora_inicio_ruta, hora_termino_ruta,
                    chofer_nombre, solicita_nombre, notas_generales,
                    estado, usuario_creador_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente_firma_entrega', ?)
            ");
            $stmt->execute([
                $folio, $unidad_id, $vuelta_id, $fecha_salida,
                !empty($datos['hora_inicio_ruta'])   ? $datos['hora_inicio_ruta']   : null,
                !empty($datos['hora_termino_ruta'])  ? $datos['hora_termino_ruta']  : null,
                !empty($datos['chofer_nombre'])      ? trim($datos['chofer_nombre'])   : null,
                !empty($datos['solicita_nombre'])    ? trim($datos['solicita_nombre']) : null,
                !empty($datos['notas_generales'])    ? trim($datos['notas_generales']) : null,
                (int) $usuario_id,
            ]);
            $sec_id = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO sec_lineas_empresa
                    (sec_id, orden, empresa_nombre, especificacion_id, cantidad, condiciones_envase)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($lineas_limpias as $l) {
                $stmt->execute([$sec_id, $l['orden'], $l['empresa_nombre'], $l['especificacion_id'], $l['cantidad'], $l['condiciones_envase']]);
            }

            foreach ($sumas as $eid => $cant) {
                $res = registrar_movimiento([
                    'especificacion_id' => $eid,
                    'tipo_movimiento'   => 'salida',
                    'cantidad'          => $cant,
                    'motivo'            => "Salida por SEC {$folio}",
                    'usuario_id'        => (int) $usuario_id,
                    'referencia_tipo'   => 'SEC',
                    'referencia_id'     => $sec_id,
                ]);
                if (!$res['ok']) {
                    $pdo->rollBack();
                    return ['success' => false, 'errores' => ['Error de inventario: ' . $res['msg']]];
                }
            }

            $pdo->commit();

            registrar_historial_sec($sec_id, $usuario_id, 'sec_creada', "SEC {$folio} creada. Stock descontado.", [
                'folio' => $folio, 'unidad_id' => $unidad_id, 'vuelta_id' => $vuelta_id,
                'total_lineas' => count($lineas_limpias), 'sumas_por_espec' => $sumas,
            ]);
            return ['success' => true, 'sec_id' => $sec_id, 'folio' => $folio, 'errores' => []];
        } catch (PDOException $e) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() === '23000' && $intentos < $max_intentos) {
                usleep(random_int(50000, 150000));
                continue;
            }
            error_log('crear_sec: ' . $e->getMessage());
            return ['success' => false, 'errores' => ['Error al crear la SEC. Intente de nuevo.']];
        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            error_log('crear_sec: ' . $e->getMessage());
            return ['success' => false, 'errores' => ['Error al crear la SEC.']];
        }
    }
    return ['success' => false, 'errores' => ['No se pudo generar un folio único.']];
}

// =====================================================================
// ACTUALIZAR
// =====================================================================

function actualizar_sec($sec_id, $datos, $lineas, $usuario_id) {
    $sec = obtener_sec_por_id($sec_id);
    if (!$sec) return ['success' => false, 'errores' => ['La SEC no existe.']];
    if (!sec_es_editable($sec)) {
        return ['success' => false, 'errores' => ['Solo se puede editar mientras esté pendiente de firma.']];
    }

    $unidad_id    = (int) ($datos['unidad_id'] ?? $sec['unidad_id']);
    $vuelta_id    = (int) ($datos['vuelta_id'] ?? $sec['vuelta_id']);
    $fecha_salida = trim($datos['fecha_salida'] ?? $sec['fecha_salida']);

    if ($unidad_id <= 0)  return ['success' => false, 'errores' => ['Unidad inválida.']];
    if ($vuelta_id <= 0)  return ['success' => false, 'errores' => ['Vuelta inválida.']];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_salida)) {
        return ['success' => false, 'errores' => ['Fecha inválida.']];
    }

    try {
        $pdo_check = conectarDB();
        $stmt = $pdo_check->prepare("SELECT unidad_id, fecha FROM sec_vueltas WHERE id = ?");
        $stmt->execute([$vuelta_id]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$v) return ['success' => false, 'errores' => ['La vuelta seleccionada no existe.']];
        if ((int) $v['unidad_id'] !== $unidad_id) return ['success' => false, 'errores' => ['La vuelta no pertenece a la unidad.']];
        if ($v['fecha'] !== $fecha_salida) return ['success' => false, 'errores' => ['La vuelta no corresponde a la fecha.']];
    } catch (Exception $e) {
        error_log('actualizar_sec (verificar vuelta): ' . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al validar la vuelta.']];
    }

    $v_lineas = validar_lineas_sec($lineas);
    if (!$v_lineas['ok']) return ['success' => false, 'errores' => $v_lineas['errores']];
    $lineas_limpias = $v_lineas['lineas_limpias'];

    $v_stock = validar_stock_edicion($lineas_limpias, $sec['lineas']);
    if (!$v_stock['ok']) return ['success' => false, 'errores' => $v_stock['errores']];

    $v_cap = validar_capacidades_para_lineas($lineas_limpias, $unidad_id);
    if (!$v_cap['ok']) return ['success' => false, 'errores' => $v_cap['errores']];

    $sumas_viejas = [];
    foreach ($sec['lineas'] as $l) {
        $eid = (int) $l['especificacion_id'];
        $sumas_viejas[$eid] = ($sumas_viejas[$eid] ?? 0) + (int) $l['cantidad'];
    }
    $sumas_nuevas = [];
    foreach ($lineas_limpias as $l) {
        $eid = (int) $l['especificacion_id'];
        $sumas_nuevas[$eid] = ($sumas_nuevas[$eid] ?? 0) + (int) $l['cantidad'];
    }

    $pdo = null;
    try {
        $pdo = conectarDB();
        $pdo->beginTransaction();
        $folio = $sec['folio'];

        foreach ($sumas_viejas as $eid => $cant) {
            $res = registrar_movimiento([
                'especificacion_id' => $eid, 'tipo_movimiento' => 'entrada', 'cantidad' => $cant,
                'motivo' => "Reversión por edición de SEC {$folio}", 'usuario_id' => (int) $usuario_id,
                'referencia_tipo' => 'SEC_EDICION_REVERSION', 'referencia_id' => (int) $sec_id,
            ]);
            if (!$res['ok']) { $pdo->rollBack(); return ['success' => false, 'errores' => ['Error al revertir stock: ' . $res['msg']]]; }
        }

        $stmt = $pdo->prepare("
            UPDATE sec_salidas SET
                unidad_id = ?, vuelta_id = ?, fecha_salida = ?,
                hora_inicio_ruta = ?, hora_termino_ruta = ?,
                chofer_nombre = ?, solicita_nombre = ?, notas_generales = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $unidad_id, $vuelta_id, $fecha_salida,
            !empty($datos['hora_inicio_ruta'])   ? $datos['hora_inicio_ruta']   : null,
            !empty($datos['hora_termino_ruta'])  ? $datos['hora_termino_ruta']  : null,
            !empty($datos['chofer_nombre'])      ? trim($datos['chofer_nombre'])   : null,
            !empty($datos['solicita_nombre'])    ? trim($datos['solicita_nombre']) : null,
            !empty($datos['notas_generales'])    ? trim($datos['notas_generales']) : null,
            (int) $sec_id,
        ]);

        $stmt = $pdo->prepare("DELETE FROM sec_lineas_empresa WHERE sec_id = ?");
        $stmt->execute([(int) $sec_id]);

        $stmt = $pdo->prepare("
            INSERT INTO sec_lineas_empresa (sec_id, orden, empresa_nombre, especificacion_id, cantidad, condiciones_envase)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($lineas_limpias as $l) {
            $stmt->execute([(int) $sec_id, $l['orden'], $l['empresa_nombre'], $l['especificacion_id'], $l['cantidad'], $l['condiciones_envase']]);
        }

        foreach ($sumas_nuevas as $eid => $cant) {
            $res = registrar_movimiento([
                'especificacion_id' => $eid, 'tipo_movimiento' => 'salida', 'cantidad' => $cant,
                'motivo' => "Salida por SEC {$folio} (edición)", 'usuario_id' => (int) $usuario_id,
                'referencia_tipo' => 'SEC', 'referencia_id' => (int) $sec_id,
            ]);
            if (!$res['ok']) { $pdo->rollBack(); return ['success' => false, 'errores' => ['Error al descontar stock: ' . $res['msg']]]; }
        }

        $pdo->commit();
        registrar_historial_sec($sec_id, $usuario_id, 'sec_editada', "SEC editada. Stock ajustado.",
            ['sumas_viejas' => $sumas_viejas, 'sumas_nuevas' => $sumas_nuevas]);
        return ['success' => true, 'sec_id' => (int) $sec_id, 'folio' => $folio, 'errores' => []];
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        error_log('actualizar_sec: ' . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al actualizar la SEC.']];
    }
}

// =====================================================================
// TRANSICIONES DE ESTADO
// =====================================================================

function firmar_entrega_sec($sec_id, $nombre, $firma_svg, $usuario_id) {
    $sec = obtener_sec_por_id($sec_id);
    if (!$sec) return ['success' => false, 'errores' => ['La SEC no existe.']];
    if (!sec_puede_firmar_entrega($sec)) {
        return ['success' => false, 'errores' => ['La SEC no está en estado Pendiente de firma.']];
    }
    $nombre = trim($nombre);
    if ($nombre === '')    return ['success' => false, 'errores' => ['El nombre de quien firma es obligatorio.']];
    if (empty($firma_svg)) return ['success' => false, 'errores' => ['Falta la firma.']];

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            UPDATE sec_salidas
            SET estado = 'en_ruta',
                entrega_usuario_id = ?,
                entrega_nombre = ?,
                entrega_firma_svg = ?,
                entrega_firmada_en = NOW()
            WHERE id = ?
        ");
        $stmt->execute([(int) $usuario_id, $nombre, $firma_svg, (int) $sec_id]);
        registrar_historial_sec($sec_id, $usuario_id, 'entrega_firmada', "Firma de entrega por {$nombre}. SEC en ruta.");
        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        error_log('firmar_entrega_sec: ' . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al registrar la firma.']];
    }
}

/**
 * Firma de recibe. NO cambia el estado.
 * Se puede firmar en en_ruta / cerrada / cerrada_con_devolucion,
 * siempre que no esté firmada aún.
 */
function firmar_recibe_sec($sec_id, $nombre, $firma_svg, $usuario_id = null) {
    $sec = obtener_sec_por_id($sec_id);
    if (!$sec) return ['success' => false, 'errores' => ['La SEC no existe.']];
    if (!sec_puede_firmar_recibe($sec)) {
        return ['success' => false, 'errores' => ['La SEC no está en un estado que permita firma de recibe, o ya fue firmada.']];
    }
    $nombre = trim($nombre);
    if ($nombre === '')    return ['success' => false, 'errores' => ['El nombre de quien recibe es obligatorio.']];
    if (empty($firma_svg)) return ['success' => false, 'errores' => ['Falta la firma.']];

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            UPDATE sec_salidas
            SET recibe_usuario_id = ?,
                recibe_nombre = ?,
                recibe_firma_svg = ?,
                recibe_firmada_en = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$usuario_id ? (int) $usuario_id : null, $nombre, $firma_svg, (int) $sec_id]);
        registrar_historial_sec($sec_id, $usuario_id, 'recibe_firmada', "Firma de recibe por {$nombre}.");
        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        error_log('firmar_recibe_sec: ' . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al registrar la firma.']];
    }
}

function cerrar_sec($sec_id, $usuario_id) {
    $sec = obtener_sec_por_id($sec_id);
    if (!$sec) return ['success' => false, 'errores' => ['La SEC no existe.']];
    if (!sec_puede_cerrarse($sec)) {
        return ['success' => false, 'errores' => ['La SEC no está en un estado que permita cierre.']];
    }
    try {
        $pdo = conectarDB();

        // Determinar estado final según si hubo devoluciones
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sec_devoluciones WHERE sec_id = ?");
        $stmt->execute([(int) $sec_id]);
        $tiene_devoluciones = (int) $stmt->fetchColumn() > 0;
        $estado_final = $tiene_devoluciones ? 'cerrada_con_devolucion' : 'cerrada';

        $stmt = $pdo->prepare("
            UPDATE sec_salidas SET estado = ?, cerrada_por = ?, cerrada_en = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$estado_final, (int) $usuario_id, (int) $sec_id]);

        $desc = $tiene_devoluciones
            ? 'SEC cerrada manualmente (con devoluciones registradas).'
            : 'SEC cerrada manualmente.';
        registrar_historial_sec($sec_id, $usuario_id, 'sec_cerrada', $desc);
        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        error_log('cerrar_sec: ' . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al cerrar la SEC.']];
    }
}

function cancelar_sec($sec_id, $motivo, $usuario_id) {
    $sec = obtener_sec_por_id($sec_id);
    if (!$sec) return ['success' => false, 'errores' => ['La SEC no existe.']];
    if (!sec_es_cancelable($sec)) {
        return ['success' => false, 'errores' => ['La SEC no se puede cancelar en su estado actual.']];
    }
    $motivo = trim($motivo);
    if ($motivo === '') return ['success' => false, 'errores' => ['El motivo de cancelación es obligatorio.']];

    $pdo = null;
    try {
        $pdo = conectarDB();
        $pdo->beginTransaction();
        $sumas = [];
        foreach ($sec['lineas'] as $l) {
            $eid = (int) $l['especificacion_id'];
            $sumas[$eid] = ($sumas[$eid] ?? 0) + (int) $l['cantidad'];
        }
        foreach ($sumas as $eid => $cant) {
            $res = registrar_movimiento([
                'especificacion_id' => $eid, 'tipo_movimiento' => 'entrada', 'cantidad' => $cant,
                'motivo' => "Reversión por cancelación de SEC {$sec['folio']}",
                'usuario_id' => (int) $usuario_id,
                'referencia_tipo' => 'SEC_CANCELADA', 'referencia_id' => (int) $sec_id,
            ]);
            if (!$res['ok']) { $pdo->rollBack(); return ['success' => false, 'errores' => ['Error al revertir stock: ' . $res['msg']]]; }
        }
        $stmt = $pdo->prepare("
            UPDATE sec_salidas SET estado = 'cancelada', cancelada_por = ?, cancelada_en = NOW(), cancelacion_motivo = ?
            WHERE id = ?
        ");
        $stmt->execute([(int) $usuario_id, $motivo, (int) $sec_id]);
        $pdo->commit();
        registrar_historial_sec($sec_id, $usuario_id, 'sec_cancelada',
            'SEC cancelada. Motivo: ' . $motivo . ' (stock revertido)',
            ['motivo' => $motivo]);
        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        error_log('cancelar_sec: ' . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al cancelar la SEC.']];
    }
}

// =====================================================================
// NOTIFICACIONES
// =====================================================================

function _sec_usuarios_destino($pdo, $codigos_depto) {
    $ph = implode(',', array_fill(0, count($codigos_depto), '?'));
    $stmt = $pdo->prepare("
        SELECT u.id FROM usuarios u
        INNER JOIN departamentos d ON d.id = u.departamento_id
        WHERE d.codigo IN ({$ph}) AND u.activo = 1
    ");
    $stmt->execute($codigos_depto);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function _sec_insertar_notificaciones($pdo, $usuarios_ids, $tipo, $titulo, $mensaje, $sec_id, $folio) {
    if (empty($usuarios_ids)) return;
    $url = (defined('URL_BASE') ? URL_BASE : '/') . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . (int) $sec_id;
    $datos_json = json_encode(['sec_id' => (int) $sec_id, 'folio' => $folio, 'url' => $url], JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare("
        INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, fecha_creacion)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    foreach (array_unique(array_map('intval', $usuarios_ids)) as $uid) {
        try { $stmt->execute([$tipo, $titulo, $mensaje, $uid, $datos_json]); }
        catch (Exception $e) { error_log("Notif SEC uid={$uid}: " . $e->getMessage()); }
    }
}

function notificar_sec_creada($sec) {
    try {
        $pdo = conectarDB();
        $usuarios = _sec_usuarios_destino($pdo, ['almacen_residuos']);
        _sec_insertar_notificaciones($pdo, $usuarios, 'sec_pendiente_firma',
            "📦 SEC {$sec['folio']} pendiente de firma",
            "Unidad {$sec['unidad_nombre']} · Vuelta {$sec['vuelta_numero']}",
            $sec['id'], $sec['folio']);
    } catch (Exception $e) { error_log('notificar_sec_creada: ' . $e->getMessage()); }
}

function notificar_sec_firmada_entrega($sec) {
    try {
        $pdo = conectarDB();
        $ids = [];
        if (!empty($sec['usuario_creador_id'])) $ids[] = (int) $sec['usuario_creador_id'];
        $ids = array_merge($ids, _sec_usuarios_destino($pdo, ['logistica', 'ventas']));
        _sec_insertar_notificaciones($pdo, $ids, 'sec_firma_entrega',
            "✍️ SEC {$sec['folio']} firmada (Entrega)",
            "Firmó: " . ($sec['entrega_nombre'] ?? '—') . '. En ruta.',
            $sec['id'], $sec['folio']);
    } catch (Exception $e) { error_log('notificar_sec_firmada_entrega: ' . $e->getMessage()); }
}

function notificar_sec_firmada_recibe($sec) {
    try {
        $pdo = conectarDB();
        $ids = [];
        if (!empty($sec['usuario_creador_id']))  $ids[] = (int) $sec['usuario_creador_id'];
        if (!empty($sec['entrega_usuario_id']))  $ids[] = (int) $sec['entrega_usuario_id'];
        $ids = array_merge($ids, _sec_usuarios_destino($pdo, ['logistica', 'ventas']));
        _sec_insertar_notificaciones($pdo, $ids, 'sec_firma_recibe',
            "✍️ SEC {$sec['folio']} firmada por recibe",
            "Firmó: " . ($sec['recibe_nombre'] ?? '—'),
            $sec['id'], $sec['folio']);
    } catch (Exception $e) { error_log('notificar_sec_firmada_recibe: ' . $e->getMessage()); }
}

function notificar_sec_cerrada($sec) {
    try {
        $pdo = conectarDB();
        $ids = [];
        if (!empty($sec['usuario_creador_id']))  $ids[] = (int) $sec['usuario_creador_id'];
        if (!empty($sec['entrega_usuario_id']))  $ids[] = (int) $sec['entrega_usuario_id'];
        _sec_insertar_notificaciones($pdo, $ids, 'sec_cerrada',
            "🔒 SEC {$sec['folio']} cerrada",
            'Cierre manual.', $sec['id'], $sec['folio']);
    } catch (Exception $e) { error_log('notificar_sec_cerrada: ' . $e->getMessage()); }
}

function notificar_sec_cancelada($sec) {
    try {
        $pdo = conectarDB();
        $ids = [];
        if (!empty($sec['usuario_creador_id']))  $ids[] = (int) $sec['usuario_creador_id'];
        if (!empty($sec['entrega_usuario_id']))  $ids[] = (int) $sec['entrega_usuario_id'];
        $ids = array_merge($ids, _sec_usuarios_destino($pdo, ['logistica', 'almacen_residuos', 'ventas']));
        _sec_insertar_notificaciones($pdo, $ids, 'sec_cancelada',
            "❌ SEC {$sec['folio']} cancelada",
            'Motivo: ' . ($sec['cancelacion_motivo'] ?? '—'),
            $sec['id'], $sec['folio']);
    } catch (Exception $e) { error_log('notificar_sec_cancelada: ' . $e->getMessage()); }
}

/**
 * Notificar registro de devolución a Logística, Ventas y Almacén.
 * @param array $sec        La SEC completa
 * @param array $devolucion Datos de la devolución recién creada
 *                          (debe incluir: cantidad_devuelta, empresa_nombre, motivo)
 */
function notificar_devolucion_registrada($sec, $devolucion) {
    try {
        $pdo = conectarDB();
        $ids = [];
        if (!empty($sec['usuario_creador_id']))  $ids[] = (int) $sec['usuario_creador_id'];
        if (!empty($sec['entrega_usuario_id']))  $ids[] = (int) $sec['entrega_usuario_id'];
        $ids = array_merge($ids, _sec_usuarios_destino($pdo, ['logistica', 'ventas', 'almacen_residuos']));

        $cant    = (int) ($devolucion['cantidad_devuelta'] ?? 0);
        $empresa = $devolucion['empresa_nombre'] ?? '—';
        _sec_insertar_notificaciones($pdo, $ids, 'sec_devolucion',
            "↩️ Devolución en SEC {$sec['folio']}",
            "{$cant} × {$empresa}",
            $sec['id'], $sec['folio']);
    } catch (Exception $e) { error_log('notificar_devolucion_registrada: ' . $e->getMessage()); }
}