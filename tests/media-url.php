<?php
declare(strict_types=1);

putenv('APP_ENV=testing');
putenv('MEDIA_BASE_URL=https://cdn.example.test/assets');

require_once dirname(__DIR__) . '/app/media.php';

$valid = app_media_public_url('products/milk bottle.jpg');
if ($valid !== 'https://cdn.example.test/assets/products/milk%20bottle.jpg') {
    throw new RuntimeException('Public media URL encoding failed.');
}

foreach (['../secret.txt', 'products/../secret.txt', 'products\\\\secret.jpg', ''] as $invalid) {
    if (app_media_public_url($invalid) !== null) {
        throw new RuntimeException('Unsafe media storage key was accepted.');
    }
}

putenv('APP_ENV=production');
putenv('MEDIA_BASE_URL=http://cdn.example.test/assets');
if (app_media_public_url('products/item.jpg') !== null) {
    throw new RuntimeException('Insecure production media URL was accepted.');
}

echo "Public media URL checks passed.\n";
