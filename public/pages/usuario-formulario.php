<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador']);

    $id = 0;

    $datosUsuario = [
        'nombre' => '',
        'correo' => '',
        'rol_id' => '',
        'activo' => 1,
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

        $consultaUsuario = $pdo->prepare(
            'SELECT nombre, correo, rol_id, activo
             FROM usuarios
             WHERE id = :id
               AND eliminado_en IS NULL'
        );

        $consultaUsuario->execute([
            ':id' => $id,
        ]);

        $datosUsuario = $consultaUsuario->fetch();

        if ($datosUsuario === false) {
            http_response_code(404);

            exit('El usuario no existe.');
        }
    }

    $consulta = $pdo->query(
        "SELECT id, nombre
         FROM roles
         WHERE nombre IN ('administrador', 'operador')
         ORDER BY nombre"
    );

    $roles = $consulta->fetchAll();

    $errorUsuario = $_SESSION['error_usuario'] ?? '';

    unset($_SESSION['error_usuario']);

    $titulo = $id === 0 ? 'Crear usuario' : 'Editar usuario';

    $accion = $id === 0
        ? '/actions/usuario-crear.php'
        : '/actions/usuario-actualizar.php';
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
<body class="sg-page-user-form">
    <header class="sg-header">
        <h1 class="sg-header-title">SIGEL</h1>

        <p class="sg-header-description">
            Gestión de préstamos de equipos de laboratorio
        </p>
    </header>

    <main class="sg-panel-main">
        <h2 class="sg-section-title"><?= escapar($titulo) ?></h2>

        <?php if ($errorUsuario !== ''): ?>
            <p class="sg-form-error" role="alert">
                <?= escapar($errorUsuario) ?>
            </p>
        <?php endif; ?>

        <form
            class="sg-user-form"
            action="<?= escapar($accion) ?>"
            method="post"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar(obtenerTokenCsrf()) ?>">

            <input
                type="hidden"
                name="id"
                value="<?= escapar((string) $id) ?>">

            <div class="sg-field sg-field-user-name">
                <label
                    class="sg-label sg-label-user-name"
                    for="sg-user-name"
                >
                    Nombre
                </label>

                <input
                    class="sg-input sg-input-user-name"
                    type="text"
                    id="sg-user-name"
                    name="nombre"
                    maxlength="100"
                    autocomplete="name"
                    value="<?= escapar($datosUsuario['nombre']) ?>"
                    required
                >
            </div>

            <div class="sg-field sg-field-user-email">
                <label
                    class="sg-label sg-label-user-email"
                    for="sg-user-email"
                >
                    Correo electrónico
                </label>

                <input
                    class="sg-input sg-input-user-email"
                    type="email"
                    id="sg-user-email"
                    name="correo"
                    maxlength="150"
                    autocomplete="off"
                    value="<?= escapar($datosUsuario['correo']) ?>"
                    required
                >
            </div>

            <div class="sg-field sg-field-user-password">
                <label
                    class="sg-label sg-label-user-password"
                    for="sg-user-password"
                >
                    Contraseña
                </label>

                <input
                    class="sg-input sg-input-user-password"
                    type="password"
                    id="sg-user-password"
                    name="password"
                    minlength="8"
                    maxlength="72"
                    autocomplete="new-password"
                    <?= $id === 0 ? 'required' : '' ?>
                >

                <p class="sg-field-description">
                    <?php if ($id === 0): ?>
                        Entre 8 y 72 bytes.
                    <?php else: ?>
                        Dejar vacía para conservar la contraseña actual.
                        Una nueva contraseña debe tener entre 8 y 72 bytes.
                    <?php endif; ?>
                </p>
            </div>

            <div class="sg-field sg-field-user-role">
                <label
                    class="sg-label sg-label-user-role"
                    for="sg-user-role"
                >
                    Rol
                </label>

                <select
                    class="sg-input sg-select-user-role"
                    id="sg-user-role"
                    name="rol_id"
                    required
                >
                    <option value="">Seleccionar rol</option>

                    <?php foreach ($roles as $rol): ?>
                        <option
                            value="<?= escapar((string) $rol['id']) ?>"
                            <?= (string) $rol['id'] === (string) $datosUsuario['rol_id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= escapar($rol['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($id > 0): ?>
                <div class="sg-field sg-field-user-status">
                    <label
                        class="sg-label sg-label-user-status"
                        for="sg-user-status"
                    >
                        Estado
                    </label>

                    <select
                        class="sg-input sg-select-user-status"
                        id="sg-user-status"
                        name="activo"
                        required
                    >
                        <option
                            value="1"
                            <?= (int) $datosUsuario['activo'] === 1
                                ? 'selected'
                                : '' ?>
                        >
                            Activo
                        </option>

                        <option
                            value="0"
                            <?= (int) $datosUsuario['activo'] === 0
                                ? 'selected'
                                : '' ?>
                        >
                            Inactivo
                        </option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="sg-form-actions">
                <button
                    class="sg-button sg-button-user-save"
                    type="submit"
                >
                    <?= $id === 0 ? 'Crear usuario' : 'Guardar cambios' ?>
                </button>

                <a
                    class="sg-link sg-link-user-cancel"
                    href="/pages/usuarios.php"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </main>
</body>
</html>