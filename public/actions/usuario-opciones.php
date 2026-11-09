<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    http_response_code(405);

    exit('Metodo no permitido.');
}

$pdo = null;

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador']);

    if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);

        exit('La solicitud no es valida.');
    }

    $accion = $_POST['accion'] ?? null;

    if (
        !is_string($accion)
        || !in_array($accion, ['paginacion', 'eliminar'], true)
    ) {
        http_response_code(400);

        exit('La accion no es valida.');
    }

    if ($accion === 'paginacion') {
        $cantidad = $_POST['por_pagina'] ?? null;

        if (
            !is_string($cantidad)
            || !in_array($cantidad, ['5', '10', '20', '50'], true)
        ) {
            http_response_code(400);

            exit('La cantidad de registros no es valida.');
        }

        $parametrosSesion = session_get_cookie_params();

        setcookie('sigel_usuarios_por_pagina', $cantidad, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => $parametrosSesion['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        header('Location: /pages/usuarios.php', true, 303);
        exit;
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

    if ($id === (int) $usuario['id']) {
        http_response_code(403);

        exit('No puede eliminar su propia cuenta.');
    }

    $pdo->beginTransaction();

    $consultaRoles = $pdo->query(
        "SELECT id, nombre
         FROM roles
         WHERE nombre IN ('administrador', 'operador')
         ORDER BY id
         FOR UPDATE"
    );

    $roles = $consultaRoles->fetchAll();

    $rolAdministradorId = null;

    foreach ($roles as $rol) {
        if ($rol['nombre'] === 'administrador') {
            $rolAdministradorId = (int) $rol['id'];
        }
    }

    $comprobarAdministrador = $pdo->prepare(
        'SELECT id
         FROM usuarios
         WHERE id = :id
           AND rol_id = :rol_id
           AND activo = 1
           AND eliminado_en IS NULL
         FOR UPDATE'
    );

    $comprobarAdministrador->execute([
        ':id' => $usuario['id'],
        ':rol_id' => $rolAdministradorId,
    ]);

    if ($comprobarAdministrador->fetch() === false) {
        $pdo->rollBack();

        http_response_code(403);

        exit('No tiene permiso para realizar esta operacion.');
    }

    $eliminar = $pdo->prepare(
        'UPDATE usuarios
         SET activo = 0,
             eliminado_en = NOW()
         WHERE id = :id
           AND eliminado_en IS NULL'
    );

    $eliminar->execute([
        ':id' => $id,
    ]);

    if ($eliminar->rowCount() === 0) {
        $pdo->rollBack();

        http_response_code(404);

        exit('El usuario no existe o ya fue eliminado.');
    }

    $revocar = $pdo->prepare(
        'DELETE FROM auth_tokens WHERE usuario_id = :id'
    );

    $revocar->execute([
        ':id' => $id,
    ]);

    $pdo->commit();

    $_SESSION['mensaje_usuarios'] = 'Usuario eliminado correctamente.';

    header('Location: /pages/usuarios.php', true, 303);
    exit;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log((string) $error);

    http_response_code(503);

    exit('El sistema no esta disponible en este momento.');
}