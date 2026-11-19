<?php
declare(strict_types=1);

/**
 * Enforce a configured tenant quota while the caller holds a transaction.
 * Missing active subscriptions and omitted quota keys remain unlimited for
 * legacy/demo tenants; explicit integer limits are enforced.
 */
function app_tenant_limit_guard(PDO $db, array $tenant, string $key, int $increment = 1): void {
    $allowed=['products','staff','media_assets'];
    if(!in_array($key,$allowed,true)||$increment<0)throw new InvalidArgumentException('Unsupported tenant quota.');
    $tenantId=(int)$tenant['id'];
    $lock=$db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');
    $lock->execute([$tenantId]);
    if(!$lock->fetchColumn())app_json(['error'=>'فروشگاه پیدا نشد.'],404);

    $stmt=$db->prepare(
        "SELECT p.limits_json
         FROM tenant_subscriptions s
         JOIN platform_plans p ON p.id=s.plan_id
         WHERE s.tenant_id=? AND s.status='active'
         ORDER BY s.id DESC LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$tenantId]);
    $raw=$stmt->fetchColumn();
    if($raw===false)return;
    $limits=json_decode((string)$raw,true);
    if(!is_array($limits)||!array_key_exists($key,$limits))return;
    $limit=$limits[$key];
    if(is_string($limit)&&ctype_digit($limit))$limit=(int)$limit;
    if(!is_int($limit)||$limit<0)return;

    if($key==='products'){
        $usage=$db->prepare('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1');
        $usage->execute([$tenantId]);
    }elseif($key==='staff'){
        $usage=$db->prepare(
            "SELECT COUNT(DISTINCT u.id)
             FROM users u
             JOIN user_roles ur ON ur.tenant_id=u.tenant_id AND ur.user_id=u.id
             JOIN roles r ON r.role_scope_id=ur.role_scope_id AND r.id=ur.role_id
             WHERE u.tenant_id=? AND u.status='active' AND r.role_key<>'customer'"
        );
        $usage->execute([$tenantId]);
    }else{
        $usage=$db->prepare('SELECT COUNT(*) FROM media_assets WHERE tenant_id=?');
        $usage->execute([$tenantId]);
    }
    $current=(int)$usage->fetchColumn();
    if($current+$increment>$limit){
        $labels=['products'=>'محصول فعال','staff'=>'کارمند','media_assets'=>'فایل رسانه'];
        app_json([
            'error'=>'سقف پلن برای '.$labels[$key].' تکمیل شده است. برای ادامه، پلن فروشگاه را ارتقا دهید.',
            'code'=>'plan_limit_reached',
            'limit'=>$limit,
            'current_usage'=>$current
        ],403);
    }
}
