<?php
session_start();
require_once '../config/conexion.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM maestros WHERE usuario = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$usuario]);
    $maestro = $stmt->fetch();

    if ($maestro && password_verify($password, $maestro['password_hash'])) {
        $_SESSION['docente_id'] = $maestro['id'];
        $_SESSION['docente_nombre'] = $maestro['nombre'] . ' ' . $maestro['apellido'];
        header('Location: admin.php');
        exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Accede al Portal — CECSAP</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="login-page-body">
  <div class="login-card-container">
    
    <!-- Columna Izquierda: Formulario -->
    <div class="login-form-col">
      <h2>ACCEDE AL PORTAL</h2>
      <p class="login-subtitle">Acceso exclusivo para docentes y administradores del sistema de asistencia CECSAP</p>
      
      <?php if ($error): ?>
        <p style="color: #c62828; font-weight: 600; font-size: 0.95rem; margin-bottom: 5px;"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="POST">
        <div style="display: flex; flex-direction: column; gap: 15px;">
          <input type="text" name="usuario" class="login-input" placeholder="NOMBRE DE USUARIO..." required autocomplete="username">
          <input type="password" name="password" class="login-input" placeholder="CONTRASEÑA..." required autocomplete="current-password">
          <button type="submit" class="login-btn">ACCEDER</button>
        </div>
      </form>

      <div style="margin-top: 10px;">
        <a href="../menu.php" style="color: #355E2B; text-decoration: none; font-weight: 600; font-size: 0.95rem;">&larr; Volver al Menú Principal</a>
      </div>
    </div>

    <!-- Columna Derecha: Imagen -->
    <div class="login-img-col">
      <img src="../img/AulaFoto.jpg" alt="Aula CECSAP">
    </div>

  </div>
</body>
</html>
