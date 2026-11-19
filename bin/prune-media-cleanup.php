<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is CLI-only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

$retentionDays = filter_var(
    app_env('MEDIA_CLEANUP_RETENTION_DAYS', '180'),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 30, 'max_range' => 3650]]
);
if ($retentionDays === false) {
    fwrite(STDERR, "MEDIA_CLEANUP_RETENTION_DAYS must be an integer between 30 and 3650.\n");
    exit(1);
}

$batchSize = 500;
$maxBatches = 20;
$db = app_db();
$totalDeleted = 0;
$batches = 0;
$deleted = 0;

while ($batches < $maxBatches) {
    $stmt = $db->prepare(
        "DELETE FROM media_cleanup_queue
         WHERE status = 'completed'
           AND processed_at IS NOT NULL
           AND processed_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
         ORDER BY processed_at ASC, id ASC
         LIMIT " . $batchSize
    );
    $stmt->execute([(int)$retentionDays]);
    $deleted = $stmt->rowCount();
    $totalDeleted += $deleted;
    $batches++;
    if ($deleted < $batchSize) break;
}

echo json_encode([
    'status' => 'ok',
    'retention_days' => (int)$retentionDays,
    'deleted' => $totalDeleted,
    'batches' => $batches,
    'batch_size' => $batchSize,
    'max_batches' => $maxBatches,
    'more_may_remain' => $batches === $maxBatches && $deleted === $batchSize,
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
