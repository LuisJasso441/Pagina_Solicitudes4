<?php
/**
 * Procesar Equipo - Crear/Editar/Eliminar
 * dashboard/sistemas/ti_sistemas/procesar_equipo.php
 * 
 * v2.0 - Tipos ampliados, hostname, personal_asignado
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para realizar esta acci&oacute;n.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
    exit;
}

$pdo = conectarDB();
$accion = $_POST['accion'] ?? '';

switch ($accion) {
    case 'crear':  crearEquipo($pdo); break;
    case 'editar': editarEquipo($pdo); break;
    case 'eliminar': eliminarEquipo($pdo); break;
    default:
        establecer_alerta('error', 'Acci&oacute;n no v&aacute;lida.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
        exit;
}

/**
 * Tipos v&aacute;lidos de equipo (v2.0)
 */
function tiposValidos() {
    return [
        'all_in_one', 'pc', 'laptop', 'monitor', 'mouse', 'teclado',
        'impresora', 'access_point', 'switch', 'camara', 'celular',
        'computadora', 'telefono', 'pantalla_tv', 'nobreak'
    ];
}

/**
 * Validaciones comunes para crear/editar
 */
function validarDatosEquipo($pdo, $datos, $equipo_id = 0) {
    $errores = [];
    
    if (empty($datos['tipo_equipo']) || !in_array($datos['tipo_equipo'], tiposValidos())) {
        $errores[] = 'Debe seleccionar un tipo de equipo v&aacute;lido.';
    }
    
    if (empty($datos['hostname'])) {
        $errores[] = 'El hostname es obligatorio.';
    } elseif (!preg_match('/^[A-Z0-9\-_]+$/i', $datos['hostname'])) {
        $errores[] = 'El hostname solo puede contener letras, n&uacute;meros, guiones y guiones bajos.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM inventario_equipos WHERE hostname = ? AND id != ?");
        $stmt->execute([strtoupper($datos['hostname']), $equipo_id]);
        if ($stmt->fetch()) {
            $errores[] = 'El hostname ya existe en otro equipo.';
        }
    }
    
    if (empty($datos['ubicacion'])) {
        $errores[] = 'La ubicaci&oacute;n es obligatoria.';
    }
    
    $estados_validos = ['activo', 'inactivo', 'en_reparacion', 'dado_de_baja'];
    if (!in_array($datos['estado'], $estados_validos)) {
        $errores[] = 'El estado seleccionado no es v&aacute;lido.';
    }
    
    return $errores;
}

/**
 * Extraer y sanitizar datos del POST
 */
function extraerDatosPost() {
    return [
        'tipo_equipo'       => trim($_POST['tipo_equipo'] ?? ''),
        'hostname'          => strtoupper(trim($_POST['hostname'] ?? '')),
        'codigo_interno'    => strtoupper(trim($_POST['codigo_interno'] ?? '')),
        'numero_serie'      => trim($_POST['numero_serie'] ?? ''),
        'marca'             => trim($_POST['marca'] ?? ''),
        'modelo'            => trim($_POST['modelo'] ?? ''),
        'ubicacion'         => trim($_POST['ubicacion'] ?? ''),
        'departamento_id'   => !empty($_POST['departamento_id']) ? intval($_POST['departamento_id']) : null,
        'usuario_asignado_id' => !empty($_POST['usuario_asignado_id']) ? intval($_POST['usuario_asignado_id']) : null,
        'personal_asignado' => trim($_POST['personal_asignado'] ?? ''),
        'correo_asignado'   => trim($_POST['correo_asignado'] ?? ''),
        'fecha_adquisicion' => !empty($_POST['fecha_adquisicion']) ? $_POST['fecha_adquisicion'] : null,
        'estado'            => $_POST['estado'] ?? 'activo',
        'notas'             => trim($_POST['notas'] ?? ''),
    ];
}

/**
 * Crear nuevo equipo
 */
