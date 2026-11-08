<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador']);

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

    $porPagina = 10;

    $contar = $pdo->prepare(
        'SELECT COUNT(*)
        FROM categorias
        WHERE eliminado_en IS NULL
            AND nombre LIKE :busqueda'
    );

    $contar->execute([
        ':busqueda' => '%' . $busqueda . '%',
    ]);

    $totalRegistros = (int) $contar->fetchColumn();

    $totalPaginas = max(
        1,
        (int) ceil($totalRegistros / $porPagina)
    );

    $pagina = min($pagina, $totalPaginas);

    $desplazamiento = ($pagina - 1) * $porPagina;

    $consulta = $pdo->prepare(
        'SELECT id, nombre, dias_max_prestamo
         FROM categorias
         WHERE eliminado_en IS NULL
           AND nombre LIKE :busqueda
         ORDER BY id DESC
         LIMIT :limite OFFSET :desplazamiento'
    );

    $consulta->bindValue(
        ':busqueda',
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

    $categorias = $consulta->fetchAll();

    $urlAnterior = '/pages/categorias.php?' . http_build_query([
        'buscar' => $busqueda,
        'pagina' => $pagina - 1,
    ]);

    $urlSiguiente = '/pages/categorias.php?' . http_build_query([
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

    <title>Categorías | SIGEL</title>

    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="sg-page-categories">
    <header class="sg-header">
        <h1 class="sg-header-title">SIGEL</h1>

        <p class="sg-header-description">
            Gestión de préstamos de equipos de laboratorio
        </p>
    </header>

    <main class="sg-panel-main">
        <div class="sg-section-heading">
            <h2 class="sg-section-title">Categorías</h2>

            <a class="sg-link sg-link-panel" href="/pages/panel.php">
                Volver al panel
            </a>
        </div>

        <form
            class="sg-search-form"
            action="/pages/categorias.php"
            method="get"
        >
            <div class="sg-field sg-field-search">
                <label
                    class="sg-label sg-label-search"
                    for="sg-category-search"
                >
                    Buscar por nombre
                </label>

                <input
                    class="sg-input sg-input-search"
                    type="search"
                    id="sg-category-search"
                    name="buscar"
                    maxlength="100"
                    value="<?= escapar($busqueda) ?>"
                >
            </div>

            <button class="sg-button sg-button-search" type="submit">
                Buscar
            </button>

            <a class="sg-link sg-link-clear" href="/pages/categorias.php">
                Limpiar
            </a>
        </form>

        <p class="sg-results-count">
            Registros encontrados: <?= escapar((string) $totalRegistros) ?>
        </p>

        <div class="sg-table-container">
            <table class="sg-table sg-table-categories">
                <caption class="sg-table-caption">
                    Categorías registradas
                </caption>

                <thead class="sg-table-head">
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Máximo de días de préstamo</th>
                    </tr>
                </thead>

                <tbody class="sg-table-body">
                    <?php if ($categorias === []): ?>
                        <tr>
                            <td class="sg-table-empty" colspan="3">
                                No se encontraron categorías.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categorias as $categoria): ?>
                            <tr>
                                <td>
                                    <?= escapar($categoria['nombre']) ?>
                                </td>

                                <td>
                                    <?= escapar(
                                        (string) $categoria['dias_max_prestamo']
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <nav class="sg-pagination" aria-label="Paginación de categorías">
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