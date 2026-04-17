<?php
/**
 * API: Obtener puestos por departamento
 * api/puestos_departamento.php
 * 
 * GET ?departamento_id=8 -> puestos del departamento
 * GET ?todos=1 -> todos los puestos agrupados por departamento (para JS)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$pdo = conectarDB();

// Modo 1: Todos agrupados (para cargar una vez en JS)
if (isset($_GET['todos'])) {
    $stmt = $pdo->query("
        SELECT pd.departamento_id, pd.nombre_puesto, pd.es_admin_area
        FROM puestos_departamento pd
        WHERE pd.activo = 1
        ORDER BY pd.departamento_id, pd.es_admin_area DESC, pd.nombre_puesto
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $agrupados = [];
    foreach ($rows as $r) {
        $did = $r['departamento_id'];
        if (!isset($agrupados[$did])) $agrupados[$did] = [];
        $agrupados[$did][] = [
            'nombre' => $r['nombre_puesto'],
            'es_admin' => (bool)$r['es_admin_area']
        ];
    }
    
    echo json_encode($agrupados);
    exit;
}

// Modo 2: Por departamento_id
$departamento_id = intval($_GET['departamento_id'] ?? 0);
if ($departamento_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT nombre_puesto, es_admin_area
    FROM puestos_departamento
    WHERE departamento_id = ? AND activo = 1
    ORDER BY es_admin_area DESC, nombre_puesto
");
$stmt->execute([$departamento_id]);
$puestos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($puestos);
