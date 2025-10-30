<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión</title>
  <link rel="stylesheet" href="../assets/css/Inicio_Registro.css">
</head>
<body>
  <!-- Barra de navegación -->
  <nav class="navbar">
    <div class="navbar-container">
      <div class="navbar-logo">
        <img src="../assets/img/Logo.png" alt="GrupoVerden Logo">
      </div>
      <div class="navbar-buttons">
        <a href="../index.php" class="btn btn-primary">Inicio</a>
        <a href="Registrarse.php" class="btn btn-primary">Registrarse</a>
      </div>
    </div>
  </nav>

  <!-- Contenedor principal para el formulario -->
  <div class="main-content">
    <div class="wrapper">
      <div class="title">Inicia sesión</div>
      <form action="procesar_login.php" method="POST">
        
        <div class="field">
          <input type="text" name="usuario" id="usuario" required>
          <label>Nombre de usuario</label>
        </div>

        <div class="field">
          <input type="password" name="password" id="password" required>
          <label>Contraseña</label>
        </div>

        <div class="content">
          <div class="checkbox">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember">Recordar</label>
          </div>
          <a href="recuperar_password.php" class="pass-link">¿Olvidó su contraseña?</a>
        </div>

        <div class="field">
          <input type="submit" value="Ingresar">
        </div>

        <div class="signup-link">
          ¿No tienes cuenta? <a href="Registrarse.php">Regístrese Ahora</a>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/validaciones.js"></script>
</body>
</html>