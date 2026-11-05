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
  function showInbox(){$("notificationsLogin").hidden=true;$("notificationsInbox").hidden=false;$("notificationsIdentity").textContent=currentUser?currentUser.full_name+" · "+currentUser.mobile:"";}
  function showLogin(){$("notificationsInbox").hidden=true;$("notificationsLogin").hidden=false;}
  async function loadNotifications(){
    const box=$("notificationsList");box.innerHTML="<p>در حال دریافت اعلان‌ها...</p>";
    try{
      const result=await api("me/notifications");
      const rows=result.data||[];
      $("notificationsUnreadCount").textContent=new Intl.NumberFormat("fa-IR").format(Number(result.unread_count)||0);
      if(!rows.length){box.innerHTML='<div class="notification-empty">اعلانی برای نمایش وجود ندارد.</div>';return;}
      box.innerHTML=rows.map(row=>{
        const unread=!row.read_at;
        return '<article class="notification-card '+(unread?"unread":"")+'"><div><h2>'+escapeText(row.title)+'</h2><p>'+escapeText(row.body)+'</p><small>'+escapeText(row.created_at||"")+'</small></div>'+(unread?'<button class="secondary-button" data-read-notification="'+Number(row.id)+'" type="button">علامت‌گذاری به‌عنوان خوانده‌شده</button>':'<span class="notification-read-label">خوانده‌شده</span>')+'</article>';
      }).join("");
    }catch(error){box.innerHTML='<div class="notification-empty">'+escapeText(error.message)+'</div>';}
  }
  async function init(){
    try{
      const me=await api("me");currentUser=me.data;
      const csrf=await api("auth/csrf");csrfToken=csrf.data.csrf_token;
      showInbox();await loadNotifications();
    }catch(_){showLogin();}
  }
  $("notificationsRequestOtp").addEventListener("click",async()=>{
    try{
      await api("auth/request-otp",{method:"POST",body:JSON.stringify({mobile:$("notificationsMobile").value.trim()})});
      $("notificationsOtpArea").hidden=false;
      message("notificationsLoginMessage","اگر حساب فعال باشد، کد ورود ارسال می‌شود.");
    }catch(error){message("notificationsLoginMessage",error.message,true);}
  });
  $("notificationsVerifyOtp").addEventListener("click",async()=>{
    try{
      const result=await api("auth/verify-otp",{method:"POST",body:JSON.stringify({mobile:$("notificationsMobile").value.trim(),code:$("notificationsOtpCode").value.trim()})});
      currentUser=result.data.user;csrfToken=result.data.csrf_token;showInbox();await loadNotifications();
    }catch(error){message("notificationsLoginMessage",error.message,true);}
  });
  $("notificationsReload").addEventListener("click",loadNotifications);
  $("notificationsReadAll").addEventListener("click",async()=>{
    try{const result=await api("me/notifications/read-all",{method:"POST",body:"{}",csrf:true});message("notificationsMessage",result.message);await loadNotifications();}
    catch(error){message("notificationsMessage",error.message,true);}
  });
  $("notificationsLogout").addEventListener("click",async()=>{
    try{await api("auth/logout",{method:"POST",body:"{}",csrf:true});}catch(_){}
    currentUser=null;csrfToken="";showLogin();
  });
  $("notificationsList").addEventListener("click",async event=>{
    const button=event.target.closest("[data-read-notification]");
    if(!button)return;
    button.disabled=true;
    try{await api("me/notifications/"+Number(button.dataset.readNotification)+"/read",{method:"POST",body:"{}",csrf:true});await loadNotifications();}
    catch(error){button.disabled=false;message("notificationsMessage",error.message,true);}
  });
  init();
})();
