<?php
/**
 * Funciones del Módulo Inventario de Sistemas (Insumos)
 * Ubicación: includes/sistemas/inventario/inventario_sistemas_funciones.php
 * 
 * CRUD simple — sin asignaciones (las asignaciones se manejan en ti_sistemas)
 */

if (!defined('INVENTARIO_SIS_CATEGORIAS')) {
    define('INVENTARIO_SIS_CATEGORIAS', [
        'cableado'        => 'Cableado',
        'perifericos'     => 'Perif&eacute;ricos',
        'licencias'       => 'Licencias',
        'equipos'         => 'Equipos',
        'redes'           => 'Redes',
        'almacenamiento'  => 'Almacenamiento',
        'componentes'     => 'Componentes',
        'baterias_pilas'  => 'Bater&iacute;as/Pilas',
        'impresion'       => 'Impresi&oacute;n',
        'herramientas'    => 'Herramientas/Construcci&oacute;n',
        'otros'           => 'Otros'
    ]);
}

function es_usuario_sistemas() {
    $depto = strtolower($_SESSION['departamento'] ?? '');
    return (strpos($depto, 'sistemas') !== false || strpos($depto, 'ti') !== false);
}

function obtener_inventario_sistemas($filtros = []) {
    $pdo = conectarDB();
    $where = ["activo = 1"];
    $params = [];
    if (!empty($filtros['categoria'])) { $where[] = "categoria = :categoria"; $params[':categoria'] = $filtros['categoria']; }
    if (!empty($filtros['busqueda'])) { $where[] = "(nombre LIKE :busqueda OR ubicacion LIKE :busqueda2)"; $params[':busqueda'] = '%' . $filtros['busqueda'] . '%'; $params[':busqueda2'] = '%' . $filtros['busqueda'] . '%'; }
    $stmt = $pdo->prepare("SELECT * FROM inventario_sistemas WHERE " . implode(' AND ', $where) . " ORDER BY categoria ASC, nombre ASC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_articulo_sistemas($id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM inventario_sistemas WHERE id = :id AND activo = 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function verificar_duplicado_sistemas($nombre, $categoria, $excluir_id = 0) {
    $pdo = conectarDB();
    $sql = "SELECT id, nombre, categoria FROM inventario_sistemas WHERE activo = 1 AND LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre)) AND categoria = :categoria";
    $params = [':nombre' => $nombre, ':categoria' => $categoria];
    if ($excluir_id > 0) { $sql .= " AND id != :excluir_id"; $params[':excluir_id'] = $excluir_id; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
}

function crear_articulo_sistemas($datos) {
    $pdo = conectarDB();
    $duplicado = verificar_duplicado_sistemas($datos['nombre'], $datos['categoria']);
    if ($duplicado) { return ['success' => false, 'duplicado' => true, 'message' => 'Ya existe un art&iacute;culo con ese nombre en la misma categor&iacute;a (ID #' . $duplicado['id'] . ').']; }
    try {
        $stmt = $pdo->prepare("INSERT INTO inventario_sistemas (nombre, categoria, cantidad_total, cantidad_disponible, ubicacion, umbral_minimo, created_by, updated_by) VALUES (:nombre, :categoria, :cantidad_total, :cantidad_disponible, :ubicacion, :umbral_minimo, :created_by, :updated_by)");
        $stmt->execute([':nombre' => $datos['nombre'], ':categoria' => $datos['categoria'], ':cantidad_total' => (int)$datos['cantidad_total'], ':cantidad_disponible' => (int)$datos['cantidad_disponible'], ':ubicacion' => $datos['ubicacion'] ?: null, ':umbral_minimo' => (int)$datos['umbral_minimo'], ':created_by' => $_SESSION['usuario_id'], ':updated_by' => $_SESSION['usuario_id']]);
        return ['success' => true, 'message' => 'Art&iacute;culo creado correctamente.', 'id' => $pdo->lastInsertId()];
    } catch (Exception $e) { return ['success' => false, 'message' => 'Error al crear: ' . $e->getMessage()]; }
}

function actualizar_articulo_sistemas($id, $datos) {
    $pdo = conectarDB();
    $duplicado = verificar_duplicado_sistemas($datos['nombre'], $datos['categoria'], $id);
    if ($duplicado) { return ['success' => false, 'duplicado' => true, 'message' => 'Ya existe otro art&iacute;culo con ese nombre en la misma categor&iacute;a (ID #' . $duplicado['id'] . ').']; }
    try {
        $stmt = $pdo->prepare("UPDATE inventario_sistemas SET nombre = :nombre, categoria = :categoria, cantidad_total = :cantidad_total, cantidad_disponible = :cantidad_disponible, ubicacion = :ubicacion, umbral_minimo = :umbral_minimo, updated_by = :updated_by WHERE id = :id AND activo = 1");
        $stmt->execute([':nombre' => $datos['nombre'], ':categoria' => $datos['categoria'], ':cantidad_total' => (int)$datos['cantidad_total'], ':cantidad_disponible' => (int)$datos['cantidad_disponible'], ':ubicacion' => $datos['ubicacion'] ?: null, ':umbral_minimo' => (int)$datos['umbral_minimo'], ':updated_by' => $_SESSION['usuario_id'], ':id' => $id]);
        return ['success' => true, 'message' => 'Art&iacute;culo actualizado correctamente.'];
    } catch (Exception $e) { return ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]; }
}

function eliminar_articulo_sistemas($id) {
    $pdo = conectarDB();
    try {
        $pdo->prepare("UPDATE inventario_sistemas SET activo = 0, updated_by = :uid WHERE id = :id")->execute([':uid' => $_SESSION['usuario_id'], ':id' => $id]);
        return ['success' => true, 'message' => 'Art&iacute;culo eliminado correctamente.'];
    } catch (Exception $e) { return ['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]; }
}

function obtener_estadisticas_inventario_sistemas() {
    $pdo = conectarDB();
    return [
        'total_articulos'    => $pdo->query("SELECT COUNT(*) FROM inventario_sistemas WHERE activo = 1")->fetchColumn(),
        'sin_stock'          => $pdo->query("SELECT COUNT(*) FROM inventario_sistemas WHERE activo = 1 AND cantidad_disponible = 0")->fetchColumn(),
        'bajo_umbral'        => $pdo->query("SELECT COUNT(*) FROM inventario_sistemas WHERE activo = 1 AND cantidad_disponible <= umbral_minimo AND umbral_minimo > 0")->fetchColumn(),
        'categorias_activas' => $pdo->query("SELECT COUNT(DISTINCT categoria) FROM inventario_sistemas WHERE activo = 1")->fetchColumn(),
        'en_prestamo'        => $pdo->query("SELECT COALESCE(SUM(cantidad_total - cantidad_disponible), 0) FROM inventario_sistemas WHERE activo = 1 AND cantidad_total > cantidad_disponible")->fetchColumn()
    ];
}
?>