<?php

function leerEquiposRecientes(): array
{
    $cookie = $_COOKIE['sigel_equipos_recientes'] ?? null;

    if (
        !is_string($cookie)
        || $cookie === ''
        || strlen($cookie) > 54
    ) {
        return [];
    }

    $valores = explode(',', $cookie);

    if (count($valores) > 5) {
        return [];
    }

    $ids = [];

    foreach ($valores as $valor) {
        $id = filter_var(
            $valor,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 4294967295,
                ],
            ]
        );

        if ($id === false) {
            return [];
        }

        if (!in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return $ids;
}

function registrarEquipoReciente(int $equipoId): void
{
    $ids = leerEquiposRecientes();

    $ids = array_values(
        array_filter(
            $ids,
            fn (int $id): bool => $id !== $equipoId
        )
    );

    array_unshift($ids, $equipoId);

    $ids = array_slice($ids, 0, 5);

    $contenido = implode(',', $ids);

    $parametrosSesion = session_get_cookie_params();

    setcookie('sigel_equipos_recientes', $contenido, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $parametrosSesion['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_COOKIE['sigel_equipos_recientes'] = $contenido;
}

function consultarEquiposRecientes(PDO $pdo): array
{
    $ids = leerEquiposRecientes();

    if ($ids === []) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $consulta = $pdo->prepare(
        'SELECT id, codigo, nombre
         FROM equipos
         WHERE eliminado_en IS NULL
           AND id IN (' . $marcadores . ')'
    );

    $consulta->execute($ids);

    $encontrados = [];

    foreach ($consulta->fetchAll() as $equipo) {
        $encontrados[(int) $equipo['id']] = $equipo;
    }

    $ordenados = [];

    foreach ($ids as $id) {
        if (isset($encontrados[$id])) {
            $ordenados[] = $encontrados[$id];
        }
    }

    return $ordenados;
}