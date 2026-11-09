<?php

function interpretarFechaPrestamo(string $valor): DateTimeImmutable
{
    $fecha = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i',
        $valor,
        new DateTimeZone('America/Costa_Rica')
    );

    if (
        $fecha === false
        || $fecha->format('Y-m-d\TH:i') !== $valor
    ) {
        throw new InvalidArgumentException('La fecha no es valida.');
    }

    return $fecha;
}

function validarIdsEquipos(mixed $valores): array
{
    if (!is_array($valores) || $valores === []) {
        throw new InvalidArgumentException('Seleccione al menos un equipo.');
    }

    $ids = [];

    foreach ($valores as $valor) {
        if (!is_string($valor)) {
            throw new InvalidArgumentException('Los equipos no son validos.');
        }

        $id = filter_var(
            $valor,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            throw new InvalidArgumentException('Los equipos no son validos.');
        }

        if (!in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    sort($ids, SORT_NUMERIC);

    return $ids;
}

function bloquearEquiposPrestamo(PDO $pdo, array $ids): array
{
    $consulta = $pdo->prepare(
        'SELECT
            equipos.id,
            equipos.estado,
            equipos.eliminado_en,
            categorias.dias_max_prestamo,
            categorias.eliminado_en AS categoria_eliminada
         FROM equipos
         INNER JOIN categorias ON equipos.categoria_id = categorias.id
         WHERE equipos.id = :id
         FOR UPDATE'
    );

    $equipos = [];

    foreach ($ids as $id) {
        $consulta->execute([
            ':id' => $id,
        ]);

        $equipo = $consulta->fetch();

        if ($equipo === false) {
            throw new DomainException('Uno de los equipos no existe.');
        }

        $equipos[] = $equipo;
    }

    return $equipos;
}

function comprobarDisponibilidadPrestamo(
    PDO $pdo,
    array $equipos,
    string $inicio,
    string $fin,
    int $prestamoExcluido = 0
): void {
    $duracion = strtotime($fin) - strtotime($inicio);

    $consulta = $pdo->prepare(
        "SELECT prestamos.id
         FROM prestamos
         INNER JOIN prestamo_detalle
            ON prestamos.id = prestamo_detalle.prestamo_id
         WHERE prestamo_detalle.equipo_id = :equipo_id
           AND prestamos.id <> :prestamo_excluido
           AND prestamos.estado IN (
               'solicitado', 'aprobado', 'entregado'
           )
           AND prestamos.fecha_inicio < :fecha_fin
           AND prestamos.fecha_fin > :fecha_inicio
         LIMIT 1
         FOR UPDATE"
    );

    foreach ($equipos as $equipo) {
        if (
            $equipo['estado'] !== 'disponible'
            || $equipo['eliminado_en'] !== null
            || $equipo['categoria_eliminada'] !== null
        ) {
            throw new DomainException(
                'Uno de los equipos no esta disponible para prestar.'
            );
        }

        if (
            $duracion > (int) $equipo['dias_max_prestamo'] * 86400
        ) {
            throw new DomainException(
                'La duracion supera el limite de una categoria.'
            );
        }

        $consulta->execute([
            ':equipo_id' => $equipo['id'],
            ':prestamo_excluido' => $prestamoExcluido,
            ':fecha_fin' => $fin,
            ':fecha_inicio' => $inicio,
        ]);

        if ($consulta->fetch() !== false) {
            throw new DomainException(
                'Uno de los equipos tiene otro prestamo en ese intervalo.'
            );
        }
    }
}

function registrarEventoPrestamo(
    PDO $pdo,
    int $actorId,
    int $prestamoId,
    string $accion,
    ?array $antes,
    array $despues
): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        $ip = null;
    }

    $consulta = $pdo->prepare(
        "INSERT INTO bitacora (
            usuario_id,
            accion,
            entidad,
            entidad_id,
            valores_antes,
            valores_despues,
            ip
         ) VALUES (
            :usuario_id,
            :accion,
            'prestamos',
            :entidad_id,
            :valores_antes,
            :valores_despues,
            :ip
         )"
    );

    $consulta->execute([
        ':usuario_id' => $actorId,
        ':accion' => $accion,
        ':entidad_id' => $prestamoId,
        ':valores_antes' => $antes === null
            ? null
            : json_encode($antes, JSON_THROW_ON_ERROR),
        ':valores_despues' => json_encode(
            $despues,
            JSON_THROW_ON_ERROR
        ),
        ':ip' => $ip,
    ]);
}

