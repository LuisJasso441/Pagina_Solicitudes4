<?php
/**
 * Funciones de Devoluciones del módulo SEC
 * Ubicación: includes/salidas_envases/sec_devoluciones_funciones.php
 *
 * Reglas:
 *  - Devolución = una línea de la SEC + cantidad + motivo.
 *  - Estados válidos para registrar: en_ruta, cerrada, cerrada_con_devolucion.
 *  - Solo Logística y Almacén pueden registrar.
 *  - Cantidad no puede exceder el SALDO de la línea
 *    (cantidad_original - suma de devoluciones previas de esa línea).
 *  - Se pueden registrar múltiples devoluciones parciales por SEC.
 *  - Una vez registrada, NO se puede anular (auditoría).
 *  - Al registrar, reintegra el stock (tipo_movimiento='devolucion').
 *  - Al registrar la primera devolución de la SEC, el estado cambia a
 *    'cerrada_con_devolucion'. Devoluciones subsecuentes lo mantienen.
 */

require_once __DIR__ . '/../../config/database.php';
if (!defined('URL_BASE')) {
    require_once __DIR__ . '/../../config/config.php';
}
require_once __DIR__ . '/sec_historial_funciones.php';
require_once __DIR__ . '/inventario_funciones.php';
require_once __DIR__ . '/sec_funciones.php';

// =====================================================================
// PERMISOS
// =====================================================================

/**
 * ¿El usuario puede registrar una devolución en esta SEC?
 * D2: Logística y Almacén.
 * D1: Estados en_ruta o devoluciones_parciales (pre-cierre).
 */
function puede_registrar_devolucion_sec($dept, $sec) {
    if (!is_array($sec)) return false;
    $dept_lc = strtolower((string) $dept);
    if (!in_array($dept_lc, ['logistica', 'almacen_residuos'], true)) return false;
    return in_array($sec['estado'], ['en_ruta', 'devoluciones_parciales'], true);
}

// =====================================================================
// CONSULTAS
// =====================================================================

/**
 * Devoluciones de una SEC (ordenadas por fecha ASC).
 */
function obtener_devoluciones_sec($sec_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT d.*,
                   e.nombre AS especificacion_nombre,
                   t.nombre AS tipo_nombre,
                   u.nombre_completo AS registrado_por_nombre,
                   dpto.nombre AS registrado_por_departamento
            FROM sec_devoluciones d
            INNER JOIN sec_especificaciones e ON e.id = d.especificacion_id
            INNER JOIN sec_tipos_envase t     ON t.id = e.tipo_envase_id
            LEFT  JOIN usuarios u             ON u.id = d.usuario_id
            LEFT  JOIN departamentos dpto     ON dpto.id = u.departamento_id
            WHERE d.sec_id = ?
            ORDER BY d.creado_en ASC, d.id ASC
        ");
        $stmt->execute([(int) $sec_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('obtener_devoluciones_sec: ' . $e->getMessage());
        return [];
    }
}

/**
 * Retorna mapa {linea_id: cantidad_ya_devuelta}
 * Útil para calcular el saldo disponible por línea.
 */
function cantidades_devueltas_por_linea($sec_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT linea_empresa_id, SUM(cantidad_devuelta) AS total
            FROM sec_devoluciones
            WHERE sec_id = ? AND linea_empresa_id IS NOT NULL
            GROUP BY linea_empresa_id
        ");
        $stmt->execute([(int) $sec_id]);
        $mapa = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mapa[(int) $row['linea_empresa_id']] = (int) $row['total'];
        }
        return $mapa;
    } catch (Exception $e) {
        error_log('cantidades_devueltas_por_linea: ' . $e->getMessage());
        return [];
    }
}

/**
 * Saldo disponible por línea (cantidad_original - devoluciones previas).
 * @param array $lineas Salida de obtener_lineas_sec()
 * @param array $mapa_devueltas Salida de cantidades_devueltas_por_linea()
 * @return array {linea_id: saldo_int}
 */
function calcular_saldos_lineas($lineas, $mapa_devueltas) {
    $saldos = [];
    foreach ($lineas as $l) {
        $lid = (int) $l['id'];
        $orig = (int) $l['cantidad'];
        $dev  = (int) ($mapa_devueltas[$lid] ?? 0);
        $saldos[$lid] = max(0, $orig - $dev);
    }
    return $saldos;
}

/**
 * Etiquetas legibles para el ENUM motivo.
 */
function motivo_devolucion_label($motivo) {
    $map = [
        'condiciones_incorrectas' => 'Condiciones incorrectas',
        'cantidad_incorrecta'     => 'Cantidad incorrecta',
        'otro'                    => 'Otro',
    ];
    return $map[$motivo] ?? $motivo;
}

// =====================================================================
// CREAR DEVOLUCIÓN
// =====================================================================

/**
 * Registra una devolución sobre una línea específica de la SEC.
 * Transaccional: inserta devolución + reintegra stock + cambia estado si aplica.
 *
 * @return array ['success'=>bool, 'devolucion_id'=>?int, 'errores'=>string[]]
 */
