<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

if (PHP_VERSION_ID < 80200) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'El servidor no cumple la versión mínima requerida.';
    exit;
}

$basePath = dirname(__DIR__);
$autoload = $basePath . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Aplicación temporalmente no disponible.';
    exit;
}

require $autoload;

App\Core\App::bootstrap($basePath)->run();
