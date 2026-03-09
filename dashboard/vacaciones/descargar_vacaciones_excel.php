<?php
/**
 * Descargar Base de Datos de Vacaciones en Excel
 * dashboard/vacaciones/descargar_vacaciones_excel.php
 *
 * Estructura:
 * - Fila 1: Titulo
 * - Fila 2: Subtitulo
 * - Fila 3: "INFORMACION DEL TRABAJADOR" (A-I) + Nombres de meses (J-AG, merge horizontal)
 * - Fila 4: Sub-headers (ID, No. Nomina...) + 1ra fecha / 2da fecha (SIN merges verticales)
 * - Fila 5+: Datos
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

    $sql_solicitudes = "
        SELECT sv.usuario_id, sv.fecha_inicio, sv.fecha_fin, sv.dias_solicitados, sv.estado
        FROM solicitudes_vacaciones sv
        WHERE sv.estado IN ('pendiente_gth', 'aprobada_gth', 'completada', 'aprobada_admin')
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
        if (!isset($vacaciones_por_usuario[$uid][$mes])) {
            $vacaciones_por_usuario[$uid][$mes] = [];
        }
        $vacaciones_por_usuario[$uid][$mes][] = [
            'inicio' => $sol['fecha_inicio'],
            'fin'    => $sol['fecha_fin']
        ];
    }

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('Sistema GrupoVerden - GTH')
        ->setTitle('Reporte de Vacaciones ' . $anio)
        ->setSubject('Control de Vacaciones');

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

    // FILA 1: TITULO
    $ultimaCol = 33;
    $ultimaColLetra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ultimaCol);

    $sheet->setCellValue('A1', 'CONTROL DE VACACIONES ' . $anio . ' - GRUPOVERDEN');
    $sheet->mergeCells('A1:' . $ultimaColLetra . '1');
    $sheet->getStyle('A1')->applyFromArray($estiloTitulo);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // FILA 2: SUBTITULO
    $sheet->setCellValue('A2', 'Generado: ' . date('d/m/Y H:i:s') . ' | Total empleados: ' . count($empleados));
    $sheet->mergeCells('A2:' . $ultimaColLetra . '2');
    $sheet->getStyle('A2')->applyFromArray($estiloSubtitulo);

    // =====================================================
    // FILA 3: "INFORMACION DEL TRABAJADOR" (A3:I3) + Meses (solo merge horizontal)
    // FILA 4: Sub-headers empleado + 1ra/2da fecha (SIN merges verticales)
    // =====================================================

    // "INFORMACION DEL TRABAJADOR" en A3:I3
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

    // Meses en fila 3 (solo merge horizontal entre 2 columnas, NO vertical)
    $colInicioMeses = 10;

    for ($mes = 1; $mes <= 12; $mes++) {
        $col1 = $colInicioMeses + ($mes - 1) * 2;
        $col2 = $col1 + 1;
        $col1Letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col1);
        $col2Letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col2);

        $sheet->setCellValue($col1Letra . '3', $nombresMeses[$mes]);
        $sheet->mergeCells($col1Letra . '3:' . $col2Letra . '3');

        $colorMes = $coloresMeses[$mes];
        $estiloMes = $estiloHeaderBase;
        $estiloMes['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorMes]];
        $estiloMes['font'] = ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']];
        $sheet->getStyle($col1Letra . '3:' . $col2Letra . '3')->applyFromArray($estiloMes);
    }

    // FILA 4: Sub-headers empleado (sin merge vertical)
    $headersBase = [
        'ID',
        mb_convert_encoding("No. N\xC3\xB3mina", 'UTF-8', 'UTF-8'),
        'Nombre',
        'Periodo',
        'Empresa',
        'Puesto',
        'Fecha de Ingreso',
        mb_convert_encoding("Antig\xC3\xBCedad", 'UTF-8', 'UTF-8'),
        mb_convert_encoding("D\xC3\xADas Restantes", 'UTF-8', 'UTF-8')
    ];

    foreach ($headersBase as $i => $header) {
        $col = $i + 1;
        $colLetra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $sheet->setCellValue($colLetra . '4', $header);
        $estilo = $estiloHeaderBase;
        $estilo['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2d6a4f']];
        $sheet->getStyle($colLetra . '4')->applyFromArray($estilo);
    }

    // 1ra fecha / 2da fecha en fila 4 (sin merge vertical)
    for ($mes = 1; $mes <= 12; $mes++) {
        $col1 = $colInicioMeses + ($mes - 1) * 2;
        $col2 = $col1 + 1;
        $col1Letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col1);
        $col2Letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col2);

        $sheet->setCellValue($col1Letra . '4', '1ra fecha');
        $sheet->setCellValue($col2Letra . '4', '2da fecha');

        $colorMes = $coloresMeses[$mes];
        $estiloSub = $estiloHeaderBase;
        $estiloSub['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorMes]];
        $estiloSub['font'] = ['bold' => true, 'size' => 8, 'color' => ['rgb' => '333333']];
        $sheet->getStyle($col1Letra . '4')->applyFromArray($estiloSub);
        $sheet->getStyle($col2Letra . '4')->applyFromArray($estiloSub);
    }

    $sheet->getRowDimension(4)->setRowHeight(22);

    // =====================================================
    // DATOS (desde fila 5)
    // =====================================================
    $fila = 5;
    $texto_anio = json_decode('"\u00f1"');
    $texto_dia = json_decode('"\u00ed"');

    foreach ($empleados as $emp) {
        $uid = $emp['id'];
        $antiguedad_texto = '';
        $periodo_texto = '';
        $dias_restantes = 0;

        if (!empty($emp['fecha_ingreso'])) {
            $fecha_ingreso = new DateTime($emp['fecha_ingreso']);
            $hoy = new DateTime();
            $diff = $fecha_ingreso->diff($hoy);
            $antiguedad_texto = $diff->y . ' a' . $texto_anio . 'o' . ($diff->y != 1 ? 's' : '') . ', '
                              . $diff->m . ' mes' . ($diff->m != 1 ? 'es' : '') . ', '
                              . $diff->d . ' d' . $texto_dia . 'a' . ($diff->d != 1 ? 's' : '');

            $resumen = obtener_resumen_vacaciones($pdo, $uid);
            // Periodo de pago desde BD
            $periodo_pago_val = $emp['periodo_pago'] ?? '';
            if ($periodo_pago_val === 'quincenal') {
                $periodo_texto = 'Quincenal';
            } elseif ($periodo_pago_val === 'semanal') {
                $periodo_texto = 'Semanal';
            } else {
                $periodo_texto = 'N/A';
            }
            $dias_restantes = $resumen['dias_disponibles'] ?? 0;
        }

        $sheet->setCellValue('A' . $fila, $uid);
        $sheet->setCellValue('B' . $fila, $emp['no_nomina'] ?? '');
        $sheet->setCellValue('C' . $fila, $emp['nombre_completo']);
        $sheet->setCellValue('D' . $fila, $periodo_texto);
        $empresa_val = $emp['empresa'] ?? '';
        if ($empresa_val === 'resimex') {
            $empresa_texto = 'Resimex';
        } elseif ($empresa_val === 'carganova') {
            $empresa_texto = 'Carganova';
        } else {
            $empresa_texto = 'N/A';
        }
        $sheet->setCellValue('E' . $fila, $empresa_texto);
        $sheet->setCellValue('F' . $fila, $emp['puesto'] ?? 'N/A');
        $sheet->setCellValue('G' . $fila, !empty($emp['fecha_ingreso']) ? date('d/m/Y', strtotime($emp['fecha_ingreso'])) : 'N/A');
        $sheet->setCellValue('H' . $fila, $antiguedad_texto);
        $sheet->setCellValue('I' . $fila, $dias_restantes);

        $sheet->getStyle('A' . $fila . ':I' . $fila)->applyFromArray($estiloCelda);
        $sheet->getStyle('A' . $fila)->applyFromArray($estiloCeldaCentrada);
        $sheet->getStyle('B' . $fila)->applyFromArray($estiloCeldaCentrada);
        $sheet->getStyle('D' . $fila)->applyFromArray($estiloCeldaCentrada);
        $sheet->getStyle('I' . $fila)->applyFromArray($estiloCeldaCentrada);

        if ($dias_restantes <= 0) {
            $sheet->getStyle('I' . $fila)->getFont()->getColor()->setRGB('DC3545');
        } else {
            $sheet->getStyle('I' . $fila)->getFont()->setBold(true)->getColor()->setRGB('198754');
        }

        for ($mes = 1; $mes <= 12; $mes++) {
            $col1 = $colInicioMeses + ($mes - 1) * 2;
            $col2 = $col1 + 1;
            $col1Letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col1);
            $col2Letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col2);

            $valor1 = '';
            $valor2 = '';

            if (isset($vacaciones_por_usuario[$uid][$mes])) {
                $sols = $vacaciones_por_usuario[$uid][$mes];
                if (isset($sols[0])) {
                    $valor1 = date('d/m/Y', strtotime($sols[0]['inicio'])) . ' - ' . date('d/m/Y', strtotime($sols[0]['fin']));
                }
                if (isset($sols[1])) {
                    $valor2 = date('d/m/Y', strtotime($sols[1]['inicio'])) . ' - ' . date('d/m/Y', strtotime($sols[1]['fin']));
                }
            }

            $sheet->setCellValue($col1Letra . $fila, $valor1);
            $sheet->setCellValue($col2Letra . $fila, $valor2);
            $sheet->getStyle($col1Letra . $fila)->applyFromArray($estiloCeldaCentrada);
            $sheet->getStyle($col2Letra . $fila)->applyFromArray($estiloCeldaCentrada);

            $colorFondo = $coloresMeses[$mes];
            if ($valor1) {
                $sheet->getStyle($col1Letra . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colorFondo);
            }
            if ($valor2) {
                $sheet->getStyle($col2Letra . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colorFondo);
            }
        }

        if ($fila % 2 == 0) {
            $sheet->getStyle('A' . $fila . ':I' . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
        }

        $fila++;
    }

    // =====================================================
    // SOLICITUDES MANUALES (empleados sin cuenta)
    // =====================================================
    $sql_manuales = "
        SELECT sv.id, sv.nombre_manual, sv.no_nomina_manual, sv.puesto_manual, 
               sv.fecha_ingreso_manual, sv.periodo_pago_manual, sv.empresa_manual,
               sv.fecha_inicio, sv.fecha_fin, sv.dias_solicitados,
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

    // Agrupar por nombre_manual (como clave unica)
    $manuales_agrupados = [];
    foreach ($solicitudes_manuales as $sm) {
        $clave = $sm['nombre_manual'] . '|' . ($sm['no_nomina_manual'] ?? '');
        if (!isset($manuales_agrupados[$clave])) {
            $manuales_agrupados[$clave] = [
                'info' => $sm,
                'meses' => []
            ];
        }
        $mes = (int)date('n', strtotime($sm['fecha_inicio']));
        if (!isset($manuales_agrupados[$clave]['meses'][$mes])) {
            $manuales_agrupados[$clave]['meses'][$mes] = [];
        }
        $manuales_agrupados[$clave]['meses'][$mes][] = [
            'inicio' => $sm['fecha_inicio'],
            'fin'    => $sm['fecha_fin']
        ];
    }

    foreach ($manuales_agrupados as $clave => $datos) {
        $sm = $datos['info'];
        
        $antiguedad_texto = '';
        $dias_restantes = 0;
        $periodo_texto = 'N/A';
        
        if (!empty($sm['fecha_ingreso_manual'])) {
            $fi_m = new DateTime($sm['fecha_ingreso_manual']);
            $hoy_m = new DateTime();
            $diff_m = $fi_m->diff($hoy_m);
            $antiguedad_texto = $diff_m->y . ' a' . $texto_anio . 'o' . ($diff_m->y != 1 ? 's' : '') . ', '
                              . $diff_m->m . ' mes' . ($diff_m->m != 1 ? 'es' : '') . ', '
                              . $diff_m->d . ' d' . $texto_dia . 'a' . ($diff_m->d != 1 ? 's' : '');
            $anios_m = max(1, $diff_m->y);
            $dias_lft_m = dias_vacaciones_lft($anios_m);
            // Sumar dias solicitados de todas las solicitudes manuales de este empleado
            $dias_tomados_m = 0;
            foreach ($datos['meses'] as $mes_sols) {
                foreach ($mes_sols as $s) {
                    $dias_tomados_m += contar_dias_habiles($s['inicio'], $s['fin']);
                }
            }
            $dias_restantes = max(0, $dias_lft_m - $dias_tomados_m);
        }
        
        $pp = $sm['periodo_pago_manual'] ?? '';
        if ($pp === 'quincenal') { $periodo_texto = 'Quincenal'; }
        elseif ($pp === 'semanal') { $periodo_texto = 'Semanal'; }
        
        $emp_val = $sm['empresa_manual'] ?? '';
        if ($emp_val === 'resimex') { $empresa_texto = 'Resimex'; }
        elseif ($emp_val === 'carganova') { $empresa_texto = 'Carganova'; }
        else { $empresa_texto = 'N/A'; }

        // Marcar como manual con asterisco
        $sheet->setCellValue('A' . $fila, '*');
        $sheet->setCellValue('B' . $fila, $sm['no_nomina_manual'] ?? '');
        $sheet->setCellValue('C' . $fila, ($sm['nombre_manual'] ?? '') . ' (Manual)');
        $sheet->setCellValue('D' . $fila, $periodo_texto);
        $sheet->setCellValue('E' . $fila, $empresa_texto);
        $sheet->setCellValue('F' . $fila, $sm['puesto_manual'] ?? 'N/A');
        $sheet->setCellValue('G' . $fila, !empty($sm['fecha_ingreso_manual']) ? date('d/m/Y', strtotime($sm['fecha_ingreso_manual'])) : 'N/A');
        $sheet->setCellValue('H' . $fila, $antiguedad_texto);
        $sheet->setCellValue('I' . $fila, $dias_restantes);

        $sheet->getStyle('A' . $fila . ':I' . $fila)->applyFromArray($estiloCelda);
        $sheet->getStyle('A' . $fila)->applyFromArray($estiloCeldaCentrada);
        $sheet->getStyle('B' . $fila)->applyFromArray($estiloCeldaCentrada);
        $sheet->getStyle('D' . $fila)->applyFromArray($estiloCeldaCentrada);
        $sheet->getStyle('I' . $fila)->applyFromArray($estiloCeldaCentrada);
        
        // Fondo amarillo claro para distinguir manuales
        $sheet->getStyle('A' . $fila . ':I' . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF8E1');
        
        if ($dias_restantes <= 0) {
            $sheet->getStyle('I' . $fila)->getFont()->getColor()->setRGB('DC3545');
        } else {
            $sheet->getStyle('I' . $fila)->getFont()->setBold(true)->getColor()->setRGB('198754');
        }

        // Columnas de meses
        for ($mes = 1; $mes <= 12; $mes++) {
            $col1 = $colInicioMeses + ($mes - 1) * 2;
            $col2 = $col1 + 1;
            $col1Letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col1);
            $col2Letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col2);

            $valor1 = '';
            $valor2 = '';

            if (isset($datos['meses'][$mes])) {
                $sols_mes = $datos['meses'][$mes];
                if (isset($sols_mes[0])) {
                    $valor1 = date('d/m/Y', strtotime($sols_mes[0]['inicio'])) . ' - ' . date('d/m/Y', strtotime($sols_mes[0]['fin']));
                }
                if (isset($sols_mes[1])) {
                    $valor2 = date('d/m/Y', strtotime($sols_mes[1]['inicio'])) . ' - ' . date('d/m/Y', strtotime($sols_mes[1]['fin']));
                }
            }

            $sheet->setCellValue($col1Letra . $fila, $valor1);
            $sheet->setCellValue($col2Letra . $fila, $valor2);
            $sheet->getStyle($col1Letra . $fila)->applyFromArray($estiloCeldaCentrada);
            $sheet->getStyle($col2Letra . $fila)->applyFromArray($estiloCeldaCentrada);

            $colorFondo = $coloresMeses[$mes];
            if ($valor1) {
                $sheet->getStyle($col1Letra . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colorFondo);
            }
            if ($valor2) {
                $sheet->getStyle($col2Letra . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colorFondo);
            }
        }

        $fila++;
    }

    // ANCHOS
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
        $col1 = $colInicioMeses + ($mes - 1) * 2;
        $col2 = $col1 + 1;
        $c1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col1);
        $c2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col2);
        $sheet->getColumnDimension($c1)->setWidth(22);
        $sheet->getColumnDimension($c2)->setWidth(22);
    }

    $sheet->freezePane('J5');
    $sheet->setAutoFilter('A4:' . $ultimaColLetra . '4');

    // DESCARGAR
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

    error_log("Excel vacaciones descargado por: " . $_SESSION['nombre_completo'] . " - Empleados: " . count($empleados));
    exit;

} catch (Exception $e) {
    error_log("Error al generar Excel de vacaciones: " . $e->getMessage());
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Error al generar el archivo Excel: ' . $e->getMessage()];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_gth.php');
    exit;
}