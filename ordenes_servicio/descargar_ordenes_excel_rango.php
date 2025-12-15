<?php
/**
 * Descargar Base de Datos de Órdenes de Servicio en Excel - POR RANGO DE FECHAS
 * EXCLUSIVO para el departamento de Mantenimiento
 * 
 * Genera un archivo Excel con las órdenes dentro del rango de fechas especificado
 * incluyendo cálculos de días y horas de mantenimiento
 * y una hoja de Resultados con gráficos
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/login.php');
    exit;
}

// Verificar que sea departamento de Mantenimiento
$departamento_codigo = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
if ($departamento_codigo !== 'mantenimiento') {
    $_SESSION['error'] = "Solo el departamento de Mantenimiento puede descargar la base de datos.";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
    exit;
}

// Obtener y validar parámetros de fecha
$fecha_desde = $_GET['fecha_desde'] ?? null;
$fecha_hasta = $_GET['fecha_hasta'] ?? null;

// Validar que ambas fechas estén presentes
if (empty($fecha_desde) || empty($fecha_hasta)) {
    $_SESSION['error'] = "Debe especificar ambas fechas (desde y hasta).";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
    exit;
}

// Validar formato de fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $_SESSION['error'] = "Formato de fecha inválido. Use YYYY-MM-DD.";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
    exit;
}

// Validar que fecha desde no sea mayor que fecha hasta
if ($fecha_desde > $fecha_hasta) {
    $_SESSION['error'] = "La fecha 'Desde' no puede ser mayor que la fecha 'Hasta'.";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
    exit;
}

// Requerir PhpSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Layout;

/**
 * Calcula los días de mantenimiento (excluyendo domingos)
 * Solo cuenta lunes a sábado
 */
