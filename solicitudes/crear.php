<?php
session_start();
require_once '../config/database.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos obligatorios
        $errores = [];
        
        if (empty($_POST['nombre'])) $errores[] = "El nombre es obligatorio";
        if (empty($_POST['apellido'])) $errores[] = "El apellido es obligatorio";
        if (empty($_POST['departamento'])) $errores[] = "El departamento es obligatorio";
        if (empty($_POST['tipo_soporte'])) $errores[] = "El tipo de soporte es obligatorio";
        if (empty($_POST['descripcion'])) $errores[] = "La descripción es obligatoria";
        if (empty($_POST['prioridad'])) $errores[] = "La prioridad es obligatoria";
        
        // Validar campos condicionales
        if ($_POST['tipo_soporte'] == 'Apoyo' && empty($_POST['tipo_apoyo'])) {
            $errores[] = "Debe seleccionar el tipo de apoyo";
        }
        if ($_POST['tipo_soporte'] == 'Problema' && empty($_POST['tipo_problema'])) {
            $errores[] = "Debe seleccionar el tipo de problema";
        }
        
        if (empty($errores)) {
            // Generar folio único
            $folio = 'SOL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Preparar datos
            $nombre = $_POST['nombre'];
            $apellido = $_POST['apellido'];
            $departamento = $_POST['departamento'];
            $tipo_soporte = $_POST['tipo_soporte'];
            $tipo_apoyo = $_POST['tipo_apoyo'] ?? '';
            $tipo_problema = $_POST['tipo_problema'] ?? '';
            $descripcion = $_POST['descripcion'];
            $prioridad = $_POST['prioridad'];
            $usuario_id = $_SESSION['usuario_id'];
            
            if (empty($errores)) {
                // Insertar en la base de datos
                $sql = "INSERT INTO solicitudes (folio, nombre_solicitante, apellido_solicitante, departamento, 
                        tipo_soporte, tipo_apoyo, tipo_problema, descripcion, prioridad, 
                        usuario_id, estado, fecha_creacion) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssssi", 
                    $folio, $nombre, $apellido, $departamento, $tipo_soporte, 
                    $tipo_apoyo, $tipo_problema, $descripcion, $prioridad, $usuario_id
                );
                
                if ($stmt->execute()) {
                    $mensaje = "Solicitud creada exitosamente. Folio: <strong>$folio</strong>";
                    $tipo_mensaje = 'success';
                    
                    // Limpiar el formulario
                    $_POST = [];
                } else {
                    $errores[] = "Error al guardar la solicitud: " . $conn->error;
                }
                
                $stmt->close();
            }
        }
        
        if (!empty($errores)) {
            $mensaje = implode('<br>', $errores);
            $tipo_mensaje = 'danger';
        }
        
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Atención</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            margin: 20px auto;
            max-width: 800px;
        }
        .form-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }
        .form-header h2 {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            padding: 12px 40px;
            font-weight: 600;
        }
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        .conditional-field {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <!-- Header del formulario -->
            <div class="form-header">
                <h2><i class="fas fa-file-alt"></i> Solicitud de Atención</h2>
            </div>

            <!-- Mensajes de alerta -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form method="POST" id="solicitudForm">
                
                <!-- Nombre del solicitante -->
                <div class="mb-3">
                    <label class="form-label required">Nombre del solicitante</label>
                    <div class="row">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="nombre" 
                                   placeholder="First Name" 
                                   value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="apellido" 
                                   placeholder="Last Name" 
                                   value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Departamento -->
                <div class="mb-3">
                    <label for="departamento" class="form-label required">Departamento</label>
                    <input type="text" class="form-control" id="departamento" name="departamento" 
                           placeholder="Ej: Recursos Humanos, TI, Ventas, etc."
                           value="<?php echo htmlspecialchars($_POST['departamento'] ?? ''); ?>" required>
                </div>

                <!-- Tipo de soporte -->
                <div class="mb-3">
                    <label for="tipo_soporte" class="form-label required">Tipo de soporte</label>
                    <select class="form-select" id="tipo_soporte" name="tipo_soporte" required>
                        <option value="">Seleccione</option>
                        <option value="Apoyo" <?php echo (($_POST['tipo_soporte'] ?? '') == 'Apoyo') ? 'selected' : ''; ?>>
                            Apoyo
                        </option>
                        <option value="Problema" <?php echo (($_POST['tipo_soporte'] ?? '') == 'Problema') ? 'selected' : ''; ?>>
                            Problema
                        </option>
                    </select>
                </div>

                <!-- Tipo de apoyo (condicional) -->
                <div class="mb-3 conditional-field" id="tipo_apoyo_field">
                    <label for="tipo_apoyo" class="form-label required">Tipo de apoyo</label>
                    <select class="form-select" id="tipo_apoyo" name="tipo_apoyo">
                        <option value="">Seleccione</option>
                        <option value="Préstamo de Herramientas y/o insumos (pinzas, desarmadores, alcohol isopropílico, etc.)">Préstamo de Herramientas y/o insumos (pinzas, desarmadores, alcohol isopropílico, etc.)</option>
                        <option value="Instalación de Software (Autocad, PDF Reader, Zoom, LightShot, etc.)">Instalación de Software (Autocad, PDF Reader, Zoom, LightShot, etc.)</option>
                        <option value="Solicitud/Actualización de información">Solicitud/Actualización de información</option>
                        <option value="Instalación y/o Configuración de SUA">Instalación y/o Configuración de SUA</option>
                        <option value="Cambios AJUSTES predeterminados (Solicitud de Outlook, navegador, etc.)">Cambios AJUSTES predeterminados (Solicitud de Outlook, navegador, etc.)</option>
                        <option value="Edición y manipulación de Archivos">Edición y manipulación de Archivos</option>
                        <option value="Copia de Archivos en CD/DVD/USB">Copia de Archivos en CD/DVD/USB</option>
                        <option value="Revisión de contenido (enlaces, correos, archivos, etc.)">Revisión de contenido (enlaces, correos, archivos, etc.)</option>
                        <option value="Impresión a color">Impresión a color</option>
                        <option value="Reemplazo de Pilas">Reemplazo de Pilas</option>
                        <option value="Preparar sala de Juntas">Preparar sala de Juntas</option>
                        <option value="Activación OFFICE 365">Activación OFFICE 365</option>
                        <option value="Recuperación de correos - fuera de año en curso">Recuperación de correos - fuera de año en curso</option>
                        <option value="Asignación de Equipos (Teléfono Red, PC, Laptops, USB, etc.)">Asignación de Equipos (Teléfono Red, PC, Laptops, USB, etc.)</option>
                        <option value="Acceso a contenido web no visible">Acceso a contenido web no visible</option>
                        <option value="Acceso a carpetas compartidas en servidor">Acceso a carpetas compartidas en servidor</option>
                    </select>
                </div>

                <!-- Tipo de problema (condicional) -->
                <div class="mb-3 conditional-field" id="tipo_problema_field">
                    <label for="tipo_problema" class="form-label required">Tipo de problema</label>
                    <select class="form-select" id="tipo_problema" name="tipo_problema">
                        <option value="">Seleccione</option>
                        <option value="No puedo imprimir">No puedo imprimir</option>
                        <option value="Acceso a carpetas compartidas en servidor">Acceso a carpetas compartidas en servidor</option>
                        <option value="No puedo acceder al correo">No puedo acceder al correo</option>
                        <option value="Error de Red - Sin internet">Error de Red - Sin internet</option>
                        <option value="Cambio de toner">Cambio de toner</option>
                        <option value="Revisión de teléfono RED - Empresarial">Revisión de teléfono RED - Empresarial</option>
                        <option value="Error en archivo (Excel, Word, Power, PPF, etc)">Error en archivo (Excel, Word, Power, PPF, etc)</option>
                        <option value="Solicitud de Respaldo (Correos, PC, Dispositivos, etc.)">Solicitud de Respaldo (Correos, PC, Dispositivos, etc.)</option>
                        <option value="PC Bloqueada, error de acceso">PC Bloqueada, error de acceso</option>
                        <option value="Revisión de hardware (PC, cámaras CCTV, impresoras, accesorios)">Revisión de hardware (PC, cámaras CCTV, impresoras, accesorios)</option>
                    </select>
                </div>

                <!-- Descripción -->
                <div class="mb-3">
                    <label for="descripcion" class="form-label required">Descripción</label>
                    <input type="text" class="form-control" id="descripcion" name="descripcion" 
                           value="<?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?>" required>
                </div>

                <!-- Prioridad (Radio buttons) -->
                <div class="mb-4">
                    <label class="form-label required">Prioridad</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="prioridad" id="prioridad_critica" 
                               value="Crítica" <?php echo (($_POST['prioridad'] ?? '') == 'Crítica') ? 'checked' : ''; ?> required>
                        <label class="form-check-label" for="prioridad_critica">Crítica</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="prioridad" id="prioridad_alto" 
                               value="Alto" <?php echo (($_POST['prioridad'] ?? '') == 'Alto') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="prioridad_alto">Alto</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="prioridad" id="prioridad_medio" 
                               value="Medio" <?php echo (($_POST['prioridad'] ?? '') == 'Medio') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="prioridad_medio">Medio</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="prioridad" id="prioridad_bajo" 
                               value="Bajo" <?php echo (($_POST['prioridad'] ?? '') == 'Bajo') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="prioridad_bajo">Bajo</label>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="d-flex justify-content-between align-items-center">
                    <a href="../dashboard/colaborativo.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Enviar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Lógica condicional para mostrar/ocultar campos
        document.getElementById('tipo_soporte').addEventListener('change', function() {
            const tipoSoporte = this.value;
            const tipoApoyoField = document.getElementById('tipo_apoyo_field');
            const tipoProblemaField = document.getElementById('tipo_problema_field');
            const tipoApoyoSelect = document.getElementById('tipo_apoyo');
            const tipoProblemaSelect = document.getElementById('tipo_problema');
            
            // Ocultar ambos campos
            tipoApoyoField.style.display = 'none';
            tipoProblemaField.style.display = 'none';
            
            // Quitar required de ambos
            tipoApoyoSelect.removeAttribute('required');
            tipoProblemaSelect.removeAttribute('required');
            
            // Limpiar valores
            tipoApoyoSelect.value = '';
            tipoProblemaSelect.value = '';
            
            // Mostrar el campo correspondiente
            if (tipoSoporte === 'Apoyo') {
                tipoApoyoField.style.display = 'block';
                tipoApoyoSelect.setAttribute('required', 'required');
            } else if (tipoSoporte === 'Problema') {
                tipoProblemaField.style.display = 'block';
                tipoProblemaSelect.setAttribute('required', 'required');
            }
        });
        
        // Mostrar el campo correcto al cargar si hay valor seleccionado
        window.addEventListener('DOMContentLoaded', function() {
            const tipoSoporte = document.getElementById('tipo_soporte').value;
            if (tipoSoporte === 'Apoyo') {
                document.getElementById('tipo_apoyo_field').style.display = 'block';
                document.getElementById('tipo_apoyo').setAttribute('required', 'required');
            } else if (tipoSoporte === 'Problema') {
                document.getElementById('tipo_problema_field').style.display = 'block';
                document.getElementById('tipo_problema').setAttribute('required', 'required');
            }
        });
    </script>
</body>
</html>