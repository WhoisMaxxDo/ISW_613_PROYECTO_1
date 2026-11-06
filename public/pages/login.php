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
    </header>

    <main class="sg-login-main">
        <section class="sg-login-card" aria-labelledby="sg-login-title">
            <h2 class="sg-login-title" id="sg-login-title">
                Iniciar sesión
            </h2>

            <p class="sg-login-description">
                Acceso para administradores y operadores del laboratorio.
            </p>

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
                >
            </div>

            <button
                class="sg-button sg-button-login"
                type="button"
                disabled
            >
                Iniciar sesión
            </button>

            <p class="sg-login-navigation">
                <a class="sg-link sg-link-home" href="/">
                    Volver al inicio
                </a>
            </p>
        </section>
    </main>
</body>
</html>