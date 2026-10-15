<?php
require_once '../includes/auth.php';
require_once '../config/conexion.php';

$nombreDocente = $_SESSION['docente_nombre'] ?? 'ADMINISTRADOR';

if (empty($_SESSION['csrf_estudiantes'])) {
    $_SESSION['csrf_estudiantes'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_estudiantes'];

function responderJSON(array $datos, int $codigo = 200): void {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function validarId($valor): int {
    $id = filter_var($valor, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        throw new RuntimeException('Identificador inválido.');
    }
    return $id;
}

function textoPost(string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

// Operaciones AJAX.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $token = $_POST['csrf_token'] ?? '';

        if (!is_string($token) || !hash_equals($csrf, $token)) {
            throw new RuntimeException('La sesión expiró. Recarga la página.');
        }

        $accion = textoPost('accion');

        if ($accion === 'listar') {
            $stmt = $pdo->query("
                SELECT
                    e.id,
                    e.nombre,
                    e.apellido,
                    e.nie,
                    e.foto_url,
                    e.card_uid,
                    e.estado_id,
                    e.grado_id,
                    g.nombre AS grado,
                    g.seccion,
                    es.nombre_estado
                FROM estudiantes e
                INNER JOIN grados g ON g.id = e.grado_id
                INNER JOIN estados es ON es.id = e.estado_id
                ORDER BY g.nombre, g.seccion, e.apellido, e.nombre
            ");

            responderJSON([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
        }

        if ($accion === 'obtener') {
            $id = validarId($_POST['id'] ?? null);

            $stmt = $pdo->prepare("
                SELECT id, nombre, apellido, nie, foto_url,
                       card_uid, grado_id, estado_id
                FROM estudiantes
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$estudiante) {
                throw new RuntimeException('El estudiante no existe.');
            }

            responderJSON([
                'success' => true,
                'data' => $estudiante
            ]);
        }

        if ($accion === 'crear' || $accion === 'modificar') {
            $id = null;

            if ($accion === 'modificar') {
                $id = validarId($_POST['id'] ?? null);
            }

            $nombre = textoPost('nombre');
            $apellido = textoPost('apellido');
            $nie = textoPost('nie');
            $cardUid = textoPost('card_uid');
            $fotoUrl = textoPost('foto_url');
            $gradoId = validarId($_POST['grado_id'] ?? null);

            if ($nombre === '' || $apellido === '') {
                throw new RuntimeException('Nombre y apellido son obligatorios.');
            }

            if (
                mb_strlen($nombre) > 100 ||
                mb_strlen($apellido) > 100 ||
                mb_strlen($nie) > 20 ||
                mb_strlen($cardUid) > 50 ||
                mb_strlen($fotoUrl) > 255
            ) {
                throw new RuntimeException('Uno de los campos supera la longitud permitida.');
            }

            if ($fotoUrl === '') {
                $fotoUrl = 'default.png';
            }

            // Comprobar que el grado exista y esté activo.
            $stmt = $pdo->prepare("
                SELECT id
                FROM grados
                WHERE id = ? AND estado_id = 1
                LIMIT 1
            ");
            $stmt->execute([$gradoId]);

            if (!$stmt->fetch()) {
                throw new RuntimeException('Selecciona un grado activo válido.');
            }

            // NIE único cuando se proporciona, sin asumir una restricción UNIQUE.
            if ($nie !== '') {
                $sqlNie = "
                    SELECT id
                    FROM estudiantes
                    WHERE nie = ?
                ";
                $paramsNie = [$nie];

                if ($id !== null) {
                    $sqlNie .= " AND id <> ?";
                    $paramsNie[] = $id;
                }

                $sqlNie .= " LIMIT 1";

                $stmt = $pdo->prepare($sqlNie);
                $stmt->execute($paramsNie);

                if ($stmt->fetch()) {
                    throw new RuntimeException('El NIE ya está registrado en otro estudiante.');
                }
            }

            // La tarjeta también debe ser única si se proporciona.
            if ($cardUid !== '') {
                $sqlCard = "
                    SELECT id
                    FROM estudiantes
                    WHERE card_uid = ?
                ";
                $paramsCard = [$cardUid];

                if ($id !== null) {
                    $sqlCard .= " AND id <> ?";
                    $paramsCard[] = $id;
                }

                $sqlCard .= " LIMIT 1";

                $stmt = $pdo->prepare($sqlCard);
                $stmt->execute($paramsCard);

                if ($stmt->fetch()) {
                    throw new RuntimeException('La tarjeta NFC ya está asignada a otro estudiante.');
                }
            }

            $nie = $nie === '' ? null : $nie;
            $cardUid = $cardUid === '' ? null : $cardUid;

            if ($accion === 'crear') {
                $stmt = $pdo->prepare("
                    INSERT INTO estudiantes
                        (nombre, apellido, grado_id, card_uid,
                         foto_url, estado_id, nie)
                    VALUES (?, ?, ?, ?, ?, 1, ?)
                ");

                $stmt->execute([
                    $nombre,
                    $apellido,
                    $gradoId,
                    $cardUid,
                    $fotoUrl,
                    $nie
                ]);

                responderJSON([
                    'success' => true,
                    'message' => 'Estudiante registrado correctamente.'
                ]);
            }

            // Comprobar que el estudiante todavía exista.
            $stmt = $pdo->prepare("SELECT id FROM estudiantes WHERE id = ?");
            $stmt->execute([$id]);

            if (!$stmt->fetch()) {
                throw new RuntimeException('El estudiante ya no existe.');
            }

            // No modificar estado_id: un estudiante inactivo permanece inactivo.
            $stmt = $pdo->prepare("
                UPDATE estudiantes
                SET nombre = ?,
                    apellido = ?,
                    grado_id = ?,
                    card_uid = ?,
                    foto_url = ?,
                    nie = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $nombre,
                $apellido,
                $gradoId,
                $cardUid,
                $fotoUrl,
                $nie,
                $id
            ]);

            responderJSON([
                'success' => true,
                'message' => 'Estudiante actualizado correctamente.'
            ]);
        }

        if ($accion === 'desactivar') {
            $id = validarId($_POST['id'] ?? null);

            $stmt = $pdo->prepare("
                UPDATE estudiantes
                SET estado_id = 2
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("SELECT id FROM estudiantes WHERE id = ?");
                $stmt->execute([$id]);

                if (!$stmt->fetch()) {
                    throw new RuntimeException('El estudiante no existe.');
                }
            }

            responderJSON([
                'success' => true,
                'message' => 'Estudiante desactivado correctamente.'
            ]);
        }

        throw new RuntimeException('Operación no válida.');

    } catch (PDOException $e) {
        error_log($e->getMessage());

        if ($e->getCode() === '23000') {
            responderJSON([
                'success' => false,
                'message' => 'Existe un dato duplicado o una referencia inválida.'
            ], 409);
        }

        responderJSON([
            'success' => false,
            'message' => 'Ocurrió un error al procesar la operación.'
        ], 500);

    } catch (Throwable $e) {
        responderJSON([
            'success' => false,
            'message' => $e->getMessage()
        ], 400);
    }
}

// Datos iniciales para los filtros y el modal.
$secciones = $pdo->query("
    SELECT id, nombre, seccion
    FROM grados
    WHERE estado_id = 1
    ORDER BY nombre, seccion
")->fetchAll(PDO::FETCH_ASSOC);

$seccionSeleccionada = $_GET['seccion'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal Docente — CECSAP</title>

    <link rel="stylesheet" href="../css/style.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdn.datatables.net/2.3.4/css/dataTables.bootstrap5.min.css">

    <style>
        :root {
            --verde-cecsap: #355E2B;
            --verde-claro: #e8f5e9;
        }

        .estudiantes-titulo {
            color: var(--verde-cecsap);
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        .btn-cecsap {
            background: var(--verde-cecsap);
            color: #fff;
            border-color: var(--verde-cecsap);
        }

        .btn-cecsap:hover {
            background: #294822;
            border-color: #294822;
            color: #fff;
        }

        .tabla-contenedor {
            width: 100%;
            overflow-x: auto;
        }

        #tablaEstudiantes {
            width: 100%;
        }

        #tablaEstudiantes td,
        #tablaEstudiantes th {
            vertical-align: middle;
        }

        .foto-estudiante {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .estado-etiqueta {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: .85rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .estado-activo {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .estado-inactivo {
            background: #ffebee;
            color: #c62828;
        }

        .filtro-seccion {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filtro-seccion label,
        .form-label {
            font-weight: 600;
        }

        .modal-header-cecsap {
            background: var(--verde-cecsap);
            color: #fff;
        }

        .modal-header-cecsap .btn-close {
            filter: brightness(0) invert(1);
        }

        #tablaEstudiantes_wrapper .page-link {
            color: var(--verde-cecsap);
        }

        #tablaEstudiantes_wrapper .active > .page-link {
            background: var(--verde-cecsap);
            border-color: var(--verde-cecsap);
            color: #fff;
        }

        #tablaEstudiantes_wrapper .dt-search input,
        #tablaEstudiantes_wrapper .dt-length select {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .acciones-estudiante {
            display: flex;
            gap: 5px;
            white-space: nowrap;
        }

        .foto-preview {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ddd;
        }
    </style>
</head>
<body>

<header class="app-header" style="background: #355E2B;">
    <div style="display:flex;align-items:center;gap:12px;">
        <img src="../img/LogoCECSAP.png"
             alt="Logo CECSAP"
             style="width:42px;height:auto;object-fit:contain;background:transparent;">

        <h1 class="app-title">
            PORTAL DOCENTE — <?= htmlspecialchars(strtoupper($nombreDocente), ENT_QUOTES, 'UTF-8') ?>
        </h1>
    </div>

    <div style="display:flex;align-items:center;">
        <button id="btnMenu" class="btn-menu" aria-label="Abrir menú"
                style="background:none;border:none;cursor:pointer;padding:0;display:flex;align-items:center;">
            <img src="../img/iconos/menu-hamburguesa.png"
                 alt="Menú" class="icon-hamburguesa">
        </button>
    </div>
</header>

<?php include '../includes/sidebar.php'; ?>

<main class="docente-container">
    <div class="docente-card">

        <div class="tabs-navegacion">
            <a href="admin.php" class="tab-btn active">Estudiantes</a>
            <a href="reportes.php" class="tab-btn">Asistencias / Reportes</a>
            <a href="docentes.php" class="tab-btn">Docentes</a>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h2 class="estudiantes-titulo mb-1">Gestión de estudiantes</h2>
                <p class="text-secondary mb-0">
                    Consulta, registra y actualiza los datos de los estudiantes.
                </p>
            </div>

            <button type="button" class="btn btn-cecsap"
                    onclick="abrirCrear()">
                + Registrar estudiante
            </button>
        </div>

        <div id="alertaOperacion" class="alert d-none" role="alert"></div>

        <div class="filtro-seccion">
            <label for="filtroSeccion">Filtrar por grado y sección:</label>

            <select id="filtroSeccion" class="form-select" style="max-width:350px;">
                <option value="">Todos los grados</option>
                <?php foreach ($secciones as $s): ?>
                    <?php $valor = $s['nombre'] . ' - ' . $s['seccion']; ?>
                    <option
                        value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $seccionSeleccionada === $valor ? 'selected' : '' ?>>
                        <?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="tabla-contenedor">
            <table id="tablaEstudiantes" class="table table-hover table-striped align-middle">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>NIE</th>
                        <th>Nombre y apellido</th>
                        <th>Grado y sección</th>
                        <th>Tarjeta NFC</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal reutilizable para crear y modificar -->
<div class="modal fade" id="modalEstudiante" tabindex="-1"
     aria-labelledby="modalEstudianteTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-cecsap">
                <h5 class="modal-title" id="modalEstudianteTitulo">
                    Registrar estudiante
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formEstudiante">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="accion" id="accionEstudiante" value="crear">
                    <input type="hidden" name="id" id="estudianteId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control"
                                   id="nombre" name="nombre"
                                   maxlength="100" required>
                        </div>

                        <div class="col-md-6">
                            <label for="apellido" class="form-label">Apellido</label>
                            <input type="text" class="form-control"
                                   id="apellido" name="apellido"
                                   maxlength="100" required>
                        </div>

                        <div class="col-md-6">
                            <label for="nie" class="form-label">NIE</label>
                            <input type="text" class="form-control"
                                   id="nie" name="nie" maxlength="20">
                            <div class="form-text">Opcional.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="grado_id" class="form-label">Grado y sección</label>
                            <select class="form-select" id="grado_id"
                                    name="grado_id" required>
                                <option value="">Selecciona un grado</option>
                                <?php foreach ($secciones as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>">
                                        <?= htmlspecialchars($s['nombre'] . ' - ' . $s['seccion'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="card_uid" class="form-label">Tarjeta NFC</label>
                            <input type="text" class="form-control"
                                   id="card_uid" name="card_uid" maxlength="50">
                            <div class="form-text">Déjalo vacío si aún no tiene tarjeta.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="foto_url" class="form-label">Ruta de la fotografía</label>
                            <input type="text" class="form-control"
                                   id="foto_url" name="foto_url"
                                   maxlength="255" value="default.png">
                            <div class="form-text">Ejemplo: estudiantes/imagen.jpg. No carga archivos.</div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex align-items-center gap-3">
                        <img id="fotoPreview"
                             class="foto-preview"
                             src="http://localhost:8000/assets/default.png"
                             alt="Vista previa">
                        <span class="text-secondary small">
                            Vista previa de la fotografía.
                        </span>
                    </div>

                    <div id="errorFormulario"
                         class="alert alert-danger d-none mt-3 mb-0"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-cecsap"
                            id="btnGuardarEstudiante">Guardar estudiante</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para confirmar desactivación -->
<div class="modal fade" id="modalDesactivar" tabindex="-1"
     aria-labelledby="modalDesactivarTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDesactivarTitulo">
                    Desactivar estudiante
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                ¿Deseas desactivar a este estudiante?
                <div id="nombreDesactivar" class="fw-semibold mt-2"></div>
                <p class="text-secondary small mt-3 mb-0">
                    El registro permanecerá en la base de datos.
                </p>
                <div id="errorDesactivar"
                     class="alert alert-danger d-none mt-3 mb-0"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger"
                        id="btnConfirmarDesactivar">Sí, desactivar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.4/js/dataTables.bootstrap5.min.js"></script>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
const URL_FOTOS = 'http://localhost:8000/assets/';

const modalEstudiante = new bootstrap.Modal(
    document.getElementById('modalEstudiante')
);

const modalDesactivar = new bootstrap.Modal(
    document.getElementById('modalDesactivar')
);

let tablaEstudiantes;
let estudianteDesactivarId = null;

function escapar(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[c]);
}

function mostrarAlerta(mensaje, tipo = 'success') {
    const alerta = document.getElementById('alertaOperacion');
    alerta.textContent = mensaje;
    alerta.className = `alert alert-${tipo}`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function solicitar(datos) {
    const formulario = new FormData();

    Object.entries(datos).forEach(([clave, valor]) => {
        formulario.append(clave, valor ?? '');
    });

    formulario.append('csrf_token', CSRF_TOKEN);

    const respuesta = await fetch('admin.php', {
        method: 'POST',
        body: formulario,
        credentials: 'same-origin'
    });

    const resultado = await respuesta.json();

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'No fue posible completar la operación.');
    }

    return resultado;
}

function urlFoto(foto) {
    const nombre = String(foto || 'default.png');

    // Esta aplicación espera rutas relativas dentro de /assets.
    // No aceptar URL externas en la vista previa.
    if (
        nombre.startsWith('/') ||
        nombre.includes('..') ||
        nombre.includes('://') ||
        nombre.includes('\\')
    ) {
        return URL_FOTOS + 'default.png';
    }

    return URL_FOTOS + nombre;
}

document.getElementById('foto_url').addEventListener('input', function () {
    document.getElementById('fotoPreview').src = urlFoto(this.value);
});

document.getElementById('fotoPreview').addEventListener('error', function () {
    this.onerror = null;
    this.src = URL_FOTOS + 'default.png';
});

function abrirCrear() {
    document.getElementById('formEstudiante').reset();
    document.getElementById('accionEstudiante').value = 'crear';
    document.getElementById('estudianteId').value = '';
    document.getElementById('foto_url').value = 'default.png';
    document.getElementById('fotoPreview').src = URL_FOTOS + 'default.png';
    document.getElementById('modalEstudianteTitulo').textContent = 'Registrar estudiante';
    document.getElementById('btnGuardarEstudiante').textContent = 'Guardar estudiante';
    document.getElementById('errorFormulario').classList.add('d-none');
    modalEstudiante.show();
}

async function abrirModificar(id) {
    try {
        const resultado = await solicitar({ accion: 'obtener', id });
        const e = resultado.data;

        document.getElementById('formEstudiante').reset();
        document.getElementById('accionEstudiante').value = 'modificar';
        document.getElementById('estudianteId').value = e.id;
        document.getElementById('nombre').value = e.nombre;
        document.getElementById('apellido').value = e.apellido;
        document.getElementById('nie').value = e.nie ?? '';
        document.getElementById('card_uid').value = e.card_uid ?? '';
        document.getElementById('grado_id').value = e.grado_id;
        document.getElementById('foto_url').value = e.foto_url || 'default.png';
        document.getElementById('fotoPreview').src = urlFoto(e.foto_url);
        document.getElementById('modalEstudianteTitulo').textContent = 'Modificar estudiante';
        document.getElementById('btnGuardarEstudiante').textContent = 'Guardar cambios';
        document.getElementById('errorFormulario').classList.add('d-none');

        modalEstudiante.show();
    } catch (error) {
        mostrarAlerta(error.message, 'danger');
    }
}

function confirmarDesactivar(id, nombre) {
    estudianteDesactivarId = id;
    document.getElementById('nombreDesactivar').textContent = nombre;
    document.getElementById('errorDesactivar').classList.add('d-none');

    const boton = document.getElementById('btnConfirmarDesactivar');
    boton.disabled = false;
    boton.textContent = 'Sí, desactivar';

    modalDesactivar.show();
}

document.getElementById('formEstudiante').addEventListener('submit', async function (evento) {
    evento.preventDefault();

    const boton = document.getElementById('btnGuardarEstudiante');
    const error = document.getElementById('errorFormulario');
    const accion = document.getElementById('accionEstudiante').value;

    error.classList.add('d-none');
    boton.disabled = true;
    boton.textContent = 'Guardando...';

    try {
        const datos = Object.fromEntries(new FormData(this).entries());
        const resultado = await solicitar(datos);

        modalEstudiante.hide();
        mostrarAlerta(resultado.message);
        tablaEstudiantes.ajax.reload(null, false);
    } catch (e) {
        error.textContent = e.message;
        error.classList.remove('d-none');
    } finally {
        boton.disabled = false;
        boton.textContent = accion === 'crear'
            ? 'Guardar estudiante'
            : 'Guardar cambios';
    }
});

document.getElementById('btnConfirmarDesactivar').addEventListener('click', async function () {
    if (!estudianteDesactivarId) return;

    const boton = this;
    const error = document.getElementById('errorDesactivar');

    boton.disabled = true;
    boton.textContent = 'Desactivando...';

    try {
        const resultado = await solicitar({
            accion: 'desactivar',
            id: estudianteDesactivarId
        });

        modalDesactivar.hide();
        mostrarAlerta(resultado.message);
        tablaEstudiantes.ajax.reload(null, false);
        estudianteDesactivarId = null;
    } catch (e) {
        error.textContent = e.message;
        error.classList.remove('d-none');
    } finally {
        boton.disabled = false;
        boton.textContent = 'Sí, desactivar';
    }
});

$(function () {
    tablaEstudiantes = $('#tablaEstudiantes').DataTable({
        ajax: async function (data, callback) {
            try {
                const resultado = await solicitar({ accion: 'listar' });
                callback({ data: resultado.data });
            } catch (error) {
                callback({ data: [] });
                mostrarAlerta(error.message, 'danger');
            }
        },

        columns: [
            {
                data: 'foto_url',
                orderable: false,
                searchable: false,
                render: function (foto, tipo) {
                    if (tipo !== 'display') return '';
                    return `<img class="foto-estudiante"
                        src="${escapar(urlFoto(foto))}"
                        alt="Fotografía"
                        loading="lazy"
                        onerror="this.onerror=null;this.src='${URL_FOTOS}default.png'">`;
                }
            },
            {
                data: 'nie',
                render: function (data, tipo) {
                    return tipo === 'display'
                        ? escapar(data || '')
                        : (data || '');
                }
            },
            {
                data: null,
                render: function (data, tipo, e) {
                    const nombre = `${e.nombre} ${e.apellido}`;
                    return tipo === 'display' ? escapar(nombre) : nombre;
                }
            },
            {
                data: null,
                render: function (data, tipo, e) {
                    const grado = `${e.grado} - ${e.seccion}`;
                    return tipo === 'display' ? escapar(grado) : grado;
                }
            },
            {
                data: 'card_uid',
                render: function (data, tipo) {
                    const tarjeta = data || '';
                    return tipo === 'display'
                        ? `<code>${escapar(tarjeta)}</code>`
                        : tarjeta;
                }
            },
            {
                data: null,
                render: function (data, tipo, e) {
                    const activo = Number(e.estado_id) === 1;
                    const estado = activo ? 'Activo' : 'Inactivo';

                    if (tipo !== 'display') return estado;

                    return activo
                        ? '<span class="estado-etiqueta estado-activo">Activo</span>'
                        : '<span class="estado-etiqueta estado-inactivo">Inactivo</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (data, tipo, e) {
                    if (tipo !== 'display') return '';

                    const id = Number(e.id);
                    const nombre = `${e.nombre} ${e.apellido}`;
                    const activo = Number(e.estado_id) === 1;

                    return `
                        <div class="acciones-estudiante">
                            <button type="button"
                                class="btn btn-sm btn-outline-success"
                                onclick="abrirModificar(${id})">
                                Editar
                            </button>
                            ${activo ? `
                                <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="confirmarDesactivar(${id}, ${JSON.stringify(nombre).replace(/</g, '\\u003c')})">
                                    Desactivar
                                </button>
                            ` : ''}
                        </div>
                    `;
                }
            }
        ],

        language: {
            decimal: ',',
            thousands: '.',
            processing: 'Procesando...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ estudiantes',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ estudiantes',
            infoEmpty: 'Mostrando 0 a 0 de 0 estudiantes',
            infoFiltered: '(filtrado de _MAX_ estudiantes en total)',
            loadingRecords: 'Cargando...',
            zeroRecords: 'No se encontraron estudiantes coincidentes',
            emptyTable: 'No hay estudiantes registrados',
            paginate: {
                first: 'Primero',
                previous: 'Anterior',
                next: 'Siguiente',
                last: 'Último'
            }
        },

        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[2, 'asc']],
        autoWidth: false
    });

    $('#filtroSeccion').on('change', function () {
        tablaEstudiantes.column(3).search(this.value).draw();
    });

    const filtroInicial = <?= json_encode($seccionSeleccionada, JSON_UNESCAPED_UNICODE) ?>;

    if (filtroInicial) {
        $('#filtroSeccion').val(filtroInicial);
        tablaEstudiantes.column(3).search(filtroInicial).draw();
    }
});
</script>

<script src="../js/marcaje.js"></script>

</body>
</html>