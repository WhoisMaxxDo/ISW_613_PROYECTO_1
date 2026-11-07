<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$errorLogin = $_SESSION['error_login'] ?? null;
unset($_SESSION['error_login']);

$tokenCsrf = obtenerTokenCsrf();
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión | SIGEL</title>

    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="sg-page-login">
    <header class="sg-header">
        <h1 class="sg-header-title">SIGEL</h1>

        <p class="sg-header-description">
            Gestión de préstamos de equipos de laboratorio
        </p>
        <?php if ($errorLogin !== ''): ?>
            <p class="sg-login-error" role="alert">
                <?= escapar($errorLogin) ?>
            </p>
        <?php endif; ?>
    </header>

    <main class="sg-login-main">
        <section class="sg-login-card" aria-labelledby="sg-login-title">
            <h2 class="sg-login-title" id="sg-login-title">
                Iniciar sesión
            </h2>

            <p class="sg-login-description">
                Acceso para administradores y operadores del laboratorio.
            </p>
            <form class="sg-login-form" action="/actions/login.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= escapar($tokenCsrf) ?>">
                            <div class="sg-field sg-field-email">
                <label
                    class="sg-label sg-label-email"
                    for="sg-email"
                >
                    Correo electrónico
                </label>

                <input
                    class="sg-input sg-input-email"
                    type="email"
                    id="sg-email"
                    name="correo"
                    maxlength="150"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="sg-field sg-field-password">
                <label
                    class="sg-label sg-label-password"
                    for="sg-password"
                >
                    Contraseña
                </label>

                <input
                    class="sg-input sg-input-password"
                    type="password"
                    id="sg-password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="sg-remember">
                <input
                    class="sg-checkbox sg-checkbox-remember"
                    type="checkbox"
                    id="sg-remember"
                    name="recordarme"
                    value="1"
                    disabled
                >

                <label class="sg-label-remember" for="sg-remember">
                    Recordarme durante 14 días
                </label>
            </div>

            <button
                class="sg-button sg-button-login"
                type="submit"
            >
                Iniciar sesión
            </button>

            <p class="sg-login-navigation">
                <a class="sg-link sg-link-home" href="/">
                    Volver al inicio
                </a>
            </p>
            </form>
        </section>
    </main>
</body>
</html>