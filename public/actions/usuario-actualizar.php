<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

function volverAEditarUsuario(int $id, string $mensaje): void
{
    $_SESSION['error_usuario'] = $mensaje;

    $destino = '/pages/usuario-formulario.php?'
        . http_build_query(['id' => $id]);

    header('Location: ' . $destino, true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    http_response_code(405);

    exit('Metodo no permitido.');
}

$pdo = null;
$id = 0;

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

    $nombre = $_POST['nombre'] ?? null;
    $correo = $_POST['correo'] ?? null;
    $password = $_POST['password'] ?? null;
    $rolRecibido = $_POST['rol_id'] ?? null;
    $activo = $_POST['activo'] ?? null;

    if (
        !is_string($nombre)
        || !is_string($correo)
        || !is_string($password)
        || !is_string($rolRecibido)
        || !is_string($activo)
    ) {
        volverAEditarUsuario($id, 'Revise los datos ingresados.');
    }

    $nombre = trim($nombre);
    $correo = trim($correo);

    if (preg_match('/\A.{1,100}\z/us', $nombre) !== 1) {
        volverAEditarUsuario(
            $id,
            'El nombre debe tener entre 1 y 100 caracteres.'
        );
    }

    if (
        strlen($correo) > 150
        || filter_var($correo, FILTER_VALIDATE_EMAIL) === false
    ) {
        volverAEditarUsuario($id, 'El correo no es valido.');
    }

    if (!in_array($activo, ['0', '1'], true)) {
        volverAEditarUsuario($id, 'El estado no es valido.');
    }

    if (
        $password !== ''
        && (
            strlen($password) < 8
            || strlen($password) > 72
            || str_contains($password, "\0")
        )
    ) {
        volverAEditarUsuario(
            $id,
            'La nueva contraseña no cumple los requisitos.'
        );
    }

    $rolId = filter_var(
        $rolRecibido,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($rolId === false) {
        volverAEditarUsuario($id, 'El rol no es valido.');
    }

    $hash = $password === ''
        ? null
        : password_hash($password, PASSWORD_BCRYPT);

    $pdo->beginTransaction();

    $consultaRoles = $pdo->query(
        "SELECT id, nombre
         FROM roles
         WHERE nombre IN ('administrador', 'operador')
         ORDER BY id
         FOR UPDATE"
    );

    $roles = $consultaRoles->fetchAll();

    $rolSeleccionado = null;
    $rolAdministradorId = null;

    foreach ($roles as $rol) {
        if ((int) $rol['id'] === $rolId) {
            $rolSeleccionado = $rol['nombre'];
        }

        if ($rol['nombre'] === 'administrador') {
            $rolAdministradorId = (int) $rol['id'];
        }
    }

    if ($rolSeleccionado === null) {
        $pdo->rollBack();

        volverAEditarUsuario($id, 'El rol no es valido.');
    }

    $consultaAdministrador = $pdo->prepare(
        'SELECT id
         FROM usuarios
         WHERE id = :id
           AND rol_id = :rol_id
           AND activo = 1
           AND eliminado_en IS NULL
         FOR UPDATE'
    );

    $consultaAdministrador->execute([
        ':id' => $usuario['id'],
        ':rol_id' => $rolAdministradorId,
    ]);

    if ($consultaAdministrador->fetch() === false) {
        $pdo->rollBack();

        http_response_code(403);

        exit('No tiene permiso para realizar esta operacion.');
    }

    $consultaUsuario = $pdo->prepare(
        'SELECT id, rol_id
         FROM usuarios
         WHERE id = :id
           AND eliminado_en IS NULL
         FOR UPDATE'
    );

    $consultaUsuario->execute([
        ':id' => $id,
    ]);

    $usuarioAnterior = $consultaUsuario->fetch();

    if ($usuarioAnterior === false) {
        $pdo->rollBack();

        http_response_code(404);

        exit('El usuario no existe.');
    }

    if (
        $id === (int) $usuario['id']
        && ($activo !== '1' || $rolSeleccionado !== 'administrador')
    ) {
        $pdo->rollBack();

        volverAEditarUsuario(
            $id,
            'No puede desactivar su cuenta ni quitarse el rol de administrador.'
        );
    }

    $sql = 'UPDATE usuarios
            SET nombre = :nombre,
                correo = :correo,
                rol_id = :rol_id,
                activo = :activo';

    $parametros = [
        ':nombre' => $nombre,
        ':correo' => $correo,
        ':rol_id' => $rolId,
        ':activo' => (int) $activo,
        ':id' => $id,
    ];

    if ($hash !== null) {
        $sql .= ', password_hash = :password_hash,
                   intentos_fallidos = 0,
                   bloqueado_hasta = NULL';

        $parametros[':password_hash'] = $hash;
    }

    $sql .= ' WHERE id = :id AND eliminado_en IS NULL';

    $actualizar = $pdo->prepare($sql);

    $actualizar->execute($parametros);

    if (
        $hash !== null
        || $activo === '0'
        || (int) $usuarioAnterior['rol_id'] !== $rolId
    ) {
        $revocar = $pdo->prepare(
            'DELETE FROM auth_tokens WHERE usuario_id = :id'
        );

        $revocar->execute([
            ':id' => $id,
        ]);
    }

    $pdo->commit();

    $_SESSION['mensaje_usuarios'] = 'Usuario actualizado correctamente.';

    header('Location: /pages/usuarios.php', true, 303);
    exit;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (
        $error instanceof PDOException
        && (int) ($error->errorInfo[1] ?? 0) === 1062
    ) {
        volverAEditarUsuario(
            $id,
            'Ya existe un usuario con ese correo.'
        );
    }

    error_log((string) $error);

    http_response_code(503);

    exit('El sistema no esta disponible en este momento.');
}