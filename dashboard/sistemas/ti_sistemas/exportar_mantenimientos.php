<?php
/**
 * Exportar Mantenimientos a Excel
 * Solo accesible para usuarios del departamento de Sistemas
 * dashboard/sistemas/ti_sistemas/exportar_mantenimientos.php
 * 
 * Características:
 * - Filtros en columnas específicas
 * - Encabezados estáticos (freeze panes)
 * - Fechas y horas separadas
 * - Hoja de gráficas
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar que sea del departamento de Sistemas
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Obtener parámetros
$periodo = $_GET['periodo'] ?? 'mes';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// Conexión a BD
$pdo = conectarDB();

// Determinar rango de fechas según el periodo
if ($periodo === 'mes') {
    $fecha_inicio = date('Y-m-01');
    $fecha_fin = date('Y-m-t');
    $nombre_periodo = 'Mes_' . date('Y-m');
} elseif ($periodo === 'rango' && $fecha_inicio && $fecha_fin) {
    $nombre_periodo = 'Del_' . str_replace('-', '', $fecha_inicio) . '_al_' . str_replace('-', '', $fecha_fin);
} else {
    $fecha_inicio = date('Y-m-01');
    $fecha_fin = date('Y-m-t');
    $nombre_periodo = 'Mes_' . date('Y-m');
}

// Consulta para obtener mantenimientos finalizados
$sql = "SELECT 
            sm.folio,
            sm.tipo_equipo,
            ie.codigo_interno as equipo_codigo,
            CONCAT(IFNULL(ie.marca, ''), ' ', IFNULL(ie.modelo, '')) as equipo_descripcion,
            ie.ubicacion as equipo_ubicacion,
            d_equipo.nombre as equipo_departamento,
            u_solicitante.nombre_completo as solicitante,
            d_solicitante.nombre as departamento_solicitante,
            sm.tipo_mantenimiento,
            sm.descripcion_problema,
            sm.descripcion_solucion,
            sm.observaciones_sistemas,
            u_tecnico.nombre_completo as atendido_por,
            sm.fecha_solicitud,
            sm.fecha_atencion,
            sm.fecha_finalizacion,
            sm.estado
        FROM solicitudes_mantenimiento_ti sm
        LEFT JOIN inventario_equipos ie ON sm.equipo_id = ie.id
        LEFT JOIN departamentos d_equipo ON ie.departamento_id = d_equipo.id
        LEFT JOIN usuarios u_solicitante ON sm.usuario_id = u_solicitante.id
        LEFT JOIN departamentos d_solicitante ON sm.departamento_id = d_solicitante.id
        LEFT JOIN usuarios u_tecnico ON sm.atendido_por = u_tecnico.id
        WHERE sm.estado = 'finalizado'
        AND DATE(sm.fecha_finalizacion) BETWEEN ? AND ?
        ORDER BY sm.fecha_finalizacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$fecha_inicio, $fecha_fin]);
$mantenimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar si hay PhpSpreadsheet disponible
$phpspreadsheet_path = __DIR__ . '/../../../vendor/autoload.php';
$usar_phpspreadsheet = file_exists($phpspreadsheet_path);

if ($usar_phpspreadsheet) {
    require_once $phpspreadsheet_path;
    exportarConPhpSpreadsheet($mantenimientos, $nombre_periodo, $fecha_inicio, $fecha_fin);
} else {
    exportarComoCSV($mantenimientos, $nombre_periodo, $fecha_inicio, $fecha_fin);
}

/**
 * Exportar usando PhpSpreadsheet con todas las mejoras
 */
