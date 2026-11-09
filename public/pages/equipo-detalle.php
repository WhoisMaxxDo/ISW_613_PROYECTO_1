<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

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
        'SELECT
            equipos.id,
            equipos.codigo,
            equipos.nombre,
            equipos.estado,
            categorias.nombre AS categoria
         FROM equipos
         INNER JOIN categorias ON equipos.categoria_id = categorias.id
         WHERE equipos.id = :id
           AND equipos.eliminado_en IS NULL'
    );

    $consulta->execute([
        ':id' => $id,
    ]);

    $equipo = $consulta->fetch();

    if ($equipo === false) {
        http_response_code(404);

        exit('El equipo no existe.');
    }

    registrarEquipoReciente((int) $equipo['id']);

    $consultaImagenes = $pdo->prepare(
        'SELECT id
         FROM equipo_imagenes
         WHERE equipo_id = :id
         ORDER BY id'
    );

    $consultaImagenes->execute([
        ':id' => $id,
    ]);

    $imagenes = $consultaImagenes->fetchAll();

    $mensajeImagen = $_SESSION['mensaje_imagen'] ?? '';

    unset($_SESSION['mensaje_imagen']);
} catch (Throwable $error) {
    error_log((string) $error);

    http_response_code(503);

    exit('El sistema no esta disponible en este momento.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= escapar($equipo['nombre']) ?> | SIGEL</title>

    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="sg-page-equipment-detail">
    <header class="sg-header">
        <h1 class="sg-header-title">SIGEL</h1>
        <p class="sg-header-description">Ficha del equipo</p>
    </header>

    <main class="sg-panel-main">
        <div class="sg-section-heading">
            <h2 class="sg-section-title">
                <?= escapar($equipo['nombre']) ?>
            </h2>

            <a class="sg-link" href="/pages/equipos.php">
                Volver a equipos
            </a>
        </div>

        <dl class="sg-equipment-information">
            <dt>Código</dt>
            <dd><?= escapar($equipo['codigo']) ?></dd>

            <dt>Categoría</dt>
            <dd><?= escapar($equipo['categoria']) ?></dd>

            <dt>Estado</dt>
            <dd><?= escapar($equipo['estado']) ?></dd>
        </dl>

        <?php if ($mensajeImagen !== ''): ?>
            <p class="sg-form-success" role="status">
                <?= escapar($mensajeImagen) ?>
            </p>
        <?php endif; ?>

        <section class="sg-equipment-images" aria-labelledby="sg-images-title">
            <h3 id="sg-images-title">Imágenes</h3>

            <?php if ($imagenes === []): ?>
                <p>Este equipo no tiene imágenes registradas.</p>
            <?php endif; ?>

            <div class="sg-image-gallery">
                <?php foreach ($imagenes as $imagen): ?>
                    <img
                        class="sg-equipment-image"
                        src="<?= escapar(
                            '/imagen.php?'
                            . http_build_query(['id' => $imagen['id']])
                        ) ?>"
                        alt="<?= escapar('Imagen de ' . $equipo['nombre']) ?>"
                        loading="lazy"
                    >
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($usuario['rol'] === 'administrador'): ?>
            <form
                class="sg-image-upload-form"
                action="/actions/equipo-imagen-subir.php"
                method="post"
                enctype="multipart/form-data"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapar(obtenerTokenCsrf()) ?>"
                >

                <input
                    type="hidden"
                    name="equipo_id"
                    value="<?= escapar((string) $equipo['id']) ?>"
                >

                <div class="sg-field sg-field-equipment-image">
                    <label class="sg-label" for="sg-equipment-image">
                        Agregar imagen
                    </label>

                    <input
                        class="sg-input"
                        type="file"
                        id="sg-equipment-image"
                        name="imagen"
                        accept=".jpg,.jpeg,.png,.webp"
                        required
                    >

                    <p class="sg-field-description">
                        JPG, PNG o WebP. Máximo 2 MiB por imagen.
                    </p>
                </div>

                <button class="sg-button" type="submit">
                    Subir imagen
                </button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>