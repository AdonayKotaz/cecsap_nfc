<?php
session_start();
require_once './config/conexion.php';

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        $nombre === '' ||
        $apellido === '' ||
        $usuario === '' ||
        $password === ''
    ) {
        $error = 'Todos los campos son obligatorios.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO maestros
                    (nombre, apellido, usuario, password_hash, estado_id, activo)
                 VALUES (?, ?, ?, ?, 1, 1)'
            );

            $stmt->execute([
                $nombre,
                $apellido,
                $usuario,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            $exito = 'Docente registrado correctamente.';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'El nombre de usuario ya está registrado.';
            } else {
                error_log($e->getMessage());
                $error = 'Ocurrió un error al registrar al docente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro de docentes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h1 class="h3 mb-4">Registrar docente</h1>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger">
                                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($exito !== ''): ?>
                            <div class="alert alert-success">
                                <?= htmlspecialchars($exito, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" id="nombre" name="nombre"
                                       class="form-control" maxlength="100"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="apellido" class="form-label">Apellido</label>
                                <input type="text" id="apellido" name="apellido"
                                       class="form-control" maxlength="100"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="usuario" class="form-label">Usuario</label>
                                <input type="text" id="usuario" name="usuario"
                                       class="form-control" maxlength="50"
                                       autocomplete="username" required>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" id="password" name="password"
                                       class="form-control" minlength="8"
                                       autocomplete="new-password" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                Registrar docente
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>