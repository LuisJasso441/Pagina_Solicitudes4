<?php
/**
 * Descargar Base de Datos de Órdenes de Servicio en Excel
 * EXCLUSIVO para el departamento de Mantenimiento
 * 
 * Genera un archivo Excel con todas las órdenes de servicio
 * incluyendo cálculos de días y horas de mantenimiento
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
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio_mantenimiento.php');
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
            $diaSemana = (int)$current->format('w'); // 0=domingo, 1=lunes, ..., 6=sábado
            // Contar solo lunes a sábado (1-6)
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
        
        // Horarios laborales
        $horaInicioLaboral = '08:30';
        $horaFinLaboralLV = '17:00'; // Lunes a Viernes
        $horaFinLaboralS = '14:00';  // Sábado
        
        while ($current->format('Y-m-d') <= $fin->format('Y-m-d')) {
            $diaSemana = (int)$current->format('w');
            $fechaActual = $current->format('Y-m-d');
            
            // Saltar domingos
            if ($diaSemana === 0) {
                $current->modify('+1 day');
                $current->setTime(8, 30, 0);
                continue;
            }
            
            // Determinar hora fin laboral según el día
            $horaFinLaboral = ($diaSemana === 6) ? $horaFinLaboralS : $horaFinLaboralLV;
            
            // Crear objetos DateTime para inicio y fin del día laboral
            $inicioLaboral = new DateTime($fechaActual . ' ' . $horaInicioLaboral);
            $finLaboral = new DateTime($fechaActual . ' ' . $horaFinLaboral);
            
            // Determinar hora de inicio efectiva para este día
            if ($fechaActual === $inicio->format('Y-m-d')) {
                $horaEfectivaInicio = max($current, $inicioLaboral);
            } else {
                $horaEfectivaInicio = $inicioLaboral;
            }
            
            // Determinar hora de fin efectiva para este día
            if ($fechaActual === $fin->format('Y-m-d')) {
                $horaEfectivaFin = min($fin, $finLaboral);
            } else {
                $horaEfectivaFin = $finLaboral;
            }
            
            // Asegurar que estemos dentro del horario laboral
            if ($horaEfectivaInicio < $inicioLaboral) {
                $horaEfectivaInicio = $inicioLaboral;
            }
            if ($horaEfectivaFin > $finLaboral) {
                $horaEfectivaFin = $finLaboral;
            }
            
            // Calcular horas de este día
            if ($horaEfectivaInicio < $horaEfectivaFin) {
                $diff = $horaEfectivaInicio->diff($horaEfectivaFin);
                $horasDelDia = $diff->h + ($diff->i / 60);
                $horasTotales += $horasDelDia;
            }
            
            // Avanzar al siguiente día
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

try {
    $pdo = conectarDB();
    
    // Obtener TODAS las órdenes de servicio
    $sql = "SELECT 
                id, folio, empresa, departamento, estado,
                apartado1_data, apartado2_data, apartado3_data,
                fecha_creacion, fecha_enviado_usuario, fecha_completado
            FROM ordenes_servicio_mantenimiento
            ORDER BY id ASC";
    
    $stmt = $pdo->query($sql);
    $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Crear el spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Órdenes de Servicio');
    
    // Definir encabezados (21 columnas)
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
        
        // Extraer fecha y hora de entrega (cuando Mantenimiento envió al usuario)
        $fechaEntrega = '';
        $horaEntrega = '';
        if (!empty($orden['fecha_enviado_usuario'])) {
            $fechaEnvio = new DateTime($orden['fecha_enviado_usuario']);
            $fechaEntrega = $fechaEnvio->format('Y-m-d');
            $horaEntrega = $fechaEnvio->format('H:i:s');
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
        $sheet->setCellValue('W' . $row, traducirEstado($orden['estado']));
        
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
        'A' => 8,   // No. OS
        'B' => 12,  // Fecha de Entrada
        'C' => 12,  // Hora de Entrada
        'D' => 12,  // Empresa
        'E' => 18,  // Área Solicitante
        'F' => 15,  // Folio
        'G' => 20,  // Equipo / Unidad
        'H' => 35,  // Descripción de la Falla
        'I' => 12,  // Fecha de Atención
        'J' => 12,  // Hora de Atención
        'K' => 12,  // Fecha de Término
        'L' => 12,  // Hora de Término
        'M' => 35,  // Descripción de Reparación
        'N' => 18,  // Empleado 1
        'O' => 18,  // Empleado 2
        'P' => 18,  // Empleado 3
        'Q' => 15,  // Código de Equipo
        'R' => 12,  // Horómetro
        'S' => 12,  // Fecha de Entrega
        'T' => 12,  // Hora de Entrega
        'U' => 12,  // Días de Mtto
        'V' => 12,  // Horas de Mtto
        'W' => 22   // Estatus
    ];
    
    foreach ($columnWidths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }
    
    // Aplicar autofiltro a todas las columnas
    $sheet->setAutoFilter('A1:W' . $lastRow);
    
    // Congelar primera fila
    $sheet->freezePane('A2');
    
    // Configurar headers para descarga
    $filename = 'Ordenes_Servicio_Mantenimiento_' . date('Y-m-d_His') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    // Escribir archivo
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    // Log de descarga
    error_log("Excel descargado por: {$_SESSION['nombre_completo']} - Total órdenes: " . count($ordenes));
    
    exit;
    
} catch (Exception $e) {
    error_log("Error al generar Excel de órdenes: " . $e->getMessage());
    $_SESSION['error'] = "Error al generar el archivo Excel: " . $e->getMessage();
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio_mantenimiento.php');
    exit;
}