function exportarConPhpSpreadsheet($mantenimientos, $nombre_periodo, $fecha_inicio, $fecha_fin) {
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    
    // ═══════════════════════════════════════════════════════════════
    // HOJA 1: MANTENIMIENTOS
    // ═══════════════════════════════════════════════════════════════
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Mantenimientos');
    
    // Configurar propiedades del documento
    $spreadsheet->getProperties()
        ->setCreator('Sistema TI - VerdenCore')
        ->setTitle('Reporte de Mantenimientos')
        ->setSubject('Mantenimientos Finalizados')
        ->setDescription('Reporte generado automáticamente');
    
    // ─────────────────────────────────────────────────────────────────
    // ENCABEZADO DEL REPORTE (Filas 1-3)
    // ─────────────────────────────────────────────────────────────────
    $sheet->setCellValue('A1', 'REPORTE DE MANTENIMIENTOS FINALIZADOS');
    $sheet->mergeCells('A1:O1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('1a472a');
    $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
    
    $sheet->setCellValue('A2', 'Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)));
    $sheet->mergeCells('A2:O2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setItalic(true);
    
    $sheet->setCellValue('A3', 'Generado: ' . date('d/m/Y H:i:s') . ' | Total registros: ' . count($mantenimientos));
    $sheet->mergeCells('A3:O3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true);
    
    // ─────────────────────────────────────────────────────────────────
    // ENCABEZADOS DE COLUMNAS (Fila 5)
    // ─────────────────────────────────────────────────────────────────
    $encabezados = [
        'A5' => 'Folio',
        'B5' => 'Tipo Equipo',        // FILTRO
        'C5' => 'Código Equipo',
        'D5' => 'Equipo',
        'E5' => 'Solicitante',        // FILTRO
        'F5' => 'Departamento',       // FILTRO
        'G5' => 'Tipo Mant.',         // FILTRO
        'H5' => 'Descripción Problema',
        'I5' => 'Solución Aplicada',
        'J5' => 'Técnico',            // FILTRO
        'K5' => 'Fecha Entrada',
        'L5' => 'Hora Entrada',
        'M5' => 'Fecha Finalización',
        'N5' => 'Hora Finalización',
        'O5' => 'Tiempo Resolución'
    ];
    
    foreach ($encabezados as $celda => $texto) {
        $sheet->setCellValue($celda, $texto);
    }
    
    // Estilo de encabezados
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 10
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '198754']
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    $sheet->getStyle('A5:O5')->applyFromArray($headerStyle);
    $sheet->getRowDimension(5)->setRowHeight(30);
    
    // ─────────────────────────────────────────────────────────────────
    // DATOS (Desde fila 6)
    // ─────────────────────────────────────────────────────────────────
    $fila = 6;
    
    foreach ($mantenimientos as $m) {
        $tiempoTexto = calcularTiempoResolucion($m['fecha_solicitud'], $m['fecha_finalizacion']);
        
        // Separar fecha y hora de entrada
        $fechaEntrada = $m['fecha_solicitud'] ? date('d/m/Y', strtotime($m['fecha_solicitud'])) : '';
        $horaEntrada = $m['fecha_solicitud'] ? date('H:i', strtotime($m['fecha_solicitud'])) : '';
        
        // Separar fecha y hora de finalización
        $fechaFin = $m['fecha_finalizacion'] ? date('d/m/Y', strtotime($m['fecha_finalizacion'])) : '';
        $horaFin = $m['fecha_finalizacion'] ? date('H:i', strtotime($m['fecha_finalizacion'])) : '';
        
        $sheet->setCellValue('A' . $fila, $m['folio']);
        $sheet->setCellValue('B' . $fila, ucfirst($m['tipo_equipo']));
        $sheet->setCellValue('C' . $fila, $m['equipo_codigo'] ?? 'N/A');
        $sheet->setCellValue('D' . $fila, trim($m['equipo_descripcion']) ?: 'N/A');
        $sheet->setCellValue('E' . $fila, $m['solicitante']);
        $sheet->setCellValue('F' . $fila, $m['departamento_solicitante']);
        $sheet->setCellValue('G' . $fila, ucfirst($m['tipo_mantenimiento']));
        $sheet->setCellValue('H' . $fila, $m['descripcion_problema']);
        $sheet->setCellValue('I' . $fila, $m['descripcion_solucion'] ?? '');
        $sheet->setCellValue('J' . $fila, $m['atendido_por'] ?? 'Sin asignar');
        $sheet->setCellValue('K' . $fila, $fechaEntrada);
        $sheet->setCellValue('L' . $fila, $horaEntrada);
        $sheet->setCellValue('M' . $fila, $fechaFin);
        $sheet->setCellValue('N' . $fila, $horaFin);
        $sheet->setCellValue('O' . $fila, $tiempoTexto);
        
        // Alternar colores de filas
        if ($fila % 2 == 0) {
            $sheet->getStyle('A' . $fila . ':O' . $fila)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F8F9FA');
        }
        
        $fila++;
    }
    
    $ultimaFila = $fila - 1;
    
    // ─────────────────────────────────────────────────────────────────
    // APLICAR FILTROS (AutoFilter)
    // ─────────────────────────────────────────────────────────────────
    if ($ultimaFila >= 6) {
        $sheet->setAutoFilter('A5:O' . $ultimaFila);
    }
    
    // ─────────────────────────────────────────────────────────────────
    // CONGELAR ENCABEZADOS (Freeze Panes)
    // ─────────────────────────────────────────────────────────────────
    $sheet->freezePane('A6');
    
    // ─────────────────────────────────────────────────────────────────
    // BORDES Y FORMATO
    // ─────────────────────────────────────────────────────────────────
    if ($ultimaFila >= 6) {
        $sheet->getStyle('A5:O' . $ultimaFila)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Alineación de columnas específicas
        $sheet->getStyle('K6:N' . $ultimaFila)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('O6:O' . $ultimaFila)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }
    
    // ─────────────────────────────────────────────────────────────────
    // AJUSTAR ANCHO DE COLUMNAS
    // ─────────────────────────────────────────────────────────────────
    $anchos = [
        'A' => 18, 'B' => 14, 'C' => 14, 'D' => 20, 'E' => 22,
        'F' => 18, 'G' => 12, 'H' => 35, 'I' => 35, 'J' => 20,
        'K' => 14, 'L' => 12, 'M' => 16, 'N' => 16, 'O' => 18
    ];
    foreach ($anchos as $col => $ancho) {
        $sheet->getColumnDimension($col)->setWidth($ancho);
    }
    
    // ═══════════════════════════════════════════════════════════════
    // HOJA 2: GRÁFICAS
    // ═══════════════════════════════════════════════════════════════
    $sheetGraficas = $spreadsheet->createSheet();
    $sheetGraficas->setTitle('Gráficas');
    
    // Título
    $sheetGraficas->setCellValue('A1', 'ANÁLISIS GRÁFICO DE MANTENIMIENTOS');
    $sheetGraficas->mergeCells('A1:L1');
    $sheetGraficas->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheetGraficas->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheetGraficas->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('1a472a');
    $sheetGraficas->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
    
    $sheetGraficas->setCellValue('A2', 'Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)));
    $sheetGraficas->mergeCells('A2:L2');
    $sheetGraficas->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    // ─────────────────────────────────────────────────────────────────
    // PREPARAR DATOS PARA GRÁFICAS
    // ─────────────────────────────────────────────────────────────────
    $datosTipoEquipo = [];
    $datosSolicitante = [];
    $datosDepartamento = [];
    $datosTipoMant = [];
    $datosTecnico = [];
    $datosTiempoResolucion = [];
    
    foreach ($mantenimientos as $m) {
        // Tipo Equipo
        $tipo = ucfirst($m['tipo_equipo']);
        $datosTipoEquipo[$tipo] = ($datosTipoEquipo[$tipo] ?? 0) + 1;
        
        // Solicitante
        $sol = $m['solicitante'] ?? 'Sin nombre';
        $datosSolicitante[$sol] = ($datosSolicitante[$sol] ?? 0) + 1;
        
        // Departamento
        $depto = $m['departamento_solicitante'] ?? 'Sin departamento';
        $datosDepartamento[$depto] = ($datosDepartamento[$depto] ?? 0) + 1;
        
        // Tipo Mantenimiento
        $tipoMant = ucfirst($m['tipo_mantenimiento']);
        $datosTipoMant[$tipoMant] = ($datosTipoMant[$tipoMant] ?? 0) + 1;
        
        // Técnico
        $tec = $m['atendido_por'] ?? 'Sin asignar';
        $datosTecnico[$tec] = ($datosTecnico[$tec] ?? 0) + 1;
        
        // Tiempo de resolución (en horas, agrupado)
        $tiempoHoras = calcularMinutosTotales($m['fecha_solicitud'], $m['fecha_finalizacion']) / 60;
        if ($tiempoHoras <= 1) {
            $rango = '0-1 hora';
        } elseif ($tiempoHoras <= 4) {
            $rango = '1-4 horas';
        } elseif ($tiempoHoras <= 8) {
            $rango = '4-8 horas';
        } elseif ($tiempoHoras <= 24) {
            $rango = '8-24 horas';
        } elseif ($tiempoHoras <= 72) {
            $rango = '1-3 días';
        } else {
            $rango = 'Más de 3 días';
        }
        $datosTiempoResolucion[$rango] = ($datosTiempoResolucion[$rango] ?? 0) + 1;
    }
    
    // Ordenar tiempo de resolución
    $ordenTiempo = ['0-1 hora', '1-4 horas', '4-8 horas', '8-24 horas', '1-3 días', 'Más de 3 días'];
    $datosTiempoOrdenado = [];
    foreach ($ordenTiempo as $rango) {
        if (isset($datosTiempoResolucion[$rango])) {
            $datosTiempoOrdenado[$rango] = $datosTiempoResolucion[$rango];
        }
    }
    
    // Ordenar otros datos de mayor a menor
    arsort($datosSolicitante);
    arsort($datosDepartamento);
    arsort($datosTecnico);
    
    // Limitar a top 10 para solicitantes
    $datosSolicitante = array_slice($datosSolicitante, 0, 10, true);
    
    // ─────────────────────────────────────────────────────────────────
    // CREAR TABLAS DE DATOS Y GRÁFICAS (con posiciones fijas)
    // ─────────────────────────────────────────────────────────────────
    
    // Constantes de posicionamiento
    $ALTURA_GRAFICA = 15;  // Filas que ocupa cada gráfica
    $ESPACIO_ENTRE = 2;    // Filas de separación entre gráficas
    
    // ═══════════════════════════════════════════════════════════════
    // GRÁFICA 1: Tipo de Equipo (Fila 4)
    // ═══════════════════════════════════════════════════════════════
    $filaGrafica1 = 4;
    $sheetGraficas->setCellValue('A' . $filaGrafica1, 'Por Tipo de Equipo');
    $sheetGraficas->getStyle('A' . $filaGrafica1)->getFont()->setBold(true)->setSize(11);
    $sheetGraficas->getStyle('A' . $filaGrafica1)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E7F1FF');
    
    $filaData = $filaGrafica1 + 1;
    $inicioTipoEquipo = $filaData;
    foreach ($datosTipoEquipo as $categoria => $cantidad) {
        $sheetGraficas->setCellValue('A' . $filaData, $categoria);
        $sheetGraficas->setCellValue('B' . $filaData, $cantidad);
        $filaData++;
    }
    $finTipoEquipo = $filaData - 1;
    
    if ($finTipoEquipo >= $inicioTipoEquipo) {
        crearGraficaPastel($sheetGraficas, 'Por Tipo de Equipo', $inicioTipoEquipo, $finTipoEquipo, 'D' . $filaGrafica1, $filaGrafica1);
    }
    
    // ═══════════════════════════════════════════════════════════════
    // GRÁFICA 2: Tipo de Mantenimiento (Fila 21)
    // ═══════════════════════════════════════════════════════════════
    $filaGrafica2 = $filaGrafica1 + $ALTURA_GRAFICA + $ESPACIO_ENTRE;
    $sheetGraficas->setCellValue('A' . $filaGrafica2, 'Por Tipo de Mantenimiento');
    $sheetGraficas->getStyle('A' . $filaGrafica2)->getFont()->setBold(true)->setSize(11);
    $sheetGraficas->getStyle('A' . $filaGrafica2)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F3E8FF');
    
    $filaData = $filaGrafica2 + 1;
    $inicioTipoMant = $filaData;
    foreach ($datosTipoMant as $categoria => $cantidad) {
        $sheetGraficas->setCellValue('A' . $filaData, $categoria);
        $sheetGraficas->setCellValue('B' . $filaData, $cantidad);
        $filaData++;
    }
    $finTipoMant = $filaData - 1;
    
    if ($finTipoMant >= $inicioTipoMant) {
        crearGraficaDona($sheetGraficas, 'Por Tipo de Mantenimiento', $inicioTipoMant, $finTipoMant, 'D' . $filaGrafica2, $filaGrafica2);
    }
    
    // ═══════════════════════════════════════════════════════════════
    // GRÁFICA 3: Departamento (Fila 38)
    // ═══════════════════════════════════════════════════════════════
    $filaGrafica3 = $filaGrafica2 + $ALTURA_GRAFICA + $ESPACIO_ENTRE;
    $sheetGraficas->setCellValue('A' . $filaGrafica3, 'Por Departamento');
    $sheetGraficas->getStyle('A' . $filaGrafica3)->getFont()->setBold(true)->setSize(11);
    $sheetGraficas->getStyle('A' . $filaGrafica3)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('D1F7E0');
    
    $filaData = $filaGrafica3 + 1;
    $inicioDepto = $filaData;
    foreach ($datosDepartamento as $categoria => $cantidad) {
        $sheetGraficas->setCellValue('A' . $filaData, $categoria);
        $sheetGraficas->setCellValue('B' . $filaData, $cantidad);
        $filaData++;
    }
    $finDepto = $filaData - 1;
    
    if ($finDepto >= $inicioDepto) {
        crearGraficaPastel($sheetGraficas, 'Por Departamento', $inicioDepto, $finDepto, 'D' . $filaGrafica3, $filaGrafica3);
    }
    
    // ═══════════════════════════════════════════════════════════════
    // GRÁFICA 4: Técnico (Fila 55)
    // ═══════════════════════════════════════════════════════════════
    $filaGrafica4 = $filaGrafica3 + $ALTURA_GRAFICA + $ESPACIO_ENTRE;
    $sheetGraficas->setCellValue('A' . $filaGrafica4, 'Por Técnico');
    $sheetGraficas->getStyle('A' . $filaGrafica4)->getFont()->setBold(true)->setSize(11);
    $sheetGraficas->getStyle('A' . $filaGrafica4)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3E0');
    
    $filaData = $filaGrafica4 + 1;
    $inicioTecnico = $filaData;
    foreach ($datosTecnico as $categoria => $cantidad) {
        $sheetGraficas->setCellValue('A' . $filaData, $categoria);
        $sheetGraficas->setCellValue('B' . $filaData, $cantidad);
        $filaData++;
    }
    $finTecnico = $filaData - 1;
    
    if ($finTecnico >= $inicioTecnico) {
        crearGraficaBarrasH($sheetGraficas, 'Mantenimientos por Técnico', $inicioTecnico, $finTecnico, 'D' . $filaGrafica4, $filaGrafica4);
    }
    
    // ═══════════════════════════════════════════════════════════════
    // GRÁFICA 5: Top 10 Solicitantes (Fila 72)
    // ═══════════════════════════════════════════════════════════════
    $filaGrafica5 = $filaGrafica4 + $ALTURA_GRAFICA + $ESPACIO_ENTRE;
    $sheetGraficas->setCellValue('A' . $filaGrafica5, 'Top 10 Solicitantes');
    $sheetGraficas->getStyle('A' . $filaGrafica5)->getFont()->setBold(true)->setSize(11);
    $sheetGraficas->getStyle('A' . $filaGrafica5)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4EC');
    
    $filaData = $filaGrafica5 + 1;
    $inicioSolicitante = $filaData;
    foreach ($datosSolicitante as $categoria => $cantidad) {
        $sheetGraficas->setCellValue('A' . $filaData, $categoria);
        $sheetGraficas->setCellValue('B' . $filaData, $cantidad);
        $filaData++;
    }
    $finSolicitante = $filaData - 1;
    
    if ($finSolicitante >= $inicioSolicitante) {
        crearGraficaBarras($sheetGraficas, 'Top 10 Solicitantes', $inicioSolicitante, $finSolicitante, 'D' . $filaGrafica5, $filaGrafica5);
    }
    
    // ═══════════════════════════════════════════════════════════════
    // GRÁFICA 6: Tiempo de Resolución (Fila 89)
    // ═══════════════════════════════════════════════════════════════
    $filaGrafica6 = $filaGrafica5 + $ALTURA_GRAFICA + $ESPACIO_ENTRE;
    $sheetGraficas->setCellValue('A' . $filaGrafica6, 'Por Tiempo de Resolución');
    $sheetGraficas->getStyle('A' . $filaGrafica6)->getFont()->setBold(true)->setSize(11);
    $sheetGraficas->getStyle('A' . $filaGrafica6)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E3F2FD');
    
    $filaData = $filaGrafica6 + 1;
    $inicioTiempo = $filaData;
    foreach ($datosTiempoOrdenado as $categoria => $cantidad) {
        $sheetGraficas->setCellValue('A' . $filaData, $categoria);
        $sheetGraficas->setCellValue('B' . $filaData, $cantidad);
        $filaData++;
    }
    $finTiempo = $filaData - 1;
    
    if ($finTiempo >= $inicioTiempo) {
        crearGraficaBarras($sheetGraficas, 'Distribución de Tiempo de Resolución', $inicioTiempo, $finTiempo, 'D' . $filaGrafica6, $filaGrafica6);
    }
    
    // Ajustar anchos
    $sheetGraficas->getColumnDimension('A')->setWidth(25);
    $sheetGraficas->getColumnDimension('B')->setWidth(12);
    
    // ═══════════════════════════════════════════════════════════════
    // DESCARGAR ARCHIVO
    // ═══════════════════════════════════════════════════════════════
    $spreadsheet->setActiveSheetIndex(0);
    
    $filename = 'Mantenimientos_' . $nombre_periodo . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setIncludeCharts(true);
    $writer->save('php://output');
    exit;
}

/**
 * Crear gráfica de pastel
 */
function crearGraficaPastel($sheet, $titulo, $filaInicio, $filaFin, $posicion, $filaBase) {
    $dataSeriesLabels = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', "Gráficas!\$B\$1", null, 1)
    ];
    $xAxisTickValues = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', "Gráficas!\$A\${$filaInicio}:\$A\${$filaFin}", null, ($filaFin - $filaInicio + 1))
    ];
    $dataSeriesValues = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('Number', "Gráficas!\$B\${$filaInicio}:\$B\${$filaFin}", null, ($filaFin - $filaInicio + 1))
    ];
    
    $series = new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(
        \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_PIECHART,
        null,
        range(0, count($dataSeriesValues) - 1),
        $dataSeriesLabels,
        $xAxisTickValues,
        $dataSeriesValues
    );
    
    $layout = new \PhpOffice\PhpSpreadsheet\Chart\Layout();
    $layout->setShowPercent(true);
    
    $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea($layout, [$series]);
    $legend = new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_RIGHT, null, false);
    $titleObj = new \PhpOffice\PhpSpreadsheet\Chart\Title($titulo);
    
    $chart = new \PhpOffice\PhpSpreadsheet\Chart\Chart(
        'chart_pie_' . $filaBase,
        $titleObj,
        $legend,
        $plotArea
    );
    
    // Posición fija: columna D hasta J, altura de 14 filas
    $chart->setTopLeftPosition('D' . $filaBase);
    $chart->setBottomRightPosition('K' . ($filaBase + 14));
    
    $sheet->addChart($chart);
}

