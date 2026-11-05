<?php
declare(strict_types=1);
require_once __DIR__ . '/app/seo.php';

$seo = app_seo_context();
if (!$seo) { http_response_code(404); exit('فروشگاه پیدا نشد.'); }
$productId = filter_var($_GET['id'] ?? null,FILTER_VALIDATE_INT);
if ($productId === false || $productId === null || $productId < 1) { http_response_code(404); exit('کالا پیدا نشد.'); }

$stmt = $seo['db']->prepare(
    "SELECT p.id,p.title,p.sku,p.brand,p.description,p.unit_label,p.price_rial,p.compare_at_rial,
            p.image_media_id,p.product_kind,p.seo_title,p.seo_slug,p.seo_description,c.title AS category_title,c.slug AS category_slug,
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
     LEFT JOIN categories c ON c.tenant_id=p.tenant_id AND c.id=p.category_id AND c.is_active=1
     WHERE p.tenant_id=? AND p.id=? AND p.is_active=1 AND p.product_kind<>'variant'
     LIMIT 1"
);
$stmt->execute([(int)$seo['tenant']['id'],$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) { http_response_code(404); exit('کالا پیدا نشد.'); }

$profile = app_seo_store_profile($seo);
$storeName = (string)($profile['name'] ?? $seo['tenant']['name']);
$visibleDescription = trim(strip_tags((string)($product['description'] ?? '')));
if ($visibleDescription === '') $visibleDescription = $product['title'].'؛ مشاهده قیمت و جزئیات کالا در '.$storeName.'.';
$metaDescription = trim(strip_tags((string)($product['seo_description'] ?? '')));
if ($metaDescription === '') $metaDescription = $visibleDescription;
$description = mb_substr(preg_replace('/\s+/u',' ',$metaDescription) ?? $metaDescription,0,160);
$image = app_media_public_data($seo['db'],(int)$seo['tenant']['id'],$product['image_media_id'] === null ? null : (int)$product['image_media_id']);
$canonicalPath = app_seo_product_path($product);
$canonical = app_seo_url($seo,$canonicalPath);
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '',PHP_URL_PATH) ?: '';
$expectedPath = '/'.$canonicalPath;
if ($currentPath !== $expectedPath || (string)($_SERVER['HTTP_HOST'] ?? '') !== (string)$seo['host']) {
    header('Location: '.$canonical,true,301);
    exit;
}
$inStock = (int)$product['available_quantity'] > 0;
$priceRial = (int)$product['price_rial'];
$title = trim((string)($product['seo_title'] ?? '')) ?: ((string)$product['title'].' | '.$storeName);
$schema = [
    '@context'=>'https://schema.org',
    '@type'=>'Product',
    'name'=>(string)$product['title'],
    'sku'=>(string)$product['sku'],
    'description'=>$visibleDescription,
    'offers'=>[
        '@type'=>'Offer',
        'url'=>$canonical,
        'priceCurrency'=>'IRR',
        'price'=>(string)$priceRial,
        'availability'=>$inStock?'https://schema.org/InStock':'https://schema.org/OutOfStock',
        'itemCondition'=>'https://schema.org/NewCondition',
    ],
];
if (!empty($product['brand'])) $schema['brand']=['@type'=>'Brand','name'=>(string)$product['brand']];
if (!empty($image['url'])) $schema['image']=[$image['url']];
$jsonLd = json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$analyticsViewItem = [
    'currency'=>'IRR',
    'value'=>$priceRial,
    'items'=>[[
        'item_id'=>(string)$product['id'],
        'item_name'=>(string)$product['title'],
        'item_brand'=>(string)($product['brand'] ?? ''),
        'item_category'=>(string)($product['category_title'] ?? ''),
        'price'=>$priceRial,
        'quantity'=>1,
    ]],
];
$analyticsViewItemJson = json_encode($analyticsViewItem,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$e = static fn(mixed $value): string => htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$priceToman = number_format((int)round($priceRial/10));
$compareToman = $product['compare_at_rial'] === null ? null : number_format((int)round((int)$product['compare_at_rial']/10));
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <?= app_seo_tracking_markup($profile) ?>
  <title><?= $e($title) ?></title>
  <meta name="description" content="<?= $e($description) ?>">
  <link rel="canonical" href="<?= $e($canonical) ?>">
  <meta property="og:type" content="product">
  <meta property="og:locale" content="fa_IR">
  <meta property="og:site_name" content="<?= $e($storeName) ?>">
  <meta property="og:title" content="<?= $e($title) ?>">
  <meta property="og:description" content="<?= $e($description) ?>">
  <meta property="og:url" content="<?= $e($canonical) ?>">
  <?php if (!empty($image['url'])): ?><meta property="og:image" content="<?= $e($image['url']) ?>"><?php endif; ?>
  <meta name="twitter:card" content="<?= !empty($image['url'])?'summary_large_image':'summary' ?>">
  <meta name="twitter:title" content="<?= $e($title) ?>">
  <meta name="twitter:description" content="<?= $e($description) ?>">
  <?php if (!empty($image['url'])): ?><meta name="twitter:image" content="<?= $e($image['url']) ?>"><?php endif; ?>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script type="application/ld+json"><?= $jsonLd ?: '{}' ?></script>
  <script>
    document.addEventListener('sm360:analytics-ready',function(){
      window.sm360Track('view_item',<?= $analyticsViewItemJson ?: '{}' ?>);
    },{once:true});
  </script>
  <style>
    .seo-product-page{max-width:1100px;margin:40px auto;padding:24px;background:var(--paper);border:1px solid var(--line);border-radius:22px;display:grid;grid-template-columns:minmax(260px, .9fr) 1.1fr;gap:34px}
    .seo-product-image{width:100%;aspect-ratio:1/1;object-fit:contain;background:#f5f3eb;border-radius:16px}
    .seo-product-copy h1{font-size:clamp(24px,4vw,36px);line-height:1.6}
    .seo-product-copy p{line-height:2.1;color:var(--muted)}
    .seo-product-price{font-size:25px;font-weight:800;color:var(--forest)}
    .seo-product-availability{display:inline-block;padding:7px 12px;border-radius:20px;background:<?= $inStock?'#edf4df':'#f7e9e4' ?>;color:<?= $inStock?'#31542b':'#9b3f2e' ?>;font-size:12px}
    .seo-product-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}
    .seo-product-actions a{display:inline-block;text-decoration:none}
    .seo-breadcrumb{max-width:1100px;margin:28px auto 0;padding:0 24px;font-size:12px;color:var(--muted)}
    @media(max-width:700px){.seo-product-page{margin:18px 4vw;padding:16px;grid-template-columns:1fr;gap:20px}.seo-breadcrumb{margin-top:18px}}
  </style>
</head>
<body>
<header class="topbar">
  <a class="brand" href="/index.php"><span class="brand-mark">۳۶۰</span><span><?= $e($storeName) ?></span></a>
  <a class="cart-button" href="/index.php?product=<?= (int)$product['id'] ?>#store">ورود به فروشگاه</a>
</header>
<nav class="seo-breadcrumb"><a href="/index.php">خانه</a> › <?php if (!empty($product['category_slug'])): ?><a href="/category/<?= $e(rawurlencode((string)$product['category_slug'])) ?>"><?= $e($product['category_title']) ?></a> › <?php endif; ?><?= $e($product['title']) ?></nav>
<main class="seo-product-page">
  <div>
    <?php if (!empty($image['url'])): ?><img class="seo-product-image" src="<?= $e($image['url']) ?>" alt="<?= $e($image['alt_text'] ?: $product['title']) ?>" width="<?= (int)($image['width'] ?? 700) ?>" height="<?= (int)($image['height'] ?? 700) ?>" fetchpriority="high"><?php else: ?><div class="seo-product-image" role="img" aria-label="تصویر محصول ثبت نشده است"></div><?php endif; ?>
  </div>
  <div class="seo-product-copy">
    <p class="eyebrow"><?= $e($storeName) ?></p>
    <?php if (!empty($product['brand'])): ?><p><?= $e($product['brand']) ?></p><?php endif; ?>
    <h1><?= $e($product['title']) ?></h1>
    <p><?= nl2br($e($visibleDescription)) ?></p>
    <p>واحد فروش: <?= $e($product['unit_label']) ?></p>
    <p><span class="seo-product-availability"><?= $inStock?'موجود در فروشگاه':'فعلاً ناموجود' ?></span></p>
    <p class="seo-product-price"><?= $priceToman ?> تومان<?php if ($product['product_kind']==='variant_parent'): ?> <small>به‌عنوان قیمت پایه</small><?php endif; ?></p>
    <?php if ($compareToman && (int)$product['compare_at_rial'] > $priceRial): ?><p><del><?= $compareToman ?> تومان</del></p><?php endif; ?>
    <p>کد کالا: <?= $e($product['sku']) ?></p>
    <div class="seo-product-actions">
      <a class="primary-button" href="/index.php?product=<?= (int)$product['id'] ?>#store">مشاهده و خرید در فروشگاه</a>
      <?php if (!empty($product['category_slug'])): ?><a class="text-button" style="color:var(--forest)" href="/category/<?= $e(rawurlencode((string)$product['category_slug'])) ?>">مشاهده دسته‌بندی</a><?php endif; ?>
    </div>
  </div>
</main>
<footer><div class="brand"><span class="brand-mark">۳۶۰</span><span><?= $e($storeName) ?></span></div><small>صفحه محصول در فروشگاه <?= $e($storeName) ?></small></footer>
</body>
</html>
