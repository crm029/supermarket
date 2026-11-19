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

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sm360-media-' . bin2hex(random_bytes(6));
$outside = $root . '-outside';
if (!mkdir($root, 0700) || !mkdir($outside, 0700)) {
    throw new RuntimeException('Unable to create media path test directories.');
}
try {
    $safeTenant = app_media_ensure_child_directory($root, 'tenant-42');
    if ($safeTenant === null || !is_dir($safeTenant)) {
        throw new RuntimeException('Safe tenant media directory was not created.');
    }
    $linkName = 'tenant-43';
    if (function_exists('symlink') && @symlink($outside, $root . DIRECTORY_SEPARATOR . $linkName)) {
        if (app_media_ensure_child_directory($root, $linkName) !== null) {
            throw new RuntimeException('A symlinked tenant media directory was accepted.');
        }
        if (glob($outside . DIRECTORY_SEPARATOR . '*') !== []) {
            throw new RuntimeException('The symlink target was unexpectedly modified.');
        }
    }

    $typeLink = $safeTenant . DIRECTORY_SEPARATOR . 'product';
    if (function_exists('symlink') && @symlink($outside, $typeLink)) {
        if (app_media_ensure_child_directory($safeTenant, 'product') !== null) {
            throw new RuntimeException('A symlinked media type directory was accepted.');
        }
        if (glob($outside . DIRECTORY_SEPARATOR . '*') !== []) {
            throw new RuntimeException('The media type symlink target was unexpectedly modified.');
        }
    }
} finally {
    foreach (glob($root . DIRECTORY_SEPARATOR . '*') ?: [] as $entry) {
        if (is_link($entry) || is_file($entry)) @unlink($entry);
        elseif (is_dir($entry)) @rmdir($entry);
    }
    @rmdir($root);
    @rmdir($outside);
}

echo "Public media URL and safe-directory checks passed.\n";