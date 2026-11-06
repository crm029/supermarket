<?php
declare(strict_types=1);
require_once __DIR__ . '/app/seo.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$seo = app_seo_context();
if (!$seo) {
    http_response_code(404);
    echo '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>';
    exit;
}
$productCount = app_seo_product_count($seo);
$productPageSize = 45000;
$productPages = (int)ceil($productCount / $productPageSize);
$maps = [app_seo_url($seo,'sitemap-core.xml')];
for ($page = 1; $page <= $productPages; $page++) {
    $maps[] = app_seo_url($seo,'sitemap-products-'.$page.'.xml');
}
$today = gmdate('Y-m-d');
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($maps as $location) {
    $xml .= '<sitemap><loc>' . htmlspecialchars($location,ENT_XML1|ENT_QUOTES,'UTF-8') . '</loc><lastmod>' . $today . '</lastmod></sitemap>' . "\n";
}
$xml .= '</sitemapindex>';
echo $xml;
