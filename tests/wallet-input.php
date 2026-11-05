<?php
declare(strict_types=1);

putenv('REFERRAL_REWARD_BASIS_POINTS=0');
require_once dirname(__DIR__) . '/app/wallet.php';
if (app_wallet_reward_basis_points() !== 0) {
    throw new RuntimeException('Referral rewards must be disabled by default.');
}
putenv('REFERRAL_REWARD_BASIS_POINTS=500');
if (app_wallet_reward_basis_points() !== 500) {
    throw new RuntimeException('Referral reward basis-point configuration failed.');
}
if (app_wallet_reward_amount(100000, 500) !== 5000 ||
    app_wallet_reward_amount(9999, 500) !== 499 ||
    app_wallet_reward_amount(100000, 10000) !== 100000 ||
    app_wallet_reward_amount(PHP_INT_MAX, 10000) !== PHP_INT_MAX) {
    throw new RuntimeException('Referral reward arithmetic is incorrect or overflows.');
}
putenv('REFERRAL_REWARD_BASIS_POINTS=10001');
$rejected = false;
try {
    app_wallet_reward_basis_points();
} catch (RuntimeException $e) {
    $rejected = true;
}
if (!$rejected) throw new RuntimeException('Out-of-range referral reward rate was accepted.');

echo "Wallet and referral input tests passed.\n";
