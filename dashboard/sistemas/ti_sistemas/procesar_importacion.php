<?php
/**
 * Procesar Importación de Inventario desde Excel
 * dashboard/sistemas/ti_sistemas/procesar_importacion.php
 * 
 * Lee ambas hojas del Excel:
 *   - INVENTARIO_EQUIPOS_ELECTRONICOS → inventario_equipos
 *   - ACCESO_EQUIPOS → acceso_equipos
 * 
 * Comportamiento: UPSERT por hostname (actualiza si existe, inserta si no)
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Verificar sesión y permisos
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
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/importar_inventario.php');
    exit;
}

// Validar archivo subido
if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
    $errores_upload = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tama&ntilde;o m&aacute;ximo permitido por el servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tama&ntilde;o m&aacute;ximo permitido por el formulario.',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subi&oacute; parcialmente.',
        UPLOAD_ERR_NO_FILE    => 'No se seleccion&oacute; ning&uacute;n archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Error del servidor: falta carpeta temporal.',
        UPLOAD_ERR_CANT_WRITE => 'Error del servidor: no se pudo escribir el archivo.',
    ];
    $codigo_error = $_FILES['archivo_excel']['error'] ?? UPLOAD_ERR_NO_FILE;
    $mensaje = $errores_upload[$codigo_error] ?? 'Error desconocido al subir el archivo.';
    establecer_alerta('error', $mensaje);
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/importar_inventario.php');
    exit;
}

// Validar extensión
$nombre_archivo = $_FILES['archivo_excel']['name'];
$extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
if (!in_array($extension, ['xlsx', 'xls'])) {
    establecer_alerta('error', 'Solo se permiten archivos .xlsx o .xls');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/importar_inventario.php');
    exit;
}

$tmp_path = $_FILES['archivo_excel']['tmp_name'];

try {
    $pdo = conectarDB();
    
    // Cargar el archivo Excel
    $spreadsheet = IOFactory::load($tmp_path);
    $hojas_disponibles = $spreadsheet->getSheetNames();
    
    // Precargar mapa de departamentos (nombre => id)
    $stmt_deptos = $pdo->query("SELECT id, nombre, codigo FROM departamentos WHERE activo = 1");
    $deptos_rows = $stmt_deptos->fetchAll(PDO::FETCH_ASSOC);
    $mapa_deptos = [];
    foreach ($deptos_rows as $d) {
        $mapa_deptos[mb_strtoupper(trim($d['nombre']), 'UTF-8')] = $d['id'];
        if (!empty($d['codigo'])) {
            $mapa_deptos[mb_strtoupper(trim($d['codigo']), 'UTF-8')] = $d['id'];
        }
    }
    
    // Mapeo de tipos del Excel a ENUM de la BD
    $mapa_tipos = [
        'ALL IN ONE'   => 'all_in_one',
        'ALLINONE'     => 'all_in_one',
        'ALL-IN-ONE'   => 'all_in_one',
        'PC'           => 'pc',
        'LAPTOP'       => 'laptop',
        'MONITOR'      => 'monitor',
        'MOUSE'        => 'mouse',
        'TECLADO'      => 'teclado',
        'IMPRESORA'    => 'impresora',
        'ACCESS POINT' => 'access_point',
        'ACCESSPOINT'  => 'access_point',
        'SWITCH'       => 'switch',
        'CAMARA'       => 'camara',
        'CÁMARA'       => 'camara',
        'CELULAR'      => 'celular',
        'TELÉFONO'     => 'celular',
        'TELEFONO'     => 'celular',
    ];
    
    $resultados = [
        'inventario' => ['insertados' => 0, 'actualizados' => 0, 'errores' => [], 'omitidos' => 0],
        'acceso'     => ['insertados' => 0, 'actualizados' => 0, 'errores' => [], 'omitidos' => 0],
    ];
    
    $hojas_importar = $_POST['hojas'] ?? ['inventario', 'acceso'];
    
    // =========================================================
    // HOJA 1: INVENTARIO_EQUIPOS_ELECTRONICOS → inventario_equipos
    // =========================================================
    if (in_array('inventario', $hojas_importar)) {
        $nombre_hoja_inv = null;
        foreach ($hojas_disponibles as $h) {
            if (stripos($h, 'INVENTARIO') !== false) {
                $nombre_hoja_inv = $h;
                break;
            }
        }
        
        if ($nombre_hoja_inv) {
            $sheet = $spreadsheet->getSheetByName($nombre_hoja_inv);
            $rows = $sheet->toArray(null, true, true, true);
            
            // Detectar fila de encabezados
            $header_row = null;
            $col_map = [];
            foreach ($rows as $idx => $row) {
                $first_cell = mb_strtoupper(trim($row['A'] ?? ''), 'UTF-8');
                if ($first_cell === 'HOSTNAME' || stripos($first_cell, 'HOST') !== false) {
                    $header_row = $idx;
                    // Mapear columnas por nombre
                    foreach ($row as $col_letter => $val) {
                        $val_upper = mb_strtoupper(trim($val ?? ''), 'UTF-8');
                        switch (true) {
                            case $val_upper === 'HOSTNAME':       $col_map['hostname'] = $col_letter; break;
                            case $val_upper === 'TIPO':           $col_map['tipo'] = $col_letter; break;
                            case $val_upper === 'MARCA':          $col_map['marca'] = $col_letter; break;
                            case $val_upper === 'MODELO':         $col_map['modelo'] = $col_letter; break;
                            case strpos($val_upper, 'SERIE') !== false: $col_map['serie'] = $col_letter; break;
                            case strpos($val_upper, 'DEPARTAMENTO') !== false: $col_map['departamento'] = $col_letter; break;
                            case strpos($val_upper, 'UBICACION') !== false || strpos($val_upper, 'UBICACIÓN') !== false: 
                                $col_map['ubicacion'] = $col_letter; break;
                            case $val_upper === 'ESTADO':         $col_map['estado'] = $col_letter; break;
                            case strpos($val_upper, 'PERSONAL') !== false || strpos($val_upper, 'ASIGNA') !== false:
                                $col_map['personal_asignado'] = $col_letter; break;
                        }
                    }
                    break;
                }
            }
            
            if ($header_row && isset($col_map['hostname'])) {
                // Preparar statements
                $sql_check = "SELECT id FROM inventario_equipos WHERE hostname = ?";
                $stmt_check = $pdo->prepare($sql_check);
                
                $sql_insert = "INSERT INTO inventario_equipos 
                    (hostname, codigo_interno, tipo_equipo, marca, modelo, numero_serie, 
                     departamento_id, ubicacion, estado, personal_asignado, registrado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_insert = $pdo->prepare($sql_insert);
                
                $sql_update = "UPDATE inventario_equipos SET 
                    tipo_equipo = ?, marca = ?, modelo = ?, numero_serie = ?,
                    departamento_id = ?, ubicacion = ?, estado = ?, personal_asignado = ?,
                    fecha_actualizacion = NOW()
                    WHERE hostname = ?";
                $stmt_update = $pdo->prepare($sql_update);
                
                // Convertir a array indexado para poder acceder a la siguiente fila
                $rows_indexed = [];
                foreach ($rows as $idx => $row) {
                    $rows_indexed[$idx] = $row;
                }
                $row_keys = array_keys($rows_indexed);
                
                $filas_saltar = []; // filas ya consumidas como "continuación"
                
                foreach ($rows_indexed as $idx => $row) {
                    if ($idx <= $header_row) continue;
                    if (in_array($idx, $filas_saltar)) continue;
                    
                    $hostname = strtoupper(trim($row[$col_map['hostname']] ?? ''));
                    if (empty($hostname)) {
                        $resultados['inventario']['omitidos']++;
                        continue;
                    }
                    
                    // Extraer datos
                    $tipo_raw = mb_strtoupper(trim($row[$col_map['tipo'] ?? ''] ?? ''), 'UTF-8');
                    $tipo_equipo = $mapa_tipos[$tipo_raw] ?? null;
                    
                    // Si no hay tipo, verificar si la siguiente fila tiene datos sin hostname (fila dividida)
                    if (!$tipo_equipo) {
                        $pos_actual = array_search($idx, $row_keys);
                        if ($pos_actual !== false && isset($row_keys[$pos_actual + 1])) {
                            $next_idx = $row_keys[$pos_actual + 1];
                            $next_row = $rows_indexed[$next_idx];
                            $next_hostname = strtoupper(trim($next_row[$col_map['hostname']] ?? ''));
                            $next_tipo_raw = mb_strtoupper(trim($next_row[$col_map['tipo'] ?? ''] ?? ''), 'UTF-8');
                            
                            // Si la siguiente fila no tiene hostname pero sí tiene tipo → unir
                            if (empty($next_hostname) && !empty($next_tipo_raw) && isset($mapa_tipos[$next_tipo_raw])) {
                                $row = $next_row; // usar datos de la fila siguiente
                                $row[$col_map['hostname']] = $hostname; // conservar hostname de la fila actual
                                $tipo_raw = $next_tipo_raw;
                                $tipo_equipo = $mapa_tipos[$tipo_raw];
                                $filas_saltar[] = $next_idx; // no procesar la fila siguiente de nuevo
                            }
                        }
                    }
                    
                    if (!$tipo_equipo) {
                        $resultados['inventario']['errores'][] = "Fila {$idx}: Tipo '{$tipo_raw}' no reconocido para {$hostname}";
                        continue;
                    }
                    
                    $marca = trim($row[$col_map['marca'] ?? ''] ?? '');
                    $modelo = trim($row[$col_map['modelo'] ?? ''] ?? '');
                    $serie = trim($row[$col_map['serie'] ?? ''] ?? '');
                    $ubicacion = trim($row[$col_map['ubicacion'] ?? ''] ?? '') ?: 'Sin especificar';
                    $personal = strtoupper(trim($row[$col_map['personal_asignado'] ?? ''] ?? ''));
                    
                    // Estado
                    $estado_raw = mb_strtoupper(trim($row[$col_map['estado'] ?? ''] ?? ''), 'UTF-8');
                    $estado = 'activo';
                    if (in_array($estado_raw, ['INACTIVO', 'BAJA', 'DADO DE BAJA'])) {
                        $estado = ($estado_raw === 'INACTIVO') ? 'inactivo' : 'dado_de_baja';
                    }
                    
                    // Departamento
                    $depto_raw = mb_strtoupper(trim($row[$col_map['departamento'] ?? ''] ?? ''), 'UTF-8');
                    $departamento_id = $mapa_deptos[$depto_raw] ?? null;
                    
                    try {
                        // Verificar si ya existe
                        $stmt_check->execute([$hostname]);
                        $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existe) {
                            $stmt_update->execute([
                                $tipo_equipo, $marca ?: null, $modelo ?: null, $serie ?: null,
                                $departamento_id, $ubicacion, $estado, $personal ?: null,
                                $hostname
                            ]);
                            $resultados['inventario']['actualizados']++;
                        } else {
                            $stmt_insert->execute([
                                $hostname, $hostname, $tipo_equipo, $marca ?: null, $modelo ?: null, 
                                $serie ?: null, $departamento_id, $ubicacion, $estado, 
                                $personal ?: null, $_SESSION['usuario_id']
                            ]);
                            $resultados['inventario']['insertados']++;
                        }
                    } catch (PDOException $e) {
                        $resultados['inventario']['errores'][] = "Fila {$idx} ({$hostname}): " . $e->getMessage();
                    }
                }
            } else {
                $resultados['inventario']['errores'][] = 'No se encontr&oacute; la fila de encabezados en la hoja de inventario.';
            }
        } else {
            $resultados['inventario']['errores'][] = 'No se encontr&oacute; una hoja con nombre que contenga "INVENTARIO".';
        }
    }
    
    // =========================================================
    // HOJA 2: ACCESO_EQUIPOS → acceso_equipos
    // =========================================================
    if (in_array('acceso', $hojas_importar)) {
        $nombre_hoja_acc = null;
        foreach ($hojas_disponibles as $h) {
            if (stripos($h, 'ACCESO') !== false) {
                $nombre_hoja_acc = $h;
                break;
            }
        }
        
        if ($nombre_hoja_acc) {
            $sheet = $spreadsheet->getSheetByName($nombre_hoja_acc);
            $rows = $sheet->toArray(null, true, true, true);
            
            // Detectar encabezados
            $header_row = null;
            $col_map = [];
            foreach ($rows as $idx => $row) {
                // Buscar fila que contenga "HOSTNAME" o "No" como primer encabezado
                foreach ($row as $col_letter => $val) {
                    $val_upper = mb_strtoupper(trim($val ?? ''), 'UTF-8');
                    if ($val_upper === 'HOSTNAME') {
                        $col_map['hostname'] = $col_letter;
                    }
                }
                if (isset($col_map['hostname'])) {
                    $header_row = $idx;
                    foreach ($row as $col_letter => $val) {
                        $val_upper = mb_strtoupper(trim($val ?? ''), 'UTF-8');
                        switch (true) {
                            case $val_upper === 'NO' || $val_upper === 'N°': $col_map['no'] = $col_letter; break;
                            case strpos($val_upper, 'DEPARTAMENTO') !== false: $col_map['departamento'] = $col_letter; break;
                            case strpos($val_upper, 'IP') !== false || strpos($val_upper, 'DIRECCI') !== false: 
                                $col_map['ip'] = $col_letter; break;
                            case strpos($val_upper, 'CONTRA') !== false: $col_map['contrasena'] = $col_letter; break;
                            case strpos($val_upper, 'USUARIO') !== false && strpos($val_upper, 'ASIGNADO') !== false: 
                                $col_map['usuario_asignado'] = $col_letter; break;
                            case $val_upper === 'ESTADO': $col_map['estado'] = $col_letter; break;
                            case $val_upper === 'WHOAMI': $col_map['whoami'] = $col_letter; break;
                            case strpos($val_upper, 'COMENTARIO') !== false: $col_map['comentarios'] = $col_letter; break;
                        }
                    }
                    break;
                }
            }
            
            if ($header_row && isset($col_map['hostname'])) {
                $sql_check_acc = "SELECT id FROM acceso_equipos WHERE hostname = ?";
                $stmt_check_acc = $pdo->prepare($sql_check_acc);
                
                $sql_insert_acc = "INSERT INTO acceso_equipos 
                    (hostname, departamento_id, direccion_ip, contrasena_equipo, 
                     usuario_asignado_nombre, estado, whoami, comentarios, registrado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_insert_acc = $pdo->prepare($sql_insert_acc);
                
                $sql_update_acc = "UPDATE acceso_equipos SET 
                    departamento_id = ?, direccion_ip = ?, contrasena_equipo = ?,
                    usuario_asignado_nombre = ?, estado = ?, whoami = ?, comentarios = ?,
                    fecha_actualizacion = NOW()
                    WHERE hostname = ?";
                $stmt_update_acc = $pdo->prepare($sql_update_acc);
                
                foreach ($rows as $idx => $row) {
                    if ($idx <= $header_row) continue;
                    
                    $hostname = strtoupper(trim($row[$col_map['hostname']] ?? ''));
                    if (empty($hostname)) {
                        $resultados['acceso']['omitidos']++;
                        continue;
                    }
                    
                    $depto_raw = mb_strtoupper(trim($row[$col_map['departamento'] ?? ''] ?? ''), 'UTF-8');
                    $departamento_id = $mapa_deptos[$depto_raw] ?? null;
                    $ip = trim($row[$col_map['ip'] ?? ''] ?? '');
                    $contrasena = trim($row[$col_map['contrasena'] ?? ''] ?? '');
                    $usuario_nombre = trim($row[$col_map['usuario_asignado'] ?? ''] ?? '');
                    $whoami = trim($row[$col_map['whoami'] ?? ''] ?? '');
                    $comentarios = trim($row[$col_map['comentarios'] ?? ''] ?? '');
                    
                    $estado_raw = mb_strtoupper(trim($row[$col_map['estado'] ?? ''] ?? ''), 'UTF-8');
                    $estado = ($estado_raw === 'INACTIVO') ? 'inactivo' : 'activo';
                    
                    try {
                        $stmt_check_acc->execute([$hostname]);
                        $existe = $stmt_check_acc->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existe) {
                            $stmt_update_acc->execute([
                                $departamento_id, $ip ?: null, $contrasena ?: null,
                                $usuario_nombre ?: null, $estado, $whoami ?: null, 
                                $comentarios ?: null, $hostname
                            ]);
                            $resultados['acceso']['actualizados']++;
                        } else {
                            $stmt_insert_acc->execute([
                                $hostname, $departamento_id, $ip ?: null, $contrasena ?: null,
                                $usuario_nombre ?: null, $estado, $whoami ?: null, 
                                $comentarios ?: null, $_SESSION['usuario_id']
                            ]);
                            $resultados['acceso']['insertados']++;
                        }
                    } catch (PDOException $e) {
                        $resultados['acceso']['errores'][] = "Fila {$idx} ({$hostname}): " . $e->getMessage();
                    }
                }
            } else {
                $resultados['acceso']['errores'][] = 'No se encontr&oacute; la fila de encabezados en la hoja de acceso.';
            }
        } else {
            $resultados['acceso']['errores'][] = 'No se encontr&oacute; una hoja con nombre que contenga "ACCESO".';
        }
    }
    
    // Guardar resultados en sesión para mostrar en la vista
    $_SESSION['importacion_resultados'] = $resultados;
    establecer_alerta('success', 'Importaci&oacute;n completada exitosamente.');
    
} catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
    error_log("Error al leer Excel: " . $e->getMessage());
    establecer_alerta('error', 'Error al leer el archivo Excel: ' . $e->getMessage());
} catch (PDOException $e) {
    error_log("Error de BD en importación: " . $e->getMessage());
    establecer_alerta('error', 'Error de base de datos durante la importaci&oacute;n.');
} catch (Exception $e) {
    error_log("Error general en importación: " . $e->getMessage());
    establecer_alerta('error', 'Error inesperado: ' . $e->getMessage());
}

header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/importar_inventario.php');
exit;