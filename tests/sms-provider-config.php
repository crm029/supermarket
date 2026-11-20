<?php
declare(strict_types=1);
putenv('APP_ENV=testing');
putenv('APP_KEY=ci-sms-provider-test-secret-with-more-than-32-characters');
require_once dirname(__DIR__) . '/app/sms_provider.php';
$secret='example-password-not-a-real-credential';
$encrypted=app_sms_secret_encrypt($secret,'password');
if(app_sms_secret_decrypt($encrypted,'password')!==$secret || str_contains($encrypted,$secret)) {
    throw new RuntimeException('SMS credential encryption/decryption failed.');
}
$wrongFieldRejected=false;
try { app_sms_secret_decrypt($encrypted,'username'); } catch (RuntimeException $e) { $wrongFieldRejected=true; }
if(!$wrongFieldRejected) throw new RuntimeException('SMS credential field separation failed.');
$tampered = $encrypted;
$tampered[strlen($tampered)-2] = $tampered[strlen($tampered)-2] === 'A' ? 'B' : 'A';
$tamperRejected = false;
try { app_sms_secret_decrypt($tampered,'password'); } catch (RuntimeException $e) { $tamperRejected = true; }
if(!$tamperRejected) throw new RuntimeException('SMS credential tamper detection failed.');
if(!function_exists('app_sms_0098_send_text') || !function_exists('app_sms_platform_test')) {
    throw new RuntimeException('0098 owner test-send workflow is missing.');
}
if(!app_sms_0098_response_success('0') || !app_sms_0098_response_success(" 0\n") ||
   !app_sms_0098_response_success('0.0') || app_sms_0098_response_success('00') ||
   app_sms_0098_response_success('1') || app_sms_0098_response_success('12') ||
   app_sms_0098_response_success('success')) {
    throw new RuntimeException('0098 provider response-code validation failed.');
}
$mobileCases = [
    '09123456789' => '09123456789',
    '+989123456789' => '09123456789',
    '00989123456789' => '09123456789',
    '989123456789' => '09123456789',
    '0912345678' => null,
    '02112345678' => null,
];
foreach ($mobileCases as $input => $expected) {
    if (app_auth_mobile($input) !== $expected) {
        throw new RuntimeException('Iranian mobile normalization failed for ' . $input . '.');
    }
}
echo "SMS provider configuration tests passed.\n";
