<?php
/**
 * Funciones del módulo Reservación de Sala de Juntas (RSJ)
 * Ubicación: includes/reservaciones/reservaciones_funciones.php
 * 
 * Campos: lugar (sala_juntas/sala_capacitaciones), motivo (texto libre)
 * Conflictos se verifican POR LUGAR (dos salas independientes)
 */

// =====================================================
// HELPERS
// =====================================================

function obtener_nombre_lugar($codigo) {
    $lugares = [
        'sala_juntas'         => 'Sala de Juntas',
        'sala_capacitaciones' => 'Sala de Capacitaciones'
    ];
    return $lugares[$codigo] ?? $codigo;
}

function obtener_nombre_lugar_corto($codigo) {
    $lugares = [
        'sala_juntas'         => 'S. Juntas',
        'sala_capacitaciones' => 'S. Capacitaciones'
    ];
    return $lugares[$codigo] ?? $codigo;
}

function obtener_lugares_disponibles() {
    return [
        'sala_juntas'         => 'Sala de Juntas',
        'sala_capacitaciones' => 'Sala de Capacitaciones'
    ];
}

// =====================================================
// CONSULTAS
// =====================================================

function obtener_reservacion_por_id($id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT r.*, 
               d.nombre AS departamento_nombre,
               COALESCE(c.color_hex, '#95A5A6') AS color_hex,
               COALESCE(c.color_texto, '#FFFFFF') AS color_texto,
               u.nombre_completo AS creador_nombre
        FROM reservaciones_sala r
        LEFT JOIN departamentos d ON r.departamento_id = d.id
        LEFT JOIN colores_departamento c ON r.departamento_id = c.departamento_id
        LEFT JOIN usuarios u ON r.usuario_id = u.id
        WHERE r.id = :id
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function obtener_eventos_calendario($inicio, $fin) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT r.id, r.solicitante_nombre, r.departamento, r.lugar, r.motivo,
               r.fecha_reservacion, r.hora_inicio, r.hora_fin,
               r.usuario_id, r.estado,
               d.nombre AS departamento_nombre,
               COALESCE(c.color_hex, '#95A5A6') AS color_hex,
               COALESCE(c.color_texto, '#FFFFFF') AS color_texto
        FROM reservaciones_sala r
        LEFT JOIN departamentos d ON r.departamento_id = d.id
        LEFT JOIN colores_departamento c ON r.departamento_id = c.departamento_id
        WHERE r.estado = 'activa'
          AND r.fecha_reservacion >= :inicio
          AND r.fecha_reservacion <= :fin
        ORDER BY r.fecha_reservacion, r.hora_inicio
    ");
    $stmt->execute([':inicio' => $inicio, ':fin' => $fin]);
    $reservaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $eventos = [];
    foreach ($reservaciones as $r) {
        $lugar_nombre = obtener_nombre_lugar($r['lugar'] ?? 'sala_juntas');
        $lugar_corto = obtener_nombre_lugar_corto($r['lugar'] ?? 'sala_juntas');
        $depto = $r['departamento_nombre'] ?? ucfirst($r['departamento']);
        
        $eventos[] = [
            'id'        => $r['id'],
            'title'     => $r['solicitante_nombre'] . ' - ' . $depto,
            'start'     => $r['fecha_reservacion'] . 'T' . $r['hora_inicio'],
            'end'       => $r['fecha_reservacion'] . 'T' . $r['hora_fin'],
            'color'     => $r['color_hex'],
            'textColor' => $r['color_texto'],
            'extendedProps' => [
                'solicitante'    => $r['solicitante_nombre'],
                'departamento'   => $depto,
                'lugar'          => $lugar_nombre,
                'lugar_corto'    => $lugar_corto,
                'lugar_codigo'   => $r['lugar'] ?? 'sala_juntas',
                'motivo'         => $r['motivo'] ?? '',
                'hora_inicio'    => substr($r['hora_inicio'], 0, 5),
                'hora_fin'       => substr($r['hora_fin'], 0, 5),
                'usuario_id'     => $r['usuario_id'],
                'estado'         => $r['estado']
            ]
        ];
    }
    return $eventos;
}

