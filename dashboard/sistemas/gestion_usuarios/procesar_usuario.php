<?php
/**
 * Procesador de Usuarios
 * Maneja: crear, editar, cambiar_estado
 * Solo accesible para usuarios del departamento de Sistemas
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar que sea del departamento de Sistemas (validación backend crítica)
$departamento_usuario = strtolower($_SESSION['departamento'] ?? '');
if ($departamento_usuario !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para realizar esta acción.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php');
    exit;
}

// Conexión a BD
$pdo = conectarDB();

$accion = $_POST['accion'] ?? '';
$usuario_actual_id = $_SESSION['usuario_id'];

/**
 * Validar nombre de usuario
 * - Único (case-insensitive)
 * - Sin espacios
 * - Solo letras, números, guión y guión bajo
 */
function validarNombreUsuario($pdo, $usuario, $excluir_id = null) {
    // Sin espacios
    if (preg_match('/\s/', $usuario)) {
        return 'El nombre de usuario no puede contener espacios.';
    }
    
    // Solo caracteres permitidos
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $usuario)) {
        return 'El nombre de usuario solo puede contener letras, números, guión (-) y guión bajo (_).';
    }
    
    // Verificar unicidad (case-insensitive)
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
 * Validar contraseña
 * - Mínimo 8 caracteres
 */
function validarPassword($password) {
    if (strlen($password) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres.';
    }
    return true;
}

/**
 * Obtener código de departamento por ID
 */
