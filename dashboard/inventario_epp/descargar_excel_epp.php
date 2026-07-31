<?php
/**
 * Descargar BD de EPP en Excel con Graficas
 * 3 hojas: Registros, Estadisticas Mensuales, Estadisticas Anuales
 * Ubicacion: dashboard/inventario_epp/descargar_excel_epp.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/inventario_epp_funciones.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Title;

$depto = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
if (!in_array($depto, ['seguridad', 'almacen_refacciones'])) {
    die('Sin acceso.');
}

$pdo = conectarDB();
$mes_actual = date('m');
$anio_actual = date('Y');

// =====================================================
// CONSULTAS
// =====================================================

$movimientos = $pdo->query("
    SELECT fecha_movimiento, tipo_movimiento, articulo, categoria, talla,
           cantidad, nombre_trabajador, usuario_nombre
    FROM movimientos_epp ORDER BY fecha_movimiento DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Helper para consultas parametrizadas
function queryStats($pdo, $sql, $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$params_mes = [':mes' => $mes_actual, ':anio' => $anio_actual];
$params_anio = [':anio' => $anio_actual];

// MENSUALES
$freq_mes = queryStats($pdo, "SELECT tipo_movimiento as label, COUNT(*) as total FROM movimientos_epp WHERE MONTH(fecha_movimiento)=:mes AND YEAR(fecha_movimiento)=:anio GROUP BY tipo_movimiento", $params_mes);
$top10_mes = queryStats($pdo, "SELECT articulo as label, COUNT(*) as total FROM movimientos_epp WHERE MONTH(fecha_movimiento)=:mes AND YEAR(fecha_movimiento)=:anio GROUP BY articulo ORDER BY total DESC LIMIT 10", $params_mes);
$top5_ent_mes = queryStats($pdo, "SELECT articulo as label, COUNT(*) as total FROM movimientos_epp WHERE tipo_movimiento='Entrada' AND MONTH(fecha_movimiento)=:mes AND YEAR(fecha_movimiento)=:anio GROUP BY articulo ORDER BY total DESC LIMIT 5", $params_mes);
$top5_sal_mes = queryStats($pdo, "SELECT articulo as label, COUNT(*) as total FROM movimientos_epp WHERE tipo_movimiento='Salida' AND MONTH(fecha_movimiento)=:mes AND YEAR(fecha_movimiento)=:anio GROUP BY articulo ORDER BY total DESC LIMIT 5", $params_mes);
$top5_emp_mes = queryStats($pdo, "SELECT nombre_trabajador as label, COUNT(*) as total FROM movimientos_epp WHERE tipo_movimiento='Salida' AND nombre_trabajador IS NOT NULL AND nombre_trabajador!='' AND MONTH(fecha_movimiento)=:mes AND YEAR(fecha_movimiento)=:anio GROUP BY nombre_trabajador ORDER BY total DESC LIMIT 5", $params_mes);

// ANUALES
$freq_anual = queryStats($pdo, "SELECT tipo_movimiento as label, COUNT(*) as total FROM movimientos_epp WHERE YEAR(fecha_movimiento)=:anio GROUP BY tipo_movimiento", $params_anio);
$top10_anual = queryStats($pdo, "SELECT articulo as label, COUNT(*) as total FROM movimientos_epp WHERE YEAR(fecha_movimiento)=:anio GROUP BY articulo ORDER BY total DESC LIMIT 10", $params_anio);
$top5_ent_anual = queryStats($pdo, "SELECT articulo as label, COUNT(*) as total FROM movimientos_epp WHERE tipo_movimiento='Entrada' AND YEAR(fecha_movimiento)=:anio GROUP BY articulo ORDER BY total DESC LIMIT 5", $params_anio);
$top5_sal_anual = queryStats($pdo, "SELECT articulo as label, COUNT(*) as total FROM movimientos_epp WHERE tipo_movimiento='Salida' AND YEAR(fecha_movimiento)=:anio GROUP BY articulo ORDER BY total DESC LIMIT 5", $params_anio);
$top5_emp_anual = queryStats($pdo, "SELECT nombre_trabajador as label, COUNT(*) as total FROM movimientos_epp WHERE tipo_movimiento='Salida' AND nombre_trabajador IS NOT NULL AND nombre_trabajador!='' AND YEAR(fecha_movimiento)=:anio GROUP BY nombre_trabajador ORDER BY total DESC LIMIT 5", $params_anio);

// =====================================================
// CREAR SPREADSHEET
// =====================================================

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()->setCreator('VerdenCore')->setTitle('Inventario EPP - Reporte');

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$dataStyle = [
    'font' => ['size' => 9, 'name' => 'Arial'],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
];
$titleStyle = [
    'font' => ['bold' => true, 'size' => 14, 'name' => 'Arial', 'color' => ['rgb' => '2C3E50']]
];
$subtitleStyle = [
    'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial', 'color' => ['rgb' => '34495E']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECF0F1']]
];

// =====================================================
// HOJA 1: REGISTROS
// =====================================================

$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Registros');

$sheet1->setCellValue('A1', 'REGISTROS DE MOVIMIENTOS DE EPP');
$sheet1->getStyle('A1')->applyFromArray($titleStyle);
$sheet1->setCellValue('A2', 'Generado: ' . date('d/m/Y H:i') . ' por ' . $_SESSION['nombre_completo']);
$sheet1->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('808080'));

$headers1 = ['Fecha y Hora', 'Tipo de Movimiento', 'Articulo', 'Categoria', 'Talla', 'Cantidad', 'Empleado Responsable'];
$col = 'A';
foreach ($headers1 as $h) { $sheet1->setCellValue($col . '4', $h); $col++; }
$sheet1->getStyle('A4:G4')->applyFromArray($headerStyle);
$sheet1->setAutoFilter('A4:G4');

$row = 5;
foreach ($movimientos as $m) {
    $sheet1->setCellValue('A' . $row, date('d/m/Y H:i', strtotime($m['fecha_movimiento'])));
    $sheet1->setCellValue('B' . $row, $m['tipo_movimiento']);
    $sheet1->setCellValue('C' . $row, $m['articulo']);
    $sheet1->setCellValue('D' . $row, $m['categoria']);
    $sheet1->setCellValue('E' . $row, $m['talla'] ?: 'Unica');
    $sheet1->setCellValue('F' . $row, (int)$m['cantidad']);
    $sheet1->setCellValue('G' . $row, $m['usuario_nombre']);
    if ($m['tipo_movimiento'] === 'Entrada') {
        $sheet1->getStyle('B' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('28A745'));
    } else {
        $sheet1->getStyle('B' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DC3545'));
    }
    $row++;
}
if ($row > 5) $sheet1->getStyle('A5:G' . ($row-1))->applyFromArray($dataStyle);
foreach (range('A', 'G') as $c) $sheet1->getColumnDimension($c)->setAutoSize(true);

// =====================================================
// FUNCION: Escribir tabla + crear grafica de barras
// =====================================================

function escribirSeccionConGrafica($sheet, $startRow, $titulo, $data, $colLabel, $colValue, $sheetTitle, &$chartIndex, $chartColor = '3498DB') {
    global $subtitleStyle, $headerStyle, $dataStyle;
    
    // Titulo de seccion
    $sheet->setCellValue('A' . $startRow, $titulo);
    $sheet->getStyle('A' . $startRow . ':B' . $startRow)->applyFromArray($subtitleStyle);
    $sheet->mergeCells('A' . $startRow . ':B' . $startRow);
    $startRow++;
    
    // Headers de tabla
    $sheet->setCellValue('A' . $startRow, $colLabel);
    $sheet->setCellValue('B' . $startRow, $colValue);
    $sheet->getStyle('A' . $startRow . ':B' . $startRow)->applyFromArray($headerStyle);
    $headerRow = $startRow;
    $startRow++;
    
    // Datos
    $dataStartRow = $startRow;
    if (empty($data)) {
        $sheet->setCellValue('A' . $startRow, 'Sin datos');
        $sheet->setCellValue('B' . $startRow, 0);
        $startRow++;
    } else {
        foreach ($data as $d) {
            $sheet->setCellValue('A' . $startRow, $d['label'] ?? '');
            $sheet->setCellValue('B' . $startRow, (int)($d['total'] ?? 0));
            $startRow++;
        }
    }
    $dataEndRow = $startRow - 1;
    
    $sheet->getStyle('A' . $dataStartRow . ':B' . $dataEndRow)->applyFromArray($dataStyle);
    
    // ===== CREAR GRAFICA DE BARRAS =====
    if (!empty($data) && count($data) > 0) {
        $chartIndex++;
        
        // Labels (eje X) - columna A
        $categoryLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetTitle}'!A{$dataStartRow}:A{$dataEndRow}", null, $dataEndRow - $dataStartRow + 1)
        ];
        
        // Valores (eje Y) - columna B
        $dataValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetTitle}'!B{$dataStartRow}:B{$dataEndRow}", null, $dataEndRow - $dataStartRow + 1)
        ];
        
        // Serie de datos
        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($dataValues) - 1),
            [], // labels de serie
            $categoryLabels,
            $dataValues
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);
        
        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $chartTitle = new Title($titulo);
        
        $chart = new Chart(
            'chart_' . $chartIndex,
            $chartTitle,
            $legend,
            $plotArea
        );
        
        // Posicion: columnas D-K, mismas filas que la tabla
        $topLeftCell = 'D' . ($headerRow - 1);
        $bottomRightCell = 'K' . ($dataEndRow + 1);
        $chart->setTopLeftPosition($topLeftCell);
        $chart->setBottomRightPosition($bottomRightCell);
        
        $sheet->addChart($chart);
    }
    
    return $startRow + 2; // espacio entre secciones
}

// =====================================================
// HOJA 2: ESTADISTICAS MENSUALES
// =====================================================

$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Estadisticas Mensuales');
$sheetTitle2 = 'Estadisticas Mensuales';

$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$nombre_mes = $meses[(int)$mes_actual] ?? $mes_actual;

$sheet2->setCellValue('A1', "ESTADISTICAS MENSUALES - {$nombre_mes} {$anio_actual}");
$sheet2->getStyle('A1')->applyFromArray($titleStyle);
$sheet2->setCellValue('A2', 'Generado: ' . date('d/m/Y H:i'));
$sheet2->getStyle('A2')->getFont()->setSize(9)->setItalic(true);

$sheet2->getColumnDimension('A')->setWidth(40);
$sheet2->getColumnDimension('B')->setWidth(15);

$ci2 = 0;
$r2 = 4;
$r2 = escribirSeccionConGrafica($sheet2, $r2, 'Frecuencia de Movimientos de EPP', $freq_mes, 'Tipo', 'Total', $sheetTitle2, $ci2);
$r2 = escribirSeccionConGrafica($sheet2, $r2, '10 Articulos que mas se mueven', $top10_mes, 'Articulo', 'Total', $sheetTitle2, $ci2);
$r2 = escribirSeccionConGrafica($sheet2, $r2, '5 Articulos con mas Entradas', $top5_ent_mes, 'Articulo', 'Total', $sheetTitle2, $ci2);
$r2 = escribirSeccionConGrafica($sheet2, $r2, '5 Articulos con mas Salidas', $top5_sal_mes, 'Articulo', 'Total', $sheetTitle2, $ci2);
$r2 = escribirSeccionConGrafica($sheet2, $r2, '5 Empleados con mas solicitudes', $top5_emp_mes, 'Empleado', 'Total', $sheetTitle2, $ci2);

// =====================================================
// HOJA 3: ESTADISTICAS ANUALES
// =====================================================

$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Estadisticas Anuales');
$sheetTitle3 = 'Estadisticas Anuales';

$sheet3->setCellValue('A1', "ESTADISTICAS ANUALES - {$anio_actual}");
$sheet3->getStyle('A1')->applyFromArray($titleStyle);
$sheet3->setCellValue('A2', 'Generado: ' . date('d/m/Y H:i'));
$sheet3->getStyle('A2')->getFont()->setSize(9)->setItalic(true);

$sheet3->getColumnDimension('A')->setWidth(40);
$sheet3->getColumnDimension('B')->setWidth(15);

$ci3 = 0;
$r3 = 4;
$r3 = escribirSeccionConGrafica($sheet3, $r3, 'Frecuencia de Movimientos de EPP', $freq_anual, 'Tipo', 'Total', $sheetTitle3, $ci3);
$r3 = escribirSeccionConGrafica($sheet3, $r3, '10 Articulos que mas se mueven', $top10_anual, 'Articulo', 'Total', $sheetTitle3, $ci3);
$r3 = escribirSeccionConGrafica($sheet3, $r3, '5 Articulos con mas Entradas', $top5_ent_anual, 'Articulo', 'Total', $sheetTitle3, $ci3);
$r3 = escribirSeccionConGrafica($sheet3, $r3, '5 Articulos con mas Salidas', $top5_sal_anual, 'Articulo', 'Total', $sheetTitle3, $ci3);
$r3 = escribirSeccionConGrafica($sheet3, $r3, '5 Empleados con mas solicitudes', $top5_emp_anual, 'Empleado', 'Total', $sheetTitle3, $ci3);

// =====================================================
// DESCARGAR
// =====================================================

$spreadsheet->setActiveSheetIndex(0);
$filename = 'EPP_Reporte_' . date('Ymd_Hi') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);
$writer->save('php://output');
exit;