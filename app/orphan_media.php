<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';

function app_platform_orphan_file_path(int $tenantId, string $key): ?string {
    if ($tenantId < 1 || strlen($key) > 500 ||
        !preg_match('#^tenant-' . $tenantId . '/(logo|hero|aisle|panorama|category|product|other)/([a-f0-9]{40}\\.(?:jpg|png|webp))$#', $key, $m)) {
        return null;
    }
    $root = trim((string)(app_env('MEDIA_STORAGE_PATH') ?? ''));
    $drive = preg_match('/^[A-Za-z]:[\\\\\\/]/', $root) === 1;
    if ($root === '' || !(str_starts_with($root, DIRECTORY_SEPARATOR) || $drive) ||
        is_link($root) || is_link(rtrim($root, "/\\\\"))) return null;
    $rootReal = realpath($root);
    if ($rootReal === false || !is_dir($rootReal)) return null;
    $tenantDir = $rootReal . DIRECTORY_SEPARATOR . 'tenant-' . $tenantId;
    $typeDir = $tenantDir . DIRECTORY_SEPARATOR . $m[1];
    if (is_link($tenantDir) || is_link($typeDir) || !is_dir($typeDir)) return null;
    $typeReal = realpath($typeDir);
    if ($typeReal === false || $typeReal !== $typeDir) return null;
    $file = $typeReal . DIRECTORY_SEPARATOR . $m[2];
    if (is_link($file) || !is_file($file)) return null;
    $fileReal = realpath($file);
    if ($fileReal === false || !str_starts_with($fileReal, $typeReal . DIRECTORY_SEPARATOR)) return null;
    return $fileReal;
}

function app_platform_orphan_review_list(PDO $db): never {
    $stmt = $db->query(
        "SELECT r.id,r.tenant_id,t.name AS tenant_name,r.storage_key,r.reason,r.status,
                r.created_at,u.full_name AS requester_name
         FROM media_orphan_reviews r
         JOIN tenants t ON t.id=r.tenant_id
         JOIN users u ON u.id=r.requested_by AND u.tenant_id=r.tenant_id
         WHERE r.status='pending'
         ORDER BY r.created_at,r.id LIMIT 200"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['id']=(int)$row['id'];
        $row['tenant_id']=(int)$row['tenant_id'];
    }
    unset($row);
    app_json(['data'=>$rows]);
}

function app_platform_orphan_review_create(PDO $db, array $tenant, array $session): never {
    $body=app_auth_request_body();
    $tenantId=filter_var($body['tenant_id']??null,FILTER_VALIDATE_INT);
    $key=trim((string)($body['storage_key']??''));
    $reason=trim((string)($body['reason']??''));
    if (!$tenantId || $tenantId<1 || mb_strlen($reason)<5 || mb_strlen($reason)>500 ||
        strlen($key)>500 || !preg_match('#^tenant-' . (int)$tenantId . '/(logo|hero|aisle|panorama|category|product|other)/[a-f0-9]{40}\\.(?:jpg|png|webp)$#',$key)) {
        app_json(['error'=>'شناسه فروشگاه، کلید فایل یا دلیل درخواست معتبر نیست.'],422);
    }
    if (app_platform_orphan_file_path((int)$tenantId,$key)===null) {
        app_json(['error'=>'فایل در مسیر امن پیدا نشد؛ لینک نمادین، مسیر نامعتبر یا فایل مفقود پذیرفته نمی‌شود.'],409);
    }
    $db->beginTransaction();
    try {
        $exists=$db->prepare('SELECT id FROM media_assets WHERE tenant_id=? AND storage_key=? LIMIT 1 FOR UPDATE');
        $exists->execute([$tenantId,$key]);
        if ($exists->fetchColumn()) {
            $db->rollBack();
            app_json(['error'=>'این فایل هنوز در کتابخانه رسانه ثبت شده است و یتیم محسوب نمی‌شود.'],409);
        }
        $stmt=$db->prepare("INSERT INTO media_orphan_reviews (tenant_id,storage_key,reason,status,requested_by) VALUES (?,?,?,'pending',?)");
        $stmt->execute([$tenantId,$key,$reason,(int)$session['id']]);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'platform.media.orphan_review_requested','media_orphan_review',(int)$db->lastInsertId(),[
            'target_tenant_id'=>(int)$tenantId,'storage_key'=>$key,'reason'=>$reason
        ]);
        $db->commit();
    } catch (PDOException $e) {
        if($db->inTransaction())$db->rollBack();
        if((string)$e->getCode()==='23000')app_json(['error'=>'برای این فایل قبلاً درخواست بررسی ثبت شده است.'],409);
        throw $e;
    } catch (Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
    app_json(['message'=>'درخواست بررسی ثبت شد؛ هیچ فایلی حذف یا وارد صف پاک‌سازی نشد.'],201);
}

function app_platform_orphan_review_decide(PDO $db, array $tenant, array $session, int $reviewId, bool $approve): never {
    $db->beginTransaction();
    try {
        $stmt=$db->prepare("SELECT id,tenant_id,storage_key,reason,status FROM media_orphan_reviews WHERE id=? FOR UPDATE");
        $stmt->execute([$reviewId]);
        $review=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$review){$db->rollBack();app_json(['error'=>'درخواست پیدا نشد.'],404);}
        if($review['status']!=='pending'){$db->rollBack();app_json(['error'=>'این درخواست قبلاً بررسی شده است.'],409);}
        $tenantId=(int)$review['tenant_id'];
        $key=(string)$review['storage_key'];
        if($approve){
            if(app_platform_orphan_file_path($tenantId,$key)===null){
                $db->rollBack();
                app_json(['error'=>'فایل دیگر در مسیر امن موجود نیست؛ درخواست را رد کنید و گزارش تطبیق را دوباره بررسی کنید.'],409);
            }
            $registered=$db->prepare('SELECT id FROM media_assets WHERE tenant_id=? AND storage_key=? LIMIT 1 FOR UPDATE');
            $registered->execute([$tenantId,$key]);
            if($registered->fetchColumn()){$db->rollBack();app_json(['error'=>'فایل در این فاصله در کتابخانه ثبت شده و پاک‌سازی مجاز نیست.'],409);}
            $queued=$db->prepare("SELECT id FROM media_cleanup_queue WHERE tenant_id=? AND storage_key=? LIMIT 1 FOR UPDATE");
            $queued->execute([$tenantId,$key]);
            if($queued->fetchColumn()){$db->rollBack();app_json(['error'=>'این فایل از قبل در صف پاک‌سازی است.'],409);}
            $queue=$db->prepare("INSERT INTO media_cleanup_queue (tenant_id,storage_key,status,attempts) VALUES (?,?,'pending',0)");
            $queue->execute([$tenantId,$key]);
        }
        $status=$approve?'approved':'rejected';
        $update=$db->prepare('UPDATE media_orphan_reviews SET status=?,reviewed_by=?,reviewed_at=UTC_TIMESTAMP() WHERE id=? AND status=\'pending\'');
        $update->execute([$status,(int)$session['id'],$reviewId]);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'platform.media.orphan_review_'.$status,'media_orphan_review',$reviewId,[
            'target_tenant_id'=>$tenantId,'storage_key'=>$key,'reason'=>$review['reason'],
            'cleanup_enqueued'=>$approve
        ]);
        $db->commit();
    } catch(Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
    app_json(['message'=>$approve?'درخواست تأیید شد و فایل وارد صف پاک‌سازی شد؛ حذف توسط worker انجام می‌شود.':'درخواست رد شد؛ فایل و صف پاک‌سازی دست‌نخورده ماندند.']);
}
