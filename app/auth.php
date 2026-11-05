<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function app_auth_is_development(): bool {
    return in_array(strtolower(app_env('APP_ENV', 'development') ?? 'development'), ['development', 'local', 'testing'], true);
}

function app_auth_secret(): string {
    $secret = app_env('APP_KEY');
    if (!$secret && app_auth_is_development()) {
        return 'local-only-supermarket360-auth-key-change-before-deploy';
    }
    if (!$secret || strlen($secret) < 32) {
        throw new RuntimeException('APP_KEY must contain at least 32 characters.');
    }
    return $secret;
}

function app_auth_mobile(string $value): ?string {
    $mobile = preg_replace('/[\s()-]+/', '', trim($value));
    if (preg_match('/^09\d{9}$/', $mobile)) return $mobile;
    if (preg_match('/^(?:\+98|0098|98)(9\d{9})$/', $mobile, $matches)) {
        return '0' . $matches[1];
    }
    return null;
}

function app_auth_hash(string $value): string {
    return hash_hmac('sha256', $value, app_auth_secret());
}

function app_auth_ip(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return app_auth_hash($ip);
}

function app_auth_hit_limit(PDO $db, int $tenantId, string $action, string $bucket, int $maximum, int $windowSeconds): void {
    $windowSeconds = max(60, min(86400, $windowSeconds));
    $sql = "INSERT INTO auth_rate_limits
              (tenant_id, action, bucket_hash, window_started_at, attempts)
            VALUES (:tenant, :action, :bucket, UTC_TIMESTAMP(), 1)
            ON DUPLICATE KEY UPDATE
              attempts = IF(window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$windowSeconds} SECOND), 1, attempts + 1),
              window_started_at = IF(window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$windowSeconds} SECOND), UTC_TIMESTAMP(), window_started_at)";
    $stmt = $db->prepare($sql);
    $stmt->execute(['tenant' => $tenantId, 'action' => $action, 'bucket' => $bucket]);

    $read = $db->prepare('SELECT attempts FROM auth_rate_limits WHERE tenant_id = ? AND action = ? AND bucket_hash = ?');
    $read->execute([$tenantId, $action, $bucket]);
    if ((int)$read->fetchColumn() > $maximum) {
        app_json(['error' => 'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.'], 429);
    }
}

function app_auth_sms_ready(): bool {
    $provider = strtolower(app_env('SMS_PROVIDER', app_auth_is_development() ? 'log' : 'disabled') ?? 'disabled');
    return app_auth_is_development() && $provider === 'log';
}

function app_auth_send_otp(string $mobile, string $code, int $challengeId): void {
    if (!app_auth_sms_ready()) {
        throw new RuntimeException('SMS provider is not configured. Production OTP delivery is disabled.');
    }
    $masked = substr($mobile, 0, 4) . '******' . substr($mobile, -2);
    error_log('[supermarket360-dev-otp] challenge=' . $challengeId . ' mobile=' . $masked . ' code=' . $code);
}

function app_auth_request_body(): array {
    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > 8192) app_json(['error' => 'حجم درخواست بیش از حد مجاز است.'], 413);
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') app_json(['error' => 'بدنه درخواست معتبر نیست.'], 400);
    if (strlen($raw) > 8192) app_json(['error' => 'حجم درخواست بیش از حد مجاز است.'], 413);
    $body = json_decode($raw, true);
    if (!is_array($body) || array_is_list($body)) {
        app_json(['error' => 'ساختار JSON درخواست معتبر نیست.'], 400);
    }
    return $body;
}

function app_auth_prune(PDO $db, int $tenantId): void {
    $challenges = $db->prepare(
        'DELETE FROM auth_challenges WHERE tenant_id = ? AND expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)'
    );
    $challenges->execute([$tenantId]);

    $limits = $db->prepare(
        'DELETE FROM auth_rate_limits WHERE tenant_id = ? AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)'
    );
    $limits->execute([$tenantId]);
}