/**
 * Crear gráfica de dona
 */
function crearGraficaDona($sheet, $titulo, $filaInicio, $filaFin, $posicion, $filaBase) {
    $dataSeriesLabels = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', "Gráficas!\$B\$1", null, 1)
    ];
    $xAxisTickValues = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', "Gráficas!\$A\${$filaInicio}:\$A\${$filaFin}", null, ($filaFin - $filaInicio + 1))
    ];
    $dataSeriesValues = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('Number', "Gráficas!\$B\${$filaInicio}:\$B\${$filaFin}", null, ($filaFin - $filaInicio + 1))
    ];
    
    $series = new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(
        \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_DONUTCHART,
        null,
        range(0, count($dataSeriesValues) - 1),
        $dataSeriesLabels,
        $xAxisTickValues,
        $dataSeriesValues
    );
    
    $layout = new \PhpOffice\PhpSpreadsheet\Chart\Layout();
    $layout->setShowPercent(true);
    
    $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea($layout, [$series]);
    $legend = new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_RIGHT, null, false);
    $titleObj = new \PhpOffice\PhpSpreadsheet\Chart\Title($titulo);
    
    $chart = new \PhpOffice\PhpSpreadsheet\Chart\Chart(
        'chart_donut_' . $filaBase,
        $titleObj,
        $legend,
        $plotArea
    );
    
    // Posición fija: columna D hasta J, altura de 14 filas
    $chart->setTopLeftPosition('D' . $filaBase);
    $chart->setBottomRightPosition('K' . ($filaBase + 14));
    
    $sheet->addChart($chart);
}

