<?php
declare(strict_types=1);
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#10251d"><meta name="robots" content="noindex,nofollow">
<title>بازرسی کالای مرجوعی | سوپرمارکت ۳۶۰</title>
<link rel="stylesheet" href="assets/css/app.css">
<style>
body{font-family:Vazirmatn,Tahoma,sans-serif;background:#f5f7f4;color:#1d3327}.inspect-top{background:#10251d;color:white;padding:16px max(18px,calc((100% - 1120px)/2));display:flex;justify-content:space-between;gap:12px}.inspect-top a{color:#e5c98a;text-decoration:none}.inspect-shell{max-width:1120px;margin:28px auto;padding:0 18px}.inspect-grid{display:grid;grid-template-columns:minmax(280px,.9fr) minmax(0,1.1fr);gap:18px}.inspect-card{background:white;border:1px solid #e1e8e2;border-radius:16px;padding:18px}.inspect-list{display:grid;gap:10px}.inspect-item{width:100%;text-align:right;border:1px solid #dce5dd;border-radius:12px;background:#fff;padding:13px;font:inherit;cursor:pointer}.inspect-item[aria-pressed=true]{border-color:#17633f;background:#eff6f0}.muted{color:#708076;font-size:13px;line-height:1.9}.inspect-form{display:grid;gap:12px;margin-top:18px}.inspect-form label{display:grid;gap:5px;font-size:13px}.inspect-form input,.inspect-form textarea,.inspect-form select{border:1px solid #ccd8ce;border-radius:9px;padding:10px;font:inherit;width:100%;box-sizing:border-box}.inspect-form textarea{min-height:85px}.inspect-actions{display:flex;gap:9px;flex-wrap:wrap}.inspect-actions button{border:0;border-radius:9px;padding:11px 16px;color:white;font:inherit;font-weight:700;cursor:pointer}.restock{background:#17633f}.reject{background:#9a4138}.message{padding:12px;border-radius:10px;background:#e9f2ea;margin:14px 0}.message:empty{display:none}.message.error{background:#fbecea;color:#963b32}.empty{padding:24px;text-align:center;color:#829087}
@media(max-width:740px){.inspect-grid{grid-template-columns:1fr}.inspect-shell{margin:18px auto}}
</style>
</head>
<body>
<header class="inspect-top"><strong>۳۶۰ | بازرسی کالای مرجوعی</strong><a href="admin.php">بازگشت به مدیریت</a></header>
<main class="inspect-shell">
<h1>تعیین تکلیف کالای برگشتی</h1>
<p class="muted">فقط کالای سالم، پلمب و دارای تاریخ انقضای معتبر را به موجودی قابل‌فروش برگردانید. کالای ردشده هرگز به موجودی فروش اضافه نمی‌شود.</p>
<div id="inspectMessage" class="message" role="status"></div>
<div class="inspect-grid">
<section class="inspect-card"><h2>در انتظار بازرسی</h2><div id="inspectList" class="inspect-list"><p class="empty">در حال دریافت...</p></div></section>
<section class="inspect-card"><h2>جزئیات بازرسی</h2><div id="inspectDetail" class="muted">یک قلم مرجوعی را انتخاب کنید.</div>
<form id="inspectForm" class="inspect-form" hidden>
<label>نتیجه بازرسی<select id="inspectDecision"><option value="restock">سالم و پلمب؛ بازگشت به موجودی</option><option value="reject">رد کالا؛ عدم بازگشت به موجودی</option></select></label>
<div id="inspectBatchFields">
<label>شماره بچ جدید<input id="inspectBatch" maxlength="80" required placeholder="مثلاً RETURN-2026-001"></label>
<label>تاریخ انقضا<input id="inspectExpiry" type="date" required></label>
</div>
<label>شرح بازرسی و دلیل تصمیم<textarea id="inspectNote" minlength="12" maxlength="500" required placeholder="وضعیت بسته‌بندی، سلامت کالا و نتیجه بررسی..."></textarea></label>
<div class="inspect-actions"><button class="restock" type="submit">ثبت نتیجه بازرسی</button><button class="reject" id="inspectReject" type="button">رد کالا</button></div>
</form>
</section>
</div>
</main>
<script src="assets/js/pos-inspection.js" defer></script>
</body>
</html>
