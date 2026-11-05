<?php
declare(strict_types=1);
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#10251d">
  <title>مرکز وظایف کارکنان | سوپرمارکت ۳۶۰</title>
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="assets/css/staff.css">
</head>
<body class="staff-body">
  <header class="topbar staff-topbar">
    <a class="brand" href="index.php"><span class="brand-mark">۳۶۰</span><span>سوپرمارکت<span class="brand-light"> ۳۶۰</span></span></a>
    <a class="text-button" href="index.php">بازگشت به فروشگاه ←</a>
  </header>
  <main class="staff-shell">
    <section id="staffLogin" class="staff-login">
      <p class="eyebrow">ویژه کارکنان فروشگاه</p>
      <h1>ورود به مرکز وظایف</h1>
      <p>برای مشاهده سفارش‌هایی که به شما واگذار شده، با شماره موبایل ثبت‌شده در فروشگاه وارد شوید.</p>
      <label for="staffMobile">شماره موبایل</label>
      <input id="staffMobile" type="tel" inputmode="tel" autocomplete="tel" placeholder="0912xxxxxxx" maxlength="14">
      <button id="staffRequestOtp" class="primary-button" type="button">ارسال کد ورود</button>
      <div id="staffOtpArea" hidden>
        <label for="staffOtpCode">کد پیامک‌شده</label>
        <input id="staffOtpCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="کد تأیید">
        <button id="staffVerifyOtp" class="primary-button" type="button">ورود امن</button>
      </div>
      <p id="staffLoginMessage" class="staff-message" role="status" aria-live="polite"></p>
    </section>

    <section id="staffWorkspace" hidden>
      <div class="staff-heading">
        <div><p class="eyebrow">مرکز عملیات</p><h1>وظایف من</h1><p id="staffIdentity"></p></div>
        <div class="staff-actions"><button id="staffReload" class="secondary-button" type="button">به‌روزرسانی</button><button id="staffLogout" class="text-button" type="button">خروج</button></div>
      </div>
      <div class="staff-summary"><strong id="staffTaskCount">۰</strong><span>وظیفه فعال</span></div>
      <div id="staffTaskList" class="staff-task-list"><p>در حال بارگذاری وظایف...</p></div>
      <p id="staffWorkspaceMessage" class="staff-message" role="status" aria-live="polite"></p>
    </section>
  </main>
  <script src="assets/js/staff.js" defer></script>
</body>
</html>
