<?php

function opcionesCookieRecordar(int $expiracion): array {
    $esHttps = isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && $_SERVER['HTTPS'] !== 'off';

    return [
        'expires' => $expiracion,
        'path' => '/',
        'secure' => $esHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}


function leerCookieRecordar(): ?array {

    $cookie = $_COOKIE['sigel_remember'] ?? null;

    if (!is_string($cookie)) {
        return null;
    }

    if (
        preg_match(
            '/\A([a-f0-9]{18}):([a-f0-9]{64})\z/',
            $cookie,
            $partes
        ) !== 1
    ) {
        return null;
    }

    return [
        'selector' => $partes[1],
        'validador' => $partes[2],
    ];
}

function eliminarCookieRecordar(): void 
{
    setcookie(
        'sigel_remember',
        '',
        opcionesCookieRecordar(time() - 3600)
    );

    unset($_COOKIE['sigel_remember']);
}

function crearRecordatorio(PDO $pdo, int $usuarioId): void
{
    $selector = bin2hex(random_bytes(9));
    $validador = bin2hex(random_bytes(32));

    $expiracion = time() + 60 * 60 * 24 * 14;

    $consulta = $pdo->prepare(
        'INSERT INTO auth_tokens (
            usuario_id,
            selector,
            validator_hash,
            expira_en
        ) VALUES (
            :usuario_id,
            :selector,
            :validator_hash,
            :expira_en
        )'
    );

    $consulta->execute([
        ':usuario_id' => $usuarioId,
        ':selector' => $selector,
        ':validator_hash' => hash('sha256', $validador),
        ':expira_en' => date('Y-m-d H-i-s', $expiracion),
    ]);

    $_SESSION['remember_selector'] = $selector;

    setcookie(
        'sigel_remember',
        $selector . ':' . $validador,
        opcionesCookieRecordar($expiracion)
    );
}

function revocarRecordatorio(PDO $pdo): void
{
    $selectorSesion = $_SESSION['remember_selector'] ?? null;

    if (is_string($selectorSesion)) {
        $consulta = $pdo->prepare(
            'DELETE FROM auth_tokens
             WHERE selector = :selector'
        );

        $consulta->execute([
            ':selector' => $selectorSesion,
        ]);
    }

    $cookie = leerCookieRecordar();

    if ($cookie !== null) {
        $consulta = $pdo->prepare(
            'DELETE FROM auth_tokens
             WHERE selector = :selector
               AND validator_hash = :validator_hash'
        );

        $consulta->execute([
            ':selector' => $cookie['selector'],
            ':validator_hash' => hash('sha256', $cookie['validador']),
        ]);
    }

    unset($_SESSION['remember_selector']);

    eliminarCookieRecordar();
}


function recuperarSesionRecordada(PDO $pdo): bool
{
    $cookie = leerCookieRecordar();

    if ($cookie === null) {
        if (isset($_COOKIE['sigel_remember'])) {
            eliminarCookieRecordar();
        }

        return false;
    }

    try {
        $pdo->beginTransaction();

        $consulta = $pdo->prepare(
            'SELECT
                auth_tokens.id,
                auth_tokens.usuario_id,
                auth_tokens.validator_hash,
                auth_tokens.expira_en,
                usuarios.activo,
                usuarios.eliminado_en
             FROM auth_tokens
             INNER JOIN usuarios
                ON auth_tokens.usuario_id = usuarios.id
             WHERE auth_tokens.selector = :selector
             FOR UPDATE'
        );

        $consulta->execute([
            ':selector' => $cookie['selector'],
        ]);

        $token = $consulta->fetch();

        if ($token === false) {
            $pdo->rollBack();

            eliminarCookieRecordar();

            return false;
        }

        $hashRecibido = hash('sha256', $cookie['validador']);

        $esValido = hash_equals(
            $token['validator_hash'],
            $hashRecibido
        );

        $estaVigente = strtotime($token['expira_en']) > time();

        $usuarioHabilitado = (int) $token['activo'] === 1
            && $token['eliminado_en'] === null;

        if (!$esValido || !$estaVigente || !$usuarioHabilitado) {
            $eliminar = $pdo->prepare(
                'DELETE FROM auth_tokens WHERE id = :id'
            );

            $eliminar->execute([
                ':id' => $token['id'],
            ]);

            $pdo->commit();

            eliminarCookieRecordar();

            return false;
        }

        $nuevoSelector = bin2hex(random_bytes(9));
        $nuevoValidador = bin2hex(random_bytes(32));

        $actualizar = $pdo->prepare(
            'UPDATE auth_tokens
             SET selector = :selector,
                 validator_hash = :validator_hash
             WHERE id = :id'
        );

        $actualizar->execute([
            ':selector' => $nuevoSelector,
            ':validator_hash' => hash('sha256', $nuevoValidador),
            ':id' => $token['id'],
        ]);

        $pdo->commit();

        if (!session_regenerate_id(true)) {
            eliminarCookieRecordar();

            throw new RuntimeException('No se pudo renovar la sesion.');
        }

        $_SESSION = [];

        $_SESSION['usuario_id'] = (int) $token['usuario_id'];
        $_SESSION['ultima_actividad'] = time();
        $_SESSION['remember_selector'] = $nuevoSelector;

        setcookie(
            'sigel_remember',
            $nuevoSelector . ':' . $nuevoValidador,
            opcionesCookieRecordar(strtotime($token['expira_en']))
        );

        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}