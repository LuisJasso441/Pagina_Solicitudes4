<?php
/**
 * Funciones para el registro de préstamos del Inventario de Sistemas
 * Ubicación sugerida: includes/sistemas/inventario/prestamos_sistemas_funciones.php
 *
 * Requiere que conectarDB() ya esté disponible (config/database.php).
 *
 * Modelo: al prestar se descuenta `cantidad_disponible` del artículo;
 * al devolver se vuelve a sumar. La tarjeta "en préstamo" de la vista
 * (cantidad_total - cantidad_disponible) refleja automáticamente los
 * préstamos vigentes.
 */

// ⚠️ AJUSTA este nombre si tu tabla de inventario se llama distinto.
if (!defined('TABLA_INV_SIS')) {
    define('TABLA_INV_SIS', 'inventario_sistemas');
}

/**
 * Registra un nuevo préstamo y descuenta el stock disponible.
 *
 * @param int    $articulo_id
 * @param string $persona        A quién se presta
 * @param string $departamento   Área (opcional)
 * @param int    $cantidad       Unidades a prestar
 * @param string $observaciones  Opcional
 * @param int    $usuario_id     Usuario de Sistemas que registra
 * @param PDO    $pdo            Conexión opcional (para reutilizar en transacción padre)
 * @return array ['success'=>bool, 'message'=>string, 'id'=>int?]
 */
function registrar_prestamo_sistemas($articulo_id, $persona, $departamento, $cantidad, $observaciones, $usuario_id, $pdo = null) {
    $articulo_id  = (int) $articulo_id;
    $cantidad     = (int) $cantidad;
    $persona      = trim((string) $persona);
    $departamento = trim((string) $departamento);
    $observaciones = trim((string) $observaciones);

    if ($articulo_id <= 0) return ['success' => false, 'message' => 'Artículo inválido.'];
    if ($persona === '')   return ['success' => false, 'message' => 'Indica a quién se presta el artículo.'];
    if ($cantidad <= 0)    return ['success' => false, 'message' => 'La cantidad debe ser mayor a 0.'];

    if ($pdo === null) {
        $pdo = conectarDB();
    }

    try {
        $pdo->beginTransaction();

        // Bloquear la fila del artículo y leer nombre + disponible (solo artículos activos)
        $stmt = $pdo->prepare("SELECT nombre, cantidad_disponible FROM " . TABLA_INV_SIS . " WHERE id = ? AND activo = 1 FOR UPDATE");
        $stmt->execute([$articulo_id]);
        $art = $stmt->fetch();

        if (!$art) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'El artículo no existe o está inactivo.'];
        }

        // Descontar disponible sólo si alcanza (patrón anti-race con rowCount)
        $upd = $pdo->prepare("
            UPDATE " . TABLA_INV_SIS . "
            SET cantidad_disponible = cantidad_disponible - ?
            WHERE id = ? AND activo = 1 AND cantidad_disponible >= ?
        ");
        $upd->execute([$cantidad, $articulo_id, $cantidad]);

        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'No hay suficientes unidades disponibles (' . (int) $art['cantidad_disponible'] . ' disp.).'];
        }

        // Insertar el préstamo
        $ins = $pdo->prepare("
            INSERT INTO prestamos_inventario_sistemas
              (articulo_id, nombre_articulo, persona, departamento, cantidad, cantidad_devuelta, estado, observaciones, usuario_prestamo, fecha_prestamo)
            VALUES (?, ?, ?, ?, ?, 0, 'prestado', ?, ?, NOW())
        ");
        $ins->execute([
            $articulo_id,
            $art['nombre'],
            $persona,
            $departamento,
            $cantidad,
            ($observaciones !== '' ? $observaciones : null),
            ($usuario_id ?: null)
        ]);
        $nuevo_id = (int) $pdo->lastInsertId();

        $pdo->commit();
        return ['success' => true, 'message' => 'Préstamo registrado correctamente.', 'id' => $nuevo_id];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Error al registrar préstamo: ' . $e->getMessage()];
    }
}

/**
 * Registra una devolución (total o parcial) y regresa el stock disponible.
 *
 * @param int $prestamo_id
 * @param int $cantidad_devolver
 * @param int $usuario_id
 * @param PDO $pdo
 * @return array ['success'=>bool, 'message'=>string]
 */
