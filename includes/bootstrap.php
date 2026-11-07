<?php

error_reporting(E_ALL);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');

date_default_timezone_set('America/Costa_Rica');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

iniciarSesion();