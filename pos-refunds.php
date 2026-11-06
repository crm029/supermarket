<?php
declare(strict_types=1);
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#10251d"><meta name="robots" content="noindex,nofollow">
<title>مرجوعی فروش حضوری | سوپرمارکت ۳۶۰</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
<style>
.refund-shell{max-width:1180px;margin:28px auto;padding:0 18px}.refund-grid{display:grid;grid-template-columns:minmax(280px,.85fr) minmax(0,1.15fr);gap:18px}.refund-card{background:#fff;border:1px solid #e1e8e2;border-radius:18px;padding:20px;box-shadow:0 8px 30px #1836260a}.refund-card h2{margin:0 0 14px;font-size:18px}.refund-list{display:grid;gap:8px}.refund-sale{display:flex;justify-content:space-between;gap:12px;text-align:right;padding:12px;border:1px solid #e1e8e2;border-radius:12px;background:#fff;color:#20372a;font:inherit;cursor:pointer}.refund-sale[aria-pressed=true]{border-color:#17633f;background:#eff6f0}.refund-sale small{display:block;color:#718078;margin-top:4px}.refund-line{display:grid;grid-template-columns:minmax(0,1fr) 90px;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid #edf1ed}.refund-line input,.refund-card textarea{width:100%;border:1px solid #d5dfd7;border-radius:10px;padding:10px;font:inherit}.refund-card textarea{min-height:90px;resize:vertical}.refund-hint{color:#6b7b71;font-size:12px;line-height:1.9}.refund-total{display:flex;justify-content:space-between;font-weight:800;padding:14px 0}.refund-message{padding:12px;border-radius:10px;margin:12px 0;background:#edf2ee;color:#425449}.refund-message:empty{display:none}.refund-message.error{background:#fbecea;color:#983c33}.refund-message.success{background:#e8f5eb;color:#22623a}.refund-message.warning{background:#fff4dd;color:#8a5b0b}.refund-btn{border:0;border-radius:10px;padding:11px 16px;background:#17633f;color:white;font:inherit;font-weight:700;cursor:pointer}.refund-btn:disabled{opacity:.5;cursor:not-allowed}.refund-empty{color:#87938a;text-align:center;padding:22px}.refund-top{background:#10251d;color:#fff;padding:16px max(20px,calc((100% - 1180px)/2));display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap}.refund-top a{color:#e5c98a;text-decoration:none}
@media(max-width:760px){.refund-grid{grid-template-columns:1fr}.refund-shell{margin:18px auto}.refund-card{padding:16px}}
</style>
</head>
<body>
<header class="refund-top"><strong>۳۶۰ | مدیریت مرجوعی صندوق</strong><a href="admin.php">بازگشت به مدیریت</a></header>
<main class="refund-shell">
  <h1>بازپرداخت فروش حضوری</h1>
  <p class="refund-hint">فقط مدیر دارای مجوز بازپرداخت می‌تواند این عملیات را انجام دهد. فروش اصلی حذف یا ویرایش نمی‌شود.</p>
  <div id="refundMessage" class="refund-message" role="status"></div>
  <div id="refundShift" class="refund-hint">در حال بررسی شیفت صندوق...</div>
  <div class="refund-grid">
    <section class="refund-card"><h2>فروش‌های اخیر</h2><div id="refundSales" class="refund-list"><p class="refund-empty">در حال دریافت...</p></div></section>
    <section class="refund-card">
      <h2>جزئیات و اقلام قابل بازپرداخت</h2>
      <div id="refundDetail"><p class="refund-empty">برای مشاهده اقلام، یک فاکتور را انتخاب کنید.</p></div>
      <form id="refundForm" hidden>
        <div class="refund-field"><label for="refundReason">دلیل بازپرداخت (اجباری)</label><textarea id="refundReason" minlength="12" maxlength="500" required placeholder="دلیل مرجوعی را دقیق ثبت کنید..."></textarea></div>
        <div class="refund-total"><span>مبلغ بازپرداخت نقدی</span><strong id="refundTotal">۰ تومان</strong></div>
        <p class="refund-hint">پس از ثبت، مبلغ از مانده مورد انتظار صندوق کم می‌شود. کالای مرجوعی تا بازرسی فیزیکی به موجودی قابل‌فروش برنمی‌گردد.</p>
        <button id="refundSubmit" class="refund-btn" type="submit" disabled>ثبت بازپرداخت نقدی</button>
      </form>
    </section>
  </div>
</main>
<script src="assets/js/pos-refunds.js" defer></script>
</body></html>