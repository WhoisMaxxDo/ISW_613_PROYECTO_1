<?php

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $pdo = conectarBaseDatos();
    $pdo->query('SELECT 1');

    $mensaje = 'Entorno inicial disponible.';
} catch (Throwable $error) {
    error_log((string) $error);

    http_response_code(503);

    $mensaje = 'El sistema no esta disponible en este momento.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGEL</title>
</head>
<body>
    <h1 class="sg-title">SIGUEL</h1>
    <p>Sistema de Gestion de Prestamo de Equipos de Laboratorio</p>
    <p>
        <?= htmlspecialchars($mensaje,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
        ) ?>
    </p>
</body>
</html>