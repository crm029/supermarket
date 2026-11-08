<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
$tenant = app_tenant();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>گزارش‌های صندوق | مدیریت فروشگاه</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{font-family:Vazirmatn,sans-serif;color:#172235;background:#f4f6fa}*{box-sizing:border-box}body{margin:0}.top{background:#14243b;color:#fff;padding:18px max(20px,calc((100% - 1180px)/2));display:flex;justify-content:space-between;align-items:center;gap:16px}.top a{color:#fff;text-decoration:none}.wrap{max-width:1180px;margin:28px auto;padding:0 18px}.head{display:flex;justify-content:space-between;align-items:end;gap:16px;flex-wrap:wrap}.head h1{margin:0 0 5px;font-size:25px}.muted{color:#718096;font-size:13px}.filters,.card,.panel{background:#fff;border:1px solid #e5eaf1;border-radius:16px;padding:18px;box-shadow:0 5px 20px #17223508}.filters{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin:20px 0}.filters label{display:grid;gap:6px;font-size:13px}.filters input{border:1px solid #d9e1ec;border-radius:9px;padding:10px;font:inherit}.btn{background:#ed8d42;color:#fff;border:0;border-radius:9px;padding:11px 18px;font:inherit;cursor:pointer}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.card small{color:#718096;display:block}.card strong{display:block;font-size:23px;margin-top:8px}.panels{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px}.panel h2{font-size:17px;margin:0 0 14px}.tablewrap{overflow:auto}table{border-collapse:collapse;width:100%;font-size:13px;white-space:nowrap}th,td{text-align:right;padding:10px;border-bottom:1px solid #edf0f5}th{color:#66758a;font-weight:600}#status{margin:14px 0;color:#64748b}a.link{color:#ed8d42}@media(max-width:800px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.panels{grid-template-columns:1fr}.head h1{font-size:21px}}@media(max-width:420px){.grid{grid-template-columns:1fr 1fr}.card{padding:13px}.card strong{font-size:18px}}
</style>
</head>
<body>
<header class="top"><strong>۳۶۰ · گزارش‌های مدیریتی</strong><a href="admin.php">بازگشت به پنل مدیریت ←</a></header>
<main class="wrap">
<div class="head"><div><h1>گزارش صندوق و فروش</h1><div class="muted">گزارش فروش حضوری، شیفت‌ها، مغایرت‌ها و مقایسه کانال‌های فروش</div></div></div>
<form class="filters" id="filters"><label>از تاریخ<input type="date" id="from" required></label><label>تا تاریخ<input type="date" id="to" required></label><button class="btn">اعمال بازه</button></form>
<p id="status" role="status">در حال بررسی دسترسی و دریافت گزارش...</p>
<section class="grid">
<article class="card"><small>خالص فروش حضوری پس از بازپرداخت</small><strong id="posRevenue">—</strong></article>
<article class="card"><small>بازپرداخت نقدی</small><strong id="posRefundTotal">—</strong></article>
<article class="card"><small>تعداد فروش حضوری</small><strong id="posCount">—</strong></article>
<article class="card"><small>شیفت‌های باز</small><strong id="openShifts">—</strong></article>
<article class="card"><small>شیفت دارای مغایرت</small><strong id="varianceCount">—</strong></article>
</section>
<section class="panels">
<article class="panel"><h2>مقایسه فروش اینترنتی و حضوری</h2><div class="tablewrap"><table><thead><tr><th>کانال</th><th>تعداد</th><th>مبلغ (تومان)</th></tr></thead><tbody id="channels"></tbody></table></div></article>
<article class="panel"><h2>خلاصه شیفت‌ها</h2><div class="tablewrap"><table><thead><tr><th>کل شیفت</th><th>بسته</th><th>باز</th><th>مغایرت</th><th>خالص مغایرت (تومان)</th></tr></thead><tbody id="shiftSummary"></tbody></table></div></article>
<article class="panel"><h2>عملکرد صندوق‌داران</h2><div class="tablewrap"><table><thead><tr><th>صندوق‌دار</th><th>شیفت</th><th>فروش</th><th>فروش ناخالص</th><th>بازپرداخت</th><th>فروش خالص (تومان)</th><th>مغایرت</th></tr></thead><tbody id="cashiers"></tbody></table></div></article>
<article class="panel"><h2>۱۰ کالای پرفروش حضوری</h2><div class="tablewrap"><table><thead><tr><th>کالا</th><th>کد</th><th>تعداد</th><th>فروش (تومان)</th></tr></thead><tbody id="items"></tbody></table></div></article>
</section>
</main>
<script>
const api='/api/v1/',fmt=new Intl.NumberFormat('fa-IR'),money=v=>fmt.format(Math.round(Number(v||0)/10))+' تومان',num=v=>fmt.format(Number(v||0)),esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const today=new Date(),dateInput=d=>{const x=new Date(d.getTime()-d.getTimezoneOffset()*60000);return x.toISOString().slice(0,10)};
document.getElementById('to').value=dateInput(today);const start=new Date(today);start.setDate(start.getDate()-29);document.getElementById('from').value=dateInput(start);
async function load(){const status=document.getElementById('status');status.textContent='در حال دریافت گزارش...';try{
const me=await fetch(api+'me',{credentials:'same-origin',headers:{Accept:'application/json'}});if(!me.ok)throw new Error('ابتدا وارد حساب مدیریتی فروشگاه شوید.');
const url=new URL(api+'admin/pos/reports',location.href);url.searchParams.set('from',document.getElementById('from').value);url.searchParams.set('to',document.getElementById('to').value);
const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});const payload=await response.json();if(!response.ok)throw new Error(payload.error||'دریافت گزارش ناموفق بود.');
const d=payload.data,p=d.in_person,s=d.shifts,o=d.online;
document.getElementById('posRevenue').textContent=money(p.net_sales_total_rial);document.getElementById('posRefundTotal').textContent=money(p.refund_total_rial);document.getElementById('posCount').textContent=num(p.sale_count);document.getElementById('openShifts').textContent=num(s.open_shift_count);document.getElementById('varianceCount').textContent=num(s.variance_shift_count);
document.getElementById('channels').innerHTML='<tr><td>حضوری (خالص)</td><td>'+num(p.sale_count)+'</td><td>'+money(p.net_sales_total_rial)+'</td></tr><tr><td>اینترنتی</td><td>'+num(o.order_count)+'</td><td>'+money(o.revenue_rial)+'</td></tr>';
document.getElementById('shiftSummary').innerHTML='<tr><td>'+num(s.shift_count)+'</td><td>'+num(s.closed_shift_count)+'</td><td>'+num(s.open_shift_count)+'</td><td>'+num(s.variance_shift_count)+'</td><td>'+money(s.net_variance_rial)+'</td></tr>';
document.getElementById('cashiers').innerHTML=d.cashiers.map(x=>'<tr><td>'+esc(x.full_name||'بدون نام')+'</td><td>'+num(x.shift_count)+'</td><td>'+num(x.sale_count)+'</td><td>'+money(x.sales_total_rial)+'</td><td>'+num(x.variance_shift_count)+'</td></tr>').join('')||'<tr><td colspan="7">داده‌ای برای این بازه وجود ندارد.</td></tr>';
document.getElementById('items').innerHTML=d.top_items.map(x=>'<tr><td>'+esc(x.title)+'</td><td>'+esc(x.sku)+'</td><td>'+num(x.quantity_sold)+'</td><td>'+money(x.revenue_rial)+'</td></tr>').join('')||'<tr><td colspan="4">داده‌ای برای این بازه وجود ندارد.</td></tr>';
status.textContent='گزارش بازه '+d.period.from+' تا '+d.period.to+' · مبالغ به تومان نمایش داده شده‌اند.';
}catch(e){status.textContent=e.message;}}
document.getElementById('filters').addEventListener('submit',e=>{e.preventDefault();load()});load();
</script>
</body></html>