function crearPrestamo(
    PDO $pdo,
    int $actorId,
    int $usuarioId,
    array $ids,
    DateTimeImmutable $inicio,
    DateTimeImmutable $fin
): int {
    if ($inicio <= new DateTimeImmutable('now')) {
        throw new InvalidArgumentException(
            'El inicio debe estar en el futuro.'
        );
    }

    if ($fin <= $inicio) {
        throw new InvalidArgumentException(
            'La fecha final debe ser posterior al inicio.'
        );
    }

    $fechaInicio = $inicio->format('Y-m-d H:i:s');
    $fechaFin = $fin->format('Y-m-d H:i:s');

    $pdo->beginTransaction();

    $consultaUsuario = $pdo->prepare(
        'SELECT id
         FROM usuarios
         WHERE id = :id
           AND activo = 1
           AND eliminado_en IS NULL
         FOR UPDATE'
    );

    $consultaUsuario->execute([
        ':id' => $usuarioId,
    ]);

    if ($consultaUsuario->fetch() === false) {
        throw new DomainException(
            'El usuario seleccionado no esta disponible.'
        );
    }

    $equipos = bloquearEquiposPrestamo($pdo, $ids);

    comprobarDisponibilidadPrestamo(
        $pdo,
        $equipos,
        $fechaInicio,
        $fechaFin
    );

    $guardar = $pdo->prepare(
        "INSERT INTO prestamos (
            usuario_id,
            operador_id,
            estado,
            fecha_inicio,
            fecha_fin
         ) VALUES (
            :usuario_id,
            :operador_id,
            'solicitado',
            :fecha_inicio,
            :fecha_fin
         )"
    );

    $guardar->execute([
        ':usuario_id' => $usuarioId,
        ':operador_id' => $actorId,
        ':fecha_inicio' => $fechaInicio,
        ':fecha_fin' => $fechaFin,
    ]);

    $prestamoId = (int) $pdo->lastInsertId();

    $detalle = $pdo->prepare(
        'INSERT INTO prestamo_detalle (prestamo_id, equipo_id)
         VALUES (:prestamo_id, :equipo_id)'
    );

    foreach ($ids as $equipoId) {
        $detalle->execute([
            ':prestamo_id' => $prestamoId,
            ':equipo_id' => $equipoId,
        ]);
    }

    registrarEventoPrestamo(
        $pdo,
        $actorId,
        $prestamoId,
        'crear',
        null,
        [
            'estado' => 'solicitado',
            'usuario_id' => $usuarioId,
            'equipos' => $ids,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ]
    );

    $pdo->commit();

    return $prestamoId;
}

function cambiarEstadoPrestamo(
    PDO $pdo,
    int $actorId,
    int $prestamoId,
    string $nuevoEstado
): void {
    $consultaDetalle = $pdo->prepare(
        'SELECT equipo_id
         FROM prestamo_detalle
         WHERE prestamo_id = :id
         ORDER BY equipo_id'
    );

    $consultaDetalle->execute([
        ':id' => $prestamoId,
    ]);

    $ids = array_map(
        'intval',
        $consultaDetalle->fetchAll(PDO::FETCH_COLUMN)
    );

    if ($ids === []) {
        throw new DomainException(
            'El prestamo no existe o no contiene equipos.'
        );
    }

    $pdo->beginTransaction();

    $equipos = bloquearEquiposPrestamo($pdo, $ids);

    $consulta = $pdo->prepare(
        'SELECT id, operador_id, aprobado_por, estado, fecha_inicio, fecha_fin
         FROM prestamos
         WHERE id = :id
         FOR UPDATE'
    );

    $consulta->execute([
        ':id' => $prestamoId,
    ]);

    $prestamo = $consulta->fetch();

    if ($prestamo === false) {
        throw new DomainException('El prestamo no existe.');
    }

    $transiciones = [
        'solicitado' => ['aprobado', 'cancelado'],
        'aprobado' => ['entregado', 'cancelado'],
        'entregado' => ['devuelto'],
        'devuelto' => [],
        'cancelado' => [],
    ];

    if (
        !in_array(
            $nuevoEstado,
            $transiciones[$prestamo['estado']],
            true
        )
    ) {
        throw new DomainException(
            'El cambio de estado no esta permitido.'
        );
    }

    if (
        $nuevoEstado === 'aprobado'
        && (int) $prestamo['operador_id'] === $actorId
    ) {
        throw new DomainException(
            'Quien registra el prestamo no puede aprobarlo.'
        );
    }

    if (in_array($nuevoEstado, ['aprobado', 'entregado'], true)) {
        comprobarDisponibilidadPrestamo(
            $pdo,
            $equipos,
            $prestamo['fecha_inicio'],
            $prestamo['fecha_fin'],
            $prestamoId
        );
    }

    if ($nuevoEstado === 'entregado') {
        $consultaEntregados = $pdo->prepare(
            "SELECT prestamos.id
             FROM prestamos
             INNER JOIN prestamo_detalle
                ON prestamos.id = prestamo_detalle.prestamo_id
             WHERE prestamo_detalle.equipo_id = :equipo_id
               AND prestamos.id <> :prestamo_id
               AND prestamos.estado = 'entregado'
               AND prestamo_detalle.fecha_devolucion IS NULL
             LIMIT 1
             FOR UPDATE"
        );

        foreach ($ids as $equipoId) {
           