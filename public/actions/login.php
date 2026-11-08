<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

function regresarAlLogin(string $mensaje): void
{
    $_SESSION['error_login'] = $mensaje;

    header('Location: /pages/login.php', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    http_response_code(405);

    exit('Metodo no permitido.');
}

if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    regresarAlLogin('La solicitud no es valida. Intente de nuevo.');
}

$correo = $_POST['correo'] ?? '';
$password = $_POST['password'] ?? '';

$recordarme = $_POST['recordarme'] ?? '0';

if (
    !is_string($recordarme)
    || !in_array($recordarme, ['0', '1'], true)
) {
    regresarAlLogin('Revise los datos ingresados.');
}

if (!is_string($correo) || !is_string($password)) {
    regresarAlLogin('Revise los datos ingresados.');
}

$correo = trim($correo);

if (
    strlen($correo) > 150
    || filter_var($correo, FILTER_VALIDATE_EMAIL) === false
    || $password === ''
    || strlen($password) > 4096
) {
    regresarAlLogin('Revise los datos ingresados.');
}

$mensajeError = 'No se pudo iniciar sesion con los datos ingresados.';
$pdo = null;

try {
    $pdo = conectarBaseDatos();

    $pdo->beginTransaction();

    $consulta = $pdo->prepare(
        'SELECT id, password_hash, intentos_fallidos, bloqueado_hasta
         FROM usuarios
         WHERE correo = :correo
           AND activo = 1
           AND eliminado_en IS NULL
         FOR UPDATE'
    );

    $consulta->execute([
        ':correo' => $correo,
    ]);

    $usuario = $consulta->fetch();

    if ($usuario === false) {
        $pdo->rollBack();

        regresarAlLogin($mensajeError);
    }

    $intentos = (int) $usuario['intentos_fallidos'];

    if ($usuario['bloqueado_hasta'] !== null) {
        $finBloqueo = strtotime($usuario['bloqueado_hasta']);

        if ($finBloqueo > time()) {
            $pdo->rollBack();

            regresarAlLogin($mensajeError);
        }

        $intentos = 0;
    }

    if (!password_verify($password, $usuario['password_hash'])) {
        $intentos++;

        $bloqueadoHasta = null;

        if ($intentos >= 5) {
            $bloqueadoHasta = date('Y-m-d H:i:s', time() + 900);
        }

        $actualizar = $pdo->prepare(
            'UPDATE usuarios
             SET intentos_fallidos = :intentos,
                 bloqueado_hasta = :bloqueado_hasta
             WHERE id = :id'
        );

        $actualizar->execute([
            ':intentos' => $intentos,
            ':bloqueado_hasta' => $bloqueadoHasta,
            ':id' => $usuario['id'],
        ]);

        $pdo->commit();

        regresarAlLogin($mensajeError);
    }

    $actualizar = $pdo->prepare(
        'UPDATE usuarios
         SET intentos_fallidos = 0,
             bloqueado_hasta = NULL
         WHERE id = :id'
    );

    $actualizar->execute([
        ':id' => $usuario['id'],
    ]);

    $pdo->commit();

    revocarRecordatorio($pdo);

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('No se pudo renovar la sesion.');
    }

    $_SESSION = [];

    if ($recordarme === '1') {
        crearRecordatorio($pdo, (int) $usuario['id']);
    }

    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['ultima_actividad'] = time();

    header('Location: /pages/panel.php', true, 303);
    exit;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log((string) $error);

    regresarAlLogin('El sistema no esta disponible en este momento.');
}