function obtener_reservaciones_lista($filtros = []) {
    $pdo = conectarDB();
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filtros['estado'])) {
        $where[] = "r.estado = :estado";
        $params[':estado'] = $filtros['estado'];
    } else {
        $where[] = "r.estado = 'activa'";
    }
    if (!empty($filtros['departamento_id'])) {
        $where[] = "r.departamento_id = :departamento_id";
        $params[':departamento_id'] = $filtros['departamento_id'];
    }
    if (!empty($filtros['lugar'])) {
        $where[] = "r.lugar = :lugar";
        $params[':lugar'] = $filtros['lugar'];
    }
    if (!empty($filtros['fecha_desde'])) {
        $where[] = "r.fecha_reservacion >= :fecha_desde";
        $params[':fecha_desde'] = $filtros['fecha_desde'];
    }
    if (!empty($filtros['fecha_hasta'])) {
        $where[] = "r.fecha_reservacion <= :fecha_hasta";
        $params[':fecha_hasta'] = $filtros['fecha_hasta'];
    }
    if (!empty($filtros['busqueda'])) {
        $where[] = "(r.solicitante_nombre LIKE :busqueda OR r.departamento LIKE :busqueda2 OR r.motivo LIKE :busqueda3)";
        $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
        $params[':busqueda2'] = '%' . $filtros['busqueda'] . '%';
        $params[':busqueda3'] = '%' . $filtros['busqueda'] . '%';
    }
    
    $where_sql = implode(' AND ', $where);
    $pagina = max(1, (int)($filtros['pagina'] ?? 1));
    $por_pagina = (int)($filtros['por_pagina'] ?? 15);
    $offset = ($pagina - 1) * $por_pagina;
    
    $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM reservaciones_sala r WHERE {$where_sql}");
    $stmt_count->execute($params);
    $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("
        SELECT r.*, d.nombre AS departamento_nombre,
               COALESCE(c.color_hex, '#95A5A6') AS color_hex,
               COALESCE(c.color_texto, '#FFFFFF') AS color_texto
        FROM reservaciones_sala r
        LEFT JOIN departamentos d ON r.departamento_id = d.id
        LEFT JOIN colores_departamento c ON r.departamento_id = c.departamento_id
        WHERE {$where_sql}
        ORDER BY r.fecha_reservacion DESC, r.hora_inicio DESC
        LIMIT {$por_pagina} OFFSET {$offset}
    ");
    $stmt->execute($params);
    
    return [
        'reservaciones' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total, 'pagina' => $pagina,
        'por_pagina' => $por_pagina,
        'total_paginas' => ceil($total / $por_pagina)
    ];
}

// =====================================================
// DISPONIBILIDAD (POR LUGAR)
// =====================================================

function verificar_disponibilidad($fecha, $hora_inicio, $hora_fin, $lugar = 'sala_juntas', $excluir_id = 0) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT r.id, r.solicitante_nombre, r.departamento, r.lugar,
               TIME_FORMAT(r.hora_inicio, '%H:%i') AS hora_inicio,
               TIME_FORMAT(r.hora_fin, '%H:%i') AS hora_fin,
               d.nombre AS departamento_nombre
        FROM reservaciones_sala r
        LEFT JOIN departamentos d ON r.departamento_id = d.id
        WHERE r.fecha_reservacion = :fecha
          AND r.lugar = :lugar
          AND r.estado = 'activa'
          AND r.id != :excluir_id
          AND (r.hora_inicio < :hora_fin AND r.hora_fin > :hora_inicio)
        LIMIT 1
    ");
    $stmt->execute([
        ':fecha' => $fecha, ':lugar' => $lugar, ':excluir_id' => $excluir_id,
        ':hora_inicio' => $hora_inicio, ':hora_fin' => $hora_fin
    ]);
    $conflicto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conflicto) {
        return [
            'disponible' => false,
            'conflicto' => [
                'id' => $conflicto['id'],
                'solicitante' => $conflicto['solicitante_nombre'],
                'departamento' => $conflicto['departamento_nombre'] ?? ucfirst($conflicto['departamento']),
                'lugar' => obtener_nombre_lugar($conflicto['lugar']),
                'hora_inicio' => $conflicto['hora_inicio'],
                'hora_fin' => $conflicto['hora_fin']
            ]
        ];
    }
    return ['disponible' => true, 'conflicto' => null];
}

// =====================================================
// CREAR / EDITAR / CANCELAR
// =====================================================

