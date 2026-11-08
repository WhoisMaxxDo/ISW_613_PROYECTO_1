<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador', 'operador']);

    $esAdministrador = $usuario['rol'] === 'administrador';

    $mensaje = $_SESSION['mensaje_equipos'] ?? '';

    unset($_SESSION['mensaje_equipos']);

    $busqueda = $_GET['buscar'] ?? '';
    $paginaRecibida = $_GET['pagina'] ?? '1';

    if (
        !is_string($busqueda)
        || !is_string($paginaRecibida)
    ) {
        http_response_code(400);

        exit('Los parametros no son validos.');
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

        exit('La pagina no es valida.');
    }

    $porPagina = obtenerCantidadPorPagina(
        'sigel_equipos_por_pagina'
    );

    $contar = $pdo->prepare(
        'SELECT COUNT(*)
         FROM equipos
         WHERE eliminado_en IS NULL
           AND (
               nombre LIKE :nombre
               OR codigo LIKE :codigo
           )'
    );

    $contar->execute([
        ':nombre' => '%' . $busqueda . '%',
        ':codigo' => '%' . $busqueda . '%',
    ]);

    $totalRegistros = (int) $contar->fetchColumn();

    $totalPaginas = max(
        1,
        (int) ceil($totalRegistros / $porPagina)
    );

    $pagina = min($pagina, $totalPaginas);

    $consulta = $pdo->prepare(
        'SELECT
            equipos.id,
            equipos.codigo,
            equipos.nombre,
            equipos.estado,
            equipos.version,
            categorias.nombre AS categoria
         FROM equipos
         INNER JOIN categorias ON equipos.categoria_id = categorias.id
         WHERE equipos.eliminado_en IS NULL
           AND (
               equipos.nombre LIKE :nombre
               OR equipos.codigo LIKE :codigo
           )
         ORDER BY equipos.id DESC
         LIMIT :limite OFFSET :desplazamiento'
    );

    $consulta->bindValue(':nombre', '%' . $busqueda . '%', PDO::PARAM_STR);
    $consulta->bindValue(':codigo', '%' . $busqueda . '%', PDO::PARAM_STR);
    $consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);

    $consulta->bindValue(
        ':desplazamiento',
        ($pagina - 1) * $porPagina,
        PDO::PARAM_INT
    );

    $consulta->execute();

    $equipos = $consulta->fetchAll();

    $urlAnterior = '/pages/equipos.php?' . http_build_query([
        'buscar' => $busqueda,
        'pagina' => $pagina - 1,
    ]);

    $urlSiguiente = '/pages/equipos.php?' . http_build_query([
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

    <title>Equipos | SIGEL</title>

    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="sg-page-equipment">
    <header class="sg-header">
        <h1 class="sg-header-title">SIGEL</h1>
        <p class="sg-header-description">Equipos de laboratorio</p>
    </header>

    <main class="sg-panel-main">
        <div class="sg-section-heading">
            <h2 class="sg-section-title">Equipos</h2>

            <a class="sg-link" href="/pages/panel.php">
                Volver al panel
            </a>
        </div>

        <?php if ($esAdministrador): ?>
            <p>
                <a class="sg-link" href="/pages/equipo-formulario.php">
                    Crear equipo
                </a>
            </p>
        <?php endif; ?>

        <?php if ($mensaje !== ''): ?>
            <p class="sg-form-success" role="status">
                <?= escapar($mensaje) ?>
            </p>
        <?php endif; ?>

        <form class="sg-search-form" action="/pages/equipos.php" method="get">
            <div class="sg-field sg-field-search">
                <label class="sg-label" for="sg-equipment-search">
                    Buscar por nombre o código
                </label>

                <input
                    class="sg-input"
                    type="search"
                    id="sg-equipment-search"
                    name="buscar"
                    maxlength="100"
                    value="<?= escapar($busqueda) ?>"
                >
            </div>

            <button class="sg-button" type="submit">Buscar</button>

            <a class="sg-link" href="/pages/equipos.php">Limpiar</a>
        </form>

        <form
            class="sg-pagination-preference-form"
            action="/actions/equipo-opciones.php"
            method="post"
        >
            <input type="hidden" name="accion" value="paginacion">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar(obtenerTokenCsrf()) ?>"
            >

            <div class="sg-field sg-field-page-size">
                <label class="sg-label" for="sg-equipment-page-size">
                    Registros por página
                </label>

                <select
                    class="sg-input"
                    id="sg-equipment-page-size"
                    name="por_pagina"
                >
                    <?php foreach ([5, 10, 20, 50] as $cantidad): ?>
                        <option
                            value="<?= escapar((string) $cantidad) ?>"
                            <?= $cantidad === $porPagina ? 'selected' : '' ?>
                        >
                            <?= escapar((string) $cantidad) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="sg-button" type="submit">Aplicar</button>
        </form>

        <p class="sg-results-count">
            Registros encontrados: <?= escapar((string) $totalRegistros) ?>
        </p>

        <div class="sg-table-container">
            <table class="sg-table">
                <caption class="sg-table-caption">Inventario de equipos</caption>

                <thead class="sg-table-head">
                    <tr>
                        <th scope="col">Código</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Categoría</th>
                        <th scope="col">Estado</th>

                        <?php if ($esAdministrador): ?>
                            <th scope="col">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody class="sg-table-body">
                    <?php if ($equipos === []): ?>
                        <tr>
                            <td
                                class="sg-table-empty"
                                colspan="<?= $esAdministrador ? '5' : '4' ?>"
                            >
                                No se encontraron equipos.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($equipos as $equipo): ?>
                        <tr>
                            <td><?= escapar($equipo['codigo']) ?></td>
                            <td><?= escapar($equipo['nombre']) ?></td>
                            <td><?= escapar($equipo['categoria']) ?></td>
                            <td><?= escapar($equipo['estado']) ?></td>

                            <?php if ($esAdministrador): ?>
                                <td class="sg-equipment-actions">
                                    <a
                                        class="sg-link"
                                        href="<?= escapar(
                                            '/pages/equipo-formulario.php?'
                                            . http_build_query(['id' => $equipo['id']])
                                        ) ?>"
                                    >
                                        Editar
                                    </a>

                                    <form
                                        class="sg-equipment-delete-form"
                                        action="/actions/equipo-opciones.php"
                                        method="post"
                                    >
                                        <input type="hidden" name="accion" value="eliminar">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= escapar(obtenerTokenCsrf()) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= escapar((string) $equipo['id']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="version"
                                            value="<?= escapar((string) $equipo['version']) ?>"
                                        >

                                        <button
                                            class="sg-button sg-button-equipment-delete"
                                            type="submit"
                                        >
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <nav class="sg-pagination" aria-label="Paginación de equipos">
            <?php if ($pagina > 1): ?>
                <a class="sg-link" href="<?= escapar($urlAnterior) ?>">
                    Anterior
                </a>
            <?php endif; ?>

            <span class="sg-pagination-summary">
                Página <?= escapar((string) $pagina) ?>
                de <?= escapar((string) $totalPaginas) ?>
            </span>

            <?php if ($pagina < $totalPaginas): ?>
                <a class="sg-link" href="<?= escapar($urlSiguiente) ?>">
                    Siguiente
                </a>
            <?php endif; ?>
        </nav>
    </main>
</body>
</html>