function getCodigoDepartamento($pdo, $departamento_id) {
    $stmt = $pdo->prepare("SELECT codigo FROM departamentos WHERE id = ?");
    $stmt->execute([$departamento_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['codigo'] : '';
}

// ============================================================
// ACCIÓN: CREAR USUARIO
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
    
    // Permisos SSC
    $ssc_lector = 1; // Siempre activo por defecto
    $ssc_creador = isset($_POST['ssc_creador']) ? 1 : 0;
    $ssc_editor = isset($_POST['ssc_editor']) ? 1 : 0;
    
    // Permisos OSM
    $osm_lector = 1; // Siempre activo por defecto
    $osm_creador = isset($_POST['osm_creador']) ? 1 : 0;
    $osm_editor = isset($_POST['osm_editor']) ? 1 : 0;
    
    // Permisos CQR
    $cqr_lector = 1; // Siempre activo por defecto
    $cqr_creador = isset($_POST['cqr_creador']) ? 1 : 0;
    $cqr_editor = isset($_POST['cqr_editor']) ? 1 : 0;
    
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
        $errores[] = 'La contraseña es obligatoria.';
    } else {
        $validacion_password = validarPassword($password);
        if ($validacion_password !== true) {
            $errores[] = $validacion_password;
        }
    }
    
    if ($password !== $password_confirm) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    
    // Si hay errores, regresar al formulario
    if (!empty($errores)) {
        $_SESSION['form_errors'] = $errores;
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/crear_usuario.php');
        exit;
    }
    
    // Obtener código de departamento
    $departamento_codigo = getCodigoDepartamento($pdo, $departamento_id);
    
    // Hash de la contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // Insertar usuario
        $sql = "INSERT INTO usuarios (nombre_completo, usuario, password, departamento, departamento_id, activo, fecha_registro, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nombre_completo, 
            strtoupper($usuario), 
            $password_hash, 
            $departamento_codigo, 
            $departamento_id, 
            $activo, 
            $usuario_actual_id
        ]);
        
        $nuevo_usuario_id = $pdo->lastInsertId();
        
        // Insertar permisos SSC
        $sql_ssc = "INSERT INTO permisos_ssc (user_id, lector, creador, editor) VALUES (?, ?, ?, ?)";
        $stmt_ssc = $pdo->prepare($sql_ssc);
        $stmt_ssc->execute([$nuevo_usuario_id, $ssc_lector, $ssc_creador, $ssc_editor]);
        
        // Insertar permisos OSM
        $sql_osm = "INSERT INTO permisos_osm (user_id, lector, creador, editor) VALUES (?, ?, ?, ?)";
        $stmt_osm = $pdo->prepare($sql_osm);
        $stmt_osm->execute([$nuevo_usuario_id, $osm_lector, $osm_creador, $osm_editor]);
        
        // Insertar permisos CQR
        $sql_cqr = "INSERT INTO permisos_cqr (user_id, lector, creador, editor) VALUES (?, ?, ?, ?)";
        $stmt_cqr = $pdo->prepare($sql_cqr);
        $stmt_cqr->execute([$nuevo_usuario_id, $cqr_lector, $cqr_creador, $cqr_editor]);
        
        $pdo->commit();
        
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=creado');
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
// ACCIÓN: EDITAR USUARIO
// ============================================================
elseif ($accion === 'editar') {
    $errores = [];
    
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=error');
        exit;
    }
    
    // Recoger datos del formulario
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $departamento_id = (int)($_POST['departamento_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
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
    
    // Validar contraseña solo si se está cambiando
    $cambiar_password = false;
    if (!empty($password) || !empty($password_confirm)) {
        if ($password !== $password_confirm) {
            $errores[] = 'Las contraseñas no coinciden.';
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
    
    // Obtener código de departamento
    $departamento_codigo = getCodigoDepartamento($pdo, $departamento_id);
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // Actualizar usuario
        if ($cambiar_password) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET 
                        nombre_completo = ?, 
                        usuario = ?, 
                        password = ?,
                        departamento = ?, 
                        departamento_id = ?,
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
                $usuario_actual_id,
                $id
            ]);
        } else {
            $sql = "UPDATE usuarios SET 
                        nombre_completo = ?, 
                        usuario = ?, 
                        departamento = ?, 
                        departamento_id = ?,
                        updated_at = NOW(),
                        updated_by = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nombre_completo, 
                strtoupper($usuario), 
                $departamento_codigo, 
                $departamento_id,
                $usuario_actual_id,
                $id
            ]);
        }
        
        // Actualizar permisos SSC (INSERT ... ON DUPLICATE KEY UPDATE)
        $sql_ssc = "INSERT INTO permisos_ssc (user_id, lector, creador, editor, updated_at) 
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE lector = ?, creador = ?, editor = ?, updated_at = NOW()";
        $stmt_ssc = $pdo->prepare($sql_ssc);
        $stmt_ssc->execute([$id, $ssc_lector, $ssc_creador, $ssc_editor, $ssc_lector, $ssc_creador, $ssc_editor]);
        
        // Actualizar permisos OSM
        $sql_osm = "INSERT INTO permisos_osm (user_id, lector, creador, editor, updated_at) 
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE lector = ?, creador = ?, editor = ?, updated_at = NOW()";
        $stmt_osm = $pdo->prepare($sql_osm);
        $stmt_osm->execute([$id, $osm_lector, $osm_creador, $osm_editor, $osm_lector, $osm_creador, $osm_editor]);
        
        // Actualizar permisos CQR
        $sql_cqr = "INSERT INTO permisos_cqr (user_id, lector, creador, editor, updated_at) 
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE lector = ?, creador = ?, editor = ?, updated_at = NOW()";
        $stmt_cqr = $pdo->prepare($sql_cqr);
        $stmt_cqr->execute([$id, $cqr_lector, $cqr_creador, $cqr_editor, $cqr_lector, $cqr_creador, $cqr_editor]);
        
        $pdo->commit();
        
        header('Location: ' . URL_BASE . "dashboard/sistemas/gestion_usuarios/editar_usuario.php?id=$id&msg=actualizado");
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
// ACCIÓN: CAMBIAR ESTADO
// ============================================================
elseif ($accion === 'cambiar_estado') {
    $id = (int)($_POST['id'] ?? 0);
    $nuevo_estado = (int)($_POST['nuevo_estado'] ?? 0);
    
    if ($id <= 0) {
        header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=error');
        exit;
    }
    
    // Validar que el estado sea válido (0 o 1)
    $nuevo_estado = $nuevo_estado === 1 ? 1 : 0;
    
    try {
        $sql = "UPDATE usuarios SET 
                    activo = ?, 
                    updated_at = NOW(), 
                    updated_by = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nuevo_estado, $usuario_actual_id, $id]);
        
        $msg = $nuevo_estado === 1 ? 'activado' : 'desactivado';
        header('Location: ' . URL_BASE . "dashboard/sistemas/gestion_usuarios/editar_usuario.php?id=$id&msg=$msg");
        exit;
        
    } catch (Exception $e) {
        header('Location: ' . URL_BASE . "dashboard/sistemas/gestion_usuarios/editar_usuario.php?id=$id&msg=error");
        exit;
    }
}

// ============================================================
// ACCIÓN NO RECONOCIDA
// ============================================================
else {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=error');
    exit;
}