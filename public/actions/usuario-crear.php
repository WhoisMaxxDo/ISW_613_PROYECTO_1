<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

function volverAlFormularioUsuario(string $mensaje): void
{
    $_SESSION['error_usuario'] = $mensaje;

    header('Location: /pages/usuario-formulario.php', true, 303);
    exit;
}

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

    $nombre = $_POST['nombre'] ?? null;
    $correo = $_POST['correo'] ?? null;
    $password = $_POST['password'] ?? null;
    $rolRecibido = $_POST['rol_id'] ?? null;

    if (
        !is_string($nombre)
        || !is_string($correo)
        || !is_string($password)
        || !is_string($rolRecibido)
    ) {
        volverAlFormularioUsuario('Revise los datos ingresados.');
    }

    $nombre = trim($nombre);
    $correo = trim($correo);

    if (preg_match('/\A.{1,100}\z/us', $nombre) !== 1) {
        volverAlFormularioUsuario(
            'El nombre debe tener entre 1 y 100 caracteres.'
        );
    }

    if (
        strlen($correo) > 150
        || filter_var($correo, FILTER_VALIDATE_EMAIL) === false
    ) {
        volverAlFormularioUsuario('El correo no es valido.');
    }

    if (
        strlen($password) < 8
        || strlen($password) > 72
        || str_contains($password, "\0")
    ) {
        volverAlFormularioUsuario(
            'La contraseña debe tener entre 8 y 72 bytes y no contener bytes nulos.'
        );
    }

    $rolId = filter_var(
        $rolRecibido,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($rolId === false) {
        volverAlFormularioUsuario('El rol no es valido.');
    }

    $consultaRol = $pdo->prepare(
        "SELECT id
         FROM roles
         WHERE id = :id
           AND nombre IN ('administrador', 'operador')"
    );

    $consultaRol->execute([
        ':id' => $rolId,
    ]);

    if ($consultaRol->fetch() === false) {
        volverAlFormularioUsuario('El rol no es valido.');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $consulta = $pdo->prepare(
        'INSERT INTO usuarios (
            rol_id,
            nombre,
            correo,
            password_hash
         ) VALUES (
            :rol_id,
            :nombre,
            :correo,
            :password_hash
         )'
    );

    $consulta->execute([
        ':rol_id' => $rolId,
        ':nombre' => $nombre,
        ':correo' => $correo,
        ':password_hash' => $hash,
    ]);

    $_SESSION['mensaje_usuarios'] = 'Usuario creado correctamente.';

    header('Location: /pages/usuarios.php', true, 303);
    exit;
} catch (Throwable $error) {
    if (
        $error instanceof PDOException
        && (int) ($error->errorInfo[1] ?? 0) === 1062
    ) {
        volverAlFormularioUsuario(
            'Ya existe un usuario con ese correo.'
        );
    }

    error_log((string) $error);

    http_response_code(503);

    exit('El sistema no esta disponible en este momento.');
}