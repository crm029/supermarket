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
  async function loadTenants(){
    const box=$("platformTenantList");box.innerHTML="<p>در حال دریافت فهرست...</p>";
    try{
      const result=await api("platform/tenants");
      const tenants=result.data||[];
      $("platformTenantCount").textContent=new Intl.NumberFormat("fa-IR").format(tenants.length)+" فروشگاه";
      if(!tenants.length){box.innerHTML='<div class="platform-empty">هنوز فروشگاهی ثبت نشده است.</div>';return;}
      box.innerHTML=tenants.map(t=>{
        const domain=(t.domains||[]).map(escapeText).join("، ")||"دامنه ثبت نشده";
        const stateClass=t.status==="pending"?"pending":(t.status==="suspended"?"suspended":"");
        const verify= t.status==="pending"&&Number(t.verified_domain_count)===0
          ?'<div class="platform-verify"><label>یادداشت بررسی مالکیت<input data-verification-note="'+Number(t.id)+'" placeholder="شرح بررسی DNS یا فایل تأیید، حداقل ۱۲ حرف"></label><label>روش بررسی<select data-verification-method="'+Number(t.id)+'"><option value="dns_txt">رکورد DNS TXT بررسی شد</option><option value="http_file">فایل تأیید بررسی شد</option><option value="manual">بررسی دستی</option></select></label><button class="primary-button" data-verify-tenant="'+Number(t.id)+'">تأیید دامنه و فعال‌سازی</button></div>':"";
        const suspend=t.status==="active"?'<button class="danger" data-suspend-tenant="'+Number(t.id)+'">تعلیق فروشگاه</button>':"";
        return '<article class="platform-tenant"><div class="platform-tenant-head"><div><h3>'+escapeText(t.name)+' · '+escapeText(t.slug)+'</h3><p dir="ltr">'+domain+'</p><p>کاربران: '+new Intl.NumberFormat("fa-IR").format(Number(t.user_count)||0)+' · سفارش‌ها: '+new Intl.NumberFormat("fa-IR").format(Number(t.order_count)||0)+' · منطقه زمانی: '+escapeText(t.timezone)+'</p></div><span class="platform-status '+stateClass+'">'+statusLabel(t.status)+'</span></div>'+verify+suspend+'</article>';
      }).join("");
    }catch(error){box.innerHTML='<div class="platform-empty">'+escapeText(error.message)+'</div>';}
  }
  async function init(){
    try{
      const me=await api("me");currentUser=me.data;
      const csrf=await api("auth/csrf");csrfToken=csrf.data.csrf_token;
      showDashboard();await loadTenants();
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
      currentUser=result.data.user;csrfToken=result.data.csrf_token;showDashboard();await loadTenants();
    }catch(error){message("platformLoginMessage",error.message,true);}
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
      $("platformCreateForm").reset();await loadTenants();
    }catch(error){message("platformMessage",error.message,true);}
  });
  $("platformTenantList").addEventListener("click",async event=>{
    const verify=event.target.closest("[data-verify-tenant]");
    const suspend=event.target.closest("[data-suspend-tenant]");
    try{
      if(verify){
        const id=Number(verify.dataset.verifyTenant);
        const note=document.querySelector('[data-verification-note="'+id+'"]').value.trim();
        const method=document.querySelector('[data-verification-method="'+id+'"]').value;
        const result=await api("platform/tenants/"+id+"/verify-domain",{method:"POST",body:JSON.stringify({ownership_confirmed:true,verification_method:method,verification_note:note}),csrf:true});
        message("platformMessage",result.message);await loadTenants();
      }else if(suspend){
        const id=Number(suspend.dataset.suspendTenant);
        const reason=window.prompt("دلیل تعلیق فروشگاه را وارد کنید:");
        if(!reason)return;
        const result=await api("platform/tenants/"+id+"/suspend",{method:"POST",body:JSON.stringify({reason}),csrf:true});
        message("platformMessage",result.message);await loadTenants();
      }
    }catch(error){message("platformMessage",error.message,true);}
  });
  $("platformReload").addEventListener("click",loadTenants);
  $("platformLogout").addEventListener("click",async()=>{
    try{await api("auth/logout",{method:"POST",body:"{}",csrf:true});}catch(_){}
    currentUser=null;csrfToken="";showLogin();
  });
  init();
})();
