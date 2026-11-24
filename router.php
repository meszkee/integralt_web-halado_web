<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if (preg_match('#^/api(/.*)?$#', $path) === 1) {
    require __DIR__ . '/api/api.php';
    return true;
}

if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/index.php';
return true;
