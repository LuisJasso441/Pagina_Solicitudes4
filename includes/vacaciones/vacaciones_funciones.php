<?php
/**
 * Funciones del Módulo de Vacaciones
 * includes/vacaciones/vacaciones_funciones.php
 * 
 * Contiene: Tabla LFT, cálculos de antigüedad, días hábiles,
 *           resumen del empleado, generación de folios y CRUD
 */

// =====================================================
// TABLA LFT - Artículo 76 (Reforma 2023)
// =====================================================

/**
 * Días de vacaciones según años de antigüedad (LFT México)
 * Reforma 2023: primer año = 12 días
 * A partir del 6° año, se incrementan 2 días cada 5 años
 * 
 * @param int $anios_antiguedad
 * @return int
 */
function dias_vacaciones_lft($anios_antiguedad) {
    if ($anios_antiguedad <= 0) return 0;
    
    // Años 1-5: valores fijos
    $tabla_fija = [
        1 => 12,
        2 => 14,
        3 => 16,
        4 => 18,
        5 => 20
    ];
    
    if ($anios_antiguedad <= 5) {
        return $tabla_fija[$anios_antiguedad];
    }
    
    // A partir del año 6: 20 días base + 2 días por cada bloque de 5 años
    $bloque = ceil(($anios_antiguedad - 5) / 5);
    return 20 + ($bloque * 2);
}

// =====================================================
// CÁLCULOS DE ANTIGÜEDAD
// =====================================================

/**
 * Calcular antigüedad a partir de fecha de ingreso
 * @param string $fecha_ingreso - Formato YYYY-MM-DD
 * @return array ['anios' => int, 'meses' => int, 'dias' => int, 'anios_completos' => int]
 */
function calcular_antiguedad($fecha_ingreso) {
    if (empty($fecha_ingreso)) {
        return ['anios' => 0, 'meses' => 0, 'dias' => 0, 'anios_completos' => 0];
    }
    
    try {
        $ingreso = new DateTime($fecha_ingreso);
        $hoy = new DateTime();
        $diff = $ingreso->diff($hoy);
        
        return [
            'anios' => $diff->y,
            'meses' => $diff->m,
            'dias'  => $diff->d,
            'anios_completos' => $diff->y
        ];
    } catch (Exception $e) {
        return ['anios' => 0, 'meses' => 0, 'dias' => 0, 'anios_completos' => 0];
    }
}

/**
 * Texto de antigüedad formateado para mostrar en UI
 * @param string $fecha_ingreso
 * @return string
 */
function texto_antiguedad($fecha_ingreso) {
    $ant = calcular_antiguedad($fecha_ingreso);
    $partes = [];
    if ($ant['anios'] > 0) $partes[] = $ant['anios'] . ' año' . ($ant['anios'] > 1 ? 's' : '');
    if ($ant['meses'] > 0) $partes[] = $ant['meses'] . ' mes' . ($ant['meses'] > 1 ? 'es' : '');
    if (empty($partes) && $ant['dias'] > 0) $partes[] = $ant['dias'] . ' día' . ($ant['dias'] > 1 ? 's' : '');
    return !empty($partes) ? implode(', ', $partes) : 'Sin fecha de ingreso';
}

// =====================================================
// CONTEO DE DÍAS HÁBILES (Lunes a Sábado)
// =====================================================

/**
 * Contar días hábiles entre dos fechas (L-S, excluye domingos)
 * @param string $fecha_inicio - YYYY-MM-DD
 * @param string $fecha_fin - YYYY-MM-DD
 * @return int
 */
function contar_dias_habiles($fecha_inicio, $fecha_fin) {
    if (empty($fecha_inicio) || empty($fecha_fin)) return 0;
    
    try {
        $inicio = new DateTime($fecha_inicio);
        $fin = new DateTime($fecha_fin);
        
        if ($inicio > $fin) return 0;
        
        $dias = 0;
        $current = clone $inicio;
        
        while ($current <= $fin) {
            $dia_semana = (int)$current->format('w'); // 0=Dom, 1=Lun ... 6=Sab
            if ($dia_semana >= 1 && $dia_semana <= 6) {
                $dias++;
            }
            $current->modify('+1 day');
        }
        
        return $dias;
    } catch (Exception $e) {
        return 0;
    }
}

// =====================================================
// PERIODO VACACIONAL
// =====================================================

/**
 * Determinar el periodo vacacional actual del empleado.
 * El periodo va de aniversario a aniversario.
 * 
 * @param string $fecha_ingreso
 * @return array|null ['inicio', 'fin', 'anio_periodo']
 */
