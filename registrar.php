<?php
// registrar.php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/conexion.php';

$uid = trim($_POST['uid'] ?? '');
$hora = date('H:i:s');

if ($uid === '') {
    echo json_encode(['encontrado' => false, 'uid' => '', 'hora' => $hora]);
    exit;
}

// 1. Buscar estudiante por número de tarjeta NFC
$stmt = $pdo->prepare("SELECT e.id, e.nombre, e.apellido, e.foto_url, e.estado,
                        CONCAT(g.nombre, ' - ', s.nombre) AS seccion
                        FROM estudiantes e
                        INNER JOIN secciones s ON e.seccion_id = s.id
                        INNER JOIN grados g ON s.grado_id = g.id
                        WHERE e.numero_tarjeta = ?");
$stmt->execute([$uid]);
$estudiante = $stmt->fetch();

if (!$estudiante) {
    echo json_encode(['encontrado' => false, 'uid' => $uid, 'hora' => $hora]);
    exit;
}

// 2. Verificar si ya marcó hoy
$stmt = $pdo->prepare("SELECT id FROM asistencias
                        WHERE estudiante_id = ? AND fecha = CURDATE()");
$stmt->execute([$estudiante['id']]);
$duplicado = (bool) $stmt->fetch();

// 3. Insertar marcaje si no es duplicado
if (!$duplicado) {
    $stmt = $pdo->prepare("INSERT INTO asistencias (estudiante_id, fecha, hora_registro, estado)
                            VALUES (?, CURDATE(), CURTIME(), 'Presente')");
    $stmt->execute([$estudiante['id']]);
}

echo json_encode([
    'encontrado' => true,
    'duplicado'  => $duplicado,
    'nombre'     => $estudiante['nombre'],
    'apellido'   => $estudiante['apellido'],
    'seccion'    => $estudiante['seccion'],
    'foto_url'   => $estudiante['foto_url'],
    'hora'       => $hora,
]);
