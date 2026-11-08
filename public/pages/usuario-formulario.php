<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador']);

    $consulta = $pdo->query(
        "SELECT id, nombre
         FROM roles
         WHERE nombre IN ('administrador', 'operador')
         ORDER BY nombre"
    );

    $roles = $consulta->fetchAll();

    $errorUsuario = $_SESSION['error_usuario'] ?? '';

    unset($_SESSION['error_usuario']);
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

    <title>Crear usuario | SIGEL</title>

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
        <h2 class="sg-section-title">Crear usuario</h2>

        <?php if ($errorUsuario !== ''): ?>
            <p class="sg-form-error" role="alert">
                <?= escapar($errorUsuario) ?>
            </p>
        <?php endif; ?>

        <form
            class="sg-user-form"
            action="/actions/usuario-crear.php"
            method="post"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar(obtenerTokenCsrf()) ?>"
            >

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
                    required
                >

                <p class="sg-field-description">
                    Entre 8 y 72 bytes.
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
                        <option value="<?= escapar((string) $rol['id']) ?>">
                            <?= escapar($rol['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sg-form-actions">
                <button
                    class="sg-button sg-button-user-save"
                    type="submit"
                >
                    Crear usuario
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