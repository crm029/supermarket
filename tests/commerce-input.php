<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/commerce.php';

$valid = app_commerce_lines(['items' => [
    ['product_id' => '12', 'quantity' => '2'],
    ['product_id' => 7, 'quantity' => 1],
    ['product_id' => 12, 'quantity' => 3],
]]);
if ($valid !== [12 => 5, 7 => 1]) {
    fwrite(STDERR, "Cart line normalization failed.\n");
    exit(1);
}

echo "Commerce input tests passed.\n";
