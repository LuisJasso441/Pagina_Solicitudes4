<?php
/**
 * Funciones de la Programación General de Mantenimientos
 * includes/mantenimientos_ti/programacion_funciones.php
 *
 * Compartidas entre programacion_mantenimientos.php (vista) y
 * procesar_programacion.php (guardado), para que ambos calculen
 * exactamente la misma lista de equipos programables.
 */

/**
 * Tipos de equipo de cómputo que entran en la programación
 */
function prog_tipos_computo(): array {
    return [
        'all_in_one' => 'All in One',
        'pc'         => 'PC',
        'laptop'     => 'Laptop',
    ];
}

/**
 * Tipos de mantenimiento
 */
function prog_tipos_mantenimiento(): array {
    return [
        'fisico' => 'Físico',
        'logico' => 'Lógico',
    ];
}

/**
 * Escape corto para la vista
 */
function prog_h($valor): string {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Identificador legible del equipo (código interno o hostname)
 */
function prog_etiqueta_equipo(array $eq): string {
    $codigo = trim($eq['codigo_interno'] ?? '');
    $host   = trim($eq['hostname'] ?? '');
    return $codigo !== '' ? $codigo : ($host !== '' ? $host : 'ID ' . intval($eq['id'] ?? 0));
}

/**
 * Equipos de cómputo activos con su estado dentro de la programación.
 *
 * pendientes: mantenimientos pendientes o en proceso del equipo, uno por tipo
 *             ['fisico' => [...], 'logico' => [...]] con bandera 'vencido'
 *             (pendiente con fecha anterior a hoy)
 *
 * Los pendientes son solo informativos: nunca bloquean ni limitan el tipo.
 *
 * estado_fila:
 *   - programable      -> sin pendientes; tipo y fecha OBLIGATORIOS
 *   - con_pendiente    -> tiene pendiente(s); se puede programar cualquier tipo (OPCIONAL)
 *   - sin_departamento -> no tiene departamento en inventario (no se puede programar)
 *   - sin_usuario      -> no tiene usuario activo asignado (no se puede programar)
 *
 * usuario_destino_id: primer usuario activo asignado (misma regla que Nueva Solicitud)
 *
 * @return array Indexado por id de equipo
 */
function prog_obtener_equipos(PDO $pdo): array {
    $tipos = array_keys(prog_tipos_computo());
    $ph    = implode(',', array_fill(0, count($tipos), '?'));
    $hoy   = date('Y-m-d');

    // ---------- Equipos ----------
    $sql = "SELECT ie.id, ie.codigo_interno, ie.hostname, ie.tipo_equipo, ie.marca, ie.modelo,
                   ie.departamento_id,
                   ie.usuario_asignado_id,   u1.nombre_completo AS usuario1_nombre, u1.activo AS usuario1_activo,
                   ie.usuario_asignado_2_id, u2.nombre_completo AS usuario2_nombre, u2.activo AS usuario2_activo
            FROM inventario_equipos ie
            LEFT JOIN usuarios u1 ON u1.id = ie.usuario_asignado_id
            LEFT JOIN usuarios u2 ON u2.id = ie.usuario_asignado_2_id
            WHERE ie.estado = 'activo'
              AND ie.tipo_equipo IN ($ph)
            ORDER BY (COALESCE(u1.nombre_completo, u2.nombre_completo) IS NULL),
                     COALESCE(u1.nombre_completo, u2.nombre_completo),
                     ie.codigo_interno";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($tipos);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---------- Mantenimientos pendientes / en proceso (el más reciente por equipo y tipo) ----------
    $sql_pend = "SELECT id, equipo_id, folio, fecha_deseada, tipo_mantenimiento, estado
                 FROM solicitudes_mantenimiento_ti
                 WHERE estado IN ('pendiente', 'en_proceso')
                   AND equipo_id IS NOT NULL
                 ORDER BY id DESC";
    $pendientes_por_equipo = [];
    foreach ($pdo->query($sql_pend)->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $eid  = intval($p['equipo_id']);
        $tipo = $p['tipo_mantenimiento'];
        if (isset($pendientes_por_equipo[$eid][$tipo])) {
            continue;
        }
        $p['vencido'] = ($p['estado'] === 'pendiente'
                         && !empty($p['fecha_deseada'])
                         && $p['fecha_deseada'] < $hoy);
        $pendientes_por_equipo[$eid][$tipo] = $p;
    }

    $equipos = [];
    foreach ($filas as $f) {
        $id = intval($f['id']);

        // Usuarios activos asignados (1ro y 2do)
        $usuarios = [];
        if (!empty($f['usuario_asignado_id']) && intval($f['usuario1_activo']) === 1) {
            $usuarios[] = ['id' => intval($f['usuario_asignado_id']), 'nombre' => $f['usuario1_nombre']];
        }
        if (!empty($f['usuario_asignado_2_id']) && intval($f['usuario2_activo']) === 1
            && intval($f['usuario_asignado_2_id']) !== intval($f['usuario_asignado_id'])) {
            $usuarios[] = ['id' => intval($f['usuario_asignado_2_id']), 'nombre' => $f['usuario2_nombre']];
        }

        // Pendientes ordenados: Físico, Lógico
        $pendientes = [];
        foreach (array_keys(prog_tipos_mantenimiento()) as $t) {
            if (isset($pendientes_por_equipo[$id][$t])) {
                $pendientes[$t] = $pendientes_por_equipo[$id][$t];
            }
        }
        if (empty($f['departamento_id'])) {
            $estado = 'sin_departamento';
        } elseif (empty($usuarios)) {
            $estado = 'sin_usuario';
        } elseif (!empty($pendientes)) {
            $estado = 'con_pendiente';
        } else {
            $estado = 'programable';
        }

        $f['usuarios']           = $usuarios;
        $f['usuario_destino_id'] = $usuarios[0]['id'] ?? null;
        $f['pendientes']         = $pendientes;
        $f['estado_fila']        = $estado;

        $equipos[$id] = $f;
    }

    return $equipos;
}

/**
 * Departamentos a mostrar: todos los activos, más cualquier departamento
 * inactivo que todavía tenga equipos de cómputo activos asignados.
 */
function prog_obtener_departamentos(PDO $pdo): array {
    $tipos = array_keys(prog_tipos_computo());
    $ph    = implode(',', array_fill(0, count($tipos), '?'));

    $sql = "SELECT d.id, d.nombre
            FROM departamentos d
            WHERE d.activo = 1
               OR d.id IN (
                    SELECT ie.departamento_id
                    FROM inventario_equipos ie
                    WHERE ie.estado = 'activo'
                      AND ie.tipo_equipo IN ($ph)
                      AND ie.departamento_id IS NOT NULL
               )
            ORDER BY d.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($tipos);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}