<?php
declare(strict_types=1);
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#10251d">
  <title>اعلان‌های من | سوپرمارکت ۳۶۰</title>
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="assets/css/notifications.css">
</head>
<body class="notifications-body">
<header class="topbar notifications-topbar">
  <a class="brand" href="index.php"><span class="brand-mark">۳۶۰</span><span>سوپرمارکت<span class="brand-light"> ۳۶۰</span></span></a>
  <div class="notifications-links"><a class="text-button" href="staff.php">مرکز وظایف</a><a class="text-button" href="index.php">فروشگاه ←</a></div>
</header>
<main class="notifications-shell">
  <section id="notificationsLogin" class="notifications-login">
    <p class="eyebrow">پیام‌های حساب کاربری</p>
    <h1>اعلان‌های من</h1>
    <p>برای مشاهده پیام‌های سفارش و فعالیت‌های فروشگاه، با شماره موبایل ثبت‌شده وارد شوید.</p>
    <label for="notificationsMobile">شماره موبایل</label>
    <input id="notificationsMobile" type="tel" inputmode="tel" autocomplete="tel" maxlength="14" placeholder="0912xxxxxxx">
    <button id="notificationsRequestOtp" class="primary-button" type="button">ارسال کد ورود</button>
    <div id="notificationsOtpArea" hidden>
      <label for="notificationsOtpCode">کد پیامک‌شده</label>
      <input id="notificationsOtpCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="کد ۶ رقمی">
      <button id="notificationsVerifyOtp" class="primary-button" type="button">ورود امن</button>
    </div>
    <p id="notificationsLoginMessage" class="notifications-message" role="status" aria-live="polite"></p>
  </section>

  <section id="notificationsInbox" hidden>
    <div class="notifications-heading">
      <div><p class="eyebrow">مرکز پیام‌ها</p><h1>اعلان‌های من</h1><p id="notificationsIdentity"></p></div>
      <div class="notifications-actions"><button id="notificationsReload" class="secondary-button" type="button">به‌روزرسانی</button><button id="notificationsReadAll" class="text-button" type="button">خواندن همه</button><button id="notificationsLogout" class="text-button" type="button">خروج</button></div>
    </div>
    <div class="notifications-summary"><strong id="notificationsUnreadCount">۰</strong><span>اعلان خوانده‌نشده</span></div>
    <div id="notificationsList" class="notifications-list"><p>در حال بارگذاری...</p></div>
    <p id="notificationsMessage" class="notifications-message" role="status" aria-live="polite"></p>
  </section>
</main>
<script src="assets/js/notifications.js" defer></script>
</body>
</html>
