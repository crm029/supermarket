<?php
declare(strict_types=1);
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#10251d">
  <title>مدیریت پلتفرم | سوپرمارکت ۳۶۰</title>
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="assets/css/platform.css">
</head>
<body class="platform-body">
<header class="topbar platform-topbar">
  <a class="brand" href="index.php"><span class="brand-mark">۳۶۰</span><span>مدیریت<span class="brand-light"> پلتفرم</span></span></a>
  <a class="text-button" href="admin.php">پنل فروشگاه ←</a>
</header>
<main class="platform-shell">
  <section id="platformLogin" class="platform-login">
    <p class="eyebrow">مدیریت سراسری</p>
    <h1>ورود مدیر پلتفرم</h1>
    <p>این بخش فقط برای حساب‌هایی است که مجوز سراسری مدیریت فروشگاه‌ها به آن‌ها داده شده است.</p>
    <label for="platformMobile">شماره موبایل</label>
    <input id="platformMobile" type="tel" inputmode="tel" autocomplete="tel" placeholder="0912xxxxxxx" maxlength="14">
    <button id="platformRequestOtp" class="primary-button" type="button">ارسال کد ورود</button>
    <div id="platformOtpArea" hidden>
      <label for="platformOtpCode">کد پیامک‌شده</label>
      <input id="platformOtpCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="کد ۶ رقمی">
      <button id="platformVerifyOtp" class="primary-button" type="button">ورود امن</button>
    </div>
    <p id="platformLoginMessage" class="platform-message" role="status" aria-live="polite"></p>
  </section>

  <section id="platformDashboard" hidden>
    <div class="platform-heading">
      <div><p class="eyebrow">کنترل چندفروشگاهی</p><h1>مدیریت فروشگاه‌ها</h1><p id="platformIdentity"></p></div>
      <div class="platform-actions"><button id="platformReload" class="secondary-button" type="button">به‌روزرسانی</button><button id="platformLogout" class="text-button" type="button">خروج</button></div>
    </div>

    <section class="platform-create">
      <h2>ساخت فروشگاه جدید</h2>
      <p>فروشگاه ابتدا در حالت انتظار ساخته می‌شود. برای فعال‌سازی، یک رکورد DNS از نوع TXT با مقدار یک‌بارمصرف باید روی دامنه ثبت شود؛ سامانه رکورد را بررسی می‌کند و پس از تطبیق، فروشگاه را فعال می‌کند.</p>
      <form id="platformCreateForm">
        <div class="platform-form-grid">
          <label>نام فروشگاه<input id="newTenantName" maxlength="160" required placeholder="مثلاً سوپرمارکت بهار"></label>
          <label>شناسه یکتا<input id="newTenantSlug" maxlength="80" required pattern="[a-z0-9][a-z0-9-]{0,78}[a-z0-9]" placeholder="bahar-market"></label>
          <label>دامنه اختصاصی<input id="newTenantHost" maxlength="253" required placeholder="shop.example.com" dir="ltr"></label>
          <label>نام مدیر فروشگاه<input id="newOwnerName" maxlength="160" required placeholder="نام و نام خانوادگی"></label>
          <label>موبایل مدیر<input id="newOwnerMobile" maxlength="14" required inputmode="tel" placeholder="0912xxxxxxx"></label>
          <label>منطقه زمانی<select id="newTenantTimezone"><option value="Asia/Tehran">تهران</option><option value="UTC">UTC</option><option value="Europe/Berlin">برلین</option></select></label>
        </div>
        <button class="primary-button" type="submit">ساخت فروشگاه در حالت انتظار</button>
      </form>
      <section id="platformDnsInstructions" class="platform-dns-instructions" hidden>
        <h3>رکورد DNS برای تأیید دامنه</h3>
        <p>این رکورد را در پنل DNS دامنه بسازید. مقدار توکن محرمانه است؛ فقط در TXT ثبتش کنید و در گزارش‌ها یا پیام‌های عمومی منتشر نکنید.</p>
        <dl>
          <dt>نوع رکورد</dt><dd>TXT</dd>
          <dt>نام / Host</dt><dd id="platformDnsRecordName" dir="ltr"></dd>
          <dt>مقدار / Value</dt><dd id="platformDnsRecordValue" dir="ltr"></dd>
          <dt>انقضا</dt><dd id="platformDnsExpires"></dd>
        </dl>
        <button id="platformCopyDnsValue" class="secondary-button" type="button">کپی مقدار TXT</button>
      </section>
    </section>

    <section id="platformPaymentSection" class="platform-payment-panel" hidden>
      <div class="platform-section-heading"><h2>تنظیمات درگاه پرداخت سراسری</h2><span class="platform-owner-only">فقط مالک پلتفرم</span></div>
      <p>شناسه مرچنت به‌صورت رمزنگاری‌شده در پایگاه داده نگهداری می‌شود و هیچ‌وقت در پاسخ API یا پنل به‌صورت کامل نمایش داده نمی‌شود. این تنظیم برای درگاه تمام فروشگاه‌های متصل به پلتفرم اعمال می‌شود.</p>
      <form id="platformPaymentForm">
        <div class="platform-form-grid">
          <label>شناسه مرچنت زرین‌پال<input id="platformMerchantId" type="password" maxlength="64" autocomplete="new-password" dir="ltr" placeholder="برای حفظ شناسه فعلی خالی بگذارید"></label>
          <label>حالت درگاه<select id="platformPaymentMode"><option value="sandbox">Sandbox — آزمایشی</option><option value="live">Real — واقعی</option></select></label>
        </div>
        <p id="platformPaymentStatus" class="platform-payment-status">در حال دریافت وضعیت درگاه...</p>
        <p class="platform-payment-warning">حالت Real فقط روی سرور production با دامنه HTTPS اجازه شروع پرداخت می‌گیرد. قبل از تغییر به Real، ترمینال و دامنه بازگشت زرین‌پال را بررسی کنید.</p>
        <button class="primary-button" type="submit">ذخیره تنظیمات درگاه</button>
      </form>
      <p id="platformPaymentMessage" class="platform-message" role="status" aria-live="polite"></p>
    </section>

    <section class="platform-list-section">
      <div class="platform-section-heading"><h2>فروشگاه‌های ثبت‌شده</h2><span id="platformTenantCount"></span></div>
      <div id="platformTenantList" class="platform-tenant-list"><p>در حال بارگذاری...</p></div>
    </section>
    <section class="platform-list-section">
      <div class="platform-section-heading"><h2>گزارش رویدادهای پلتفرم</h2><span>آخرین ۲۰۰ رویداد</span></div>
      <div id="platformAuditList" class="platform-audit-list"><p>در حال بارگذاری...</p></div>
    </section>
    <p id="platformMessage" class="platform-message" role="status" aria-live="polite"></p>
  </section>
</main>
<script src="assets/js/platform.js" defer></script>
</body>
</html>