/**
 * Crear gráfica de barras verticales
 */
function crearGraficaBarras($sheet, $titulo, $filaInicio, $filaFin, $posicion, $filaBase) {
    $dataSeriesLabels = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', "Gráficas!\$B\$1", null, 1)
    ];
    $xAxisTickValues = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', "Gráficas!\$A\${$filaInicio}:\$A\${$filaFin}", null, ($filaFin - $filaInicio + 1))
    ];
    $dataSeriesValues = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('Number', "Gráficas!\$B\${$filaInicio}:\$B\${$filaFin}", null, ($filaFin - $filaInicio + 1))
    ];
    
    $series = new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(
        \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_BARCHART,
        \PhpOffice\PhpSpreadsheet\Chart\DataSeries::GROUPING_CLUSTERED,
        range(0, count($dataSeriesValues) - 1),
        $dataSeriesLabels,
        $xAxisTickValues,
        $dataSeriesValues
    );
    $series->setPlotDirection(\PhpOffice\PhpSpreadsheet\Chart\DataSeries::DIRECTION_COL);
    
    $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea(null, [$series]);
    $legend = new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_BOTTOM, null, false);
    $titleObj = new \PhpOffice\PhpSpreadsheet\Chart\Title($titulo);
    
    $chart = new \PhpOffice\PhpSpreadsheet\Chart\Chart(
        'chart_bar_' . $filaBase,
        $titleObj,
        $legend,
        $plotArea
    );
    
    // Posición fija: columna D hasta K, altura de 14 filas
    $chart->setTopLeftPosition('D' . $filaBase);
    $chart->setBottomRightPosition('K' . ($filaBase + 14));
    
    $sheet->addChart($chart);
}

