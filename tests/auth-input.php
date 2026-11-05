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
    if (app_auth_mobile($input) !== $expected) {
        throw new RuntimeException('Mobile normalization failed for: ' . $input);
    }
}

foreach (['123456789', '0912345678', '08123456789', '+441234567890', '09abc456789'] as $invalid) {
    if (app_auth_mobile((string)$invalid) !== null) {
        throw new RuntimeException('Invalid mobile number was accepted: ' . $invalid);
    }
}

if (strlen(app_auth_secret()) < 32) {
    throw new RuntimeException('Authentication secret validation failed.');
}

echo "Authentication input checks passed.\n";
