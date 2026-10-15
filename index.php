<?php
session_start();
unset($_SESSION['docente_id']);
unset($_SESSION['docente_nombre']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>CECSAP Sistema de Asistencia</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <?php include 'includes/sidebar.php'; ?>

  <main class="landing-wrapper">
    <div class="landing-grid">
      <div class="landing-left">
        <h1>CECSAP SISTEMA DE ASISTENCIA</h1>
        <p class="landing-date">
          <?php
          // Fecha actual en español
          $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
          $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
          echo $dias[date('w')] . ', ' . date('j') . ' de ' . $meses[date('n')] . ' de ' . date('Y');
          ?>
        </p>
        <a href="menu.php" class="btn-acceder">ACCEDER</a>
      </div>
      <div class="landing-right-logo">
        <img src="img/LogoCECSAP.png" alt="Logo Oficial CECSAP">
      </div>
    </div>
  </main>

  <script src="js/marcaje.js"></script>
</body>
</html>
