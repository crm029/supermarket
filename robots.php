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
echo "Disallow: /platform.php\n";
echo "Disallow: /staff.php\n";
echo "Disallow: /notifications.php\n";
echo "Disallow: /api/\n";
echo "Disallow: /app/\n";
echo "Disallow: /database/\n";
echo "Disallow: /bin/\n";
echo "Disallow: /tests/\n";
echo "Disallow: /docs/\n";
echo "Disallow: /scripts/\n";
echo "Disallow: /.env\n";
echo "Disallow: /README.md\n";
echo "Disallow: /THIRD_PARTY_NOTICES.md\n";
echo "Disallow: /payment-result.php\n";
echo "Allow: /product.php?id=\n";
echo "Allow: /category.php?slug=\n";
echo "Disallow: /*?*\n";
echo "Sitemap: " . app_seo_url($seo,'sitemap.xml') . "\n";
