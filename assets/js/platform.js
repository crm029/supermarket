(()=> {
  "use strict";
  const $=id=>document.getElementById(id);
  let csrfToken="",currentUser=null;
  async function api(path,options={}){
    const headers={"Accept":"application/json",...(options.headers||{})};
    if(options.body)headers["Content-Type"]="application/json";
    if(options.csrf)headers["X-CSRF-Token"]=csrfToken;
    const response=await fetch("api/v1/"+path,{...options,headers,credentials:"same-origin"});
    const payload=await response.json().catch(()=>({}));
    if(!response.ok)throw new Error(payload.error||"درخواست انجام نشد.");
    return payload;
  }
  function escapeText(value){return String(value??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));}
  function message(id,value,isError=false){$(id).textContent=value;$(id).style.color=isError?"#b42318":"#52665a";}
  function showDashboard(){ $("platformLogin").hidden=true;$("platformDashboard").hidden=false;$("platformIdentity").textContent=currentUser?currentUser.full_name+" · "+currentUser.mobile:""; }
  function showLogin(){ $("platformDashboard").hidden=true;$("platformLogin").hidden=false; }
  function statusLabel(status){return {pending:"در انتظار تأیید دامنه",active:"فعال",suspended:"تعلیق‌شده"}[status]||status;}
  function showChallenge(challenge){
    $("platformDnsRecordName").textContent=challenge.record_name||"";
    $("platformDnsRecordValue").textContent=challenge.record_value||"";
    $("platformDnsExpires").textContent=challenge.expires_at||"";
    $("platformDnsInstructions").hidden=false;
    $("platformDnsInstructions").scrollIntoView({behavior:"smooth",block:"center"});
  }
  async function loadAudit(){
    const box=$("platformAuditList");
    if(!box)return;
    try{
      const result=await api("platform/audit");
      const rows=result.data||[];
      if(!rows.length){box.innerHTML='<div class="platform-empty">هنوز رویدادی ثبت نشده است.</div>';return;}
      const labels={"platform.tenant.created":"ایجاد فروشگاه","platform.tenant.domain_challenge_issued":"صدور کد DNS","platform.tenant.domain_verified":"تأیید خودکار دامنه","platform.tenant.suspended":"تعلیق فروشگاه"};
      box.innerHTML=rows.map(row=>{
        const details=row.details?JSON.stringify(row.details):"";
        return '<article class="platform-audit-row"><div><strong>'+escapeText(labels[row.action]||row.action)+'</strong><small>'+escapeText(row.actor_name||"مدیر پلتفرم")+' · '+escapeText(row.created_at||"")+'</small></div><p>'+escapeText(row.entity_type||"")+" #"+Number(row.entity_id||0)+'</p><small>'+escapeText(details)+'</small></article>';
      }).join("");
    }catch(error){box.innerHTML='<div class="platform-empty">'+escapeText(error.message)+'</div>';}
  }
  async function loadOrphanReviews(){
    const box=$("platformOrphanReviewList");
    if(!box)return;
    try{
      const result=await api("platform/media-orphan-reviews");
      const rows=result.data||[];
      box.innerHTML=rows.length?rows.map(r=>'<article class="platform-tenant"><div class="platform-tenant-head"><div><strong>فروشگاه '+Number(r.tenant_id)+' · '+escapeText(r.tenant_name)+'</strong><p dir="ltr">'+escapeText(r.storage_key)+'</p><small>'+escapeText(r.reason)+' · درخواست‌دهنده: '+escapeText(r.requester_name)+' · '+escapeText(r.created_at)+'</small></div><span class="platform-status pending">در انتظار بررسی</span></div><div class="platform-change-actions"><button class="primary-button" data-approve-orphan="'+Number(r.id)+'">تأیید و افزودن به صف</button><button class="danger" data-reject-orphan="'+Number(r.id)+'">رد درخواست</button></div></article>').join(""):'<div class="platform-empty">درخواست در انتظار بررسی وجود ندارد.</div>';
    }catch(error){
      box.innerHTML='<div class="platform-empty">'+escapeText(error.message)+'</div>';
    }
  }
  async function loadSmsSettings(){
    const section=$("platformSmsSection");
    try{
      const result=await api("platform/sms-settings");const d=result.data||{};
      $("platformSmsProvider").value=d.provider||"disabled";
      $("platformSmsSender").value=d.sender_number||"30005883803803";
      $("platformSmsDomain").value=d.domain||"0098";
      $("platformSmsUsername").value="";
      $("platformSmsPassword").value="";
      $("platformSmsStatus").textContent="نام کاربری: "+(d.username_configured?"ثبت شده":"ثبت نشده")+" · رمز عبور: "+(d.password_configured?"ثبت شده":"ثبت نشده")+(d.updated_at?" · آخرین تغییر: "+d.updated_at:"");
      section.hidden=false;
    }catch(error){section.hidden=true;if(error.message!=="برای مدیریت پلتفرم دسترسی کافی ندارید.")message("platformMessage",error.message,true);}
  }
  async function loadPaymentSettings(){
    const section=$("platformPaymentSection");
    try{
      const result=await api("platform/payment-settings");
      const data=result.data||{};
      $("platformPaymentMode").value=data.mode==="live"?"live":"sandbox";
      $("platformPaymentStatus").textContent=data.merchant_configured
        ? "شناسه ثبت‌شده: "+(data.merchant_masked||"ثبت شده")+" · آخرین تغییر: "+(data.updated_at||"نامشخص")
        : "هنوز شناسه مرچنت ثبت نشده است؛ برای شروع تست، شناسه Sandbox را وارد کنید.";
      section.hidden=false;
    }catch(error){
      section.hidden=true;
      if(error.message!=="برای مدیریت پلتفرم دسترسی کافی ندارید.")message("platformMessage",error.message,true);
    }
  }
  async function loadTenants(){
    const box=$("platformTenantList");box.innerHTML="<p>در حال دریافت فهرست...</p>";
    try{
      const result=await api("platform/tenants");
      const tenants=result.data||[];
      $("platformTenantCount").textContent=new Intl.NumberFormat("fa-IR").format(tenants.length)+" فروشگاه";
      if(!tenants.length){box.innerHTML='<div class="platform-empty">هنوز فروشگاهی ثبت نشده است.</div>';await loadAudit();return;}
      box.innerHTML=tenants.map(t=>{
        const domain=(t.domains||[]).map(escapeText).join("، ")||"دامنه ثبت نشده";
        const stateClass=t.status==="pending"?"pending":(t.status==="suspended"?"suspended":"");
        const txtName="_supermarket360-verification."+(t.domains&&t.domains[0]?t.domains[0]:"");
        const expiry=t.verification_expires_at?" · انقضا: "+escapeText(t.verification_expires_at):"";
        const verify= t.status==="pending"&&Number(t.verified_domain_count)===0
          ?'<div class="platform-verify"><p>رکورد TXT: <code dir="ltr">'+escapeText(txtName)+'</code>'+expiry+'</p><button class="secondary-button" data-issue-challenge="'+Number(t.id)+'">صدور / تمدید توکن DNS</button><button class="primary-button" data-verify-tenant="'+Number(t.id)+'">بررسی DNS و فعال‌سازی</button><small>تلاش‌های بررسی: '+Number(t.verification_attempts||0)+' از ۱۰</small></div>':"";
        const suspend=t.status==="active"?'<button class="danger" data-suspend-tenant="'+Number(t.id)+'">تعلیق فروشگاه</button>':"";
        return '<article class="platform-tenant"><div class="platform-tenant-head"><div><h3>'+escapeText(t.name)+' · '+escapeText(t.slug)+'</h3><p dir="ltr">'+domain+'</p><p>کاربران: '+new Intl.NumberFormat("fa-IR").format(Number(t.user_count)||0)+' · سفارش‌ها: '+new Intl.NumberFormat("fa-IR").format(Number(t.order_count)||0)+' · منطقه زمانی: '+escapeText(t.timezone)+'</p></div><span class="platform-status '+stateClass+'">'+statusLabel(t.status)+'</span></div>'+verify+suspend+'</article>';
      }).join("");
      await loadAudit();
    }catch(error){box.innerHTML='<div class="platform-empty">'+escapeText(error.message)+'</div>';}
  }

  const moneyToman=value=>new Intl.NumberFormat("fa-IR").format(Math.floor(Number(value||0)/10));
  function billingStatus(value){return ({pending_payment:"در انتظار پرداخت",active:"فعال",past_due:"سررسید گذشته",cancelled:"لغوشده",expired:"منقضی"})[value]||value;}
  async function loadBilling(){
    const section=$("platformBillingSection");
    try{
      const result=await api("platform/billing");
      const data=result.data||{};
      section.hidden=false;
      const totals=data.totals||{};
      $("platformInvoiceCount").textContent=new Intl.NumberFormat("fa-IR").format(totals.invoice_count||0);
      $("platformCollected").textContent=moneyToman(totals.collected_rial);
      $("platformOutstanding").textContent=moneyToman(totals.outstanding_rial);
      const plans=data.plans||[];
      $("platformPlanList").innerHTML=plans.length?plans.map(p=>'<article class="platform-tenant"><div class="platform-tenant-head"><div><strong>'+escapeText(p.name)+' · '+escapeText(p.code)+'</strong><p>'+moneyToman(p.monthly_price_rial)+' تومان در ماه</p><small>'+escapeText(p.description||"")+'</small></div><span class="platform-status '+(p.is_active?"":"suspended")+'">'+(p.is_active?"فعال":"غیرفعال")+'</span></div><button class="secondary-button" data-toggle-plan="'+Number(p.id)+'" data-active="'+(p.is_active?"1":"0")+'">'+(p.is_active?"غیرفعال‌کردن":"فعال‌کردن")+'</button></article>').join(""):'<div class="platform-empty">هنوز پلنی تعریف نشده است.</div>';
      const activePlans=plans.filter(p=>p.is_active);
      $("platformBillingTenantList").innerHTML=(data.tenants||[]).map(t=>{
        const active=["pending_payment","active","past_due"].includes(t.subscription_status);
        const sub=t.subscription_id?'<p>اشتراک: '+escapeText(t.plan_name||"پلن")+' · '+escapeText(billingStatus(t.subscription_status))+' · '+Number(t.billing_period_months||1)+' ماه</p><small>پایان دوره: '+escapeText(t.current_period_end||"پس از پرداخت تعیین می‌شود")+'</small>':"<p>اشتراکی ثبت نشده است.</p>";
        const select=activePlans.length?'<div class="platform-form-grid"><label>پلن<select data-plan-select="'+Number(t.id)+'">'+activePlans.map(p=>'<option value="'+Number(p.id)+'">'+escapeText(p.name)+' · '+moneyToman(p.monthly_price_rial)+' تومان/ماه</option>').join("")+'</select></label><label>دوره<select data-month-select="'+Number(t.id)+'"><option value="1">۱ ماه</option><option value="3">۳ ماه</option><option value="12">۱۲ ماه</option></select></label></div><button class="primary-button" data-assign-subscription="'+Number(t.id)+'" '+(active?"disabled":"")+'>صدور اشتراک و فاکتور</button>':"";
        const renew=t.subscription_id&&["active","past_due"].includes(t.subscription_status)?'<button class="secondary-button" data-renew-subscription="'+Number(t.subscription_id)+'">صدور فاکتور تمدید</button>':"";
        return '<article class="platform-tenant"><div class="platform-tenant-head"><div><strong>'+escapeText(t.name)+'</strong><small>'+escapeText(t.slug)+'</small>'+sub+'</div><span class="platform-status">'+escapeText(t.status)+'</span></div>'+select+renew+'</article>';
      }).join("")||'<div class="platform-empty">فروشگاهی ثبت نشده است.</div>';
      const invoices=data.invoices||[];
      $("platformInvoiceList").innerHTML=invoices.length?invoices.map(i=>{
        const due=Math.max(0,Number(i.amount_rial)-Number(i.paid_rial));
        const pay=i.status==="issued"&&due>0?'<button class="primary-button" data-record-payment="'+Number(i.id)+'" data-due="'+due+'">ثبت دریافت وجه</button>':"";
        return '<article class="platform-tenant"><div class="platform-tenant-head"><div><strong>'+escapeText(i.invoice_number)+' · '+escapeText(i.tenant_name)+'</strong><p>مبلغ: '+moneyToman(i.amount_rial)+' تومان · دریافت: '+moneyToman(i.paid_rial)+' تومان</p><small>'+escapeText(i.period_start)+' تا '+escapeText(i.period_end)+' · سررسید '+escapeText(i.due_at)+'</small></div><span class="platform-status '+(i.status==="paid"?"":"pending")+'">'+(i.status==="paid"?"پرداخت‌شده":i.status==="issued"?"باز":"باطل")+'</span></div>'+pay+'</article>';
      }).join(""):'<div class="platform-empty">هنوز فاکتوری صادر نشده است.</div>';
    }catch(error){section.hidden=true;if(error.message!=="برای مدیریت پلتفرم دسترسی کافی ندارید.")message("platformBillingMessage",error.message,true);}
  }

  function usageNumber(value){return new Intl.NumberFormat("fa-IR").format(Number(value||0));}
  function usageBytes(value){
    const n=Number(value||0);
    if(n<1024)return usageNumber(n)+" بایت";
    if(n<1024*1024)return (n/1024).toLocaleString("fa-IR",{maximumFractionDigits:1})+" کیلوبایت";
    if(n<1024*1024*1024)return (n/1024/1024).toLocaleString("fa-IR",{maximumFractionDigits:1})+" مگابایت";
    return (n/1024/1024/1024).toLocaleString("fa-IR",{maximumFractionDigits:2})+" گیگابایت";
  }


  async function loadChangeRequests(){
    const box=$("platformChangeRequestList");
    if(!box)return;
    box.innerHTML="<p>در حال دریافت درخواست‌ها...</p>";
    try{
      const result=await api("platform/subscription-change-requests");
      const rows=result.data||[];
      box.innerHTML=rows.length?rows.map(r=>{
        const status=r.status==="pending"?"در انتظار بررسی":"فاکتور صادرشده";
        const actions=r.status==="pending"
          ?'<div class="platform-change-actions"><button class="primary-button" data-approve-change="'+Number(r.id)+'">تأیید و صدور فاکتور</button><button class="danger" data-reject-change="'+Number(r.id)+'">رد درخواست</button></div>'
          :'<small>فاکتور: '+escapeText(r.invoice_number||"—")+' · مبلغ: '+moneyToman(r.amount_rial)+' تومان · سررسید: '+escapeText(r.due_at||"—")+'</small>';
        return '<article class="platform-tenant"><div class="platform-tenant-head"><div><strong>'+escapeText(r.tenant_name)+' · '+escapeText(r.plan_name)+'</strong><small>'+escapeText(r.slug)+' · دوره '+Number(r.requested_billing_period_months)+' ماه · '+moneyToman(r.monthly_price_rial)+' تومان/ماه</small><small>ثبت درخواست: '+escapeText(r.created_at)+'</small></div><span class="platform-status '+(r.status==="pending"?"pending":"")+'">'+status+'</span></div>'+actions+'</article>';
      }).join(""):'<div class="platform-empty">درخواست تغییر پلن بازی وجود ندارد.</div>';
    }catch(error){box.innerHTML='<div class="platform-empty">'+escapeText(error.message)+'</div>';}
  }
  async function loadReconciliation(){
    const list=$("platformReconciliationList"),totalsBox=$("platformReconciliationTotals");
    if(!list||!totalsBox)return;
    list.innerHTML="<p>در حال بررسی دفتر مالی...</p>";
    try{
      const result=await api("platform/billing/reconciliation");
      const data=result.data||{},totals=data.totals||{},rows=data.anomalies||[];
      totalsBox.innerHTML=[
        ["کل فاکتورها",usageNumber(totals.invoice_count)],
        ["مبلغ صادرشده",moneyToman(totals.invoiced_rial)+" تومان"],
        ["دریافت دفتر",moneyToman(totals.received_rial)+" تومان"],
        ["مانده باز",moneyToman(totals.outstanding_rial)+" تومان"],
        ["مغایرت‌ها",usageNumber(totals.anomaly_count)]
      ].map(([label,value])=>'<article><span>'+escapeText(label)+'</span><strong>'+escapeText(value)+'</strong></article>').join("");
      const labels={overpaid:"دریافت بیش از مبلغ فاکتور",paid_amount_mismatch:"فاکتور پرداخت‌شده با جمع دریافتی متفاوت",paid_timestamp_missing:"زمان پرداخت ثبت نشده",unexpected_paid_timestamp:"زمان پرداخت برای فاکتور غیرپرداخت‌شده",fully_received_but_open:"فاکتور کامل دریافت شده ولی باز است",void_invoice_has_receipts:"برای فاکتور باطل‌شده دریافت ثبت شده"};
      list.innerHTML=rows.length?rows.map(row=>'<article class="platform-tenant platform-reconciliation-row"><div class="platform-tenant-head"><div><strong>'+escapeText(row.invoice_number)+' · '+escapeText(row.tenant_name)+'</strong><small>'+escapeText(labels[row.anomaly_code]||row.anomaly_code)+'</small></div><span class="platform-status suspended">نیازمند بررسی</span></div><p>مبلغ: '+moneyToman(row.amount_rial)+' تومان · دریافت: '+moneyToman(row.paid_rial)+' تومان · مانده: '+moneyToman(row.balance_rial)+' تومان</p><small>سررسید: '+escapeText(row.due_at||"—")+' · شناسه فاکتور: '+Number(row.id)+'</small></article>').join(""):'<div class="platform-empty">مغایرت مالی ثبت‌شده‌ای پیدا نشد.</div>';
    }catch(error){list.innerHTML='<div class="platform-empty">'+escapeText(error.message)+'</div>';totalsBox.innerHTML="";}
  }
  async function loadUsage(){
    const list=$("platformUsageList"),totalsBox=$("platformUsageTotals");
    if(!list||!totalsBox)return;
    list.innerHTML="<p>در حال محاسبه مصرف...</p>";
    try{
      const result=await api("platform/usage");
      const data=result.data||{},summary=data.totals||{},tenants=data.tenants||[];
      $("platformUsagePeriod").textContent="ماه جاری UTC · "+escapeText(data.month_utc||"");
      const totalCards=[
        ["فروشگاه‌ها",summary.tenants],["اشتراک فعال",summary.active_subscriptions],
        ["اشتراک سررسیدگذشته",summary.past_due_subscriptions],["سفارش ماهانه",summary.monthly_orders],
        ["محصول فعال",summary.products],["حجم رسانه",usageBytes(summary.storage_bytes)]
      ];
      totalsBox.innerHTML=totalCards.map(([label,value])=>'<article><span>'+escapeText(label)+'</span><strong>'+escapeText(typeof value==="number"?usageNumber(value):value)+'</strong></article>').join("");
      const metrics=[
        ["products","محصول فعال",usageNumber],["staff","کارمند",usageNumber],
        ["media_assets","فایل رسانه",usageNumber],["storage_bytes","حجم رسانه",usageBytes],
        ["monthly_orders","سفارش این ماه",usageNumber]
      ];
      list.innerHTML=tenants.map(t=>{
        const usage=t.usage||{},limits=t.limits||{};
        const rows=metrics.map(([key,label,format])=>{
          const current=Number(usage[key]||0),raw=limits[key];
          const hasLimit=raw!==undefined&&raw!==null&&Number.isFinite(Number(raw))&&Number(raw)>=0;
          const cap=hasLimit?Number(raw):null;
          const pct=hasLimit&&cap>0?Math.min(100,Math.round(current/cap*100)):hasLimit?100:0;
          const detail=hasLimit?format(current)+" / "+format(cap):format(current)+" / بدون سقف";
          return '<div class="platform-usage-metric"><div><span>'+escapeText(label)+'</span><strong>'+escapeText(detail)+'</strong></div>'+(hasLimit?'<div class="platform-usage-track"><i style="width:'+pct+'%"></i></div>':'<div class="platform-usage-unlimited">بدون محدودیت تعریف‌شده</div>')+'</div>';
        }).join("");
        return '<article class="platform-usage-tenant"><div class="platform-tenant-head"><div><strong>'+escapeText(t.name)+'</strong><small>'+escapeText(t.plan_name||"بدون پلن")+' · '+escapeText(t.subscription_status||"بدون اشتراک")+'</small></div><span class="platform-status">'+escapeText(t.tenant_status||"")+'</span></div><div class="platform-usage-metrics">'+rows+'</div></article>';
      }).join("")||'<div class="platform-empty">فروشگاهی برای گزارش مصرف وجود ندارد.</div>';
    }catch(error){list.innerHTML='<div class="platform-empty">'+escapeText(error.message)+'</div>';totalsBox.innerHTML="";}
  }
  async function init(){
    try{
      const me=await api("me");currentUser=me.data;
      const csrf=await api("auth/csrf");csrfToken=csrf.data.csrf_token;
      showDashboard();await Promise.all([loadTenants(),loadPaymentSettings(),loadSmsSettings(),loadBilling(),loadUsage(),loadReconciliation(),loadChangeRequests(),loadOrphanReviews()]);
    }catch(error){
      showLogin();
      if(error.message!=="برای ادامه وارد حساب کاربری شوید.")message("platformLoginMessage",error.message,true);
    }
  }
  $("platformRequestOtp").addEventListener("click",async()=>{
    try{
      await api("auth/request-otp",{method:"POST",body:JSON.stringify({mobile:$("platformMobile").value.trim()})});
      $("platformOtpArea").hidden=false;
      message("platformLoginMessage","اگر حساب فعال باشد، کد ورود ارسال می‌شود.");
    }catch(error){message("platformLoginMessage",error.message,true);}
  });
  $("platformVerifyOtp").addEventListener("click",async()=>{
    try{
      const result=await api("auth/verify-otp",{method:"POST",body:JSON.stringify({mobile:$("platformMobile").value.trim(),code:$("platformOtpCode").value.trim()})});
      currentUser=result.data.user;csrfToken=result.data.csrf_token;showDashboard();await Promise.all([loadTenants(),loadPaymentSettings(),loadSmsSettings(),loadBilling(),loadUsage(),loadReconciliation(),loadChangeRequests()]);
    }catch(error){message("platformLoginMessage",error.message,true);}
  });
  $("platformSmsForm").addEventListener("submit",async event=>{
    event.preventDefault();
    try{
      const result=await api("platform/sms-settings",{method:"PUT",csrf:true,body:JSON.stringify({
        provider:$("platformSmsProvider").value,
        sender_number:$("platformSmsSender").value.trim(),
        username:$("platformSmsUsername").value.trim(),
        password:$("platformSmsPassword").value,
        domain:"0098"
      })});
      $("platformSmsUsername").value="";$("platformSmsPassword").value="";
      message("platformSmsMessage",result.message||"تنظیمات ذخیره شد.");await loadSmsSettings();
    }catch(error){message("platformSmsMessage",error.message,true);}
  });
  $("platformPaymentForm").addEventListener("submit",async event=>{
    event.preventDefault();
    const mode=$("platformPaymentMode").value;
    let confirmationPhrase="";
    if(mode==="live"){
      const phrase=window.prompt("برای فعال‌سازی درگاه واقعی، عبارت REAL را دقیقاً وارد کنید:");
      if(phrase===null)return;
      confirmationPhrase=phrase.trim();
      if(confirmationPhrase!=="REAL"){message("platformPaymentMessage","عبارت تأیید صحیح نیست.",true);return;}
    }
    try{
      const result=await api("platform/payment-settings",{method:"PUT",csrf:true,body:JSON.stringify({
        mode,
        merchant_id:$("platformMerchantId").value.trim(),
        confirmation_phrase:confirmationPhrase
      })});
      $("platformMerchantId").value="";
      message("platformPaymentMessage",result.message||"تنظیمات ذخیره شد.");
      await loadPaymentSettings();
    }catch(error){message("platformPaymentMessage",error.message,true);}
  });
  $("platformCreateForm").addEventListener("submit",async event=>{
    event.preventDefault();
    const body={
      name:$("newTenantName").value.trim(),slug:$("newTenantSlug").value.trim(),
      host:$("newTenantHost").value.trim(),owner_name:$("newOwnerName").value.trim(),
      owner_mobile:$("newOwnerMobile").value.trim(),timezone:$("newTenantTimezone").value
    };
    try{
      const result=await api("platform/tenants",{method:"POST",body:JSON.stringify(body),csrf:true});
      message("platformMessage",result.message+" شناسه فروشگاه: "+result.data.tenant_id);
      showChallenge(result.data.verification);$("platformCreateForm").reset();await loadTenants();
    }catch(error){message("platformMessage",error.message,true);}
  });
  $("platformTenantList").addEventListener("click",async event=>{
    const verify=event.target.closest("[data-verify-tenant]");
    const issue=event.target.closest("[data-issue-challenge]");
    const suspend=event.target.closest("[data-suspend-tenant]");
    try{
      if(issue){
        const id=Number(issue.dataset.issueChallenge);
        const result=await api("platform/tenants/"+id+"/verification-challenge",{method:"POST",body:"{}",csrf:true});
        showChallenge(result.data);message("platformMessage",result.message);
      }else if(verify){
        const id=Number(verify.dataset.verifyTenant);
        if(!window.confirm("سامانه رکورد DNS TXT را بررسی می‌کند و فقط در صورت تطبیق، فروشگاه فعال می‌شود."))return;
        const result=await api("platform/tenants/"+id+"/verify-domain",{method:"POST",body:"{}",csrf:true});
        message("platformMessage",result.message);$("platformDnsInstructions").hidden=true;await loadTenants();
      }else if(suspend){
        const id=Number(suspend.dataset.suspendTenant);
        const reason=window.prompt("دلیل تعلیق فروشگاه را وارد کنید:");
        if(!reason)return;
        const result=await api("platform/tenants/"+id+"/suspend",{method:"POST",body:JSON.stringify({reason}),csrf:true});
        message("platformMessage",result.message);await loadTenants();
      }
    }catch(error){message("platformMessage",error.message,true);}
  });
  $("platformOrphanReviewForm").addEventListener("submit",async event=>{
    event.preventDefault();
    try{
      const result=await api("platform/media-orphan-reviews",{method:"POST",csrf:true,body:JSON.stringify({
        tenant_id:Number($("orphanTenantId").value),
        storage_key:$("orphanStorageKey").value.trim(),
        reason:$("orphanReviewReason").value.trim()
      })});
      message("platformOrphanReviewMessage",result.message);
      $("platformOrphanReviewForm").reset();
      await loadOrphanReviews();
    }catch(error){message("platformOrphanReviewMessage",error.message,true);}
  });
  $("platformOrphanReviewList").addEventListener("click",async event=>{
    const approve=event.target.closest("[data-approve-orphan]");
    const reject=event.target.closest("[data-reject-orphan]");
    if(!approve&&!reject)return;
    const id=Number((approve||reject).dataset.approveOrphan||(approve||reject).dataset.rejectOrphan);
    const action=approve?"approve":"reject";
    const phrase=approve
      ?"با تأیید، فایل پس از بررسی مجدد وارد صف پاک‌سازی می‌شود. worker آن را حذف خواهد کرد. ادامه می‌دهید؟"
      :"درخواست رد شود؟ هیچ فایلی حذف نخواهد شد.";
    if(!window.confirm(phrase))return;
    try{
      const result=await api("platform/media-orphan-reviews/"+id+"/"+action,{method:"POST",csrf:true,body:"{}"});
      message("platformOrphanReviewMessage",result.message);
      await loadOrphanReviews();
      await loadAudit();
    }catch(error){message("platformOrphanReviewMessage",error.message,true);}
  });
  $("platformCopyDnsValue").addEventListener("click",async()=>{
    try{await navigator.clipboard.writeText($("platformDnsRecordValue").textContent);message("platformMessage","مقدار TXT کپی شد.");}
    catch(_){message("platformMessage","کپی خودکار در دسترس نیست؛ مقدار TXT را دستی انتخاب و کپی کنید.",true);}
  });


  $("platformChangeRequestList").addEventListener("click",async event=>{
    const approve=event.target.closest("[data-approve-change]");
    const reject=event.target.closest("[data-reject-change]");
    try{
      if(approve){
        const id=Number(approve.dataset.approveChange);
        if(!window.confirm("درخواست تأیید و برای دوره بعد فاکتور صادر شود؟"))return;
        const result=await api("platform/subscription-change-requests/"+id+"/approve",{method:"POST",csrf:true,body:"{}"});
        message("platformBillingMessage",result.message+" شناسه فاکتور: "+result.data.invoice_id);
      }else if(reject){
        const id=Number(reject.dataset.rejectChange);
        if(!window.confirm("این درخواست تغییر پلن رد شود؟"))return;
        const result=await api("platform/subscription-change-requests/"+id+"/reject",{method:"POST",csrf:true,body:"{}"});
        message("platformBillingMessage",result.message);
      }else return;
      await Promise.all([loadChangeRequests(),loadBilling()]);
    }catch(error){message("platformBillingMessage",error.message,true);}
  });
  $("platformExportBilling").addEventListener("click",()=>{window.location.href="api/v1/platform/billing/export";});
  $("platformPlanForm").addEventListener("submit",async event=>{
    event.preventDefault();
    let limits;
    try{
      limits=JSON.parse($("platformPlanLimits").value||"{}");
      if(!limits||Array.isArray(limits)||typeof limits!=="object")throw new Error("محدودیت‌ها باید یک شیء JSON باشند.");
    }catch(error){message("platformPlanMessage",error.message||"JSON محدودیت‌ها معتبر نیست.",true);return;}
    try{
      const result=await api("platform/plans",{method:"POST",csrf:true,body:JSON.stringify({
        code:$("platformPlanCode").value.trim(),name:$("platformPlanName").value.trim(),
        description:$("platformPlanDescription").value.trim(),monthly_price_rial:Math.round(Number($("platformPlanPrice").value)*10),limits
      })});
      message("platformPlanMessage",result.message);$("platformPlanForm").reset();await loadBilling();
    }catch(error){message("platformPlanMessage",error.message,true);}
  });
  $("platformPlanList").addEventListener("click",async event=>{
    const button=event.target.closest("[data-toggle-plan]");if(!button)return;
    const id=Number(button.dataset.togglePlan),current=button.dataset.active==="1";
    try{
      const latest=await api("platform/billing");
      const item=(latest.data.plans||[]).find(p=>p.id===id);if(!item)return;
      await api("platform/plans/"+id,{method:"PUT",csrf:true,body:JSON.stringify({
        name:item.name,description:item.description,monthly_price_rial:item.monthly_price_rial,
        limits:item.limits||{},is_active:!current
      })});
      await loadBilling();
    }catch(error){message("platformBillingMessage",error.message,true);}
  });
  $("platformBillingTenantList").addEventListener("click",async event=>{
    const assign=event.target.closest("[data-assign-subscription]"),renew=event.target.closest("[data-renew-subscription]");
    try{
      if(assign){
        const id=Number(assign.dataset.assignSubscription);
        const planId=Number(document.querySelector('[data-plan-select="'+id+'"]').value);
        const months=Number(document.querySelector('[data-month-select="'+id+'"]').value);
        const result=await api("platform/tenants/"+id+"/subscription",{method:"POST",csrf:true,body:JSON.stringify({plan_id:planId,billing_period_months:months})});
        message("platformBillingMessage",result.message+" مبلغ فاکتور: "+moneyToman(result.data.amount_rial)+" تومان.");
      }else if(renew){
        const id=Number(renew.dataset.renewSubscription);
        const result=await api("platform/subscriptions/"+id+"/renew",{method:"POST",csrf:true,body:"{}"});
        message("platformBillingMessage",result.message);
      }
      await loadBilling();
    }catch(error){message("platformBillingMessage",error.message,true);}
  });
  $("platformInvoiceList").addEventListener("click",async event=>{
    const button=event.target.closest("[data-record-payment]");if(!button)return;
    const invoiceId=Number(button.dataset.recordPayment),due=Number(button.dataset.due);
    const amountInput=window.prompt("مبلغ دریافتی به تومان (مانده: "+moneyToman(due)+" تومان):",String(Math.ceil(due/10)));
    if(amountInput===null)return;
    const toman=Number(amountInput);
    if(!Number.isSafeInteger(toman)||toman<1){message("platformBillingMessage","مبلغ واردشده معتبر نیست.",true);return;}
    const reference=window.prompt("شناسه پیگیری یا شماره رسید (اختیاری):","")??"";
    try{
      const result=await api("platform/invoices/"+invoiceId+"/payments",{method:"POST",csrf:true,body:JSON.stringify({
        amount_rial:toman*10,method:"bank_transfer",reference,
        idempotency_key:"ui-"+crypto.randomUUID().replace(/-/g,"")
      })});
      message("platformBillingMessage",result.message);await loadBilling();
    }catch(error){message("platformBillingMessage",error.message,true);}
  });
  $("platformReload").addEventListener("click",async()=>{await Promise.all([loadTenants(),loadPaymentSettings(),loadBilling(),loadUsage(),loadReconciliation(),loadChangeRequests(),loadOrphanReviews()]);});
  $("platformLogout").addEventListener("click",async()=>{
    try{await api("auth/logout",{method:"POST",body:"{}",csrf:true});}catch(_){}
    currentUser=null;csrfToken="";showLogin();
  });
  init();
})();
