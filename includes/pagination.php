<?php

function obtenerCantidadPorPagina(string $nombreCookie): int
{
    $valor = $_COOKIE[$nombreCookie] ?? null;
    if (
        !is_string($valor)
        || !in_array($valor, ['5', '10', '20', '50'], true)
    ) {
        return 10;
    }

    return (int) $valor;
}