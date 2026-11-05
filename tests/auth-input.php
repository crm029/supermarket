<?php
declare(strict_types=1);

putenv('APP_ENV=testing');
putenv('APP_KEY=unit-test-secret-with-more-than-thirty-two-characters');

require_once dirname(__DIR__) . '/app/auth.php';

$valid = [
    '09123456789' => '09123456789',
    '+989123456789' => '09123456789',
    '00989123456789' => '09123456789',
    '989123456789' => '09123456789',
    ' 09123456789 ' => '09123456789',
];

foreach ($valid as $input => $expected) {
    if (app_auth_mobile((string)$input) !== $expected) {
        throw new RuntimeException('Mobile normalization failed for: ' . $input);
    }
}

foreach (['123456789', '0912345678', '08123456789', '+441234567890', '09abc456789'] as $invalid) {
    if (app_auth_mobile((string)$invalid) !== null) {
        throw new RuntimeException('Invalid mobile number was accepted: ' . $invalid);
    }
}

putenv('SMS_PROVIDER=kavenegar');
putenv('KAVENEGAR_API_KEY');
putenv('KAVENEGAR_VERIFY_TEMPLATE');
if (app_auth_sms_ready()) {
    throw new RuntimeException('Kavenegar must remain disabled without credentials.');
}
putenv('KAVENEGAR_API_KEY=test-private-api-key');
putenv('KAVENEGAR_VERIFY_TEMPLATE=supermarket_verify');
if (!app_auth_sms_ready()) {
    throw new RuntimeException('Kavenegar should be ready when both server credentials are configured.');
}
putenv('APP_ENV=production');
putenv('SMS_PROVIDER=log');
if (app_auth_sms_ready()) {
    throw new RuntimeException('Development log SMS provider must never be enabled in production.');
}
putenv('APP_ENV=testing');

if (strlen(app_auth_secret()) < 32) {
    throw new RuntimeException('Authentication secret validation failed.');
}

echo "Authentication input checks passed.\n";
