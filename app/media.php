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


function app_admin_media_list(PDO $db, array $tenant): never {
    $stmt = $db->prepare(
        "SELECT m.id,m.media_type,m.storage_key,m.alt_text,m.width,m.height,m.created_at,
          ((SELECT COUNT(*) FROM products p WHERE p.tenant_id=m.tenant_id AND p.image_media_id=m.id) +
           (SELECT COUNT(*) FROM categories c WHERE c.tenant_id=m.tenant_id AND c.image_media_id=m.id) +
           (SELECT COUNT(*) FROM store_scenes s WHERE s.tenant_id=m.tenant_id AND (s.media_id=m.id OR s.fallback_media_id=m.id))) AS usage_count
         FROM media_assets m WHERE m.tenant_id = ?
         ORDER BY m.created_at DESC,m.id DESC LIMIT 200"
    );
    $stmt->execute([$tenant['id']]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'id' => (int)$row['id'],
            'media_type' => $row['media_type'],
            'alt_text' => $row['alt_text'],
            'width' => $row['width'] === null ? null : (int)$row['width'],
            'height' => $row['height'] === null ? null : (int)$row['height'],
            'created_at' => $row['created_at'],
            'usage_count' => (int)$row['usage_count'],
            'url' => app_media_public_url((string)$row['storage_key']),
        ];
    }
    app_json(['data' => $items]);
}

function app_media_cleanup_mark(PDO $db, int $queueId, bool $completed, ?string $error = null): void {
    $stmt = $db->prepare(
        "UPDATE media_cleanup_queue
         SET status=?,attempts=attempts+1,last_error=?,processed_at=?
         WHERE id=?"
    );
    $stmt->execute([
        $completed ? 'completed' : 'pending',
        $completed ? null : $error,
        $completed ? gmdate('Y-m-d H:i:s') : null,
        $queueId,
    ]);
}

function app_media_cleanup_process_one(PDO $db, array $item): bool {
    $queueId = (int)$item['id'];
    $tenantId = (int)$item['tenant_id'];
    $key = (string)$item['storage_key'];
    $root = trim((string)(app_env('MEDIA_STORAGE_PATH') ?? ''));
    $isWindowsDrive = preg_match('/^[A-Za-z]:/', $root) === 1 &&
        in_array(substr($root, 2, 1), ['/', '\\\\'], true);
    if ($root === '' || !(str_starts_with($root, DIRECTORY_SEPARATOR) || $isWindowsDrive)) {
        app_media_cleanup_mark($db,$queueId,false,'storage_path_not_configured');
        return false;
    }
    $rootReal = realpath($root);
    if ($rootReal === false || !is_dir($rootReal)) {
        app_media_cleanup_mark($db,$queueId,false,'storage_root_unavailable');
        return false;
    }
    $pattern = '#^tenant-' . $tenantId . '/(logo|hero|aisle|panorama|category|product|other)/([a-f0-9]{40}\\.(?:jpg|png|webp))$#';
    if (!preg_match($pattern, $key, $matches)) {
        app_media_cleanup_mark($db,$queueId,false,'invalid_storage_key');
        return false;
    }
    $tenantDir = $rootReal . DIRECTORY_SEPARATOR . 'tenant-' . $tenantId;
    $typeDir = $tenantDir . DIRECTORY_SEPARATOR . $matches[1];
    if (is_link($tenantDir) || is_link($typeDir)) {
        app_media_cleanup_mark($db,$queueId,false,'unsafe_storage_path');
        return false;
    }
    if (!is_dir($typeDir)) {
        app_media_cleanup_mark($db,$queueId,true);
        return true;
    }
    $typeReal = realpath($typeDir);
    if ($typeReal === false || $typeReal !== $typeDir) {
        app_media_cleanup_mark($db,$queueId,false,'unsafe_storage_path');
        return false;
    }
    $file = $typeReal . DIRECTORY_SEPARATOR . $matches[2];
    if (is_link($file)) {
        app_media_cleanup_mark($db,$queueId,false,'symlink_refused');
        return false;
    }
    if (!file_exists($file)) {
        app_media_cleanup_mark($db,$queueId,true);
        return true;
    }
    $fileReal = realpath($file);
    if ($fileReal === false || !is_file($fileReal) ||
        !str_starts_with($fileReal, $typeReal . DIRECTORY_SEPARATOR)) {
        app_media_cleanup_mark($db,$queueId,false,'unsafe_file_path');
        return false;
    }
    if (!@unlink($fileReal)) {
        app_media_cleanup_mark($db,$queueId,false,'file_delete_failed');
        return false;
    }
    app_media_cleanup_mark($db,$queueId,true);
    return true;
}

