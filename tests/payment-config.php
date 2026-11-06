<?php
declare(strict_types=1);

putenv('APP_ENV=testing');
putenv('APP_KEY=ci-payment-config-test-key-which-is-long-enough-32');
putenv('APP_BASE_URL=https://market.example/subdir');
putenv('ZARINPAL_MERCHANT_ID=00000000-0000-0000-0000-000000000000');
putenv('ZARINPAL_SANDBOX=true');

require_once dirname(__DIR__) . '/app/payments.php';

$config = app_payment_config();
if ($config['callback_url'] !== 'https://market.example/subdir/api/v1/payments/zarinpal/callback' ||
    $config['request_url'] !== 'https://sandbox.zarinpal.com/pg/v4/payment/request.json' ||
    $config['verify_url'] !== 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json' ||
    $config['start_pay'] !== 'https://sandbox.zarinpal.com/pg/StartPay/') {
    throw new RuntimeException('ZarinPal sandbox configuration is incorrect.');
}

$encrypted = app_payment_merchant_encrypt('merchant-secret-1234');
if (app_payment_merchant_decrypt($encrypted) !== 'merchant-secret-1234' || str_contains($encrypted, 'merchant-secret-1234')) {
    throw new RuntimeException('ZarinPal merchant encryption is not protecting the stored value.');
}
$snapshot = app_payment_config(null, ['gateway_mode' => 'sandbox', 'merchant_id_encrypted' => $encrypted]);
if ($snapshot['merchant_id'] !== 'merchant-secret-1234' || $snapshot['mode'] !== 'sandbox') {
    throw new RuntimeException('Payment attempt credential snapshot was not restored correctly.');
}

putenv('APP_ENV=testing');
putenv('ZARINPAL_SANDBOX=false');
$liveRejected = false;
try {
    app_payment_config();
} catch (RuntimeException $e) {
    $liveRejected = true;
}
if (!$liveRejected) throw new RuntimeException('Live gateway mode was allowed outside production.');

putenv('APP_ENV=production');
putenv('APP_BASE_URL=https://market.example');
$live = app_payment_config();
if ($live['request_url'] !== 'https://api.zarinpal.com/pg/v4/payment/request.json' ||
    $live['verify_url'] !== 'https://api.zarinpal.com/pg/v4/payment/verify.json' ||
    $live['start_pay'] !== 'https://www.zarinpal.com/pg/StartPay/') {
    throw new RuntimeException('ZarinPal live configuration is incorrect.');
}

putenv('APP_ENV=production');
putenv('APP_BASE_URL=http://market.example');
putenv('ZARINPAL_SANDBOX=true');
$rejected = false;
try {
    app_payment_config();
} catch (RuntimeException $e) {
    $rejected = true;
}
if (!$rejected) throw new RuntimeException('Production payment callback accepted an insecure HTTP origin.');

echo "Payment configuration tests passed.\n";
