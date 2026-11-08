<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador']);

    $mensajeUsuarios = $_SESSION['mensaje_usuarios'] ?? '';

    unset($_SESSION['mensaje_usuarios']);

    $busqueda = $_GET['buscar'] ?? '';
    $paginaRecibida = $_GET['pagina'] ?? '1';

    if (
        !is_string($busqueda)
        || !is_string($paginaRecibida)
    ) {
        http_response_code(400);

        exit('Los parametros de busqueda no son validos.');
    }

    $busqueda = trim($busqueda);

    if (strlen($busqueda) > 100) {
        http_response_code(400);

        exit('La busqueda es demasiado larga.');
    }

    $pagina = filter_var(
        $paginaRecibida,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
                'max_range' => 1000000,
            ],
        ]
    );

    if ($pagina === false) {
        http_response_code(400);

        exit('El numero de pagina no es valido.');
    }

    $porPagina = obtenerCantidadPorPagina(
        'sigel_usuarios_por_pagina'
    );

    $contar = $pdo->prepare(
        'SELECT COUNT(*)
         FROM usuarios
         WHERE eliminado_en IS NULL
           AND (
               nombre LIKE :buscar_nombre
               OR correo LIKE :buscar_correo
           )'
    );

    $contar->execute([
        ':buscar_nombre' => '%' . $busqueda . '%',
        ':buscar_correo' => '%' . $busqueda . '%',
    ]);

    $totalRegistros = (int) $contar->fetchColumn();

    $totalPaginas = max(
        1,
        (int) ceil($totalRegistros / $porPagina)
    );

    $pagina = min($pagina, $totalPaginas);

    $desplazamiento = ($pagina - 1) * $porPagina;

    $consulta = $pdo->prepare(
        'SELECT
            usuarios.id,
            usuarios.nombre,
            usuarios.correo,
            usuarios.activo,
            roles.nombre AS rol
         FROM usuarios
         INNER JOIN roles ON usuarios.rol_id = roles.id
         WHERE usuarios.eliminado_en IS NULL
           AND (
               usuarios.nombre LIKE :buscar_nombre
               OR usuarios.correo LIKE :buscar_correo
           )
         ORDER BY usuarios.id DESC
         LIMIT :limite OFFSET :desplazamiento'
    );

    $consulta->bindValue(
        ':buscar_nombre',
        '%' . $busqueda . '%',
        PDO::PARAM_STR
    );

    $consulta->bindValue(
        ':buscar_correo',
        '%' . $busqueda . '%',
        PDO::PARAM_STR
    );

    $consulta->bindValue(
        ':limite',
        $porPagina,
        PDO::PARAM_INT
    );

    $consulta->bindValue(
        ':desplazamiento',
        $desplazamiento,
        PDO::PARAM_INT
    );

    $consulta->execute();

    $usuarios = $consulta->fetchAll();

    $urlAnterior = '/pages/usuarios.php?' . http_build_query([
        'buscar' => $busqueda,
        'pagina' => $pagina - 1,
    ]);

    $urlSiguiente = '/pages/usuarios.php?' . http_build_query([
        'buscar' => $busqueda,
        'pagina' => $pagina + 1,
    ]);
} catch (Throwable $error) {
    error_log((string) $error);

    http_response_code(503);

    exit('El sistema no esta disponible en este momento.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Usuarios | SIGEL</title>

    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="sg-page-users">
    <header class="sg-header">
        <h1 class="sg-header-title">SIGEL</h1>

        <p class="sg-header-description">
            Gestión de préstamos de equipos de laboratorio
        </p>
    </header>

    <main class="sg-panel-main">
        <div class="sg-section-heading">
            <h2 class="sg-section-title">Usuarios</h2>

            <a class="sg-link sg-link-panel" href="/pages/panel.php">
                Volver al panel
            </a>
        </div>

        <p class="sg-user-create">
            <a
                class="sg-link sg-link-user-create"
                href="/pages/usuario-formulario.php"
            >
                Crear usuario
            </a>
        </p>

        <?php if ($mensajeUsuarios !== ''): ?>
            <p class="sg-form-success" role="status">
                <?= escapar($mensajeUsuarios) ?>
            </p>
        <?php endif; ?>

        <form
            class="sg-search-form"
            action="/pages/usuarios.php"
            method="get"
        >
            <div class="sg-field sg-field-search">
                <label
                    class="sg-label sg-label-search"
                    for="sg-user-search"
                >
                    Buscar por nombre o correo
                </label>

                <input
                    class="sg-input sg-input-search"
                    type="search"
                    id="sg-user-search"
                    name="buscar"
                    maxlength="100"
                    value="<?= escapar($busqueda) ?>"
                >
            </div>

            <button class="sg-button sg-button-search" type="submit">
                Buscar
            </button>

            <a class="sg-link sg-link-clear" href="/pages/usuarios.php">
                Limpiar
            </a>
        </form>

        <p class="sg-results-count">
            Registros encontrados: <?= escapar((string) $totalRegistros) ?>
        </p>

        <div class="sg-table-container">
            <table class="sg-table sg-table-users">
                <caption class="sg-table-caption">
                    Usuarios registrados
                </caption>

                <thead class="sg-table-head">
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>

                <tbody class="sg-table-body">
                    <?php if ($usuarios === []): ?>
                        <tr>
                            <td class="sg-table-empty" colspan="4">
                                No se encontraron usuarios.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $usuarioListado): ?>
                            <tr>
                                <td>
                                    <?= escapar($usuarioListado['nombre']) ?>
                                </td>

                                <td>
                                    <?= escapar($usuarioListado['correo']) ?>
                                </td>

                                <td>
                                    <?= escapar($usuarioListado['rol']) ?>
                                </td>

                                <td>
                                    <?= (int) $usuarioListado['activo'] === 1
                                        ? 'Activo'
                                        : 'Inactivo' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <nav class="sg-pagination" aria-label="Paginación de usuarios">
            <?php if ($pagina > 1): ?>
                <a
                    class="sg-link sg-pagination-previous"
                    href="<?= escapar($urlAnterior) ?>"
                >
                    Anterior
                </a>
            <?php endif; ?>

            <span class="sg-pagination-summary">
                Página <?= escapar((string) $pagina) ?>
                de <?= escapar((string) $totalPaginas) ?>
            </span>

            <?php if ($pagina < $totalPaginas): ?>
                <a
                    class="sg-link sg-pagination-next"
                    href="<?= escapar($urlSiguiente) ?>"
                >
                    Siguiente
                </a>
            <?php endif; ?>
        </nav>
    </main>
</body>
</html>