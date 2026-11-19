(()=> {
  "use strict";
  const $=id=>document.getElementById(id);
  let csrf="";
  const fa=new Intl.NumberFormat("fa-IR");
  const toman=value=>fa.format(Math.floor(Number(value||0)/10))+" تومان";
  const esc=value=>String(value??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  const status=value=>({active:"فعال",past_due:"سررسید گذشته",pending_payment:"در انتظار پرداخت",pending:"در انتظار بررسی",invoiced:"فاکتور صادرشده",applied:"اعمال‌شده",rejected:"ردشده"})[value]||value||"—";
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
    box.innerHTML='<h2>وضعیت فعلی</h2><div class="subscription-current-grid"><article><span>پلن</span><strong>'+esc(sub.plan_name)+'</strong></article><article><span>وضعیت</span><strong>'+esc(status(sub.status))+'</strong></article><article><span>قیمت ماهانه</span><strong>'+toman(sub.price_monthly_rial)+'</strong></article><article><span>دوره پرداخت</span><strong>'+fa.format(sub.billing_period_months)+' ماه</strong></article><article><span>شروع دوره</span><strong>'+esc(sub.current_period_start||"—")+'</strong></article><article><span>پایان دوره</span><strong>'+esc(sub.current_period_end||"—")+'</strong></article></div>';
  }
  function renderPlans(plans,sub){
    $("subscriptionPlans").innerHTML=plans.length?plans.map(p=>{
      const limits=p.limits||{};
      const bytes=Number(limits.storage_bytes||0);\n      const storage=bytes>=1073741824?(bytes/1073741824).toFixed(1)+" گیگابایت":(bytes/1048576).toFixed(0)+" مگابایت";\n      const summary=[["محصول",limits.products],["کارمند",limits.staff],["رسانه",limits.media_assets],["سفارش ماهانه",limits.monthly_orders],["فضای ذخیره‌سازی",limits.storage_bytes===undefined?undefined:storage]];
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
      renderPlans(data.plans||[],data.subscription||null);
      renderRequests(data.change_requests||[]);
      renderInvoices(data.invoices||[]);
      $("subscriptionMessage").textContent="";
    }catch(error){$("subscriptionMessage").textContent=error.message;}
  }
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
