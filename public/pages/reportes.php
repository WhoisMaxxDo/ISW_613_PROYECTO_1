<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador']);

    $rango = leerRangoReporte();

    $equipos = consultarEquiposMasPrestados($pdo, $rango);

    $urlCsv = '/actions/reporte-csv.php?' . http_build_query([
        'desde' => $rango['desde'],
        'hasta' => $rango['hasta'],
    ]);
} catch (InvalidArgumentException $error) {
    http_response_code(400);

    exit(escapar($error->getMessage()));
} catch (Throwable $error) {
    error_log((string) $error);

    http_response_code(503);

    exit('El reporte no esta disponible en este momento.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reportes | SIGEL</title>

    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="sg-page-reports">
    <header class="sg-header">
        <h1 class="sg-header-title">SIGEL</h1>
        <p class="sg-header-description">Reportes del laboratorio</p>
    </header>

    <main class="sg-panel-main">
        <div class="sg-section-heading">
            <h2 class="sg-section-title">Equipos más prestados</h2>

            <a class="sg-link" href="/pages/panel.php">
                Volver al panel
            </a>
        </div>

        <form
            class="sg-report-filter"
            action="/pages/reportes.php"
            method="get"
        >
            <div class="sg-field">
                <label class="sg-label" for="sg-report-start">
                    Desde
                </label>

                <input
                    class="sg-input"
                    type="date"
                    id="sg-report-start"
                    name="desde"
                    value="<?= escapar($rango['desde']) ?>"
                    required
                >
            </div>

            <div class="sg-field">
                <label class="sg-label" for="sg-report-end">
                    Hasta
                </label>

                <input
                    class="sg-input"
                    type="date"
                    id="sg-report-end"
                    name="hasta"
                    value="<?= escapar($rango['hasta']) ?>"
                    required
                >
            </div>

            <button class="sg-button" type="submit">
                Consultar
            </button>
        </form>

        <p class="sg-report-description">
            Préstamos entregados o devueltos cuya fecha de inicio
            pertenece al rango seleccionado.
        </p>

        <p class="sg-report-export">
            <a class="sg-link" href="<?= escapar($urlCsv) ?>">
                Exportar a CSV
            </a>
        </p>

        <div class="sg-table-container">
            <table class="sg-table sg-table-report">
                <caption class="sg-table-caption">
                    Del <?= escapar($rango['desde']) ?>
                    al <?= escapar($rango['hasta']) ?>
                </caption>

                <thead class="sg-table-head">
                    <tr>
                        <th scope="col">Código</th>
                        <th scope="col">Equipo</th>
                        <th scope="col">Cantidad de préstamos</th>
                    </tr>
                </thead>

                <tbody class="sg-table-body">
                    <?php if ($equipos === []): ?>
                        <tr>
                            <td class="sg-table-empty" colspan="3">
                                No hay préstamos entregados o devueltos
                                para este rango.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($equipos as $equipo): ?>
                        <tr>
                            <td><?= escapar($equipo['codigo']) ?></td>
                            <td><?= escapar($equipo['nombre']) ?></td>
                            <td>
                                <?= escapar((string) $equipo['cantidad']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>