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
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Axis;
use PhpOffice\PhpSpreadsheet\Chart\Layout;

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

// Los movimientos generados por un vale se identifican por es_automatico = 1
// y observaciones = 'Entrega por Vale {folio}' (ver confirmar_entrega_vale).
// COLLATE explicito: movimientos_epp y vales_epp tienen collations distintas.
$movimientos = $pdo->query("
    SELECT m.fecha_movimiento, m.tipo_movimiento, m.articulo, m.categoria,
           m.cantidad, m.usuario_nombre,
           v.nombre_empleado AS vale_empleado,
           v.area AS vale_area
    FROM movimientos_epp m
    LEFT JOIN vales_epp v
           ON m.es_automatico = 1
          AND m.observaciones COLLATE utf8mb4_unicode_ci
              = CONCAT('Entrega por Vale ', v.folio) COLLATE utf8mb4_unicode_ci
    ORDER BY m.fecha_movimiento DESC
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
$top5_depto_mes = queryStats($pdo, "SELECT area as label, COUNT(*) as total FROM vales_epp WHERE estado='Entregado' AND area IS NOT NULL AND area!='' AND MONTH(fecha_entrega)=:mes AND YEAR(fecha_entrega)=:anio GROUP BY area ORDER BY total DESC LIMIT 5", $params_mes);

// ANUALES
$freq_anual = queryStats($pdo, "SELECT tipo_movimiento as label, COUNT(*) as total FROM movimientos_epp WHERE YEAR(fecha_movimiento)=:anio GROUP BY tipo_movimiento", $params_anio);
$top10_anual = queryStats($pdo, "SELECT articulo as label, COUNT(*) as total FROM movimientos_epp WHERE YEAR(fecha_movimiento)=:anio GROUP BY articulo ORDER BY total DESC LIMIT 10", $params_anio);
$top5_ent_anual = queryStats($pdo, "SELECT articulo as label, COUNT(*) as total FROM movimientos_epp WHERE tipo_movimiento='Entrada' AND YEAR(fecha_movimiento)=:anio GROUP BY articulo ORDER BY total DESC LIMIT 5", $params_anio);
$top5_sal_anual = queryStats($pdo, "SELECT articulo as label, COUNT(*) as total FROM movimientos_epp WHERE tipo_movimiento='Salida' AND YEAR(fecha_movimiento)=:anio GROUP BY articulo ORDER BY total DESC LIMIT 5", $params_anio);
$top5_depto_anual = queryStats($pdo, "SELECT area as label, COUNT(*) as total FROM vales_epp WHERE estado='Entregado' AND area IS NOT NULL AND area!='' AND YEAR(fecha_entrega)=:anio GROUP BY area ORDER BY total DESC LIMIT 5", $params_anio);

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

$headers1 = ['Fecha y Hora', 'Tipo de Movimiento', 'Articulo', 'Categoria', 'Cantidad', 'Nombre del Empleado', 'Departamento', 'Empleado Responsable'];
$col = 'A';
foreach ($headers1 as $h) { $sheet1->setCellValue($col . '4', $h); $col++; }
$sheet1->getStyle('A4:H4')->applyFromArray($headerStyle);
$sheet1->setAutoFilter('A4:H4');

$row = 5;
foreach ($movimientos as $m) {
    $sheet1->setCellValue('A' . $row, date('d/m/Y H:i', strtotime($m['fecha_movimiento'])));
    $sheet1->setCellValue('B' . $row, $m['tipo_movimiento']);
    $sheet1->setCellValue('C' . $row, $m['articulo']);
    $sheet1->setCellValue('D' . $row, $m['categoria']);
    $sheet1->setCellValue('E' . $row, (int)$m['cantidad']);
    // Solo se llenan cuando el movimiento proviene de un vale EPP
    $sheet1->setCellValue('F' . $row, $m['vale_empleado'] ?? '');
    $sheet1->setCellValue('G' . $row, $m['vale_area'] ?? '');
    $sheet1->setCellValue('H' . $row, $m['usuario_nombre']);
    if ($m['tipo_movimiento'] === 'Entrada') {
        $sheet1->getStyle('B' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('28A745'));
    } else {
        $sheet1->getStyle('B' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DC3545'));
    }
    $row++;
}
if ($row > 5) $sheet1->getStyle('A5:H' . ($row-1))->applyFromArray($dataStyle);
foreach (range('A', 'H') as $c) $sheet1->getColumnDimension($c)->setAutoSize(true);

// =====================================================
// FUNCION: Escribir tabla + crear grafica de barras
// =====================================================

function escribirSeccionConGrafica($sheet, $startRow, $titulo, $data, $colLabel, $colValue, $sheetTitle, &$chartIndex, $horizontal = true) {
    global $subtitleStyle, $headerStyle, $dataStyle;

    $filasMinGrafica = 16; // alto minimo de la grafica (en filas)
    $tituloRow = $startRow;

    // Titulo de seccion
    $sheet->setCellValue('A' . $startRow, $titulo);
    $sheet->getStyle('A' . $startRow . ':B' . $startRow)->applyFromArray($subtitleStyle);
    $sheet->mergeCells('A' . $startRow . ':B' . $startRow);
    $startRow++;

    // Headers de tabla
    $sheet->setCellValue('A' . $startRow, $colLabel);
    $sheet->setCellValue('B' . $startRow, $colValue);
    $sheet->getStyle('A' . $startRow . ':B' . $startRow)->applyFromArray($headerStyle);
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

    // Sin datos: solo tabla, sin grafica
    if (empty($data)) {
        return $startRow + 2;
    }

    $chartIndex++;
    $numPuntos = $dataEndRow - $dataStartRow + 1;

    $categoryLabels = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetTitle}'!\$A\${$dataStartRow}:\$A\${$dataEndRow}", null, $numPuntos)
    ];
    $dataValues = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetTitle}'!\$B\${$dataStartRow}:\$B\${$dataEndRow}", null, $numPuntos)
    ];

    $series = new DataSeries(
        DataSeries::TYPE_BARCHART,
        DataSeries::GROUPING_CLUSTERED,
        range(0, count($dataValues) - 1),
        [],
        $categoryLabels,
        $dataValues
    );
    // Barras horizontales: los nombres largos se leen completos en el eje vertical
    $series->setPlotDirection($horizontal ? DataSeries::DIRECTION_BAR : DataSeries::DIRECTION_COL);

    // Mostrar el valor en cada barra
    $layout = new Layout();
    $layout->setShowVal(true);
    $plotArea = new PlotArea($layout, [$series]);

    // Eje de categorias: en horizontal, el #1 del ranking queda arriba
    $ejeCategorias = new Axis();
    if ($horizontal) {
        $ejeCategorias->setAxisOrientation('maxMin');
    }

    $chart = new Chart(
        'chart_' . $chartIndex,
        new Title($titulo),
        null,              // sin leyenda ("Series1" no aporta nada)
        $plotArea,
        true,
        DataSeries::EMPTY_AS_GAP,
        null,
        null,
        $ejeCategorias
    );

    // Alto minimo y crece con el numero de barras, para que no se aplaste
    $chartBottomRow = max($dataEndRow, $tituloRow + max($filasMinGrafica, $numPuntos * 2 + 4));
    $chart->setTopLeftPosition('D' . $tituloRow);
    $chart->setBottomRightPosition('M' . $chartBottomRow);
    $sheet->addChart($chart);

    return $chartBottomRow + 3; // la siguiente seccion empieza debajo de la grafica
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

