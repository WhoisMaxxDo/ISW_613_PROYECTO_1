<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador', 'operador']);

    $mensaje = $_SESSION['mensaje_prestamos'] ?? '';
    $errorPrestamo = $_SESSION['error_prestamos'] ?? '';

    unset(
        $_SESSION['mensaje_prestamos'],
        $_SESSION['error_prestamos']
    );

    $paginaRecibida = $_GET['pagina'] ?? '1';

    if (!is_string($paginaRecibida)) {
        http_response_code(400);

        exit('La pagina no es valida.');
    }

    $pagina = filter_var(
        $paginaRecibida,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
                'max_range' => 1000000,
            ],
        ]
    );

    if ($pagina === false) {
        http_response_code(400);

        exit('La pagina no es valida.');
    }

    $usuariosDisponibles = $pdo->query(
        'SELECT id, nombre, correo
         FROM usuarios
         WHERE activo = 1
           AND eliminado_en IS NULL
         ORDER BY nombre'
    )->fetchAll();

    $equiposDisponibles = $pdo->query(
        "SELECT equipos.id, equipos.codigo, equipos.nombre
         FROM equipos
         INNER JOIN categorias ON equipos.categoria_id = categorias.id
         WHERE equipos.estado = 'disponible'
           AND equipos.eliminado_en IS NULL
           AND categorias.eliminado_en IS NULL
         ORDER BY equipos.codigo"
    )->fetchAll();

    $totalRegistros = (int) $pdo->query(
        'SELECT COUNT(*) FROM prestamos'
    )->fetchColumn();

    $porPagina = 10;

    $totalPaginas = max(
        1,
        (int) ceil($totalRegistros / $porPagina)
    );

    $pagina = min($pagina, $totalPaginas);

    $consulta = $pdo->prepare(
        'SELECT
            prestamos.id,
            prestamos.operador_id,
            prestamos.estado,
            prestamos.fecha_inicio,
            prestamos.fecha_fin,
            destinatario.nombre AS usuario_nombre,
            creador.nombre AS operador_nombre,
            aprobador.nombre AS aprobado_nombre
         FROM prestamos
         INNER JOIN usuarios AS destinatario
            ON prestamos.usuario_id = destinatario.id
         INNER JOIN usuarios AS creador
            ON prestamos.operador_id = creador.id
         LEFT JOIN usuarios AS aprobador
            ON prestamos.aprobado_por = aprobador.id
         ORDER BY prestamos.id DESC
         LIMIT :limite OFFSET :desplazamiento'
    );

    $consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);

    $consulta->bindValue(
        ':desplazamiento',
        ($pagina - 1) * $porPagina,
        PDO::PARAM_INT
    );

    $consulta->execute();

    $prestamos = $consulta->fetchAll();

    $consultaDetalle = $pdo->prepare(
        'SELECT
            equipos.codigo,
            equipos.nombre,
            prestamo_detalle.fecha_devolucion
         FROM prestamo_detalle
         INNER JOIN equipos ON prestamo_detalle.equipo_id = equipos.id
         WHERE prestamo_detalle.prestamo_id = :id
         ORDER BY equipos.codigo'
    );

    foreach ($prestamos as $indice => $prestamo) {
        $consultaDetalle->execute([
            ':id' => $prestamo['id'],
        ]);

        $prestamos[$indice]['equipos'] = $consultaDetalle->fetchAll();
    }

    $accionesPorEstado = [
        'solicitado' => [
            'aprobado' => 'Aprobar',
            'cancelado' => 'Cancelar préstamo',
        ],
        'aprobado' => [
            'entregado' => 'Registrar entrega',
            'cancelado' => 'Cancelar préstamo',
        ],
        'entregado' => [
            'devuelto' => 'Registrar devolución',
        ],
        'devuelto' => [],
        'cancelado' => [],
    ];

    $tokenCsrf = obtenerTokenCsrf();

    $inicioMinimo = date('Y-m-d\TH:i', time() + 60);

    $urlAnterior = '/pages/prestamos.php?' . http_build_query([
        'pagina' => $pagina - 1,
    ]);

    $urlSiguiente = '/pages/prestamos.php?' . http_build_query([
        'pagina' => $pagina + 1,
    ]);
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

    <title>Préstamos | SIGEL</title>

    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="sg-page-loans">
    <header class="sg-header">
        <h1 class="sg-header-title">SIGEL</h1>
        <p class="sg-header-description">Préstamos de equipos</p>
    </header>

    <main class="sg-panel-main">
        <div class="sg-section-heading">
            <h2 class="sg-section-title">Préstamos</h2>

            <a class="sg-link" href="/pages/panel.php">
                Volver al panel
            </a>
        </div>

        <?php if ($mensaje !== ''): ?>
            <p class="sg-form-success" role="status">
                <?= escapar($mensaje) ?>
            </p>
        <?php endif; ?>

        <?php if ($errorPrestamo !== ''): ?>
            <p class="sg-form-error" role="alert">
                <?= escapar($errorPrestamo) ?>
            </p>
        <?php endif; ?>

        <details class="sg-loan-create">
            <summary class="sg-loan-create-title">
                Registrar préstamo
            </summary>

            <form
                class="sg-loan-form"
                action="/actions/prestamo-opciones.php"
                method="post"
            >
                <input type="hidden" name="accion" value="crear">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapar($tokenCsrf) ?>"
                >

                <div class="sg-field sg-field-loan-user">
                    <label class="sg-label" for="sg-loan-user">
                        Usuario que recibe los equipos
                    </label>

                    <select
                        class="sg-input"
                        id="sg-loan-user"
                        name="usuario_id"
                        required
                    >
                        <option value="">Seleccionar usuario</option>

                        <?php foreach ($usuariosDisponibles as $destinatario): ?>
                            <option
                                value="<?= escapar((string) $destinatario['id']) ?>"
                            >
                                <?= escapar(
                                    $destinatario['nombre']
                                    . ' — '
                                    . $destinatario['correo']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sg-loan-date-fields">
                    <div class="sg-field sg-field-loan-start">
                        <label class="sg-label" for="sg-loan-start">
                            Inicio
                        </label>

                        <input
                            class="sg-input"
                            type="datetime-local"
                            id="sg-loan-start"
                            name="fecha_inicio"
                            min="<?= escapar($inicioMinimo) ?>"
                            required
                        >
                    </div>

                    <div class="sg-field sg-field-loan-end">
                        <label class="sg-label" for="sg-loan-end">
                            Fin previsto
                        </label>

                        <input
                            class="sg-input"
                            type="datetime-local"
                            id="sg-loan-end"
                            name="fecha_fin"
                            min="<?= escapar($inicioMinimo) ?>"
                            required
                        >
                    </div>
                </div>

                <fieldset class="sg-loan-equipment-fieldset">
                    <legend>Equipos</legend>

                    <?php if ($equiposDisponibles === []): ?>
                        <p>No hay equipos habilitados para solicitar.</p>
                    <?php endif; ?>

                    <div class="sg-loan-equipment-options">
                        <?php foreach ($equiposDisponibles as $equipo): ?>
                            <label class="sg-loan-equipment-option">
                                <input
                                    class="sg-checkbox"
                                    type="checkbox"
                                    name="equipos[]"
                                    value="<?= escapar((string) $equipo['id']) ?>"
                                >

                                <span>
                                    <?= escapar(
                                        $equipo['codigo']
                                        . ' — '
                                        . $equipo['nombre']
                                    ) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <p class="sg-field-description">
                    Seleccione uno o varios equipos.
                    La disponibilidad del intervalo se valida al guardar.
                </p>

                <button class="sg-button sg-button-loan-create" type="submit">
                    Solicitar préstamo
                </button>
            </form>
        </details>

        <section class="sg-loan-list" aria-labelledby="sg-loan-list-title">
            <h2 id="sg-loan-list-title">Préstamos registrados</h2>

            <p class="sg-results-count">
                Registros: <?= escapar((string) $totalRegistros) ?>
            </p>

            <?php if ($prestamos === []): ?>
                <p>No hay préstamos registrados.</p>
            <?php endif; ?>

            <?php foreach ($prestamos as $prestamo): ?>
                <article class="sg-loan-card">
                    <div class="sg-loan-card-heading">
                        <h3>
                            Préstamo #<?= escapar((string) $prestamo['id']) ?>
                        </h3>

                        <span class="sg-loan-status">
                            <?= escapar(ucfirst($prestamo['estado'])) ?>
                        </span>
                    </div>

                    <dl class="sg-loan-information">
                        <dt>Usuario</dt>
                        <dd><?= escapar($prestamo['usuario_nombre']) ?></dd>

                        <dt>Registrado por</dt>
                        <dd><?= escapar($prestamo['operador_nombre']) ?></dd>

                        <dt>Aprobado por</dt>
                        <dd>
                            <?= escapar($prestamo['aprobado_nombre'] ?? 'Sin aprobación') ?>
                        </dd>

                        <dt>Inicio</dt>
                        <dd><?= escapar($prestamo['fecha_inicio']) ?></dd>

                        <dt>Fin previsto</dt>
                        <dd><?= escapar($prestamo['fecha_fin']) ?></dd>
                    </dl>

                    <h4 class="sg-loan-equipment-title">Equipos incluidos</h4>

                    <ul class="sg-loan-equipment-list">
                        <?php foreach ($prestamo['equipos'] as $equipo): ?>
                            <li>
                                <?= escapar(
                                    $equipo['codigo'] . ' — ' . $equipo['nombre']
                                ) ?>

                                <?php if ($equipo['fecha_devolucion'] !== null): ?>
                                    <span class="sg-loan-return-date">
                                        Devuelto:
                                        <?= escapar($equipo['fecha_devolucion']) ?>
                                    </span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (
                        $prestamo['estado'] === 'solicitado'
                        && (int) $prestamo['operador_id'] === (int) $usuario['id']
                    ): ?>
                        <p class="sg-loan-approval-note">
                            La aprobación debe realizarla otro usuario.
                        </p>
                    <?php endif; ?>

                    <div class="sg-loan-actions">
                        <?php foreach (
                            $accionesPorEstado[$prestamo['estado']]
                            as $estado => $etiqueta
                        ): ?>
                            <?php if (
                                $estado === 'aprobado'
                                && (int) $prestamo['operador_id'] === (int) $usuario['id']
                            ): ?>
                                <?php continue; ?>
                            <?php endif; ?>

                            <form
                                class="sg-loan-state-form"
                                action="/actions/prestamo-opciones.php"
                                method="post"
                            >
                                <input type="hidden" name="accion" value="estado">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escapar($tokenCsrf) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= escapar((string) $prestamo['id']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="estado"
                                    value="<?= escapar($estado) ?>"
                                >

                                <button class="sg-button" type="submit">
                                    <?= escapar($etiqueta) ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <nav class="sg-pagination" aria-label="Paginación de préstamos">
            <?php if ($pagina > 1): ?>
                <a class="sg-link" href="<?= escapar($urlAnterior) ?>">
                    Anterior
                </a>
            <?php endif; ?>

            <span class="sg-pagination-summary">
                Página <?= escapar((string) $pagina) ?>
                de <?= escapar((string) $totalPaginas) ?>
            </span>

            <?php if ($pagina < $totalPaginas): ?>
                <a class="sg-link" href="<?= escapar($urlSiguiente) ?>">
                    Siguiente
                </a>
            <?php endif; ?>
        </nav>
    </main>
</body>
</html>