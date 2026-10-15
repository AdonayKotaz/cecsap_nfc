<?php
require_once 'config/conexion.php';

$usuario = 'admin';
$password_plana = 'cecsap2026';
$hash_real = password_hash($password_plana, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE docentes SET password_hash = ? WHERE usuario = ?");
$exito = $stmt->execute([$hash_real, $usuario]);

if ($exito) {
    echo "<h1>¡Contraseña actualizada con éxito!</h1>";
    echo "<p>Usuario: <b>admin</b></p>";
    echo "<p>Contraseña: <b>cecsap2026</b></p>";
    echo "<a href='admin/login.php'>Ir al Login</a>";
} else {
    echo "<h1>Error al actualizar</h1>";
}
?>