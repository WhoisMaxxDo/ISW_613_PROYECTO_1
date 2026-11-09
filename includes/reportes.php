<?php

function leerRangoReporte(): array
{
    $desdeTexto = $_GET['desde'] ?? date('Y-m-01');
    $hastaTexto = $_GET['hasta'] ?? date('Y-m-d');

    if (!is_string($desdeTexto) || !is_string($hastaTexto)) {
        throw new InvalidArgumentException(
            'Las fechas no son validas.'
        );
    }

    $zona = new DateTimeZone('America/Costa_Rica');

    $desde = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $desdeTexto,
        $zona
    );

    $hasta = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $hastaTexto,
        $zona
    );

    if (
        $desde === false
        || $hasta === false
        || $desde->format('Y-m-d') !== $desdeTexto
        || $hasta->format('Y-m-d') !== $hastaTexto
    ) {
        throw new InvalidArgumentException(
            'Las fechas deben tener el formato AAAA-MM-DD y ser validas.'
        );
    }

    if ($desde > $hasta) {
        throw new InvalidArgumentException(
            'La fecha inicial no puede ser posterior a la final.'
        );
    }

    if (
        (int) $desde->format('Y') < 1000
        || (int) $hasta->format('Y') > 9998
    ) {
        throw new InvalidArgumentException(
            'Las fechas estan fuera del rango admitido.'
        );
    }

    return [
        'desde' => $desdeTexto,
        'hasta' => $hastaTexto,
        'inicio_sql' => $desde->format('Y-m-d H:i:s'),
        'fin_sql' => $hasta->modify('+1 day')->format('Y-m-d H:i:s'),
    ];
}

function consultarEquiposMasPrestados(PDO $pdo, array $rango): array
{
    $consulta = $pdo->prepare(
        "SELECT
            equipos.id,
            equipos.codigo,
            equipos.nombre,
            COUNT(DISTINCT prestamos.id) AS cantidad
         FROM prestamos
         INNER JOIN prestamo_detalle
            ON prestamos.id = prestamo_detalle.prestamo_id
         INNER JOIN equipos
            ON prestamo_detalle.equipo_id = equipos.id
         WHERE prestamos.estado IN ('entregado', 'devuelto')
           AND prestamos.fecha_inicio >= :desde
           AND prestamos.fecha_inicio < :hasta
         GROUP BY equipos.id, equipos.codigo, equipos.nombre
         ORDER BY cantidad DESC, equipos.id ASC"
    );

    $consulta->execute([
        ':desde' => $rango['inicio_sql'],
        ':hasta' => $rango['fin_sql'],
    ]);

    return $consulta->fetchAll();
}

function neutralizarCeldaCsv(string $texto): string
{
    if (
        preg_match(
            '/\A(?:[\x00-\x20]*[=+\-@]|[\t\r\n])/',
            $texto
        ) === 1
    ) {
        return "'" . $texto;
    }

    return $texto;
}