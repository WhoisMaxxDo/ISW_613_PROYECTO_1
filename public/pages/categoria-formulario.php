<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador']);

    $id = 0;

    $categoria = [
        'nombre' => '',
        'dias_max_prestamo' => '',
    ];

    if (isset($_GET['id'])) {
        if (!is_string($_GET['id'])) {
            http_response_code(400);

            exit('El identificador no es valido.');
        }

        $id = filter_var(
            $_GET['id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            http_response_code(400);

            exit('El identificador no es valido.');
        }

        $consulta = $pdo->prepare(
            'SELECT nombre, dias_max_prestamo
             FROM categorias
             WHERE id = :id
               AND eliminado_en IS NULL'
        );

        $consulta->execute([
            ':id' => $id,
        ]);

        $categoria = $consulta->fetch();

        if ($categoria === false) {
            http_response_code(404);

            exit('La categoria no existe.');
        }
    }

    $errorCategoria = $_SESSION['error_categoria'] ?? '';

    unset($_SESSION['error_categoria']);

    $titulo = $id === 0 ? 'Crear categoría' : 'Editar categoría';
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

    <title><?= escapar($titulo) ?> | SIGEL</title>

    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="sg-page-category-form">
    <header class="sg-header">
        <h1 class="sg-header-title">SIGEL</h1>

        <p class="sg-header-description">
            Gestión de préstamos de equipos de laboratorio
        </p>
    </header>

    <main class="sg-panel-main">
        <h2 class="sg-section-title">
            <?= escapar($titulo) ?>
        </h2>

        <?php if ($errorCategoria !== ''): ?>
            <p class="sg-form-error" role="alert">
                <?= escapar($errorCategoria) ?>
            </p>
        <?php endif; ?>

        <form
            class="sg-category-form"
            action="/actions/categoria-guardar.php"
            method="post"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar(obtenerTokenCsrf()) ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?= escapar((string) $id) ?>"
            >

            <div class="sg-field sg-field-category-name">
                <label
                    class="sg-label sg-label-category-name"
                    for="sg-category-name"
                >
                    Nombre
                </label>

                <input
                    class="sg-input sg-input-category-name"
                    type="text"
                    id="sg-category-name"
                    name="nombre"
                    maxlength="100"
                    value="<?= escapar($categoria['nombre']) ?>"
                    required
                >
            </div>

            <div class="sg-field sg-field-category-days">
                <label
                    class="sg-label sg-label-category-days"
                    for="sg-category-days"
                >
                    Máximo de días de préstamo
                </label>

                <input
                    class="sg-input sg-input-category-days"
                    type="number"
                    id="sg-category-days"
                    name="dias_max_prestamo"
                    min="1"
                    max="3650"
                    step="1"
                    value="<?= escapar(
                        (string) $categoria['dias_max_prestamo']
                    ) ?>"
                    required
                >
            </div>

            <div class="sg-form-actions">
                <button
                    class="sg-button sg-button-category-save"
                    type="submit"
                >
                    Guardar categoría
                </button>

                <a
                    class="sg-link sg-link-category-cancel"
                    href="/pages/categorias.php"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </main>
</body>
</html>