<?php
/**
 * Funciones del apartado de Historial del modulo SEC
 * Ubicacion: includes/salidas_envases/sec_historial_funciones.php
 *
 * Registra eventos de vida de una SEC para auditoria y trazabilidad.
 * datos_json guarda payload adicional (motivos, condiciones, snapshots).
 */

require_once __DIR__ . '/../../config/database.php';

// ====================================================================
// REGISTRO Y CONSULTA
// ====================================================================

/**
 * Registrar un evento en el historial.
 * Nunca lanza excepcion: falla silenciosamente para no bloquear la
 * operacion principal.
 *
 * @param int    $sec_id
 * @param int|null $usuario_id  (null cuando el sistema es el actor)
 * @param string $tipo_evento
 * @param string $descripcion
 * @param mixed  $datos         (array/objeto que se serializa a JSON, opcional)
 * @return bool
 */
function registrar_historial_sec($sec_id, $usuario_id, $tipo_evento, $descripcion, $datos = null) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            INSERT INTO sec_historial (sec_id, usuario_id, tipo_evento, descripcion, datos_json, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $datos_json = $datos !== null ? json_encode($datos, JSON_UNESCAPED_UNICODE) : null;
        $stmt->execute([
            (int)$sec_id,
            $usuario_id ? (int)$usuario_id : null,
            $tipo_evento,
            $descripcion,
            $datos_json
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Error registrar_historial_sec: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener historial completo de una SEC (mas reciente primero).
 */
function obtener_historial_sec($sec_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT h.*,
                   u.nombre_completo AS autor_nombre,
                   d.nombre AS autor_departamento
            FROM sec_historial h
            LEFT JOIN usuarios u ON u.id = h.usuario_id
            LEFT JOIN departamentos d ON d.id = u.departamento_id
            WHERE h.sec_id = ?
            ORDER BY h.fecha_creacion DESC, h.id DESC
        ");
        $stmt->execute([(int)$sec_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obtener_historial_sec: " . $e->getMessage());
        return [];
    }
}

function contar_historial_sec($sec_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sec_historial WHERE sec_id = ?");
        $stmt->execute([(int)$sec_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// ====================================================================
// ICONO Y COLOR POR TIPO DE EVENTO
// ====================================================================

/**
 * Devuelve icono Bootstrap y color por tipo de evento.
 * @return array ['icono' => 'bi-...', 'color' => 'primary|success|...']
 */
function historial_icono_evento($tipo) {
    $map = [
        'sec_creada'           => ['bi-file-earmark-plus',    'primary'],
        'entrega_firmada'      => ['bi-pen',                  'info'],
        'recibe_firmada'       => ['bi-check2-square',        'success'],
        'lineas_editadas'      => ['bi-pencil-square',        'warning'],
        'sec_cancelada'        => ['bi-x-circle',             'danger'],
        'sec_cerrada'          => ['bi-check-circle-fill',    'success'],
        'evidencia_subida'     => ['bi-image',                'secondary'],
        'evidencia_eliminada'  => ['bi-trash',                'danger'],
        'comentario_agregado'  => ['bi-chat-dots',            'primary'],
        'comentario_editado'   => ['bi-pencil',               'secondary'],
        'comentario_eliminado' => ['bi-trash3',               'danger'],
    ];
    if (isset($map[$tipo])) return ['icono' => $map[$tipo][0], 'color' => $map[$tipo][1]];
    return ['icono' => 'bi-circle', 'color' => 'secondary'];
}

// ====================================================================
// SNAPSHOT Y DIFF DE LINEAS (para tipo_evento = 'lineas_editadas')
// ====================================================================

/**
 * Genera un snapshot serializable de las lineas actuales de una SEC.
 * @param array $lineas  Salida de obtener_lineas_sec()
 * @return array
 */
function snapshot_lineas_sec($lineas) {
    $out = [];
    foreach ($lineas as $l) {
        $slot_label = '';
        if (!empty($l['slot_hora_inicio']) && !empty($l['slot_hora_fin'])) {
            $slot_label = substr($l['slot_hora_inicio'], 0, 5) . ' - ' . substr($l['slot_hora_fin'], 0, 5);
        }
        $out[] = [
            'cantidad'      => (int)($l['cantidad'] ?? 0),
            'tipo_envase'   => $l['tipo_envase'] ?? '',
            'unidad_id'     => (int)($l['unidad_transporte_id'] ?? 0),
            'unidad_nombre' => $l['unidad_nombre'] ?? '',
            'unidad_placas' => $l['unidad_placas'] ?? '',
            'slot_id'       => (int)($l['slot_id'] ?? 0),
            'slot_label'    => $slot_label,
        ];
    }
    return $out;
}

/**
 * Devuelve lista de cambios amigables entre dos snapshots.
 * Compara por firma (cantidad|tipo|slot_id): identifica lineas quitadas
 * y agregadas. Una modificacion en una linea se ve como quitar+agregar.
 *
 * @return string[] Lista de descripciones de cambios (una por linea)
 */
function comparar_lineas_sec($antes, $despues) {
    $cambios = [];

    $sig = function($l) {
        return implode('|', [
            (int)($l['cantidad'] ?? 0),
            $l['tipo_envase'] ?? '',
            (int)($l['slot_id'] ?? 0),
        ]);
    };

    $sigs_antes   = array_map($sig, $antes);
    $sigs_despues = array_map($sig, $despues);

    foreach ($antes as $la) {
        if (!in_array($sig($la), $sigs_despues, true)) {
            $desc = "Quito: {$la['cantidad']} x {$la['tipo_envase']}";
            if (!empty($la['unidad_nombre'])) $desc .= " en {$la['unidad_nombre']}";
            if (!empty($la['slot_label']))    $desc .= " ({$la['slot_label']})";
            $cambios[] = $desc;
        }
    }
    foreach ($despues as $ld) {
        if (!in_array($sig($ld), $sigs_antes, true)) {
            $desc = "Agrego: {$ld['cantidad']} x {$ld['tipo_envase']}";
            if (!empty($ld['unidad_nombre'])) $desc .= " en {$ld['unidad_nombre']}";
            if (!empty($ld['slot_label']))    $desc .= " ({$ld['slot_label']})";
            $cambios[] = $desc;
        }
    }

    if (empty($cambios)) $cambios[] = 'Sin cambios detectados en las lineas.';
    return $cambios;
}