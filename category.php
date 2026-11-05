<?php
declare(strict_types=1);
require_once __DIR__ . '/app/seo.php';

$seo = app_seo_context();
if (!$seo) { http_response_code(404); exit('فروشگاه پیدا نشد.'); }
$slug = $_GET['slug'] ?? '';
if (!is_string($slug) || $slug === '' || mb_strlen($slug)>140) { http_response_code(404); exit('دسته‌بندی پیدا نشد.'); }

$stmt = $seo['db']->prepare(
    'SELECT c.id,c.title,c.slug,c.description,c.seo_title,c.seo_description,c.updated_at,c.image_media_id,m.storage_key,m.alt_text
     FROM categories c
     LEFT JOIN media_assets m ON m.tenant_id=c.tenant_id AND m.id=c.image_media_id
     WHERE c.tenant_id=? AND c.slug=? AND c.is_active=1
     LIMIT 1'
);
$stmt->execute([(int)$seo['tenant']['id'],$slug]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$category) {
    $sourcePath = '/category/'.rawurlencode($slug);
    $redirectQuery = $seo['db']->prepare(
        'SELECT target_path FROM seo_url_redirects WHERE tenant_id=? AND source_path=? LIMIT 1'
    );
    $redirectQuery->execute([(int)$seo['tenant']['id'],$sourcePath]);
    $targetPath = (string)($redirectQuery->fetchColumn() ?: '');
    if ($targetPath !== '' && $targetPath !== $sourcePath &&
        preg_match('#^/category/[a-z0-9][a-z0-9-]{0,139}$#',$targetPath)) {
        header('Location: '.app_seo_url($seo,ltrim($targetPath,'/')),true,301);
        exit;
    }
    http_response_code(404);
    exit('دسته‌بندی پیدا نشد.');
}

