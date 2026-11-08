<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    http_response_code(405);

    exit('Metodo no permitido.');
}

if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);

    exit('La solicitud no es valida.');
}

try {
    if (
        isset($_SESSION['remember_selector'])
        || isset($_COOKIE['sigel_remember'])
    ) {
        $pdo = conectarBaseDatos();

        revocarRecordatorio($pdo);
    }

    cerrarSesion();

    header('Location: /pages/login.php', true, 303);
    exit;
} catch (Throwable $error) {
    error_log((string) $error);

    http_response_code(503);

    exit('No se pudo completar el cierre de sesion. Intente nuevamente.');
}