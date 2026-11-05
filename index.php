<?php
declare(strict_types=1);
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#10251d">
  <meta name="description" content="سوپرمارکت ۳۶۰؛ تجربه خرید در یک فروشگاه مجازی تعاملی.">
  <title>سوپرمارکت ۳۶۰ | خرید متفاوت</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="#" aria-label="سوپرمارکت ۳۶۰"><span class="brand-mark">۳۶۰</span><span>مارکت<span class="brand-light">‌پلاس</span></span></a>
  <label class="search"><span>⌕</span><input id="searchInput" type="search" placeholder="دنبال چی می‌گردی؟ مثلاً شیر، برنج، چیپس..." aria-label="جست‌وجوی محصولات"></label>
  <nav class="top-actions" aria-label="دسترسی سریع">
    <button class="icon-button" id="accountButton" type="button">ورود / عضویت</button>
    <button class="cart-button" id="cartButton" type="button">سبد خرید <b id="cartCount">۰</b></button>
  </nav>
</header>
<main>
  <section class="welcome">
    <div>
      <p class="eyebrow">تجربه‌ای فراتر از خرید آنلاین</p>
      <h1>انگار واقعاً<br><em>توی فروشگاهی.</em></h1>
      <p class="welcome-copy">بین راهروها قدم بزن، قفسه‌ها رو بگرد و هرچی لازم داری به سبدت اضافه کن.</p>
      <div class="welcome-actions">
        <button class="primary-button" id="enterStore" type="button">ورود به فروشگاه <span>←</span></button>
        <button class="text-button" id="browseCategories" type="button">دیدن دسته‌بندی‌ها</button>
      </div>
      <div class="trust-row"><span>✓ قیمت شفاف</span><span>✓ ارسال با پیک فروشگاه</span><span>✓ تحویل حضوری</span></div>
    </div>
    <div class="hero-art" role="img" aria-label="تصویر نمونه از راهروی یک سوپرمارکت">
      <div class="hero-chip"><span class="live-dot"></span> فروشگاه مجازی ۳۶۰</div>
      <div class="aisle-label">راهروی شماره ۰۱ <small>خوراکی‌های روزمره</small></div>
    </div>
  </section>

  <section class="store-layout" id="store">
    <aside class="category-panel">
      <div class="panel-heading"><div><small>همه‌چیز دم دسته</small><h2>دسته‌بندی‌ها</h2></div><span>۰۸</span></div>
      <div class="category-list" id="categoryList">
        <button class="category active" data-category="all"><span>✳</span> همه محصولات <b>›</b></button>
        <button class="category" data-category="dairy"><span>🥛</span> لبنیات و صبحانه <b>›</b></button>
        <button class="category" data-category="grocery"><span>🍚</span> خواربار و مواد غذایی <b>›</b></button>
        <button class="category" data-category="snacks"><span>🍪</span> تنقلات و خوراکی <b>›</b></button>
        <button class="category" data-category="drinks"><span>🧃</span> نوشیدنی‌ها <b>›</b></button>
        <button class="category" data-category="cleaning"><span>🧴</span> شوینده و بهداشتی <b>›</b></button>
        <button class="category" data-category="frozen"><span>🧊</span> مواد غذایی منجمد <b>›</b></button>
        <button class="category" data-category="fruit"><span>🍎</span> میوه و سبزیجات <b>›</b></button>
      </div>
      <div class="movement-box"><small>روش گشت‌وگذار</small><div class="movement-options"><button class="mode active" data-mode="guided">راهنمای دسته‌ها</button><button class="mode" data-mode="tour">تور تصویری</button><button class="mode" data-mode="free">حرکت آزاد</button></div><p id="modeHint">با انتخاب هر دسته، به قفسه مربوط هدایت می‌شی.</p></div>
    </aside>

    <section class="aisle-view" aria-label="محیط فروشگاه">
      <div class="aisle-top"><div><small class="eyebrow">خوش اومدی به فروشگاه</small><h2 id="aisleTitle">همه محصولات</h2></div><span class="aisle-number">AISLE <b>01</b></span></div>
      <div class="aisle-scene" id="aisleScene">
        <div class="scene-overlay"><span>تصویر محیط نمونه</span><span>برای نمایش عکس واقعی فروشگاه، بعداً تصاویر اختصاصی جایگزین می‌شوند.</span></div>
        <button class="scene-arrow prev" id="prevAisle" aria-label="راهروی قبلی">‹</button>
        <button class="scene-arrow next" id="nextAisle" aria-label="راهروی بعدی">›</button>
        <div class="scene-floor"></div>
      </div>
      <div class="scene-controls"><span>● فروشگاه نمونه</span><span>برای حرکت بین راهروها از فلش‌ها استفاده کن</span><div><button id="zoomOut" aria-label="کوچک‌نمایی">−</button><button id="zoomIn" aria-label="بزرگ‌نمایی">+</button></div></div>
      <div class="shelf-heading"><div><small>منتخب این قفسه</small><h3>محصولات این راهرو</h3></div><span id="productCount">۰ محصول</span></div>
      <div class="product-grid" id="productGrid"></div>
    </section>
  </section>

  <section class="info-strip">
    <article><span>01</span><h3>خرید راحت</h3><p>محصولات رو پیدا کن و بدون دردسر سفارش بده.</p></article>
    <article><span>02</span><h3>ارسال یا دریافت</h3><p>با پیک فروشگاه بگیر یا حضوری تحویل بگیر.</p></article>
    <article><span>03</span><h3>حساب و کیف پول</h3><p>سفارش‌ها، اعتبار و کد معرفت یک‌جا.</p></article>
  </section>
