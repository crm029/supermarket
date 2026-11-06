<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/wallet.php';
require_once __DIR__ . '/stock_batches.php';

function app_payment_merchant_encrypt(string $merchant): string {
    if (!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL is required to protect payment credentials.');
    $key = hash('sha256', 'supermarket360:zarinpal:merchant:v1:' . app_auth_secret(), true);
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($merchant, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'supermarket360:zarinpal:v1', 16);
    if (!is_string($ciphertext) || strlen($tag) !== 16) throw new RuntimeException('Unable to protect payment credentials.');
    return 'v1:' . base64_encode($nonce . $tag . $ciphertext);
}

function app_payment_merchant_decrypt(?string $stored): ?string {
    if ($stored === null || $stored === '') return null;
    if (!str_starts_with($stored, 'v1:') || !function_exists('openssl_decrypt')) {
        throw new RuntimeException('Stored payment credentials cannot be decrypted.');
    }
    $packed = base64_decode(substr($stored, 3), true);
    if (!is_string($packed) || strlen($packed) < 29) throw new RuntimeException('Stored payment credentials are invalid.');
    $key = hash('sha256', 'supermarket360:zarinpal:merchant:v1:' . app_auth_secret(), true);
    $plain = openssl_decrypt(substr($packed, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($packed, 0, 12), substr($packed, 12, 16), 'supermarket360:zarinpal:v1');
    if (!is_string($plain) || $plain === '') throw new RuntimeException('Stored payment credentials cannot be decrypted.');
    return $plain;
}

function app_payment_config(?PDO $db = null, ?array $attemptSnapshot = null): array {
    $merchant = '';
    $mode = null;
    $encryptedMerchant = null;

    if ($attemptSnapshot !== null && isset($attemptSnapshot['gateway_mode'])) {
        $mode = (string)$attemptSnapshot['gateway_mode'];
        $encryptedMerchant = $attemptSnapshot['merchant_id_encrypted'] ?? null;
    }
    if ($db !== null && ($mode === null || !$encryptedMerchant)) {
        $settings = $db->query('SELECT merchant_id_encrypted, mode FROM platform_payment_settings WHERE id = 1 LIMIT 1')->fetch();
        if ($settings) {
            if ($mode === null) $mode = (string)$settings['mode'];
            if (!$encryptedMerchant) $encryptedMerchant = $settings['merchant_id_encrypted'] ?? null;
        }
    }
    $merchant = app_payment_merchant_decrypt(is_string($encryptedMerchant) ? $encryptedMerchant : null) ?? trim((string)(app_env('ZARINPAL_MERCHANT_ID') ?? ''));
    if ($mode === null) {
        $mode = filter_var(app_env('ZARINPAL_SANDBOX', 'true'), FILTER_VALIDATE_BOOLEAN) ? 'sandbox' : 'live';
    }
    if (!in_array($mode, ['sandbox', 'live'], true)) throw new RuntimeException('ZarinPal gateway mode is invalid.');

    $baseUrl = rtrim(trim((string)(app_env('APP_BASE_URL') ?? '')), '/');
    $parts = $baseUrl !== '' ? parse_url($baseUrl) : false;
    if ($merchant === '' || $parts === false || empty($parts['scheme']) || empty($parts['host']) ||
        !in_array(strtolower((string)$parts['scheme']), ['https', 'http'], true) ||
        isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        throw new RuntimeException('ZarinPal merchant ID and a valid APP_BASE_URL are required.');
    }
    $production = strtolower(app_env('APP_ENV', 'development') ?? 'development') === 'production';
    if ($production && strtolower((string)$parts['scheme']) !== 'https') {
        throw new RuntimeException('APP_BASE_URL must use HTTPS in production.');
    }
    if ($mode === 'live' && (!$production || strtolower((string)$parts['scheme']) !== 'https')) {
        throw new RuntimeException('درگاه واقعی فقط روی محیط production با نشانی HTTPS فعال می‌شود.');
    }
    $sandbox = $mode === 'sandbox';
    $apiBase = $sandbox ? 'https://sandbox.zarinpal.com/pg/v4/payment' : 'https://api.zarinpal.com/pg/v4/payment';
    $startPay = $sandbox ? 'https://sandbox.zarinpal.com/pg/StartPay/' : 'https://www.zarinpal.com/pg/StartPay/';
    return [
        'merchant_id' => $merchant,
        'merchant_id_encrypted' => app_payment_merchant_encrypt($merchant),
        'mode' => $mode,
        'callback_url' => $baseUrl . '/api/v1/payments/zarinpal/callback',
        'request_url' => $apiBase . '/request.json',
        'verify_url' => $apiBase . '/verify.json',
        'start_pay' => $startPay,
    ];
}

function app_payment_platform_settings_read(PDO $db): never {
    $row = $db->query('SELECT merchant_id_encrypted, mode, updated_at FROM platform_payment_settings WHERE id = 1 LIMIT 1')->fetch();
    $merchant = $row ? app_payment_merchant_decrypt($row['merchant_id_encrypted'] ?? null) : null;
    if (!$merchant) $merchant = trim((string)(app_env('ZARINPAL_MERCHANT_ID') ?? ''));
    $mode = $row ? (string)$row['mode'] : (filter_var(app_env('ZARINPAL_SANDBOX', 'true'), FILTER_VALIDATE_BOOLEAN) ? 'sandbox' : 'live');
    app_json(['data' => [
        'mode' => $mode,
        'merchant_configured' => $merchant !== '',
        'merchant_masked' => $merchant === '' ? null : '••••••••' . substr($merchant, -4),
        'updated_at' => $row['updated_at'] ?? null,
    ]]);
}

function app_payment_platform_settings_update(PDO $db, array $tenant, array $session): never {
    $body = app_auth_request_body();
    $mode = (string)($body['mode'] ?? '');
    $merchantInput = trim((string)($body['merchant_id'] ?? ''));
    $confirmation = (string)($body['confirmation_phrase'] ?? '');
    if (!in_array($mode, ['sandbox', 'live'], true) ||
        ($merchantInput !== '' && !preg_match('/^[A-Za-z0-9-]{8,64}$/', $merchantInput)) ||
        ($mode === 'live' && $confirmation !== 'REAL')) {
        app_json(['error' => 'تنظیمات درگاه معتبر نیست؛ برای حالت واقعی عبارت REAL الزامی است.'], 422);
    }

    $db->beginTransaction();
    try {
        $row = $db->query('SELECT merchant_id_encrypted, mode FROM platform_payment_settings WHERE id = 1 FOR UPDATE')->fetch();
        $previousMode = $row ? (string)$row['mode'] : 'sandbox';
        $encrypted = $row['merchant_id_encrypted'] ?? null;
        if ($merchantInput !== '') $encrypted = app_payment_merchant_encrypt($merchantInput);
        $currentMerchant = app_payment_merchant_decrypt(is_string($encrypted) ? $encrypted : null)
            ?? trim((string)(app_env('ZARINPAL_MERCHANT_ID') ?? ''));
        if ($currentMerchant === '') {
            app_json(['error' => 'ابتدا شناسه مرچنت زرین‌پال را وارد کنید.'], 422);
        }
        $save = $db->prepare(
            'INSERT INTO platform_payment_settings (id,merchant_id_encrypted,mode,updated_by)
             VALUES (1,?,?,?) ON DUPLICATE KEY UPDATE merchant_id_encrypted=VALUES(merchant_id_encrypted),
             mode=VALUES(mode),updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()'
        );
        $save->execute([$encrypted, $mode, (int)$session['id']]);
        app_audit_log($db, (int)$tenant['id'], (int)$session['id'], 'platform.payment_gateway.updated', 'platform_payment_settings', 1, [
            'mode_from' => $previousMode,
            'mode_to' => $mode,
            'merchant_id_changed' => $merchantInput !== '',
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    app_json(['message' => 'تنظیمات درگاه با موفقیت ذخیره شد.', 'data' => ['mode' => $mode]]);
}

function app_payment_http_post(string $url, array $payload): array {
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required for ZarinPal.');
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) throw new RuntimeException('Unable to encode payment request.');
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_USERAGENT => 'Supermarket360/1.0 ZarinPal-v4',
    ]);
    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if (!is_string($body) || $error !== '' || $status < 200 || $status >= 300) {
        throw new RuntimeException('Payment provider connection failed (HTTP ' . $status . ').');
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) throw new RuntimeException('Payment provider returned invalid JSON.');
    return $decoded;
}

function app_payment_start(PDO $db, array $tenant, array $session): never {
    $body = app_auth_request_body();
    $orderId = filter_var($body['order_id'] ?? null, FILTER_VALIDATE_INT);
    if ($orderId === false || $orderId < 1) app_json(['error' => 'شناسه سفارش معتبر نیست.'], 422);

    // Serialize gateway-attempt creation against wallet settlement on the same order.
    $db->beginTransaction();
    try {
        $orderQuery = $db->prepare(
            "SELECT id, order_number, status, total_rial
             FROM orders WHERE tenant_id = ? AND user_id = ? AND id = ? FOR UPDATE"
        );
        $orderQuery->execute([$tenant['id'], $session['id'], $orderId]);
        $order = $orderQuery->fetch();
        if (!$order) {
            $db->rollBack();
            app_json(['error' => 'سفارش پیدا نشد.'], 404);
        }
        if ($order['status'] !== 'pending_payment') {
            $db->rollBack();
            app_json(['error' => 'این سفارش در انتظار پرداخت نیست.'], 409);
        }

        $reservation = $db->prepare(
            "SELECT 1 FROM stock_reservations
             WHERE tenant_id = ? AND order_id = ? AND status = 'active'
               AND expires_at > UTC_TIMESTAMP() LIMIT 1 FOR UPDATE"
        );
        $reservation->execute([$tenant['id'], $orderId]);
        if (!$reservation->fetchColumn()) {
            $db->rollBack();
            app_json(['error' => 'مهلت رزرو موجودی تمام شده است؛ سفارش جدید ثبت کنید.'], 409);
        }

        try {
            $config = app_payment_config($db);
        } catch (Throwable $e) {
            $db->rollBack();
            app_json(['error' => 'درگاه پرداخت هنوز پیکربندی نشده است.'], 503);
        }
        // Recover attempts left in 'created' if the web process died before contacting the gateway.
        $expireStale = $db->prepare(
            "UPDATE payment_attempts SET status='failed'
             WHERE tenant_id=? AND order_id=? AND provider='zarinpal' AND status='created'
               AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 MINUTE)"
        );
        $expireStale->execute([$tenant['id'],$orderId]);
        $existing = $db->prepare(
            "SELECT authority, status FROM payment_attempts
             WHERE tenant_id = ? AND order_id = ? AND provider = 'zarinpal'
             ORDER BY id DESC LIMIT 1 FOR UPDATE"
        );
        $existing->execute([$tenant['id'], $orderId]);
        $previous = $existing->fetch();
        if ($previous && $previous['status'] === 'redirected' && $previous['authority']) {
            $db->commit();
            app_json(['data' => ['redirect_url' => $config['start_pay'] . $previous['authority'],
                'authority' => $previous['authority'], 'order_number' => $order['order_number']]]);
        }
        if ($previous && $previous['status'] === 'verified') {
            $db->commit();
            app_json(['data' => ['already_paid' => true, 'order_number' => $order['order_number']]]);
        }

        $idempotency = 'order-' . $orderId . '-' . bin2hex(random_bytes(6));
        $insert = $db->prepare(
            "INSERT INTO payment_attempts
             (tenant_id,order_id,provider,idempotency_key,amount_rial,gateway_mode,merchant_id_encrypted,status)
             VALUES (?, ?, 'zarinpal', ?, ?, ?, ?, 'created')"
        );
        $insert->execute([$tenant['id'], $orderId, $idempotency, $order['total_rial'], $config['mode'], $config['merchant_id_encrypted']]);
        $attemptId = (int)$db->lastInsertId();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    try {
        $provider = app_payment_http_post($config['request_url'], [
            'merchant_id' => $config['merchant_id'],
            'amount' => (int)$order['total_rial'],
            'callback_url' => $config['callback_url'],
            'description' => 'پرداخت سفارش ' . $order['order_number'],
            'metadata' => ['mobile' => (string)$session['mobile']],
        ]);
        $code = (int)($provider['data']['code'] ?? 0);
        $authority = (string)($provider['data']['authority'] ?? '');
        if ($code !== 100 || !preg_match('/^[A-Za-z0-9_-]{5,64}$/', $authority)) {
            $fail = $db->prepare("UPDATE payment_attempts SET status = 'failed' WHERE tenant_id = ? AND id = ? AND status = 'created'");
            $fail->execute([$tenant['id'], $attemptId]);
            app_json(['error' => 'درگاه پرداخت درخواست را نپذیرفت.'], 502);
        }
        $update = $db->prepare(
            "UPDATE payment_attempts SET authority = ?, status = 'redirected'
             WHERE tenant_id = ? AND id = ? AND status = 'created'"
        );
        $update->execute([$authority, $tenant['id'], $attemptId]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Payment attempt state changed unexpectedly.');
        app_json(['data' => [
            'redirect_url' => $config['start_pay'] . $authority,
            'authority' => $authority,
            'order_number' => $order['order_number'],
        ]]);
    } catch (Throwable $e) {
        $fail = $db->prepare("UPDATE payment_attempts SET status = 'failed' WHERE tenant_id = ? AND id = ? AND status = 'created'");
        $fail->execute([$tenant['id'], $attemptId]);
        error_log('[supermarket360-payment] ' . $e->getMessage());
        app_json(['error' => 'ارتباط با درگاه پرداخت برقرار نشد. کمی بعد دوباره تلاش کنید.'], 502);
    }
}

function app_payment_redirect_result(string $status, ?int $orderId = null): never {
    $base = rtrim((string)(app_env('APP_BASE_URL') ?? ''), '/');
    $path = $base !== '' ? (string)(parse_url($base, PHP_URL_PATH) ?? '') : '';
    $path = '/' . trim($path, '/');
    if ($path === '/') $path = '';
    $query = 'status=' . rawurlencode($status);
    if ($orderId !== null && $orderId > 0) $query .= '&order=' . $orderId;
    header('Location: ' . $path . '/payment-result.php?' . $query, true, 303);
    exit;
}

function app_payment_callback(PDO $db, array $tenant): never {
    $authority = trim((string)($_GET['Authority'] ?? ''));
    $status = strtoupper(trim((string)($_GET['Status'] ?? '')));
    if (!preg_match('/^[A-Za-z0-9_-]{5,64}$/', $authority)) {
        app_payment_redirect_result('failed');
    }

    $find = $db->prepare(
        "SELECT id, order_id, amount_rial, status, gateway_mode, merchant_id_encrypted
         FROM payment_attempts
         WHERE tenant_id = ? AND provider = 'zarinpal' AND authority = ?
         LIMIT 1"
    );
    $find->execute([$tenant['id'], $authority]);
    $attempt = $find->fetch();
    if (!$attempt) {
        app_payment_redirect_result('failed');
    }
    if ($attempt['status'] === 'verified') {
        app_payment_redirect_result('success', (int)$attempt['order_id']);
    }
    if ($status !== 'OK') {
        $fail = $db->prepare("UPDATE payment_attempts SET status = 'failed' WHERE id = ? AND status <> 'verified'");
        $fail->execute([$attempt['id']]);
        app_payment_redirect_result('failed', (int)$attempt['order_id']);
    }

    try {
        $config = app_payment_config($db, $attempt);
        $provider = app_payment_http_post($config['verify_url'], [
            'merchant_id' => $config['merchant_id'],
            'amount' => (int)$attempt['amount_rial'],
            'authority' => $authority,
        ]);
        $code = (int)($provider['data']['code'] ?? 0);
        $reference = (string)($provider['data']['ref_id'] ?? '');
        if (!in_array($code, [100, 101], true) || $reference === '') {
            $fail = $db->prepare("UPDATE payment_attempts SET status = 'failed' WHERE id = ? AND status <> 'verified'");
            $fail->execute([$attempt['id']]);
            app_payment_redirect_result('failed', (int)$attempt['order_id']);
        }

        $db->beginTransaction();
        // Keep the lock order consistent with wallet settlement: order first, payment attempt second.
        $orderQuery = $db->prepare('SELECT id, user_id, order_number, status FROM orders WHERE tenant_id = ? AND id = ? FOR UPDATE');
        $orderQuery->execute([$tenant['id'], $attempt['order_id']]);
        $order = $orderQuery->fetch();
        if (!$order) throw new RuntimeException('Payment order not found.');

        $lockAttempt = $db->prepare('SELECT id, order_id, status FROM payment_attempts WHERE tenant_id = ? AND id = ? FOR UPDATE');
        $lockAttempt->execute([$tenant['id'], $attempt['id']]);
        $lockedAttempt = $lockAttempt->fetch();
        if (!$lockedAttempt) throw new RuntimeException('Payment attempt disappeared.');
        if ($lockedAttempt['status'] === 'verified') {
            $db->commit();
            app_payment_redirect_result('success', (int)$attempt['order_id']);
        }

        $reservationResult=app_stock_commit_order_reservations(
            $db,(int)$tenant['id'],(int)$order['id'],(int)$order['user_id'],
            'پرداخت تأییدشده','آزادسازی رزرو ناقص'
        );
        $hasExpired=(bool)$reservationResult['has_expired'];
        if(!$reservationResult['committed']){
            // Payment may arrive after an expiry worker ran. Record it as paid and flag it for manual fulfilment review.
            $audit=$db->prepare(
                "INSERT INTO audit_logs (tenant_id,actor_user_id,action,entity_type,entity_id,details)
                 VALUES (?,?,'payment.paid_without_active_reservation','order',?,JSON_OBJECT('authority',?))"
            );
            $audit->execute([$tenant['id'],$order['user_id'],$order['id'],$authority]);
        }

        $verified = $db->prepare(
            "UPDATE payment_attempts SET status = 'verified', provider_reference = ?, verified_at = UTC_TIMESTAMP()
             WHERE id = ? AND status <> 'verified'"
        );
        $verified->execute([$reference,$attempt['id']]);
        $paid = $db->prepare("UPDATE orders SET status = 'paid' WHERE tenant_id = ? AND id = ? AND status IN ('pending_payment','cancelled')");
        $paid->execute([$tenant['id'],$order['id']]);
        if ($paid->rowCount() === 1) {
            app_wallet_process_referral_reward($db, (int)$tenant['id'], (int)$order['id'], (int)$order['user_id'], (int)$attempt['amount_rial']);
            $paymentBody=$hasExpired
                ? 'پرداخت سفارش '.$order['order_number'].' تأیید شد؛ به دلیل پایان زمان رزرو، فروشگاه باید موجودی را دوباره بررسی کند.'
                : 'پرداخت سفارش '.$order['order_number'].' با موفقیت تأیید شد.';
            app_notification_create($db,(int)$tenant['id'],(int)$order['user_id'],'payment-confirmed-'.$order['id'],'order',
                'پرداخت سفارش تأیید شد',$paymentBody,'order',(int)$order['id']);
            app_notification_notify_permission($db,(int)$tenant['id'],'orders.manage','payment-confirmed-'.$order['id'],'order',
                'پرداخت سفارش دریافت شد',$paymentBody,'order',(int)$order['id']);
        }
        $db->commit();
        app_payment_redirect_result('success', (int)$order['id']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[supermarket360-payment-callback] ' . $e->getMessage());
        app_payment_redirect_result('review', (int)$attempt['order_id']);
    }
}
