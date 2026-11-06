<?php
declare(strict_types=1);
require_once __DIR__ . '/app/seo.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$seo = app_seo_context();
if (!$seo) {
    http_response_code(404);
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    exit;
}
$page = filter_var($_GET['page'] ?? null,FILTER_VALIDATE_INT);
$productCount = app_seo_product_count($seo);
$pageSize = 45000;
$pageCount = (int)ceil($productCount / $pageSize);
if ($page === false || $page === null || $page < 1 || $page > $pageCount) {
    http_response_code(404);
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    exit;
}
$products = app_seo_product_list($seo,$pageSize,($page-1)*$pageSize);
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($products as $product) {
    $location = app_seo_url($seo,app_seo_product_path($product));
    $lastmod = app_seo_lastmod($product['updated_at'] ?? $product['created_at'] ?? null);
    $xml .= '<url><loc>' . htmlspecialchars($location,ENT_XML1|ENT_QUOTES,'UTF-8') . '</loc><lastmod>' . htmlspecialchars($lastmod,ENT_XML1|ENT_QUOTES,'UTF-8') . '</lastmod></url>' . "\n";
}
$xml .= '</urlset>';
echo $xml;
