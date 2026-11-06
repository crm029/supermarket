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
  async function init(){
    try{
      const me=await api("me");currentUser=me.data;
      const csrf=await api("auth/csrf");csrfToken=csrf.data.csrf_token;
      showDashboard();await Promise.all([loadTenants(),loadPaymentSettings()]);
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
      currentUser=result.data.user;csrfToken=result.data.csrf_token;showDashboard();await Promise.all([loadTenants(),loadPaymentSettings()]);
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
  $("platformReload").addEventListener("click",loadTenants);
  $("platformLogout").addEventListener("click",async()=>{
    try{await api("auth/logout",{method:"POST",body:"{}",csrf:true});}catch(_){}
    currentUser=null;csrfToken="";showLogin();
  });
  init();
})();
