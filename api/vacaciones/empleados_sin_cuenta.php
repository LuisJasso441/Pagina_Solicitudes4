<?php
/**
 * API: Obtener empleados sin cuenta de usuario
 * api/vacaciones/empleados_sin_cuenta.php
 * 
 * GET -> Lista de empleados activos sin cuenta (usuario_id IS NULL)
 * GET ?id=X -> Datos completos de un empleado específico
 * 
 * Usado por: nueva_solicitud_manual.php (dropdown dinámico)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/vacaciones/vacaciones_funciones.php';

// Verificar sesión y permisos
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

if (empty($_SESSION['es_admin_area'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos de Admin de Área']);
    exit;
}

try {
    $pdo = conectarDB();
    $empleado_id = intval($_GET['id'] ?? 0);

    // =====================================================
    // Si se pide un empleado específico: devolver datos completos
    // =====================================================
    if ($empleado_id > 0) {
        $stmt = $pdo->prepare("
            SELECT e.id, e.nombre_completo, e.no_nomina, e.puesto,
                   e.departamento_id, e.fecha_ingreso, e.periodo_pago,
                   e.empresa, e.jornada,
                   d.nombre AS departamento_nombre
            FROM empleados_gth e
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            WHERE e.id = ? AND e.activo = 1 AND e.usuario_id IS NULL
        ");
        $stmt->execute([$empleado_id]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$emp) {
            echo json_encode(['error' => 'Empleado no encontrado o ya tiene cuenta']);
            exit;
        }
        
        // Calcular antigüedad y días LFT si tiene fecha_ingreso
        $antiguedad_texto = '--';
        $dias_lft = 0;
        $dias_tomados = 0;
        
        if (!empty($emp['fecha_ingreso'])) {
            $ant = calcular_antiguedad($emp['fecha_ingreso']);
            $anio_p = max(1, $ant['anios_completos'] > 0 ? $ant['anios_completos'] + 1 : 1);
            $dias_lft = dias_vacaciones_lft($anio_p);
            $antiguedad_texto = $ant['anios'] . ' año(s), ' . $ant['meses'] . ' mes(es)';
            
            // Días ya tomados (solicitudes manuales aprobadas/completadas)
            $stmt_dias = $pdo->prepare("
                SELECT COALESCE(SUM(dias_solicitados), 0) 
                FROM solicitudes_vacaciones 
                WHERE es_manual = 1 
                  AND nombre_manual = ? 
                  AND estado IN ('pendiente_gth', 'aprobada_gth', 'completada')
            ");
            $stmt_dias->execute([$emp['nombre_completo']]);
            $dias_tomados = (int)$stmt_dias->fetchColumn();
        }
        
        $emp['antiguedad_texto'] = $antiguedad_texto;
        $emp['dias_lft'] = $dias_lft;
        $emp['dias_tomados'] = $dias_tomados;
        
        echo json_encode(['success' => true, 'empleado' => $emp]);
        exit;
    }

    // =====================================================
    // Sin ID: devolver lista completa de empleados sin cuenta
    // =====================================================
    $stmt = $pdo->prepare("
        SELECT e.id, e.nombre_completo, e.no_nomina, e.puesto,
               e.departamento_id, d.nombre AS departamento_nombre
        FROM empleados_gth e
        LEFT JOIN departamentos d ON e.departamento_id = d.id
        WHERE e.activo = 1 AND e.usuario_id IS NULL
        ORDER BY e.nombre_completo ASC
    ");
    $stmt->execute();
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'empleados' => $empleados]);

} catch (Exception $e) {
    error_log("API empleados_sin_cuenta error: " . $e->getMessage());
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}