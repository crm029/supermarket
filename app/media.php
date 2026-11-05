<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

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
    if ($key === '' || strlen($key) > 500 || preg_match('/[\x00-\x20\\\\]/', $key)) return null;
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
