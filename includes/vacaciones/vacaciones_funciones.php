<?php
/**
 * Funciones del Modulo de Vacaciones
 * includes/vacaciones/vacaciones_funciones.php
 * 
 * Contiene: Tabla LFT, calculos de antiguedad, dias habiles (con festivos + jornada),
 *           resumen del empleado, generacion de folios, dias festivos MX
 */

// =====================================================
// TABLA LFT - Articulo 76 (Reforma 2023)
// =====================================================

function dias_vacaciones_lft($anios_antiguedad) {
    if ($anios_antiguedad <= 0) return 0;
    $tabla_fija = [1 => 12, 2 => 14, 3 => 16, 4 => 18, 5 => 20];
    if ($anios_antiguedad <= 5) return $tabla_fija[$anios_antiguedad];
    $bloque = ceil(($anios_antiguedad - 5) / 5);
    return 20 + ($bloque * 2);
}

// =====================================================
// CALCULOS DE ANTIGUEDAD
// =====================================================

function calcular_antiguedad($fecha_ingreso) {
    if (empty($fecha_ingreso)) return ['anios' => 0, 'meses' => 0, 'dias' => 0, 'anios_completos' => 0];
    try {
        $ingreso = new DateTime($fecha_ingreso);
        $hoy = new DateTime();
        $diff = $ingreso->diff($hoy);
        return ['anios' => $diff->y, 'meses' => $diff->m, 'dias' => $diff->d, 'anios_completos' => $diff->y];
    } catch (Exception $e) {
        return ['anios' => 0, 'meses' => 0, 'dias' => 0, 'anios_completos' => 0];
    }
}

function texto_antiguedad($fecha_ingreso) {
    $ant = calcular_antiguedad($fecha_ingreso);
    $partes = [];
    if ($ant['anios'] > 0) $partes[] = $ant['anios'] . ' año' . ($ant['anios'] > 1 ? 's' : '');
    if ($ant['meses'] > 0) $partes[] = $ant['meses'] . ' mes' . ($ant['meses'] > 1 ? 'es' : '');
    if (empty($partes) && $ant['dias'] > 0) $partes[] = $ant['dias'] . ' día' . ($ant['dias'] > 1 ? 's' : '');
    return !empty($partes) ? implode(', ', $partes) : 'Sin fecha de ingreso';
}

// =====================================================
// DIAS FESTIVOS OFICIALES MEXICO (LFT)
// =====================================================

/**
 * Calcular dias festivos oficiales de Mexico para un anio dado
 * Incluye: 1 Ene, 1er Lun Feb, 3er Lun Mar, 1 May, 16 Sep, 3er Lun Nov, 25 Dic
 * 
 * @param int $anio
 * @return array [['fecha' => 'Y-m-d', 'nombre' => '...'], ...]
 */
function obtener_festivos_oficiales($anio) {
    $festivos = [];
    
    // Fijos
    $festivos[] = ['fecha' => "$anio-01-01", 'nombre' => 'Año Nuevo'];
    $festivos[] = ['fecha' => "$anio-05-01", 'nombre' => 'Día del Trabajo'];
    $festivos[] = ['fecha' => "$anio-09-16", 'nombre' => 'Día de la Independencia'];
    $festivos[] = ['fecha' => "$anio-12-25", 'nombre' => 'Navidad'];
    
    // 1er lunes de febrero (Constitución)
    $feb1 = new DateTime("$anio-02-01");
    $dow = (int)$feb1->format('w'); // 0=Dom...6=Sab
    $offset = ($dow <= 1) ? (1 - $dow) : (8 - $dow);
    $feb1->modify("+{$offset} days");
    $festivos[] = ['fecha' => $feb1->format('Y-m-d'), 'nombre' => 'Día de la Constitución'];
    
    // 3er lunes de marzo (Natalicio Benito Juárez)
    $mar1 = new DateTime("$anio-03-01");
    $dow = (int)$mar1->format('w');
    $offset = ($dow <= 1) ? (1 - $dow) : (8 - $dow);
    $mar1->modify("+{$offset} days"); // 1er lunes
    $mar1->modify('+14 days'); // 3er lunes
    $festivos[] = ['fecha' => $mar1->format('Y-m-d'), 'nombre' => 'Natalicio de Benito Juárez'];
    
    // 3er lunes de noviembre (Revolución)
    $nov1 = new DateTime("$anio-11-01");
    $dow = (int)$nov1->format('w');
    $offset = ($dow <= 1) ? (1 - $dow) : (8 - $dow);
    $nov1->modify("+{$offset} days");
    $nov1->modify('+14 days');
    $festivos[] = ['fecha' => $nov1->format('Y-m-d'), 'nombre' => 'Día de la Revolución'];
    
    return $festivos;
}

