<?php
/**
 * API para vincular/desvincular periféricos
 * dashboard/sistemas/ti_sistemas/api_perifericos.php
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']); exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    echo json_encode(['success' => false, 'message' => 'Sin permisos.']); exit;
}

$pdo = conectarDB();
$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? '';

switch ($accion) {

    // Vincular: asigna personal_asignado = hostname_padre
    case 'vincular':
        $periferico_id = (int)($input['periferico_id'] ?? 0);
        $hostname_padre = strtoupper(trim($input['hostname_padre'] ?? ''));

        if (!$periferico_id || !$hostname_padre) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']); exit;
        }

        $stmt = $pdo->prepare("UPDATE inventario_equipos SET personal_asignado = ? WHERE id = ?");
        $stmt->execute([$hostname_padre, $periferico_id]);
        echo json_encode(['success' => true, 'message' => 'Periférico vinculado correctamente.']);
        break;

    // Desvincular: quita personal_asignado
    case 'desvincular':
        $periferico_id = (int)($input['periferico_id'] ?? 0);

        if (!$periferico_id) {
            echo json_encode(['success' => false, 'message' => 'ID no válido.']); exit;
        }

        $stmt = $pdo->prepare("UPDATE inventario_equipos SET personal_asignado = NULL WHERE id = ?");
        $stmt->execute([$periferico_id]);
        echo json_encode(['success' => true, 'message' => 'Periférico desvinculado.']);
        break;

    // Listar periféricos disponibles (sin vincular o vinculados a otro padre)
    case 'disponibles':
        $hostname_padre = strtoupper(trim($input['hostname_padre'] ?? ''));
        $busqueda = trim($input['busqueda'] ?? '');

        $sql = "SELECT id, hostname, tipo_equipo, marca, modelo, personal_asignado 
                FROM inventario_equipos 
                WHERE (personal_asignado IS NULL OR personal_asignado = '' 
                       OR personal_asignado NOT IN (SELECT DISTINCT hostname FROM inventario_equipos WHERE hostname IS NOT NULL))
                  AND hostname != ?
                  AND tipo_equipo IN ('monitor','mouse','teclado','impresora','camara','celular','telefono')";
        $params = [$hostname_padre];

        if ($busqueda) {
            $sql .= " AND (hostname LIKE ? OR marca LIKE ? OR modelo LIKE ?)";
            $b = "%{$busqueda}%";
            $params = array_merge($params, [$b, $b, $b]);
        }

        $sql .= " ORDER BY tipo_equipo, hostname LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $disponibles]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
}
?>