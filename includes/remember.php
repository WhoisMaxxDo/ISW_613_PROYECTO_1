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