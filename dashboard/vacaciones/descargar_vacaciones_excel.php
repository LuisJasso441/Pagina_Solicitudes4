<?php
/**
 * Descargar Base de Datos de Vacaciones en Excel
 * dashboard/vacaciones/descargar_vacaciones_excel.php
 *
 * Estructura:
 * - Fila 1: Titulo
 * - Fila 2: Subtitulo
 * - Fila 3: "INFORMACION DEL TRABAJADOR" (A3:I3) + Meses (J3:J4 merge, K3:K4 merge...)
 * - Fila 4: Sub-headers empleado (ID, No. Nomina...) con filtro solo en A4:I4
 * - Fila 5+: Datos con color unico por empleado
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/vacaciones/vacaciones_funciones.php';

if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$depto_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
$es_gth = in_array($depto_codigo, ['gth', 'gestion_talento', 'contabilidad']);
$es_sistemas = in_array($depto_codigo, ['sistemas', 'ti']);

if (!$es_gth && !$es_sistemas) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'No tienes permisos para descargar este reporte.'];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_gth.php');
    exit;
}

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'PhpSpreadsheet no instalado.'];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_gth.php');
    exit;
}
require_once $autoloadPath;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

try {
    $pdo = conectarDB();
    $anio = intval($_GET['anio'] ?? date('Y'));

    // EMPLEADOS CON CUENTA
    $sql_empleados = "
        SELECT u.id, u.no_nomina, u.nombre_completo, u.puesto, u.fecha_ingreso,
               u.periodo_pago, u.empresa, d.nombre AS departamento_nombre
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE u.activo = 1 AND u.fecha_ingreso IS NOT NULL
        ORDER BY d.nombre ASC, u.nombre_completo ASC
    ";
    $empleados = $pdo->query($sql_empleados)->fetchAll(PDO::FETCH_ASSOC);

    if (empty($empleados)) {
        $_SESSION['alerta'] = ['tipo' => 'warning', 'mensaje' => 'No hay empleados con fecha de ingreso registrada.'];
        header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_gth.php');
        exit;
    }

    // SOLICITUDES NORMALES
    $sql_solicitudes = "
        SELECT sv.usuario_id, sv.fecha_inicio, sv.fecha_fin, sv.dias_solicitados, sv.estado, sv.es_manual
        FROM solicitudes_vacaciones sv
        WHERE sv.estado IN ('pendiente_gth', 'aprobada_gth', 'completada', 'aprobada_admin', 'pendiente_admin')
          AND sv.es_manual = 0
          AND YEAR(sv.fecha_inicio) = ?
        ORDER BY sv.usuario_id, sv.fecha_inicio ASC
    ";
    $stmt = $pdo->prepare($sql_solicitudes);
    $stmt->execute([$anio]);
    $todas_solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $vacaciones_por_usuario = [];
    foreach ($todas_solicitudes as $sol) {
        $uid = $sol['usuario_id'];
        $mes = (int)date('n', strtotime($sol['fecha_inicio']));
        $vacaciones_por_usuario[$uid][$mes][] = [
            'inicio' => $sol['fecha_inicio'],
            'fin'    => $sol['fecha_fin']
        ];
    }

    // SOLICITUDES MANUALES
    $sql_manuales = "
        SELECT sv.nombre_manual, sv.no_nomina_manual, sv.puesto_manual, sv.fecha_ingreso_manual,
               sv.periodo_pago_manual, sv.empresa_manual, sv.fecha_inicio, sv.fecha_fin,
               d.nombre AS departamento_nombre
        FROM solicitudes_vacaciones sv
        LEFT JOIN departamentos d ON sv.departamento_id = d.id
        WHERE sv.es_manual = 1
          AND sv.estado NOT IN ('cancelada', 'rechazada_admin', 'rechazada_gth')
          AND YEAR(sv.fecha_inicio) = ?
        ORDER BY sv.nombre_manual, sv.fecha_inicio ASC
    ";
    $stmt_man = $pdo->prepare($sql_manuales);
    $stmt_man->execute([$anio]);
    $solicitudes_manuales = $stmt_man->fetchAll(PDO::FETCH_ASSOC);

    $manuales_agrupados = [];
    foreach ($solicitudes_manuales as $sm) {
        $clave = ($sm['nombre_manual'] ?? '') . '|' . ($sm['no_nomina_manual'] ?? '');
        if (!isset($manuales_agrupados[$clave])) {
            $manuales_agrupados[$clave] = ['info' => $sm, 'meses' => []];
        }
        $mes = (int)date('n', strtotime($sm['fecha_inicio']));
        $manuales_agrupados[$clave]['meses'][$mes][] = [
            'inicio' => $sm['fecha_inicio'],
            'fin'    => $sm['fecha_fin']
        ];
    }

    // =====================================================
    // CREAR SPREADSHEET
    // =====================================================
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('Sistema VerdenCore - GTH')
        ->setTitle('Reporte de Vacaciones ' . $anio);

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Vacaciones ' . $anio);

    // ESTILOS
    $estiloTitulo = [
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a472a']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ];
    $estiloSubtitulo = [
        'font' => ['italic' => true, 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ];
    $estiloHeaderBase = [
        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
    ];
    $estiloCelda = [
        'font' => ['size' => 9],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]]
    ];
    $estiloCeldaCentrada = array_merge($estiloCelda, [
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]
    ]);

    $coloresMeses = [
        1  => 'C6EFCE', 2  => 'FDE9D9', 3  => 'D9E2F3', 4  => 'E2EFDA',
        5  => 'FCE4EC', 6  => 'FFF9C4', 7  => 'D1C4E9', 8  => 'B2DFDB',
        9  => 'FFE0B2', 10 => 'C8E6C9', 11 => 'BBDEFB', 12 => 'F8BBD0',
    ];
    $nombresMeses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    // Paleta de colores para empleados (se ciclan)
    $coloresEmpleados = [
        'DBEAFE', // azul claro
        'FEF3C7', // amarillo claro
        'D1FAE5', // verde claro
        'FCE7F3', // rosa claro
        'E0E7FF', // indigo claro
        'FED7AA', // naranja claro
        'CCFBF1', // turquesa claro
        'EDE9FE', // violeta claro
        'FEE2E2', // rojo claro
        'CFFAFE', // cyan claro
        'F0FDF4', // green mint
        'FDF2F8', // pink light
        'ECFDF5', // emerald
        'FFF7ED', // orange light
        'F5F3FF', // purple light
    ];

    // 9 cols info + 12 meses = 21 (A-U)
    $colInicioMeses = 10;
    $ultimaCol = 21;
    $ultimaColLetra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ultimaCol);

    // FILA 1: TITULO
    $sheet->setCellValue('A1', 'CONTROL DE VACACIONES ' . $anio . ' - GRUPO VERDEN');
    $sheet->mergeCells('A1:' . $ultimaColLetra . '1');
    $sheet->getStyle('A1')->applyFromArray($estiloTitulo);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // FILA 2: SUBTITULO
    $totalEmpleados = count($empleados) + count($manuales_agrupados);
    $sheet->setCellValue('A2', 'Generado: ' . date('d/m/Y H:i:s') . ' | Total empleados: ' . $totalEmpleados);
    $sheet->mergeCells('A2:' . $ultimaColLetra . '2');
    $sheet->getStyle('A2')->applyFromArray($estiloSubtitulo);

    // =====================================================
    // FILA 3: "INFORMACION DEL TRABAJADOR" (A3:I3) + Meses (merge J3:J4, K3:K4...)
    // FILA 4: Sub-headers empleado (A4:I4) + meses ya mergeados arriba
    // =====================================================

    $tituloGrupo = mb_convert_encoding("INFORMACI\xC3\x93N DEL TRABAJADOR", 'UTF-8', 'UTF-8');
    $sheet->setCellValue('A3', $tituloGrupo);
    $sheet->mergeCells('A3:I3');
    $estiloGrupo = [
        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a472a']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
    ];
    $sheet->getStyle('A3:I3')->applyFromArray($estiloGrupo);
    $sheet->getRowDimension(3)->setRowHeight(22);

    // Meses en fila 3-4 (merge vertical para que ocupe 1 bloque grande)
    for ($mes = 1; $mes <= 12; $mes++) {
        $colMes = $colInicioMeses + ($mes - 1);
        $colLetra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colMes);

        $sheet->setCellValue($colLetra . '3', $nombresMeses[$mes]);
        $sheet->mergeCells($colLetra . '3:' . $colLetra . '4');

        $colorMes = $coloresMeses[$mes];
        $estiloMes = $estiloHeaderBase;
        $estiloMes['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorMes]];
        $estiloMes['font'] = ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']];
        $sheet->getStyle($colLetra . '3:' . $colLetra . '4')->applyFromArray($estiloMes);
    }

    // FILA 4: Sub-headers empleado (sin filtro en meses)
    $headersBase = [
        'ID',
        mb_convert_encoding("No. N\xC3\xB3mina", 'UTF-8', 'UTF-8'),
        'Nombre', 'Periodo', 'Empresa', 'Puesto', 'Fecha de Ingreso',
        mb_convert_encoding("Antig\xC3\xBCedad", 'UTF-8', 'UTF-8'),
        mb_convert_encoding("D\xC3\xADas Restantes", 'UTF-8', 'UTF-8')
    ];
    foreach ($headersBase as $i => $header) {
        $colLetra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($colLetra . '4', $header);
        $estilo = $estiloHeaderBase;
        $estilo['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2d6a4f']];
        $sheet->getStyle($colLetra . '4')->applyFromArray($estilo);
    }

    $sheet->getRowDimension(4)->setRowHeight(22);

    // =====================================================
    // FUNCION HELPER: escribir empleado con color unico
    // =====================================================
    $texto_anio = json_decode('"\u00f1"');
    $texto_dia = json_decode('"\u00ed"');

    function escribirEmpleado($sheet, &$fila, $infoEmpleado, $solicitudesPorMes, $estilos, $colInicioMeses, $colorEmpleado, $esManual = false) {
        $maxFilas = 1;
        for ($m = 1; $m <= 12; $m++) {
            if (isset($solicitudesPorMes[$m])) {
                $maxFilas = max($maxFilas, count($solicitudesPorMes[$m]));
            }
        }

        $filaInicio = $fila;

        // Info del empleado
        $sheet->setCellValue('A' . $fila, $infoEmpleado['id']);
        $sheet->setCellValue('B' . $fila, $infoEmpleado['no_nomina']);
        $sheet->setCellValue('C' . $fila, $infoEmpleado['nombre']);
        $sheet->setCellValue('D' . $fila, $infoEmpleado['periodo']);
        $sheet->setCellValue('E' . $fila, $infoEmpleado['empresa']);
        $sheet->setCellValue('F' . $fila, $infoEmpleado['puesto']);
        $sheet->setCellValue('G' . $fila, $infoEmpleado['fecha_ingreso']);
        $sheet->setCellValue('H' . $fila, $infoEmpleado['antiguedad']);
        $sheet->setCellValue('I' . $fila, $infoEmpleado['dias_restantes']);

        // Merge vertical si multiples filas
        if ($maxFilas > 1) {
            $filaFin = $fila + $maxFilas - 1;
            for ($c = 1; $c <= 9; $c++) {
                $cl = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $sheet->mergeCells($cl . $filaInicio . ':' . $cl . $filaFin);
            }
        }

        // Estilo info con color del empleado de fondo
        $filaFinReal = $filaInicio + $maxFilas - 1;
        $sheet->getStyle('A' . $filaInicio . ':I' . $filaFinReal)->applyFromArray($estilos['celda']);
        $sheet->getStyle('A' . $filaInicio . ':I' . $filaFinReal)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colorEmpleado);
        $sheet->getStyle('A' . $filaInicio)->applyFromArray($estilos['centrada']);
        $sheet->getStyle('B' . $filaInicio)->applyFromArray($estilos['centrada']);
        $sheet->getStyle('D' . $filaInicio)->applyFromArray($estilos['centrada']);
        $sheet->getStyle('I' . $filaInicio)->applyFromArray($estilos['centrada']);

        // Color dias restantes
        $dr = $infoEmpleado['dias_restantes'];
        if ($dr <= 0) {
            $sheet->getStyle('I' . $filaInicio)->getFont()->getColor()->setRGB('DC3545');
        } else {
            $sheet->getStyle('I' . $filaInicio)->getFont()->setBold(true)->getColor()->setRGB('198754');
        }

        // Solicitudes por mes: color del empleado en sus celdas de fecha
        for ($mes = 1; $mes <= 12; $mes++) {
            $colMes = $colInicioMeses + ($mes - 1);
            $colLetra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colMes);

            if (isset($solicitudesPorMes[$mes])) {
                foreach ($solicitudesPorMes[$mes] as $idx => $sol) {
                    $filaActual = $filaInicio + $idx;
                    $valor = date('d/m/Y', strtotime($sol['inicio'])) . ' - ' . date('d/m/Y', strtotime($sol['fin']));
                    $sheet->setCellValue($colLetra . $filaActual, $valor);
                    $sheet->getStyle($colLetra . $filaActual)->applyFromArray($estilos['centrada']);
                    // Color del empleado (no del mes)
                    $sheet->getStyle($colLetra . $filaActual)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colorEmpleado);
                }
            }

            // Bordes para filas vacias
            for ($r = 0; $r < $maxFilas; $r++) {
                $filaR = $filaInicio + $r;
                $celdaActual = $colLetra . $filaR;
                if (!$sheet->getCell($celdaActual)->getValue()) {
                    $sheet->getStyle($celdaActual)->applyFromArray($estilos['centrada']);
                }
            }
        }

        $fila += $maxFilas;
        return $maxFilas;
    }

    // =====================================================
    // DATOS (desde fila 5)
    // =====================================================
    $fila = 5;
    $contadorColor = 0;
    $estilosParam = [
        'celda' => $estiloCelda,
        'centrada' => $estiloCeldaCentrada
    ];

    // --- EMPLEADOS CON CUENTA ---
    foreach ($empleados as $emp) {
        $uid = $emp['id'];
        $antiguedad_texto = '';
        $periodo_texto = 'N/A';
        $empresa_texto = 'N/A';
        $dias_restantes = 0;

        if (!empty($emp['fecha_ingreso'])) {
            $fi = new DateTime($emp['fecha_ingreso']);
            $diff = $fi->diff(new DateTime());
            $antiguedad_texto = $diff->y . ' a' . $texto_anio . 'o' . ($diff->y != 1 ? 's' : '') . ', '
                              . $diff->m . ' mes' . ($diff->m != 1 ? 'es' : '') . ', '
                              . $diff->d . ' d' . $texto_dia . 'a' . ($diff->d != 1 ? 's' : '');
            $resumen = obtener_resumen_vacaciones($pdo, $uid);
            $dias_restantes = $resumen['dias_disponibles'] ?? 0;
        }

        $pp = $emp['periodo_pago'] ?? '';
        if ($pp === 'quincenal') $periodo_texto = 'Quincenal';
        elseif ($pp === 'semanal') $periodo_texto = 'Semanal';

        $ev = $emp['empresa'] ?? '';
        if ($ev === 'resimex') $empresa_texto = 'Resimex';
        elseif ($ev === 'carganova') $empresa_texto = 'Carganova';

        $info = [
            'id' => $uid,
            'no_nomina' => $emp['no_nomina'] ?? '',
            'nombre' => $emp['nombre_completo'],
            'periodo' => $periodo_texto,
            'empresa' => $empresa_texto,
            'puesto' => $emp['puesto'] ?? 'N/A',
            'fecha_ingreso' => !empty($emp['fecha_ingreso']) ? date('d/m/Y', strtotime($emp['fecha_ingreso'])) : 'N/A',
            'antiguedad' => $antiguedad_texto,
            'dias_restantes' => $dias_restantes
        ];

        $colorEmp = $coloresEmpleados[$contadorColor % count($coloresEmpleados)];
        $solsMes = $vacaciones_por_usuario[$uid] ?? [];
        escribirEmpleado($sheet, $fila, $info, $solsMes, $estilosParam, $colInicioMeses, $colorEmp);
        $contadorColor++;
    }

    // --- EMPLEADOS MANUALES ---
    foreach ($manuales_agrupados as $datos) {
        $sm = $datos['info'];
        $antiguedad_texto = '';
        $dias_restantes = 0;

        if (!empty($sm['fecha_ingreso_manual'])) {
            $fi_m = new DateTime($sm['fecha_ingreso_manual']);
            $diff_m = $fi_m->diff(new DateTime());
            $antiguedad_texto = $diff_m->y . ' a' . $texto_anio . 'o' . ($diff_m->y != 1 ? 's' : '') . ', '
                              . $diff_m->m . ' mes' . ($diff_m->m != 1 ? 'es' : '') . ', '
                              . $diff_m->d . ' d' . $texto_dia . 'a' . ($diff_m->d != 1 ? 's' : '');
            $anios_m = max(1, $diff_m->y);
            $dias_lft_m = dias_vacaciones_lft($anios_m);
            $dias_tomados_m = 0;
            foreach ($datos['meses'] as $sols_mes) {
                foreach ($sols_mes as $s) {
                    $dias_tomados_m += contar_dias_habiles($s['inicio'], $s['fin']);
                }
            }
            $dias_restantes = max(0, $dias_lft_m - $dias_tomados_m);
        }

        $pp = $sm['periodo_pago_manual'] ?? '';
        $periodo_texto = 'N/A';
        if ($pp === 'quincenal') $periodo_texto = 'Quincenal';
        elseif ($pp === 'semanal') $periodo_texto = 'Semanal';

        $ev = $sm['empresa_manual'] ?? '';
        $empresa_texto = 'N/A';
        if ($ev === 'resimex') $empresa_texto = 'Resimex';
        elseif ($ev === 'carganova') $empresa_texto = 'Carganova';

        $info = [
            'id' => '*',
            'no_nomina' => $sm['no_nomina_manual'] ?? '',
            'nombre' => ($sm['nombre_manual'] ?? '') . ' (Manual)',
            'periodo' => $periodo_texto,
            'empresa' => $empresa_texto,
            'puesto' => $sm['puesto_manual'] ?? 'N/A',
            'fecha_ingreso' => !empty($sm['fecha_ingreso_manual']) ? date('d/m/Y', strtotime($sm['fecha_ingreso_manual'])) : 'N/A',
            'antiguedad' => $antiguedad_texto,
            'dias_restantes' => $dias_restantes
        ];

        $colorEmp = $coloresEmpleados[$contadorColor % count($coloresEmpleados)];
        escribirEmpleado($sheet, $fila, $info, $datos['meses'], $estilosParam, $colInicioMeses, $colorEmp, true);
        $contadorColor++;
    }

    // =====================================================
    // ANCHOS
    // =====================================================
    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(28);
    $sheet->getColumnDimension('D')->setWidth(12);
    $sheet->getColumnDimension('E')->setWidth(14);
    $sheet->getColumnDimension('F')->setWidth(22);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(28);
    $sheet->getColumnDimension('I')->setWidth(14);

    for ($mes = 1; $mes <= 12; $mes++) {
        $colMes = $colInicioMeses + ($mes - 1);
        $cl = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colMes);
        $sheet->getColumnDimension($cl)->setWidth(24);
    }

    // Congelar paneles y filtro SOLO en columnas de empleado (A-I)
    $sheet->freezePane('J5');
    $sheet->setAutoFilter('A4:I4');

    // =====================================================
    // DESCARGAR
    // =====================================================
    $filename = 'Vacaciones_' . $anio . '_' . date('Y-m-d_His') . '.xlsx';
    if (ob_get_length()) { ob_end_clean(); }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    error_log("Excel vacaciones descargado por: " . $_SESSION['nombre_completo'] . " - Empleados: " . $totalEmpleados);
    exit;

} catch (Exception $e) {
    error_log("Error al generar Excel de vacaciones: " . $e->getMessage());
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Error al generar el archivo Excel: ' . $e->getMessage()];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_gth.php');
    exit;
}