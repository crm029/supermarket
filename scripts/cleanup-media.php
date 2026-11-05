<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/app/media.php';

try {
    $result = app_media_cleanup_process_pending(app_db(), 100);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(($result['pending'] ?? 0) > 0 ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Media cleanup worker failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
