<?php
/**
 * Procesar Equipo - Crear/Editar/Eliminar
 * Solo accesible para usuarios del departamento de Sistemas
 * dashboard/sistemas/ti_sistemas/procesar_equipo.php
 * 
 * ⚠️ CORREGIDO para coincidir con estructura real de tabla inventario_equipos
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar que sea del departamento de Sistemas
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para realizar esta acción.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
    exit;
}

// Conexión a BD
$pdo = conectarDB();

// Obtener acción
$accion = $_POST['accion'] ?? '';

switch ($accion) {
    case 'crear':
        crearEquipo($pdo);
        break;
    case 'editar':
        editarEquipo($pdo);
        break;
    case 'eliminar':
        eliminarEquipo($pdo);
        break;
    default:
        establecer_alerta('error', 'Acción no válida.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
        exit;
}

/**
 * Crear nuevo equipo
 * Campos de la tabla: tipo_equipo, codigo_interno, marca, modelo, numero_serie,
 *                     ubicacion, departamento_id, usuario_asignado_id, estado,
 *                     fecha_adquisicion, especificaciones, notas, registrado_por
 */
function crearEquipo($pdo) {
    global $_SESSION;
    
    $errores = [];
    
    // Obtener y sanitizar datos
    $tipo_equipo = trim($_POST['tipo_equipo'] ?? '');
    $codigo_interno = strtoupper(trim($_POST['codigo_interno'] ?? ''));
    $numero_serie = trim($_POST['numero_serie'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $departamento_id = !empty($_POST['departamento_id']) ? intval($_POST['departamento_id']) : null;
    $usuario_asignado_id = !empty($_POST['usuario_asignado_id']) ? intval($_POST['usuario_asignado_id']) : null;
    $correo_asignado = trim($_POST['correo_asignado'] ?? '');
    $fecha_adquisicion = !empty($_POST['fecha_adquisicion']) ? $_POST['fecha_adquisicion'] : null;
    $estado = $_POST['estado'] ?? 'activo';
    $notas = trim($_POST['notas'] ?? '');
    $agregar_otro = isset($_POST['agregar_otro']);
    
    // Validaciones
    $tipos_validos = ['computadora', 'impresora', 'camara', 'telefono'];
    if (empty($tipo_equipo) || !in_array($tipo_equipo, $tipos_validos)) {
        $errores[] = 'Debe seleccionar un tipo de equipo válido.';
    }
    
    if (empty($codigo_interno)) {
        $errores[] = 'El código interno es obligatorio.';
    } elseif (!preg_match('/^[A-Z0-9\-]+$/', $codigo_interno)) {
        $errores[] = 'El código interno solo puede contener letras, números y guiones.';
    } else {
        // Verificar que el código no exista
        $stmt = $pdo->prepare("SELECT id FROM inventario_equipos WHERE codigo_interno = ?");
        $stmt->execute([$codigo_interno]);
        if ($stmt->fetch()) {
            $errores[] = 'El código interno ya existe. Por favor, use otro.';
        }
    }
    
    // Ubicación es obligatoria (NOT NULL en la BD)
    if (empty($ubicacion)) {
        $errores[] = 'La ubicación es obligatoria.';
    }
    
    // Estados válidos según el enum de la BD
    $estados_validos = ['activo', 'inactivo', 'en_reparacion', 'dado_de_baja'];
    if (!in_array($estado, $estados_validos)) {
        $errores[] = 'El estado seleccionado no es válido.';
    }
    
    // Si hay errores, regresar al formulario
    if (!empty($errores)) {
        $_SESSION['form_errors'] = $errores;
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/crear_equipo.php');
        exit;
    }
    
    try {
        $sql = "INSERT INTO inventario_equipos 
                (tipo_equipo, codigo_interno, numero_serie, marca, modelo, 
                 ubicacion, departamento_id, usuario_asignado_id, correo_asignado, estado,
                 fecha_adquisicion, notas, registrado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $tipo_equipo,
            $codigo_interno,
            $numero_serie ?: null,
            $marca ?: null,
            $modelo ?: null,
            $ubicacion,
            $departamento_id,
            $usuario_asignado_id,
            $correo_asignado ?: null,
            $estado,
            $fecha_adquisicion,
            $notas ?: null,
            $_SESSION['usuario_id']
        ]);
        
        $equipo_id = $pdo->lastInsertId();
        
        // Crear notificación para usuarios de Sistemas
        crearNotificacionEquipo($pdo, $equipo_id, $codigo_interno, $tipo_equipo, 'nuevo');
        
        establecer_alerta('success', "Equipo {$codigo_interno} registrado exitosamente.");
        
        // Si es "guardar y agregar otro", regresar al formulario
        if ($agregar_otro) {
            header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/crear_equipo.php');
        } else {
            header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
        }
        exit;
        
    } catch (PDOException $e) {
        error_log("Error al crear equipo: " . $e->getMessage());
        $_SESSION['form_errors'] = ['Error al guardar el equipo: ' . $e->getMessage()];
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/crear_equipo.php');
        exit;
    }
}

/**
 * Editar equipo existente
 */
function editarEquipo($pdo) {
    global $_SESSION;
    
    $equipo_id = intval($_POST['equipo_id'] ?? 0);
    
    if (!$equipo_id) {
        establecer_alerta('error', 'ID de equipo no válido.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
        exit;
    }
    
    $errores = [];
    
    // Obtener y sanitizar datos
    $tipo_equipo = trim($_POST['tipo_equipo'] ?? '');
    $codigo_interno = strtoupper(trim($_POST['codigo_interno'] ?? ''));
    $numero_serie = trim($_POST['numero_serie'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $departamento_id = !empty($_POST['departamento_id']) ? intval($_POST['departamento_id']) : null;
    $usuario_asignado_id = !empty($_POST['usuario_asignado_id']) ? intval($_POST['usuario_asignado_id']) : null;
    $correo_asignado = trim($_POST['correo_asignado'] ?? '');
    $fecha_adquisicion = !empty($_POST['fecha_adquisicion']) ? $_POST['fecha_adquisicion'] : null;
    $estado = $_POST['estado'] ?? 'activo';
    $notas = trim($_POST['notas'] ?? '');
    
    // Validaciones
    $tipos_validos = ['computadora', 'impresora', 'camara', 'telefono'];
    if (empty($tipo_equipo) || !in_array($tipo_equipo, $tipos_validos)) {
        $errores[] = 'Debe seleccionar un tipo de equipo válido.';
    }
    
    if (empty($codigo_interno)) {
        $errores[] = 'El código interno es obligatorio.';
    } elseif (!preg_match('/^[A-Z0-9\-]+$/', $codigo_interno)) {
        $errores[] = 'El código interno solo puede contener letras, números y guiones.';
    } else {
        // Verificar que el código no exista (excepto el mismo registro)
        $stmt = $pdo->prepare("SELECT id FROM inventario_equipos WHERE codigo_interno = ? AND id != ?");
        $stmt->execute([$codigo_interno, $equipo_id]);
        if ($stmt->fetch()) {
            $errores[] = 'El código interno ya existe en otro equipo.';
        }
    }
    
    // Ubicación es obligatoria
    if (empty($ubicacion)) {
        $errores[] = 'La ubicación es obligatoria.';
    }
    
    // Estados válidos según el enum de la BD
    $estados_validos = ['activo', 'inactivo', 'en_reparacion', 'dado_de_baja'];
    if (!in_array($estado, $estados_validos)) {
        $errores[] = 'El estado seleccionado no es válido.';
    }
    
    // Si hay errores, regresar al formulario
    if (!empty($errores)) {
        $_SESSION['form_errors'] = $errores;
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/editar_equipo.php?id=' . $equipo_id);
        exit;
    }
    
    try {
        $sql = "UPDATE inventario_equipos SET 
                tipo_equipo = ?, codigo_interno = ?, numero_serie = ?, marca = ?, modelo = ?,
                ubicacion = ?, departamento_id = ?, usuario_asignado_id = ?, correo_asignado = ?,
                fecha_adquisicion = ?, estado = ?, notas = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $tipo_equipo,
            $codigo_interno,
            $numero_serie ?: null,
            $marca ?: null,
            $modelo ?: null,
            $ubicacion,
            $departamento_id,
            $usuario_asignado_id,
            $correo_asignado ?: null,
            $fecha_adquisicion,
            $estado,
            $notas ?: null,
            $equipo_id
        ]);
        
        establecer_alerta('success', "Equipo {$codigo_interno} actualizado exitosamente.");
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
        exit;
        
    } catch (PDOException $e) {
        error_log("Error al editar equipo: " . $e->getMessage());
        $_SESSION['form_errors'] = ['Error al actualizar el equipo: ' . $e->getMessage()];
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/editar_equipo.php?id=' . $equipo_id);
        exit;
    }
}

/**
 * Eliminar equipo
 */
function eliminarEquipo($pdo) {
    $equipo_id = intval($_POST['equipo_id'] ?? 0);
    
    if (!$equipo_id) {
        establecer_alerta('error', 'ID de equipo no válido.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
        exit;
    }
    
    try {
        // Obtener información del equipo antes de eliminar
        $stmt = $pdo->prepare("SELECT codigo_interno FROM inventario_equipos WHERE id = ?");
        $stmt->execute([$equipo_id]);
        $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$equipo) {
            establecer_alerta('error', 'Equipo no encontrado.');
            header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
            exit;
        }
        
        // Eliminar equipo
        $stmt = $pdo->prepare("DELETE FROM inventario_equipos WHERE id = ?");
        $stmt->execute([$equipo_id]);
        
        establecer_alerta('success', "Equipo {$equipo['codigo_interno']} eliminado exitosamente.");
        
    } catch (PDOException $e) {
        error_log("Error al eliminar equipo: " . $e->getMessage());
        establecer_alerta('error', 'Error al eliminar el equipo. Puede tener registros asociados.');
    }
    
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
    exit;
}

/**
 * Crear notificación de equipo para usuarios de Sistemas
 */
function crearNotificacionEquipo($pdo, $equipo_id, $codigo, $tipo, $accion) {
    global $_SESSION;
    
    $tipos_nombres = [
        'computadora' => 'Computadora',
        'impresora' => 'Impresora',
        'camara' => 'Cámara',
        'telefono' => 'Teléfono'
    ];
    
    $tipo_nombre = $tipos_nombres[$tipo] ?? $tipo;
    
    if ($accion === 'nuevo') {
        $titulo = '🖥️ Nuevo Equipo Registrado';
        $mensaje = "{$_SESSION['nombre_completo']} ha registrado un nuevo equipo: {$codigo} ({$tipo_nombre})";
    } else {
        $titulo = '🖥️ Equipo Actualizado';
        $mensaje = "{$_SESSION['nombre_completo']} ha actualizado el equipo: {$codigo}";
    }
    
    // Obtener usuarios de Sistemas (excepto el actual)
    $sql = "SELECT u.id FROM usuarios u 
            INNER JOIN departamentos d ON u.departamento_id = d.id 
            WHERE d.codigo = 'sistemas' AND u.activo = 1 AND u.id != ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Crear notificación para cada usuario de Sistemas
    $sql_notif = "INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, fecha_creacion) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt_notif = $pdo->prepare($sql_notif);
    
    $datos = json_encode([
        'equipo_id' => $equipo_id,
        'codigo' => $codigo,
        'tipo_equipo' => $tipo,
        'url' => URL_BASE . 'dashboard/sistemas/ti_sistemas/ver_equipo.php?id=' . $equipo_id
    ]);
    
    foreach ($usuarios as $usuario_id) {
        try {
            $stmt_notif->execute([
                'inventario_equipo',
                $titulo,
                $mensaje,
                $usuario_id,
                $datos
            ]);
        } catch (PDOException $e) {
            // Si falla la notificación, solo registrar el error pero no interrumpir
            error_log("Error al crear notificación de equipo: " . $e->getMessage());
        }
    }
}