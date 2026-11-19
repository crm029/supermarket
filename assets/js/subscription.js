(()=> {
  "use strict";
  const $=id=>document.getElementById(id);
  let csrf="";
  const fa=new Intl.NumberFormat("fa-IR");
  const toman=value=>fa.format(Math.floor(Number(value||0)/10))+" تومان";
  const esc=value=>String(value??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  const status=value=>({active:"فعال",past_due:"سررسید گذشته",pending_payment:"در انتظار پرداخت",cancelled:"لغوشده",expired:"منقضی",pending:"در انتظار بررسی",invoiced:"فاکتور صادرشده",applied:"اعمال‌شده",rejected:"ردشده"})[value]||value||"—";
  async function api(path,options={}){
    const headers={"Accept":"application/json",...(options.headers||{})};
    if(options.body)headers["Content-Type"]="application/json";
    if(options.csrf)headers["X-CSRF-Token"]=csrf;
    const response=await fetch("api/v1/"+path,{...options,headers,credentials:"same-origin"});
    const data=await response.json().catch(()=>({}));
    if(!response.ok)throw new Error(data.error||"درخواست انجام نشد.");
    return data;
  }
  function renderCurrent(sub){
    const box=$("subscriptionCurrent");
    if(!sub){box.hidden=false;box.innerHTML="<h2>اشتراک فعالی ثبت نشده است</h2><p>برای فعال‌سازی فروشگاه با پشتیبانی پلتفرم تماس بگیرید.</p>";return;}
    box.hidden=false;
    box.innerHTML='<h2>وضعیت فعلی</h2><div class="subscription-current-grid"><article><span>پلن</span><strong>'+esc(sub.plan_name)+'</strong></article><article><span>وضعیت</span><strong>'+esc(status(sub.status))+'</strong></article><article><span>قیمت ماهانه</span><strong>'+toman(sub.price_monthly_rial)+'</strong></article><article><span>دوره پرداخت</span><strong>'+fa.format(sub.billing_period_months)+' ماه</strong></article><article><span>شروع دوره</span><strong>'+esc(sub.current_period_start||"—")+'</strong></article><article><span>پایان دوره</span><strong>'+esc(sub.current_period_end||"—")+'</strong></article></div>'+(Number(sub.cancel_at_period_end)===1?'<p class="subscription-note">لغو برای پایان دوره زمان‌بندی شده است. تا '+esc(sub.current_period_end||"پایان دوره")+' دسترسی شما برقرار می‌ماند.</p><button id="subscriptionResumeButton" class="primary-button" type="button">لغو درخواست و ادامه اشتراک</button>':(sub.status==="active"?'<button id="subscriptionCancelButton" class="subscription-cancel-button" type="button">زمان‌بندی لغو در پایان دوره</button>':(sub.status==="pending_payment"?'<p class="subscription-note">پس از پرداخت فاکتور و فعال‌شدن اشتراک، مدیریت تمدید و لغو در دسترس خواهد بود.</p>':(sub.status==="cancelled"?'<p class="subscription-note">این اشتراک در پایان دوره لغو شده است. برای فعال‌سازی مجدد با مدیریت پلتفرم تماس بگیرید.</p>':(sub.status==="expired"?'<p class="subscription-note">دوره این اشتراک به پایان رسیده است.</p>':'<p class="subscription-note">وضعیت اشتراک برای زمان‌بندی لغو عادی نیست؛ لطفاً وضعیت مالی را با پشتیبانی بررسی کنید.</p>')))));
  }
  function renderUsage(usage,sub,month){
    const box=$("subscriptionUsage");
    if(!sub){box.hidden=true;return;}
    box.hidden=false;
    $("subscriptionUsageMonth").textContent="ماه محاسباتی UTC: "+fa.format(Number(String(month||"").slice(5,7)||0))+" / "+String(month||"");
    const labels={products:"محصول فعال",staff:"کارمند فعال",media_assets:"فایل رسانه",storage_bytes:"فضای ذخیره‌سازی",monthly_orders:"سفارش این ماه"};
    const pretty=(key,value)=>key==="storage_bytes"?(value>=1073741824?(value/1073741824).toFixed(1)+" گیگابایت":(value/1048576).toFixed(0)+" مگابایت"):fa.format(value);
    const rows=Object.keys(labels).map(key=>{
      const current=Number(usage[key]||0),limit=sub.limits&&Object.prototype.hasOwnProperty.call(sub.limits,key)?Number(sub.limits[key]):null;
      const percent=limit===null?0:(limit===0?(current>0?100:0):Math.min(100,Math.round(current/limit*100)));
      const total=limit===null?"بدون سقف تعریف‌شده":pretty(key,limit);
      return '<article class="subscription-usage-item"><div><strong>'+labels[key]+'</strong><span>'+pretty(key,current)+' / '+total+'</span></div><div class="subscription-usage-track"><span style="width:'+percent+'%"></span></div></article>';
    });
    $("subscriptionUsageGrid").innerHTML=rows.join("");
  }
  function renderPlans(plans,sub){
    $("subscriptionPlans").innerHTML=plans.length?plans.map(p=>{
      const limits=p.limits||{};
      const bytes=Number(limits.storage_bytes||0);
      const storage=bytes>=1073741824?(bytes/1073741824).toFixed(1)+" گیگابایت":(bytes/1048576).toFixed(0)+" مگابایت";
      const summary=[["محصول",limits.products],["کارمند",limits.staff],["رسانه",limits.media_assets],["سفارش ماهانه",limits.monthly_orders],["فضای ذخیره‌سازی",limits.storage_bytes===undefined?undefined:storage]];
      return '<article class="subscription-plan"><h3>'+esc(p.name)+'</h3><strong>'+toman(p.monthly_price_rial)+'</strong><p>ماهانه</p><p>'+esc(p.description||"")+'</p><ul>'+summary.filter(x=>x[1]!==undefined).map(x=>'<li>'+esc(x[0])+': '+fa.format(Number(x[1]))+'</li>').join("")+'</ul></article>';
    }).join(""):'<div>پلن فعالی برای انتخاب وجود ندارد.</div>';
    $("subscriptionPlanSelect").innerHTML=plans.map(p=>'<option value="'+Number(p.id)+'">'+esc(p.name)+' · '+toman(p.monthly_price_rial)+'</option>').join("");
    $("subscriptionRequestForm").hidden=!sub||!plans.length||sub.status!=="active";
    if(sub&&plans.length){
      const available=plans.find(p=>Number(p.id)!==Number(sub.plan_id))||plans[0];
      $("subscriptionPlanSelect").value=String(available.id);
    }
  }
  function renderRequests(rows){
    $("subscriptionRequests").innerHTML=rows.length?rows.map(r=>{
      const invoice=r.invoice_id?'<p>فاکتور: '+esc(r.invoice_number)+' · '+toman(r.amount_rial)+' · سررسید '+esc(r.due_at)+'</p>':"";
      return '<article class="subscription-request"><div class="subscription-request-head"><strong>'+esc(r.requested_plan_name)+' · '+fa.format(r.requested_billing_period_months)+' ماه</strong><span class="subscription-status">'+esc(status(r.status))+'</span></div><small>ثبت: '+esc(r.created_at)+'</small>'+invoice+'</article>';
    }).join(""):'<div class="subscription-request">هنوز درخواستی ثبت نکرده‌اید.</div>';
  }
  function renderInvoices(rows){
    const box=$("subscriptionInvoices");
    box.innerHTML=rows.length?rows.map(invoice=>{
      const label=({paid:"پرداخت‌شده",partial:"پرداخت ناقص",unpaid:"در انتظار پرداخت",void:"باطل‌شده"})[invoice.display_status]||invoice.display_status;
      return '<article class="subscription-invoice"><div class="subscription-invoice-head"><strong>'+esc(invoice.invoice_number)+'</strong><span class="subscription-status">'+label+'</span></div><div class="subscription-invoice-grid"><span>پلن: '+esc(invoice.plan_name)+'</span><span>مبلغ: '+toman(invoice.amount_rial)+'</span><span>پرداخت‌شده: '+toman(invoice.paid_rial)+'</span><span>مانده: '+toman(invoice.balance_rial)+'</span><span>سررسید: '+esc(invoice.due_at)+'</span><span>دوره: '+esc(invoice.period_start)+' تا '+esc(invoice.period_end)+'</span></div></article>';
    }).join(""):'<div class="subscription-request">هنوز فاکتوری برای این فروشگاه صادر نشده است.</div>';
  }
  async function load(){
    try{
      const [csrfResult,result]=await Promise.all([api("auth/csrf"),api("admin/subscription")]);
      csrf=csrfResult.data.csrf_token;
      const data=result.data||{};
      renderCurrent(data.subscription||null);
      renderUsage(data.usage||{},data.subscription||null,data.usage_month_utc||"");
      renderPlans(data.plans||[],data.subscription||null);
      renderRequests(data.change_requests||[]);
      renderInvoices(data.invoices||[]);
      $("subscriptionMessage").textContent="";
    }catch(error){$("subscriptionMessage").textContent=error.message;}
  }
  $("subscriptionCurrent").addEventListener("click",async event=>{
    const cancel=event.target.closest("#subscriptionCancelButton");
    const resume=event.target.closest("#subscriptionResumeButton");
    if(!cancel&&!resume)return;
    const action=cancel?"cancel":"resume";
    const question=cancel?"اشتراک در پایان دوره لغو شود؟ تا تاریخ پایان دوره امکان استفاده باقی می‌ماند.":"درخواست لغو برداشته شود و تمدید اشتراک ادامه پیدا کند؟";
    if(!window.confirm(question))return;
    try{
      const result=await api("admin/subscription/"+action,{method:"POST",csrf:true,body:"{}"});
      $("subscriptionMessage").textContent=result.message||"درخواست ثبت شد.";
      await load();
    }catch(error){$("subscriptionMessage").textContent=error.message;}
  });
  $("subscriptionRequestForm").addEventListener("submit",async event=>{
    event.preventDefault();
    try{
      const result=await api("admin/subscription/change-requests",{method:"POST",csrf:true,body:JSON.stringify({
        plan_id:Number($("subscriptionPlanSelect").value),
        billing_period_months:Number($("subscriptionPeriodSelect").value)
      })});
      $("subscriptionMessage").textContent=result.message||"درخواست ثبت شد.";
      await load();
    }catch(error){$("subscriptionMessage").textContent=error.message;}
  });
  load();
})();