</main>
<footer><div class="brand"><span class="brand-mark">۳۶۰</span><span>مارکت‌پلاس</span></div><div class="footer-links"><a href="#about">درباره ما</a><a href="#contact">تماس با ما</a><a href="#policies">قوانین و مقررات</a></div><small>© نسخه نمایشی پلتفرم سوپرمارکت ۳۶۰</small></footer>

<dialog id="productDialog" class="product-dialog"><button class="dialog-close" data-close="productDialog" aria-label="بستن">×</button><div id="productDetail"></div></dialog>
<dialog id="cartDialog" class="cart-dialog"><button class="dialog-close" data-close="cartDialog" aria-label="بستن">×</button><small class="eyebrow">انتخاب‌های خوشمزه‌ات</small><h2>سبد خرید</h2><div id="cartItems"></div><div class="cart-total"><span>جمع کل</span><strong id="cartTotal">۰ تومان</strong></div><button class="primary-button full" id="checkoutButton" type="button">ادامه و ثبت سفارش</button><p class="demo-note">این نسخه نمایشی است؛ پرداخت و ثبت سفارش در مراحل بعدی فعال می‌شود.</p></dialog>
<dialog id="accountDialog" class="product-dialog">
  <button class="dialog-close" data-close="accountDialog" aria-label="بستن">×</button>
  <p class="eyebrow">حساب کاربری سوپرمارکت ۳۶۰</p>
  <h2>ورود یا ساخت حساب</h2>
  <div class="account-form">
    <label>نام و نام خانوادگی <input id="accountName" autocomplete="name" maxlength="160" placeholder="برای ثبت‌نام"></label>
    <label>شماره موبایل <input id="accountMobile" inputmode="tel" autocomplete="tel" maxlength="14" placeholder="09xxxxxxxxx" required></label>
    <label>کد معرف (اختیاری) <input id="accountReferral" maxlength="16" placeholder="کد معرف"></label>
    <div class="account-actions">
      <button class="primary-button" id="registerButton" type="button">ساخت حساب</button>
      <button class="text-button" id="requestOtpButton" type="button">دریافت کد ورود</button>
    </div>
    <label>کد تأیید پیامک <input id="accountOtp" inputmode="numeric" maxlength="6" placeholder="کد ۶ رقمی"></label>
    <button class="primary-button full" id="verifyOtpButton" type="button">تأیید و ورود</button>
    <p id="accountMessage" class="form-message" role="status"></p>
  </div>
</dialog>

<dialog id="checkoutDialog" class="product-dialog">
  <button class="dialog-close" data-close="checkoutDialog" aria-label="بستن">×</button>
  <p class="eyebrow">مرحله نهایی خرید</p>
  <h2>روش دریافت سفارش</h2>
  <div class="account-form">
    <label>روش دریافت
      <select id="fulfillmentSelect"><option value="delivery">ارسال رایگان با پیک فروشگاه</option><option value="pickup">تحویل حضوری</option></select>
    </label>
    <div id="deliveryAddressFields">
      <label>عنوان نشانی <input id="addressTitle" value="خانه" maxlength="80"></label>
      <label>تحویل‌گیرنده <input id="addressRecipient" autocomplete="name" maxlength="160"></label>
      <label>موبایل تحویل‌گیرنده <input id="addressMobile" inputmode="tel" autocomplete="tel"></label>
      <label>استان <input id="addressProvince" maxlength="100"></label>
      <label>شهر <input id="addressCity" maxlength="100"></label>
      <label>نشانی کامل <textarea id="addressText" rows="3" maxlength="2000"></textarea></label>
      <label>کد پستی <input id="addressPostal" inputmode="numeric" maxlength="10"></label>
    </div>
    <label>توضیحات سفارش (اختیاری) <textarea id="orderNote" rows="2" maxlength="1000"></textarea></label>
    <button class="primary-button full" id="placeOrderButton" type="button">ثبت سفارش</button>
    <p id="checkoutMessage" class="form-message" role="status"></p>
  </div>
</dialog>
<script src="assets/js/app.js" defer></script>
</body>
</html>
