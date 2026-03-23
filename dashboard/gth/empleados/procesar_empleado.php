<?php
/**
 * Procesador de Empleados GTH
 * dashboard/gth/empleados/procesar_empleado.php
 * 
 * Tabla maestra: empleados_gth
 * Si el empleado tiene usuario_id (vinculado), sincroniza campos a usuarios
 */
session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php'); exit;
}

$depto_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
if (!in_array($depto_codigo, ['gth', 'gestion_talento', 'contabilidad'])) {
    establecer_alerta('error', 'No tienes permisos.');
    header('Location: ' . URL_BASE . 'index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/gth/empleados/listar_empleados.php'); exit;
}

$pdo = conectarDB();
$accion = $_POST['accion'] ?? '';
$usuario_actual_id = $_SESSION['usuario_id'];

/**
 * Sincronizar campos compartidos de empleados_gth -> usuarios
 */
function sincronizar_a_usuarios($pdo, $usuario_id, $datos, $updated_by) {
    if (!$usuario_id) return;

    // Obtener codigo de departamento
    $dep_codigo = '';
    if (!empty($datos['departamento_id'])) {
        $stmt = $pdo->prepare("SELECT codigo FROM departamentos WHERE id = ?");
        $stmt->execute([$datos['departamento_id']]);
        $dep_codigo = $stmt->fetchColumn() ?: '';
    }

    $sql = "UPDATE usuarios SET 
                nombre_completo = ?, no_nomina = ?, puesto = ?,
                departamento_id = ?, departamento = ?,
                fecha_ingreso = ?, periodo_pago = ?, empresa = ?, jornada = ?,
                updated_at = NOW(), updated_by = ?
            WHERE id = ?";
    $pdo->prepare($sql)->execute([
        $datos['nombre_completo'],
        $datos['no_nomina'] ?: null,
        $datos['puesto'] ?: null,
        $datos['departamento_id'],
        $dep_codigo,
        $datos['fecha_ingreso'] ?: null,
        $datos['periodo_pago'] ?: null,
        $datos['empresa'] ?: null,
        $datos['jornada'] ?? 'lunes_sabado',
        $updated_by,
        $usuario_id
    ]);
}

// ============================================================
// CREAR EMPLEADO
// ============================================================
if ($accion === 'crear') {
    $errores = [];
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $no_nomina = trim($_POST['no_nomina'] ?? '');
    $departamento_id = intval($_POST['departamento_id'] ?? 0);
    $puesto = trim($_POST['puesto'] ?? '');
    $fecha_ingreso = trim($_POST['fecha_ingreso'] ?? '');
    $periodo_pago = trim($_POST['periodo_pago'] ?? '');
    $empresa = trim($_POST['empresa'] ?? '');
    $jornada = trim($_POST['jornada'] ?? 'lunes_sabado');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if (empty($nombre_completo)) $errores[] = 'El nombre es obligatorio.';
    if ($departamento_id <= 0) $errores[] = 'Seleccione un departamento.';

    if (!empty($no_nomina)) {
        $stmt = $pdo->prepare("SELECT id FROM empleados_gth WHERE no_nomina = ?");
        $stmt->execute([$no_nomina]);
        if ($stmt->fetch()) $errores[] = 'Nomina ya existe en empleados.';
    }

    if (!empty($errores)) {
        $_SESSION['form_errors_emp'] = $errores;
        $_SESSION['form_data_emp'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/gth/empleados/crear_empleado.php'); exit;
    }

    try {
        $pdo->beginTransaction();

        // Buscar si ya existe un usuario con esta nomina para auto-vincular
        $vincular_usuario_id = null;
        if (!empty($no_nomina)) {
            $stmt_buscar = $pdo->prepare("SELECT id FROM usuarios WHERE no_nomina = ? AND id NOT IN (SELECT COALESCE(usuario_id, 0) FROM empleados_gth WHERE usuario_id IS NOT NULL)");
            $stmt_buscar->execute([$no_nomina]);
            $usuario_encontrado = $stmt_buscar->fetch(PDO::FETCH_ASSOC);
            if ($usuario_encontrado) {
                $vincular_usuario_id = $usuario_encontrado['id'];
            }
        }

        $sql = "INSERT INTO empleados_gth (usuario_id, nombre_completo, no_nomina, puesto, departamento_id, fecha_ingreso, periodo_pago, empresa, jornada, observaciones, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([
            $vincular_usuario_id,
            $nombre_completo, $no_nomina ?: null, $puesto ?: null, $departamento_id,
            $fecha_ingreso ?: null, $periodo_pago ?: null, $empresa ?: null, $jornada,
            $observaciones ?: null, $usuario_actual_id
        ]);

        // Si se vinculo, sincronizar datos del empleado hacia el usuario
        if ($vincular_usuario_id) {
            sincronizar_a_usuarios($pdo, $vincular_usuario_id, [
                'nombre_completo' => $nombre_completo,
                'no_nomina' => $no_nomina,
                'puesto' => $puesto,
                'departamento_id' => $departamento_id,
                'fecha_ingreso' => $fecha_ingreso,
                'periodo_pago' => $periodo_pago,
                'empresa' => $empresa,
                'jornada' => $jornada,
            ], $usuario_actual_id);
        }

        $pdo->commit();

        $msg_vinculado = $vincular_usuario_id ? ' y vinculado con su cuenta de plataforma' : '';
        $_SESSION['alerta'] = ['tipo' => 'success', 'mensaje' => 'Empleado <strong>' . htmlspecialchars($nombre_completo) . '</strong> creado' . $msg_vinculado . '.'];
        header('Location: ' . URL_BASE . 'dashboard/gth/empleados/listar_empleados.php'); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error crear empleado: " . $e->getMessage());
        $_SESSION['form_errors_emp'] = ['Error: ' . $e->getMessage()];
        $_SESSION['form_data_emp'] = $_POST;
        header('Location: ' . URL_BASE . 'dashboard/gth/empleados/crear_empleado.php'); exit;
    }
}

