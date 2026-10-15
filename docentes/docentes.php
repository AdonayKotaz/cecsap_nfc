<?php
require_once '../includes/auth.php';
require_once '../config/conexion.php';

$nombreDocente = $_SESSION['docente_nombre'] ?? 'ADMINISTRADOR';

if (empty($_SESSION['csrf_docentes'])) {
    $_SESSION['csrf_docentes'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_docentes'];

// Procesar operaciones AJAX.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $token = $_POST['csrf_token'] ?? '';

        if (!is_string($token) || !hash_equals($csrf, $token)) {
            throw new RuntimeException('La sesión del formulario expiró. Recarga la página.');
        }

        $accion = $_POST['accion'] ?? '';

        if ($accion === 'listar') {
            $stmt = $pdo->query("
                SELECT
                *
                FROM maestros
                ORDER BY id DESC
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            exit;
        }

        if ($accion === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $usuario = trim($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($nombre === '' || $apellido === '' || $usuario === '' || $password === '') {
                throw new RuntimeException('Completa todos los campos obligatorios.');
            }

            if (mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100 || mb_strlen($usuario) > 50) {
                throw new RuntimeException('Uno de los campos supera la longitud permitida.');
            }

            if (strlen($password) < 8) {
                throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO maestros
                    (nombre, apellido, usuario, password_hash, estado_id, activo)
                VALUES (?, ?, ?, ?, 1, 1)
            ");

            $stmt->execute([
                $nombre,
                $apellido,
                $usuario,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Docente registrado correctamente.'
            ]);
            exit;
        }

        if ($accion === 'obtener') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('Identificador de docente inválido.');
            }

            $stmt = $pdo->prepare("
                SELECT id, nombre, apellido, usuario, estado_id, activo
                FROM maestros
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $maestro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$maestro) {
                throw new RuntimeException('El docente no existe.');
            }

            echo json_encode([
                'success' => true,
                'data' => $maestro
            ]);
            exit;
        }

        if ($accion === 'modificar') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $nombre = trim($_POST['nombre'] ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $usuario = trim($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';

            if (!$id || $id < 1) {
                throw new RuntimeException('Identificador de docente inválido.');
            }

            if ($nombre === '' || $apellido === '' || $usuario === '') {
                throw new RuntimeException('Nombre, apellido y usuario son obligatorios.');
            }

            if (mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100 || mb_strlen($usuario) > 50) {
                throw new RuntimeException('Uno de los campos supera la longitud permitida.');
            }

            if ($password !== '' && strlen($password) < 8) {
                throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
            }

            $stmt = $pdo->prepare("SELECT id FROM maestros WHERE id = ?");
            $stmt->execute([$id]);

            if (!$stmt->fetch()) {
                throw new RuntimeException('El docente no existe.');
            }

            if ($password !== '') {
                $stmt = $pdo->prepare("
                    UPDATE maestros
                    SET nombre = ?, apellido = ?, usuario = ?, password_hash = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $nombre,
                    $apellido,
                    $usuario,
                    password_hash($password, PASSWORD_DEFAULT),
                    $id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE maestros
                    SET nombre = ?, apellido = ?, usuario = ?
                    WHERE id = ?
                ");

                $stmt->execute([$nombre, $apellido, $usuario, $id]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Docente actualizado correctamente.'
            ]);
            exit;
        }

        if ($accion === 'desactivar') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('Identificador de docente inválido.');
            }

            $stmt = $pdo->prepare("
                UPDATE maestros
                SET estado_id = 2, activo = 0
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("SELECT id FROM maestros WHERE id = ?");
                $stmt->execute([$id]);

                if (!$stmt->fetch()) {
                    throw new RuntimeException('El docente no existe.');
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Docente desactivado correctamente.'
            ]);
            exit;
        }

        throw new RuntimeException('Operación no válida.');

    } catch (PDOException $e) {
        error_log($e->getMessage());

        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'El nombre de usuario ya está registrado.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la operación.'
            ]);
        }
        exit;

    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de docentes — CECSAP</title>

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

        .docentes-titulo {
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
            color: #fff;
            border-color: #294822;
        }

        .docente-avatar {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--verde-claro);
            color: var(--verde-cecsap);
            font-weight: 700;
            font-size: .9rem;
            flex-shrink: 0;
        }

        .badge-estado {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: .82rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-activo {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-inactivo {
            background: #ffebee;
            color: #c62828;
        }

        .tabla-docentes-contenedor {
            width: 100%;
        }

        #tablaDocentes td {
            vertical-align: middle;
        }

        #tablaDocentes .acciones {
            white-space: nowrap;
        }

        .modal-header-cecsap {
            background: var(--verde-cecsap);
            color: #fff;
        }

        .modal-header-cecsap .btn-close {
            filter: brightness(0) invert(1);
        }

        .form-label {
            font-weight: 600;
        }

        #tablaDocentes_wrapper .page-link {
            color: var(--verde-cecsap);
        }

        #tablaDocentes_wrapper .active > .page-link {
            background: var(--verde-cecsap);
            border-color: var(--verde-cecsap);
            color: #fff;
        }

        @media (max-width: 768px) {
            .docentes-acciones {
                width: 100%;
            }

            .docentes-acciones button {
                width: 100%;
            }

            .tabla-docentes-contenedor {
                overflow-x: auto;
            }
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
        <button id="btnMenu"
                class="btn-menu"
                aria-label="Abrir menú"
                style="background:none;border:none;cursor:pointer;padding:0;display:flex;align-items:center;">
            <img src="../img/iconos/menu-hamburguesa.png"
                 alt="Menú"
                 class="icon-hamburguesa">
        </button>
    </div>
</header>

<?php include '../includes/sidebar.php'; ?>

<main class="docente-container">
    <div class="docente-card">

        <div class="tabs-navegacion">
            <a href="admin.php" class="tab-btn">Estudiantes</a>
            <a href="reportes.php" class="tab-btn">Asistencias / Reportes</a>
            <a href="docentes.php" class="tab-btn active">Docentes</a>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h2 class="docentes-titulo mb-1">Gestión de docentes</h2>
                <p class="text-secondary mb-0">
                    Administra las cuentas y el acceso al portal docente.
                </p>
            </div>

            <div class="docentes-acciones">
                <button type="button" class="btn btn-cecsap"
                        onclick="abrirCrear()">
                    + Registrar docente
                </button>
            </div>
        </div>

        <div id="alertaOperacion" class="alert d-none" role="alert"></div>

        <div class="tabla-docentes-contenedor">
            <table id="tablaDocentes"
                   class="table table-hover table-striped align-middle w-100">
                <thead>
                    <tr>
                        <th>Docente</th>
                        <th>Usuario</th>
                        <th>Estado</th>
                        <th>Fecha de registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div>
</main>

<!-- Modal reutilizable: crear y modificar -->
<div class="modal fade" id="modalDocente" tabindex="-1"
     aria-labelledby="modalDocenteTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header modal-header-cecsap">
                <h5 class="modal-title" id="modalDocenteTitulo">
                    Registrar docente
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formDocente">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="accion" id="accionDocente" value="crear">
                    <input type="hidden" name="id" id="docenteId">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control"
                               id="nombre" name="nombre"
                               maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label for="apellido" class="form-label">Apellido</label>
                        <input type="text" class="form-control"
                               id="apellido" name="apellido"
                               maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control"
                               id="usuario" name="usuario"
                               maxlength="50" autocomplete="username" required>
                    </div>

                    <div class="mb-2">
                        <label for="password" class="form-label">
                            Contraseña
                        </label>
                        <input type="password" class="form-control"
                               id="password" name="password"
                               minlength="8" autocomplete="new-password">
                        <div class="form-text" id="ayudaPassword">
                            Mínimo 8 caracteres.
                        </div>
                    </div>

                    <div id="errorFormulario"
                         class="alert alert-danger d-none mt-3 mb-0"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-cecsap"
                            id="btnGuardarDocente">
                        Guardar docente
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- Modal de confirmación para desactivar -->
<div class="modal fade" id="modalDesactivar" tabindex="-1"
     aria-labelledby="modalDesactivarTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="modalDesactivarTitulo">
                    Desactivar docente
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <p class="mb-2">
                    ¿Estás seguro de que deseas desactivar esta cuenta?
                </p>
                <div class="fw-semibold" id="nombreDesactivar"></div>
                <p class="text-secondary small mt-3 mb-0">
                    El registro se conservará en la base de datos, pero la
                    cuenta dejará de estar activa.
                </p>
                <div id="errorDesactivar"
                     class="alert alert-danger d-none mt-3 mb-0"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-danger"
                        id="btnConfirmarDesactivar">
                    Sí, desactivar
                </button>
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

const modalDocenteElement = document.getElementById('modalDocente');
const modalDesactivarElement = document.getElementById('modalDesactivar');

const modalDocente = new bootstrap.Modal(modalDocenteElement);
const modalDesactivar = new bootstrap.Modal(modalDesactivarElement);

let docenteDesactivarId = null;
let tablaDocentes;

function escapar(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, caracter => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[caracter]);
}

