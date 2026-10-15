<?php
require_once '../includes/auth.php';
require_once '../config/conexion.php';

$nombreDocente = $_SESSION['docente_nombre'] ?? 'ADMINISTRADOR';

$secciones = $pdo->query("SELECT DISTINCT CONCAT(g.nombre, ' - ', s.nombre) AS seccion 
                          FROM secciones s 
                          INNER JOIN grados g ON s.grado_id = g.id 
                          ORDER BY seccion")->fetchAll();
$seccionSeleccionada = $_GET['seccion'] ?? '';

$sql = "SELECT 
            e.id, 
            e.nie, 
            e.nombre, 
            e.apellido, 
            e.numero_tarjeta, 
            e.foto_url, 
            e.estado,
            CONCAT(g.nombre, ' - ', s.nombre) AS seccion
        FROM estudiantes e
        INNER JOIN secciones s ON e.seccion_id = s.id
        INNER JOIN grados g ON s.grado_id = g.id";

$params = [];
if ($seccionSeleccionada !== '') {
    $sql .= " WHERE CONCAT(g.nombre, ' - ', s.nombre) = ?";
    $params[] = $seccionSeleccionada;
}
$sql .= " ORDER BY g.nombre, s.nombre, e.apellido, e.nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$estudiantes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Portal Docente — CECSAP</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <header class="app-header" style="background: #355E2B;">
    <div style="display: flex; align-items: center; gap: 12px;">
      <img src="../img/LogoCECSAP.png" alt="Logo CECSAP" style="width: 42px; height: auto; object-fit: contain; background: transparent;">
      <h1 class="app-title">PORTAL DOCENTE — <?= htmlspecialchars(strtoupper($nombreDocente)) ?></h1>
    </div>
    <div style="display: flex; align-items: center;">
      <button id="btnMenu" class="btn-menu" aria-label="Abrir menú" style="background:none; border:none; cursor:pointer; padding:0; display:flex; align-items:center;">
        <img src="../img/iconos/menu-hamburguesa.png" alt="Menú" class="icon-hamburguesa">
      </button>
    </div>
  </header>
  <?php include '../includes/sidebar.php'; ?>

  <main class="docente-container">
    <div class="docente-card">
      <div class="tabs-navegacion">
        <a href="admin.php" class="tab-btn active">Estudiantes</a>
        <a href="reportes.php" class="tab-btn">Asistencias / Reportes</a>
      </div>

      <h2 style="color: #355E2B; margin-bottom: 20px; font-size: 1.5rem;">Listas de Estudiantes</h2>

      <form method="GET" style="margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
        <label for="seccion" style="font-weight: 600; color: #333;">Filtrar por sección:</label>
        <select name="seccion" id="seccion" onchange="this.form.submit()">
          <option value="">Todas</option>
          <?php foreach ($secciones as $s): ?>
            <option value="<?= htmlspecialchars($s['seccion']) ?>" <?= $seccionSeleccionada === $s['seccion'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['seccion']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <table>
        <thead>
          <tr><th>Foto</th><th>NIE</th><th>Nombre y Apellido</th><th>Sección</th><th>Tarjeta NFC</th><th>Estado</th></tr>
        </thead>
        <tbody>
          <?php if (empty($estudiantes)): ?>
            <tr><td colspan="6" style="text-align:center; padding: 20px;">No se encontraron estudiantes.</td></tr>
          <?php else: ?>
            <?php foreach ($estudiantes as $e): ?>
            <tr>
              <td><img src="<?= htmlspecialchars($e['foto_url'] ? '../img/estudiantes/' . $e['foto_url'] : '../img/default.png') ?>" width="40" height="40" style="border-radius:50%; object-fit:cover;"></td>
              <td><?= htmlspecialchars($e['nie'] ?? '') ?></td>
              <td><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?></td>
              <td><?= htmlspecialchars($e['seccion']) ?></td>
              <td><code><?= htmlspecialchars($e['numero_tarjeta'] ?? '') ?></code></td>
              <td><span style="padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; background: <?= $e['estado'] === 'Activo' ? '#e8f5e9' : '#ffebee' ?>; color: <?= $e['estado'] === 'Activo' ? '#2e7d32' : '#c62828' ?>;"><?= htmlspecialchars($e['estado']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
  <script src="../js/marcaje.js"></script>
</body>
</html>