// ============================================================
// EDITAR EMPLEADO (siempre en empleados_gth, sincroniza a usuarios si vinculado)
// ============================================================
elseif ($accion === 'editar') {
    $id = intval($_POST['id'] ?? 0);
    $errores = [];

    if ($id <= 0) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'ID invalido.'];
        header('Location: ' . URL_BASE . 'dashboard/gth/empleados/listar_empleados.php'); exit;
    }

    // Obtener empleado actual para saber si esta vinculado
    $stmt_emp = $pdo->prepare("SELECT usuario_id FROM empleados_gth WHERE id = ?");
    $stmt_emp->execute([$id]);
    $emp_actual = $stmt_emp->fetch(PDO::FETCH_ASSOC);

    if (!$emp_actual) {
        $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Empleado no encontrado.'];
        header('Location: ' . URL_BASE . 'dashboard/gth/empleados/listar_empleados.php'); exit;
    }

    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $no_nomina = trim($_POST['no_nomina'] ?? '');
    $departamento_id = intval($_POST['departamento_id'] ?? 0);
    $puesto = trim($_POST['puesto'] ?? '');
    $fecha_ingreso = trim($_POST['fecha_ingreso'] ?? '');
    $periodo_pago = trim($_POST['periodo_pago'] ?? '');
    $empresa = trim($_POST['empresa'] ?? '');
    $jornada = trim($_POST['jornada'] ?? 'lunes_sabado');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if (empty($nombre_completo)) $errores[] = 'El nombre es obligatorio.';
    if ($departamento_id <= 0) $errores[] = 'Seleccione un departamento.';

    if (!empty($no_nomina)) {
        $stmt = $pdo->prepare("SELECT id FROM empleados_gth WHERE no_nomina = ? AND id != ?");
        $stmt->execute([$no_nomina, $id]);
        if ($stmt->fetch()) $errores[] = 'Nomina duplicada en empleados.';

        // Si esta vinculado, excluir su propio usuario de la validacion
        $excluir_uid = $emp_actual['usuario_id'] ?? 0;
        $sql_u = "SELECT id FROM usuarios WHERE no_nomina = ?" . ($excluir_uid ? " AND id != ?" : "");
        $params_u = [$no_nomina];
        if ($excluir_uid) $params_u[] = $excluir_uid;
        $stmt2 = $pdo->prepare($sql_u);
        $stmt2->execute($params_u);
        if ($stmt2->fetch()) $errores[] = 'Nomina duplicada en usuarios.';
    }

    if (!empty($errores)) {
        $_SESSION['form_errors_emp'] = $errores;
        $_SESSION['form_data_emp'] = $_POST;
        header('Location: ' . URL_BASE . "dashboard/gth/empleados/editar_empleado.php?id=$id"); exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Actualizar empleados_gth (tabla maestra)
        $sql = "UPDATE empleados_gth SET 
                    nombre_completo = ?, no_nomina = ?, puesto = ?, departamento_id = ?,
                    fecha_ingreso = ?, periodo_pago = ?, empresa = ?, jornada = ?,
                    observaciones = ?, updated_at = NOW(), updated_by = ?
                WHERE id = ?";
        $pdo->prepare($sql)->execute([
            $nombre_completo, $no_nomina ?: null, $puesto ?: null, $departamento_id,
            $fecha_ingreso ?: null, $periodo_pago ?: null, $empresa ?: null, $jornada,
            $observaciones ?: null, $usuario_actual_id, $id
        ]);

        // 2. Sincronizar a usuarios si esta vinculado
        if (!empty($emp_actual['usuario_id'])) {
            sincronizar_a_usuarios($pdo, $emp_actual['usuario_id'], [
                'nombre_completo' => $nombre_completo,
                'no_nomina' => $no_nomina,
                'puesto' => $puesto,
                'departamento_id' => $departamento_id,
                'fecha_ingreso' => $fecha_ingreso,
                'periodo_pago' => $periodo_pago,
                'empresa' => $empresa,
                'jornada' => $jornada,
            ], $usuario_actual_id);
        }

        $pdo->commit();

        $sync_msg = !empty($emp_actual['usuario_id']) ? ' (sincronizado con su cuenta de plataforma)' : '';
        $_SESSION['alerta'] = ['tipo' => 'success', 'mensaje' => 'Empleado <strong>' . htmlspecialchars($nombre_completo) . '</strong> actualizado.' . $sync_msg];
        header('Location: ' . URL_BASE . 'dashboard/gth/empleados/listar_empleados.php'); exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error editar empleado: " . $e->getMessage());
        $_SESSION['form_errors_emp'] = ['Error: ' . $e->getMessage()];
        $_SESSION['form_data_emp'] = $_POST;
        header('Location: ' . URL_BASE . "dashboard/gth/empleados/editar_empleado.php?id=$id"); exit;
    }
}

else {
    header('Location: ' . URL_BASE . 'dashboard/gth/empleados/listar_empleados.php'); exit;
}