function mostrarAlerta(mensaje, tipo = 'success') {
    const alerta = document.getElementById('alertaOperacion');
    alerta.className = `alert alert-${tipo}`;
    alerta.textContent = mensaje;

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function mostrarErrorFormulario(mensaje) {
    const error = document.getElementById('errorFormulario');
    error.textContent = mensaje;
    error.classList.remove('d-none');
}

function ocultarErrorFormulario() {
    const error = document.getElementById('errorFormulario');
    error.textContent = '';
    error.classList.add('d-none');
}

async function solicitar(datos) {
    const formulario = new FormData();

    Object.entries(datos).forEach(([clave, valor]) => {
        formulario.append(clave, valor ?? '');
    });

    formulario.append('csrf_token', CSRF_TOKEN);

    const respuesta = await fetch('docentes.php', {
        method: 'POST',
        body: formulario,
        credentials: 'same-origin'
    });

    const resultado = await respuesta.json();

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'No se pudo completar la operación.');
    }

    return resultado;
}

async function cargarDocentes() {
    const resultado = await solicitar({ accion: 'listar' });
    return resultado.data;
}

function nombreCompleto(docente) {
    return `${docente.nombre} ${docente.apellido}`;
}

function abrirCrear() {
    document.getElementById('formDocente').reset();
    document.getElementById('accionDocente').value = 'crear';
    document.getElementById('docenteId').value = '';
    document.getElementById('modalDocenteTitulo').textContent = 'Registrar docente';
    document.getElementById('btnGuardarDocente').textContent = 'Guardar docente';
    document.getElementById('password').required = true;
    document.getElementById('ayudaPassword').textContent = 'Mínimo 8 caracteres.';
    ocultarErrorFormulario();
    modalDocente.show();
}

