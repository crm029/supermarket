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
$urls = [[
    'loc' => app_seo_url($seo),
    'lastmod' => gmdate('Y-m-d'),
]];
foreach (app_seo_category_list($seo) as $category) {
    $slug = rawurlencode((string)$category['slug']);
    $urls[] = [
        'loc' => app_seo_url($seo,'category/'.$slug),
        'lastmod' => app_seo_lastmod($category['updated_at'] ?? null),
    ];
}
foreach (app_seo_product_list($seo) as $product) {
    $urls[] = [
        'loc' => app_seo_url($seo,'product/'.(int)$product['id']),
        'lastmod' => app_seo_lastmod($product['updated_at'] ?? $product['created_at'] ?? null),
    ];
}
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    $xml .= '<url><loc>' . htmlspecialchars($url['loc'],ENT_XML1|ENT_QUOTES,'UTF-8') . '</loc>';
    $xml .= '<lastmod>' . htmlspecialchars($url['lastmod'],ENT_XML1|ENT_QUOTES,'UTF-8') . '</lastmod></url>' . "\n";
}
$xml .= '</urlset>';
echo $xml;
