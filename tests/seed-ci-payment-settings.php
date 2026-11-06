<?php
declare(strict_types=1);

// CI-only fixture: create an encrypted live-mode configuration for the read-only preflight test.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/app/payments.php';
$db = app_db();
$merchant = trim((string)(app_env('ZARINPAL_MERCHANT_ID') ?? ''));
if ($merchant === '') throw new RuntimeException('CI merchant placeholder is missing.');
$encrypted = app_payment_merchant_encrypt($merchant);
$save = $db->prepare(
    "UPDATE platform_payment_settings
     SET merchant_id_encrypted = ?, mode = 'live', updated_at = UTC_TIMESTAMP()
     WHERE id = 1"
);
$save->execute([$encrypted]);
if ($save->rowCount() !== 1) throw new RuntimeException('CI payment settings fixture was not saved.');
echo "Encrypted CI payment settings fixture prepared.\n";