function app_auth_register(PDO $db, array $tenant): never {
    $body = app_auth_request_body();
    $name = trim((string)($body['full_name'] ?? ''));
    $mobile = app_auth_mobile((string)($body['mobile'] ?? ''));
    $referralCode = strtoupper(trim((string)($body['referral_code'] ?? '')));
    if ($name === '' || mb_strlen($name) > 160 || $mobile === null ||
        ($referralCode !== '' && !preg_match('/^[A-Z0-9]{4,16}$/', $referralCode))) {
        app_json(['error' => 'نام، شماره موبایل یا کد معرف معتبر نیست.'], 422);
    }

    $referrerId = null;
    if ($referralCode !== '') {
        $referrer = $db->prepare("SELECT id FROM users WHERE tenant_id = ? AND referral_code = ? AND status = 'active' LIMIT 1");
        $referrer->execute([$tenant['id'], $referralCode]);
        $referrerId = $referrer->fetchColumn();
        if (!$referrerId) app_json(['error' => 'کد معرف معتبر نیست.'], 422);
    }

    $existing = $db->prepare('SELECT id, status FROM users WHERE tenant_id = ? AND mobile = ? LIMIT 1');
    $existing->execute([$tenant['id'], $mobile]);
    $row = $existing->fetch();
    if ($row) {
        if ($row['status'] === 'pending') {
            app_json(['message' => 'ثبت‌نام شما قبلاً آغاز شده است؛ کد تأیید را درخواست کنید.'], 200);
        }
        app_json(['error' => 'با این شماره قبلاً حساب کاربری ایجاد شده است؛ وارد شوید.'], 409);
    }

    $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 10));
    $insert = $db->prepare(
        "INSERT INTO users (tenant_id, full_name, mobile, status, referral_code, referred_by_user_id)
         VALUES (?, ?, ?, 'pending', ?, ?)"
    );
    try {
        $insert->execute([$tenant['id'], $name, $mobile, $code, $referrerId]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') app_json(['error' => 'ثبت‌نام با این شماره امکان‌پذیر نیست.'], 409);
        throw $e;
    }
    app_json(['message' => 'حساب اولیه ساخته شد؛ برای فعال‌سازی کد تأیید را درخواست کنید.'], 201);
}

function app_auth_refresh_csrf(PDO $db, array $session): never {
    $csrf = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $stmt = $db->prepare('UPDATE auth_sessions SET csrf_hash = ? WHERE id = ? AND revoked_at IS NULL');
    $stmt->execute([hash('sha256', $csrf), $session['session_id']]);
    app_json(['data' => ['csrf_token' => $csrf]]);
}

