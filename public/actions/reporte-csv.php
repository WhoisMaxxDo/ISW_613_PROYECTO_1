<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    $pdo = conectarBaseDatos();

    $usuario = exigirAutenticacion($pdo);

    exigirRol($usuario, ['administrador']);

    $rango = leerRangoReporte();

    $equipos = consultarEquiposMasPrestados($pdo, $rango);

    $salida = fopen('php://output', 'w');

    if ($salida === false) {
        throw new RuntimeException('No se pudo abrir la salida CSV.');
    }

    $nombreArchivo = 'sigel-equipos-'
        . $rango['desde']
        . '-'
        . $rango['hasta']
        . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');

    header(
        'Content-Disposition: attachment; filename="'
        . $nombreArchivo
        . '"'
    );

    if (fputcsv(
        $salida,
        ['Codigo', 'Equipo', 'Cantidad de prestamos'],
        ',',
        '"',
        ''
    ) === false) {
        throw new RuntimeException('No se pudo escribir la cabecera CSV.');
    }

    foreach ($equipos as $equipo) {
        $fila = [
            neutralizarCeldaCsv($equipo['codigo']),
            neutralizarCeldaCsv($equipo['nombre']),
            neutralizarCeldaCsv((string) $equipo['cantidad']),
        ];

        if (fputcsv($salida, $fila, ',', '"', '') === false) {
            throw new RuntimeException('No se pudo escribir una fila CSV.');
        }
    }

    fclose($salida);
    exit;
} catch (InvalidArgumentException $error) {
    http_response_code(400);

    exit(escapar($error->getMessage()));
} catch (Throwable $error) {
    error_log((string) $error);

    if (!headers_sent()) {
        http_response_code(503);

        exit('No se pudo generar el archivo.');
    }

    exit;
}