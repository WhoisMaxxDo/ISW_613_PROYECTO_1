<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['RQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    http_response_code(405);

    exit('Metodo no permitido.');
}


if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);

    exit('La solicitud no es valida.');

}

cerrarSesion();

header ('Location: /pages/login.php', true, 303);
exit;