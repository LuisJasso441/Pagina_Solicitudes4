<?php
/**
 * Generador de XLSX de SECs.
 * Ubicación: includes/salidas_envases/sec_export_funciones.php
 *
 * Sub-bloque 8.1 — Export.
 *
 * Genera un archivo XLSX con 3 hojas:
 *   - Resumen: 1 fila por SEC (para vista rápida)
 *   - Detalle: 1 fila por línea de SEC (para tablas dinámicas)
 *   - Devoluciones: 1 fila por devolución de las SECs filtradas
 *
 * Usa PhpSpreadsheet (ya en vendor).
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/sec_filtros_funciones.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// =====================================================================
// ESTILOS COMUNES
// =====================================================================

function _sec_export_estilo_encabezado() {
    return [
        'font' => [
            'bold'  => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size'  => 11,
        ],
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '0D6EFD'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => 'FFFFFF'],
            ],
        ],
    ];
}

function _sec_export_estilo_zebra() {
    return [
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F8F9FA'],
        ],
    ];
}

function _sec_export_color_estado($estado) {
    $map = [
        'pendiente_firma_entrega' => 'FFF3CD',
        'en_ruta'                 => 'CFE2FF',
        'cerrada'                 => 'D1E7DD',
        'cerrada_con_devolucion'  => 'FFE5D0',
        'cancelada'               => 'F8D7DA',
    ];
    return $map[$estado] ?? 'FFFFFF';
}

function _sec_export_estado_label($estado) {
    $map = [
        'pendiente_firma_entrega' => 'Pendiente de firma',
        'en_ruta'                 => 'En ruta',
        'cerrada'                 => 'Cerrada',
        'cerrada_con_devolucion'  => 'Cerrada con devolución',
        'cancelada'               => 'Cancelada',
    ];
    return $map[$estado] ?? $estado;
}

function _sec_export_motivo_label($motivo) {
    $map = [
        'condiciones_incorrectas' => 'Condiciones incorrectas',
        'cantidad_incorrecta'     => 'Cantidad incorrecta',
        'otro'                    => 'Otro',
    ];
    return $map[$motivo] ?? $motivo;
}

/**
 * Formatea fecha a d/m/Y o "—" si vacía.
 */
function _sec_export_fecha($fecha) {
    if (empty($fecha)) return '—';
    return date('d/m/Y', strtotime($fecha));
}

/**
 * Formatea datetime a d/m/Y H:i o "—" si vacía.
 */
function _sec_export_datetime($fecha) {
    if (empty($fecha)) return '—';
    return date('d/m/Y H:i', strtotime($fecha));
}

// =====================================================================
// HOJA 1: RESUMEN
// =====================================================================