async function abrirModificar(id) {
    document.getElementById('formDocente').reset();
    ocultarErrorFormulario();

    try {
        const resultado = await solicitar({
            accion: 'obtener',
            id: id
        });

        const docente = resultado.data;

        document.getElementById('accionDocente').value = 'modificar';
        document.getElementById('docenteId').value = docente.id;
        document.getElementById('nombre').value = docente.nombre;
        document.getElementById('apellido').value = docente.apellido;
        document.getElementById('usuario').value = docente.usuario;
        document.getElementById('password').required = false;

        document.getElementById('modalDocenteTitulo').textContent = 'Modificar docente';
        document.getElementById('btnGuardarDocente').textContent = 'Guardar cambios';
        document.getElementById('ayudaPassword').textContent =
            'Deja este campo vacío para conservar la contraseña actual.';

        modalDocente.show();
    } catch (error) {
        mostrarAlerta(error.message, 'danger');
    }
}

function confirmarDesactivar(id, nombre) {
    docenteDesactivarId = id;
    document.getElementById('nombreDesactivar').textContent = nombre;

    const error = document.getElementById('errorDesactivar');
    error.classList.add('d-none');
    error.textContent = '';

    document.getElementById('btnConfirmarDesactivar').disabled = false;
    document.getElementById('btnConfirmarDesactivar').textContent = 'Sí, desactivar';

    modalDesactivar.show();
}

