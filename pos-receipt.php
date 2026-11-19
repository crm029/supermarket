<?php
declare(strict_types=1);
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#10251d">
<title>رسید فروش حضوری | سوپرمارکت ۳۶۰</title>
<link rel="stylesheet" href="assets/css/pos-receipt.css">
</head>
<body>
<header class="receipt-toolbar">
  <a href="pos.php">بازگشت به صندوق</a>
  <label>عرض کاغذ
    <select id="receiptWidth"><option value="80">۸۰ میلی‌متر</option><option value="58">۵۸ میلی‌متر</option></select>
  </label>
  <button id="receiptPrint" type="button" disabled>چاپ رسید</button>
</header>
<main class="receipt-page">
  <p id="receiptMessage" role="status" aria-live="polite">در حال دریافت اطلاعات فروش...</p>
  <article id="receiptPaper" class="receipt-paper receipt-80" hidden></article>
</main>
<script src="assets/js/pos-receipt.js" defer></script>
</body>
</html>
