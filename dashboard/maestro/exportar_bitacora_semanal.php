<?php
/**
 * Exportar Bitácora Semanal de Actividades a Excel
 * dashboard/maestro/exportar_bitacora_semanal.php
 *
 * Acceso: solo Sistemas / TI
 *
 * Recibe (GET o POST) los mismos parámetros que la Bitácora Semanal:
 *   semana        cualquier fecha YYYY-MM-DD de la semana (lunes a domingo)
 *   estado        activas (def.) | pendiente | en_proceso | finalizadas | todas
 *   prioridad, tipo, departamento, tecnico (id | 'sin'), buscar
 *
 * Genera 2 hojas:
 *   1) "Bitácora"  -> encabezado, indicadores y listado de actividades
 *   2) "Resumen"   -> totales por estado, prioridad, departamento y técnico
 *
 * v1.0
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// =====================================================================
// SEGURIDAD
// =====================================================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento_sesion = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_ti = in_array($departamento_sesion, ['ti', 'sistemas', 'ti_sistemas'], true)
         || (function_exists('es_usuario_ti') && es_usuario_ti());

if (!$es_ti) {
    establecer_alerta('error', 'No tiene permisos para acceder a esta función.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    establecer_alerta('error', 'PhpSpreadsheet no está instalado.');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

$in = array_merge($_GET, $_POST);

// =====================================================================
// SEMANA
// =====================================================================
$semana_param = trim((string) ($in['semana'] ?? ''));
$fecha_base = DateTime::createFromFormat('!Y-m-d', $semana_param);
if (!$fecha_base || $fecha_base->format('Y-m-d') !== $semana_param) {
    $fecha_base = new DateTime('today');
}
$lunes      = (clone $fecha_base)->modify('monday this week');
$domingo    = (clone $lunes)->modify('+6 days');
$fin_semana = (clone $lunes)->modify('+7 days');
$ini_sql    = $lunes->format('Y-m-d 00:00:00');
$fin_sql    = $fin_semana->format('Y-m-d 00:00:00');
$num_semana = (int) $lunes->format('W');

$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
          'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$texto_semana = 'Semana ' . $num_semana . ' · del ' . $lunes->format('j') . ' de ' . $meses[(int) $lunes->format('n')]
              . ' al ' . $domingo->format('j') . ' de ' . $meses[(int) $domingo->format('n')] . ' de ' . $domingo->format('Y');

// =====================================================================
// FILTROS (mismos criterios que la Bitácora Semanal)
// =====================================================================
$estados_filtro = [
    'activas'     => 'Pendientes + En proceso',
    'pendiente'   => 'Pendientes',
    'en_proceso'  => 'En proceso',
    'finalizadas' => 'Finalizadas en la semana',
    'todas'       => 'Todas (abiertas + finalizadas en la semana)',
];
$filtro_estado = (string) ($in['estado'] ?? 'activas');
if (!isset($estados_filtro[$filtro_estado])) $filtro_estado = 'activas';

$prioridades = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$filtro_prioridad = (string) ($in['prioridad'] ?? '');
if (!isset($prioridades[$filtro_prioridad])) $filtro_prioridad = '';

$filtro_tipo = (string) ($in['tipo'] ?? '');
if (!in_array($filtro_tipo, ['Apoyo', 'Problema'], true)) $filtro_tipo = '';

$filtro_depto = (int) ($in['departamento'] ?? 0);

$filtro_tecnico = (string) ($in['tecnico'] ?? '');
if ($filtro_tecnico !== 'sin') {
    $filtro_tecnico = ((int) $filtro_tecnico > 0) ? (string) (int) $filtro_tecnico : '';
}

$busqueda = trim((string) ($in['buscar'] ?? ''));

$pdo = conectarDB();

$cond_abiertas    = "(s.estado IN ('pendiente','en_proceso') AND s.fecha_creacion < ?)";
$cond_finalizadas = "(s.estado = 'finalizada' AND s.fecha_atencion >= ? AND s.fecha_atencion < ?)";

$where  = [];
$params = [];

switch ($filtro_estado) {
    case 'pendiente':
    case 'en_proceso':
        $where[]  = "(s.estado = ? AND s.fecha_creacion < ?)";
        array_push($params, $filtro_estado, $fin_sql);
        break;
    case 'finalizadas':
        $where[] = $cond_finalizadas;
        array_push($params, $ini_sql, $fin_sql);
        break;
    case 'todas':
        $where[] = "($cond_abiertas OR $cond_finalizadas)";
        array_push($params, $fin_sql, $ini_sql, $fin_sql);
        break;
    default:
        $where[]  = $cond_abiertas;
        $params[] = $fin_sql;
}

$filtros_texto = [];   // para mostrar en el encabezado del Excel

if ($filtro_prioridad) {
    $where[]  = "s.prioridad = ?";
    $params[] = $filtro_prioridad;
    $filtros_texto[] = 'Prioridad: ' . $prioridades[$filtro_prioridad];
}
if ($filtro_tipo) {
    $where[]  = "s.tipo_soporte = ?";
    $params[] = $filtro_tipo;
    $filtros_texto[] = 'Tipo: ' . $filtro_tipo;
}
if ($filtro_depto) {
    $where[]  = "COALESCE(s.departamento_id, u.departamento_id) = ?";
    $params[] = $filtro_depto;
    $st = $pdo->prepare("SELECT nombre FROM departamentos WHERE id = ?");
    $st->execute([$filtro_depto]);
    $filtros_texto[] = 'Departamento: ' . ($st->fetchColumn() ?: $filtro_depto);
}
if ($filtro_tecnico === 'sin') {
    $where[] = "s.atendido_por IS NULL";
    $filtros_texto[] = 'Atendido por: Sin asignar';
} elseif ($filtro_tecnico !== '') {
    $where[]  = "s.atendido_por = ?";
    $params[] = (int) $filtro_tecnico;
    $st = $pdo->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
    $st->execute([(int) $filtro_tecnico]);
    $filtros_texto[] = 'Atendido por: ' . ($st->fetchColumn() ?: $filtro_tecnico);
}
if ($busqueda !== '') {
    $where[] = "(s.folio LIKE ? OR s.descripcion LIKE ? OR COALESCE(s.nombre_solicitante, u.nombre_completo) LIKE ?
                 OR s.tipo_apoyo LIKE ? OR s.tipo_problema LIKE ?)";
    $like = '%' . $busqueda . '%';
    array_push($params, $like, $like, $like, $like, $like);
    $filtros_texto[] = 'Búsqueda: "' . $busqueda . '"';
}

// =====================================================================
// CONSULTAS
// =====================================================================
try {
    $sql = "SELECT s.id, s.folio, s.estado, s.prioridad, s.tipo_soporte, s.tipo_apoyo, s.tipo_problema,
                   s.descripcion, s.comentarios_ti, s.fecha_creacion, s.fecha_atencion,
                   COALESCE(s.nombre_solicitante, u.nombre_completo) AS solicitante,
                   COALESCE(d.nombre, s.departamento)               AS depto_nombre,
                   ut.nombre_completo                               AS tecnico_nombre,
                   (SELECT MAX(h.fecha_cambio) FROM historial_estados h
                     WHERE h.solicitud_id = s.id AND h.estado_nuevo = 'en_proceso') AS fecha_en_proceso
            FROM solicitudes_atencion s
            INNER JOIN usuarios u     ON s.usuario_id = u.id
            LEFT JOIN departamentos d ON d.id = COALESCE(s.departamento_id, u.departamento_id)
            LEFT JOIN usuarios ut     ON s.atendido_por = ut.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                CASE s.estado WHEN 'en_proceso' THEN 1 WHEN 'pendiente' THEN 2 ELSE 3 END,
                CASE s.prioridad WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END,
                s.fecha_creacion ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Indicadores generales de la semana (sin filtros adicionales, igual que en pantalla)
    $sql_kpi = "SELECT
        SUM(CASE WHEN estado = 'pendiente'  AND fecha_creacion < ? THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN estado = 'en_proceso' AND fecha_creacion < ? THEN 1 ELSE 0 END) AS en_proceso,
        SUM(CASE WHEN fecha_creacion >= ? AND fecha_creacion < ? THEN 1 ELSE 0 END)  AS nuevas,
        SUM(CASE WHEN estado = 'finalizada' AND fecha_atencion >= ? AND fecha_atencion < ? THEN 1 ELSE 0 END) AS finalizadas,
        SUM(CASE WHEN estado IN ('pendiente','en_proceso') AND fecha_creacion < ?
                  AND prioridad IN ('critica','alta') THEN 1 ELSE 0 END) AS urgentes
    FROM solicitudes_atencion";
    $stmt = $pdo->prepare($sql_kpi);
    $stmt->execute([$fin_sql, $fin_sql, $ini_sql, $fin_sql, $ini_sql, $fin_sql, $fin_sql]);
    $kpi = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log('Error exportando Bitácora Semanal: ' . $e->getMessage());
    establecer_alerta('error', 'Error al consultar la bitácora semanal.');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// =====================================================================
// HELPERS
// =====================================================================
$nombres_estado = ['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso',
                   'finalizada' => 'Finalizada', 'cancelada' => 'Cancelada'];
$colores_estado = ['pendiente' => 'FFF3CD', 'en_proceso' => 'CFE2FF',
                   'finalizada' => 'D1E7DD', 'cancelada' => 'E2E3E5'];
$colores_prioridad = ['critica' => 'F8D7DA', 'alta' => 'FFE5B4', 'media' => 'CFF4FC', 'baja' => 'D1E7DD'];

function bse_dias(array $a, DateTime $fin_semana) {
    $inicio = new DateTime($a['fecha_creacion']);
    if ($a['estado'] === 'finalizada' && !empty($a['fecha_atencion'])) {
        $fin = new DateTime($a['fecha_atencion']);
    } else {
        $hoy = new DateTime();
        $fin = ($hoy < $fin_semana) ? $hoy : $fin_semana;
    }
    return max(0, (int) $inicio->diff($fin)->days);
}

function bse_fecha_excel($valor) {
    if (empty($valor)) return null;
    return ExcelDate::PHPToExcel(new DateTime($valor));
}

$verde_header = 'C6E0B4';   // mismo tono que los formatos FR-TI
$borde_fino = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]]];

// =====================================================================
// HOJA 1: BITÁCORA
// =====================================================================
try {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Bitácora');

    $columnas = [
        'A' => ['No.', 5],
        'B' => ['Folio', 21],
        'C' => ['Fecha de solicitud', 15],
        'D' => ['Solicitante', 22],
        'E' => ['Departamento', 18],
        'F' => ['Tipo', 10],
        'G' => ['Detalle', 26],
        'H' => ['Descripción', 42],
        'I' => ['Prioridad', 10],
        'J' => ['Estado', 12],
        'K' => ['En proceso desde', 15],
        'L' => ['Atendido por', 20],
        'M' => ['Finalizada', 15],
        'N' => ['Antigüedad (días)', 11],
        'O' => ['Comentarios de Sistemas', 38],
    ];
    $ultima_col = 'O';
    foreach ($columnas as $col => [, $ancho]) {
        $sheet->getColumnDimension($col)->setWidth($ancho);
    }

    // ---- Encabezado del formato ----
    $sheet->mergeCells('A1:C3');
    $logo = null;
    foreach (['LOGO RESIMEX.jpg', 'Logo.png'] as $archivo_logo) {
        $ruta = __DIR__ . '/../../assets/img/' . $archivo_logo;
        if (file_exists($ruta)) { $logo = $ruta; break; }
    }
    if ($logo) {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setPath($logo);
        $drawing->setHeight(42);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(8);
        $drawing->setOffsetY(6);
        $drawing->setWorksheet($sheet);
    }

    $sheet->mergeCells('D1:' . $ultima_col . '2');
    $sheet->setCellValue('D1', 'BITÁCORA SEMANAL DE ACTIVIDADES DE SISTEMAS');
    $sheet->getStyle('D1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->mergeCells('D3:' . $ultima_col . '3');
    $sheet->setCellValue('D3', $texto_semana);
    $sheet->getStyle('D3')->getFont()->setBold(true)->setSize(10);
    $sheet->getStyle('D3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1:' . $ultima_col . '3')->applyFromArray([
        'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]],
    ]);
    $sheet->getStyle('A1:C3')->applyFromArray(['borders' => ['right' => ['borderStyle' => Border::BORDER_MEDIUM]]]);
    foreach ([1, 2, 3] as $r) $sheet->getRowDimension($r)->setRowHeight(18);

    // ---- Datos de generación ----
    $sheet->mergeCells('A5:' . $ultima_col . '5');
    $sheet->setCellValue('A5', 'Contenido: ' . $estados_filtro[$filtro_estado]
        . (empty($filtros_texto) ? '' : '   |   Filtros: ' . implode(' · ', $filtros_texto)));
    $sheet->mergeCells('A6:' . $ultima_col . '6');
    $sheet->setCellValue('A6', 'Generado el ' . date('d/m/Y H:i') . ' por ' . ($_SESSION['nombre_completo'] ?? 'Sistemas')
        . '. Las actividades abiertas incluyen las creadas en semanas anteriores que siguen sin cerrarse.');
    $sheet->getStyle('A5:A6')->getFont()->setItalic(true)->getColor()->setRGB('555555');

    // ---- Indicadores ----
    $indicadores = [
        ['Pendientes', (int) ($kpi['pendientes'] ?? 0), 'FFF3CD'],
        ['En proceso', (int) ($kpi['en_proceso'] ?? 0), 'CFE2FF'],
        ['Nuevas en la semana', (int) ($kpi['nuevas'] ?? 0), 'E2D9F3'],
        ['Finalizadas en la semana', (int) ($kpi['finalizadas'] ?? 0), 'D1E7DD'],
        ['Abiertas críticas / altas', (int) ($kpi['urgentes'] ?? 0), 'F8D7DA'],
    ];
    // Cada indicador ocupa 3 columnas (A-C, D-F, G-I, J-L, M-O)
    $col_ini = 1;
    foreach ($indicadores as [$etiqueta, $valor, $color]) {
        $c1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_ini);
        $c3 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_ini + 2);
        $sheet->mergeCells("{$c1}8:{$c3}8");
        $sheet->mergeCells("{$c1}9:{$c3}9");
        $sheet->setCellValue("{$c1}8", $valor);
        $sheet->setCellValue("{$c1}9", $etiqueta);
        $sheet->getStyle("{$c1}8:{$c3}9")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]],
        ]);
        $sheet->getStyle("{$c1}8")->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle("{$c1}9")->getFont()->setSize(8)->setBold(true);
        $col_ini += 3;
    }
    $sheet->getRowDimension(8)->setRowHeight(24);

    // ---- Encabezado de la tabla ----
    $FILA_HEADER = 11;
    foreach ($columnas as $col => [$titulo]) {
        $sheet->setCellValue($col . $FILA_HEADER, $titulo);
    }
    $rango_header = 'A' . $FILA_HEADER . ':' . $ultima_col . $FILA_HEADER;
    $sheet->getStyle($rango_header)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $verde_header]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    ] + $borde_fino);
    $sheet->getRowDimension($FILA_HEADER)->setRowHeight(30);

    // ---- Filas ----
    $fila = $FILA_HEADER + 1;
    if (empty($actividades)) {
        $sheet->mergeCells('A' . $fila . ':' . $ultima_col . $fila);
        $sheet->setCellValue('A' . $fila, 'No hay actividades con estos criterios en la semana seleccionada.');
        $sheet->getStyle('A' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $fila . ':' . $ultima_col . $fila)->applyFromArray($borde_fino);
        $fila++;
    }

    foreach ($actividades as $i => $a) {
        $detalle = $a['tipo_soporte'] === 'Apoyo' ? ($a['tipo_apoyo'] ?? '') : ($a['tipo_problema'] ?? '');

        $sheet->setCellValue('A' . $fila, $i + 1);
        $sheet->setCellValueExplicit('B' . $fila, $a['folio'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('C' . $fila, bse_fecha_excel($a['fecha_creacion']));
        $sheet->setCellValue('D' . $fila, $a['solicitante'] ?? '—');
        $sheet->setCellValue('E' . $fila, $a['depto_nombre'] ?? '—');
        $sheet->setCellValue('F' . $fila, $a['tipo_soporte']);
        $sheet->setCellValue('G' . $fila, trim((string) $detalle));
        $sheet->setCellValue('H' . $fila, (string) $a['descripcion']);
        $sheet->setCellValue('I' . $fila, $prioridades[$a['prioridad']] ?? ucfirst($a['prioridad']));
        $sheet->setCellValue('J' . $fila, $nombres_estado[$a['estado']] ?? $a['estado']);
        $sheet->setCellValue('K' . $fila, bse_fecha_excel($a['fecha_en_proceso']));
        $sheet->setCellValue('L' . $fila, $a['tecnico_nombre'] ?: 'Sin asignar');
        $sheet->setCellValue('M' . $fila, $a['estado'] === 'finalizada' ? bse_fecha_excel($a['fecha_atencion']) : null);
        $sheet->setCellValue('N' . $fila, bse_dias($a, $fin_semana));
        $sheet->setCellValue('O' . $fila, (string) ($a['comentarios_ti'] ?? ''));

        if (isset($colores_estado[$a['estado']])) {
            $sheet->getStyle('J' . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colores_estado[$a['estado']]);
        }
        if (isset($colores_prioridad[$a['prioridad']])) {
            $sheet->getStyle('I' . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colores_prioridad[$a['prioridad']]);
        }
        $fila++;
    }

    $ultima_fila = max($FILA_HEADER + 1, $fila - 1);
    if (!empty($actividades)) {
        $rango_datos = 'A' . ($FILA_HEADER + 1) . ':' . $ultima_col . $ultima_fila;
        $sheet->getStyle($rango_datos)->applyFromArray($borde_fino);
        $sheet->getStyle($rango_datos)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach (['A', 'C', 'F', 'I', 'J', 'K', 'M', 'N'] as $col) {
            $sheet->getStyle($col . ($FILA_HEADER + 1) . ':' . $col . $ultima_fila)
                  ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        foreach (['C', 'K', 'M'] as $col) {
            $sheet->getStyle($col . ($FILA_HEADER + 1) . ':' . $col . $ultima_fila)
                  ->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
        }
        $sheet->setAutoFilter('A' . $FILA_HEADER . ':' . $ultima_col . $ultima_fila);
    }

    // ---- Vista e impresión ----
    $sheet->freezePane('C' . ($FILA_HEADER + 1));
    $sheet->setShowGridlines(false);
    $sheet->getPageSetup()
          ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
          ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
          ->setFitToWidth(1)
          ->setFitToHeight(0)
          ->setRowsToRepeatAtTopByStartAndEnd($FILA_HEADER, $FILA_HEADER);
    $sheet->getPageMargins()->setTop(0.4)->setBottom(0.5)->setLeft(0.3)->setRight(0.3);
    $sheet->getHeaderFooter()->setOddFooter('&L' . 'Bitácora Semanal · Semana ' . $num_semana . '&RPágina &P de &N');
    $sheet->setSelectedCell('A1');

    // =================================================================
    // HOJA 2: RESUMEN
    // =================================================================
    $res = $spreadsheet->createSheet();
    $res->setTitle('Resumen');
    $res->getColumnDimension('A')->setWidth(34);
    $res->getColumnDimension('B')->setWidth(12);
    $res->getColumnDimension('D')->setWidth(34);
    $res->getColumnDimension('E')->setWidth(12);

    $res->mergeCells('A1:E1');
    $res->setCellValue('A1', 'RESUMEN · ' . $texto_semana);
    $res->getStyle('A1')->getFont()->setBold(true)->setSize(12);
    $res->mergeCells('A2:E2');
    $res->setCellValue('A2', 'Contenido: ' . $estados_filtro[$filtro_estado] . ' · Total de actividades: ' . count($actividades));
    $res->getStyle('A2')->getFont()->setItalic(true);

    $agrupar = function (callable $clave) use ($actividades) {
        $conteo = [];
        foreach ($actividades as $a) {
            $k = $clave($a);
            $conteo[$k] = ($conteo[$k] ?? 0) + 1;
        }
        arsort($conteo);
        return $conteo;
    };
    $tablas = [
        ['A', 4, 'Por estado',       $agrupar(fn($a) => $nombres_estado[$a['estado']] ?? $a['estado'])],
        ['D', 4, 'Por prioridad',    $agrupar(fn($a) => $prioridades[$a['prioridad']] ?? $a['prioridad'])],
        ['A', 0, 'Por departamento', $agrupar(fn($a) => $a['depto_nombre'] ?: 'Sin departamento')],
        ['D', 0, 'Por técnico',      $agrupar(fn($a) => $a['tecnico_nombre'] ?: 'Sin asignar')],
    ];

    $fila_siguiente = 4;
    foreach (array_chunk($tablas, 2) as $par) {
        $max_filas = 0;
        foreach ($par as [$col, , $titulo, $datos]) {
            $col2 = $col === 'A' ? 'B' : 'E';
            $f = $fila_siguiente;
            $res->setCellValue($col . $f, $titulo);
            $res->setCellValue($col2 . $f, 'Cantidad');
            $res->getStyle($col . $f . ':' . $col2 . $f)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $verde_header]],
            ] + $borde_fino);
            $f++;
            if (empty($datos)) {
                $res->setCellValue($col . $f, '—');
                $res->setCellValue($col2 . $f, 0);
                $f++;
            }
            foreach ($datos as $nombre => $cantidad) {
                $res->setCellValue($col . $f, (string) $nombre);
                $res->setCellValue($col2 . $f, $cantidad);
                $f++;
            }
            $res->setCellValue($col . $f, 'Total');
            $res->setCellValue($col2 . $f, '=SUM(' . $col2 . ($fila_siguiente + 1) . ':' . $col2 . ($f - 1) . ')');
            $res->getStyle($col . $f . ':' . $col2 . $f)->getFont()->setBold(true);
            $res->getStyle($col . ($fila_siguiente + 1) . ':' . $col2 . $f)->applyFromArray($borde_fino);
            $res->getStyle($col2 . $fila_siguiente . ':' . $col2 . $f)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $max_filas = max($max_filas, $f - $fila_siguiente + 1);
        }
        $fila_siguiente += $max_filas + 1;
    }
    $res->setSelectedCell('A1');

    $spreadsheet->setActiveSheetIndex(0);
    $spreadsheet->getProperties()
        ->setCreator('VerdenCore - Sistemas')
        ->setLastModifiedBy($_SESSION['nombre_completo'] ?? 'Sistemas')
        ->setTitle('Bitácora Semanal de Actividades - Semana ' . $num_semana)
        ->setSubject($texto_semana)
        ->setDescription('Total de actividades: ' . count($actividades));

    // =================================================================
    // DESCARGA
    // =================================================================
    $sufijo = [
        'activas' => 'Abiertas', 'pendiente' => 'Pendientes', 'en_proceso' => 'En_proceso',
        'finalizadas' => 'Finalizadas', 'todas' => 'Todas',
    ][$filtro_estado];
    $nombre_archivo = 'Bitacora_Semanal_' . $lunes->format('Y') . '-S' . str_pad((string) $num_semana, 2, '0', STR_PAD_LEFT)
                    . '_' . $sufijo . '.xlsx';

    $archivo_temporal = tempnam(sys_get_temp_dir(), 'bsem_');
    (new Xlsx($spreadsheet))->save($archivo_temporal);

    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Content-Length: ' . filesize($archivo_temporal));
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    readfile($archivo_temporal);
    @unlink($archivo_temporal);
    exit;

} catch (Exception $e) {
    error_log('Error generando Bitácora Semanal: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    establecer_alerta('error', 'Error al generar la bitácora: ' . $e->getMessage());
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}