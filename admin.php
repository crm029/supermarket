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
        <div class="admin-form-grid"><label>عنوان دسته<input id="categoryTitle" maxlength="120" required></label><label>شناسه انگلیسی (Slug)<input id="categorySlug" maxlength="140" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></label></div>
        <button class="primary-button" type="submit">افزودن دسته</button>
      </form>
      <div id="categoryRows" class="admin-category-list"></div>
    </section>
    <section class="admin-panel" id="panel-orders">
      <div class="admin-heading"><div><p class="eyebrow">آماده‌سازی و ارسال</p><h2>مدیریت سفارش‌ها</h2></div><button class="text-button" id="reloadOrders">به‌روزرسانی</button></div>
      <div class="admin-toolbar"><select id="orderStatusFilter"><option value="">همه وضعیت‌ها</option><option value="paid">پرداخت‌شده</option><option value="accepted">پذیرفته‌شده</option><option value="picking">در حال جمع‌آوری</option><option value="packed">بسته‌بندی‌شده</option><option value="dispatched">ارسال‌شده</option><option value="ready_pickup">آماده تحویل حضوری</option><option value="delivered">تحویل‌شده</option></select></div>
      <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>شماره سفارش</th><th>مشتری</th><th>اقلام</th><th>مبلغ (تومان)</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody id="orderRows"><tr><td colspan="6">در حال بارگذاری...</td></tr></tbody></table></div>
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