function app_media_cleanup_process_pending(PDO $db, int $limit = 100): array {
    $limit = max(1, min(500, $limit));
    $rows = $db->query(
        "SELECT id,tenant_id,storage_key FROM media_cleanup_queue
         WHERE status='pending' ORDER BY created_at,id LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC);
    $completed = 0;
    foreach ($rows as $row) {
        if (app_media_cleanup_process_one($db,$row)) $completed++;
    }
    return ['checked'=>count($rows),'completed'=>$completed,'pending'=>count($rows)-$completed];
}

function app_admin_media_delete(PDO $db, array $tenant, array $session, int $mediaId): never {
    $db->beginTransaction();
    try {
        $find = $db->prepare('SELECT storage_key FROM media_assets WHERE tenant_id=? AND id=? LIMIT 1 FOR UPDATE');
        $find->execute([$tenant['id'],$mediaId]);
        $storageKey = $find->fetchColumn();
        if (!$storageKey) {
            $db->rollBack();
            app_json(['error'=>'تصویر پیدا نشد.'],404);
        }
        $usage = $db->prepare(
            "SELECT
              (SELECT COUNT(*) FROM products WHERE tenant_id=? AND image_media_id=?) +
              (SELECT COUNT(*) FROM categories WHERE tenant_id=? AND image_media_id=?) +
              (SELECT COUNT(*) FROM store_scenes WHERE tenant_id=? AND (media_id=? OR fallback_media_id=?))"
        );
        $usage->execute([$tenant['id'],$mediaId,$tenant['id'],$mediaId,$tenant['id'],$mediaId,$mediaId]);
        if ((int)$usage->fetchColumn() > 0) {
            $db->rollBack();
            app_json(['error'=>'این تصویر هنوز به محصول، دسته‌بندی یا صحنه‌ای متصل است و قابل حذف نیست.'],409);
        }
        $queue = $db->prepare(
            "INSERT INTO media_cleanup_queue (tenant_id,storage_key,status,attempts,last_error,processed_at)
             VALUES (?,?, 'pending',0,NULL,NULL)
             ON DUPLICATE KEY UPDATE status='pending',attempts=0,last_error=NULL,processed_at=NULL"
        );
        $queue->execute([$tenant['id'],$storageKey]);
        $queueFind = $db->prepare('SELECT id FROM media_cleanup_queue WHERE tenant_id=? AND storage_key=? LIMIT 1');
        $queueFind->execute([$tenant['id'],$storageKey]);
        $queueId = (int)$queueFind->fetchColumn();
        $delete = $db->prepare('DELETE FROM media_assets WHERE tenant_id=? AND id=?');
        $delete->execute([$tenant['id'],$mediaId]);
        if ($delete->rowCount() !== 1) throw new RuntimeException('Media record changed during deletion.');
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'media.deleted','media_asset',$mediaId,[
            'cleanup_queue_id'=>$queueId
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    $queueItem = ['id'=>$queueId,'tenant_id'=>(int)$tenant['id'],'storage_key'=>(string)$storageKey];
    $cleaned = app_media_cleanup_process_one($db,$queueItem);
    app_json([
        'data'=>['id'=>$mediaId,'cleanup_pending'=>!$cleaned],
        'message'=>$cleaned?'تصویر حذف شد.':'تصویر از کتابخانه حذف شد و پاک‌سازی فایل در صف باقی ماند.'
    ]);
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
