<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

function volverAlFormularioCategoria(int $id, string $mensaje): void
{
    $_SESSION['error_categoria'] = $mensaje;

    $destino = '/pages/categoria-formulario.php';

    if ($id > 0) {
        $destino .= '?' . http_build_query(['id' => $id]);
    }

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
        ['options' => ['min_range' => 0]]
    );

    if ($id === false) {
        http_response_code(400);

        exit('El identificador no es valido.');
    }

    $nombre = $_POST['nombre'] ?? null;
    $diasRecibidos = $_POST['dias_max_prestamo'] ?? null;

    if (!is_string($nombre) || !is_string($diasRecibidos)) {
        volverAlFormularioCategoria($id, 'Revise los datos ingresados.');
    }

    $nombre = trim($nombre);

    if (preg_match('/\A.{1,100}\z/us', $nombre) !== 1) {
        volverAlFormularioCategoria(
            $id,
            'El nombre debe tener entre 1 y 100 caracteres.'
        );
    }

    $dias = filter_var(
        $diasRecibidos,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
                'max_range' => 3650,
            ],
        ]
    );

    if ($dias === false) {
        volverAlFormularioCategoria(
            $id,
            'Los dias deben ser un entero entre 1 y 3650.'
        );
    }

    $pdo->beginTransaction();

    if ($id === 0) {
        $consulta = $pdo->prepare(
            'INSERT INTO categorias (nombre, dias_max_prestamo)
             VALUES (:nombre, :dias)'
        );

        $consulta->execute([
            ':nombre' => $nombre,
            ':dias' => $dias,
        ]);
    } else {
        $consultar = $pdo->prepare(
            'SELECT id
             FROM categorias
             WHERE id = :id
               AND eliminado_en IS NULL
             FOR UPDATE'
        );

        $consultar->execute([
            ':id' => $id,
        ]);

        if ($consultar->fetch() === false) {
            $pdo->rollBack();

            http_response_code(404);

            exit('La categoria no existe.');
        }

        $consulta = $pdo->prepare(
            'UPDATE categorias
             SET nombre = :nombre,
                 dias_max_prestamo = :dias
             WHERE id = :id
               AND eliminado_en IS NULL'
        );

        $consulta->execute([
            ':nombre' => $nombre,
            ':dias' => $dias,
            ':id' => $id,
        ]);
    }

    $pdo->commit();

    $_SESSION['mensaje_categorias'] = 'Categoria guardada correctamente.';

    header('Location: /pages/categorias.php', true, 303);
    exit;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (
        $error instanceof PDOException
        && (int) ($error->errorInfo[1] ?? 0) === 1062
    ) {
        volverAlFormularioCategoria(
            $id,
            'Ya existe una categoria con ese nombre.'
        );
    }

    error_log((string) $error);

    http_response_code(503);

    exit('El sistema no esta disponible en este momento.');
}