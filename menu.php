<?php
session_start();
unset($_SESSION['docente_id']);
unset($_SESSION['docente_nombre']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Menú Principal — CECSAP</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <?php include 'includes/sidebar.php'; ?>

  <main class="menu-wrapper">
    <h2 style="color: #355E2B; font-size: 2rem; margin-bottom: 40px; font-weight: 800; text-align: center;">Seleccione una opción</h2>
    
    <div class="menu-cards-container">
      <a href="admin/login.php" class="menu-card">
        <div class="menu-card-icon">
          <img src="img/iconos/docente.png" alt="Portal Docente" class="menu-icon">
        </div>
        <h3>PORTAL DOCENTE</h3>
      </a>

      <a href="asistencia.php" class="menu-card">
        <div class="menu-card-icon">
          <img src="img/iconos/tarjeta.png" alt="Asistencia" class="menu-icon">
        </div>
        <h3>ASISTENCIA</h3>
      </a>
    </div>

    <div style="text-align: center; margin-top: 20px;">
      <a href="index.php" class="btn-salir">Salir / Inicio</a>
    </div>
  </main>

  <script src="js/marcaje.js"></script>
</body>
</html>
