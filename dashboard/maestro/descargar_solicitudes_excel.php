<?php
/**
 * descargar_solicitudes_excel.php
 * Genera y descarga un archivo Excel con los datos de Solicitudes de Atención
 * 
 * Ubicación: ti_sistemas/descargar_solicitudes_excel.php
 */

// Incluir config primero (ya inicia sesión)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';

// Verificar que sea usuario de TI (misma función que usa gestion_solicitudes.php)
if (!es_usuario_ti()) {
    $_SESSION['error'] = "No tienes permiso para descargar estos registros.";
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// Cargar autoloader de Composer
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $_SESSION['error'] = "PhpSpreadsheet no está instalado. Ejecuta: composer require phpoffice/phpspreadsheet";
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}
require $autoloadPath;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Layout;

try {
    // Conectar a BD
    $pdo = conectarDB();
    
    // Obtener primer y último día del mes actual
    $primerDiaMes = date('Y-m-01 00:00:00');
    $ultimoDiaMes = date('Y-m-t 23:59:59');
    
    // Obtener solicitudes del mes actual con información del usuario y técnico
    $sql = "SELECT 
                s.id,
                s.folio,
                s.departamento,
                s.tipo_soporte,
                s.tipo_apoyo,
                s.tipo_problema,
                s.descripcion,
                s.prioridad,
                s.estado,
                s.fecha_creacion,
                s.fecha_atencion,
                u.nombre_completo as solicitante,
                t.nombre_completo as tecnico_nombre
            FROM solicitudes_atencion s
            LEFT JOIN usuarios u ON s.usuario_id = u.id
            LEFT JOIN usuarios t ON s.atendido_por = t.id
            WHERE s.fecha_creacion BETWEEN :fecha_inicio AND :fecha_fin
            ORDER BY s.fecha_creacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':fecha_inicio' => $primerDiaMes,
        ':fecha_fin' => $ultimoDiaMes
    ]);
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($solicitudes)) {
        $_SESSION['error'] = "No hay solicitudes registradas en el mes actual para descargar.";
        header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
        exit;
    }
    
    // Nombre del mes para el archivo
    $nombreMes = strftime('%B_%Y', strtotime($primerDiaMes));
    setlocale(LC_TIME, 'es_ES.UTF-8', 'es_MX.UTF-8', 'spanish');
    $meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 
              'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto',
              'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
    $mesIngles = date('F', strtotime($primerDiaMes));
    $mesEspanol = $meses[$mesIngles] ?? $mesIngles;
    $anio = date('Y', strtotime($primerDiaMes));
    
    // Crear el spreadsheet
    $spreadsheet = new Spreadsheet();
    
    // ==========================================
    // HOJA 1: REGISTROS DE TICKETS
    // ==========================================
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('Registros de Tickets');
    
    // Definir encabezados
    $headers = [
        'A' => 'Folio',
        'B' => 'Nombre del Solicitante',
        'C' => 'Departamento',
        'D' => 'Tipo de Soporte',
        'E' => 'Tipo de Apoyo/Problema',
        'F' => 'Descripción',
        'G' => 'Prioridad',
        'H' => 'Estado',
        'I' => 'Fecha de Creación',
        'J' => 'Fecha de Finalización',
        'K' => 'Tiempo de Resolución',
        'L' => 'Atendido por'
    ];
    
    // Escribir encabezados
    foreach ($headers as $col => $header) {
        $sheet1->setCellValue($col . '1', $header);
    }
    
    // Estilo de encabezados
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 11
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '198754']
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
    
    $sheet1->getStyle('A1:L1')->applyFromArray($headerStyle);
    $sheet1->getRowDimension(1)->setRowHeight(25);
    
    // Variables para estadísticas
    $conteoEstados = ['pendiente' => 0, 'en_proceso' => 0, 'finalizada' => 0, 'cancelada' => 0];
    $conteoDepartamentos = [];
    $conteoTipoSoporte = ['Apoyo' => 0, 'Problema' => 0];
    $conteoTecnicos = [];
    
    // Conteo cruzado: prioridad x tipo de soporte (orden alfabético igual que el ejemplo)
    $conteoTipoPrioridad = [
        'Alta'    => ['Apoyo' => 0, 'Problema' => 0],
        'Baja'    => ['Apoyo' => 0, 'Problema' => 0],
        'Crítica' => ['Apoyo' => 0, 'Problema' => 0],
        'Media'   => ['Apoyo' => 0, 'Problema' => 0],
    ];
    
    // Mapeo de prioridad de BD → etiqueta de gráfica
    $prioridadDisplay = [
        'alta'    => 'Alta',
        'baja'    => 'Baja',
        'critica' => 'Crítica',
        'media'   => 'Media',
    ];
    
    // Mapeo de estados para mostrar
    $estadosDisplay = [
        'pendiente' => 'Pendiente',
        'en_proceso' => 'En Proceso',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada'
    ];
    
    // Mapeo de departamentos para mostrar
    $departamentosDisplay = [
        'sistemas' => 'TI / Sistemas',
        'laboratorio' => 'Laboratorio',
        'normatividad' => 'Normatividad',
        'ventas' => 'Ventas',
        'calidad' => 'Calidad',
        'atencion_clientes' => 'Atención a Clientes',
        'direccion' => 'Dirección',
        'tesoreria' => 'Tesorería',
        'mantenimiento' => 'Mantenimiento',
        'almacen' => 'Almacén',
        'compras' => 'Compras',
        'recursos_humanos' => 'Recursos Humanos'
    ];
    
    // Escribir datos
    $row = 2;
    foreach ($solicitudes as $sol) {
        // Determinar tipo de apoyo/problema
        $tipoApoyoProblema = '';
        if ($sol['tipo_soporte'] === 'Apoyo') {
            $tipoApoyoProblema = $sol['tipo_apoyo'] ?? '';
        } else {
            $tipoApoyoProblema = $sol['tipo_problema'] ?? '';
        }
        
        // Calcular tiempo de resolución
        $tiempoResolucion = '';
        if ($sol['estado'] === 'finalizada' && !empty($sol['fecha_atencion'])) {
            $fechaCreacion = new DateTime($sol['fecha_creacion']);
            $fechaFinalizacion = new DateTime($sol['fecha_atencion']);
            $diferencia = $fechaCreacion->diff($fechaFinalizacion);
            
            $partes = [];
            if ($diferencia->days > 0) {
                $partes[] = $diferencia->days . ' día(s)';
            }
            if ($diferencia->h > 0) {
                $partes[] = $diferencia->h . ' hora(s)';
            }
            if ($diferencia->i > 0) {
                $partes[] = $diferencia->i . ' min';
            }
            $tiempoResolucion = !empty($partes) ? implode(', ', $partes) : 'Mismo momento';
        }
        
        // Nombre departamento formateado
        $deptoDisplay = $departamentosDisplay[$sol['departamento']] ?? ucfirst($sol['departamento']);
        
        // Escribir fila
        $sheet1->setCellValue('A' . $row, $sol['folio']);
        $sheet1->setCellValue('B' . $row, $sol['solicitante'] ?? 'N/A');
        $sheet1->setCellValue('C' . $row, $deptoDisplay);
        $sheet1->setCellValue('D' . $row, $sol['tipo_soporte']);
        $sheet1->setCellValue('E' . $row, $tipoApoyoProblema);
        $sheet1->setCellValue('F' . $row, $sol['descripcion']);
        $sheet1->setCellValue('G' . $row, ucfirst($sol['prioridad']));
        $sheet1->setCellValue('H' . $row, $estadosDisplay[$sol['estado']] ?? $sol['estado']);
        $sheet1->setCellValue('I' . $row, date('d/m/Y H:i', strtotime($sol['fecha_creacion'])));
        $sheet1->setCellValue('J' . $row, $sol['fecha_atencion'] ? date('d/m/Y H:i', strtotime($sol['fecha_atencion'])) : '');
        $sheet1->setCellValue('K' . $row, $tiempoResolucion);
        $sheet1->setCellValue('L' . $row, $sol['tecnico_nombre'] ?? '');
        
        // Aplicar estilo de fila alternada
        if ($row % 2 == 0) {
            $sheet1->getStyle('A' . $row . ':L' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F8F9FA');
        }
        
        // Bordes
        $sheet1->getStyle('A' . $row . ':L' . $row)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        // Acumular estadísticas
        if (isset($conteoEstados[$sol['estado']])) {
            $conteoEstados[$sol['estado']]++;
        }
        
        $deptoKey = $deptoDisplay;
        $conteoDepartamentos[$deptoKey] = ($conteoDepartamentos[$deptoKey] ?? 0) + 1;
        
        if (isset($conteoTipoSoporte[$sol['tipo_soporte']])) {
            $conteoTipoSoporte[$sol['tipo_soporte']]++;
        }
        
        // Acumular conteo cruzado tipo x prioridad
        $prioridadKey = $prioridadDisplay[$sol['prioridad']] ?? null;
        $tipoKey = $sol['tipo_soporte'] ?? null;
        if ($prioridadKey !== null && $tipoKey !== null && isset($conteoTipoPrioridad[$prioridadKey][$tipoKey])) {
            $conteoTipoPrioridad[$prioridadKey][$tipoKey]++;
        }
        
        if (!empty($sol['tecnico_nombre'])) {
            $conteoTecnicos[$sol['tecnico_nombre']] = ($conteoTecnicos[$sol['tecnico_nombre']] ?? 0) + 1;
        }
        
        $row++;
    }
    
    // Ajustar anchos de columna
    $columnWidths = ['A' => 22, 'B' => 25, 'C' => 18, 'D' => 14, 'E' => 30, 'F' => 40, 'G' => 12, 'H' => 14, 'I' => 18, 'J' => 18, 'K' => 20, 'L' => 20];
    foreach ($columnWidths as $col => $width) {
        $sheet1->getColumnDimension($col)->setWidth($width);
    }
    
    // Congelar primera fila
    $sheet1->freezePane('A2');
    
    // Activar autofiltro
    $lastRow = $row - 1;
    $sheet1->setAutoFilter('A1:L' . $lastRow);
    
    // ==========================================
    // HOJA 2: GRÁFICAS Y ESTADÍSTICAS
    // ==========================================
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Estadísticas y Gráficas');
    
    // Estilo para títulos de tablas
    $titleStyle = [
        'font' => [
            'bold' => true,
            'size' => 12,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '0D6EFD']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ]
    ];
    
    $tableHeaderStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E9ECEF']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ]
    ];
    
    $cellStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ]
    ];
    
    // ========== TABLA 1: SOLICITUDES POR ESTADO ==========
    $sheet2->setCellValue('A1', 'SOLICITUDES POR ESTADO');
    $sheet2->mergeCells('A1:B1');
    $sheet2->getStyle('A1:B1')->applyFromArray($titleStyle);
    
    $sheet2->setCellValue('A2', 'Estado');
    $sheet2->setCellValue('B2', 'Cantidad');
    $sheet2->getStyle('A2:B2')->applyFromArray($tableHeaderStyle);
    
    $rowTabla1 = 3;
    foreach ($conteoEstados as $estado => $cantidad) {
        $sheet2->setCellValue('A' . $rowTabla1, $estadosDisplay[$estado] ?? $estado);
        $sheet2->setCellValue('B' . $rowTabla1, $cantidad);
        $sheet2->getStyle('A' . $rowTabla1 . ':B' . $rowTabla1)->applyFromArray($cellStyle);
        $rowTabla1++;
    }
    $lastRowT1 = $rowTabla1 - 1;
    
    // Total
    $sheet2->setCellValue('A' . $rowTabla1, 'TOTAL');
    $sheet2->setCellValue('B' . $rowTabla1, '=SUM(B3:B' . $lastRowT1 . ')');
    $sheet2->getStyle('A' . $rowTabla1 . ':B' . $rowTabla1)->applyFromArray($tableHeaderStyle);
    
    $sheet2->getColumnDimension('A')->setWidth(35);
    $sheet2->getColumnDimension('B')->setWidth(12);
    
    // ========== TABLA 2: SOLICITUDES POR DEPARTAMENTO ==========
    $startRowT2 = $rowTabla1 + 3;
    $sheet2->setCellValue('A' . $startRowT2, 'SOLICITUDES POR DEPARTAMENTO');
    $sheet2->mergeCells('A' . $startRowT2 . ':B' . $startRowT2);
    $sheet2->getStyle('A' . $startRowT2 . ':B' . $startRowT2)->applyFromArray($titleStyle);
    
    $sheet2->setCellValue('A' . ($startRowT2 + 1), 'Departamento');
    $sheet2->setCellValue('B' . ($startRowT2 + 1), 'Cantidad');
    $sheet2->getStyle('A' . ($startRowT2 + 1) . ':B' . ($startRowT2 + 1))->applyFromArray($tableHeaderStyle);
    
    arsort($conteoDepartamentos);
    
    $rowTabla2 = $startRowT2 + 2;
    $startDataT2 = $rowTabla2;
    foreach ($conteoDepartamentos as $depto => $cantidad) {
        $sheet2->setCellValue('A' . $rowTabla2, $depto);
        $sheet2->setCellValue('B' . $rowTabla2, $cantidad);
        $sheet2->getStyle('A' . $rowTabla2 . ':B' . $rowTabla2)->applyFromArray($cellStyle);
        $rowTabla2++;
    }
    $lastRowT2 = $rowTabla2 - 1;
    
    $sheet2->setCellValue('A' . $rowTabla2, 'TOTAL');
    $sheet2->setCellValue('B' . $rowTabla2, '=SUM(B' . $startDataT2 . ':B' . $lastRowT2 . ')');
    $sheet2->getStyle('A' . $rowTabla2 . ':B' . $rowTabla2)->applyFromArray($tableHeaderStyle);
    
    // ========== TABLA 3: SOLICITUDES POR TIPO DE SOPORTE ==========
    $startRowT3 = $rowTabla2 + 3;
    $sheet2->setCellValue('A' . $startRowT3, 'SOLICITUDES POR TIPO DE SOPORTE');
    $sheet2->mergeCells('A' . $startRowT3 . ':B' . $startRowT3);
    $sheet2->getStyle('A' . $startRowT3 . ':B' . $startRowT3)->applyFromArray($titleStyle);
    
    $sheet2->setCellValue('A' . ($startRowT3 + 1), 'Tipo');
    $sheet2->setCellValue('B' . ($startRowT3 + 1), 'Cantidad');
    $sheet2->getStyle('A' . ($startRowT3 + 1) . ':B' . ($startRowT3 + 1))->applyFromArray($tableHeaderStyle);
    
    $rowTabla3 = $startRowT3 + 2;
    $startDataT3 = $rowTabla3;
    foreach ($conteoTipoSoporte as $tipo => $cantidad) {
        $sheet2->setCellValue('A' . $rowTabla3, $tipo);
        $sheet2->setCellValue('B' . $rowTabla3, $cantidad);
        $sheet2->getStyle('A' . $rowTabla3 . ':B' . $rowTabla3)->applyFromArray($cellStyle);
        $rowTabla3++;
    }
    $lastRowT3 = $rowTabla3 - 1;
    
    $sheet2->setCellValue('A' . $rowTabla3, 'TOTAL');
    $sheet2->setCellValue('B' . $rowTabla3, '=SUM(B' . $startDataT3 . ':B' . $lastRowT3 . ')');
    $sheet2->getStyle('A' . $rowTabla3 . ':B' . $rowTabla3)->applyFromArray($tableHeaderStyle);
    
    // ========== GRÁFICAS ==========
    
    // GRÁFICA 1: Solicitudes por Estado (Barras)
    $dataSeriesLabels1 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Estadísticas y Gráficas'!\$B\$2", null, 1)
    ];
    $xAxisTickValues1 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Estadísticas y Gráficas'!\$A\$3:\$A\$" . $lastRowT1, null, 4)
    ];
    $dataSeriesValues1 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Estadísticas y Gráficas'!\$B\$3:\$B\$" . $lastRowT1, null, 4)
    ];
    
    $series1 = new DataSeries(
        DataSeries::TYPE_BARCHART,
        DataSeries::GROUPING_STANDARD,
        range(0, count($dataSeriesValues1) - 1),
        $dataSeriesLabels1,
        $xAxisTickValues1,
        $dataSeriesValues1
    );
    $series1->setPlotDirection(DataSeries::DIRECTION_COL);
    
    $layout1 = new Layout();
    $layout1->setShowVal(true);
    
    $plotArea1 = new PlotArea($layout1, [$series1]);
    $legend1 = new Legend(Legend::POSITION_RIGHT, null, false);
    $title1 = new Title('Solicitudes por Estado');
    
    $chart1 = new Chart(
        'chart1',
        $title1,
        $legend1,
        $plotArea1,
        true,
        DataSeries::EMPTY_AS_GAP,
        null,
        null
    );
    
    $chart1->setTopLeftPosition('D2');
    $chart1->setBottomRightPosition('K16');
    $sheet2->addChart($chart1);
    
    // GRÁFICA 2: Solicitudes por Departamento (Barras)
    if (!empty($conteoDepartamentos)) {
        $numDeptos = count($conteoDepartamentos);
        $dataSeriesLabels2 = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Estadísticas y Gráficas'!\$B\$" . ($startRowT2 + 1), null, 1)
        ];
        $xAxisTickValues2 = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Estadísticas y Gráficas'!\$A\$" . $startDataT2 . ":\$A\$" . $lastRowT2, null, $numDeptos)
        ];
        $dataSeriesValues2 = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Estadísticas y Gráficas'!\$B\$" . $startDataT2 . ":\$B\$" . $lastRowT2, null, $numDeptos)
        ];
        
        $series2 = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($dataSeriesValues2) - 1),
            $dataSeriesLabels2,
            $xAxisTickValues2,
            $dataSeriesValues2
        );
        $series2->setPlotDirection(DataSeries::DIRECTION_COL);
        
        $plotArea2 = new PlotArea(null, [$series2]);
        $legend2 = new Legend(Legend::POSITION_BOTTOM, null, false);
        $title2 = new Title('Solicitudes por Departamento');
        
        $chart2 = new Chart(
            'chart2',
            $title2,
            $legend2,
            $plotArea2,
            true,
            DataSeries::EMPTY_AS_GAP,
            null,
            null
        );
        
        $chart2->setTopLeftPosition('D18');
        $chart2->setBottomRightPosition('K35');
        $sheet2->addChart($chart2);
    }
    
    // GRÁFICA 3: Tipo de Soporte (Pastel)
    $dataSeriesLabels3 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Estadísticas y Gráficas'!\$B\$" . ($startRowT3 + 1), null, 1)
    ];
    $xAxisTickValues3 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Estadísticas y Gráficas'!\$A\$" . $startDataT3 . ":\$A\$" . $lastRowT3, null, 2)
    ];
    $dataSeriesValues3 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Estadísticas y Gráficas'!\$B\$" . $startDataT3 . ":\$B\$" . $lastRowT3, null, 2)
    ];
    
    $series3 = new DataSeries(
        DataSeries::TYPE_PIECHART,
        null,
        range(0, count($dataSeriesValues3) - 1),
        $dataSeriesLabels3,
        $xAxisTickValues3,
        $dataSeriesValues3
    );
    
    $layout3 = new Layout();
    $layout3->setShowVal(true);
    $layout3->setShowPercent(true);
    
    $plotArea3 = new PlotArea($layout3, [$series3]);
    $legend3 = new Legend(Legend::POSITION_RIGHT, null, false);
    $title3 = new Title('Tipo de Soporte');
    
    $chart3 = new Chart(
        'chart3',
        $title3,
        $legend3,
        $plotArea3,
        true,
        DataSeries::EMPTY_AS_GAP,
        null,
        null
    );
    
    $chart3->setTopLeftPosition('L2');
    $chart3->setBottomRightPosition('S16');
    $sheet2->addChart($chart3);
        
    // ========== TABLA 5: SOLICITUDES POR TIPO Y PRIORIDAD ==========
    $startRowT5 = $rowTabla3 + 3;
    $sheet2->setCellValue('A' . $startRowT5, 'SOLICITUDES POR TIPO Y PRIORIDAD');
    $sheet2->mergeCells('A' . $startRowT5 . ':C' . $startRowT5);
    $sheet2->getStyle('A' . $startRowT5 . ':C' . $startRowT5)->applyFromArray($titleStyle);
    
    // Encabezados (Prioridad / Apoyo / Problema)
    $sheet2->setCellValue('A' . ($startRowT5 + 1), 'Prioridad');
    $sheet2->setCellValue('B' . ($startRowT5 + 1), 'Apoyo');
    $sheet2->setCellValue('C' . ($startRowT5 + 1), 'Problema');
    $sheet2->getStyle('A' . ($startRowT5 + 1) . ':C' . ($startRowT5 + 1))->applyFromArray($tableHeaderStyle);
    
    $rowTabla5 = $startRowT5 + 2;
    $startDataT5 = $rowTabla5;
    foreach ($conteoTipoPrioridad as $prioridad => $tipos) {
        $sheet2->setCellValue('A' . $rowTabla5, $prioridad);
        $sheet2->setCellValue('B' . $rowTabla5, $tipos['Apoyo']);
        $sheet2->setCellValue('C' . $rowTabla5, $tipos['Problema']);
        $sheet2->getStyle('A' . $rowTabla5 . ':C' . $rowTabla5)->applyFromArray($cellStyle);
        $rowTabla5++;
    }
    $lastRowT5 = $rowTabla5 - 1;
    
    // GRÁFICA 5: Solicitudes por Tipo y Prioridad (Barras agrupadas)
    $dataSeriesLabels5 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Estadísticas y Gráficas'!\$B\$" . ($startRowT5 + 1), null, 1), // Apoyo
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Estadísticas y Gráficas'!\$C\$" . ($startRowT5 + 1), null, 1), // Problema
    ];
    $xAxisTickValues5 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Estadísticas y Gráficas'!\$A\$" . $startDataT5 . ":\$A\$" . $lastRowT5, null, 4)
    ];
    $dataSeriesValues5 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Estadísticas y Gráficas'!\$B\$" . $startDataT5 . ":\$B\$" . $lastRowT5, null, 4), // Apoyo
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Estadísticas y Gráficas'!\$C\$" . $startDataT5 . ":\$C\$" . $lastRowT5, null, 4), // Problema
    ];
    
    $series5 = new DataSeries(
        DataSeries::TYPE_BARCHART,
        DataSeries::GROUPING_CLUSTERED,
        range(0, count($dataSeriesValues5) - 1),
        $dataSeriesLabels5,
        $xAxisTickValues5,
        $dataSeriesValues5
    );
    $series5->setPlotDirection(DataSeries::DIRECTION_COL);
    
    $layout5 = new Layout();
    $layout5->setShowVal(true);
    
    $plotArea5 = new PlotArea($layout5, [$series5]);
    $legend5 = new Legend(Legend::POSITION_RIGHT, null, false);
    $title5 = new Title('Solicitudes por Tipo y Prioridad');
    
    $chart5 = new Chart(
        'chart5',
        $title5,
        $legend5,
        $plotArea5,
        true,
        DataSeries::EMPTY_AS_GAP,
        null,
        null
    );
    
    $chart5->setTopLeftPosition('L18');
    $chart5->setBottomRightPosition('S35');
    $sheet2->addChart($chart5);
    
    // Activar la primera hoja al abrir
    $spreadsheet->setActiveSheetIndex(0);
    
    // Generar nombre del archivo con mes actual
    $filename = 'Solicitudes_Atencion_' . $mesEspanol . '_' . $anio . '.xlsx';
    
    // Limpiar cualquier output previo
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Headers para descarga
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
    
    exit;
    
} catch (Exception $e) {
    error_log("Error al generar Excel de solicitudes: " . $e->getMessage());
    $_SESSION['error'] = "Error al generar el archivo Excel: " . $e->getMessage();
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}