function calcularDiasMantenimiento($fecha_inicio, $fecha_fin) {
    if (empty($fecha_inicio) || empty($fecha_fin)) {
        return '';
    }
    
    try {
        $inicio = new DateTime($fecha_inicio);
        $fin = new DateTime($fecha_fin);
        
        if ($inicio > $fin) {
            return 0;
        }
        
        $dias = 0;
        $current = clone $inicio;
        
        while ($current <= $fin) {
            $diaSemana = (int)$current->format('w');
            if ($diaSemana >= 1 && $diaSemana <= 6) {
                $dias++;
            }
            $current->modify('+1 day');
        }
        
        return $dias;
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Calcula las horas de mantenimiento dentro del horario laboral
 * L-V: 8:30 a 17:00 (8.5 horas)
 * S: 8:30 a 14:00 (5.5 horas)
 * Domingos: No se cuentan
 */
function calcularHorasMantenimiento($fecha_inicio, $hora_inicio, $fecha_fin, $hora_fin) {
    if (empty($fecha_inicio) || empty($hora_inicio) || empty($fecha_fin) || empty($hora_fin)) {
        return '';
    }
    
    try {
        $inicio = new DateTime($fecha_inicio . ' ' . $hora_inicio);
        $fin = new DateTime($fecha_fin . ' ' . $hora_fin);
        
        if ($inicio > $fin) {
            return 0;
        }
        
        $horasTotales = 0;
        $current = clone $inicio;
        
        $horaInicioLaboral = '08:30';
        $horaFinLaboralLV = '17:00';
        $horaFinLaboralS = '14:00';
        
        while ($current->format('Y-m-d') <= $fin->format('Y-m-d')) {
            $diaSemana = (int)$current->format('w');
            $fechaActual = $current->format('Y-m-d');
            
            if ($diaSemana === 0) {
                $current->modify('+1 day');
                $current->setTime(8, 30, 0);
                continue;
            }
            
            $horaFinLaboral = ($diaSemana === 6) ? $horaFinLaboralS : $horaFinLaboralLV;
            
            $inicioLaboral = new DateTime($fechaActual . ' ' . $horaInicioLaboral);
            $finLaboral = new DateTime($fechaActual . ' ' . $horaFinLaboral);
            
            if ($fechaActual === $inicio->format('Y-m-d')) {
                $horaEfectivaInicio = max($current, $inicioLaboral);
            } else {
                $horaEfectivaInicio = $inicioLaboral;
            }
            
            if ($fechaActual === $fin->format('Y-m-d')) {
                $horaEfectivaFin = min($fin, $finLaboral);
            } else {
                $horaEfectivaFin = $finLaboral;
            }
            
            if ($horaEfectivaInicio < $inicioLaboral) {
                $horaEfectivaInicio = $inicioLaboral;
            }
            if ($horaEfectivaFin > $finLaboral) {
                $horaEfectivaFin = $finLaboral;
            }
            
            if ($horaEfectivaInicio < $horaEfectivaFin) {
                $diff = $horaEfectivaInicio->diff($horaEfectivaFin);
                $horasDelDia = $diff->h + ($diff->i / 60);
                $horasTotales += $horasDelDia;
            }
            
            $current->modify('+1 day');
            $current->setTime(8, 30, 0);
        }
        
        return round($horasTotales, 2);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Traduce el estado a texto legible
 */
function traducirEstado($estado) {
    $estados = [
        'pendiente_mantenimiento' => 'Pendiente de Mantenimiento',
        'en_proceso' => 'En Proceso',
        'pendiente_usuario' => 'Pendiente de Validación',
        'devuelto' => 'Devuelto para Corrección',
        'completado' => 'Completado'
    ];
    return $estados[$estado] ?? $estado;
}

/**
 * Crea un gráfico de pastel (Pie)
 */
function crearGraficoPastel($hoja, $titulo, $rangoLabels, $rangoValores, $posicionCelda, $anchoColumnas = 8, $altoFilas = 15) {
    $dataSeriesLabels = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $hoja . '!' . $rangoLabels, null, 1)
    ];
    
    $xAxisTickValues = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $hoja . '!' . $rangoLabels, null, 4)
    ];
    
    $dataSeriesValues = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $hoja . '!' . $rangoValores, null, 4)
    ];
    
    $series = new DataSeries(
        DataSeries::TYPE_PIECHART,
        null,
        range(0, count($dataSeriesValues) - 1),
        $dataSeriesLabels,
        $xAxisTickValues,
        $dataSeriesValues
    );
    
    $layout = new Layout();
    $layout->setShowVal(true);
    $layout->setShowPercent(true);
    
    $plotArea = new PlotArea($layout, [$series]);
    $legend = new Legend(Legend::POSITION_RIGHT, null, false);
    $title = new Title($titulo);
    
    $chart = new Chart(
        'chart_' . str_replace(' ', '_', $titulo),
        $title,
        $legend,
        $plotArea,
        true,
        DataSeries::EMPTY_AS_GAP,
        null,
        null
    );
    
    $chart->setTopLeftPosition($posicionCelda);
    $chart->setBottomRightPosition(
        chr(ord(substr($posicionCelda, 0, 1)) + $anchoColumnas) . (intval(substr($posicionCelda, 1)) + $altoFilas)
    );
    
    return $chart;
}

/**
 * Crea un gráfico de barras
 */
function crearGraficoBarras($hoja, $titulo, $rangoLabels, $rangoValores, $posicionCelda, $anchoColumnas = 10, $altoFilas = 15, $horizontal = false) {
    $dataSeriesLabels = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, $titulo, 1)
    ];
    
    $xAxisTickValues = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $hoja . '!' . $rangoLabels, null, 10)
    ];
    
    $dataSeriesValues = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $hoja . '!' . $rangoValores, null, 10)
    ];
    
    $series = new DataSeries(
        $horizontal ? DataSeries::TYPE_BARCHART : DataSeries::TYPE_BARCHART,
        DataSeries::GROUPING_STANDARD,
        range(0, count($dataSeriesValues) - 1),
        $dataSeriesLabels,
        $xAxisTickValues,
        $dataSeriesValues
    );
    
    if ($horizontal) {
        $series->setPlotDirection(DataSeries::DIRECTION_BAR);
    } else {
        $series->setPlotDirection(DataSeries::DIRECTION_COL);
    }
    
    $layout = new Layout();
    $layout->setShowVal(true);
    
    $plotArea = new PlotArea($layout, [$series]);
    $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
    $title = new Title($titulo);
    
    $chart = new Chart(
        'chart_' . str_replace(' ', '_', $titulo),
        $title,
        $legend,
        $plotArea,
        true,
        DataSeries::EMPTY_AS_GAP,
        null,
        null
    );
    
    $chart->setTopLeftPosition($posicionCelda);
    $chart->setBottomRightPosition(
        chr(ord(substr($posicionCelda, 0, 1)) + $anchoColumnas) . (intval(substr($posicionCelda, 1)) + $altoFilas)
    );
    
    return $chart;
}

