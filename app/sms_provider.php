<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

function app_sms_secret_encrypt(string $value, string $field): string {
    if (!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL is required to protect SMS credentials.');
    $key = hash('sha256', 'supermarket360:sms:' . $field . ':v1:' . app_auth_secret(), true);
    $nonce = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'supermarket360:sms:' . $field . ':v1', 16);
    if (!is_string($cipher) || strlen($tag) !== 16) throw new RuntimeException('Unable to protect SMS credentials.');
    return 'v1:' . base64_encode($nonce . $tag . $cipher);
}

function app_sms_secret_decrypt(?string $stored, string $field): ?string {
    if ($stored === null || $stored === '') return null;
    if (!str_starts_with($stored, 'v1:') || !function_exists('openssl_decrypt')) throw new RuntimeException('Stored SMS credentials cannot be decrypted.');
    $packed = base64_decode(substr($stored, 3), true);
    if (!is_string($packed) || strlen($packed) < 29) throw new RuntimeException('Stored SMS credentials are invalid.');
    $key = hash('sha256', 'supermarket360:sms:' . $field . ':v1:' . app_auth_secret(), true);
    $plain = openssl_decrypt(substr($packed, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($packed, 0, 12), substr($packed, 12, 16), 'supermarket360:sms:' . $field . ':v1');
    if (!is_string($plain) || $plain === '') throw new RuntimeException('Stored SMS credentials cannot be decrypted.');
    return $plain;
}

function app_sms_settings(PDO $db): array {
    $row = $db->query('SELECT provider,sender_number,username_encrypted,password_encrypted,domain,updated_at FROM platform_sms_settings WHERE id=1')->fetch();
    if (!$row) return ['provider'=>'disabled','sender_number'=>'','username'=>null,'password'=>null,'domain'=>'0098','updated_at'=>null];
    return [
        'provider'=>(string)$row['provider'],
        'sender_number'=>(string)($row['sender_number'] ?? ''),
        'username'=>app_sms_secret_decrypt($row['username_encrypted'] ?? null,'username'),
        'password'=>app_sms_secret_decrypt($row['password_encrypted'] ?? null,'password'),
        'domain'=>(string)$row['domain'],
        'updated_at'=>$row['updated_at'] ?? null,
    ];
}

function app_sms_platform_settings_read(PDO $db): never {
    $s=app_sms_settings($db);
    app_json(['data'=>[
        'provider'=>$s['provider'],
        'sender_number'=>$s['sender_number'],
        'domain'=>$s['domain'],
        'username_configured'=>is_string($s['username']) && $s['username']!=='',
        'password_configured'=>is_string($s['password']) && $s['password']!=='',
        'username_masked'=>empty($s['username'])?null:'••••••••',
        'updated_at'=>$s['updated_at'],
    ]]);
}

function app_sms_platform_settings_update(PDO $db, array $tenant, array $session): never {
    $b=app_auth_request_body();
    $provider=(string)($b['provider']??'');
    $sender=trim((string)($b['sender_number']??''));
    $username=trim((string)($b['username']??''));
    $password=(string)($b['password']??'');
    $domain=trim((string)($b['domain']??'0098'));
    if (!in_array($provider,['disabled','0098vip','kavenegar'],true) ||
        ($provider==='0098vip' && (!preg_match('/^\d{5,20}$/',$sender) || $domain!=='0098' ||
            ($username!=='' && strlen($username)>128) || ($password!=='' && strlen($password)>256))) ||
        ($provider==='kavenegar' && $sender!=='' && !preg_match('/^\d{5,20}$/',$sender))) {
        app_json(['error'=>'تنظیمات سرویس پیامک معتبر نیست.'],422);
    }
    $db->beginTransaction();
    try {
        $old=$db->query('SELECT username_encrypted,password_encrypted FROM platform_sms_settings WHERE id=1 FOR UPDATE')->fetch() ?: [];
        $encUser=$old['username_encrypted']??null;
        $encPass=$old['password_encrypted']??null;
        if($username!=='')$encUser=app_sms_secret_encrypt($username,'username');
        if($password!=='')$encPass=app_sms_secret_encrypt($password,'password');
        if($provider==='0098vip'){
            $plainUser=app_sms_secret_decrypt(is_string($encUser)?$encUser:null,'username');
            $plainPass=app_sms_secret_decrypt(is_string($encPass)?$encPass:null,'password');
            if(!$plainUser || !$plainPass){$db->rollBack();app_json(['error'=>'برای فعال‌سازی ۰۰۹۸، نام کاربری و رمز عبور را وارد کنید.'],422);}
        }
        $q=$db->prepare("INSERT INTO platform_sms_settings(id,provider,sender_number,username_encrypted,password_encrypted,domain,updated_by)
          VALUES(1,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE provider=VALUES(provider),sender_number=VALUES(sender_number),
          username_encrypted=VALUES(username_encrypted),password_encrypted=VALUES(password_encrypted),domain=VALUES(domain),
          updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()");
        $q->execute([$provider,$sender!==''?$sender:null,$encUser,$encPass,$domain,(int)$session['id']]);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'platform.sms_provider.updated','platform_sms_settings',1,[
            'provider'=>$provider,'credentials_changed'=>($username!==''||$password!==''),
            'sender_number'=>$sender
        ]);
        $db->commit();
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    app_json(['message'=>'تنظیمات پیامک با موفقیت ذخیره شد.']);
}