function crear_reservacion($datos) {
    $pdo = conectarDB();
    try {
        $lugar = $datos['lugar'] ?? 'sala_juntas';
        $disponibilidad = verificar_disponibilidad(
            $datos['fecha_reservacion'], $datos['hora_inicio'], $datos['hora_fin'], $lugar
        );
        if (!$disponibilidad['disponible']) {
            return ['success' => false, 'error' => 'conflicto', 'conflicto' => $disponibilidad['conflicto']];
        }
        $stmt = $pdo->prepare("
            INSERT INTO reservaciones_sala 
            (solicitante_nombre, departamento, departamento_id, lugar, motivo, usuario_id, 
             fecha_reservacion, hora_inicio, hora_fin, estado)
            VALUES 
            (:solicitante, :departamento, :departamento_id, :lugar, :motivo, :usuario_id,
             :fecha, :hora_inicio, :hora_fin, 'activa')
        ");
        $stmt->execute([
            ':solicitante' => $datos['solicitante_nombre'],
            ':departamento' => $datos['departamento'],
            ':departamento_id' => $datos['departamento_id'] ?? null,
            ':lugar' => $lugar,
            ':motivo' => $datos['motivo'] ?? null,
            ':usuario_id' => $datos['usuario_id'],
            ':fecha' => $datos['fecha_reservacion'],
            ':hora_inicio' => $datos['hora_inicio'],
            ':hora_fin' => $datos['hora_fin']
        ]);
        return ['success' => true, 'id' => $pdo->lastInsertId()];
    } catch (Exception $e) {
        error_log("Error al crear reservación: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function editar_reservacion($id, $datos) {
    $pdo = conectarDB();
    try {
        $reservacion = obtener_reservacion_por_id($id);
        if (!$reservacion) return ['success' => false, 'error' => 'Reservación no encontrada'];
        if ($reservacion['estado'] !== 'activa') return ['success' => false, 'error' => 'Solo se pueden editar reservaciones activas'];
        
        $lugar = $datos['lugar'] ?? 'sala_juntas';
        $disponibilidad = verificar_disponibilidad(
            $datos['fecha_reservacion'], $datos['hora_inicio'], $datos['hora_fin'], $lugar, $id
        );
        if (!$disponibilidad['disponible']) {
            return ['success' => false, 'error' => 'conflicto', 'conflicto' => $disponibilidad['conflicto']];
        }
        $stmt = $pdo->prepare("
            UPDATE reservaciones_sala SET
                solicitante_nombre = :solicitante, departamento = :departamento,
                departamento_id = :departamento_id, lugar = :lugar, motivo = :motivo,
                fecha_reservacion = :fecha, hora_inicio = :hora_inicio, hora_fin = :hora_fin
            WHERE id = :id
        ");
        $stmt->execute([
            ':solicitante' => $datos['solicitante_nombre'], ':departamento' => $datos['departamento'],
            ':departamento_id' => $datos['departamento_id'] ?? null, ':lugar' => $lugar,
            ':motivo' => $datos['motivo'] ?? null,
            ':fecha' => $datos['fecha_reservacion'], ':hora_inicio' => $datos['hora_inicio'],
            ':hora_fin' => $datos['hora_fin'], ':id' => $id
        ]);
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Error al editar reservación: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function cancelar_reservacion($id, $usuario_id, $motivo_cancelacion = null) {
    $pdo = conectarDB();
    try {
        $reservacion = obtener_reservacion_por_id($id);
        if (!$reservacion) return ['success' => false, 'error' => 'Reservación no encontrada'];
        if ($reservacion['estado'] !== 'activa') return ['success' => false, 'error' => 'La reservación ya está cancelada'];
        $stmt = $pdo->prepare("
            UPDATE reservaciones_sala SET estado = 'cancelada', cancelado_por = :cancelado_por,
                motivo_cancelacion = :motivo, fecha_cancelacion = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':cancelado_por' => $usuario_id, ':motivo' => $motivo_cancelacion, ':id' => $id]);
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Error al cancelar reservación: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// =====================================================
// PERMISOS
// =====================================================

function puede_modificar_reservacion($reservacion, $usuario_id, $departamento) {
    $depto = strtolower(trim($departamento));
    if ((int)$reservacion['usuario_id'] === (int)$usuario_id) return true;
    if ($depto === 'sistemas') return true;
    return false;
}

// =====================================================
// AUXILIARES
// =====================================================

function obtener_colores_departamentos() {
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT d.id, d.codigo, d.nombre, COALESCE(c.color_hex, '#95A5A6') AS color_hex,
               COALESCE(c.color_texto, '#FFFFFF') AS color_texto
        FROM departamentos d LEFT JOIN colores_departamento c ON d.id = c.departamento_id
        WHERE d.activo = 1 ORDER BY d.nombre
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_departamentos_activos() {
    $pdo = conectarDB();
    $stmt = $pdo->query("SELECT id, codigo, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatear_hora_12h($hora_24h) {
    return date('h:i A', strtotime($hora_24h));
}

function convertir_a_24h($hora, $ampm) {
    $timestamp = strtotime($hora . ' ' . $ampm);
    return date('H:i:s', $timestamp);
}