/**
 * Obtener TODOS los dias festivos (oficiales + empresa) para un rango de fechas
 * 
 * @param PDO|null $pdo Conexion a BD (para extras empresa). NULL = solo oficiales
 * @param string $fecha_inicio Y-m-d
 * @param string $fecha_fin Y-m-d
 * @return array Fechas como strings 'Y-m-d'
 */
function obtener_dias_festivos($pdo, $fecha_inicio, $fecha_fin) {
    $festivos = [];
    
    // Obtener anios involucrados
    $anio_ini = (int)substr($fecha_inicio, 0, 4);
    $anio_fin = (int)substr($fecha_fin, 0, 4);
    
    // Oficiales
    for ($a = $anio_ini; $a <= $anio_fin; $a++) {
        foreach (obtener_festivos_oficiales($a) as $f) {
            if ($f['fecha'] >= $fecha_inicio && $f['fecha'] <= $fecha_fin) {
                $festivos[] = $f['fecha'];
            }
        }
    }
    
    // Extras empresa (desde BD)
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT fecha FROM dias_festivos_empresa WHERE activo = 1 AND fecha BETWEEN ? AND ?");
            $stmt->execute([$fecha_inicio, $fecha_fin]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!in_array($row['fecha'], $festivos)) {
                    $festivos[] = $row['fecha'];
                }
            }
        } catch (Exception $e) {
            // Tabla puede no existir aun, ignorar
        }
    }
    
    return $festivos;
}

/**
 * Obtener todos los festivos de un anio completo como array de strings
 * Util para pasar al frontend como JSON
 * 
 * @param PDO|null $pdo
 * @param int $anio
 * @return array [['fecha' => 'Y-m-d', 'nombre' => '...'], ...]
 */
function obtener_festivos_anio_completo($pdo, $anio) {
    $todos = [];
    
    // Oficiales
    foreach (obtener_festivos_oficiales($anio) as $f) {
        $todos[] = $f;
    }
    
    // Extras empresa
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT fecha, nombre FROM dias_festivos_empresa WHERE activo = 1 AND YEAR(fecha) = ?");
            $stmt->execute([$anio]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $todos[] = ['fecha' => $row['fecha'], 'nombre' => $row['nombre']];
            }
        } catch (Exception $e) {}
    }
    
    // Ordenar por fecha
    usort($todos, function($a, $b) { return strcmp($a['fecha'], $b['fecha']); });
    
    return $todos;
}

// =====================================================
// CONTEO DE DIAS HABILES (con jornada + festivos)
// =====================================================

/**
 * Contar dias habiles entre dos fechas
 * Respeta jornada (L-V o L-S) y excluye dias festivos
 * 
 * @param string $fecha_inicio YYYY-MM-DD
 * @param string $fecha_fin YYYY-MM-DD
 * @param string $jornada 'lunes_viernes' o 'lunes_sabado' (default)
 * @param PDO|null $pdo Conexion BD para festivos empresa (null = solo oficiales)
 * @return int
 */