function _sec_export_llenar_resumen($sheet, $secs) {
    $sheet->setTitle('Resumen');

    $headers = [
        'Folio', 'Fecha salida', 'Estado', 'Unidad', 'Placas', 'Vuelta',
        '# Empresas', 'Total envases', 'Solicita',
        'Firma entrega', 'Fecha entrega',
        'Firma recibe', 'Fecha recibe',
        'Creado por', 'Creado en',
    ];

    // Encabezado
    foreach ($headers as $i => $h) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($col . '1', $h);
    }
    $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1')
          ->applyFromArray(_sec_export_estilo_encabezado());
    $sheet->getRowDimension(1)->setRowHeight(30);
    $sheet->freezePane('A2');

    // Datos
    $row = 2;
    foreach ($secs as $sec) {
        $lineas = $sec['lineas'] ?? [];
        $empresas = array_unique(array_map(fn($l) => $l['empresa_nombre'], $lineas));
        $total_envases = array_sum(array_map(fn($l) => (int) $l['cantidad'], $lineas));

        $data = [
            $sec['folio'],
            _sec_export_fecha($sec['fecha_salida']),
            _sec_export_estado_label($sec['estado']),
            $sec['unidad_nombre']    ?? '—',
            $sec['unidad_matricula'] ?? '—',
            $sec['vuelta_numero']    ?? '—',
            count($empresas),
            $total_envases,
            $sec['solicita_nombre'] ?? '—',
            $sec['entrega_nombre']  ?? '—',
            _sec_export_datetime($sec['entrega_firmada_en'] ?? null),
            $sec['recibe_nombre']   ?? '—',
            _sec_export_datetime($sec['recibe_firmada_en'] ?? null),
            $sec['creador_nombre']  ?? '—',
            _sec_export_datetime($sec['creado_en'] ?? null),
        ];
        foreach ($data as $i => $v) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . $row, $v);
        }

        // Color de fila por estado (columna Estado)
        $sheet->getStyle('C' . $row)->applyFromArray([
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => _sec_export_color_estado($sec['estado'])],
            ],
            'font' => ['bold' => true],
        ]);

        // Zebra
        if ($row % 2 === 0) {
            $sheet->getStyle('A' . $row . ':' . Coordinate::stringFromColumnIndex(count($headers)) . $row)
                  ->applyFromArray(_sec_export_estilo_zebra());
        }

        $row++;
    }

    // Autosize columnas
    for ($i = 1; $i <= count($headers); $i++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }

    // Total al final
    if (count($secs) > 0) {
        $sheet->setCellValue('A' . $row, 'TOTAL: ' . count($secs) . ' SECs');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
    }
}

// =====================================================================
// HOJA 2: DETALLE
// =====================================================================

function _sec_export_llenar_detalle($sheet, $secs) {
    $sheet->setTitle('Detalle');

    $headers = [
        'Folio', 'Fecha', 'Estado',
        'Empresa destino', 'Tipo envase', 'Especificación', 'Cantidad',
        'Unidad', 'Placas', 'Vuelta',
        'Solicita', 'Creado por',
    ];

    foreach ($headers as $i => $h) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($col . '1', $h);
    }
    $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1')
          ->applyFromArray(_sec_export_estilo_encabezado());
    $sheet->getRowDimension(1)->setRowHeight(30);
    $sheet->freezePane('A2');

    $row = 2;
    $total_envases = 0;
    foreach ($secs as $sec) {
        foreach (($sec['lineas'] ?? []) as $l) {
            $data = [
                $sec['folio'],
                _sec_export_fecha($sec['fecha_salida']),
                _sec_export_estado_label($sec['estado']),
                $l['empresa_nombre']         ?? '—',
                $l['tipo_nombre']            ?? '—',
                $l['especificacion_nombre']  ?? '—',
                (int) $l['cantidad'],
                $sec['unidad_nombre']        ?? '—',
                $sec['unidad_matricula']     ?? '—',
                $sec['vuelta_numero']        ?? '—',
                $sec['solicita_nombre']      ?? '—',
                $sec['creador_nombre']       ?? '—',
            ];
            foreach ($data as $i => $v) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValue($col . $row, $v);
            }
            // Zebra
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':' . Coordinate::stringFromColumnIndex(count($headers)) . $row)
                      ->applyFromArray(_sec_export_estilo_zebra());
            }
            $total_envases += (int) $l['cantidad'];
            $row++;
        }
    }

    // Autosize
    for ($i = 1; $i <= count($headers); $i++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }

    // Total
    if ($row > 2) {
        $sheet->setCellValue('F' . $row, 'TOTAL');
        $sheet->setCellValue('G' . $row, $total_envases);
        $sheet->getStyle('F' . $row . ':G' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFE066'],
            ],
        ]);
    }
}

// =====================================================================
// HOJA 3: DEVOLUCIONES
// =====================================================================

