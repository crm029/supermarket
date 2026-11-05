<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('#(?:^|/)api/v1(?:/|$)#', $path)) {
    require dirname(__DIR__) . '/api/v1/index.php';
    return true;
}
return false;
