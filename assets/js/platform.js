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
  async function init(){
    try{
      const me=await api("me");currentUser=me.data;
      const csrf=await api("auth/csrf");csrfToken=csrf.data.csrf_token;
      showDashboard();await Promise.all([loadTenants(),loadPaymentSettings(),loadBilling()]);
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
      currentUser=result.data.user;csrfToken=result.data.csrf_token;showDashboard();await Promise.all([loadTenants(),loadPaymentSettings(),loadBilling()]);
    }catch(error){message("platformLoginMessage",error.message,true);}
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
  $("platformCopyDnsValue").addEventListener("click",async()=>{
    try{await navigator.clipboard.writeText($("platformDnsRecordValue").textContent);message("platformMessage","مقدار TXT کپی شد.");}
    catch(_){message("platformMessage","کپی خودکار در دسترس نیست؛ مقدار TXT را دستی انتخاب و کپی کنید.",true);}
  });

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
  $("platformReload").addEventListener("click",loadTenants);
  $("platformLogout").addEventListener("click",async()=>{
    try{await api("auth/logout",{method:"POST",body:"{}",csrf:true});}catch(_){}
    currentUser=null;csrfToken="";showLogin();
  });
  init();
})();
