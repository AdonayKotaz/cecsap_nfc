<!-- includes/header.php -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : '';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<header class="app-header">
  <div style="display: flex; align-items: center; gap: 12px;">
    <img src="<?= $base_path ?>img/LogoCECSAP.png" alt="Logo CECSAP" style="width: 42px; height: auto; object-fit: contain; background: transparent;">
    <h1 class="app-title">
      <?php
      if ($current_page === 'admin.php' || $current_page === 'reportes.php') {
          $docenteName = isset($_SESSION['docente_nombre']) ? strtoupper($_SESSION['docente_nombre']) : 'ADMINISTRADOR';
          echo 'PORTAL DOCENTE — ' . htmlspecialchars($docenteName);
      } elseif ($current_page === 'asistencia.php') {
          echo 'REGISTRO DE ASISTENCIA';
      } else {
          echo 'CECSAP Asistencia';
      }
      ?>
    </h1>
  </div>
  <div style="display: flex; align-items: center;">
    <button id="btnMenu" class="btn-menu" aria-label="Abrir menú" style="background:none; border:none; cursor:pointer; padding:0; display:flex; align-items:center;">
      <img src="<?= $base_path ?>img/iconos/menu-hamburguesa.png" alt="Menú" class="icon-hamburguesa">
    </button>
  </div>
</header>