function _sec_export_llenar_devoluciones($sheet, $devoluciones) {
    $sheet->setTitle('Devoluciones');

    $headers = [
        'Folio SEC', 'Fecha SEC', 'Fecha devolución',
        'Empresa', 'Tipo envase', 'Especificación', 'Cantidad devuelta',
        'Motivo', 'Detalle motivo', 'Registrado por',
    ];

    foreach ($headers as $i => $h) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($col . '1', $h);
    }
    $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1')
          ->applyFromArray(_sec_export_estilo_encabezado());
    $sheet->getRowDimension(1)->setRowHeight(30);
    $sheet->freezePane('A2');

    if (empty($devoluciones)) {
        $sheet->setCellValue('A2', 'No hay devoluciones registradas para las SECs de este filtro.');
        $sheet->mergeCells('A2:' . Coordinate::stringFromColumnIndex(count($headers)) . '2');
        $sheet->getStyle('A2')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'font'      => ['italic' => true, 'color' => ['rgb' => '6C757D']],
        ]);
        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        return;
    }

    $row = 2;
    $total_devuelto = 0;
    foreach ($devoluciones as $d) {
        $data = [
            $d['sec_folio']              ?? '—',
            _sec_export_fecha($d['sec_fecha'] ?? null),
            _sec_export_datetime($d['creado_en'] ?? null),
            $d['empresa_nombre']         ?? '—',
            $d['tipo_nombre']            ?? '—',
            $d['especificacion_nombre']  ?? '—',
            (int) $d['cantidad_devuelta'],
            _sec_export_motivo_label($d['motivo'] ?? ''),
            $d['motivo_otro']            ?? '',
            $d['registrado_por_nombre']  ?? '—',
        ];
        foreach ($data as $i => $v) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . $row, $v);
        }
        if ($row % 2 === 0) {
            $sheet->getStyle('A' . $row . ':' . Coordinate::stringFromColumnIndex(count($headers)) . $row)
                  ->applyFromArray(_sec_export_estilo_zebra());
        }
        $total_devuelto += (int) $d['cantidad_devuelta'];
        $row++;
    }

    for ($i = 1; $i <= count($headers); $i++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }

    // Total
    $sheet->setCellValue('F' . $row, 'TOTAL DEVUELTO');
    $sheet->setCellValue('G' . $row, $total_devuelto);
    $sheet->getStyle('F' . $row . ':G' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFE5D0'],
        ],
    ]);
}

// =====================================================================
// GENERADOR PRINCIPAL
// =====================================================================

/**
 * Genera el XLSX en memoria y lo devuelve como string binario.
 * Para enviarlo al navegador, imprimir con headers de descarga.
 *
 * @param array $filtros Los mismos filtros del listado
 * @return string Contenido binario del archivo XLSX
 */
function generar_xlsx_secs($filtros) {
    $secs         = obtener_sec_filtradas($filtros);
    $devoluciones = obtener_devoluciones_de_sec_filtradas($filtros);

    $spreadsheet = new Spreadsheet();

    // Metadata
    $spreadsheet->getProperties()
        ->setCreator('Verden Core — Módulo SEC')
        ->setTitle('Exportación de Salidas de Envases')
        ->setSubject('SECs con filtros aplicados')
        ->setDescription('Exportación generada el ' . date('d/m/Y H:i'));

    // Hoja 1: Resumen (activa por defecto)
    _sec_export_llenar_resumen($spreadsheet->getActiveSheet(), $secs);

    // Hoja 2: Detalle
    $hoja_detalle = $spreadsheet->createSheet();
    _sec_export_llenar_detalle($hoja_detalle, $secs);

    // Hoja 3: Devoluciones
    $hoja_dev = $spreadsheet->createSheet();
    _sec_export_llenar_devoluciones($hoja_dev, $devoluciones);

    // Activar la primera hoja
    $spreadsheet->setActiveSheetIndex(0);

    // Escribir a un buffer
    $writer = new Xlsx($spreadsheet);
    ob_start();
    $writer->save('php://output');
    $contenido = ob_get_clean();

    // Limpiar
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return $contenido;
}

/**
 * Genera el nombre del archivo con timestamp.
 */
function nombre_archivo_export_secs() {
    return 'SECs_export_' . date('Y-m-d_His') . '.xlsx';
}