/**
 * Crear gráfica de barras horizontales
 */
function crearGraficaBarrasH($sheet, $titulo, $filaInicio, $filaFin, $posicion, $filaBase) {
    $dataSeriesLabels = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', "Gráficas!\$B\$1", null, 1)
    ];
    $xAxisTickValues = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', "Gráficas!\$A\${$filaInicio}:\$A\${$filaFin}", null, ($filaFin - $filaInicio + 1))
    ];
    $dataSeriesValues = [
        new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('Number', "Gráficas!\$B\${$filaInicio}:\$B\${$filaFin}", null, ($filaFin - $filaInicio + 1))
    ];
    
    $series = new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(
        \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_BARCHART,
        \PhpOffice\PhpSpreadsheet\Chart\DataSeries::GROUPING_CLUSTERED,
        range(0, count($dataSeriesValues) - 1),
        $dataSeriesLabels,
        $xAxisTickValues,
        $dataSeriesValues
    );
    $series->setPlotDirection(\PhpOffice\PhpSpreadsheet\Chart\DataSeries::DIRECTION_BAR);
    
    $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea(null, [$series]);
    $legend = new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_BOTTOM, null, false);
    $titleObj = new \PhpOffice\PhpSpreadsheet\Chart\Title($titulo);
    
    $chart = new \PhpOffice\PhpSpreadsheet\Chart\Chart(
        'chart_barh_' . $filaBase,
        $titleObj,
        $legend,
        $plotArea
    );
    
    // Posición fija: columna D hasta K, altura de 14 filas
    $chart->setTopLeftPosition('D' . $filaBase);
    $chart->setBottomRightPosition('K' . ($filaBase + 14));
    
    $sheet->addChart($chart);
}

