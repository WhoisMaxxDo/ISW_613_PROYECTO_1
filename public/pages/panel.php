<?php

require_once __DIR__ . '/../../includes/bootstrap.php';


try {
    $pdo = conectarBaseDatos();
    $usuario = exigirAutenticacion($pdo);
} catch (throwable $error) {
    error_log((string) $error);

    http_response_code(503);

    exit('El sistema no esta disponible en este momento.');
}
    

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    title>Panel | SIGEL</title>
    link rel="stylesheet" href="/assets/css/styles.css">
</head>
    <meta> charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


<body class="sg-page-panel">
    <header class="sg-header">
        <h1 class="sg-header-title">SIGEL</h1>
        <p class="sg-header-description">
            gestion de prestamos de equipos de laboratorio
        </p>
    </header>

    <main class="sg-panel-main">
        <h2 class="sg-panel-title">
            Bienvenido <?= escapar($usuario['nombre']) ?>
        </h2>

        <p class="sg-panel-role">
            Rol: <?= escapar($usuario['rol']) ?>
        </p>
    </main>
</body>
</html>