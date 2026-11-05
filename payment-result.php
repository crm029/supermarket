<?php
declare(strict_types=1);
$status = (string)($_GET['status'] ?? 'review');
$allowed = ['success', 'failed', 'review'];
if (!in_array($status, $allowed, true)) $status = 'review';
$orderId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT);
$messages = [
    'success' => ['پرداخت با موفقیت تأیید شد', 'سفارش شما پرداخت شده و برای آماده‌سازی به فروشگاه ارسال می‌شود.', '✓'],
    'failed' => ['پرداخت انجام نشد', 'تراکنش تأیید نشد. اگر مبلغی از حساب شما کسر شده، نتیجه را با پشتیبانی پیگیری کنید.', '×'],
    'review' => ['در حال بررسی پرداخت', 'نتیجه از درگاه دریافت شد اما ثبت نهایی نیازمند بررسی است. لطفاً شماره سفارش را نگه دارید.', '…'],
];
[$title, $description, $symbol] = $messages[$status];
?><!doctype html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#10251d"><title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> | سوپرمارکت ۳۶۰</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="payment-result-body">
<main class="payment-result-card">
  <div class="payment-result-symbol <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8'); ?></div>
  <p class="eyebrow">سوپرمارکت ۳۶۰</p>
  <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
  <p><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php if ($orderId !== false && $orderId > 0): ?><small>شناسه سفارش: <?php echo (int)$orderId; ?></small><?php endif; ?>
  <a class="primary-button" href="index.php">بازگشت به فروشگاه</a>
</main>
</body></html>
