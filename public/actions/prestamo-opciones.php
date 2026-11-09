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

    exigirRol($usuario, ['administrador', 'operador']);

    if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);

        exit('La solicitud no es valida.');
    }

    $accion = $_POST['accion'] ?? null;

    if (
        !is_string($accion)
        || !in_array($accion, ['crear', 'estado'], true)
    ) {
        throw new InvalidArgumentException('La accion no es valida.');
    }

    if ($accion === 'crear') {
        $usuarioRecibido = $_POST['usuario_id'] ?? null;
        $inicioRecibido = $_POST['fecha_inicio'] ?? null;
        $finRecibido = $_POST['fecha_fin'] ?? null;

        if (
            !is_string($usuarioRecibido)
            || !is_string($inicioRecibido)
            || !is_string($finRecibido)
        ) {
            throw new InvalidArgumentException(
                'Los datos del prestamo no son validos.'
            );
        }

        $usuarioId = filter_var(
            $usuarioRecibido,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($usuarioId === false) {
            throw new InvalidArgumentException(
                'El usuario no es valido.'
            );
        }

        $ids = validarIdsEquipos($_POST['equipos'] ?? null);

        $inicio = interpretarFechaPrestamo($inicioRecibido);
        $fin = interpretarFechaPrestamo($finRecibido);

        crearPrestamo(
            $pdo,
            (int) $usuario['id'],
            $usuarioId,
            $ids,
            $inicio,
            $fin
        );

        $_SESSION['mensaje_prestamos'] = 'Prestamo solicitado correctamente.';
    } else {
        $idRecibido = $_POST['id'] ?? null;
        $estado = $_POST['estado'] ?? null;

        if (!is_string($idRecibido) || !is_string($estado)) {
            throw new InvalidArgumentException(
                'Los datos del cambio no son validos.'
            );
        }

        $id = filter_var(
            $idRecibido,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (
            $id === false
            || !in_array(
                $estado,
                ['aprobado', 'entregado', 'devuelto', 'cancelado'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'El identificador o estado no es valido.'
            );
        }

        cambiarEstadoPrestamo(
            $pdo,
            (int) $usuario['id'],
            $id,
            $estado
        );

        $_SESSION['mensaje_prestamos'] = 'Estado actualizado correctamente.';
    }

    header('Location: /pages/prestamos.php', true, 303);
    exit;
} catch (InvalidArgumentException | DomainException $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_prestamos'] = $error->getMessage();

    header('Location: /pages/prestamos.php', true, 303);
    exit;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log((string) $error);

    http_response_code(503);

    exit('No se pudo completar la operacion. Intente nuevamente.');
}