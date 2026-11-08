<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    http_response_code(405);

    exit('Metodo no permitido.');
}

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador']);

    if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);

        exit('La solicitud no es valida.');
    }

    $cantidad = $_POST['por_pagina'] ?? null;

    if (
        !is_string($cantidad)
        || !in_array($cantidad, ['5', '10', '20', '50'], true)
    ) {
        http_response_code(400);

        exit('La cantidad de registros no es valida.');
    }

    $parametrosSesion = session_get_cookie_params();

    setcookie('sigel_categorias_por_pagina', $cantidad, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/',
        'secure' => $parametrosSesion['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    header('Location: /pages/categorias.php', true, 303);
    exit;
} catch (Throwable $error) {
    error_log((string) $error);

    http_response_code(503);

    exit('El sistema no esta disponible en este momento.');
}