<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';

function app_media_public_url(string $storageKey): ?string {
    $base = trim((string)app_env('MEDIA_BASE_URL', ''));
    if ($base === '') return null;

    $parts = parse_url($base);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']) ||
        isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        return null;
    }

    $scheme = strtolower((string)$parts['scheme']);
    $development = in_array(strtolower(app_env('APP_ENV', 'development') ?? 'development'), ['development', 'local', 'testing'], true);
    if ($scheme !== 'https' && !($development && $scheme === 'http')) return null;

    $key = trim($storageKey, '/');
    if ($key === '' || strlen($key) > 500 || preg_match('/[\x00-\x1F\x7F\\\\]/', $key)) return null;
    $segments = explode('/', $key);
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') return null;
    }

    $encodedKey = implode('/', array_map('rawurlencode', $segments));
    return rtrim($base, '/') . '/' . $encodedKey;
}

function app_media_public_data(PDO $db, int $tenantId, ?int $mediaId): ?array {
    if ($mediaId === null || $mediaId < 1) return null;

    $stmt = $db->prepare(
        'SELECT id, storage_key, alt_text, width, height
         FROM media_assets WHERE tenant_id = ? AND id = ? LIMIT 1'
    );
    $stmt->execute([$tenantId, $mediaId]);
    $media = $stmt->fetch();
    if (!$media) return null;

    return [
        'id' => (int)$media['id'],
        'url' => app_media_public_url((string)$media['storage_key']),
        'alt_text' => $media['alt_text'],
        'width' => $media['width'] === null ? null : (int)$media['width'],
        'height' => $media['height'] === null ? null : (int)$media['height'],
    ];
}


function app_media_upload(PDO $db, array $tenant, array $session): never {
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        app_json(['error' => 'فایل تصویر ارسال نشده است.'], 422);
    }
    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
        !isset($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
        app_json(['error' => 'بارگذاری تصویر ناموفق بود.'], 422);
    }
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 5 * 1024 * 1024) app_json(['error' => 'حجم تصویر باید حداکثر ۵ مگابایت باشد.'], 413);

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string)$file['tmp_name']);
    $types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mime) || !isset($types[$mime])) {
        app_json(['error' => 'فقط تصویر JPG، PNG یا WebP مجاز است.'], 415);
    }
    $image = @getimagesize((string)$file['tmp_name']);
    if (!is_array($image) || (int)$image[0] < 1 || (int)$image[1] < 1 ||
        (int)$image[0] > 6000 || (int)$image[1] > 6000) {
        app_json(['error' => 'ابعاد تصویر معتبر نیست یا بیش از حد بزرگ است.'], 422);
    }

    $mediaType = strtolower(trim((string)($_POST['media_type'] ?? 'product')));
    $allowedTypes = ['logo','hero','aisle','panorama','category','product','other'];
    if (!in_array($mediaType, $allowedTypes, true)) app_json(['error' => 'نوع رسانه معتبر نیست.'], 422);
    $alt = trim((string)($_POST['alt_text'] ?? ''));
    if (mb_strlen($alt) > 255) app_json(['error' => 'متن جایگزین تصویر بیش از حد طولانی است.'], 422);

    $storageRoot = trim((string)(app_env('MEDIA_STORAGE_PATH') ?? ''));
    $isWindowsDrive = preg_match('/^[A-Za-z]:/', $storageRoot) === 1 &&
        in_array(substr($storageRoot, 2, 1), ['/', '\\'], true);
    $absolutePath = str_starts_with($storageRoot, DIRECTORY_SEPARATOR) || $isWindowsDrive;
    if ($storageRoot === '' || !$absolutePath) {
        app_json(['error' => 'مسیر ذخیره‌سازی رسانه در سرور پیکربندی نشده است.'], 503);
    }
    if (!is_dir($storageRoot) && !@mkdir($storageRoot, 0750, true) && !is_dir($storageRoot)) {
        app_json(['error' => 'فضای ذخیره‌سازی رسانه در دسترس نیست.'], 503);
    }
    $root = realpath($storageRoot);
    if ($root === false || !is_writable($root)) app_json(['error' => 'فضای ذخیره‌سازی رسانه قابل نوشتن نیست.'], 503);

    $tenantDir = $root . DIRECTORY_SEPARATOR . 'tenant-' . (int)$tenant['id'];
    $typeDir = $tenantDir . DIRECTORY_SEPARATOR . $mediaType;
    foreach ([$tenantDir, $typeDir] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            app_json(['error' => 'ساخت پوشه رسانه ممکن نشد.'], 503);
        }
    }

    $filename = bin2hex(random_bytes(20)) . '.' . $types[$mime];
    $destination = $typeDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
        app_json(['error' => 'ذخیره تصویر روی سرور ناموفق بود.'], 500);
    }
    @chmod($destination, 0640);
    $storageKey = 'tenant-' . (int)$tenant['id'] . '/' . $mediaType . '/' . $filename;

    try {
        $stmt = $db->prepare(
            'INSERT INTO media_assets (tenant_id,media_type,storage_key,alt_text,width,height,metadata)
             VALUES (?,?,?,?,?,?,?)'
        );
        $metadata = json_encode(['mime' => $mime, 'size' => $size], JSON_UNESCAPED_SLASHES);
        $stmt->execute([
            $tenant['id'], $mediaType, $storageKey, $alt,
            (int)$image[0], (int)$image[1], $metadata,
        ]);
        $id = (int)$db->lastInsertId();
        app_audit_log($db, (int)$tenant['id'], (int)$session['id'], 'media.uploaded', 'media_asset', $id, [
            'media_type' => $mediaType, 'width' => (int)$image[0], 'height' => (int)$image[1],
        ]);
        app_json(['data' => app_media_public_data($db, (int)$tenant['id'], $id), 'message' => 'تصویر بارگذاری شد.'], 201);
    } catch (Throwable $e) {
        @unlink($destination);
        throw $e;
    }
}
