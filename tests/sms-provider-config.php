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
if(app_sms_secret_decrypt($encrypted,'username')!==null) {
    throw new RuntimeException('SMS credential field separation failed.');
}
if(!app_sms_0098_response_success('0') || !app_sms_0098_response_success(" 0\n") ||
   app_sms_0098_response_success('1') || app_sms_0098_response_success('12') ||
   app_sms_0098_response_success('success')) {
    throw new RuntimeException('0098 provider response-code validation failed.');
}
echo "SMS provider configuration tests passed.\n";
