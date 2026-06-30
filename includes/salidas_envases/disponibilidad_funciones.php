<?php
/**
 * Funciones del módulo de Disponibilidad de Unidades de Transporte
 * Parte del módulo SEC - Salidas de Envases para Clientes
 *
 * Ubicación: includes/salidas_envases/disponibilidad_funciones.php
 *
 * CAMBIO IMPORTANTE: cada slot es un RANGO (hora_inicio + hora_fin), no un punto.
 *
 * Logística: CRUD completo
 * Ventas / Almacén de Residuos: solo lectura
 */

require_once __DIR__ . '/../../config/database.php';

// ====================================================================
// HELPERS DE NORMALIZACIÓN
// ====================================================================

/**
 * Normaliza "HH:MM" a "HH:MM:SS" (idempotente)
 */
function _norm_hora($h) {
    $h = trim((string)$h);
    if (preg_match('/^\d{2}:\d{2}$/', $h)) return $h . ':00';
    return $h;
}

/**
 * Color consistente por unidad (basado en su ID)
 */
function color_para_unidad($unidad_id) {
    $colores = [
        '#14b8a6', '#3b82f6', '#8b5cf6', '#ec4899',
        '#f59e0b', '#10b981', '#6366f1', '#ef4444',
        '#06b6d4', '#d946ef', '#f97316', '#0ea5e9'
    ];
    return $colores[((int)$unidad_id) % count($colores)];
}

// ====================================================================
// CONSULTAS
// ====================================================================

/**
 * Obtener bloques de disponibilidad en un rango de fechas
 */
