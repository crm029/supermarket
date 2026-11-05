<?php
declare(strict_types=1);
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#10251d">
  <title>مدیریت فروشگاه | سوپرمارکت ۳۶۰</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="admin-body">
<header class="topbar admin-topbar">
  <a class="brand" href="index.php"><span class="brand-mark">۳۶۰</span><span>مدیریت<span class="brand-light"> فروشگاه</span></span></a>
  <div class="admin-top-actions"><span id="adminIdentity">در حال بررسی دسترسی...</span><a class="text-button" href="index.php">مشاهده فروشگاه ←</a></div>
</header>
<main class="admin-shell">
  <aside class="admin-sidebar">
    <p class="eyebrow">پنل فروشنده</p><h1>مرکز کنترل</h1>
    <button class="admin-nav active" data-panel="overview">نمای کلی</button>
    <button class="admin-nav" data-panel="products">مدیریت محصولات</button>
    <button class="admin-nav" data-panel="categories">دسته‌بندی‌ها</button>
    <button class="admin-nav" data-panel="orders">مدیریت سفارش‌ها</button>
    <button class="admin-nav" data-panel="inventory">انبار و موجودی</button>
    <button class="admin-nav" data-panel="scenes">طراحی فروشگاه مجازی</button>
    <div class="admin-sidebar-note">دسترسی‌ها بر اساس نقش و مجوز حساب شما از سمت سرور کنترل می‌شوند.</div>
  </aside>
  <section class="admin-content">
    <section class="admin-panel active" id="panel-overview">
      <p class="eyebrow">داشبورد فروشگاه</p><h2>خلاصه وضعیت</h2>
      <div class="admin-stats"><article><small>محصولات فعال</small><strong id="statProducts">—</strong></article><article><small>دسته‌بندی‌ها</small><strong id="statCategories">—</strong></article><article><small>وضعیت اتصال</small><strong id="statConnection">—</strong></article></div>
      <div class="admin-welcome"><h3>به مرکز مدیریت خوش آمدید</h3><p>از اینجا می‌توانید کاتالوگ فروشگاه را مدیریت کنید. قیمت‌ها در API بر اساس ریال ذخیره می‌شوند و در پنل با تومان وارد می‌شوند.</p></div>
    </section>
    <section class="admin-panel" id="panel-products">
      <div class="admin-heading"><div><p class="eyebrow">کاتالوگ فروشگاه</p><h2>مدیریت محصولات</h2></div><button class="primary-button" id="newProductButton">+ افزودن محصول</button></div>
      <div class="admin-toolbar"><input id="productSearch" type="search" placeholder="جست‌وجوی نام یا کد کالا"><button class="text-button" id="reloadProducts">به‌روزرسانی</button></div>
      <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>کالا</th><th>کد کالا</th><th>قیمت (تومان)</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody id="productRows"><tr><td colspan="5">در حال بارگذاری...</td></tr></tbody></table></div>
      <form id="productForm" class="admin-form" hidden>
        <h3 id="productFormTitle">افزودن محصول</h3><input type="hidden" id="productId">
        <div class="admin-form-grid">
          <label>کد کالا (SKU)<input id="productSku" maxlength="80" required></label>
          <label>نام محصول<input id="productTitle" maxlength="180" required></label>
          <label>قیمت (تومان)<input id="productPrice" type="number" min="0" step="1" required></label>
          <label>دسته‌بندی<select id="productCategory"><option value="">بدون دسته‌بندی</option></select></label>
          <label>برند<input id="productBrand" maxlength="120"></label>
          <label>شناسه تصویر محصول<input id="productMediaId" type="number" min="1"></label>
          <label>واحد فروش<input id="productUnit" maxlength="80" value="عدد"></label>
        </div>
        <label>توضیحات<textarea id="productDescription" rows="3" maxlength="10000"></textarea></label>
        <div class="admin-form-actions"><button class="primary-button" type="submit">ذخیره محصول</button><button class="text-button" id="cancelProductForm" type="button">انصراف</button></div>
      </form>
    </section>
    <section class="admin-panel" id="panel-categories">
      <div class="admin-heading"><div><p class="eyebrow">ساختار فروشگاه</p><h2>دسته‌بندی‌ها</h2></div></div>
      <form id="categoryForm" class="admin-form admin-category-form">
        <h3>افزودن دسته‌بندی</h3>
        <div class="admin-form-grid"><label>عنوان دسته<input id="categoryTitle" maxlength="120" required></label><label>شناسه انگلیسی (Slug)<input id="categorySlug" maxlength="140" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></label><label>شناسه تصویر دسته<input id="categoryMediaId" type="number" min="1"></label></div>
        <button class="primary-button" type="submit">افزودن دسته</button>
      </form>
      <div id="categoryRows" class="admin-category-list"></div>
    </section>
    <section class="admin-panel" id="panel-orders">
      <div class="admin-heading"><div><p class="eyebrow">آماده‌سازی و ارسال</p><h2>مدیریت سفارش‌ها</h2></div><button class="text-button" id="reloadOrders">به‌روزرسانی</button></div>
      <div class="admin-toolbar"><select id="orderStatusFilter"><option value="">همه وضعیت‌ها</option><option value="paid">پرداخت‌شده</option><option value="accepted">پذیرفته‌شده</option><option value="picking">در حال جمع‌آوری</option><option value="packed">بسته‌بندی‌شده</option><option value="dispatched">ارسال‌شده</option><option value="ready_pickup">آماده تحویل حضوری</option><option value="delivered">تحویل‌شده</option></select></div>
      <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>شماره سفارش</th><th>مشتری</th><th>اقلام</th><th>مبلغ (تومان)</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody id="orderRows"><tr><td colspan="6">در حال بارگذاری...</td></tr></tbody></table></div><div id="adminOrderDetail" class="admin-welcome" hidden></div>
    </section>
    <section class="admin-panel" id="panel-inventory">
      <div class="admin-heading"><div><p class="eyebrow">کنترل موجودی</p><h2>انبار و کالاها</h2></div><button class="text-button" id="reloadInventory">به‌روزرسانی</button></div>
      <form id="warehouseForm" class="admin-form">
        <h3>افزودن انبار</h3><div class="admin-form-grid"><label>نام انبار<input id="warehouseTitle" maxlength="120" required></label><label>نوع انبار<select id="warehouseType"><option value="main">اصلی</option><option value="store">فروشگاه</option><option value="cold">سردخانه</option><option value="other">سایر</option></select></label></div><button class="primary-button" type="submit">ثبت انبار</button>
      </form>
      <form id="receiptForm" class="admin-form">
        <h3>ثبت رسید موجودی</h3><div class="admin-form-grid"><label>انبار<select id="receiptWarehouse" required></select></label><label>محصول<select id="receiptProduct" required></select></label><label>تعداد ورودی<input id="receiptQuantity" type="number" min="1" max="1000000" required></label><label>شرح رسید<input id="receiptReason" maxlength="255"></label></div><button class="primary-button" type="submit">افزایش موجودی</button>
      </form>
      <form id="adjustmentForm" class="admin-form">
        <h3>اصلاح موجودی</h3><div class="admin-form-grid"><label>انبار<select id="adjustWarehouse" required></select></label><label>محصول<select id="adjustProduct" required></select></label><label>تغییر تعداد (+ یا -)<input id="adjustQuantity" type="number" step="1" required></label><label>دلیل اصلاح<input id="adjustReason" maxlength="255" required></label></div><button class="primary-button" type="submit">ثبت اصلاح</button>
      </form>
      <form id="transferForm" class="admin-form">
        <h3>انتقال بین انبارها</h3><div class="admin-form-grid"><label>از انبار<select id="transferFrom" required></select></label><label>به انبار<select id="transferTo" required></select></label><label>محصول<select id="transferProduct" required></select></label><label>تعداد انتقال<input id="transferQuantity" type="number" min="1" max="1000000" required></label></div><button class="primary-button" type="submit">ثبت انتقال</button>
      </form>
      <h3>موجودی فعلی</h3><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>محصول</th><th>انبار</th><th>موجودی</th><th>رزروشده</th><th>قابل فروش</th></tr></thead><tbody id="stockRows"><tr><td colspan="5">در حال بارگذاری...</td></tr></tbody></table></div>
    </section>
    <section class="admin-panel" id="panel-scenes">
      <div class="admin-heading"><div><p class="eyebrow">تجربه خرید تعاملی</p><h2>طراحی فروشگاه مجازی</h2></div><button class="text-button" id="reloadScenes">به‌روزرسانی</button></div>
      <form id="mediaUploadForm" class="admin-form">
        <h3>بارگذاری تصویر راهرو یا قفسه</h3>
        <div class="admin-form-grid"><label>فایل تصویر (JPG، PNG، WebP)<input id="mediaFile" type="file" accept="image/jpeg,image/png,image/webp" required></label><label>نوع تصویر<select id="mediaType"><option value="aisle">راهرو</option><option value="panorama">پانوراما ۳۶۰</option><option value="product">محصول</option><option value="category">دسته‌بندی</option><option value="hero">بنر اصلی</option></select></label></div>
        <label>توضیح تصویر<input id="mediaAlt" maxlength="255" placeholder="مثلاً راهروی لبنیات"></label><button class="primary-button" type="submit">بارگذاری تصویر</button><p id="mediaUploadMessage" class="form-message"></p>
      </form>
      <form id="sceneForm" class="admin-form">
        <h3>ساخت صحنه جدید</h3>
        <div class="admin-form-grid"><label>عنوان راهرو<input id="sceneTitle" maxlength="140" required></label><label>نوع صحنه<select id="sceneType"><option value="aisle_photo">تصویر راهرو</option><option value="panorama">پانورامای ۳۶۰</option><option value="webgl_scene">صحنه سه‌بعدی</option></select></label><label>شناسه تصویر اصلی<input id="sceneMediaId" type="number" min="1" required></label><label>شناسه تصویر جایگزین (اختیاری)<input id="sceneFallbackId" type="number" min="1"></label><label>ترتیب نمایش<input id="sceneSort" type="number" min="0" value="0"></label><label class="admin-check-label"><input id="scenePublished" type="checkbox"> انتشار در فروشگاه</label></div>
        <button class="primary-button" type="submit">ساخت صحنه</button>
      </form>
      <div id="sceneRows" class="admin-category-list"></div>
      <div id="scenePreview" class="scene-preview"><p>برای تعیین محل نقاط تعاملی، یک صحنه انتخاب کنید.</p></div>
      <form id="hotspotForm" class="admin-form">
        <h3>افزودن نقطه تعاملی به صحنه</h3>
        <div class="admin-form-grid"><label>صحنه<select id="hotspotScene" required></select></label><label>نوع عملکرد<select id="hotspotAction"><option value="product">بازکردن محصول</option><option value="category">رفتن به دسته‌بندی</option><option value="navigate">رفتن به راهرو</option></select></label><label>عنوان نقطه<input id="hotspotLabel" maxlength="120" required></label><label>موقعیت افقی X (۰ تا ۱)<input id="hotspotX" type="number" min="0" max="1" step="0.01" value="0.5" required></label><label>موقعیت عمودی Y (۰ تا ۱)<input id="hotspotY" type="number" min="0" max="1" step="0.01" value="0.5" required></label><label id="hotspotProductField">محصول<select id="hotspotProduct"></select></label><label id="hotspotCategoryField" hidden>دسته‌بندی<select id="hotspotCategory"></select></label><label id="hotspotTargetField" hidden>صحنه مقصد<select id="hotspotTarget"></select></label></div>
        <button class="primary-button" type="submit">افزودن نقطه</button>
      </form>
      <div id="hotspotRows" class="admin-category-list"></div>
    </section>
    <p id="adminMessage" class="form-message" role="status"></p>
  </section>
</main>
<dialog id="adminLoginDialog" class="product-dialog">
  <p class="eyebrow">احراز هویت مدیر</p><h2>ورود به پنل مدیریت</h2>
  <div class="account-form"><label>شماره موبایل<input id="adminMobile" inputmode="tel" autocomplete="tel" placeholder="09xxxxxxxxx"></label><button class="primary-button" id="adminOtpRequest" type="button">دریافت کد ورود</button><label>کد تأیید<input id="adminOtpCode" inputmode="numeric" maxlength="6"></label><button class="primary-button full" id="adminOtpVerify" type="button">تأیید و ورود</button><p id="adminLoginMessage" class="form-message" role="status"></p></div>
</dialog>
<script src="assets/js/admin.js" defer></script>
</body>
</html>
