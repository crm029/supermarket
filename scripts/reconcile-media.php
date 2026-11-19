<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is CLI-only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

function media_reconcile_emit(array $report, int $exitCode): never {
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($exitCode);
}

$root = trim((string)(app_env('MEDIA_STORAGE_PATH') ?? ''));
$isWindowsDrive = preg_match('/^[A-Za-z]:/', $root) === 1 && in_array(substr($root, 2, 1), ['/', '\\\\'], true);
if ($root === '' || !(str_starts_with($root, DIRECTORY_SEPARATOR) || $isWindowsDrive)) {
    media_reconcile_emit(['status'=>'configuration_error','error'=>'MEDIA_STORAGE_PATH must be an absolute path.'], 1);
}
$rootReal = realpath($root);
if ($rootReal === false || !is_dir($rootReal) || is_link($root)) {
    media_reconcile_emit(['status'=>'configuration_error','error'=>'Media storage root is unavailable or unsafe.'], 1);
}

$db = app_db();
$known = [];
foreach ($db->query('SELECT tenant_id,storage_key FROM media_assets')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $known[(string)$row['storage_key']] = ['tenant_id'=>(int)$row['tenant_id'],'state'=>'registered'];
}
$queued = [];
foreach ($db->query("SELECT tenant_id,storage_key FROM media_cleanup_queue WHERE status='pending'")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $queued[(string)$row['storage_key']] = (int)$row['tenant_id'];
}

$report = [
    'status'=>'ok',
    'checked_at'=>gmdate('c'),
    'storage_root'=>$rootReal,
    'counts'=>['files_scanned'=>0,'registered_files'=>0,'queued_cleanup_files'=>0,'orphan_files'=>0,'missing_registered_files'=>0,'unsafe_entries'=>0],
    'orphan_files'=>[],
    'missing_registered_files'=>[],
    'unsafe_entries'=>[],
    'truncated'=>false,
];
$maxDetails = 500;
$seen = [];
$allowedTypes = ['logo','hero','aisle','panorama','category','product','other'];

$tenantDirs = new DirectoryIterator($rootReal);
foreach ($tenantDirs as $tenantEntry) {
    if ($tenantEntry->isDot()) continue;
    $tenantName = $tenantEntry->getFilename();
    if (!preg_match('/^tenant-([1-9][0-9]*)$/', $tenantName, $tenantMatch)) continue;
    if ($tenantEntry->isLink() || !$tenantEntry->isDir()) {
        $report['counts']['unsafe_entries']++;
        if (count($report['unsafe_entries']) < $maxDetails) $report['unsafe_entries'][] = $tenantName;
        continue;
    }
    $tenantId = (int)$tenantMatch[1];
    foreach (new DirectoryIterator($tenantEntry->getPathname()) as $typeEntry) {
        if ($typeEntry->isDot()) continue;
        $type = $typeEntry->getFilename();
        if (!in_array($type, $allowedTypes, true)) continue;
        if ($typeEntry->isLink() || !$typeEntry->isDir()) {
            $report['counts']['unsafe_entries']++;
            if (count($report['unsafe_entries']) < $maxDetails) $report['unsafe_entries'][] = $tenantName.'/'.$type;
            continue;
        }
        foreach (new DirectoryIterator($typeEntry->getPathname()) as $fileEntry) {
            if ($fileEntry->isDot()) continue;
            $name = $fileEntry->getFilename();
            if (!preg_match('/^[a-f0-9]{40}\.(?:jpg|png|webp)$/', $name)) continue;
            $key = $tenantName.'/'.$type.'/'.$name;
            if ($fileEntry->isLink() || !$fileEntry->isFile()) {
                $report['counts']['unsafe_entries']++;
                if (count($report['unsafe_entries']) < $maxDetails) $report['unsafe_entries'][] = $key;
                continue;
            }
            $report['counts']['files_scanned']++;
            $seen[$key] = true;
            if (isset($known[$key]) && $known[$key]['tenant_id'] === $tenantId) {
                $report['counts']['registered_files']++;
            } elseif (isset($queued[$key]) && $queued[$key] === $tenantId) {
                $report['counts']['queued_cleanup_files']++;
            } else {
                $report['counts']['orphan_files']++;
                if (count($report['orphan_files']) < $maxDetails) $report['orphan_files'][] = $key;
            }
        }
    }
}

foreach ($known as $key => $meta) {
    if (!isset($seen[$key])) {
        $report['counts']['missing_registered_files']++;
        if (count($report['missing_registered_files']) < $maxDetails) $report['missing_registered_files'][] = $key;
    }
}

$hasIssues = $report['counts']['orphan_files'] > 0 ||
    $report['counts']['missing_registered_files'] > 0 ||
    $report['counts']['unsafe_entries'] > 0;
foreach (['orphan_files','missing_registered_files','unsafe_entries'] as $list) {
    if ($report['counts'][$list === 'orphan_files' ? 'orphan_files' : ($list === 'missing_registered_files' ? 'missing_registered_files' : 'unsafe_entries')] > $maxDetails) {
        $report['truncated'] = true;
    }
}
$report['status'] = $hasIssues ? 'attention_required' : 'ok';
media_reconcile_emit($report, $hasIssues ? 2 : 0);
