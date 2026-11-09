<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    http_response_code(405);

    exit('Metodo no permitido.');
}

$pdo = null;
$rutaDestino = null;
$archivoMovido = false;

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador']);

    if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);

        exit('La solicitud no es valida.');
    }

    $idRecibido = $_POST['equipo_id'] ?? null;

    if (!is_string($idRecibido)) {
        http_response_code(400);

        exit('El identificador no es valido.');
    }

    $equipoId = filter_var(
        $idRecibido,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($equipoId === false) {
        http_response_code(400);

        exit('El identificador no es valido.');
    }

    $archivo = $_FILES['imagen'] ?? null;

    if (
        !is_array($archivo)
        || !isset($archivo['error'], $archivo['name'], $archivo['tmp_name'])
        || !is_int($archivo['error'])
        || !is_string($archivo['name'])
        || !is_string($archivo['tmp_name'])
    ) {
        http_response_code(400);

        exit('El archivo no es valido.');
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);

        exit('No se pudo recibir la imagen. Revise su tamaño.');
    }

    if (!is_uploaded_file($archivo['tmp_name'])) {
        http_response_code(400);

        exit('El archivo no es una carga valida.');
    }

    $tamano = filesize($archivo['tmp_name']);

    if ($tamano === false || $tamano < 1 || $tamano > 2 * 1024 * 1024) {
        http_response_code(400);

        exit('La imagen debe pesar como maximo 2 MiB.');
    }

    $extension = strtolower(
        pathinfo($archivo['name'], PATHINFO_EXTENSION)
    );

    $inspector = new finfo(FILEINFO_MIME_TYPE);

    $mime = $inspector->file($archivo['tmp_name']);

    $permitidos = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    if (
        !is_string($mime)
        || !isset($permitidos[$mime])
        || !in_array($extension, $permitidos[$mime], true)
    ) {
        http_response_code(400);

        exit('El contenido y la extension de la imagen no son validos.');
    }

    $extensionFinal = $permitidos[$mime][0];

    $nombreArchivo = bin2hex(random_bytes(16))
        . '.'
        . $extensionFinal;

    $carpeta = __DIR__ . '/../../storage/images';

    if (!is_dir($carpeta) || !is_writable($carpeta)) {
        throw new RuntimeException('El almacenamiento no esta disponible.');
    }

    $rutaDestino = $carpeta . '/' . $nombreArchivo;

    if (file_exists($rutaDestino)) {
        throw new RuntimeException('El nombre generado ya existe.');
    }

    $pdo->beginTransaction();

    $consultaEquipo = $pdo->prepare(
        'SELECT id
         FROM equipos
         WHERE id = :id
           AND eliminado_en IS NULL
         FOR UPDATE'
    );

    $consultaEquipo->execute([
        ':id' => $equipoId,
    ]);

    if ($consultaEquipo->fetch() === false) {
        $pdo->rollBack();

        http_response_code(404);

        exit('El equipo no existe.');
    }

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }

    $archivoMovido = true;

    $guardar = $pdo->prepare(
        'INSERT INTO equipo_imagenes (
            equipo_id, nombre_archivo, mime
         ) VALUES (
            :equipo_id, :nombre_archivo, :mime
         )'
    );

    $guardar->execute([
        ':equipo_id' => $equipoId,
        ':nombre_archivo' => $nombreArchivo,
        ':mime' => $mime,
    ]);

    $pdo->commit();

    $archivoMovido = false;

    $_SESSION['mensaje_imagen'] = 'Imagen agregada correctamente.';

    header(
        'Location: /pages/equipo-detalle.php?'
            . http_build_query(['id' => $equipoId]),
        true,
        303
    );

    exit;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (
        $archivoMovido
        && $rutaDestino !== null
        && is_file($rutaDestino)
    ) {
        if (!unlink($rutaDestino)) {
            error_log('No se pudo limpiar una imagen tras un fallo de guardado.');
        }
    }

    error_log((string) $error);

    http_response_code(503);

    exit('No se pudo guardar la imagen.');
}