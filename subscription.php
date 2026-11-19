<?php
declare(strict_types=1);
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#10251d">
  <title>اشتراک فروشگاه | سوپرمارکت ۳۶۰</title>
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="assets/css/subscription.css">
</head>
<body class="subscription-body">
<header class="topbar">
  <a class="brand" href="admin.php"><span class="brand-mark">۳۶۰</span><span>اشتراک<span class="brand-light"> فروشگاه</span></span></a>
  <a class="text-button" href="admin.php">بازگشت به پنل ←</a>
</header>
<main class="subscription-shell">
  <section class="subscription-heading">
    <p class="eyebrow">مدیریت پلن و امکانات</p>
    <h1>اشتراک فروشگاه شما</h1>
    <p>پلن فعلی، تاریخ دوره و درخواست‌های تغییر پلن را از این بخش مدیریت کنید.</p>
  </section>
  <p id="subscriptionMessage" class="subscription-message" role="status" aria-live="polite">در حال دریافت اطلاعات اشتراک...</p>
  <section id="subscriptionCurrent" class="subscription-current" hidden></section>
  <section class="subscription-plans-section">
    <div class="subscription-section-heading"><h2>پلن‌های قابل انتخاب</h2><span>تغییر پلن پس از بررسی و پرداخت فاکتور اعمال می‌شود.</span></div>
    <div id="subscriptionPlans" class="subscription-plan-grid"></div>
  </section>
  <form id="subscriptionRequestForm" class="subscription-request-form" hidden>
    <h2>ثبت درخواست تغییر پلن</h2>
    <div class="subscription-form-grid">
      <label>پلن انتخابی<select id="subscriptionPlanSelect" required></select></label>
      <label>دوره پرداخت<select id="subscriptionPeriodSelect"><option value="1">۱ ماه</option><option value="3">۳ ماه</option><option value="12">۱۲ ماه</option></select></label>
    </div>
    <p class="subscription-note">درخواست ابتدا توسط مدیریت پلتفرم بررسی می‌شود. پس از تأیید، فاکتور دوره بعد صادر خواهد شد و پلن جدید پس از تسویه کامل اعمال می‌شود. پلن فعلی تا آن زمان فعال می‌ماند.</p>
    <button class="primary-button" type="submit">ارسال درخواست برای بررسی</button>
  </form>
  <section class="subscription-history">
    <div class="subscription-section-heading"><h2>فاکتورها و سوابق پرداخت</h2><span>آخرین ۵۰ فاکتور</span></div>
    <div id="subscriptionInvoices" class="subscription-request-list"></div>
  </section>
  <section class="subscription-history">
    <div class="subscription-section-heading"><h2>درخواست‌های تغییر پلن</h2><span>آخرین ۲۰ درخواست</span></div>
    <div id="subscriptionRequests" class="subscription-request-list"></div>
  </section>
</main>
<script src="assets/js/subscription.js" defer></script>
</body>
</html>