$productsQuery = $seo['db']->prepare(
    "SELECT p.id,p.title,p.sku,p.seo_slug,p.brand,p.description,p.unit_label,p.price_rial,p.image_media_id,
            m.storage_key,m.alt_text AS image_alt_text,
            COALESCE((SELECT SUM(sl.on_hand-sl.reserved)
                      FROM stock_levels sl
                      JOIN warehouses w ON w.tenant_id=sl.tenant_id AND w.id=sl.warehouse_id
                      WHERE sl.tenant_id=p.tenant_id AND sl.product_id=p.id AND w.is_active=1),0)
            + COALESCE((SELECT SUM(vsl.on_hand-vsl.reserved)
                        FROM products v
                        JOIN stock_levels vsl ON vsl.tenant_id=v.tenant_id AND vsl.product_id=v.id
                        JOIN warehouses vw ON vw.tenant_id=vsl.tenant_id AND vw.id=vsl.warehouse_id
                        WHERE v.tenant_id=p.tenant_id AND v.parent_product_id=p.id
                          AND v.product_kind='variant' AND v.is_active=1 AND vw.is_active=1),0) AS available_quantity
     FROM products p
     LEFT JOIN media_assets m ON m.tenant_id=p.tenant_id AND m.id=p.image_media_id
     WHERE p.tenant_id=? AND p.category_id=? AND p.is_active=1 AND p.product_kind<>'variant'
     ORDER BY p.title,p.id
     LIMIT 200"
);
$productsQuery->execute([(int)$seo['tenant']['id'],(int)$category['id']]);
$products = $productsQuery->fetchAll(PDO::FETCH_ASSOC);
$profile = app_seo_store_profile($seo);
$storeName = (string)($profile['name'] ?? $seo['tenant']['name']);
$visibleDescription = trim(strip_tags((string)($category['description'] ?? '')));
$defaultDescription = $visibleDescription !== '' ? $visibleDescription : ('مشاهده محصولات دسته '.$category['title'].' و بررسی قیمت و موجودی در '.$storeName.'.');
$metaDescription = trim(strip_tags((string)($category['seo_description'] ?? '')));
if ($metaDescription === '') $metaDescription = $defaultDescription;
$description = mb_substr(preg_replace('/\s+/u',' ',$metaDescription) ?? $metaDescription,0,160);
$pageTitle = trim((string)($category['seo_title'] ?? '')) ?: ((string)$category['title'].' | '.$storeName);
$canonical = app_seo_url($seo,'category/'.rawurlencode((string)$category['slug']));
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '',PHP_URL_PATH) ?: '';
$expectedPath = '/category/'.rawurlencode((string)$category['slug']);
if ($currentPath !== $expectedPath || (string)$seo['request_host'] !== (string)$seo['host']) {
    header('Location: '.$canonical,true,301);
    exit;
}
$categoryImage = $category['storage_key'] ? app_media_public_url((string)$category['storage_key']) : null;
$e = static fn(mixed $value): string => htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$listItems = [];
foreach ($products as $index=>$item) {
    $listItems[] = [
        '@type'=>'ListItem',
        'position'=>$index+1,
        'url'=>app_seo_url($seo,app_seo_product_path($item)),
        'name'=>(string)$item['title'],
    ];
}
$schema = [
    '@context'=>'https://schema.org',
    '@type'=>'CollectionPage',
    'name'=>(string)$category['title'],
    'description'=>$defaultDescription,
    'url'=>$canonical,
    'mainEntity'=>['@type'=>'ItemList','itemListElement'=>$listItems],
];
$jsonLd = json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$analyticsViewList = [
    'item_list_id'=>'category-'.(int)$category['id'],
    'item_list_name'=>(string)$category['title'],
    'items'=>array_map(static fn(array $item): array => [
        'item_id'=>(string)$item['id'],
        'item_name'=>(string)$item['title'],
        'item_brand'=>(string)($item['brand'] ?? ''),
        'price'=>(int)$item['price_rial'],
        'quantity'=>1,
    ],$products),
];
$analyticsViewListJson = json_encode($analyticsViewList,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <?= app_seo_tracking_markup($profile) ?>
  <title><?= $e($pageTitle) ?></title>
  <meta name="description" content="<?= $e($description) ?>">
  <link rel="canonical" href="<?= $e($canonical) ?>">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="fa_IR">
  <meta property="og:site_name" content="<?= $e($storeName) ?>">
  <meta property="og:title" content="<?= $e($pageTitle) ?>">
  <meta property="og:description" content="<?= $e($description) ?>">
  <meta property="og:url" content="<?= $e($canonical) ?>">
  <?php if ($categoryImage): ?><meta property="og:image" content="<?= $e($categoryImage) ?>"><?php endif; ?>
  <meta name="twitter:card" content="<?= $categoryImage?'summary_large_image':'summary' ?>">
  <meta name="twitter:title" content="<?= $e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= $e($description) ?>">
  <?php if ($categoryImage): ?><meta name="twitter:image" content="<?= $e($categoryImage) ?>"><?php endif; ?>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script type="application/ld+json"><?= $jsonLd ?: '{}' ?></script>
  <script>
    document.addEventListener('sm360:analytics-ready',function(){
      window.sm360Track('view_item_list',<?= $analyticsViewListJson ?: '{}' ?>);
    },{once:true});
  </script>
  <style>
    .seo-category-head{max-width:1200px;margin:34px auto 18px;padding:0 4vw}
    .seo-category-grid{max-width:1200px;margin:0 auto 55px;padding:0 4vw;display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px}
    .seo-product-card{display:block;text-decoration:none;color:var(--ink);border:1px solid var(--line);border-radius:14px;background:white;padding:12px}
    .seo-product-card img{width:100%;aspect-ratio:1/1;object-fit:contain;background:#f5f3eb;border-radius:10px}
    .seo-product-card h2{font-size:14px;line-height:1.8}
    .seo-product-card p{font-size:12px;color:var(--muted)}
    .seo-product-card strong{color:var(--forest)}
  </style>
</head>
<body>
<header class="topbar">
  <a class="brand" href="/index.php"><span class="brand-mark">۳۶۰</span><span><?= $e($storeName) ?></span></a>
  <a class="cart-button" href="/index.php?category=<?= $e(rawurlencode((string)$category['slug'])) ?>#store">ورود به این قفسه</a>
</header>
<main>
  <header class="seo-category-head">
    <p class="eyebrow"><?= $e($storeName) ?></p>
    <h1><?= $e($category['title']) ?></h1>
    <p><?= $e($defaultDescription) ?></p>
    <p><?= number_format(count($products)) ?> محصول در این دسته‌بندی</p>
  </header>
  <section class="seo-category-grid" aria-label="محصولات دسته‌بندی">
    <?php foreach ($products as $item):
      $imageUrl = $item['storage_key'] ? app_media_public_url((string)$item['storage_key']) : null;
      $inStock = (int)$item['available_quantity'] > 0;
    ?>
      <a class="seo-product-card" href="/<?= $e(app_seo_product_path($item)) ?>">
        <?php if ($imageUrl): ?><img src="<?= $e($imageUrl) ?>" alt="<?= $e($item['image_alt_text'] ?: $item['title']) ?>" loading="lazy"><?php else: ?><div class="product-image"></div><?php endif; ?>
        <h2><?= $e($item['title']) ?></h2>
        <p><?= $e($item['brand'] ?? '') ?> · <?= $e($item['unit_label']) ?></p>
        <strong><?= number_format((int)round((int)$item['price_rial']/10)) ?> تومان</strong>
        <p><?= $inStock?'موجود در فروشگاه':'فعلاً ناموجود' ?></p>
      </a>
    <?php endforeach; ?>
    <?php if (!$products): ?><p>هنوز محصولی در این دسته‌بندی ثبت نشده است.</p><?php endif; ?>
  </section>
</main>
<footer><div class="brand"><span class="brand-mark">۳۶۰</span><span><?= $e($storeName) ?></span></div><a href="/index.php#store">بازگشت به فروشگاه</a></footer>
</body>
</html>
