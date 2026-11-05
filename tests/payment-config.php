<?php
declare(strict_types=1);

putenv('APP_ENV=testing');
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

putenv('APP_ENV=production');
putenv('APP_BASE_URL=http://market.example');
$rejected = false;
try {
    app_payment_config();
} catch (RuntimeException $e) {
    $rejected = true;
}
if (!$rejected) throw new RuntimeException('Production payment callback accepted an insecure HTTP origin.');

echo "Payment configuration tests passed.\n";
