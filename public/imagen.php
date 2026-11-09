<?php

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador', 'operador']);

    $idRecibido = $_GET['id'] ?? null;

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
        'SELECT equipo_imagenes.nombre_archivo, equipo_imagenes.mime
         FROM equipo_imagenes
         INNER JOIN equipos ON equipo_imagenes.equipo_id = equipos.id
         WHERE equipo_imagenes.id = :id
           AND equipos.eliminado_en IS NULL'
    );

    $consulta->execute([
        ':id' => $id,
    ]);

    $imagen = $consulta->fetch();

    if ($imagen === false) {
        http_response_code(404);

        exit('La imagen no existe.');
    }

    if (
        preg_match(
            '/\A[a-f0-9]{32}\.(jpg|png|webp)\z/',
            $imagen['nombre_archivo']
        ) !== 1
        || !in_array(
            $imagen['mime'],
            ['image/jpeg', 'image/png', 'image/webp'],
            true
        )
    ) {
        throw new RuntimeException('Los metadatos de la imagen no son validos.');
    }

    $ruta = __DIR__ . '/../storage/images/' . $imagen['nombre_archivo'];

    if (!is_file($ruta) || !is_readable($ruta)) {
        http_response_code(404);

        exit('La imagen no esta disponible.');
    }

    header('Content-Type: ' . $imagen['mime']);
    header('X-Content-Type-Options: nosniff');

    header(
        'Content-Disposition: inline; filename="'
        . $imagen['nombre_archivo']
        . '"'
    );

    if (readfile($ruta) === false) {
        error_log('No se pudo completar la lectura de una imagen.');
    }

    exit;
} catch (Throwable $error) {
    error_log((string) $error);

    http_response_code(503);

    exit('La imagen no esta disponible.');
}