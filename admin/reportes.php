<?php
require_once '../includes/auth.php';
require_once '../config/conexion.php';

$nombreDocente = $_SESSION['docente_nombre'] ?? 'ADMINISTRADOR';

$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('monday this week'));
$fechaFin    = $_GET['fecha_fin'] ?? date('Y-m-d');
$seccion     = $_GET['seccion'] ?? '';

$secciones = $pdo->query("SELECT DISTINCT CONCAT(g.nombre, ' - ', s.nombre) AS seccion 
                          FROM secciones s 
                          INNER JOIN grados g ON s.grado_id = g.id 
                          ORDER BY seccion")->fetchAll();

// Estudiantes de la sección (o todos)
$sqlEst = "SELECT 
            e.id, 
            e.nombre, 
            e.apellido, 
            CONCAT(g.nombre, ' - ', s.nombre) AS seccion
        FROM estudiantes e
        INNER JOIN secciones s ON e.seccion_id = s.id
        INNER JOIN grados g ON s.grado_id = g.id";

$params = [];
if ($seccion !== '') { 
    $sqlEst .= " WHERE CONCAT(g.nombre, ' - ', s.nombre) = ?"; 
    $params[] = $seccion; 
}
$sqlEst .= " ORDER BY g.nombre, s.nombre, e.apellido, e.nombre";

$stmt = $pdo->prepare($sqlEst);
$stmt->execute($params);
$estudiantes = $stmt->fetchAll();

// Asistencias del rango, agrupadas por estudiante y fecha
$stmt = $pdo->prepare("SELECT estudiante_id, fecha, hora_registro, estado
                        FROM asistencias
                        WHERE fecha BETWEEN ? AND ?");
$stmt->execute([$fechaInicio, $fechaFin]);
$marcajes = $stmt->fetchAll();

// Indexar marcajes por estudiante_id => [fechas]
$marcajesPorEstudiante = [];
foreach ($marcajes as $m) {
    $marcajesPorEstudiante[$m['estudiante_id']][] = $m['fecha'];
}

// Generar lista de días (lunes a viernes) dentro del rango
$dias = [];
$actual = new DateTime($fechaInicio);
$fin = new DateTime($fechaFin);
while ($actual <= $fin) {
    if ((int)$actual->format('N') <= 5) $dias[] = $actual->format('Y-m-d');
    $actual->modify('+1 day');
}

// Exportar CSV
if (isset($_GET['exportar'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inasistencias_' . $fechaInicio . '_a_' . $fechaFin . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Estudiante', 'Sección', 'Fecha', 'Estado']);
    foreach ($estudiantes as $e) {
        foreach ($dias as $dia) {
            $presente = in_array($dia, $marcajesPorEstudiante[$e['id']] ?? []);
            if (!$presente) {
                fputcsv($out, [$e['nombre'] . ' ' . $e['apellido'], $e['seccion'], $dia, 'Inasistencia']);
            }
        }
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reportes de Asistencia — CECSAP</title>
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
        <a href="admin.php" class="tab-btn">Estudiantes</a>
        <a href="reportes.php" class="tab-btn active">Asistencias / Reportes</a>
      </div>

      <h2 style="color: #355E2B; margin-bottom: 20px; font-size: 1.5rem;">Reporte de Asistencia / Inasistencias (para SIGES)</h2>

      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:24px; background: #f9fbf9; padding: 16px; border-radius: 8px; border: 1px solid #e0e6df;">
        <div>
          <label style="font-weight: 600; color: #333;">Desde</label><br>
          <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
        </div>
        <div>
          <label style="font-weight: 600; color: #333;">Hasta</label><br>
          <input type="date" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>">
        </div>
        <div>
          <label style="font-weight: 600; color: #333;">Sección</label><br>
          <select name="seccion">
            <option value="">Todas</option>
            <?php foreach ($secciones as $s): ?>
              <option value="<?= htmlspecialchars($s['seccion']) ?>" <?= $seccion === $s['seccion'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['seccion']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" style="background: #355E2B;">Consultar</button>
        <a href="?fecha_inicio=<?= $fechaInicio ?>&fecha_fin=<?= $fechaFin ?>&seccion=<?= urlencode($seccion) ?>&exportar=1" style="text-decoration:none;">
          <button type="button" style="background: #2e7d32;">Exportar Inasistencias (CSV)</button>
        </a>
      </form>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Estudiante</th><th>Sección</th>
              <?php foreach ($dias as $dia): ?><th><?= date('d/m', strtotime($dia)) ?></th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($estudiantes)): ?>
              <tr><td colspan="<?= 2 + count($dias) ?>" style="text-align:center; padding: 20px;">No hay registros para mostrar.</td></tr>
            <?php else: ?>
              <?php foreach ($estudiantes as $e): ?>
              <tr>
                <td><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?></td>
                <td><?= htmlspecialchars($e['seccion']) ?></td>
                <?php foreach ($dias as $dia):
                  $presente = in_array($dia, $marcajesPorEstudiante[$e['id']] ?? []); ?>
                  <td style="text-align:center; background:<?= $presente ? '#e8f5e9' : '#ffebee' ?>;">
                    <?php if ($presente): ?>
                      <img src="../img/iconos/Asistencia.png" alt="Presente" style="width: 18px; height: 18px; vertical-align: middle;">
                    <?php else: ?>
                      <img src="../img/iconos/cruz.png" alt="Inasistencia" style="width: 18px; height: 18px; vertical-align: middle;">
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
  <script src="../js/marcaje.js"></script>
</body>
</html>
