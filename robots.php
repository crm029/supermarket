<?php
declare(strict_types=1);
require_once __DIR__ . '/app/seo.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$seo = app_seo_context();
if (!$seo) {
    http_response_code(404);
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Allow: /product/\n";
echo "Allow: /category/\n";
echo "Disallow: /admin.php\n";
echo "Disallow: /api/\n";
echo "Disallow: /payment-result.php\n";
echo "Disallow: /product.php\n";
echo "Disallow: /category.php\n";
echo "Disallow: /*?*\n";
echo "Sitemap: " . app_seo_url($seo,'sitemap.xml') . "\n";