function contar_dias_habiles($fecha_inicio, $fecha_fin, $jornada = 'lunes_sabado', $pdo = null) {
    if (empty($fecha_inicio) || empty($fecha_fin)) return 0;
    
    try {
        $inicio = new DateTime($fecha_inicio);
        $fin = new DateTime($fecha_fin);
        if ($inicio > $fin) return 0;
        
        // Obtener festivos del rango
        $festivos = obtener_dias_festivos($pdo, $fecha_inicio, $fecha_fin);
        
        $dias = 0;
        $current = clone $inicio;
        $es_lv = ($jornada === 'lunes_viernes');
        
        while ($current <= $fin) {
            $dia_semana = (int)$current->format('w'); // 0=Dom, 1=Lun...5=Vie, 6=Sab
            $fecha_str = $current->format('Y-m-d');
            
            // Excluir domingos siempre
            if ($dia_semana === 0) {
                $current->modify('+1 day');
                continue;
            }
            
            // Excluir sabados si jornada es L-V
            if ($es_lv && $dia_semana === 6) {
                $current->modify('+1 day');
                continue;
            }
            
            // Excluir dias festivos
            if (in_array($fecha_str, $festivos)) {
                $current->modify('+1 day');
                continue;
            }
            
            $dias++;
            $current->modify('+1 day');
        }
        
        return $dias;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Calcular fecha de regreso (siguiente dia habil despues de fecha_fin)
 * 
 * @param string $fecha_fin YYYY-MM-DD
 * @param string $jornada
 * @param PDO|null $pdo
 * @return string YYYY-MM-DD
 */
function calcular_fecha_regreso($fecha_fin, $jornada = 'lunes_sabado', $pdo = null) {
    try {
        $regreso = new DateTime($fecha_fin);
        $regreso->modify('+1 day');
        $es_lv = ($jornada === 'lunes_viernes');
        
        // Buscar festivos del mes siguiente por seguridad
        $fin_busqueda = clone $regreso;
        $fin_busqueda->modify('+15 days');
        $festivos = obtener_dias_festivos($pdo, $regreso->format('Y-m-d'), $fin_busqueda->format('Y-m-d'));
        
        // Avanzar hasta encontrar dia habil
        for ($i = 0; $i < 30; $i++) {
            $dow = (int)$regreso->format('w');
            $fecha_str = $regreso->format('Y-m-d');
            
            if ($dow === 0) { $regreso->modify('+1 day'); continue; }
            if ($es_lv && $dow === 6) { $regreso->modify('+1 day'); continue; }
            if (in_array($fecha_str, $festivos)) { $regreso->modify('+1 day'); continue; }
            
            break;
        }
        
        return $regreso->format('Y-m-d');
    } catch (Exception $e) {
        return $fecha_fin;
    }
}

// =====================================================
// PERIODO VACACIONAL
// =====================================================

function obtener_periodo_actual($fecha_ingreso) {
    if (empty($fecha_ingreso)) return null;
    try {
        $ingreso = new DateTime($fecha_ingreso);
        $hoy = new DateTime();
        $anios = calcular_antiguedad($fecha_ingreso)['anios_completos'];
        $inicio_periodo = clone $ingreso;
        $inicio_periodo->modify("+{$anios} years");
        $fin_periodo = clone $inicio_periodo;
        $fin_periodo->modify('+1 year');
        $fin_periodo->modify('-1 day');
        return [
            'inicio'       => $inicio_periodo->format('Y-m-d'),
            'fin'          => $fin_periodo->format('Y-m-d'),
            'anio_periodo' => $anios + 1
        ];
    } catch (Exception $e) {
        return null;
    }
}

// =====================================================
// PERIODO ANTERIOR + DIAS PENDIENTES + VENCIMIENTO
// =====================================================

/**
 * Obtener datos del periodo vacacional ANTERIOR
 * 
 * @param string $fecha_ingreso
 * @return array|null ['inicio', 'fin', 'anio_periodo', 'fecha_vencimiento', 'vencido']
 */
function obtener_periodo_anterior($fecha_ingreso) {
    if (empty($fecha_ingreso)) return null;
    
    try {
        $ingreso = new DateTime($fecha_ingreso);
        $hoy = new DateTime();
        $anios = calcular_antiguedad($fecha_ingreso)['anios_completos'];
        
        // Si tiene menos de 1 anio, no hay periodo anterior
        if ($anios < 1) return null;
        
        // Periodo anterior = un periodo atras
        $anios_anterior = $anios - 1;
        $inicio_anterior = clone $ingreso;
        $inicio_anterior->modify("+{$anios_anterior} years");
        
        $fin_anterior = clone $inicio_anterior;
        $fin_anterior->modify('+1 year');
        $fin_anterior->modify('-1 day');
        
        // Vencimiento = fin del periodo anterior + 18 meses (1.5 anios)
        $fecha_vencimiento = clone $fin_anterior;
        $fecha_vencimiento->modify('+18 months');
        
        $vencido = ($hoy > $fecha_vencimiento);
        
        return [
            'inicio'             => $inicio_anterior->format('Y-m-d'),
            'fin'                => $fin_anterior->format('Y-m-d'),
            'anio_periodo'       => $anios_anterior + 1,
            'fecha_vencimiento'  => $fecha_vencimiento->format('Y-m-d'),
            'vencido'            => $vencido
        ];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Obtener dias pendientes del periodo anterior de un empleado
 * Si vencidos (>1.5 anios), retorna 0 (desaparecen completamente)
 * 
 * @param PDO $pdo
 * @param int $usuario_id
 * @return array ['dias_pendientes' => int, 'periodo' => array|null, 'vencido' => bool, 'dias_lft' => int, 'dias_tomados' => int]
 */
function obtener_dias_periodo_anterior($pdo, $usuario_id) {
    $resultado = [
        'dias_pendientes' => 0,
        'periodo' => null,
        'vencido' => false,
        'dias_lft' => 0,
        'dias_tomados' => 0
    ];
    
    // Obtener fecha de ingreso
    $stmt = $pdo->prepare("SELECT fecha_ingreso FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $fecha_ingreso = $stmt->fetchColumn();
    
    if (empty($fecha_ingreso)) return $resultado;
    
    $periodo_ant = obtener_periodo_anterior($fecha_ingreso);
    if (!$periodo_ant) return $resultado;
    
    $resultado['periodo'] = $periodo_ant;
    $resultado['vencido'] = $periodo_ant['vencido'];
    
    // Si ya vencio, desaparecen completamente
    if ($periodo_ant['vencido']) return $resultado;
    
    // Dias LFT del periodo anterior
    $dias_lft = dias_vacaciones_lft($periodo_ant['anio_periodo']);
    $resultado['dias_lft'] = $dias_lft;
    
    // Dias tomados en el periodo anterior
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(dias_solicitados), 0)
        FROM solicitudes_vacaciones
        WHERE usuario_id = ?
        AND estado NOT IN ('cancelada', 'rechazada_admin', 'rechazada_gth')
        AND fecha_inicio >= ?
        AND fecha_inicio <= ?
    ");
    $stmt->execute([$usuario_id, $periodo_ant['inicio'], $periodo_ant['fin']]);
    $dias_tomados = (int)$stmt->fetchColumn();
    $resultado['dias_tomados'] = $dias_tomados;
    
    // Dias pendientes = LFT - tomados (minimo 0)
    $resultado['dias_pendientes'] = max(0, $dias_lft - $dias_tomados);
    
    return $resultado;
}

// =====================================================
// RESUMEN COMPLETO DE VACACIONES
// =====================================================

function obtener_resumen_vacaciones($pdo, $usuario_id) {
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
    
    // Periodo anterior: dias pendientes (no vencidos)
    $periodo_anterior = obtener_dias_periodo_anterior($pdo, $usuario_id);
    
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
        'solicitudes'           => $solicitudes,
        'periodo_anterior'      => $periodo_anterior
    ];
}

// =====================================================
// FOLIO
// =====================================================

function generar_folio_vacaciones($pdo) {
    $fecha = date('Ymd');
    $prefijo = "VAC-{$fecha}-";
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(folio, -4) AS UNSIGNED)) FROM solicitudes_vacaciones WHERE folio LIKE ?");
    $stmt->execute([$prefijo . '%']);
    $max = $stmt->fetchColumn();
    $numero = ($max !== null && $max !== false) ? intval($max) + 1 : 1;
    $folio = $prefijo . str_pad($numero, 4, '0', STR_PAD_LEFT);
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM solicitudes_vacaciones WHERE folio = ?");
    $stmt2->execute([$folio]);
    while ((int)$stmt2->fetchColumn() > 0) {
        $numero++;
        $folio = $prefijo . str_pad($numero, 4, '0', STR_PAD_LEFT);
        $stmt2->execute([$folio]);
    }
    return $folio;
}

// =====================================================
// ESTADOS Y BADGES
// =====================================================

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
// OBTENER ADMINS DE AREA DEL DEPARTAMENTO
// =====================================================

function obtener_admins_area($pdo, $departamento_id) {
    $stmt = $pdo->prepare("SELECT id, nombre_completo FROM usuarios WHERE departamento_id = ? AND es_admin_area = 1 AND activo = 1");
    $stmt->execute([$departamento_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =====================================================
// FORMATEO DE FECHAS
// =====================================================

function fecha_corta_es($fecha) {
    if (empty($fecha)) return '-';
    $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    try {
        $dt = new DateTime($fecha);
        return $dt->format('d') . ' ' . $meses[(int)$dt->format('m') - 1] . ' ' . $dt->format('Y');
    } catch (Exception $e) { return $fecha; }
}

function fecha_larga_es($fecha) {
    if (empty($fecha)) return '-';
    $dias_semana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    try {
        $dt = new DateTime($fecha);
        return $dias_semana[(int)$dt->format('w')] . ', ' . $dt->format('d') . ' de ' . $meses[(int)$dt->format('m') - 1] . ' de ' . $dt->format('Y');
    } catch (Exception $e) { return $fecha; }
}