<?php

function iniciarSesion(): void
{
    $esHttps = isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_name('sigel_session');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $esHttps,
        'httponly' => true,
        'samesite' => 'Lax',  
    ]);

    session_start();
}

function cerrarSesion(): void
{
    $_SESSION = [];

    $PARAMETROS = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $PARAMETROS['path'],
        'domain' => $PARAMETROS['domain'],
        'secure' => $PARAMETROS['secure'],
        'httponly' => $PARAMETROS['httponly'],
        'samesite' => $PARAMETROS['samesite'],
    ]);

    session_destroy();
}