<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

function volverAEquipos(string $mensaje): void
{
    $_SESSION['mensaje_equipos'] = $mensaje;

    header('Location: /pages/equipos.php', true, 303);
    exit;
}

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
        || !in_array($accion, ['guardar', 'eliminar', 'paginacion'], true)
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

        setcookie('sigel_equipos_por_pagina', $cantidad, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => $parametrosSesion['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        header('Location: /pages/equipos.php', true, 303);
        exit;
    }

    exigirRol($usuario, ['administrador']);

    $idRecibido = $_POST['id'] ?? null;
    $versionRecibida = $_POST['version'] ?? null;

    if (
        !is_string($idRecibido)
        || !is_string($versionRecibida)
    ) {
        http_response_code(400);

        exit('Los identificadores no son validos.');
    }

    $id = filter_var(
        $idRecibido,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    $version = filter_var(
        $versionRecibida,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    if ($id === false || $version === false) {
        http_response_code(400);

        exit('Los identificadores no son validos.');
    }

    if ($accion === 'eliminar' && $id === 0) {
        http_response_code(400);

        exit('El identificador no es valido.');
    }

    if ($accion === 'guardar') {
        $codigo = $_POST['codigo'] ?? null;
        $nombre = $_POST['nombre'] ?? null;
        $estado = $_POST['estado'] ?? null;
        $categoriaRecibida = $_POST['categoria_id'] ?? null;

        if (
            !is_string($codigo)
            || !is_string($nombre)
            || !is_string($estado)
            || !is_string($categoriaRecibida)
        ) {
            http_response_code(400);

            exit('Los datos del equipo no son validos.');
        }

        $codigo = trim($codigo);
        $nombre = trim($nombre);

        if (
            preg_match('/\A[A-Za-z0-9_-]{1,50}\z/', $codigo) !== 1
        ) {
            http_response_code(400);

            exit('El codigo admite de 1 a 50 letras, numeros, guiones o guiones bajos.');
        }

        if (preg_match('/\A.{1,150}\z/us', $nombre) !== 1) {
            http_response_code(400);

            exit('El nombre debe tener entre 1 y 150 caracteres.');
        }

        if (
            !in_array(
                $estado,
                ['disponible', 'mantenimiento', 'baja'],
                true
            )
        ) {
            http_response_code(400);

            exit('El estado no es valido.');
        }

        $categoriaId = filter_var(
            $categoriaRecibida,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($categoriaId === false) {
            http_response_code(400);

            exit('La categoria no es valida.');
        }
    }

    $pdo->beginTransaction();

    if ($id > 0) {
        $consultarEquipo = $pdo->prepare(
            'SELECT id, version
             FROM equipos
             WHERE id = :id
               AND eliminado_en IS NULL
             FOR UPDATE'
        );

        $consultarEquipo->execute([
            ':id' => $id,
        ]);

        $equipoAnterior = $consultarEquipo->fetch();

        if ($equipoAnterior === false) {
            $pdo->rollBack();

            http_response_code(404);

            exit('El equipo no existe.');
        }

        if ((int) $equipoAnterior['version'] !== $version) {
            $pdo->rollBack();

            http_response_code(409);

            exit('El equipo cambio. Regrese al listado y vuelva a intentarlo.');
        }
    }

    if ($accion === 'eliminar') {
        $consultarPrestamos = $pdo->prepare(
            "SELECT prestamos.id
             FROM prestamos
             INNER JOIN prestamo_detalle
                ON prestamos.id = prestamo_detalle.prestamo_id
             WHERE prestamo_detalle.equipo_id = :equipo_id
               AND prestamos.estado IN (
                   'solicitado', 'aprobado', 'entregado'
               )
               AND prestamo_detalle.fecha_devolucion IS NULL
             LIMIT 1"
        );

        $consultarPrestamos->execute([
            ':equipo_id' => $id,
        ]);

        if ($consultarPrestamos->fetch() !== false) {
            $pdo->rollBack();

            http_response_code(409);

            exit('No puede eliminar un equipo con prestamos activos.');
        }

        $eliminar = $pdo->prepare(
            'UPDATE equipos
             SET eliminado_en = NOW(),
                 version = version + 1
             WHERE id = :id'
        );

        $eliminar->execute([
            ':id' => $id,
        ]);

        $pdo->commit();

        volverAEquipos('Equipo eliminado correctamente.');
    }

    $consultarCategoria = $pdo->prepare(
        'SELECT id
         FROM categorias
         WHERE id = :id
           AND eliminado_en IS NULL
         FOR UPDATE'
    );

    $consultarCategoria->execute([
        ':id' => $categoriaId,
    ]);

    if ($consultarCategoria->fetch() === false) {
        $pdo->rollBack();

        http_response_code(400);

        exit('Seleccione una categoria disponible.');
    }

    if ($id === 0) {
        $guardar = $pdo->prepare(
            'INSERT INTO equipos (
                categoria_id, codigo, nombre, estado
             ) VALUES (
                :categoria_id, :codigo, :nombre, :estado
             )'
        );

        $guardar->execute([
            ':categoria_id' => $categoriaId,
            ':codigo' => $codigo,
            ':nombre' => $nombre,
            ':estado' => $estado,
        ]);
    } else {
        $guardar = $pdo->prepare(
            'UPDATE equipos
             SET categoria_id = :categoria_id,
                 codigo = :codigo,
                 nombre = :nombre,
                 estado = :estado,
                 version = version + 1
             WHERE id = :id'
        );

        $guardar->execute([
            ':categoria_id' => $categoriaId,
            ':codigo' => $codigo,
            ':nombre' => $nombre,
            ':estado' => $estado,
            ':id' => $id,
        ]);
    }

    $pdo->commit();

    volverAEquipos('Equipo guardado correctamente.');
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (
        $error instanceof PDOException
        && (int) ($error->errorInfo[1] ?? 0) === 1062
    ) {
        http_response_code(409);

        exit('Ya existe un equipo con ese codigo.');
    }

    error_log((string) $error);

    http_response_code(503);

    exit('El sistema no esta disponible en este momento.');
}