function app_auth_request_otp(PDO $db, array $tenant): never {
    if (!app_auth_sms_ready()) {
        app_json(['error' => 'سرویس ارسال پیامک هنوز برای این فروشگاه پیکربندی نشده است.'], 503);
    }

    $body = app_auth_request_body();
    $mobile = app_auth_mobile((string)($body['mobile'] ?? ''));
    if ($mobile === null) app_json(['error' => 'شماره موبایل معتبر نیست.'], 422);

    app_auth_prune($db, (int)$tenant['id']);

    $phoneBucket = app_auth_hash('phone:' . $mobile);
    $ipBucket = app_auth_hash('ip:' . (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    app_auth_hit_limit($db, (int)$tenant['id'], 'otp_request_phone', $phoneBucket, 3, 900);
    app_auth_hit_limit($db, (int)$tenant['id'], 'otp_request_ip', $ipBucket, 10, 900);

    // The same response is returned for unknown, blocked and active accounts.
    $userQuery = $db->prepare(
        "SELECT id FROM users WHERE tenant_id = ? AND mobile = ? AND status IN ('active','pending') LIMIT 1"
    );
    $userQuery->execute([$tenant['id'], $mobile]);
    if (!$userQuery->fetchColumn()) {
        app_json(['message' => 'اگر حساب فعالی با این شماره وجود داشته باشد، کد ورود ارسال می‌شود.'], 202);
    }

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash = hash_hmac('sha256', $code, app_auth_secret());

    $db->beginTransaction();
    try {
        $expireOld = $db->prepare(
            "UPDATE auth_challenges SET consumed_at = UTC_TIMESTAMP()
             WHERE tenant_id = ? AND mobile = ? AND purpose = 'login' AND consumed_at IS NULL"
        );
        $expireOld->execute([$tenant['id'], $mobile]);

        $insert = $db->prepare(
            "INSERT INTO auth_challenges
              (tenant_id, mobile, purpose, otp_hash, expires_at, request_ip_hash)
             VALUES (?, ?, 'login', ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE), ?)"
        );
        $insert->execute([$tenant['id'], $mobile, $otpHash, app_auth_ip()]);
        $challengeId = (int)$db->lastInsertId();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    try {
        app_auth_send_otp($mobile, $code, $challengeId);
    } catch (Throwable $e) {
        $cancel = $db->prepare('UPDATE auth_challenges SET consumed_at = UTC_TIMESTAMP() WHERE id = ?');
        $cancel->execute([$challengeId]);
        throw $e;
    }

    app_json(['message' => 'اگر حساب فعالی با این شماره وجود داشته باشد، کد ورود ارسال می‌شود.'], 202);
}

function app_auth_verify_otp(PDO $db, array $tenant): never {
    $body = app_auth_request_body();
    $mobile = app_auth_mobile((string)($body['mobile'] ?? ''));
    $code = trim((string)($body['code'] ?? ''));
    if ($mobile === null || !preg_match('/^\d{6}$/', $code)) {
        app_json(['error' => 'شماره موبایل یا کد ورود معتبر نیست.'], 422);
    }

    $phoneBucket = app_auth_hash('phone:' . $mobile);
    $ipBucket = app_auth_hash('ip:' . (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    app_auth_hit_limit($db, (int)$tenant['id'], 'otp_verify_phone', $phoneBucket, 10, 900);
    app_auth_hit_limit($db, (int)$tenant['id'], 'otp_verify_ip', $ipBucket, 30, 900);

    $db->beginTransaction();
    try {
        $find = $db->prepare(
            "SELECT id, otp_hash, attempts
             FROM auth_challenges
             WHERE tenant_id = ? AND mobile = ? AND purpose = 'login'
               AND consumed_at IS NULL AND expires_at > UTC_TIMESTAMP()
             ORDER BY id DESC LIMIT 1 FOR UPDATE"
        );
        $find->execute([$tenant['id'], $mobile]);
        $challenge = $find->fetch();
        if (!$challenge) {
            $db->rollBack();
            app_json(['error' => 'کد ورود نامعتبر یا منقضی شده است.'], 422);
        }

        if ((int)$challenge['attempts'] >= 5) {
            $lock = $db->prepare('UPDATE auth_challenges SET consumed_at = UTC_TIMESTAMP() WHERE id = ?');
            $lock->execute([$challenge['id']]);
            $db->commit();
            app_json(['error' => 'تعداد تلاش‌های مجاز تمام شده است. دوباره کد بگیرید.'], 429);
        }

        $candidate = hash_hmac('sha256', $code, app_auth_secret());
        if (!hash_equals((string)$challenge['otp_hash'], $candidate)) {
            $wrong = $db->prepare(
                "UPDATE auth_challenges
                 SET attempts = attempts + 1,
                     consumed_at = IF(attempts >= 5, UTC_TIMESTAMP(), consumed_at)
                 WHERE id = ?"
            );
            $wrong->execute([$challenge['id']]);
            $db->commit();
            app_json(['error' => 'کد ورود نامعتبر یا منقضی شده است.'], 422);
        }

        $userQuery = $db->prepare(
            "SELECT id, full_name, mobile, referral_code
             FROM users WHERE tenant_id = ? AND mobile = ? AND status = 'active'
             LIMIT 1 FOR UPDATE"
        );
        $userQuery->execute([$tenant['id'], $mobile]);
        $user = $userQuery->fetch();
        if (!$user) {
            $consume = $db->prepare('UPDATE auth_challenges SET consumed_at = UTC_TIMESTAMP() WHERE id = ?');
            $consume->execute([$challenge['id']]);
            $db->commit();
            app_json(['error' => 'حساب کاربری فعال نیست.'], 403);
        }

        $consume = $db->prepare('UPDATE auth_challenges SET consumed_at = UTC_TIMESTAMP() WHERE id = ?');
        $consume->execute([$challenge['id']]);

        if ($user['status'] === 'pending') {
            $activate = $db->prepare("UPDATE users SET status = 'active', mobile_verified_at = UTC_TIMESTAMP() WHERE tenant_id = ? AND id = ? AND status = 'pending'");
            $activate->execute([$tenant['id'], $user['id']]);
            if ($user['referred_by_user_id'] !== null) {
                $referral = $db->prepare(
                    "INSERT IGNORE INTO referral_events (tenant_id, referrer_user_id, referred_user_id, status)
                     VALUES (?, ?, ?, 'pending')"
                );
                $referral->execute([$tenant['id'], $user['referred_by_user_id'], $user['id']]);
            }
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $csrf = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+14 days');
        $sessionInsert = $db->prepare(
            "INSERT INTO auth_sessions
              (tenant_id, user_id, token_hash, csrf_hash, expires_at, ip_hash, user_agent_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $sessionInsert->execute([
            $tenant['id'],
            $user['id'],
            hash('sha256', $token),
            hash('sha256', $csrf),
            $expiresAt->format('Y-m-d H:i:s'),
            app_auth_ip(),
            app_auth_hash(substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)),
        ]);

        $secure = isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        if (strtolower(app_env('APP_ENV', 'development') ?? 'development') === 'production' && !$secure) {
            throw new RuntimeException('HTTPS is required before issuing an authentication cookie.');
        }
        $cookieSet = setcookie('sm_session', $token, [
            'expires' => $expiresAt->getTimestamp(),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!$cookieSet) throw new RuntimeException('Unable to issue authentication cookie.');

        $db->commit();
        app_json([
            'data' => [
                'user' => [
                    'id' => (int)$user['id'],
                    'full_name' => $user['full_name'],
                    'mobile' => $user['mobile'],
                    'referral_code' => $user['referral_code'],
                ],
                'csrf_token' => $csrf,
                'expires_at' => $expiresAt->format(DateTimeInterface::ATOM),
            ],
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function app_auth_current(PDO $db, array $tenant): ?array {
    $token = (string)($_COOKIE['sm_session'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) return null;

    $stmt = $db->prepare(
        "SELECT s.id AS session_id, s.csrf_hash, u.id, u.full_name, u.mobile, u.referral_code
         FROM auth_sessions s
         JOIN users u ON u.id = s.user_id AND u.tenant_id = s.tenant_id
         WHERE s.tenant_id = ? AND s.token_hash = ? AND s.revoked_at IS NULL
           AND s.expires_at > UTC_TIMESTAMP() AND u.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$tenant['id'], hash('sha256', $token)]);
    $session = $stmt->fetch();
    if (!$session) return null;

    $touch = $db->prepare('UPDATE auth_sessions SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?');
    $touch->execute([$session['session_id']]);
    return $session;
}

function app_auth_require(PDO $db, array $tenant): array {
    $session = app_auth_current($db, $tenant);
    if (!$session) app_json(['error' => 'برای ادامه وارد حساب کاربری شوید.'], 401);
    return $session;
}

function app_auth_require_csrf(array $session): void {
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($provided === '' || !hash_equals((string)$session['csrf_hash'], hash('sha256', $provided))) {
        app_json(['error' => 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.'], 419);
    }
}

function app_auth_logout(PDO $db, array $session): never {
    $stmt = $db->prepare('UPDATE auth_sessions SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND revoked_at IS NULL');
    $stmt->execute([$session['session_id']]);
    setcookie('sm_session', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    app_json(['message' => 'با موفقیت خارج شدید.']);
}