function crear_devolucion_sec($sec_id, $linea_id, $cantidad, $motivo, $motivo_otro, $usuario_id) {
    $sec = obtener_sec_por_id($sec_id);
    if (!$sec) return ['success' => false, 'errores' => ['La SEC no existe.']];

    // Validar estado
    if (!in_array($sec['estado'], ['en_ruta', 'devoluciones_parciales'], true)) {
        return ['success' => false, 'errores' => ['La SEC no está en un estado que permita devoluciones.']];
    }

    // Validar línea existe y pertenece a esta SEC
    $linea = null;
    foreach ($sec['lineas'] as $l) {
        if ((int) $l['id'] === (int) $linea_id) { $linea = $l; break; }
    }
    if (!$linea) return ['success' => false, 'errores' => ['La línea seleccionada no existe en esta SEC.']];

    $cantidad = (int) $cantidad;
    if ($cantidad <= 0) return ['success' => false, 'errores' => ['La cantidad debe ser mayor a 0.']];

    // Validar motivo
    $motivos_validos = ['condiciones_incorrectas', 'cantidad_incorrecta', 'otro'];
    if (!in_array($motivo, $motivos_validos, true)) {
        return ['success' => false, 'errores' => ['Motivo inválido.']];
    }
    $motivo_otro = trim((string) $motivo_otro);
    if ($motivo === 'otro' && $motivo_otro === '') {
        return ['success' => false, 'errores' => ['Debe describir el motivo cuando selecciona "Otro".']];
    }
    if (mb_strlen($motivo_otro) > 500) {
        return ['success' => false, 'errores' => ['El detalle del motivo es demasiado largo (máx 500).']];
    }

    // Calcular saldo disponible para esta línea
    $mapa_devueltas = cantidades_devueltas_por_linea($sec_id);
    $devuelto_prev  = (int) ($mapa_devueltas[(int) $linea_id] ?? 0);
    $saldo          = (int) $linea['cantidad'] - $devuelto_prev;
    if ($cantidad > $saldo) {
        return ['success' => false, 'errores' => [
            "La cantidad ({$cantidad}) excede el saldo disponible de esta línea ({$saldo}). "
            . "Original: {$linea['cantidad']}, ya devuelto: {$devuelto_prev}."
        ]];
    }

    $pdo = null;
    try {
        $pdo = conectarDB();
        $pdo->beginTransaction();

        // Insertar devolución
        $stmt = $pdo->prepare("
            INSERT INTO sec_devoluciones
                (sec_id, linea_empresa_id, empresa_nombre, especificacion_id,
                 cantidad_devuelta, motivo, motivo_otro, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int) $sec_id,
            (int) $linea_id,
            $linea['empresa_nombre'],
            (int) $linea['especificacion_id'],
            $cantidad,
            $motivo,
            $motivo_otro !== '' ? $motivo_otro : null,
            (int) $usuario_id,
        ]);
        $dev_id = (int) $pdo->lastInsertId();

        // Reintegro al inventario
        $res_mov = registrar_movimiento([
            'especificacion_id' => (int) $linea['especificacion_id'],
            'tipo_movimiento'   => 'devolucion',
            'cantidad'          => $cantidad,
            'motivo'            => "Devolución de SEC {$sec['folio']} · {$linea['empresa_nombre']}",
            'usuario_id'        => (int) $usuario_id,
            'referencia_tipo'   => 'SEC_DEVOLUCION',
            'referencia_id'     => $dev_id,
        ]);
        if (!$res_mov['ok']) {
            $pdo->rollBack();
            return ['success' => false, 'errores' => ['Error al reintegrar al inventario: ' . $res_mov['msg']]];
        }

        // Cambio de estado a 'devoluciones_parciales' solo si venía de 'en_ruta'.
        // Si ya estaba 'devoluciones_parciales', se mantiene sin update.
        // La SEC ya no se cierra automáticamente al primer registro: eso lo
        // decide Logística/Almacén desde el botón "Cerrar SEC" cuando termine
        // el proceso con todas las empresas.
        $estado_antes   = $sec['estado'];
        $estado_despues = ($estado_antes === 'en_ruta') ? 'devoluciones_parciales' : $estado_antes;
        if ($estado_despues !== $estado_antes) {
            $stmt = $pdo->prepare("UPDATE sec_salidas SET estado = ? WHERE id = ?");
            $stmt->execute([$estado_despues, (int) $sec_id]);
        }

        $pdo->commit();

        // Historial
        $desc = "Devolución: {$cantidad} × {$linea['empresa_nombre']} · " . motivo_devolucion_label($motivo);
        registrar_historial_sec(
            (int) $sec_id, (int) $usuario_id, 'devolucion_registrada', $desc,
            [
                'devolucion_id'    => $dev_id,
                'linea_id'         => (int) $linea_id,
                'empresa'          => $linea['empresa_nombre'],
                'especificacion'   => $linea['especificacion_nombre'] ?? null,
                'cantidad'         => $cantidad,
                'saldo_previo'     => $saldo,
                'motivo'           => $motivo,
                'motivo_otro'      => $motivo_otro !== '' ? $motivo_otro : null,
                'estado_antes'    => $estado_antes,
                'estado_despues'  => $estado_despues,
            ]
        );

        return ['success' => true, 'devolucion_id' => $dev_id, 'errores' => []];
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        error_log('crear_devolucion_sec: ' . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al registrar la devolución. Intente de nuevo.']];
    }
}