function obtener_disponibilidades_rango($fecha_desde, $fecha_hasta, $unidad_id = null) {
    try {
        $pdo = conectarDB();
        $sql = "
            SELECT d.*,
                   u.nombre AS unidad_nombre,
                   u.placas AS unidad_placas
            FROM sec_disponibilidad_unidades d
            INNER JOIN unidades_transporte u ON u.id = d.unidad_transporte_id
            WHERE d.fecha BETWEEN ? AND ?
        ";
        $params = [$fecha_desde, $fecha_hasta];

        if ($unidad_id) {
            $sql .= " AND d.unidad_transporte_id = ?";
            $params[] = (int)$unidad_id;
        }

        $sql .= " ORDER BY d.fecha ASC, d.hora_inicio_ruta ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $disponibilidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($disponibilidades as &$d) {
            $d['slots'] = obtener_slots_disponibilidad($d['id']);
            $d['total_slots']    = count($d['slots']);
            $d['slots_ocupados'] = 0;
            $d['slots_libres']   = 0;
            foreach ($d['slots'] as $s) {
                if ($s['ocupado']) $d['slots_ocupados']++;
                else               $d['slots_libres']++;
            }
        }

        return $disponibilidades;
    } catch (Exception $e) {
        error_log("Error obtener_disponibilidades_rango: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener una disponibilidad por su ID, con slots
 */
function obtener_disponibilidad_por_id($id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT d.*,
                   u.nombre AS unidad_nombre,
                   u.placas AS unidad_placas
            FROM sec_disponibilidad_unidades d
            INNER JOIN unidades_transporte u ON u.id = d.unidad_transporte_id
            WHERE d.id = ?
        ");
        $stmt->execute([(int)$id]);
        $disponibilidad = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$disponibilidad) return false;

        $disponibilidad['slots'] = obtener_slots_disponibilidad($disponibilidad['id']);
        return $disponibilidad;
    } catch (Exception $e) {
        error_log("Error obtener_disponibilidad_por_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener slots de una disponibilidad ordenados por hora_inicio
 */
function obtener_slots_disponibilidad($disponibilidad_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT e.*,
                   s.folio AS sec_folio
            FROM sec_espacios_disponibles e
            LEFT JOIN salidas_envases_clientes s ON s.id = e.sec_id
            WHERE e.disponibilidad_id = ?
            ORDER BY e.hora_inicio ASC
        ");
        $stmt->execute([(int)$disponibilidad_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obtener_slots_disponibilidad: " . $e->getMessage());
        return [];
    }
}

/**
 * Solapamiento entre bloques (sin cambio en signatura)
 */
function existe_solapamiento_disponibilidad($unidad_id, $fecha, $hora_inicio, $hora_termino, $excluir_id = null) {
    try {
        $pdo = conectarDB();
        $sql = "
            SELECT COUNT(*) FROM sec_disponibilidad_unidades
            WHERE unidad_transporte_id = ?
              AND fecha = ?
              AND NOT (hora_termino_ruta <= ? OR hora_inicio_ruta >= ?)
        ";
        $params = [(int)$unidad_id, $fecha, $hora_inicio, $hora_termino];

        if ($excluir_id) {
            $sql .= " AND id <> ?";
            $params[] = (int)$excluir_id;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Exception $e) {
        error_log("Error existe_solapamiento_disponibilidad: " . $e->getMessage());
        return false;
    }
}

// ====================================================================
// VALIDACIÓN
// ====================================================================

/**
 * Validar datos de una disponibilidad
 * Espera: slots_inicio[], slots_fin[] (arrays paralelos)
 */
function validar_disponibilidad($datos) {
    $errores = [];

    $unidad_id = (int)($datos['unidad_transporte_id'] ?? 0);
    if ($unidad_id <= 0) {
        $errores[] = 'Debes seleccionar una unidad de transporte.';
    }

    $fecha = trim($datos['fecha'] ?? '');
    if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $errores[] = 'La fecha es obligatoria (formato YYYY-MM-DD).';
    }

    $hi = _norm_hora($datos['hora_inicio_ruta']  ?? '');
    $hf = _norm_hora($datos['hora_termino_ruta'] ?? '');

    if ($hi === '' || $hf === '') {
        $errores[] = 'Las horas de inicio y término de ruta son obligatorias.';
    } elseif ($hi >= $hf) {
        $errores[] = 'La hora de inicio de ruta debe ser menor que la hora de término.';
    }

    $slots_inicio = $datos['slots_inicio'] ?? [];
    $slots_fin    = $datos['slots_fin']    ?? [];

    if (!is_array($slots_inicio) || !is_array($slots_fin)) {
        $errores[] = 'Datos de espacios disponibles inválidos.';
        return $errores;
    }
    if (count($slots_inicio) !== count($slots_fin)) {
        $errores[] = 'La cantidad de horas inicio y fin de los espacios no coincide.';
        return $errores;
    }
    if (count($slots_inicio) === 0) {
        $errores[] = 'Debes registrar al menos un espacio disponible.';
        return $errores;
    }

    $pares = [];
    foreach ($slots_inicio as $i => $si_raw) {
        $sf_raw = $slots_fin[$i] ?? '';
        $si = _norm_hora($si_raw);
        $sf = _norm_hora($sf_raw);

        if (trim($si_raw) === '' || trim($sf_raw) === '') {
            $errores[] = "Espacio #" . ($i + 1) . ": falta hora inicio o hora fin.";
            continue;
        }
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $si) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $sf)) {
            $errores[] = "Espacio #" . ($i + 1) . ": formato de hora inválido (usa HH:MM).";
            continue;
        }
        if ($si >= $sf) {
            $errores[] = "Espacio #" . ($i + 1) . ": la hora inicio ($si) debe ser menor que la hora fin ($sf).";
            continue;
        }
        if ($hi !== '' && $hf !== '' && ($si < $hi || $sf > $hf)) {
            $errores[] = "Espacio #" . ($i + 1) . " ($si - $sf) está fuera del rango de la ruta ($hi - $hf).";
            continue;
        }
        $pares[] = ['inicio' => $si, 'fin' => $sf, 'idx' => $i + 1];
    }

    // Solapamiento entre slots del mismo bloque
    for ($a = 0; $a < count($pares); $a++) {
        for ($b = $a + 1; $b < count($pares); $b++) {
            $pa = $pares[$a]; $pb = $pares[$b];
            if (!($pa['fin'] <= $pb['inicio'] || $pa['inicio'] >= $pb['fin'])) {
                $errores[] = "Los espacios #{$pa['idx']} y #{$pb['idx']} se solapan.";
            }
        }
    }

    return $errores;
}

// ====================================================================
// MUTACIONES
// ====================================================================

/**
 * Crear nuevo bloque de disponibilidad con sus slots (rangos)
 */
function crear_disponibilidad($datos, $usuario_id) {
    $errores = validar_disponibilidad($datos);
    if (!empty($errores)) {
        return ['success' => false, 'id' => null, 'errores' => $errores];
    }

    if (existe_solapamiento_disponibilidad(
        (int)$datos['unidad_transporte_id'],
        $datos['fecha'],
        _norm_hora($datos['hora_inicio_ruta']),
        _norm_hora($datos['hora_termino_ruta'])
    )) {
        return ['success' => false, 'id' => null, 'errores' => [
            'Esta unidad ya tiene un bloque de disponibilidad solapado en esa fecha y rango horario.'
        ]];
    }

    $pdo = conectarDB();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sec_disponibilidad_unidades
                (unidad_transporte_id, fecha, hora_inicio_ruta, hora_termino_ruta, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$datos['unidad_transporte_id'],
            $datos['fecha'],
            _norm_hora($datos['hora_inicio_ruta']),
            _norm_hora($datos['hora_termino_ruta']),
            (int)$usuario_id
        ]);
        $disponibilidad_id = (int)$pdo->lastInsertId();

        $stmt_slot = $pdo->prepare("
            INSERT INTO sec_espacios_disponibles (disponibilidad_id, hora_inicio, hora_fin, ocupado)
            VALUES (?, ?, ?, 0)
        ");
        foreach ($datos['slots_inicio'] as $i => $si) {
            $sf = $datos['slots_fin'][$i] ?? '';
            $stmt_slot->execute([$disponibilidad_id, _norm_hora($si), _norm_hora($sf)]);
        }

        $pdo->commit();
        return ['success' => true, 'id' => $disponibilidad_id, 'errores' => []];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error crear_disponibilidad: " . $e->getMessage());
        return ['success' => false, 'id' => null, 'errores' => ['Error al guardar: ' . $e->getMessage()]];
    }
}

