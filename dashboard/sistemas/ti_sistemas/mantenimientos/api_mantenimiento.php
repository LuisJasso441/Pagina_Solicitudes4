<?php
/**
 * API Mantenimiento - Endpoints AJAX para el flujo Equipo → Departamento / Usuario
 * dashboard/sistemas/ti_sistemas/mantenimientos/api_mantenimiento.php
 *
 * Acciones:
 *   GET  ?accion=usuarios&departamento_id=N
 *   GET  ?accion=equipos&grupo=computo[&departamento_id=N][&usuario_id=N]
 *        (departamento_id es opcional: sin él se listan los equipos de todos los departamentos)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';

// ------- Verificar sesión -------
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

// ------- Restringir a Sistemas -------
$departamento_actual = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_sistemas = in_array($departamento_actual, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_sistemas) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos.']);
    exit;
}

// ------- Mapeo de grupos de inventario (idéntico a inventario.php) -------
$grupos_tipos = [
    'computo'     => ['all_in_one', 'pc', 'laptop', 'computadora'],
    'perifericos' => ['monitor', 'mouse', 'teclado'],
    'impresoras'  => ['impresora'],
    'red'         => ['access_point', 'switch'],
    'otros'       => ['camara', 'celular', 'telefono', 'pantalla_tv', 'nobreak'],
];

$pdo = conectarDB();
$accion = $_GET['accion'] ?? '';

try {
    switch ($accion) {

        // ============================================================
        // USUARIOS POR DEPARTAMENTO
        // ============================================================
        case 'usuarios': {
            $departamento_id = intval($_GET['departamento_id'] ?? 0);
            if (!$departamento_id) {
                echo json_encode(['success' => false, 'message' => 'Departamento no especificado.']);
                exit;
            }

            $sql = "SELECT u.id, u.nombre_completo, u.usuario
                    FROM usuarios u
                    WHERE u.departamento_id = ?
                      AND u.activo = 1
                    ORDER BY u.nombre_completo";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$departamento_id]);
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $usuarios]);
            exit;
        }

        // ============================================================
        // EQUIPOS POR DEPARTAMENTO + GRUPO (+ USUARIO opcional)
        // ============================================================
        case 'equipos': {
            $departamento_id = intval($_GET['departamento_id'] ?? 0);
            $grupo           = $_GET['grupo'] ?? '';
            $usuario_id      = intval($_GET['usuario_id'] ?? 0);

            if (!isset($grupos_tipos[$grupo])) {
                echo json_encode(['success' => false, 'message' => 'Grupo de equipo no válido.']);
                exit;
            }

            $tipos_grupo  = $grupos_tipos[$grupo];
            $placeholders = implode(',', array_fill(0, count($tipos_grupo), '?'));

            // Equipos activos del grupo seleccionado (opcionalmente de un departamento)
            // Se devuelve el departamento y los DOS usuarios que puede tener asignado un equipo,
            // para que el formulario los llene automáticamente al elegir el equipo.
            $filtro_depto = $departamento_id ? " AND ie.departamento_id = ?" : "";

            $sql = "SELECT ie.id, ie.hostname, ie.codigo_interno, ie.tipo_equipo,
                           ie.marca, ie.modelo, ie.ubicacion,
                           ie.departamento_id,
                           d.nombre            AS departamento_nombre,
                           ie.usuario_asignado_id, ie.usuario_asignado_2_id,
                           ua.nombre_completo  AS usuario_asignado_nombre,
                           ua.activo           AS usuario_asignado_activo,
                           ua2.nombre_completo AS usuario_asignado_2_nombre,
                           ua2.activo          AS usuario_asignado_2_activo
                    FROM inventario_equipos ie
                    LEFT JOIN departamentos d ON ie.departamento_id       = d.id
                    LEFT JOIN usuarios ua     ON ie.usuario_asignado_id   = ua.id
                    LEFT JOIN usuarios ua2    ON ie.usuario_asignado_2_id = ua2.id
                    WHERE ie.estado = 'activo'
                      AND ie.tipo_equipo IN ({$placeholders})
                      {$filtro_depto}
                    ORDER BY
                        CASE WHEN ie.usuario_asignado_id = ? OR ie.usuario_asignado_2_id = ? THEN 0 ELSE 1 END,
                        ie.codigo_interno,
                        ie.hostname";

            // El orden de los parámetros sigue al de los "?" en la consulta.
            // El usuario_id se pasa dos veces: uno por cada columna del ORDER BY.
            $params = $tipos_grupo;
            if ($departamento_id) {
                $params[] = $departamento_id;
            }
            $params[] = $usuario_id;
            $params[] = $usuario_id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Marca cada equipo si está asignado al usuario seleccionado (para resaltar en el front)
            // Se considera asignado si coincide con CUALQUIERA de los dos usuarios del equipo
            foreach ($equipos as &$eq) {
                $eq['asignado_al_usuario'] = (
                    $usuario_id > 0 && (
                        $eq['usuario_asignado_id']   == $usuario_id ||
                        $eq['usuario_asignado_2_id'] == $usuario_id
                    )
                ) ? 1 : 0;
            }
            unset($eq);

            echo json_encode(['success' => true, 'data' => $equipos]);
            exit;
        }

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
            exit;
    }
} catch (Exception $e) {
    error_log("api_mantenimiento.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
    exit;
}