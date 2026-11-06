<?php
declare(strict_types=1);
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#10251d"><title>صندوق فروشگاهی | سوپرمارکت ۳۶۰</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{font-family:Vazirmatn,Tahoma,sans-serif;color:#17231d;background:#f3f6f2;font-synthesis:none}
*{box-sizing:border-box}body{margin:0}.pos-top{background:#10251d;color:#fff;padding:16px max(20px,calc((100% - 1240px)/2));display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}.pos-brand{font-weight:800;font-size:20px}.pos-top a{color:#e5c98a;text-decoration:none}.pos-wrap{max-width:1240px;margin:28px auto;padding:0 18px}.pos-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:20px}.pos-heading h1{margin:0;font-size:25px}.pos-heading p{margin:5px 0 0;color:#68766e}.pos-card{background:#fff;border:1px solid #e0e8e1;border-radius:18px;padding:20px;box-shadow:0 8px 30px #1836260a;margin-bottom:18px}.pos-card h2{font-size:18px;margin:0 0 16px}.pos-grid{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(320px,.7fr);gap:18px}.pos-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.pos-field{display:grid;gap:6px}.pos-field label{font-size:13px;color:#5b6c61}.pos-field input,.pos-field select{width:100%;border:1px solid #d5dfd7;border-radius:10px;padding:11px 12px;font:inherit;background:#fff;min-width:0}.pos-btn{border:0;border-radius:10px;padding:11px 16px;background:#17633f;color:#fff;font:inherit;font-weight:700;cursor:pointer}.pos-btn:disabled{opacity:.5;cursor:wait}.pos-btn.secondary{background:#e9f0eb;color:#244632}.pos-btn.danger{background:#9c3d34}.pos-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.pos-stat{padding:12px;background:#f4f7f4;border-radius:12px}.pos-stat small{display:block;color:#64746a}.pos-stat strong{display:block;margin-top:5px;font-size:17px}.pos-search{display:flex;gap:8px}.pos-search input{flex:1;min-width:0;border:1px solid #d5dfd7;border-radius:10px;padding:12px;font:inherit}.pos-results{display:grid;gap:8px;margin-top:12px}.pos-result{display:flex;justify-content:space-between;align-items:center;gap:12px;text-align:right;padding:12px;border:1px solid #e1e8e2;background:#fff;border-radius:12px;font:inherit;cursor:pointer}.pos-result:hover{border-color:#6b9a7c;background:#f8fbf8}.pos-result small,.pos-cart-row small{display:block;color:#6c7b71;font-size:12px;margin-top:3px}.pos-cart-row{display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid #edf1ed;padding:12px 0}.pos-qty{display:flex;align-items:center;gap:8px}.pos-qty button{border:0;border-radius:8px;background:#edf3ee;color:#244632;width:30px;height:30px;font-size:18px;cursor:pointer}.pos-qty button[data-remove]{background:#f9e9e7;color:#a23f36}.pos-total{display:flex;justify-content:space-between;align-items:center;font-weight:800;font-size:19px;padding:16px 0}.pos-empty{color:#87938a;text-align:center;padding:20px}.pos-message{padding:12px 14px;border-radius:10px;background:#edf2ee;color:#425449;margin:12px 0}.pos-message:empty{display:none}.pos-message.error{background:#fbecea;color:#983c33}.pos-message.success{background:#e8f5eb;color:#22623a}.pos-message.warning{background:#fff4dd;color:#8a5b0b}.pos-hint{color:#728078;font-size:12px;line-height:1.8}.pos-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.pos-hidden{display:none!important}
@media(max-width:800px){.pos-grid{grid-template-columns:1fr}.pos-heading{align-items:flex-start;flex-direction:column}.pos-stats{grid-template-columns:1fr}.pos-wrap{margin:18px auto}.pos-card{padding:16px}}@media(max-width:480px){.pos-form-grid{grid-template-columns:1fr}.pos-result{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<header class="pos-top"><div class="pos-brand">۳۶۰ | صندوق فروشگاهی</div><div><span id="posIdentity">در حال بررسی حساب...</span>　<a href="admin.php">بازگشت به مدیریت</a></div></header>
<main class="pos-wrap">
<div class="pos-heading"><div><h1>صندوق فروش حضوری</h1><p>فروش نقدی، محاسبه باقی‌پول و ثبت خودکار خروج کالا از انبار</p></div><a href="admin.php" class="pos-btn secondary">پنل مدیریت</a></div>
<div id="posMessage" class="pos-message" role="status"></div>
<a id="posLoginLink" href="admin.php" hidden>ورود به پنل مدیریت و دریافت دسترسی</a>
<section id="posOpenShift" class="pos-card">
<h2>شروع شیفت صندوق</h2>
<form id="posOpenForm">
<div class="pos-form-grid">
<div class="pos-field"><label for="posWarehouse">انبار فروش حضوری</label><select id="posWarehouse" required></select></div>
<div class="pos-field"><label for="posOpeningFloat">موجودی اولیه صندوق (تومان)</label><input id="posOpeningFloat" type="number" min="0" step="1" value="0" required></div>
<div class="pos-field"><label for="posOpeningNote">توضیح شروع شیفت</label><input id="posOpeningNote" maxlength="255" placeholder="اختیاری"></div>
</div><div class="pos-actions"><button class="pos-btn" type="submit">باز کردن شیفت</button></div>
</form><p class="pos-hint">برای استفاده، مدیر فروشگاه باید مجوزهای صندوق را از بخش «کارکنان و سطح دسترسی» به نقش صندوق‌دار بدهد.</p>
</section>
<section id="posActiveShift" class="pos-card pos-hidden">
<div class="pos-heading"><div><h2>شیفت فعال</h2><p>انبار: <strong id="posShiftWarehouse">—</strong></p></div></div>
<div class="pos-stats">
<div class="pos-stat"><small>موجودی اولیه</small><strong id="posShiftOpening">—</strong></div>
<div class="pos-stat"><small>فروش مورد انتظار صندوق</small><strong id="posShiftExpected">—</strong></div>
<div class="pos-stat"><small>تعداد فاکتور</small><strong id="posShiftSales">—</strong></div>
</div>
</section>
<section id="posSaleArea" class="pos-grid pos-hidden">
<div class="pos-card">
<h2>افزودن کالا به فاکتور</h2>
<div class="pos-search"><input id="posSearch" type="search" placeholder="اسکن بارکد یا جست‌وجوی نام / کد کالا" autocomplete="off"><button id="posSearchButton" class="pos-btn" type="button">جست‌وجو</button></div>
<div id="posSearchResults" class="pos-results"><p class="pos-empty">بارکد کالا را اسکن یا نام آن را جست‌وجو کنید.</p></div>
</div>
<div class="pos-card">
<h2>فاکتور فروش</h2>
<div id="posCartRows"><p class="pos-empty">هنوز کالایی به فاکتور اضافه نشده است.</p></div>
<div class="pos-total"><span>مبلغ قابل پرداخت</span><span id="posTotal">۰ تومان</span></div>
<form id="posSaleForm">
<div class="pos-field"><label for="posCashReceived">وجه دریافتی (تومان)</label><input id="posCashReceived" type="number" min="0" step="1" required></div>
<div class="pos-total"><span>باقی‌پول</span><span id="posChange">—</span></div>
<div class="pos-field"><label for="posSaleNote">توضیح فاکتور</label><input id="posSaleNote" maxlength="255" placeholder="اختیاری"></div>
<div class="pos-actions"><button id="posCompleteSale" class="pos-btn" type="submit">ثبت فروش نقدی</button></div>
</form>
</div>
</section>
<section id="posCloseShift" class="pos-card pos-hidden">
<h2>پایان شیفت و مغایرت‌گیری</h2>
<form id="posCloseForm"><div class="pos-form-grid">
<div class="pos-field"><label for="posCountedCash">وجه نقد شمارش‌شده (تومان)</label><input id="posCountedCash" type="number" min="0" step="1" required></div>
<div class="pos-field"><label for="posClosingNote">توضیح بستن شیفت</label><input id="posClosingNote" maxlength="255" placeholder="اختیاری"></div>
</div><div class="pos-actions"><button class="pos-btn danger" type="submit">بستن شیفت و ثبت مغایرت</button></div></form>
<p class="pos-hint">مبلغ مورد انتظار بر اساس موجودی اولیه به‌علاوه فروش‌های نقدی منهای باقی‌پول محاسبه می‌شود.</p>
</section>
</main>
<script src="assets/js/pos.js" defer></script>
</body></html>
