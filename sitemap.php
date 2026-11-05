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
$xml = new XMLWriter();
$xml->openMemory();
$xml->startDocument('1.0','UTF-8');
$xml->startElement('urlset');
$xml->writeAttribute('xmlns','http://www.sitemaps.org/schemas/sitemap/0.9');
foreach ($urls as $url) {
    $xml->startElement('url');
    $xml->writeElement('loc',$url['loc']);
    $xml->writeElement('lastmod',$url['lastmod']);
    $xml->endElement();
}
$xml->endElement();
$xml->endDocument();
echo $xml->outputMemory();
