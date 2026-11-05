<?php
declare(strict_types=1);
require_once __DIR__ . '/app/seo.php';
$seoContext = app_seo_context();
$seoProfile = $seoContext ? app_seo_store_profile($seoContext) : [];
$trackingMarkup = app_seo_tracking_markup($seoProfile);
$status = (string)($_GET['status'] ?? 'review');
$allowed = ['success', 'failed', 'review'];
if (!in_array($status, $allowed, true)) $status = 'review';
$orderId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT);
$purchaseAnalyticsJson = null;
if ($status === 'success' && $seoContext && $orderId !== false && $orderId !== null && $orderId > 0) {
    $orderQuery = $seoContext['db']->prepare(
        "SELECT id,order_number,subtotal_rial,discount_rial,delivery_fee_rial
         FROM orders
         WHERE tenant_id=? AND id=? AND status IN ('paid','accepted','picking','packed','ready_pickup','dispatched','delivered')
         LIMIT 1"
    );
    $orderQuery->execute([(int)$seoContext['tenant']['id'],$orderId]);
    $paidOrder = $orderQuery->fetch(PDO::FETCH_ASSOC);
    if ($paidOrder) {
        $itemsQuery = $seoContext['db']->prepare(
            'SELECT product_id,product_title_snapshot,unit_price_rial,quantity
             FROM order_items WHERE tenant_id=? AND order_id=? ORDER BY id'
        );
        $itemsQuery->execute([(int)$seoContext['tenant']['id'],$orderId]);
        $items = [];
        foreach ($itemsQuery->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $items[] = [
                'item_id'=>(string)$item['product_id'],
                'item_name'=>(string)$item['product_title_snapshot'],
                'price'=>(int)$item['unit_price_rial'],
                'quantity'=>(int)$item['quantity'],
            ];
        }
        $purchaseAnalyticsJson = json_encode([
            'transaction_id'=>(string)$paidOrder['order_number'],
            'currency'=>'IRR',
            'value'=>max(0,(int)$paidOrder['subtotal_rial']-(int)$paidOrder['discount_rial']),
            'shipping'=>(int)$paidOrder['delivery_fee_rial'],
            'items'=>$items,
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    }
}
$messages = [
    'success' => ['پرداخت با موفقیت تأیید شد', 'سفارش شما پرداخت شده و برای آماده‌سازی به فروشگاه ارسال می‌شود.', '✓'],
    'failed' => ['پرداخت انجام نشد', 'تراکنش تأیید نشد. اگر مبلغی از حساب شما کسر شده، نتیجه را با پشتیبانی پیگیری کنید.', '×'],
    'review' => ['در حال بررسی پرداخت', 'نتیجه از درگاه دریافت شد اما ثبت نهایی نیازمند بررسی است. لطفاً شماره سفارش را نگه دارید.', '…'],
];
[$title, $description, $symbol] = $messages[$status];
?><!doctype html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#10251d"><title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> | سوپرمارکت ۳۶۰</title><?php echo $trackingMarkup; ?><link rel="stylesheet" href="assets/css/app.css"><?php if ($purchaseAnalyticsJson !== null): ?><script>document.addEventListener('sm360:analytics-ready',function(){window.sm360Track('purchase',<?php echo $purchaseAnalyticsJson; ?>);},{once:true});</script><?php endif; ?></head>
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