document.getElementById('formDocente').addEventListener('submit', async function (evento) {
    evento.preventDefault();
    ocultarErrorFormulario();

    const boton = document.getElementById('btnGuardarDocente');
    const accion = document.getElementById('accionDocente').value;

    boton.disabled = true;
    boton.textContent = 'Guardando...';

    try {
        const datos = Object.fromEntries(new FormData(this).entries());
        const resultado = await solicitar(datos);

        modalDocente.hide();
        mostrarAlerta(resultado.message);
        tablaDocentes.ajax.reload(null, false);
    } catch (error) {
        mostrarErrorFormulario(error.message);
    } finally {
        boton.disabled = false;
        boton.textContent = accion === 'crear' ? 'Guardar docente' : 'Guardar cambios';
    }
});

document.getElementById('btnConfirmarDesactivar').addEventListener('click', async function () {
    if (!docenteDesactivarId) return;

    const boton = this;
    boton.disabled = true;
    boton.textContent = 'Desactivando...';

    const errorElemento = document.getElementById('errorDesactivar');

    try {
        const resultado = await solicitar({
            accion: 'desactivar',
            id: docenteDesactivarId
        });

        modalDesactivar.hide();
        mostrarAlerta(resultado.message);
        tablaDocentes.ajax.reload(null, false);
        docenteDesactivarId = null;
    } catch (error) {
        errorElemento.textContent = error.message;
        errorElemento.classList.remove('d-none');
    } finally {
        boton.disabled = false;
        boton.textContent = 'Sí, desactivar';
    }
});

$(function () {
    tablaDocentes = $('#tablaDocentes').DataTable({
        ajax: async function (data, callback) {
            try {
                const docentes = await cargarDocentes();
                callback({ data: docentes });
            } catch (error) {
                callback({ data: [] });
                mostrarAlerta(error.message, 'danger');
            }
        },

        columns: [
            {
                data: null,
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return `${row.nombre} ${row.apellido}`;
                    }

                    const iniciales =
                        (row.nombre.charAt(0) + row.apellido.charAt(0)).toUpperCase();

                    return `
                        <div class="d-flex align-items-center gap-2">
                            <span class="docente-avatar">${escapar(iniciales)}</span>
                            <span class="fw-semibold">${escapar(row.nombre)} ${escapar(row.apellido)}</span>
                        </div>
                    `;
                }
            },
            {
                data: 'usuario',
                render: function (data, type) {
                    return type === 'display'
                        ? `<code>${escapar(data)}</code>`
                        : data;
                }
            },
            {
                data: null,
                render: function (data, type, row) {
                    const activo = Number(row.estado_id) === 1 &&
                                   Number(row.activo) === 1;

                    if (type !== 'display') {
                        return activo ? 'Activo' : 'Inactivo';
                    }

                    return activo
                        ? '<span class="badge-estado badge-activo">Activo</span>'
                        : '<span class="badge-estado badge-inactivo">Inactivo</span>';
                }
            },
            {
                data: 'created_at',
                render: function (data, type) {
                    if (!data) return '';
                    if (type !== 'display') return data;

                    const fecha = new Date(String(data).replace(' ', 'T'));

                    return Number.isNaN(fecha.getTime())
                        ? escapar(data)
                        : escapar(fecha.toLocaleDateString('es-SV'));
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    if (type !== 'display') return '';

                    const id = Number(row.id);
                    const nombre = escapar(nombreCompleto(row));
                    const activo = Number(row.estado_id) === 1 &&
                                   Number(row.activo) === 1;

                    return `
                        <div class="acciones d-flex gap-1">
                            <button type="button"
                                class="btn btn-sm btn-outline-success"
                                title="Modificar docente"
                                onclick="abrirModificar(${id})">
                                Editar
                            </button>

                            ${activo ? `
                                <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    title="Desactivar docente"
                                    onclick="confirmarDesactivar(${id}, '${nombre.replace(/'/g, '&#039;')}')">
                                    Desactivar
                                </button>
                            ` : ''}
                        </div>
                    `;
                }
            }
        ],

        language: {
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ docentes',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ docentes',
            infoEmpty: 'No hay docentes registrados',
            infoFiltered: '(filtrado de _MAX_ docentes)',
            zeroRecords: 'No se encontraron coincidencias',
            emptyTable: 'No hay docentes registrados',
            loadingRecords: 'Cargando docentes...',
            processing: 'Procesando...',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        },

        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'asc']],
        autoWidth: false
    });
});
</script>

<script src="../js/marcaje.js"></script>

</body>
</html>