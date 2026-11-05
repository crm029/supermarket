<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/admin_catalog.php';

$data = app_admin_product_payload([
    'sku' => 'milk-1',
    'title' => 'شیر کم‌چرب',
    'price_rial' => 125000,
    'compare_at_rial' => 150000,
    'category_id' => 12,
    'unit_label' => 'بطری',
    'is_weighted' => false,
    'is_active' => true,
]);

if ($data['sku'] !== 'MILK-1' ||
    $data['price_rial'] !== 125000 ||
    $data['compare_at_rial'] !== 150000 ||
    $data['category_id'] !== 12 ||
    $data['is_weighted'] !== 0 ||
    $data['is_active'] !== 1) {
    throw new RuntimeException('Product create payload normalization failed.');
}

$patch = app_admin_product_payload(['price_rial' => 140000], true);
if ($patch !== ['price_rial' => 140000]) {
    throw new RuntimeException('Partial product update payload failed.');
}

echo "Admin catalog input checks passed.\n";
