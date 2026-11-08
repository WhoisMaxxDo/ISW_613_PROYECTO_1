<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador']);

    $id = 0;

    $equipo = [
        'categoria_id' => '',
        'codigo' => '',
        'nombre' => '',
        'estado' => 'disponible',
        'version' => 0,
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
            'SELECT categoria_id, codigo, nombre, estado, version
             FROM equipos
             WHERE id = :id
               AND eliminado_en IS NULL'
        );

        $consulta->execute([
            ':id' => $id,
        ]);

        $equipo = $consulta->fetch();

        if ($equipo === false) {
            http_response_code(404);

            exit('El equipo no existe.');
        }
    }

    $categorias = $pdo->query(
        'SELECT id, nombre
         FROM categorias
         WHERE eliminado_en IS NULL
         ORDER BY nombre'
    )->fetchAll();

    $titulo = $id === 0 ? 'Crear equipo' : 'Editar equipo';
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
<body class="sg-page-equipment-form">
    <header class="sg-header">
        <h1 class="sg-header-title">SIGEL</h1>
        <p class="sg-header-description">Equipos de laboratorio</p>
    </header>

    <main class="sg-panel-main">
        <h2 class="sg-section-title"><?= escapar($titulo) ?></h2>

        <form
            class="sg-equipment-form"
            action="/actions/equipo-opciones.php"
            method="post"
        >
            <input type="hidden" name="accion" value="guardar">

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

            <input
                type="hidden"
                name="version"
                value="<?= escapar((string) $equipo['version']) ?>"
            >

            <div class="sg-field sg-field-equipment-code">
                <label class="sg-label" for="sg-equipment-code">
                    Código
                </label>

                <input
                    class="sg-input"
                    type="text"
                    id="sg-equipment-code"
                    name="codigo"
                    maxlength="50"
                    pattern="[A-Za-z0-9_-]{1,50}"
                    value="<?= escapar($equipo['codigo']) ?>"
                    required
                >
            </div>

            <div class="sg-field sg-field-equipment-name">
                <label class="sg-label" for="sg-equipment-name">
                    Nombre
                </label>

                <input
                    class="sg-input"
                    type="text"
                    id="sg-equipment-name"
                    name="nombre"
                    maxlength="150"
                    value="<?= escapar($equipo['nombre']) ?>"
                    required
                >
            </div>

            <div class="sg-field sg-field-equipment-category">
                <label class="sg-label" for="sg-equipment-category">
                    Categoría
                </label>

                <select
                    class="sg-input"
                    id="sg-equipment-category"
                    name="categoria_id"
                    required
                >
                    <option value="">Seleccionar categoría</option>

                    <?php foreach ($categorias as $categoria): ?>
                        <option
                            value="<?= escapar((string) $categoria['id']) ?>"
                            <?= (string) $categoria['id']
                                === (string) $equipo['categoria_id']
                                    ? 'selected'
                                    : '' ?>
                        >
                            <?= escapar($categoria['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sg-field sg-field-equipment-status">
                <label class="sg-label" for="sg-equipment-status">
                    Estado
                </label>

                <select
                    class="sg-input"
                    id="sg-equipment-status"
                    name="estado"
                    required
                >
                    <?php foreach (['disponible', 'mantenimiento', 'baja'] as $estado): ?>
                        <option
                            value="<?= escapar($estado) ?>"
                            <?= $estado === $equipo['estado']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= escapar(ucfirst($estado)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sg-form-actions">
                <button class="sg-button" type="submit">
                    Guardar equipo
                </button>

                <a class="sg-link" href="/pages/equipos.php">
                    Cancelar
                </a>
            </div>
        </form>
    </main>
</body>
</html>