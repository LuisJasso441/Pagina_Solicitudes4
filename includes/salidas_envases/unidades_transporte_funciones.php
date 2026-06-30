<?php
/**
 * Funciones del módulo de Unidades de Transporte
 * Parte del módulo SEC - Salidas de Envases para Clientes
 *
 * Ubicación: includes/salidas_envases/unidades_transporte_funciones.php
 *
 * Gestión exclusiva por Logística. Otros módulos (SEC, Disponibilidad)
 * consultan unidades activas para asignación.
 */

require_once __DIR__ . '/../../config/database.php';

// ====================================================================
// CONSULTAS
// ====================================================================

/**
 * Obtener todas las unidades de transporte
 *
 * @param bool $solo_activas Si true, sólo retorna las que tienen activa=1
 * @return array
 */
function obtener_unidades_transporte($solo_activas = true) {
    try {
        $pdo = conectarDB();
        $sql = "SELECT * FROM unidades_transporte";
        if ($solo_activas) {
            $sql .= " WHERE activa = 1";
        }
        $sql .= " ORDER BY activa DESC, nombre ASC";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obtener_unidades_transporte: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener una unidad por su ID
 *
 * @param int $id
 * @return array|false
 */
function obtener_unidad_transporte_por_id($id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT * FROM unidades_transporte WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obtener_unidad_transporte_por_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Contar cuántas SEC usan esta unidad (para advertir antes de desactivar)
 *
 * @param int $id
 * @return int
 */
function contar_usos_unidad_transporte($id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sec_lineas WHERE unidad_transporte_id = ?");
        $stmt->execute([(int)$id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error contar_usos_unidad_transporte: " . $e->getMessage());
        return 0;
    }
}

// ====================================================================
// VALIDACIÓN
// ====================================================================

/**
 * Validar datos de una unidad de transporte
 *
 * @param array $datos
 * @return array Lista de errores (vacía si OK)
 */
function validar_unidad_transporte($datos) {
    $errores = [];

    $nombre = trim($datos['nombre'] ?? '');
    if ($nombre === '') {
        $errores[] = 'El nombre de la unidad es obligatorio.';
    } elseif (mb_strlen($nombre) > 100) {
        $errores[] = 'El nombre no puede exceder 100 caracteres.';
    }

    $placas = trim($datos['placas'] ?? '');
    if ($placas === '') {
        $errores[] = 'Las placas son obligatorias.';
    } elseif (mb_strlen($placas) > 20) {
        $errores[] = 'Las placas no pueden exceder 20 caracteres.';
    }

    $tipos = ['tmb', 'tote', 'gfa', 'jaula'];
    foreach ($tipos as $tipo) {
        $key = "capacidad_$tipo";
        $val = $datos[$key] ?? 0;
        if (!is_numeric($val) || (int)$val < 0) {
            $errores[] = "La capacidad de " . strtoupper($tipo) . " debe ser un entero ≥ 0.";
        }
    }

    return $errores;
}

// ====================================================================
// MUTACIONES
// ====================================================================

/**
 * Crear nueva unidad de transporte
 *
 * @param array $datos
 * @param int   $usuario_id
 * @return array ['success' => bool, 'id' => int|null, 'errores' => array]
 */
function crear_unidad_transporte($datos, $usuario_id) {
    $errores = validar_unidad_transporte($datos);
    if (!empty($errores)) {
        return ['success' => false, 'id' => null, 'errores' => $errores];
    }

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            INSERT INTO unidades_transporte
                (nombre, placas,
                 capacidad_tmb, capacidad_tote, capacidad_gfa, capacidad_jaula,
                 activa, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
        ");
        $stmt->execute([
            trim($datos['nombre']),
            strtoupper(trim($datos['placas'])),
            (int)($datos['capacidad_tmb']   ?? 0),
            (int)($datos['capacidad_tote']  ?? 0),
            (int)($datos['capacidad_gfa']   ?? 0),
            (int)($datos['capacidad_jaula'] ?? 0),
            (int)$usuario_id,
            (int)$usuario_id
        ]);
        return ['success' => true, 'id' => (int)$pdo->lastInsertId(), 'errores' => []];
    } catch (Exception $e) {
        error_log("Error crear_unidad_transporte: " . $e->getMessage());
        return ['success' => false, 'id' => null, 'errores' => ['Error al guardar: ' . $e->getMessage()]];
    }
}

/**
 * Actualizar unidad de transporte existente
 *
 * @param int   $id
 * @param array $datos
 * @param int   $usuario_id
 * @return array ['success' => bool, 'errores' => array]
 */
function actualizar_unidad_transporte($id, $datos, $usuario_id) {
    $errores = validar_unidad_transporte($datos);
    if (!empty($errores)) {
        return ['success' => false, 'errores' => $errores];
    }

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            UPDATE unidades_transporte
            SET nombre = ?,
                placas = ?,
                capacidad_tmb = ?, capacidad_tote = ?, capacidad_gfa = ?, capacidad_jaula = ?,
                updated_by = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($datos['nombre']),
            strtoupper(trim($datos['placas'])),
            (int)($datos['capacidad_tmb']   ?? 0),
            (int)($datos['capacidad_tote']  ?? 0),
            (int)($datos['capacidad_gfa']   ?? 0),
            (int)($datos['capacidad_jaula'] ?? 0),
            (int)$usuario_id,
            (int)$id
        ]);
        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        error_log("Error actualizar_unidad_transporte: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al actualizar: ' . $e->getMessage()]];
    }
}

/**
 * Soft delete: marca activa=0
 * NO se elimina físicamente para preservar histórico en SECs ya emitidas.
 *
 * @param int $id
 * @param int $usuario_id
 * @return array ['success' => bool, 'errores' => array]
 */
function desactivar_unidad_transporte($id, $usuario_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("UPDATE unidades_transporte SET activa = 0, updated_by = ? WHERE id = ?");
        $stmt->execute([(int)$usuario_id, (int)$id]);
        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        error_log("Error desactivar_unidad_transporte: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al desactivar: ' . $e->getMessage()]];
    }
}

/**
 * Reactivar unidad previamente desactivada
 *
 * @param int $id
 * @param int $usuario_id
 * @return array ['success' => bool, 'errores' => array]
 */
function reactivar_unidad_transporte($id, $usuario_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("UPDATE unidades_transporte SET activa = 1, updated_by = ? WHERE id = ?");
        $stmt->execute([(int)$usuario_id, (int)$id]);
        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        error_log("Error reactivar_unidad_transporte: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al reactivar: ' . $e->getMessage()]];
    }
}