/**
 * Actualizar bloque de disponibilidad
 *
 * Reglas:
 * - Slots OCUPADOS no se pueden eliminar ni modificar (deben seguir en la lista nueva con mismas horas)
 * - Slots ocupados deben quedar dentro del nuevo rango del bloque
 * - Slots libres se reconcilian (los que no están en la lista nueva se borran; los nuevos se insertan)
 */
function actualizar_disponibilidad($id, $datos, $usuario_id) {
    $errores = validar_disponibilidad($datos);
    if (!empty($errores)) {
        return ['success' => false, 'errores' => $errores];
    }

    $existente = obtener_disponibilidad_por_id($id);
    if (!$existente) {
        return ['success' => false, 'errores' => ['La disponibilidad no existe.']];
    }

    $hi_nuevo = _norm_hora($datos['hora_inicio_ruta']);
    $hf_nuevo = _norm_hora($datos['hora_termino_ruta']);

    if (existe_solapamiento_disponibilidad(
        (int)$datos['unidad_transporte_id'],
        $datos['fecha'],
        $hi_nuevo, $hf_nuevo,
        (int)$id
    )) {
        return ['success' => false, 'errores' => [
            'Esta unidad ya tiene otro bloque solapado en esa fecha y rango horario.'
        ]];
    }

    // Pares nuevos normalizados
    $pares_nuevos = [];
    foreach ($datos['slots_inicio'] as $i => $si) {
        $sf = $datos['slots_fin'][$i] ?? '';
        $pares_nuevos[] = ['inicio' => _norm_hora($si), 'fin' => _norm_hora($sf)];
    }

    // Validar slots OCUPADOS
    $slots_ocupados = array_filter($existente['slots'], function($s) { return (int)$s['ocupado'] === 1; });
    foreach ($slots_ocupados as $so) {
        // Debe seguir en lista nueva con misma hora
        $encontrado = false;
        foreach ($pares_nuevos as $pn) {
            if ($pn['inicio'] === $so['hora_inicio'] && $pn['fin'] === $so['hora_fin']) {
                $encontrado = true; break;
            }
        }
        if (!$encontrado) {
            return ['success' => false, 'errores' => [
                "El slot ocupado {$so['hora_inicio']} - {$so['hora_fin']} no se puede modificar ni eliminar. " .
                "Cancela primero la SEC " . ($so['sec_folio'] ?? '#' . $so['sec_id']) . "."
            ]];
        }
        // Debe estar dentro del nuevo rango
        if ($so['hora_inicio'] < $hi_nuevo || $so['hora_fin'] > $hf_nuevo) {
            return ['success' => false, 'errores' => [
                "Slot ocupado {$so['hora_inicio']} - {$so['hora_fin']} quedaría fuera del nuevo rango. " .
                "Cancela primero la SEC " . ($so['sec_folio'] ?? '#' . $so['sec_id']) . "."
            ]];
        }
    }

    $pdo = conectarDB();
    $pdo->beginTransaction();
    try {
        // Update bloque
        $stmt = $pdo->prepare("
            UPDATE sec_disponibilidad_unidades
            SET unidad_transporte_id = ?, fecha = ?, hora_inicio_ruta = ?, hora_termino_ruta = ?
            WHERE id = ?
        ");
        $stmt->execute([
            (int)$datos['unidad_transporte_id'],
            $datos['fecha'],
            $hi_nuevo, $hf_nuevo,
            (int)$id
        ]);

        // Reconciliación de slots
        // 1) Eliminar slots LIBRES que ya no están en pares_nuevos
        $stmt_libres = $pdo->prepare("
            SELECT id, hora_inicio, hora_fin
            FROM sec_espacios_disponibles
            WHERE disponibilidad_id = ? AND ocupado = 0
        ");
        $stmt_libres->execute([(int)$id]);
        $libres = $stmt_libres->fetchAll(PDO::FETCH_ASSOC);

        $stmt_borrar = $pdo->prepare("DELETE FROM sec_espacios_disponibles WHERE id = ?");
        foreach ($libres as $libre) {
            $sigue = false;
            foreach ($pares_nuevos as $pn) {
                if ($pn['inicio'] === $libre['hora_inicio'] && $pn['fin'] === $libre['hora_fin']) {
                    $sigue = true; break;
                }
            }
            if (!$sigue) $stmt_borrar->execute([(int)$libre['id']]);
        }

        // 2) Insertar pares nuevos que aún no existen
        $stmt_existe = $pdo->prepare("
            SELECT COUNT(*) FROM sec_espacios_disponibles
            WHERE disponibilidad_id = ? AND hora_inicio = ? AND hora_fin = ?
        ");
        $stmt_ins = $pdo->prepare("
            INSERT INTO sec_espacios_disponibles (disponibilidad_id, hora_inicio, hora_fin, ocupado)
            VALUES (?, ?, ?, 0)
        ");
        foreach ($pares_nuevos as $pn) {
            $stmt_existe->execute([(int)$id, $pn['inicio'], $pn['fin']]);
            if ((int)$stmt_existe->fetchColumn() === 0) {
                $stmt_ins->execute([(int)$id, $pn['inicio'], $pn['fin']]);
            }
        }

        $pdo->commit();
        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error actualizar_disponibilidad: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al actualizar: ' . $e->getMessage()]];
    }
}

/**
 * Eliminar bloque (bloqueado si tiene slots ocupados)
 */
function eliminar_disponibilidad($id) {
    $disponibilidad = obtener_disponibilidad_por_id($id);
    if (!$disponibilidad) {
        return ['success' => false, 'errores' => ['La disponibilidad no existe.']];
    }

    $ocupados = array_filter($disponibilidad['slots'], function($s) { return (int)$s['ocupado'] === 1; });
    if (count($ocupados) > 0) {
        $folios = [];
        foreach ($ocupados as $o) {
            $folios[] = $o['sec_folio'] ?? ('SEC #' . $o['sec_id']);
        }
        return ['success' => false, 'errores' => [
            'No se puede eliminar: hay ' . count($ocupados) . ' slot(s) ocupado(s) por: ' .
            implode(', ', array_unique($folios)) . '. Cancela primero esas SEC.'
        ]];
    }

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("DELETE FROM sec_disponibilidad_unidades WHERE id = ?");
        $stmt->execute([(int)$id]);
        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        error_log("Error eliminar_disponibilidad: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al eliminar: ' . $e->getMessage()]];
    }
}

// ====================================================================
// CONSULTAS PARA OTROS MÓDULOS
// ====================================================================

function obtener_disponibilidades_hoy_con_libres() {
    $hoy = date('Y-m-d');
    $disponibilidades = obtener_disponibilidades_rango($hoy, $hoy);

    return array_filter($disponibilidades, function($d) {
        return $d['slots_libres'] > 0;
    });
}