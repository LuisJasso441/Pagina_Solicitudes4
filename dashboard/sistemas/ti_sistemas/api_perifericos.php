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

    // Vincular: asigna personal_asignado = código interno del equipo principal
    // (acepta hostname_padre por compatibilidad con páginas que aún no se actualicen)
    case 'vincular':
        $periferico_id = (int)($input['periferico_id'] ?? 0);
        $padre_id      = (int)($input['padre_id'] ?? 0);
        $codigo_padre  = strtoupper(trim($input['codigo_padre'] ?? $input['hostname_padre'] ?? ''));

        if (!$periferico_id || !$codigo_padre) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']); exit;
        }
        if ($padre_id && $padre_id === $periferico_id) {
            echo json_encode(['success' => false, 'message' => 'Un equipo no puede vincularse a sí mismo.']); exit;
        }

        // Buscar el equipo principal para heredar sus datos (por ID; si no llega, por código o hostname)
        if ($padre_id) {
            $stmt = $pdo->prepare("SELECT id, codigo_interno, hostname, ubicacion, departamento_id, usuario_asignado_id, usuario_asignado_2_id FROM inventario_equipos WHERE id = ?");
            $stmt->execute([$padre_id]);
        } else {
            $stmt = $pdo->prepare("SELECT id, codigo_interno, hostname, ubicacion, departamento_id, usuario_asignado_id, usuario_asignado_2_id FROM inventario_equipos WHERE codigo_interno = ? OR hostname = ? LIMIT 1");
            $stmt->execute([$codigo_padre, $codigo_padre]);
        }
        $padre = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$padre) {
            echo json_encode(['success' => false, 'message' => 'No se encontró el equipo principal.']); exit;
        }
        if ((int)$padre['id'] === $periferico_id) {
            echo json_encode(['success' => false, 'message' => 'Un equipo no puede vincularse a sí mismo.']); exit;
        }

        // Siempre se guarda el vínculo con el código interno real del equipo principal
        $campos  = ['personal_asignado = ?'];
        $valores = [$padre['codigo_interno'] ?: $codigo_padre];

        // Heredar ubicación, departamento y usuarios del equipo principal.
        // Solo se copian los datos que el principal sí tiene, para no borrar nada del periférico.
        if (!empty($padre['ubicacion'])) {
            $campos[] = 'ubicacion = ?';
            $valores[] = $padre['ubicacion'];
        }
        if (!empty($padre['departamento_id'])) {
            $campos[] = 'departamento_id = ?';
            $valores[] = $padre['departamento_id'];
        }
        if (!empty($padre['usuario_asignado_id'])) {
            // Los dos usuarios van juntos: el periférico queda igual que su equipo principal
            $campos[] = 'usuario_asignado_id = ?';
            $valores[] = $padre['usuario_asignado_id'];
            $campos[] = 'usuario_asignado_2_id = ?';
            $valores[] = $padre['usuario_asignado_2_id'] ?: null;
        }

        $valores[] = $periferico_id;
        $stmt = $pdo->prepare("UPDATE inventario_equipos SET " . implode(', ', $campos) . " WHERE id = ?");
        $stmt->execute($valores);
        echo json_encode(['success' => true, 'message' => 'Periférico vinculado; se copiaron ubicación, departamento y usuario del equipo principal.']);
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
    // Un periférico está disponible si no tiene equipo principal, o si el que tiene
    // ya no existe (ni como código interno ni como hostname, por los vínculos antiguos).
    case 'disponibles':
        $padre_id = (int)($input['padre_id'] ?? 0);
        $busqueda = trim($input['busqueda'] ?? '');

        $sql = "SELECT id, hostname, codigo_interno, tipo_equipo, marca, modelo, personal_asignado 
                FROM inventario_equipos 
                WHERE (personal_asignado IS NULL OR personal_asignado = '' 
                       OR (personal_asignado NOT IN (SELECT codigo_interno FROM inventario_equipos WHERE codigo_interno IS NOT NULL AND codigo_interno != '')
                           AND personal_asignado NOT IN (SELECT hostname FROM inventario_equipos WHERE hostname IS NOT NULL AND hostname != '')))
                  AND id != ?
                  AND tipo_equipo IN ('monitor','mouse','teclado','impresora','camara','celular','telefono')";
        $params = [$padre_id];

        if ($busqueda) {
            $sql .= " AND (codigo_interno LIKE ? OR hostname LIKE ? OR marca LIKE ? OR modelo LIKE ? OR numero_serie LIKE ?)";
            $b = "%{$busqueda}%";
            $params = array_merge($params, [$b, $b, $b, $b, $b]);
        }

        $sql .= " ORDER BY tipo_equipo, codigo_interno LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $disponibles]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
}
?>