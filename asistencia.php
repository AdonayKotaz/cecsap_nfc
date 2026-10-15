<?php
session_start();
unset($_SESSION['docente_id']);
unset($_SESSION['docente_nombre']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Asistencia — CECSAP</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main class="marcaje-aula-wrapper">

    <!-- Input invisible que captura la lectura NFC -->
    <input type="text" id="nfcInput" class="nfc-input" autocomplete="off">

    <div id="panelEspera" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 0;">
      <img src="img/LogoCECSAP.png" alt="Escudo CECSAP" class="escudo-aula">
      <p class="texto-espera-aula">Esperando tarjeta...</p>
    </div>

    <section class="historial" style="max-width: 550px; width: 100%; margin: 20px auto 0 auto; text-align: left;">
      <h3 style="color: #355E2B; font-size: 1.2rem; margin-bottom: 12px; border-bottom: 2px solid #d4e0d6; padding-bottom: 6px;">Marcajes de hoy</h3>
      <ul id="listaHistorial" style="list-style: none; max-height: 250px; overflow-y: auto;"></ul>
    </section>

  </main>

  <script src="js/marcaje.js"></script>
</body>
</html>