function obtener_periodo_actual($fecha_ingreso) {
    if (empty($fecha_ingreso)) return null;
    
    try {
        $ingreso = new DateTime($fecha_ingreso);
        $hoy = new DateTime();
        $anios = calcular_antiguedad($fecha_ingreso)['anios_completos'];
        
        // El periodo actual va desde el último aniversario hasta el próximo
        $inicio_periodo = clone $ingreso;
        $inicio_periodo->modify("+{$anios} years");
        
        $fin_periodo = clone $inicio_periodo;
        $fin_periodo->modify('+1 year');
        $fin_periodo->modify('-1 day');
        
        return [
            'inicio'       => $inicio_periodo->format('Y-m-d'),
            'fin'          => $fin_periodo->format('Y-m-d'),
            'anio_periodo' => $anios + 1 // El año que está cursando
        ];
    } catch (Exception $e) {
        return null;
    }
}

// =====================================================
// RESUMEN COMPLETO DE VACACIONES
// =====================================================

/**
 * Obtener resumen completo de vacaciones de un empleado
 * Incluye: antigüedad, días LFT, tomados, disponibles, solicitudes
 * 
 * @param PDO $pdo
 * @param int $usuario_id
 * @return array
 */
function obtener_resumen_vacaciones($pdo, $usuario_id) {
    // Obtener datos del usuario
    $stmt = $pdo->prepare("
        SELECT u.fecha_ingreso, u.nombre_completo, u.departamento_id, u.no_nomina, u.puesto,
               d.nombre AS departamento_nombre
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE u.id = ?
    ");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario || empty($usuario['fecha_ingreso'])) {
        return [
            'tiene_fecha_ingreso'   => false,
            'antiguedad_texto'      => 'Sin fecha de ingreso registrada',
            'dias_correspondientes' => 0,
            'dias_tomados'          => 0,
            'dias_disponibles'      => 0,
            'periodo'               => null,
            'solicitudes'           => []
        ];
    }
    
    $fecha_ingreso = $usuario['fecha_ingreso'];
    $antiguedad = calcular_antiguedad($fecha_ingreso);
    $periodo = obtener_periodo_actual($fecha_ingreso);
    $anio_periodo = $periodo ? $periodo['anio_periodo'] : 0;
    $dias_correspondientes = dias_vacaciones_lft($anio_periodo);
    
    // Contar días tomados en el periodo actual (solicitudes no canceladas ni rechazadas)
    $dias_tomados = 0;
    if ($periodo) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(dias_solicitados), 0) as total_dias
            FROM solicitudes_vacaciones 
            WHERE usuario_id = ? 
            AND estado NOT IN ('cancelada', 'rechazada_admin', 'rechazada_gth')
            AND fecha_inicio >= ?
            AND fecha_inicio <= ?
        ");
        $stmt->execute([$usuario_id, $periodo['inicio'], $periodo['fin']]);
        $dias_tomados = (int)$stmt->fetchColumn();
    }
    
    // Obtener solicitudes del periodo actual
    $solicitudes = [];
    if ($periodo) {
        $stmt = $pdo->prepare("
            SELECT sv.*
            FROM solicitudes_vacaciones sv
            WHERE sv.usuario_id = ?
            AND sv.fecha_inicio >= ?
            AND sv.fecha_inicio <= ?
            ORDER BY sv.fecha_creacion DESC
        ");
        $stmt->execute([$usuario_id, $periodo['inicio'], $periodo['fin']]);
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return [
        'tiene_fecha_ingreso'   => true,
        'fecha_ingreso'         => $fecha_ingreso,
        'nombre_completo'       => $usuario['nombre_completo'],
        'no_nomina'             => $usuario['no_nomina'],
        'puesto'                => $usuario['puesto'],
        'departamento_nombre'   => $usuario['departamento_nombre'],
        'antiguedad'            => $antiguedad,
        'antiguedad_texto'      => texto_antiguedad($fecha_ingreso),
        'dias_correspondientes' => $dias_correspondientes,
        'dias_tomados'          => $dias_tomados,
        'dias_disponibles'      => max(0, $dias_correspondientes - $dias_tomados),
        'periodo'               => $periodo,
        'solicitudes'           => $solicitudes
    ];
}

// =====================================================
// FOLIO
// =====================================================

/**
 * Generar folio único para solicitud de vacaciones
 * Formato: VAC-YYYYMMDD-000X (consecutivo por día)
 * 
 * @param PDO $pdo
 * @return string
 */
function generar_folio_vacaciones($pdo) {
    $fecha = date('Ymd');
    $prefijo = "VAC-{$fecha}-";
    
    $stmt = $pdo->prepare("SELECT folio FROM solicitudes_vacaciones WHERE folio LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefijo . '%']);
    $ultimo = $stmt->fetchColumn();
    
    if ($ultimo) {
        $numero = intval(substr($ultimo, -4)) + 1;
    } else {
        $numero = 1;
    }
    
    return $prefijo . str_pad($numero, 4, '0', STR_PAD_LEFT);
}

// =====================================================
// ESTADOS Y BADGES
// =====================================================

/**
 * Badge HTML de estado para mostrar en tablas y cards
 */
function badge_estado_vacaciones($estado) {
    $badges = [
        'pendiente_admin'  => '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pendiente Admin</span>',
        'aprobada_admin'   => '<span class="badge bg-info text-dark"><i class="bi bi-check me-1"></i>Aprobada Admin</span>',
        'rechazada_admin'  => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rechazada Admin</span>',
        'pendiente_gth'    => '<span class="badge bg-primary"><i class="bi bi-hourglass-split me-1"></i>Pendiente GTH</span>',
        'aprobada_gth'     => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aprobada GTH</span>',
        'rechazada_gth'    => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rechazada GTH</span>',
        'completada'       => '<span class="badge bg-success"><i class="bi bi-check-all me-1"></i>Completada</span>',
        'cancelada'        => '<span class="badge bg-dark"><i class="bi bi-slash-circle me-1"></i>Cancelada</span>',
    ];
    return $badges[$estado] ?? '<span class="badge bg-secondary">' . htmlspecialchars($estado) . '</span>';
}

/**
 * Texto legible del estado
 */
function texto_estado_vacaciones($estado) {
    $textos = [
        'pendiente_admin'  => 'Pendiente de Admin. de Área',
        'aprobada_admin'   => 'Aprobada por Admin. de Área',
        'rechazada_admin'  => 'Rechazada por Admin. de Área',
        'pendiente_gth'    => 'Pendiente de GTH',
        'aprobada_gth'     => 'Aprobada por GTH',
        'rechazada_gth'    => 'Rechazada por GTH',
        'completada'       => 'Completada',
        'cancelada'        => 'Cancelada',
    ];
    return $textos[$estado] ?? $estado;
}

// =====================================================
// OBTENER SOLICITUD POR ID
// =====================================================

/**
 * Obtener solicitud de vacaciones con datos del usuario
 */
function obtener_solicitud_vacaciones($pdo, $solicitud_id) {
    $stmt = $pdo->prepare("
        SELECT sv.*, 
               u.nombre_completo, u.no_nomina, u.puesto, u.fecha_ingreso,
               d.nombre AS departamento_nombre,
               admin_u.nombre_completo AS admin_nombre,
               gth_u.nombre_completo AS gth_nombre
        FROM solicitudes_vacaciones sv
        LEFT JOIN usuarios u ON sv.usuario_id = u.id
        LEFT JOIN departamentos d ON sv.departamento_id = d.id
        LEFT JOIN usuarios admin_u ON sv.admin_id = admin_u.id
        LEFT JOIN usuarios gth_u ON sv.gth_id = gth_u.id
        WHERE sv.id = ?
    ");
    $stmt->execute([$solicitud_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// =====================================================
// OBTENER ADMINS DE ÁREA DEL DEPARTAMENTO
// =====================================================

/**
 * Obtener usuarios con rol es_admin_area de un departamento
 */
function obtener_admins_area($pdo, $departamento_id) {
    $stmt = $pdo->prepare("
        SELECT id, nombre_completo 
        FROM usuarios 
        WHERE departamento_id = ? AND es_admin_area = 1 AND activo = 1
    ");
    $stmt->execute([$departamento_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =====================================================
// FORMATEO DE FECHAS
// =====================================================

/**
 * Formatear fecha a español corto: "27 Feb 2026"
 */
function fecha_corta_es($fecha) {
    if (empty($fecha)) return '-';
    
    $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    
    try {
        $dt = new DateTime($fecha);
        return $dt->format('d') . ' ' . $meses[(int)$dt->format('m') - 1] . ' ' . $dt->format('Y');
    } catch (Exception $e) {
        return $fecha;
    }
}

/**
 * Formatear fecha completa: "Jueves, 27 de Febrero de 2026"
 */
function fecha_larga_es($fecha) {
    if (empty($fecha)) return '-';
    
    $dias_semana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    
    try {
        $dt = new DateTime($fecha);
        return $dias_semana[(int)$dt->format('w')] . ', ' . $dt->format('d') . ' de ' . $meses[(int)$dt->format('m') - 1] . ' de ' . $dt->format('Y');
    } catch (Exception $e) {
        return $fecha;
    }
}