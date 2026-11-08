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

    $idRecibido = $_POST['id'] ?? null;

    if (!is_string($idRecibido)) {
        http_response_code(400);

        exit('El identificador no es valido.');
    }

    $id = filter_var(
        $idRecibido,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        http_response_code(400);

        exit('El identificador no es valido.');
    }

    $consulta = $pdo->prepare(
        'UPDATE categorias
        set eliminado_en = NOW()
        WHERE id = :id
        AND eliminado_en IS NULL'
    );

    $consulta->execute([
        ':id' => $id,
    ]);

    if ($consulta->rowCount() === 0) {
        http_response_code(404);

        exit('La categoria no existe o ya fue eliminada.');
    }

    $_SESSION['mensaje_categorias'] = 'Categoria eliminada correctamente.';

    header('Location: /pages/categorias.php', true, 303);
    exit;
} catch (throwable $error) {
    errro_log((string) $error);

    http_response_code(503);

    exit('El sistema no esta disponible en este momento.');
}