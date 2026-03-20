<?php
/**
 * Procesar Promocion de Empleado a Usuario
 * dashboard/sistemas/gestion_usuarios/procesar_promover.php
 * 
 * Crea registro en usuarios con datos de empleados_gth
 * Vincula empleados_gth.usuario_id = nuevo_usuario.id
 */
session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php'); exit;
}

if (strtolower($_SESSION['departamento'] ?? '') !== 'sistemas') {
    establecer_alerta('error', 'No tienes permisos.');
    header('Location: ' . URL_BASE . 'index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/promover_empleado.php'); exit;
}

$pdo = conectarDB();
$errores = [];

$empleado_gth_id = intval($_POST['empleado_gth_id'] ?? 0);
$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// Validar
if ($empleado_gth_id <= 0) $errores[] = 'Seleccione un empleado.';
if (empty($usuario)) $errores[] = 'El nombre de usuario es obligatorio.';
if (preg_match('/\s/', $usuario)) $errores[] = 'Sin espacios en el usuario.';
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $usuario)) $errores[] = 'Solo letras, numeros, guion y guion bajo.';
if (empty($password)) $errores[] = 'La contrasena es obligatoria.';
if (strlen($password) < 8) $errores[] = 'Minimo 8 caracteres.';
if ($password !== $password_confirm) $errores[] = 'Las contrasenas no coinciden.';

// Verificar que el usuario no exista
if (!empty($usuario)) {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(usuario) = LOWER(?)");
    $stmt->execute([strtolower($usuario)]);
    if ($stmt->fetch()) $errores[] = 'El nombre de usuario ya existe.';
}

// Verificar que el empleado existe y no esta vinculado
$stmt_emp = $pdo->prepare("SELECT * FROM empleados_gth WHERE id = ? AND usuario_id IS NULL AND activo = 1");
$stmt_emp->execute([$empleado_gth_id]);
$empleado = $stmt_emp->fetch(PDO::FETCH_ASSOC);

if (!$empleado) $errores[] = 'Empleado no encontrado o ya tiene cuenta.';

if (!empty($errores)) {
    $_SESSION['form_errors_promover'] = $errores;
    $_SESSION['form_data_promover'] = $_POST;
    header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/promover_empleado.php');
    exit;
}

// Obtener codigo de departamento
$dep_codigo = '';
if ($empleado['departamento_id']) {
    $stmt_d = $pdo->prepare("SELECT codigo FROM departamentos WHERE id = ?");
    $stmt_d->execute([$empleado['departamento_id']]);
    $dep_codigo = $stmt_d->fetchColumn() ?: '';
}

try {
    $pdo->beginTransaction();

    // 1. Crear usuario
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $sql_user = "INSERT INTO usuarios (nombre_completo, no_nomina, puesto, departamento, departamento_id,
                 fecha_ingreso, periodo_pago, empresa, jornada, usuario, password, activo, es_admin_area,
                 fecha_registro, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW(), ?)";
    $pdo->prepare($sql_user)->execute([
        $empleado['nombre_completo'],
        $empleado['no_nomina'],
        $empleado['puesto'],
        $dep_codigo,
        $empleado['departamento_id'],
        $empleado['fecha_ingreso'],
        $empleado['periodo_pago'],
        $empleado['empresa'],
        $empleado['jornada'] ?? 'lunes_sabado',
        strtoupper($usuario),
        $password_hash,
        $_SESSION['usuario_id']
    ]);

    $nuevo_usuario_id = $pdo->lastInsertId();

    // 2. Insertar permisos por defecto
    $pdo->prepare("INSERT INTO permisos_ssc (user_id, lector, creador, editor) VALUES (?, 1, 0, 0)")->execute([$nuevo_usuario_id]);
    $pdo->prepare("INSERT INTO permisos_osm (user_id, lector, creador, editor) VALUES (?, 1, 0, 0)")->execute([$nuevo_usuario_id]);
    $pdo->prepare("INSERT INTO permisos_cqr (user_id, lector, creador, editor) VALUES (?, 1, 0, 0)")->execute([$nuevo_usuario_id]);

    // 3. Vincular empleado con usuario
    $pdo->prepare("UPDATE empleados_gth SET usuario_id = ?, updated_at = NOW(), updated_by = ? WHERE id = ?")
        ->execute([$nuevo_usuario_id, $_SESSION['usuario_id'], $empleado_gth_id]);

    $pdo->commit();

    $_SESSION['alerta'] = ['tipo' => 'success', 'mensaje' => 'Empleado <strong>' . htmlspecialchars($empleado['nombre_completo']) . '</strong> promovido a usuario <code>' . strtoupper($usuario) . '</code> exitosamente.'];
    header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error promover empleado: " . $e->getMessage());
    $_SESSION['form_errors_promover'] = ['Error: ' . $e->getMessage()];
    $_SESSION['form_data_promover'] = $_POST;
    header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/promover_empleado.php');
    exit;
}