<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrarse</title>
  <link rel="stylesheet" href="../assets/css/Inicio_Registro.css">
  <style>
    /* Mensaje de error */
    .error-message {
      color: #dc2626;
      font-size: 14px;
      margin-top: 5px;
      display: none;
    }
    .error-message.active {
      display: block;
    }

    /* SOLUCIÓN: Campo especial para select sin label flotante */
    .field-select {
      height: auto;
      width: 100%;
      margin-top: 20px;
      position: relative;
    }

    .field-select label {
      display: block;
      color: #4158d0;
      font-weight: 500;
      font-size: 16px;
      margin-bottom: 8px;
      margin-left: 5px;
    }

    .field-select select {
      height: 50px;
      width: 100%;
      outline: none;
      font-size: 17px;
      padding-left: 20px;
      padding-right: 40px;
      border: 1px solid lightgrey;
      border-radius: 25px;
      background: #fff;
      transition: all 0.3s ease;
      cursor: pointer;
      color: #333;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23999' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 20px center;
    }

    .field-select select:focus {
      border-color: #4158d0;
    }

    .field-select select option {
      padding: 10px;
      color: #333;
    }

    .field-select select option:disabled {
      color: #999999;
    }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="navbar-container">
      <div class="navbar-logo">
        <img src="../assets/img/Logo.png" alt="GrupoVerden Logo">
      </div>
      <div class="navbar-buttons">
        <a href="../index.php" class="btn btn-primary">Inicio</a>
        <a href="InicioSesion.php" class="btn btn-primary">Iniciar Sesión</a>
      </div>
    </div>
  </nav>

  <div class="main-content">
    <div class="wrapper">
      <div class="title">Regístrate</div>
      <form action="procesar_registro.php" method="POST" id="formRegistro">

        <!-- Nombre completo -->
        <div class="field">
          <input type="text" name="nombre_completo" id="nombre_completo" required>
          <label>Nombre completo</label>
        </div>

        <!-- Select de Departamento - CON LABEL ESTÁTICO -->
        <div class="field-select">
          <label for="departamento">Departamento</label>
          <select id="departamento" name="departamento" required>
            <option value="" disabled selected>Seleccione un departamento</option>
            <option value="almacen_refacciones">Almacén de refacciones</option>
            <option value="almacen_residuos">Almacén de residuos</option>
            <option value="atencion_clientes">Atención a clientes</option>
            <option value="calidad">Calidad</option>
            <option value="construccion">Construcción</option>
            <option value="contabilidad">Contabilidad</option>
            <option value="gestion_talento">Gestión de talento humano</option>
            <option value="laboratorio">Laboratorio</option>
            <option value="logistica">Logística</option>
            <option value="mantenimiento">Mantenimiento</option>
            <option value="normatividad">Normatividad</option>
            <option value="ptar">PTAR</option>
            <option value="seguridad">Seguridad</option>
            <option value="sistemas">Sistemas</option>
            <option value="tesoreria">Tesorería</option>
            <option value="ventas">Ventas</option>
          </select>
        </div>

        <!-- Nombre de usuario -->
        <div class="field">
          <input type="text" name="usuario" id="usuario" required>
          <label>Nombre de usuario</label>
        </div>

        <!-- Contraseña -->
        <div class="field">
          <input type="password" name="password" id="password" required minlength="4">
          <label>Contraseña (mínimo 4 caracteres)</label>
        </div>

        <!-- Confirmar contraseña -->
        <div class="field">
          <input type="password" name="password_confirm" id="password_confirm" required>
          <label>Confirmar contraseña</label>
          <span class="error-message" id="passwordError">Las contraseñas no coinciden</span>
        </div>

        <!-- Botón registro -->
        <div class="field">
          <input type="submit" value="Registrarse">
        </div>
        
        <div class="signup-link">
          ¿Ya tienes cuenta? <a href="InicioSesion.php">Inicia sesión</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Validar que las contraseñas coincidan
    const form = document.getElementById('formRegistro');
    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');
    const passwordError = document.getElementById('passwordError');

    form.addEventListener('submit', function(e) {
      if (password.value !== passwordConfirm.value) {
        e.preventDefault();
        passwordError.classList.add('active');
        passwordConfirm.style.borderColor = '#dc2626';
      }
    });

    passwordConfirm.addEventListener('input', function() {
      if (password.value === passwordConfirm.value) {
        passwordError.classList.remove('active');
        passwordConfirm.style.borderColor = '#4158d0';
      } else {
        passwordError.classList.add('active');
        passwordConfirm.style.borderColor = '#dc2626';
      }
    });
  </script>
</body>
</html>