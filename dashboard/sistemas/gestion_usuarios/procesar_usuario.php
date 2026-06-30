<?php
/**
 * Procesador de Usuarios
 * Maneja: crear, editar, cambiar_estado
 * Solo accesible para usuarios del departamento de Sistemas
 * 
 * ACTUALIZADO: Auto-vinculacion con empleados_gth
 * Editar solo maneja: nombre, usuario, password, departamento, admin_area, permisos
 * Campos de GTH (nomina, puesto, ingreso, periodo, empresa) se gestionan desde GTH
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';

// Verificar sesion
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar que sea del departamento de Sistemas
$departamento_usuario = strtolower($_SESSION['departamento'] ?? '');
if ($departamento_usuario !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para realizar esta accion.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php');
    exit;
}

// Conexion a BD
$pdo = conectarDB();

$accion = $_POST['accion'] ?? '';
$usuario_actual_id = $_SESSION['usuario_id'];

/**
 * Validar nombre de usuario
 */
function validarNombreUsuario($pdo, $usuario, $excluir_id = null) {
    if (preg_match('/\s/', $usuario)) {
        return 'El nombre de usuario no puede contener espacios.';
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $usuario)) {
        return 'El nombre de usuario solo puede contener letras, numeros, guion (-) y guion bajo (_).';
    }
    $sql = "SELECT id FROM usuarios WHERE LOWER(usuario) = LOWER(?)";
    $params = [strtolower($usuario)];
    if ($excluir_id) {
        $sql .= " AND id != ?";
        $params[] = $excluir_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        return 'El nombre de usuario ya existe en el sistema.';
    }
    return true;
}

/**
 * Validar contrasena
 */
function validarPassword($password) {
    if (strlen($password) < 8) {
        return 'La contrasena debe tener al menos 8 caracteres.';
    }
    return true;
}

/**
 * Obtener codigo de departamento por ID
 */