function devolver_prestamo_sistemas($prestamo_id, $cantidad_devolver, $usuario_id, $pdo = null) {
    $prestamo_id       = (int) $prestamo_id;
    $cantidad_devolver = (int) $cantidad_devolver;

    if ($prestamo_id <= 0)       return ['success' => false, 'message' => 'Préstamo inválido.'];
    if ($cantidad_devolver <= 0) return ['success' => false, 'message' => 'La cantidad a devolver debe ser mayor a 0.'];

    if ($pdo === null) {
        $pdo = conectarDB();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM prestamos_inventario_sistemas WHERE id = ? FOR UPDATE");
        $stmt->execute([$prestamo_id]);
        $p = $stmt->fetch();

        if (!$p) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Préstamo no encontrado.'];
        }

        $pendiente = (int) $p['cantidad'] - (int) $p['cantidad_devuelta'];
        if ($pendiente <= 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Este préstamo ya fue devuelto por completo.'];
        }
        if ($cantidad_devolver > $pendiente) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Solo quedan ' . $pendiente . ' unidad(es) por devolver.'];
        }

        $nueva_devuelta = (int) $p['cantidad_devuelta'] + $cantidad_devolver;
        $nuevo_estado   = ($nueva_devuelta >= (int) $p['cantidad']) ? 'devuelto' : 'parcial';
        $fecha_dev      = ($nuevo_estado === 'devuelto') ? date('Y-m-d H:i:s') : null;

        $upd = $pdo->prepare("
            UPDATE prestamos_inventario_sistemas
            SET cantidad_devuelta = ?, estado = ?, usuario_devolucion = ?, fecha_devolucion = ?
            WHERE id = ?
        ");
        $upd->execute([$nueva_devuelta, $nuevo_estado, ($usuario_id ?: null), $fecha_dev, $prestamo_id]);

        // Regresar el stock disponible del artículo
        $stock = $pdo->prepare("UPDATE " . TABLA_INV_SIS . " SET cantidad_disponible = cantidad_disponible + ? WHERE id = ?");
        $stock->execute([$cantidad_devolver, (int) $p['articulo_id']]);

        $pdo->commit();
        return ['success' => true, 'message' => 'Devolución registrada correctamente.'];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Error al registrar la devolución: ' . $e->getMessage()];
    }
}

/**
 * Lista los préstamos con filtros opcionales.
 *
 * @param array $filtros ['estado'=>, 'articulo_id'=>, 'busqueda'=>]
 * @return array
 */
function obtener_prestamos_sistemas($filtros = []) {
    $pdo = conectarDB();

    $sql = "SELECT p.*,
                   (p.cantidad - p.cantidad_devuelta) AS pendiente,
                   up.nombre_completo AS usuario_prestamo_nombre
            FROM prestamos_inventario_sistemas p
            LEFT JOIN usuarios up ON p.usuario_prestamo = up.id
            WHERE 1=1";
    $params = [];

    if (!empty($filtros['estado'])) {
        $sql .= " AND p.estado = ?";
        $params[] = $filtros['estado'];
    }
    if (!empty($filtros['articulo_id'])) {
        $sql .= " AND p.articulo_id = ?";
        $params[] = (int) $filtros['articulo_id'];
    }
    if (!empty($filtros['busqueda'])) {
        $sql .= " AND (p.persona LIKE ? OR p.nombre_articulo LIKE ? OR p.departamento LIKE ?)";
        $like = '%' . $filtros['busqueda'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY p.fecha_prestamo DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Estadísticas rápidas de préstamos.
 * @return array ['total_prestamos'=>, 'activos'=>, 'unidades_prestadas'=>]
 */
function obtener_estadisticas_prestamos_sistemas() {
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total_prestamos,
            COALESCE(SUM(CASE WHEN estado IN ('prestado','parcial') THEN 1 ELSE 0 END), 0) AS activos,
            COALESCE(SUM(CASE WHEN estado IN ('prestado','parcial') THEN (cantidad - cantidad_devuelta) ELSE 0 END), 0) AS unidades_prestadas
        FROM prestamos_inventario_sistemas
    ");
    return $stmt->fetch();
}