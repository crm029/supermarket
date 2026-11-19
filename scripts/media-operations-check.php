<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
$commands = [
    'cleanup' => $root . '/scripts/cleanup-media.php',
    'reconciliation' => $root . '/scripts/reconcile-media.php',
];

function media_ops_run(string $script): array {
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__),
        null,
        ['bypass_shell' => true]
    );
    if (!is_resource($process)) {
        return ['exit_code' => 1, 'json' => null];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    // Child diagnostics are intentionally not forwarded: they may contain private paths.
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $decoded = null;
    if (is_string($stdout) && $stdout !== '') {
        try {
            $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = null;
        }
    }
    return ['exit_code' => $exitCode, 'json' => is_array($decoded) ? $decoded : null];
}

$cleanup = media_ops_run($commands['cleanup']);
$reconciliation = media_ops_run($commands['reconciliation']);
$cleanupData = $cleanup['json'] ?? [];
$reconcileData = $reconciliation['json'] ?? [];
$counts = $reconcileData['counts'] ?? [];
$alerts = [];

if ($cleanup['exit_code'] === 1 || $cleanup['json'] === null) {
    $alerts[] = ['severity'=>'error','code'=>'cleanup_worker_failed'];
} elseif (($cleanupData['pending'] ?? 0) > 0 || $cleanup['exit_code'] === 2) {
    $alerts[] = ['severity'=>'warning','code'=>'cleanup_queue_backlog'];
}
if ($reconciliation['exit_code'] === 1 || $reconciliation['json'] === null) {
    $alerts[] = ['severity'=>'error','code'=>'media_reconciliation_failed'];
} elseif (($reconciliation['exit_code'] === 2) || ($reconcileData['status'] ?? '') !== 'ok') {
    $alerts[] = ['severity'=>'warning','code'=>'media_reconciliation_attention_required'];
}

$hasError = count(array_filter($alerts, static fn(array $a): bool => $a['severity'] === 'error')) > 0;
$status = $hasError ? 1 : (count($alerts) > 0 ? 2 : 0);
$report = [
    'status' => $status === 0 ? 'ok' : ($status === 1 ? 'error' : 'attention_required'),
    'checked_at' => gmdate('c'),
    'cleanup' => [
        'checked' => (int)($cleanupData['checked'] ?? 0),
        'completed' => (int)($cleanupData['completed'] ?? 0),
        'pending_total' => (int)($cleanupData['pending'] ?? 0),
    ],
    'reconciliation' => [
        'status' => (string)($reconcileData['status'] ?? 'unavailable'),
        'files_scanned' => (int)($counts['files_scanned'] ?? 0),
        'orphan_files' => (int)($counts['orphan_files'] ?? 0),
        'missing_registered_files' => (int)($counts['missing_registered_files'] ?? 0),
        'unsafe_entries' => (int)($counts['unsafe_entries'] ?? 0),
    ],
    'alerts' => $alerts,
];
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($status);