function crearEquipo($pdo) {
    $datos = extraerDatosPost();
    $errores = validarDatosEquipo($pdo, $datos);
    $agregar_otro = isset($_POST['agregar_otro']);
    
    if (!empty($errores)) {
        $_SESSION['form_errors'] = $errores;
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/crear_equipo.php');
        exit;
    }
    
    // Si no se proporcionó codigo_interno, usar hostname
    if (empty($datos['codigo_interno'])) {
        $datos['codigo_interno'] = $datos['hostname'];
    }
    
    try {
        $sql = "INSERT INTO inventario_equipos 
                (tipo_equipo, hostname, codigo_interno, numero_serie, marca, modelo, 
                 ubicacion, departamento_id, usuario_asignado_id, personal_asignado,
                 correo_asignado, estado, fecha_adquisicion, notas, registrado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $datos['tipo_equipo'],
            $datos['hostname'],
            $datos['codigo_interno'],
            $datos['numero_serie'] ?: null,
            $datos['marca'] ?: null,
            $datos['modelo'] ?: null,
            $datos['ubicacion'],
            $datos['departamento_id'],
            $datos['usuario_asignado_id'],
            $datos['personal_asignado'] ?: null,
            $datos['correo_asignado'] ?: null,
            $datos['estado'],
            $datos['fecha_adquisicion'],
            $datos['notas'] ?: null,
            $_SESSION['usuario_id']
        ]);
        
        $equipo_id = $pdo->lastInsertId();
        crearNotificacionEquipo($pdo, $equipo_id, $datos['hostname'], $datos['tipo_equipo'], 'nuevo');
        
        establecer_alerta('success', "Equipo {$datos['hostname']} registrado exitosamente.");
        
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
    $equipo_id = intval($_POST['equipo_id'] ?? 0);
    if (!$equipo_id) {
        establecer_alerta('error', 'ID de equipo no v&aacute;lido.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
        exit;
    }
    
    $datos = extraerDatosPost();
    $errores = validarDatosEquipo($pdo, $datos, $equipo_id);
    
    if (!empty($errores)) {
        $_SESSION['form_errors'] = $errores;
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/editar_equipo.php?id=' . $equipo_id);
        exit;
    }
    
    if (empty($datos['codigo_interno'])) {
        $datos['codigo_interno'] = $datos['hostname'];
    }
    
    try {
        $sql = "UPDATE inventario_equipos SET 
                tipo_equipo = ?, hostname = ?, codigo_interno = ?, numero_serie = ?, 
                marca = ?, modelo = ?, ubicacion = ?, departamento_id = ?, 
                usuario_asignado_id = ?, personal_asignado = ?, correo_asignado = ?,
                fecha_adquisicion = ?, estado = ?, notas = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $datos['tipo_equipo'],
            $datos['hostname'],
            $datos['codigo_interno'],
            $datos['numero_serie'] ?: null,
            $datos['marca'] ?: null,
            $datos['modelo'] ?: null,
            $datos['ubicacion'],
            $datos['departamento_id'],
            $datos['usuario_asignado_id'],
            $datos['personal_asignado'] ?: null,
            $datos['correo_asignado'] ?: null,
            $datos['fecha_adquisicion'],
            $datos['estado'],
            $datos['notas'] ?: null,
            $equipo_id
        ]);
        
        establecer_alerta('success', "Equipo {$datos['hostname']} actualizado exitosamente.");
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
        establecer_alerta('error', 'ID de equipo no v&aacute;lido.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT hostname, codigo_interno FROM inventario_equipos WHERE id = ?");
        $stmt->execute([$equipo_id]);
        $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$equipo) {
            establecer_alerta('error', 'Equipo no encontrado.');
            header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM inventario_equipos WHERE id = ?");
        $stmt->execute([$equipo_id]);
        
        $nombre_equipo = $equipo['hostname'] ?: $equipo['codigo_interno'];
        establecer_alerta('success', "Equipo {$nombre_equipo} eliminado exitosamente.");
        
    } catch (PDOException $e) {
        error_log("Error al eliminar equipo: " . $e->getMessage());
        establecer_alerta('error', 'Error al eliminar el equipo. Puede tener registros asociados.');
    }
    
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
    exit;
}

/**
 * Crear notificaci&oacute;n de equipo
 */
function crearNotificacionEquipo($pdo, $equipo_id, $codigo, $tipo, $accion) {
    $tipos_nombres = [
        'all_in_one'   => 'All In One',
        'pc'           => 'PC',
        'laptop'       => 'Laptop',
        'monitor'      => 'Monitor',
        'mouse'        => 'Mouse',
        'teclado'      => 'Teclado',
        'impresora'    => 'Impresora',
        'access_point' => 'Access Point',
        'switch'       => 'Switch',
        'camara'       => 'C&aacute;mara',
        'celular'      => 'Celular',
        'computadora'  => 'Computadora',
        'telefono'     => 'Tel&eacute;fono'
    ];
    
    $tipo_nombre = $tipos_nombres[$tipo] ?? ucfirst($tipo);
    $acciones_texto = [
        'nuevo' => 'registrado',
        'editar' => 'actualizado',
        'eliminar' => 'eliminado'
    ];
    $accion_texto = $acciones_texto[$accion] ?? $accion;
    
    try {
        $mensaje = "{$tipo_nombre} {$codigo} ha sido {$accion_texto} en el inventario.";
        
        $stmt_usuarios = $pdo->prepare(
            "SELECT u.id FROM usuarios u 
             INNER JOIN departamentos d ON u.departamento_id = d.id 
             WHERE LOWER(d.codigo) = 'sistemas' AND u.activo = 1 AND u.id != ?"
        );
        $stmt_usuarios->execute([$_SESSION['usuario_id']]);
        $usuarios_sistemas = $stmt_usuarios->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($usuarios_sistemas)) {
            $sql_notif = "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, url_destino) 
                          VALUES (?, 'inventario', ?, ?, ?)";
            $stmt_notif = $pdo->prepare($sql_notif);
            $url = "dashboard/sistemas/ti_sistemas/ver_equipo.php?id={$equipo_id}";
            
            foreach ($usuarios_sistemas as $uid) {
                $stmt_notif->execute([$uid, "Equipo {$accion_texto}", $mensaje, $url]);
            }
        }
    } catch (PDOException $e) {
        error_log("Error al crear notificaci&oacute;n de equipo: " . $e->getMessage());
    }
}