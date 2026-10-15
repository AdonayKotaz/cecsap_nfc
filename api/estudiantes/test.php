<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once '../../config/conexion.php';

function responderJson(int $codigoEstado, array $respuesta): void {
    http_response_code($codigoEstado);
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(405, ["error" => "Método no permitido. Usa POST."]);
}

$datos = json_decode(file_get_contents("php://input"), true) ?? [];

$cardUid   = trim($datos['card_uid'] ?? '');
$fechaHora = trim($datos['fecha_hora'] ?? '');

if (empty($cardUid) || empty($fechaHora)) {
    responderJson(400, ["error" => "Se requieren 'card_uid' y 'fecha_hora'."]);
}

// Desglosar fecha y hora
$fecha = date('Y-m-d', strtotime($fechaHora));
$hora  = date('H:i:s', strtotime($fechaHora));

// =========================================================================
// PASO 1: Comprobar a quién le pertenece la tarjeta
// =========================================================================
$stmt = $pdo->prepare("SELECT id, nombre, apellido, grado_id, estado_id FROM estudiantes WHERE card_uid = ? LIMIT 1");
$stmt->execute([$cardUid]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    responderJson(404, ["error" => "Tarjeta no asignada a ningún estudiante."]);
}

if ((int)$estudiante['estado_id'] !== 1) {
    responderJson(403, ["error" => "El estudiante se encuentra inactivo."]);
}

$estudianteId   = $estudiante['id'];
$nombreCompleto = $estudiante['nombre'] . " " . $estudiante['apellido'];

// =========================================================================
// PASO 2: Verificar en la tabla 'asistencias' los registros de hoy
// =========================================================================
$stmtAsistencia = $pdo->prepare("
    SELECT id, hora_registro, estado_id 
    FROM asistencias 
    WHERE estudiante_id = ? AND fecha = ?
");
$stmtAsistencia->execute([$estudianteId, $fecha]);
$asistenciasHoy = $stmtAsistencia->fetchAll(PDO::FETCH_ASSOC);

$totalRegistros = count($asistenciasHoy);

// =========================================================================
// PASO 3: Consultar horario oficial y aplicar lógica de marcaje
// =========================================================================
$numeroDia = (int)date('N', strtotime($fechaHora));

$stmtHorario = $pdo->prepare("
    SELECT id, h_entrada, h_salida 
    FROM horarios 
    WHERE id_grado = ? AND dia = ? AND estado_id = 1 
    LIMIT 1
");
$stmtHorario->execute([$estudiante['grado_id'], $numeroDia]);
$horario = $stmtHorario->fetch(PDO::FETCH_ASSOC);

$horarioId          = $horario['id'] ?? null;
$horaOficialEntrada = $horario['h_entrada'] ?? '06:30:00';

if ($totalRegistros === 0) {
    // CASO A: Registrar ENTRADA
    $estadoAsistencia = ($hora <= $horaOficialEntrada) ? 3 : 4; // 3 = Presente, 4 = Llegada Tarde

    $stmtInsert = $pdo->prepare("
        INSERT INTO asistencias (estudiante_id, horario_id, fecha, hora_registro, estado_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([$estudianteId, $horarioId, $fecha, $hora, $estadoAsistencia]);

    responderJson(201, [
        "cod" => 1,
        "estudiante" => $nombreCompleto,
        "tipo_registro" => "Entrada",
        "hora_marcada" => $hora,
        "hora_limite_entrada" => $horaOficialEntrada,
        "estado_id" => $estadoAsistencia,
        "mensaje" => ($estadoAsistencia === 3) ? "Entrada registrada a tiempo." : "Entrada registrada como LLEGADA TARDE."
    ]);

} elseif ($totalRegistros === 1) {
    
    // CASO B: Registrar SALIDA (Insertar nueva fila con la hora de salida)
   
    $stmtInsert = $pdo->prepare("
        INSERT INTO asistencias (estudiante_id, horario_id, fecha, hora_registro, estado_id) 
        VALUES (?, ?, ?, ?, 3)
    ");
    $stmtInsert->execute([$estudianteId, $horarioId, $fecha, $hora]);

    responderJson(200, [
        "cod" => 2,
        "estudiante" => $nombreCompleto,
        "tipo_registro" => "Salida",
        "hora_marcada" => $hora,
        "mensaje" => "Salida registrada correctamente."
    ]);
}

 else {
    // CASO C: Límite Alcanzado
    responderJson(400, [
        "error" => "Límite de marcajes alcanzado.",
        "estudiante" => $nombreCompleto,
        "mensaje" => "El estudiante ya registró su Entrada y Salida para el día de hoy."
    ]);
}