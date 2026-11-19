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

    <section id="platformBillingSection" class="platform-billing-panel" hidden>
      <div class="platform-section-heading"><h2>اشتراک‌ها و امور مالی SaaS</h2><span>مدیریت سراسری</span></div>
      <button id="platformExportBilling" class="secondary-button" type="button">خروجی CSV فاکتورها</button>
      <div class="platform-billing-totals">
        <article><span>تعداد فاکتورها</span><strong id="platformInvoiceCount">۰</strong></article>
        <article><span>دریافت‌شده</span><strong id="platformCollected">۰</strong><small>تومان</small></article>
        <article><span>مانده فاکتورها</span><strong id="platformOutstanding">۰</strong><small>تومان</small></article>
      </div>
      <section class="platform-reconciliation">
        <div class="platform-section-heading"><h3>تطبیق مالی فاکتورها و دریافت‌ها</h3><span>کنترل مغایرت دفتر مالی</span></div>
        <div id="platformReconciliationTotals" class="platform-billing-totals"></div>
        <div id="platformReconciliationList" class="platform-tenant-list"><p>در حال بررسی دفتر مالی...</p></div>
      </section>
      <section class="platform-usage-section">
        <div class="platform-section-heading"><h3>مصرف منابع فروشگاه‌ها</h3><span id="platformUsagePeriod">ماه جاری UTC</span></div>
        <div id="platformUsageTotals" class="platform-billing-totals"></div>
        <div id="platformUsageList" class="platform-usage-list"><p>در حال محاسبه مصرف...</p></div>
      </section>
      <section class="platform-change-requests">
        <div class="platform-section-heading"><h3>درخواست‌های تغییر پلن فروشگاه‌ها</h3><span>تأیید، صدور فاکتور یا رد درخواست</span></div>
        <div id="platformChangeRequestList" class="platform-tenant-list"><p>در حال دریافت درخواست‌ها...</p></div>
      </section>
      <form id="platformPlanForm" class="platform-create">
        <h3>تعریف پلن جدید</h3>
        <div class="platform-form-grid">
          <label>کد انگلیسی پلن<input id="platformPlanCode" required maxlength="40" pattern="[a-z0-9][a-z0-9_-]{1,39}" placeholder="basic"></label>
          <label>نام پلن<input id="platformPlanName" required maxlength="120" placeholder="پایه"></label>
          <label>قیمت ماهانه (تومان)<input id="platformPlanPrice" type="number" min="1" max="100000000000" step="1" required></label>
          <label>توضیحات<input id="platformPlanDescription" maxlength="500" placeholder="مناسب فروشگاه‌های کوچک"></label>
          <label>محدودیت‌های پلن (JSON)<textarea id="platformPlanLimits" rows="3" dir="ltr">{"products":100,"staff":5,"media_assets":100}</textarea></label>
        </div>
        <button class="primary-button" type="submit">ثبت پلن</button>
        <p id="platformPlanMessage" class="platform-message" role="status"></p>
      </form>
      <div class="platform-billing-columns">
        <section><h3>پلن‌های فروش</h3><div id="platformPlanList" class="platform-tenant-list"></div></section>
        <section><h3>اشتراک فروشگاه‌ها</h3><div id="platformBillingTenantList" class="platform-tenant-list"></div></section>
      </div>
      <section class="platform-list-section"><div class="platform-section-heading"><h3>فاکتورها و ثبت دریافت</h3><span>۲۰۰ فاکتور اخیر</span></div><div id="platformInvoiceList" class="platform-tenant-list"></div></section>
      <p id="platformBillingMessage" class="platform-message" role="status" aria-live="polite"></p>
    </section>

    <section class="platform-list-section">
      <div class="platform-section-heading"><h2>فروشگاه‌های ثبت‌شده</h2><span id="platformTenantCount"></span></div>
      <div id="platformTenantList" class="platform-tenant-list"><p>در حال بارگذاری...</p></div>
    </section>
    <section class="platform-list-section">
      <div class="platform-section-heading"><h2>بررسی فایل‌های رسانه‌ای یتیم</h2><span>فقط مدیر پلتفرم مجاز</span></div>
      <p>ابتدا گزارش read-only فایل یتیم را بررسی کنید. ثبت درخواست یا رد آن هیچ فایلی را حذف نمی‌کند؛ تأیید نهایی فقط فایل را وارد صف می‌کند و worker پس از بررسی مجدد مسیر آن را پاک می‌کند.</p>
      <form id="platformOrphanReviewForm" class="platform-form-grid">
        <label>شناسه فروشگاه<input id="orphanTenantId" type="number" min="1" step="1" required></label>
        <label>کلید کامل فایل<input id="orphanStorageKey" maxlength="500" required dir="ltr" placeholder="tenant-1/product/40-character-hash.jpg"></label>
        <label>دلیل بررسی<input id="orphanReviewReason" maxlength="500" required minlength="5"></label>
        <button class="secondary-button" type="submit">ثبت درخواست بررسی</button>
      </form>
      <p id="platformOrphanReviewMessage" class="platform-message" role="status" aria-live="polite"></p>
      <div id="platformOrphanReviewList" class="platform-tenant-list"><p>در حال دریافت درخواست‌ها...</p></div>
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
