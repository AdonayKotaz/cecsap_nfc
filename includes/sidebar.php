<!-- includes/sidebar.php -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base_path_sb = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : '';
$base_path_docentes = (strpos($_SERVER['PHP_SELF'], '/docentes/') !== false) ? '../' : '';
?>
<nav id="sidebar" class="sidebar">
  <button id="btnCerrarMenu" class="btn-cerrar">&times;</button>
  <div style="text-align: center; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 2px solid #e8f5e9;">
    <img src="<?= $base_path_sb ?>img/LogoCECSAP.png" alt="Logo CECSAP" style="width: 75px; height: auto; object-fit: contain; background: transparent; margin-bottom: 6px;">
    <h3 style="color: #355E2B; margin-top: 4px; font-size: 1.15rem; font-weight: 700;">CECSAP</h3>
  </div>
  <ul>
    <li><a href="<?= $base_path_sb ?>index.php">Inicio / Landing</a></li>
    <li><a href="<?= $base_path_sb ?>menu.php">Menú Principal</a></li>
    <li><a href="<?= $base_path_sb ?>asistencia.php">Marcaje NFC</a></li>
    <li><a href="<?= $base_path_docentes ?>docentes.php">Docentes</a></li>
  </ul>
</nav>
<div id="overlay" class="overlay"></div>
