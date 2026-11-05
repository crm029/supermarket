<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (preg_match('#^/product/([1-9][0-9]*)(?:/([a-z0-9][a-z0-9-]{0,179}))?/?$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    if (isset($matches[2]) && $matches[2] !== '') $_GET['slug'] = $matches[2];
    require __DIR__ . '/../product.php';
    return true;
}

if (preg_match('#^/category/([a-z0-9][a-z0-9-]{0,139})/?$#', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/../category.php';
    return true;
}

if ($path === '/robots.txt') {
    require __DIR__ . '/../robots.php';
    return true;
}
if ($path === '/sitemap.xml') {
    require __DIR__ . '/../sitemap.php';
    return true;
}

return false;
