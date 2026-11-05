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
    <button class="admin-nav" data-panel="purchasing">تأمین‌کنندگان و خرید</button>
    <button class="admin-nav" data-panel="settings">تنظیمات و ظاهر فروشگاه</button>
    <button class="admin-nav" data-panel="delivery-zones">محدوده‌های ارسال</button>
    <button class="admin-nav" data-panel="pickup-slots">زمان‌بندی تحویل حضوری</button>
    <button class="admin-nav" data-panel="coupons">کدهای تخفیف</button>
    <button class="admin-nav" data-panel="staff">کارکنان و سطح دسترسی</button>
    <button class="admin-nav" data-panel="media-library">کتابخانه رسانه</button>
    <button class="admin-nav" data-panel="scenes">طراحی فروشگاه مجازی</button>
    <div class="admin-sidebar-note">دسترسی‌ها بر اساس نقش و مجوز حساب شما از سمت سرور کنترل می‌شوند.</div>
  </aside>
  <section class="admin-content">
    <section class="admin-panel active" id="panel-overview">
      <p class="eyebrow">داشبورد فروشگاه</p><h2>خلاصه وضعیت</h2>
      <div class="admin-stats"><article><small>محصولات فعال</small><strong id="statProducts">—</strong></article><article><small>دسته‌بندی‌ها</small><strong id="statCategories">—</strong></article><article><small>وضعیت اتصال</small><strong id="statConnection">—</strong></article><article><small>فروش تأییدشده ۳۰ روز</small><strong id="statRevenue">—</strong></article><article><small>در انتظار پرداخت</small><strong id="statPending">—</strong></article><article><small>هشدار موجودی کم</small><strong id="statLowStock">—</strong></article></div><div class="admin-welcome"><h3>فروش روزانه ۷ روز اخیر</h3><div id="salesChart" class="sales-chart">در حال بارگذاری...</div></div>
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
          <label>بارکد<input id="productBarcode" maxlength="80" inputmode="numeric"></label>
          <label>نام محصول<input id="productTitle" maxlength="180" required></label>
          <label>نوع محصول<select id="productKind"><option value="simple">محصول معمولی</option><option value="variant_parent">محصول دارای تنوع</option></select></label>
          <label>قیمت (تومان)<input id="productPrice" type="number" min="0" step="1" required></label>
          <label>دسته‌بندی<select id="productCategory"><option value="">بدون دسته‌بندی</option></select></label>
          <label>برند<input id="productBrand" maxlength="120"></label>
          <label>شناسه تصویر محصول<input id="productMediaId" type="number" min="1"></label>
          <label>واحد فروش<input id="productUnit" maxlength="80" value="عدد"></label>
          <label>حد هشدار موجودی<input id="productLowStockThreshold" type="number" min="0" max="1000000" step="1" value="5" required></label>
        </div>
        <label>توضیحات کامل محصول<textarea id="productDescription" rows="3" maxlength="10000"></textarea></label>
        <div class="admin-form-grid">
          <label>عنوان سئو (اختیاری)<input id="productSeoTitle" maxlength="180" placeholder="اگر خالی باشد از نام محصول استفاده می‌شود"></label>
          <label>توضیحات متا (حداکثر ۳۲۰ نویسه)<textarea id="productSeoDescription" rows="2" maxlength="320" placeholder="متن کوتاه مخصوص نتیجه جست‌وجو"></textarea></label>
        </div>
        <div class="admin-form-actions"><button class="primary-button" type="submit">ذخیره محصول</button><button class="text-button" id="cancelProductForm" type="button">انصراف</button></div>
      </form>
      <section id="variantManager" class="admin-form variant-manager" hidden>
        <div class="admin-heading"><div><p class="eyebrow">مدیریت گزینه‌های فروش</p><h3 id="variantManagerTitle">تنوع‌های محصول</h3></div><button type="button" class="text-button" id="closeVariantManager">بستن</button></div>
        <form id="variantForm">
          <input type="hidden" id="variantId">
          <div class="admin-form-grid">
            <label>عنوان تنوع<input id="variantLabel" maxlength="120" required placeholder="مثلاً ۵۰۰ گرمی"></label>
            <label>کد کالا (SKU)<input id="variantSku" maxlength="80" required></label>
            <label>بارکد<input id="variantBarcode" maxlength="80" inputmode="numeric"></label>
            <label>قیمت (تومان)<input id="variantPrice" type="number" min="1" step="1" required></label>
            <label>قیمت قبل از تخفیف (تومان)<input id="variantCompareAt" type="number" min="1" step="1"></label>
            <label>واحد فروش<input id="variantUnit" maxlength="80" value="عدد" required></label>
            <label>حد هشدار موجودی<input id="variantLowStock" type="number" min="0" max="1000000" value="5" required></label>
            <label class="admin-check-label"><input id="variantWeighted" type="checkbox"> کالای وزنی</label>
            <label class="admin-check-label"><input id="variantActive" type="checkbox" checked> تنوع فعال باشد</label>
          </div>
          <div class="admin-form-actions"><button class="primary-button" type="submit">ذخیره تنوع</button><button class="text-button" id="resetVariantForm" type="button">تنوع جدید</button></div>
          <p id="variantMessage" class="form-message" role="status"></p>
        </form>
        <div id="variantRows" class="admin-row-list"><p>در حال بارگذاری...</p></div>
      </section>
    </section>
    <section class="admin-panel" id="panel-categories">
      <div class="admin-heading"><div><p class="eyebrow">ساختار فروشگاه</p><h2>دسته‌بندی‌ها</h2></div></div>
      <form id="categoryForm" class="admin-form admin-category-form">
        <h3 id="categoryFormTitle">افزودن دسته‌بندی</h3><input type="hidden" id="categoryId">
        <div class="admin-form-grid"><label>عنوان دسته<input id="categoryTitle" maxlength="120" required></label><label>شناسه انگلیسی (Slug)<input id="categorySlug" maxlength="140" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></label><label>شناسه تصویر دسته<input id="categoryMediaId" type="number" min="1"></label></div>
        <label>توضیحات دسته‌بندی<textarea id="categoryDescription" rows="2" maxlength="2000" placeholder="توضیح مفید درباره محصولات این دسته"></textarea></label>
        <div class="admin-form-grid">
          <label>عنوان سئو (اختیاری)<input id="categorySeoTitle" maxlength="180" placeholder="عنوان نمایش در گوگل"></label>
          <label>توضیحات متا<textarea id="categorySeoDescription" rows="2" maxlength="320" placeholder="خلاصه جذاب و دقیق دسته‌بندی"></textarea></label>
        </div>
        <div class="admin-form-actions"><button class="primary-button" id="categorySubmitButton" type="submit">افزودن دسته</button><button class="text-button" id="categoryCancelButton" type="button" hidden>انصراف از ویرایش</button></div>
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
        <h3>ثبت رسید موجودی</h3><div class="admin-form-grid"><label>انبار<select id="receiptWarehouse" required></select></label><label>محصول<select id="receiptProduct" required></select></label><label>تعداد ورودی<input id="receiptQuantity" type="number" min="1" max="1000000" required></label><label>شماره بچ<input id="receiptBatchNumber" maxlength="80" placeholder="اختیاری"></label><label>تاریخ انقضا<input id="receiptExpiryDate" type="date"></label><label>شرح رسید<input id="receiptReason" maxlength="255"></label></div><button class="primary-button" type="submit">افزایش موجودی</button>
      </form>
      <form id="adjustmentForm" class="admin-form">
        <h3>اصلاح موجودی</h3><div class="admin-form-grid"><label>انبار<select id="adjustWarehouse" required></select></label><label>محصول<select id="adjustProduct" required></select></label><label>تغییر تعداد (+ یا -)<input id="adjustQuantity" type="number" step="1" required></label><label>شماره بچ جدید (فقط افزایش)<input id="adjustBatchNumber" maxlength="80" placeholder="اختیاری"></label><label>تاریخ انقضای بچ جدید (فقط افزایش)<input id="adjustExpiryDate" type="date"></label><label>دلیل اصلاح<input id="adjustReason" maxlength="255" required></label></div><button class="primary-button" type="submit">ثبت اصلاح</button>
      </form>
      <form id="transferForm" class="admin-form">
        <h3>انتقال بین انبارها</h3><div class="admin-form-grid"><label>از انبار<select id="transferFrom" required></select></label><label>به انبار<select id="transferTo" required></select></label><label>محصول<select id="transferProduct" required></select></label><label>تعداد انتقال<input id="transferQuantity" type="number" min="1" max="1000000" required></label></div><button class="primary-button" type="submit">ثبت انتقال</button>
      </form>
      <h3>موجودی فعلی</h3><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>محصول</th><th>انبار</th><th>موجودی</th><th>رزروشده</th><th>قابل فروش</th><th>حد هشدار</th></tr></thead><tbody id="stockRows"><tr><td colspan="6">در حال بارگذاری...</td></tr></tbody></table></div>
      <div class="admin-heading"><div><h3>هشدارهای کمبود موجودی</h3><p class="admin-hint">این فهرست کالاهای زیر حد هشدار را حتی در انبارهایی که هنوز برایشان رکورد موجودی ندارند نمایش می‌دهد.</p></div></div>
      <div id="stockLowSummary" class="admin-welcome" aria-live="polite">در حال بررسی کمبود موجودی...</div>
      <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>کالا</th><th>انبار</th><th>موجودی قابل فروش</th><th>حد هشدار</th><th>کمبود تا حد هشدار</th><th>اقدام</th></tr></thead><tbody id="stockLowRows"><tr><td colspan="6">در حال بارگذاری...</td></tr></tbody></table></div>
      <div class="admin-heading"><div><h3>ردیابی بچ و تاریخ انقضا</h3><p class="admin-hint">بچ‌های نزدیک انقضا و موجودی‌های بدون تاریخ انقضا را از این بخش کنترل کنید.</p></div><button class="text-button" id="reloadStockBatches" type="button">به‌روزرسانی بچ‌ها</button></div>
      <div class="admin-toolbar">
        <select id="stockBatchState" aria-label="وضعیت تاریخ انقضا"><option value="all">همه بچ‌ها</option><option value="expired">منقضی‌شده</option><option value="expiring">در آستانه انقضا</option><option value="valid">دارای مهلت بیشتر</option><option value="unknown">بدون تاریخ انقضا</option></select>
        <select id="stockBatchDays" aria-label="بازه هشدار"><option value="7">۷ روز آینده</option><option value="15">۱۵ روز آینده</option><option value="30" selected>۳۰ روز آینده</option><option value="60">۶۰ روز آینده</option><option value="90">۹۰ روز آینده</option></select>
      </div>
      <div id="stockExpirySummary" class="admin-welcome" aria-live="polite">در حال دریافت گزارش انقضا...</div>
      <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>کالا</th><th>انبار</th><th>شماره بچ</th><th>تاریخ انقضا</th><th>موجودی</th><th>رزروشده</th><th>قابل استفاده</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody id="stockBatchRows"><tr><td colspan="9">در حال بارگذاری...</td></tr></tbody></table></div>
      <div class="admin-heading"><div><h3>گردش و سوابق موجودی</h3><p class="admin-hint">تمام ورودها، خروج‌ها، انتقال‌ها، فروش، مرجوعی و امحا با کاربر ثبت‌کننده و شرح عملیات قابل پیگیری است.</p></div><button class="text-button" id="reloadStockMovements" type="button">به‌روزرسانی گردش</button></div>
      <div class="admin-toolbar">
        <select id="movementWarehouseFilter" aria-label="فیلتر انبار"><option value="">همه انبارها</option></select>
        <select id="movementProductFilter" aria-label="فیلتر کالا"><option value="">همه کالاها</option></select>
        <select id="movementTypeFilter" aria-label="نوع گردش">
          <option value="">همه عملیات</option><option value="receipt">ورود موجودی</option><option value="transfer_in">ورود انتقال</option><option value="transfer_out">خروج انتقال</option><option value="adjustment">اصلاح موجودی</option><option value="reservation">رزرو سفارش</option><option value="release">آزادسازی رزرو</option><option value="sale">فروش</option><option value="return">مرجوعی مشتری</option><option value="supplier_return">مرجوعی تأمین‌کننده</option><option value="expiry_writeoff">امحای کالای منقضی</option>
        </select>
        <label>از تاریخ <input id="movementFromDate" type="date"></label><label>تا تاریخ <input id="movementToDate" type="date"></label>
        <button class="primary-button" id="applyMovementFilters" type="button">اعمال فیلتر</button>
      </div>
      <div id="stockMovementSummary" class="admin-welcome" aria-live="polite">در حال دریافت سوابق...</div>
      <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>زمان ثبت</th><th>کالا</th><th>انبار</th><th>نوع عملیات</th><th>تغییر تعداد</th><th>ثبت‌کننده</th><th>شرح / مرجع</th></tr></thead><tbody id="stockMovementRows"><tr><td colspan="7">در حال بارگذاری...</td></tr></tbody></table></div>
    </section>

    <section class="admin-panel" id="panel-purchasing">
      <div class="admin-heading"><div><p class="eyebrow">تدارکات و تأمین کالا</p><h2>تأمین‌کنندگان و سفارش خرید</h2></div><button class="text-button" id="reloadPurchasing" type="button">به‌روزرسانی</button></div>
      <p class="admin-hint">ثبت تأمین‌کننده، صدور سفارش خرید و دریافت مرحله‌ای کالا در انبار. موجودی فقط پس از ثبت رسید واقعی افزایش پیدا می‌کند.</p>
      <form id="supplierForm" class="admin-form">
        <h3>ثبت تأمین‌کننده</h3>
        <div class="admin-form-grid">
          <label>نام تأمین‌کننده<input id="supplierName" maxlength="180" required></label>
          <label>نام رابط<input id="supplierContact" maxlength="160"></label>
          <label>تلفن<input id="supplierPhone" maxlength="30" inputmode="tel"></label>
          <label>ایمیل<input id="supplierEmail" maxlength="190" type="email" dir="ltr"></label>
          <label>نشانی<input id="supplierAddress" maxlength="2000"></label>
          <label>یادداشت<input id="supplierNotes" maxlength="2000"></label>
        </div>
        <button class="primary-button" type="submit">ثبت تأمین‌کننده</button>
        <p id="supplierMessage" class="form-message" role="status"></p>
      </form>
      <div id="supplierRows" class="admin-row-list"><p>در حال بارگذاری...</p></div>
      <form id="purchaseOrderForm" class="admin-form">
        <h3>صدور سفارش خرید</h3>
        <div class="admin-form-grid">
          <label>تأمین‌کننده<select id="purchaseSupplier" required></select></label>
          <label>کالا<select id="purchaseProduct" required></select></label>
          <label>تعداد سفارش<input id="purchaseQuantity" type="number" min="1" max="1000000" value="1" required></label>
          <label>قیمت خرید هر واحد (تومان)<input id="purchaseUnitCost" type="number" min="0" step="1" value="0" required></label>
          <label>تاریخ مورد انتظار<input id="purchaseExpected" type="date"></label>
          <label>توضیحات<input id="purchaseNotes" maxlength="4000"></label>
        </div>
        <button class="primary-button" type="submit">ثبت سفارش خرید</button>
        <p id="purchaseOrderMessage" class="form-message" role="status"></p>
      </form>
      <div class="admin-heading"><div><h3>سفارش‌های خرید</h3></div></div>
      <div id="purchaseOrderRows" class="admin-row-list"><p>در حال بارگذاری...</p></div>
      <form id="purchaseReceiveForm" class="admin-form">
        <h3>ثبت دریافت کالا</h3>
        <div class="admin-form-grid">
          <label>سفارش خرید<select id="receivePurchaseOrder" required></select></label>
          <label>انبار مقصد<select id="receiveWarehouse" required></select></label>
          <label>قلم سفارش<select id="receivePurchaseItem" required></select></label>
          <label>تعداد دریافتی<input id="receiveQuantity" type="number" min="1" max="1000000" required></label>
          <label>شماره بچ تأمین‌کننده<input id="receiveBatchNumber" maxlength="80" placeholder="در صورت درج روی بسته‌بندی"></label>
          <label>تاریخ انقضا<input id="receiveExpiryDate" type="date"></label>
          <label>شرح رسید<input id="receiveNotes" maxlength="500"></label>
        </div>
        <button class="primary-button" type="submit">ثبت رسید و افزایش موجودی</button>
        <p id="purchaseReceiveMessage" class="form-message" role="status"></p>
      </form>
      <div class="admin-heading"><div><h3>مرجوعی به تأمین‌کننده</h3><p class="admin-hint">فقط از رسیدهای واقعی همان تأمین‌کننده و همان انبار قابل انتخاب است.</p></div></div>
      <form id="supplierReturnForm" class="admin-form">
        <div class="admin-form-grid">
          <label>تأمین‌کننده<select id="returnSupplier" required></select></label>
          <label>انبار مبدأ<select id="returnWarehouse" required></select></label>
          <label>قلم رسید قابل مرجوعی<select id="returnReceiptItem" required></select></label>
          <label>تعداد مرجوعی<input id="returnQuantity" type="number" min="1" max="1000000" required></label>
          <label>علت مرجوعی<input id="returnReason" maxlength="255" required placeholder="آسیب‌دیدگی، مغایرت یا..."></label>
          <label>توضیحات<input id="returnNotes" maxlength="1000"></label>
        </div>
        <button class="primary-button" type="submit">ثبت مرجوعی و کسر موجودی</button>
        <p id="supplierReturnMessage" class="form-message" role="status"></p>
      </form>
      <div class="admin-heading"><div><h3>سوابق مرجوعی</h3></div></div>
      <div id="supplierReturnRows" class="admin-row-list"><p>در حال بارگذاری...</p></div>
    </section>
    <section class="admin-panel" id="panel-staff">
      <div class="admin-heading"><div><p class="eyebrow">مدیریت تیم فروشگاه</p><h2>کارکنان و سطح دسترسی</h2></div><button class="text-button" id="reloadStaff" type="button">به‌روزرسانی</button></div>
      <div class="admin-welcome"><p>کارمند ابتدا باید با شماره موبایل خود ثبت‌نام و تأیید شود. سپس می‌توانید یک یا چند نقش محدود به او اختصاص دهید. نقش مدیر اصلی از این بخش قابل واگذاری یا حذف نیست.</p></div>
      <form id="staffRoleForm" class="admin-form">
        <h3>ساخت نقش جدید</h3>
        <div class="admin-form-grid"><label>شناسه انگلیسی نقش<input id="staffRoleKey" maxlength="60" pattern="[a-z][a-z0-9_]{2,59}" placeholder="cashier" required></label><label>عنوان نمایشی نقش<input id="staffRoleTitle" maxlength="100" placeholder="صندوقدار" required></label></div>
        <p class="admin-hint">مجوز مدیریت کارکنان فقط برای مدیر اصلی محفوظ است و به نقش‌های جدید داده نمی‌شود.</p>
        <div id="staffPermissionList" class="staff-permission-grid"></div>
        <button class="primary-button" type="submit">ساخت نقش</button>
      </form>
      <form id="staffAssignForm" class="admin-form">
        <h3>اختصاص نقش به کارمند</h3>
        <div class="admin-form-grid"><label>شماره موبایل کارمند<input id="staffMobile" inputmode="tel" maxlength="14" placeholder="09xxxxxxxxx" required></label><label>نقش<select id="staffRoleSelect" required><option value="">ابتدا نقش بسازید</option></select></label></div>
        <button class="primary-button" type="submit">اختصاص نقش</button>
      </form>
      <div class="admin-heading"><div><h3>اعضای تیم</h3></div></div>
      <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>کارمند</th><th>موبایل</th><th>وضعیت</th><th>نقش‌ها و عملیات</th></tr></thead><tbody id="staffRows"><tr><td colspan="4">در حال بارگذاری...</td></tr></tbody></table></div>
      <p id="staffMessage" class="form-message" role="status"></p>
    </section>
    <section class="admin-panel" id="panel-coupons">
      <div class="admin-heading"><div><p class="eyebrow">باشگاه مشتریان و فروش</p><h2>کدهای تخفیف</h2></div><button class="text-button" id="reloadCoupons" type="button">به‌روزرسانی</button></div>
      <p class="admin-hint">کدها می‌توانند درصدی یا مبلغ ثابت باشند. سقف استفاده کلی، سهمیه هر مشتری، حداقل مبلغ سبد و بازه زمانی قابل تنظیم است. تخفیف فقط پس از اعتبارسنجی سمت سرور روی سفارش اعمال می‌شود.</p>
      <form id="couponForm" class="admin-form">
        <h3 id="couponFormTitle">ایجاد کد تخفیف</h3>
        <input type="hidden" id="couponId">
        <div class="admin-form-grid">
          <label>کد تخفیف<input id="couponCode" maxlength="40" required placeholder="WELCOME15" dir="ltr" autocapitalize="characters"></label>
          <label>عنوان داخلی<input id="couponTitle" maxlength="120" required placeholder="تخفیف خوش‌آمدگویی"></label>
          <label>نوع تخفیف<select id="couponType"><option value="percentage">درصدی</option><option value="fixed">مبلغ ثابت</option></select></label>
          <label id="couponValueLabel">درصد تخفیف<input id="couponValue" type="number" min="1" max="100" step="1" required></label>
          <label>سقف تخفیف (تومان، اختیاری)<input id="couponMaxDiscount" type="number" min="0" step="1" placeholder="بدون سقف"></label>
          <label>حداقل مبلغ سبد (تومان)<input id="couponMinimum" type="number" min="0" step="1" value="0" required></label>
          <label>حداکثر استفاده کل (خالی = نامحدود)<input id="couponUsageLimit" type="number" min="1" step="1"></label>
          <label>سهمیه هر مشتری<input id="couponPerUserLimit" type="number" min="1" max="1000" value="1" required></label>
          <label>شروع اعتبار<input id="couponStart" type="datetime-local" required></label>
          <label>پایان اعتبار<input id="couponEnd" type="datetime-local" required></label>
          <label class="admin-check-label"><input id="couponActive" type="checkbox" checked> کد فعال باشد</label>
        </div>
        <div class="admin-form-actions"><button class="primary-button" type="submit">ذخیره کد تخفیف</button><button class="text-button" id="cancelCouponEdit" type="button">پاک‌کردن فرم</button></div>
        <p id="couponMessage" class="form-message" role="status"></p>
      </form>
      <div id="couponRows" class="admin-row-list"><p>در حال بارگذاری...</p></div>
    </section>
    <section class="admin-panel" id="panel-pickup-slots">
      <div class="admin-heading"><div><p class="eyebrow">ظرفیت و زمان دریافت</p><h2>زمان‌بندی تحویل حضوری</h2></div><button class="text-button" id="reloadPickupSlots" type="button">به‌روزرسانی</button></div>
      <p class="admin-hint">زمان‌ها بر اساس منطقه زمانی فروشگاه ثبت می‌شوند. ظرفیت هر بازه هنگام ثبت سفارش به‌صورت تراکنشی کنترل می‌شود. اگر هیچ بازه‌ای تعریف نشود، تحویل حضوری بدون زمان‌بندی فعال می‌ماند.</p>
      <form id="pickupSlotForm" class="admin-form">
        <h3>ایجاد بازه دریافت</h3>
        <div class="admin-form-grid">
          <label>عنوان بازه<input id="pickupSlotTitle" maxlength="120" required placeholder="تحویل عصر"></label>
          <label>شروع<input id="pickupSlotStart" type="datetime-local" required></label>
          <label>پایان<input id="pickupSlotEnd" type="datetime-local" required></label>
          <label>ظرفیت سفارش<input id="pickupSlotCapacity" type="number" min="1" max="1000" value="10" required></label>
        </div>
        <button class="primary-button" type="submit">ثبت بازه دریافت</button>
        <p id="pickupSlotAdminMessage" class="form-message" role="status"></p>
      </form>
      <div id="pickupSlotRows" class="admin-row-list"><p>در حال بارگذاری...</p></div>
    </section>
    <section class="admin-panel" id="panel-delivery-zones">
      <div class="admin-heading"><div><p class="eyebrow">پوشش خدمات پیک</p><h2>محدوده‌های ارسال</h2></div><button class="text-button" id="reloadDeliveryZones" type="button">به‌روزرسانی</button></div>
      <p class="admin-hint">وقتی حداقل یک محدوده فعال داشته باشید، سفارش ارسالی فقط برای نشانی‌های منطبق با استان، شهر و پیش‌شماره کد پستی پذیرفته می‌شود. پیش‌شماره خالی یعنی تمام کدهای پستی آن شهر.</p>
      <form id="deliveryZoneForm" class="admin-form">
        <h3 id="deliveryZoneFormTitle">افزودن محدوده جدید</h3>
        <input type="hidden" id="deliveryZoneId">
        <div class="admin-form-grid">
          <label>عنوان محدوده<input id="deliveryZoneTitle" maxlength="120" required placeholder="مرکز شهر"></label>
          <label>استان<input id="deliveryZoneProvince" maxlength="100" required></label>
          <label>شهر<input id="deliveryZoneCity" maxlength="100" required></label>
          <label>پیش‌شماره کد پستی (اختیاری)<input id="deliveryZonePostalPrefix" maxlength="10" inputmode="numeric" placeholder="مثلاً ۱۲۳"></label>
          <label>هزینه ارسال (تومان)<input id="deliveryZoneFee" type="number" min="0" max="1000000000" required></label>
          <label>حداقل مبلغ سفارش (تومان)<input id="deliveryZoneMinimum" type="number" min="0" max="1000000000" required></label>
          <label>ترتیب نمایش<input id="deliveryZoneSort" type="number" min="0" max="100000" value="0" required></label>
          <label class="admin-check-label"><input id="deliveryZoneActive" type="checkbox" checked> محدوده فعال باشد</label>
        </div>
        <div class="admin-form-actions"><button class="primary-button" type="submit">ذخیره محدوده</button><button class="text-button" id="cancelDeliveryZoneEdit" type="button">پاک‌کردن فرم</button></div>
        <p id="deliveryZoneMessage" class="form-message" role="status"></p>
      </form>
      <div id="deliveryZoneRows" class="admin-row-list"><p>در حال بارگذاری...</p></div>
    </section>
    <section class="admin-panel" id="panel-settings">
      <div class="admin-heading"><div><p class="eyebrow">هویت و تجربه مشتری</p><h2>تنظیمات فروشگاه</h2></div><button class="text-button" id="reloadSettings" type="button">بارگذاری مجدد</button></div>
      <form id="storeSettingsForm" class="admin-form">
        <h3>اطلاعات و هویت بصری</h3>
        <div class="admin-form-grid">
          <label>نام فروشگاه<input id="settingStoreName" maxlength="120" required></label>
          <label>شعار کوتاه<input id="settingTagline" maxlength="180"></label>
          <label>تلفن پشتیبانی<input id="settingSupportPhone" maxlength="24" inputmode="tel"></label>
          <label>رنگ اصلی فروشگاه<input id="settingPrimaryColor" type="color" value="#173D2D"></label>
          <label>رنگ تأکیدی<input id="settingAccentColor" type="color" value="#E6A23C"></label>
        </div>
        <h3>روش‌های دریافت سفارش</h3>
        <div class="admin-form-grid">
          <label class="admin-check-label"><input id="settingDeliveryEnabled" type="checkbox"> ارسال با پیک فعال باشد</label>
          <label class="admin-check-label"><input id="settingPickupEnabled" type="checkbox"> تحویل حضوری فعال باشد</label>
          <label>هزینه ارسال (تومان)<input id="settingDeliveryFee" type="number" min="0" max="100000000" step="1" required></label>
          <label>حداقل مبلغ سفارش (تومان)<input id="settingMinimumOrder" type="number" min="0" max="1000000000" step="1" required></label>
          <label>مدت رزرو کالا برای پرداخت (دقیقه)<input id="settingReservationMinutes" type="number" min="5" max="120" step="1" required></label>
        </div>
        <p class="admin-hint">هزینه ارسال، حداقل خرید و زمان رزرو در سمت سرور کنترل می‌شوند؛ تغییرات فقط پس از ذخیره اعمال خواهند شد.</p>
        <div class="admin-form-actions"><button class="primary-button" type="submit">ذخیره تنظیمات فروشگاه</button></div>
        <p id="storeSettingsMessage" class="form-message" role="status"></p>
      </form>
    </section>
    <section class="admin-panel" id="panel-media-library">
      <div class="admin-heading"><div><p class="eyebrow">فایل‌های تصویری فروشگاه</p><h2>کتابخانه رسانه</h2></div><button class="text-button" id="reloadMediaLibrary" type="button">به‌روزرسانی</button></div>
      <p class="admin-hint">شناسه هر تصویر برای اتصال به محصول، دسته‌بندی یا صحنه قابل استفاده است. رسانه‌های در حال استفاده مشخص شده‌اند؛ پاک‌سازی امن فایل‌ها در مرحله بعد اضافه می‌شود.</p>
      <div id="mediaLibraryRows" class="media-library-grid"><p>در حال بارگذاری...</p></div>
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
        <div class="admin-form-grid"><label>عنوان راهرو<input id="sceneTitle" maxlength="140" required></label><label>نوع صحنه<select id="sceneType"><option value="aisle_photo">تصویر راهرو</option><option value="panorama">پانورامای ۳۶۰</option></select></label><label>شناسه تصویر اصلی<input id="sceneMediaId" type="number" min="1" required></label><label>شناسه تصویر جایگزین (اختیاری)<input id="sceneFallbackId" type="number" min="1"></label><label>ترتیب نمایش<input id="sceneSort" type="number" min="0" value="0"></label><label class="admin-check-label"><input id="scenePublished" type="checkbox"> انتشار در فروشگاه</label></div>
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
