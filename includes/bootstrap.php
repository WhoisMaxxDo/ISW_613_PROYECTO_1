<?php

error_reporting(E_ALL);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');

date_default_timezone_set('America/Costa_Rica');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/remember.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/equipos-recientes.php';
require_once __DIR__ . '/prestamos.php';
require_once __DIR__ . '/reportes.php';

iniciarSesion();