function getCodigoDepartamento($pdo, $departamento_id) {
    $stmt = $pdo->prepare("SELECT codigo FROM departamentos WHERE id = ?");
    $stmt->execute([$departamento_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['codigo'] : '';
}

// ============================================================
// ACCION: CREAR USUARIO
// ============================================================
if ($accion === 'crear') {
    $errores = [];
    
    // Recoger datos del formulario
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $departamento_id = (int)($_POST['departamento_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;
    $es_admin_area = isset($_POST['es_admin_area']) ? 1 : 0;
    $empleado_gth_id = intval($_POST['empleado_gth_id'] ?? 0);
    
    // Permisos SSC
    $ssc_lector = 1;
    $ssc_creador = isset($_POST['ssc_creador']) ? 1 : 0;
    $ssc_editor = isset($_POST['ssc_editor']) ? 1 : 0;
    
    // Permisos OSM
    $osm_lector = 1;
    $osm_creador = isset($_POST['osm_creador']) ? 1 : 0;
    $osm_editor = isset($_POST['osm_editor']) ? 1 : 0;
    
    // Permisos CQR
    $cqr_lector = 1;
    $cqr_creador = isset($_POST['cqr_creador']) ? 1 : 0;
    $cqr_editor = isset($_POST['cqr_editor']) ? 1 : 0;
    
    // Permisos SEC
    $sec_lector = 1;
    $sec_creador = isset($_POST['sec_creador']) ? 1 : 0;
    $sec_editor = isset($_POST['sec_editor']) ? 1 : 0;
    
    // Si se vincula con empleado, obtener datos del empleado
    $empleado_datos = null;
    if ($empleado_gth_id > 0) {
        $stmt_emp = $pdo->prepare("SELECT * FROM empleados_gth WHERE id = ? AND usuario_id IS NULL AND activo = 1");
        $stmt_emp->execute([$empleado_gth_id]);
        $empleado_datos = $stmt_emp->fetch(PDO::FETCH_ASSOC);
        if (!$empleado_datos) {
            $errores[] = 'Empleado no encontrado o ya tiene cuenta vinculada.';
        } else {
            // Usar datos del empleado
            $nombre_completo = $empleado_datos['nombre_completo'];
            $departamento_id = (int)$empleado_datos['departamento_id'];
        }
    }
    
    // Validaciones
    if (empty($nombre_completo)) {
        $errores[] = 'El nombre completo es obligatorio.';
    }
    
    if (empty($usuario)) {
        $errores[] = 'El nombre de usuario es obligatorio.';
    } else {
        $validacion_usuario = validarNombreUsuario($pdo, $usuario);
        if ($validacion_usuario !== true) {
            $errores[] = $validacion_usuario;
        }
    }
    
    if ($departamento_id <= 0) {
        $errores[] = 'Debe seleccionar un departamento.';
    }
    
    if (empty($password)) {
        $errores[] = 'La contrasena es obligatoria.';
    } else {
        $validacion_password = validarPassword($password);
        if ($validacion_password !== true) {
            $errores[] = $validacion_password;
        }
    }
    
    if ($password !== $password_confirm) {
        $errores[] = 'Las contrasenas no coinciden.';
    }
    
    // Si hay errores, regresar al formulario
    if (!empty($errores)) {
        $_SESSION['form_errors'] = $errores;
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/crear_usuario.php');
        exit;
    }
    
    // Obtener codigo de departamento
    $departamento_codigo = getCodigoDepartamento($pdo, $departamento_id);
    
    // Hash de la contrasena
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Datos del empleado para INSERT (si se vincula, usa datos del empleado)
    $no_nomina = $empleado_datos['no_nomina'] ?? null;
    $puesto = $empleado_datos['puesto'] ?? null;
    $fecha_ingreso = $empleado_datos['fecha_ingreso'] ?? null;
    $periodo_pago = $empleado_datos['periodo_pago'] ?? null;
    $empresa = $empleado_datos['empresa'] ?? null;
    $jornada = $empleado_datos['jornada'] ?? null;
    
    // Iniciar transaccion
    $pdo->beginTransaction();
    
    try {
        // Insertar usuario
        $sql = "INSERT INTO usuarios (nombre_completo, no_nomina, puesto, fecha_ingreso, periodo_pago, empresa, jornada, usuario, password, departamento, departamento_id, activo, es_admin_area, fecha_registro, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nombre_completo,
            $no_nomina,
            $puesto,
            $fecha_ingreso,
            $periodo_pago,
            $empresa,
            $jornada,
            strtoupper($usuario), 
            $password_hash, 
            $departamento_codigo, 
            $departamento_id, 
            $activo,
            $es_admin_area,
            $usuario_actual_id
        ]);
        
        $nuevo_usuario_id = $pdo->lastInsertId();
        
        // Insertar permisos SSC
        $pdo->prepare("INSERT INTO permisos_ssc (user_id, lector, creador, editor) VALUES (?, ?, ?, ?)")
            ->execute([$nuevo_usuario_id, $ssc_lector, $ssc_creador, $ssc_editor]);
        
        // Insertar permisos OSM
        $pdo->prepare("INSERT INTO permisos_osm (user_id, lector, creador, editor) VALUES (?, ?, ?, ?)")
            ->execute([$nuevo_usuario_id, $osm_lector, $osm_creador, $osm_editor]);
        
        // Insertar permisos CQR
        $pdo->prepare("INSERT INTO permisos_cqr (user_id, lector, creador, editor) VALUES (?, ?, ?, ?)")
            ->execute([$nuevo_usuario_id, $cqr_lector, $cqr_creador, $cqr_editor]);
        
        // Insertar permisos SEC
        $pdo->prepare("INSERT INTO permisos_sec (user_id, lector, creador, editor) VALUES (?, ?, ?, ?)")
            ->execute([$nuevo_usuario_id, $sec_lector, $sec_creador, $sec_editor]);
        
        // Vincular con empleado_gth
        if ($empleado_gth_id > 0 && $empleado_datos) {
            $pdo->prepare("UPDATE empleados_gth SET usuario_id = ?, updated_at = NOW(), updated_by = ? WHERE id = ?")
                ->execute([$nuevo_usuario_id, $usuario_actual_id, $empleado_gth_id]);
        }
        
        $pdo->commit();
        
        $msg = ($empleado_gth_id > 0) ? 'creado_vinculado' : 'creado';
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=' . $msg);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['form_errors'] = ['Error al crear el usuario: ' . $e->getMessage()];
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/crear_usuario.php');
        exit;
    }
}

// ============================================================
// ACCION: EDITAR USUARIO
// Solo: nombre, usuario, password, departamento, admin_area, permisos
// Campos de GTH (nomina, puesto, ingreso, periodo, empresa) se gestionan desde GTH
// ============================================================
elseif ($accion === 'editar') {
    $errores = [];
    
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=error');
        exit;
    }
    
    // Recoger datos del formulario (solo campos de Sistemas)
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $departamento_id = (int)($_POST['departamento_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $es_admin_area = isset($_POST['es_admin_area']) ? 1 : 0;
    
    // Permisos SSC
    $ssc_lector = 1;
    $ssc_creador = isset($_POST['ssc_creador']) ? 1 : 0;
    $ssc_editor = isset($_POST['ssc_editor']) ? 1 : 0;
    
    // Permisos OSM
    $osm_lector = 1;
    $osm_creador = isset($_POST['osm_creador']) ? 1 : 0;
    $osm_editor = isset($_POST['osm_editor']) ? 1 : 0;
    
    // Permisos CQR
    $cqr_lector = 1;
    $cqr_creador = isset($_POST['cqr_creador']) ? 1 : 0;
    $cqr_editor = isset($_POST['cqr_editor']) ? 1 : 0;
    
    // Permisos SEC
    $sec_lector = 1;
    $sec_creador = isset($_POST['sec_creador']) ? 1 : 0;
    $sec_editor = isset($_POST['sec_editor']) ? 1 : 0;
    
    // Validaciones
    if (empty($nombre_completo)) {
        $errores[] = 'El nombre completo es obligatorio.';
    }
    
    if (empty($usuario)) {
        $errores[] = 'El nombre de usuario es obligatorio.';
    } else {
        $validacion_usuario = validarNombreUsuario($pdo, $usuario, $id);
        if ($validacion_usuario !== true) {
            $errores[] = $validacion_usuario;
        }
    }
    
    if ($departamento_id <= 0) {
        $errores[] = 'Debe seleccionar un departamento.';
    }
    
    // Validar contrasena solo si se esta cambiando
    $cambiar_password = false;
    if (!empty($password) || !empty($password_confirm)) {
        if ($password !== $password_confirm) {
            $errores[] = 'Las contrasenas no coinciden.';
        } else {
            $validacion_password = validarPassword($password);
            if ($validacion_password !== true) {
                $errores[] = $validacion_password;
            } else {
                $cambiar_password = true;
            }
        }
    }
    
    // Si hay errores, regresar al formulario
    if (!empty($errores)) {
        $_SESSION['form_errors'] = $errores;
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . "dashboard/sistemas/gestion_usuarios/editar_usuario.php?id=$id");
        exit;
    }
    
    // Obtener codigo de departamento
    $departamento_codigo = getCodigoDepartamento($pdo, $departamento_id);
    
    // Iniciar transaccion
    $pdo->beginTransaction();
    
    try {
        // Actualizar usuario (sin campos de GTH)
        if ($cambiar_password) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET 
                        nombre_completo = ?,
                        usuario = ?, 
                        password = ?,
                        departamento = ?, 
                        departamento_id = ?,
                        es_admin_area = ?,
                        updated_at = NOW(),
                        updated_by = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nombre_completo,
                strtoupper($usuario),
                $password_hash,
                $departamento_codigo,
                $departamento_id,
                $es_admin_area,
                $usuario_actual_id,
                $id
            ]);
        } else {
            $sql = "UPDATE usuarios SET 
                        nombre_completo = ?, 
                        usuario = ?, 
                        departamento = ?, 
                        departamento_id = ?,
                        es_admin_area = ?,
                        updated_at = NOW(),
                        updated_by = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nombre_completo,
                strtoupper($usuario),
                $departamento_codigo,
                $departamento_id,
                $es_admin_area,
                $usuario_actual_id,
                $id
            ]);
        }
        
        // Actualizar permisos SSC (UPSERT)
        $pdo->prepare("INSERT INTO permisos_ssc (user_id, lector, creador, editor) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE lector = VALUES(lector), creador = VALUES(creador), editor = VALUES(editor)")
            ->execute([$id, $ssc_lector, $ssc_creador, $ssc_editor]);
        
        // Actualizar permisos OSM (UPSERT)
        $pdo->prepare("INSERT INTO permisos_osm (user_id, lector, creador, editor) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE lector = VALUES(lector), creador = VALUES(creador), editor = VALUES(editor)")
            ->execute([$id, $osm_lector, $osm_creador, $osm_editor]);
        
        // Actualizar permisos CQR (UPSERT)
        $pdo->prepare("INSERT INTO permisos_cqr (user_id, lector, creador, editor) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE lector = VALUES(lector), creador = VALUES(creador), editor = VALUES(editor)")
            ->execute([$id, $cqr_lector, $cqr_creador, $cqr_editor]);
        
        // Actualizar permisos SEC
        $pdo->prepare("INSERT INTO permisos_sec (user_id, lector, creador, editor) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE lector = VALUES(lector), creador = VALUES(creador), editor = VALUES(editor)")
            ->execute([$id, $sec_lector, $sec_creador, $sec_editor]);
        
        $pdo->commit();
        
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=editado');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['form_errors'] = ['Error al actualizar el usuario: ' . $e->getMessage()];
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . "dashboard/sistemas/gestion_usuarios/editar_usuario.php?id=$id");
        exit;
    }
}

// ============================================================
// ACCION: CAMBIAR ESTADO (ACTIVAR/DESACTIVAR)
// ============================================================
elseif ($accion === 'cambiar_estado') {
    $id = (int)($_POST['id'] ?? 0);
    $nuevo_estado = (int)($_POST['nuevo_estado'] ?? 0);
    
    if ($id <= 0) {
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=error');
        exit;
    }
    
    // No permitir desactivarse a si mismo
    if ($id == $usuario_actual_id && $nuevo_estado == 0) {
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=no_autodesactivar');
        exit;
    }
    
    try {
        $sql = "UPDATE usuarios SET activo = ?, updated_at = NOW(), updated_by = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nuevo_estado, $usuario_actual_id, $id]);
        
        $msg = $nuevo_estado ? 'activado' : 'desactivado';
        header('Location: ' . URL_BASE . "dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=$msg");
        exit;
        
    } catch (Exception $e) {
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=error');
        exit;
    }
}

// Accion no reconocida
else {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php');
    exit;
}

?>