$sheet2->getColumnDimension('A')->setWidth(55);
$sheet2->getColumnDimension('B')->setWidth(12);

$ci2 = 0;
$r2 = 4;
$r2 = escribirSeccionConGrafica($sheet2, $r2, 'Frecuencia de Movimientos de EPP', $freq_mes, 'Tipo', 'Total', $sheetTitle2, $ci2, false);
$r2 = escribirSeccionConGrafica($sheet2, $r2, '10 Articulos que mas se mueven', $top10_mes, 'Articulo', 'Total', $sheetTitle2, $ci2);
$r2 = escribirSeccionConGrafica($sheet2, $r2, '5 Articulos con mas Entradas', $top5_ent_mes, 'Articulo', 'Total', $sheetTitle2, $ci2);
$r2 = escribirSeccionConGrafica($sheet2, $r2, '5 Articulos con mas Salidas', $top5_sal_mes, 'Articulo', 'Total', $sheetTitle2, $ci2);
$r2 = escribirSeccionConGrafica($sheet2, $r2, '5 Departamentos con mas solicitudes', $top5_depto_mes, 'Departamento', 'Vales', $sheetTitle2, $ci2);

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

$sheet3->getColumnDimension('A')->setWidth(55);
$sheet3->getColumnDimension('B')->setWidth(12);

$ci3 = 0;
$r3 = 4;
$r3 = escribirSeccionConGrafica($sheet3, $r3, 'Frecuencia de Movimientos de EPP', $freq_anual, 'Tipo', 'Total', $sheetTitle3, $ci3, false);
$r3 = escribirSeccionConGrafica($sheet3, $r3, '10 Articulos que mas se mueven', $top10_anual, 'Articulo', 'Total', $sheetTitle3, $ci3);
$r3 = escribirSeccionConGrafica($sheet3, $r3, '5 Articulos con mas Entradas', $top5_ent_anual, 'Articulo', 'Total', $sheetTitle3, $ci3);
$r3 = escribirSeccionConGrafica($sheet3, $r3, '5 Articulos con mas Salidas', $top5_sal_anual, 'Articulo', 'Total', $sheetTitle3, $ci3);
$r3 = escribirSeccionConGrafica($sheet3, $r3, '5 Departamentos con mas solicitudes', $top5_depto_anual, 'Departamento', 'Vales', $sheetTitle3, $ci3);

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