/**
 * Exportar como CSV (fallback)
 */
function exportarComoCSV($mantenimientos, $nombre_periodo, $fecha_inicio, $fecha_fin) {
    $filename = 'Mantenimientos_' . $nombre_periodo . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['REPORTE DE MANTENIMIENTOS FINALIZADOS'], ';');
    fputcsv($output, ['Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin))], ';');
    fputcsv($output, ['Generado: ' . date('d/m/Y H:i:s')], ';');
    fputcsv($output, [], ';');
    
    fputcsv($output, [
        'Folio', 'Tipo Equipo', 'Código Equipo', 'Equipo', 'Solicitante', 'Departamento',
        'Tipo Mantenimiento', 'Descripción del Problema', 'Solución Aplicada', 'Técnico',
        'Fecha Entrada', 'Hora Entrada', 'Fecha Finalización', 'Hora Finalización', 'Tiempo de Resolución'
    ], ';');
    
    foreach ($mantenimientos as $m) {
        $tiempoTexto = calcularTiempoResolucion($m['fecha_solicitud'], $m['fecha_finalizacion']);
        
        fputcsv($output, [
            $m['folio'],
            ucfirst($m['tipo_equipo']),
            $m['equipo_codigo'] ?? 'N/A',
            trim($m['equipo_descripcion']) ?: 'N/A',
            $m['solicitante'],
            $m['departamento_solicitante'],
            ucfirst($m['tipo_mantenimiento']),
            $m['descripcion_problema'],
            $m['descripcion_solucion'] ?? '',
            $m['atendido_por'] ?? 'Sin asignar',
            $m['fecha_solicitud'] ? date('d/m/Y', strtotime($m['fecha_solicitud'])) : '',
            $m['fecha_solicitud'] ? date('H:i', strtotime($m['fecha_solicitud'])) : '',
            $m['fecha_finalizacion'] ? date('d/m/Y', strtotime($m['fecha_finalizacion'])) : '',
            $m['fecha_finalizacion'] ? date('H:i', strtotime($m['fecha_finalizacion'])) : '',
            $tiempoTexto
        ], ';');
    }
    
    fputcsv($output, [], ';');
    fputcsv($output, ['Total de mantenimientos: ' . count($mantenimientos)], ';');
    
    fclose($output);
    exit;
}

/**
 * Calcular tiempo de resolución (texto)
 */
function calcularTiempoResolucion($fecha_inicio, $fecha_fin) {
    if (!$fecha_inicio || !$fecha_fin) {
        return 'N/A';
    }
    
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $diferencia = $inicio->diff($fin);
    
    $partes = [];
    
    if ($diferencia->days > 0) {
        $partes[] = $diferencia->days . ' día' . ($diferencia->days > 1 ? 's' : '');
    }
    
    if ($diferencia->h > 0) {
        $partes[] = $diferencia->h . ' hr' . ($diferencia->h > 1 ? 's' : '');
    }
    
    if ($diferencia->i > 0 && $diferencia->days == 0) {
        $partes[] = $diferencia->i . ' min';
    }
    
    if (empty($partes)) {
        return 'Menos de 1 min';
    }
    
    return implode(' ', $partes);
}

/**
 * Calcular minutos totales (para gráficas)
 */
function calcularMinutosTotales($fecha_inicio, $fecha_fin) {
    if (!$fecha_inicio || !$fecha_fin) {
        return 0;
    }
    
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $diferencia = $inicio->diff($fin);
    
    return ($diferencia->days * 24 * 60) + ($diferencia->h * 60) + $diferencia->i;
}