try {
    $pdo = conectarDB();
    
    // Formatear fechas para mostrar
    $fechaDesdeFormato = date('d/m/Y', strtotime($fecha_desde));
    $fechaHastaFormato = date('d/m/Y', strtotime($fecha_hasta));
    $rangoTexto = $fechaDesdeFormato . ' - ' . $fechaHastaFormato;
    
    // Obtener órdenes dentro del rango de fechas
    $sql = "SELECT 
                id, folio, empresa, departamento, estado,
                apartado1_data, apartado2_data, apartado3_data,
                fecha_creacion, fecha_enviado_usuario, fecha_completado
            FROM ordenes_servicio_mantenimiento
            WHERE DATE(fecha_creacion) >= :fecha_desde 
              AND DATE(fecha_creacion) <= :fecha_hasta
            ORDER BY id ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':fecha_desde' => $fecha_desde,
        ':fecha_hasta' => $fecha_hasta
    ]);
    $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar si hay registros
    if (empty($ordenes)) {
        $_SESSION['error'] = "No hay registros en el rango seleccionado ($rangoTexto).";
        header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
        exit;
    }
    
    // Crear el spreadsheet
    $spreadsheet = new Spreadsheet();
    
    // ==========================================
    // HOJA 1: ÓRDENES DE SERVICIO
    // ==========================================
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Órdenes de Servicio');
    
    // Definir encabezados (23 columnas)
    $headers = [
        'A' => 'No. OS',
        'B' => 'Fecha de Entrada',
        'C' => 'Hora de Entrada',
        'D' => 'Empresa',
        'E' => 'Área Solicitante',
        'F' => 'Folio',
        'G' => 'Equipo / Unidad',
        'H' => 'Descripción de la Falla',
        'I' => 'Fecha de Atención',
        'J' => 'Hora de Atención',
        'K' => 'Fecha de Término',
        'L' => 'Hora de Término',
        'M' => 'Descripción de Reparación',
        'N' => 'Empleado 1',
        'O' => 'Empleado 2',
        'P' => 'Empleado 3',
        'Q' => 'Código de Equipo',
        'R' => 'Horómetro',
        'S' => 'Fecha de Entrega',
        'T' => 'Hora de Entrega',
        'U' => 'Días de Mtto',
        'V' => 'Horas de Mtto',
        'W' => 'Estatus'
    ];
    
    // Escribir encabezados
    foreach ($headers as $col => $header) {
        $sheet->setCellValue($col . '1', $header);
    }
    
    // Estilo de encabezados
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 10
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '667EEA']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    
    $sheet->getStyle('A1:W1')->applyFromArray($headerStyle);
    $sheet->getRowDimension(1)->setRowHeight(30);
    
    // Variables para estadísticas
    $conteoEmpresas = [];
    $conteoAreas = [];
    $conteoEmpleados = [];
    $conteoEstatus = [];
    $diasMttoArray = [];
    
    // Escribir datos
    $row = 2;
    foreach ($ordenes as $orden) {
        // Decodificar JSON
        $apartado1 = json_decode($orden['apartado1_data'] ?? '{}', true) ?: [];
        $apartado2 = json_decode($orden['apartado2_data'] ?? '{}', true) ?: [];
        
        // Obtener personal asignado
        $personal = $apartado2['personal_asignado'] ?? [];
        $empleado1 = $personal[0]['nombre'] ?? '';
        $empleado2 = $personal[1]['nombre'] ?? '';
        $empleado3 = $personal[2]['nombre'] ?? '';
        
        // Calcular días y horas de mantenimiento
        $diasMtto = calcularDiasMantenimiento(
            $apartado2['fecha_atencion'] ?? '',
            $apartado2['fecha_termino'] ?? ''
        );
        
        $horasMtto = calcularHorasMantenimiento(
            $apartado2['fecha_atencion'] ?? '',
            $apartado2['hora_inicio'] ?? '',
            $apartado2['fecha_termino'] ?? '',
            $apartado2['hora_termino'] ?? ''
        );
        
        // Extraer fecha y hora de entrega
        $fechaEntrega = '';
        $horaEntrega = '';
        if (!empty($orden['fecha_enviado_usuario'])) {
            $fechaEnvio = new DateTime($orden['fecha_enviado_usuario']);
            $fechaEntrega = $fechaEnvio->format('Y-m-d');
            $horaEntrega = $fechaEnvio->format('H:i:s');
        }
        
        // ===== RECOLECTAR ESTADÍSTICAS =====
        // Empresa
        $empresa = $orden['empresa'] ?? 'Sin especificar';
        $conteoEmpresas[$empresa] = ($conteoEmpresas[$empresa] ?? 0) + 1;
        
        // Área solicitante
        $area = $apartado1['area_solicitante'] ?? $orden['departamento'] ?? 'Sin especificar';
        $conteoAreas[$area] = ($conteoAreas[$area] ?? 0) + 1;
        
        // Empleados (contar participaciones)
        if (!empty($empleado1)) {
            $conteoEmpleados[$empleado1] = ($conteoEmpleados[$empleado1] ?? 0) + 1;
        }
        if (!empty($empleado2)) {
            $conteoEmpleados[$empleado2] = ($conteoEmpleados[$empleado2] ?? 0) + 1;
        }
        if (!empty($empleado3)) {
            $conteoEmpleados[$empleado3] = ($conteoEmpleados[$empleado3] ?? 0) + 1;
        }
        
        // Estatus
        $estatusTexto = traducirEstado($orden['estado']);
        $conteoEstatus[$estatusTexto] = ($conteoEstatus[$estatusTexto] ?? 0) + 1;
        
        // Días de mantenimiento (solo números válidos)
        if (is_numeric($diasMtto) && $diasMtto > 0) {
            $diasMttoArray[] = $diasMtto;
        }
        
        // Escribir fila
        $sheet->setCellValue('A' . $row, $orden['id']);
        $sheet->setCellValue('B' . $row, $apartado1['fecha_entrada'] ?? '');
        $sheet->setCellValue('C' . $row, $apartado1['hora_entrada'] ?? '');
        $sheet->setCellValue('D' . $row, $orden['empresa']);
        $sheet->setCellValue('E' . $row, $apartado1['area_solicitante'] ?? $orden['departamento']);
        $sheet->setCellValue('F' . $row, $apartado1['folio'] ?? $orden['folio']);
        $sheet->setCellValue('G' . $row, $apartado1['unidad_equipo'] ?? '');
        $sheet->setCellValue('H' . $row, $apartado1['descripcion_falla'] ?? '');
        $sheet->setCellValue('I' . $row, $apartado2['fecha_atencion'] ?? '');
        $sheet->setCellValue('J' . $row, $apartado2['hora_inicio'] ?? '');
        $sheet->setCellValue('K' . $row, $apartado2['fecha_termino'] ?? '');
        $sheet->setCellValue('L' . $row, $apartado2['hora_termino'] ?? '');
        $sheet->setCellValue('M' . $row, $apartado2['descripcion_reparacion'] ?? '');
        $sheet->setCellValue('N' . $row, $empleado1);
        $sheet->setCellValue('O' . $row, $empleado2);
        $sheet->setCellValue('P' . $row, $empleado3);
        $sheet->setCellValue('Q' . $row, $apartado2['codigo_equipo'] ?? '');
        $sheet->setCellValue('R' . $row, $apartado2['horometro'] ?? '');
        $sheet->setCellValue('S' . $row, $fechaEntrega);
        $sheet->setCellValue('T' . $row, $horaEntrega);
        $sheet->setCellValue('U' . $row, $diasMtto);
        $sheet->setCellValue('V' . $row, $horasMtto);
        $sheet->setCellValue('W' . $row, $estatusTexto);
        
        $row++;
    }
    
    // Estilo de datos
    $lastRow = $row - 1;
    if ($lastRow >= 2) {
        $dataRange = 'A2:W' . $lastRow;
        
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ]
        ];
        
        $sheet->getStyle($dataRange)->applyFromArray($dataStyle);
        
        // Alternar colores de filas
        for ($i = 2; $i <= $lastRow; $i++) {
            if ($i % 2 == 0) {
                $sheet->getStyle('A' . $i . ':W' . $i)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F8F9FA');
            }
        }
    }
    
    // Ajustar anchos de columna
    $columnWidths = [
        'A' => 8,   'B' => 12,  'C' => 12,  'D' => 12,  'E' => 18,
        'F' => 15,  'G' => 20,  'H' => 35,  'I' => 12,  'J' => 12,
        'K' => 12,  'L' => 12,  'M' => 35,  'N' => 18,  'O' => 18,
        'P' => 18,  'Q' => 15,  'R' => 12,  'S' => 12,  'T' => 12,
        'U' => 12,  'V' => 12,  'W' => 22
    ];
    
    foreach ($columnWidths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }
    
    // Aplicar autofiltro
    if ($lastRow >= 2) {
        $sheet->setAutoFilter('A1:W' . $lastRow);
    }
    
    // Congelar primera fila
    $sheet->freezePane('A2');
    
    // ==========================================
    // HOJA 2: RESULTADOS (GRÁFICOS)
    // ==========================================
    $sheetResultados = $spreadsheet->createSheet();
    $sheetResultados->setTitle('Resultados');
    
    // Calcular estadísticas de días de mtto
    $promedioDias = count($diasMttoArray) > 0 ? round(array_sum($diasMttoArray) / count($diasMttoArray), 2) : 0;
    $maximoDias = count($diasMttoArray) > 0 ? max($diasMttoArray) : 0;
    $minimoDias = count($diasMttoArray) > 0 ? min($diasMttoArray) : 0;
    
    // Ordenar arrays por valor descendente
    arsort($conteoAreas);
    arsort($conteoEmpleados);
    
    // Estilo para títulos de sección
    $tituloSeccionStyle = [
        'font' => [
            'bold' => true,
            'size' => 12,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '667EEA']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    
    // Estilo para encabezados de tabla
    $headerTablaStyle = [
        'font' => [
            'bold' => true,
            'size' => 10
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E8E8E8']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ]
    ];
    
    // Estilo para celdas de datos
    $datosStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ]
    ];
    
    // ===== TÍTULO PRINCIPAL =====
    $sheetResultados->setCellValue('A1', 'RESULTADOS - ÓRDENES DE SERVICIO - ' . strtoupper($rangoTexto));
    $sheetResultados->mergeCells('A1:L1');
    $sheetResultados->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '667EEA']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $sheetResultados->getRowDimension(1)->setRowHeight(30);
    
    $sheetResultados->setCellValue('A2', 'Total de órdenes en el período: ' . count($ordenes));
    $sheetResultados->mergeCells('A2:L2');
    $sheetResultados->getStyle('A2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    // ===== TABLA 1: EMPRESA (Columnas A-B, Fila 4) =====
    $sheetResultados->setCellValue('A4', 'EMPRESA');
    $sheetResultados->mergeCells('A4:B4');
    $sheetResultados->getStyle('A4:B4')->applyFromArray($tituloSeccionStyle);
    
    $sheetResultados->setCellValue('A5', 'Empresa');
    $sheetResultados->setCellValue('B5', 'Cantidad');
    $sheetResultados->getStyle('A5:B5')->applyFromArray($headerTablaStyle);
    
    $filaEmpresa = 6;
    foreach ($conteoEmpresas as $empresa => $cantidad) {
        $sheetResultados->setCellValue('A' . $filaEmpresa, $empresa);
        $sheetResultados->setCellValue('B' . $filaEmpresa, $cantidad);
        $sheetResultados->getStyle('A' . $filaEmpresa . ':B' . $filaEmpresa)->applyFromArray($datosStyle);
        $filaEmpresa++;
    }
    $ultimaFilaEmpresa = $filaEmpresa - 1;
    
    // ===== TABLA 2: ESTATUS (Columnas D-E, Fila 4) =====
    $sheetResultados->setCellValue('D4', 'ESTATUS');
    $sheetResultados->mergeCells('D4:E4');
    $sheetResultados->getStyle('D4:E4')->applyFromArray($tituloSeccionStyle);
    
    $sheetResultados->setCellValue('D5', 'Estatus');
    $sheetResultados->setCellValue('E5', 'Cantidad');
    $sheetResultados->getStyle('D5:E5')->applyFromArray($headerTablaStyle);
    
    $filaEstatus = 6;
    foreach ($conteoEstatus as $estatus => $cantidad) {
        $sheetResultados->setCellValue('D' . $filaEstatus, $estatus);
        $sheetResultados->setCellValue('E' . $filaEstatus, $cantidad);
        $sheetResultados->getStyle('D' . $filaEstatus . ':E' . $filaEstatus)->applyFromArray($datosStyle);
        $filaEstatus++;
    }
    $ultimaFilaEstatus = $filaEstatus - 1;
    
    // ===== TABLA 3: DÍAS DE MTTO (Columnas G-H, Fila 4) =====
    $sheetResultados->setCellValue('G4', 'DÍAS DE MANTENIMIENTO');
    $sheetResultados->mergeCells('G4:H4');
    $sheetResultados->getStyle('G4:H4')->applyFromArray($tituloSeccionStyle);
    
    $sheetResultados->setCellValue('G5', 'Indicador');
    $sheetResultados->setCellValue('H5', 'Días');
    $sheetResultados->getStyle('G5:H5')->applyFromArray($headerTablaStyle);
    
    $sheetResultados->setCellValue('G6', 'Promedio');
    $sheetResultados->setCellValue('H6', $promedioDias);
    $sheetResultados->setCellValue('G7', 'Máximo');
    $sheetResultados->setCellValue('H7', $maximoDias);
    $sheetResultados->setCellValue('G8', 'Mínimo');
    $sheetResultados->setCellValue('H8', $minimoDias);
    $sheetResultados->getStyle('G6:H8')->applyFromArray($datosStyle);
    
    // ===== TABLA 4: ÁREA SOLICITANTE (Columnas A-B, después de Empresa) =====
    $filaInicioArea = $ultimaFilaEmpresa + 3;
    $sheetResultados->setCellValue('A' . $filaInicioArea, 'ÁREA SOLICITANTE');
    $sheetResultados->mergeCells('A' . $filaInicioArea . ':B' . $filaInicioArea);
    $sheetResultados->getStyle('A' . $filaInicioArea . ':B' . $filaInicioArea)->applyFromArray($tituloSeccionStyle);
    
    $sheetResultados->setCellValue('A' . ($filaInicioArea + 1), 'Área');
    $sheetResultados->setCellValue('B' . ($filaInicioArea + 1), 'Cantidad');
    $sheetResultados->getStyle('A' . ($filaInicioArea + 1) . ':B' . ($filaInicioArea + 1))->applyFromArray($headerTablaStyle);
    
    $filaArea = $filaInicioArea + 2;
    foreach ($conteoAreas as $area => $cantidad) {
        $sheetResultados->setCellValue('A' . $filaArea, $area);
        $sheetResultados->setCellValue('B' . $filaArea, $cantidad);
        $sheetResultados->getStyle('A' . $filaArea . ':B' . $filaArea)->applyFromArray($datosStyle);
        $filaArea++;
    }
    $ultimaFilaArea = $filaArea - 1;
    
    // ===== TABLA 5: EMPLEADOS (Columnas D-E, después de Estatus) =====
    $filaInicioEmpleado = max($ultimaFilaEstatus, 8) + 3;
    $sheetResultados->setCellValue('D' . $filaInicioEmpleado, 'PARTICIPACIÓN DE EMPLEADOS');
    $sheetResultados->mergeCells('D' . $filaInicioEmpleado . ':E' . $filaInicioEmpleado);
    $sheetResultados->getStyle('D' . $filaInicioEmpleado . ':E' . $filaInicioEmpleado)->applyFromArray($tituloSeccionStyle);
    
    $sheetResultados->setCellValue('D' . ($filaInicioEmpleado + 1), 'Empleado');
    $sheetResultados->setCellValue('E' . ($filaInicioEmpleado + 1), 'Participaciones');
    $sheetResultados->getStyle('D' . ($filaInicioEmpleado + 1) . ':E' . ($filaInicioEmpleado + 1))->applyFromArray($headerTablaStyle);
    
    $filaEmpleado = $filaInicioEmpleado + 2;
    foreach ($conteoEmpleados as $empleado => $cantidad) {
        $sheetResultados->setCellValue('D' . $filaEmpleado, $empleado);
        $sheetResultados->setCellValue('E' . $filaEmpleado, $cantidad);
        $sheetResultados->getStyle('D' . $filaEmpleado . ':E' . $filaEmpleado)->applyFromArray($datosStyle);
        $filaEmpleado++;
    }
    $ultimaFilaEmpleado = $filaEmpleado - 1;
    
    // ===== CREAR GRÁFICOS =====
    
    // Gráfico 1: Empresa (Pastel) - Posición J4
    if (count($conteoEmpresas) > 0) {
        $chartEmpresa = crearGraficoPastel(
            "'Resultados'",
            'Órdenes por Empresa',
            '$A$6:$A$' . $ultimaFilaEmpresa,
            '$B$6:$B$' . $ultimaFilaEmpresa,
            'J4',
            6,
            12
        );
        $sheetResultados->addChart($chartEmpresa);
    }
    
    // Gráfico 2: Estatus (Pastel) - Posición J17
    if (count($conteoEstatus) > 0) {
        $chartEstatus = crearGraficoPastel(
            "'Resultados'",
            'Órdenes por Estatus',
            '$D$6:$D$' . $ultimaFilaEstatus,
            '$E$6:$E$' . $ultimaFilaEstatus,
            'J17',
            6,
            12
        );
        $sheetResultados->addChart($chartEstatus);
    }
    
    // Gráfico 3: Días de Mantenimiento (Barras) - Posición J30
    $chartDias = crearGraficoBarras(
        "'Resultados'",
        'Días de Mantenimiento',
        '$G$6:$G$8',
        '$H$6:$H$8',
        'J30',
        6,
        12,
        false
    );
    $sheetResultados->addChart($chartDias);
    
    // Gráfico 4: Área Solicitante (Barras Horizontales) - Posición A + offset
    $posicionGraficoArea = max($ultimaFilaArea, $ultimaFilaEmpleado) + 3;
    if (count($conteoAreas) > 0) {
        $chartArea = crearGraficoBarras(
            "'Resultados'",
            'Órdenes por Área Solicitante',
            '$A$' . ($filaInicioArea + 2) . ':$A$' . $ultimaFilaArea,
            '$B$' . ($filaInicioArea + 2) . ':$B$' . $ultimaFilaArea,
            'A' . $posicionGraficoArea,
            6,
            12,
            true
        );
        $sheetResultados->addChart($chartArea);
    }
    
    // Gráfico 5: Empleados (Barras) - Posición debajo de Área Solicitante
    $posicionGraficoEmpleados = $posicionGraficoArea + 15; // 15 filas debajo del gráfico de área
    if (count($conteoEmpleados) > 0) {
        $chartEmpleados = crearGraficoBarras(
            "'Resultados'",
            'Participación de Empleados',
            '$D$' . ($filaInicioEmpleado + 2) . ':$D$' . $ultimaFilaEmpleado,
            '$E$' . ($filaInicioEmpleado + 2) . ':$E$' . $ultimaFilaEmpleado,
            'A' . $posicionGraficoEmpleados,
            6,
            12,
            false
        );
        $sheetResultados->addChart($chartEmpleados);
    }
    
    // Ajustar anchos de columnas en Resultados
    $sheetResultados->getColumnDimension('A')->setWidth(25);
    $sheetResultados->getColumnDimension('B')->setWidth(12);
    $sheetResultados->getColumnDimension('C')->setWidth(3);
    $sheetResultados->getColumnDimension('D')->setWidth(28);
    $sheetResultados->getColumnDimension('E')->setWidth(15);
    $sheetResultados->getColumnDimension('F')->setWidth(3);
    $sheetResultados->getColumnDimension('G')->setWidth(15);
    $sheetResultados->getColumnDimension('H')->setWidth(10);
    
    // Establecer hoja activa como la primera (Órdenes de Servicio)
    $spreadsheet->setActiveSheetIndex(0);
    
    // Configurar headers para descarga
    $filename = 'Ordenes_Servicio_' . str_replace('/', '-', $fechaDesdeFormato) . '_a_' . str_replace('/', '-', $fechaHastaFormato) . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    // Escribir archivo CON gráficos
    $writer = new Xlsx($spreadsheet);
    $writer->setIncludeCharts(true);
    $writer->save('php://output');
    
    // Log de descarga
    error_log("Excel por rango descargado por: {$_SESSION['nombre_completo']} - Rango: {$rangoTexto} - Órdenes: " . count($ordenes));
    
    exit;
    
} catch (Exception $e) {
    error_log("Error al generar Excel de órdenes por rango: " . $e->getMessage());
    $_SESSION['error'] = "Error al generar el archivo Excel: " . $e->getMessage();
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
    exit;
}