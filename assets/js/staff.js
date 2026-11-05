(()=> {
  "use strict";
  const $=id=>document.getElementById(id);
  let csrfToken="";
  let currentUser=null;
  async function api(path,options={}){
    const headers={"Accept":"application/json",...(options.headers||{})};
    if(options.body)headers["Content-Type"]="application/json";
    if(options.csrf)headers["X-CSRF-Token"]=csrfToken;
    const response=await fetch("api/v1/"+path,{...options,headers,credentials:"same-origin"});
    let payload={};
    try{payload=await response.json();}catch(_){}
    if(!response.ok)throw new Error(payload.error||"ارتباط با سرور انجام نشد.");
    return payload;
  }
  function escapeText(value){
    return String(value??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  }
  function message(target,text,isError=false){
    $(target).textContent=text;
    $(target).style.color=isError?"#b42318":"#52665a";
  }
  function showWorkspace(){
    $("staffLogin").hidden=true;
    $("staffWorkspace").hidden=false;
    $("staffIdentity").textContent=currentUser?currentUser.full_name+" · "+currentUser.mobile:"";
  }
  function showLogin(){
    $("staffWorkspace").hidden=true;
    $("staffLogin").hidden=false;
  }
  async function loadTasks(){
    const list=$("staffTaskList");
    list.innerHTML="<p>در حال دریافت وظایف...</p>";
    try{
      const result=await api("me/tasks");
      const tasks=result.data||[];
      $("staffTaskCount").textContent=new Intl.NumberFormat("fa-IR").format(tasks.length);
      if(!tasks.length){list.innerHTML='<div class="staff-empty">در حال حاضر وظیفه فعالی به شما واگذار نشده است.</div>';return;}
      list.innerHTML=tasks.map(task=>{
        const picker=task.assignment_type==="picker";
        const title=picker?"جمع‌آوری اقلام سفارش":"ارسال و تحویل سفارش";
        const items=picker?'<h3>فهرست اقلام</h3><ul>'+(task.items||[]).map(item=>'<li>'+escapeText(item.title)+' · '+new Intl.NumberFormat("fa-IR").format(Number(item.quantity)||0)+' عدد</li>').join("")+'</ul>':'';
        const address=!picker?[task.recipient,task.recipient_mobile,task.customer_name,task.customer_mobile,task.province,task.city,task.delivery_address,task.postal_code].filter(Boolean).join(" · "):"";
        const contact=!picker?'<p><strong>مشخصات تحویل:</strong> '+escapeText(address||"نشانی ثبت نشده")+'</p>':"";
        return '<article class="staff-task"><div class="staff-task-head"><div><span class="task-type">'+(picker?"نیروی جمع‌آوری":"پیک")+'</span><h2>'+title+' · '+escapeText(task.order_number)+'</h2></div><span class="admin-status">'+escapeText(task.order_status)+'</span></div><p>ثبت سفارش: '+escapeText(task.created_at||"")+'</p><p>تعداد اقلام: '+new Intl.NumberFormat("fa-IR").format(Number(task.item_count)||0)+'</p>'+contact+items+'<button class="primary-button task-complete" data-complete-task="'+Number(task.assignment_id)+'" type="button">ثبت انجام وظیفه</button></article>';
      }).join("");
    }catch(error){list.innerHTML='<div class="staff-empty">'+escapeText(error.message)+'</div>';}
  }
  async function init(){
    try{
      const me=await api("me");
      currentUser=me.data;
      const csrf=await api("auth/csrf");
      csrfToken=csrf.data.csrf_token;
      showWorkspace();
      await loadTasks();
    }catch(_){showLogin();}
  }
  $("staffRequestOtp").addEventListener("click",async()=>{
    const mobile=$("staffMobile").value.trim();
    try{
      await api("auth/request-otp",{method:"POST",body:JSON.stringify({mobile})});
      $("staffOtpArea").hidden=false;
      message("staffLoginMessage","اگر شماره متعلق به حساب فعال باشد، کد ورود ارسال می‌شود.");
    }catch(error){message("staffLoginMessage",error.message,true);}
  });
  $("staffVerifyOtp").addEventListener("click",async()=>{
    try{
      const result=await api("auth/verify-otp",{method:"POST",body:JSON.stringify({mobile:$("staffMobile").value.trim(),code:$("staffOtpCode").value.trim()})});
      currentUser=result.data.user;
      csrfToken=result.data.csrf_token;
      showWorkspace();
      await loadTasks();
    }catch(error){message("staffLoginMessage",error.message,true);}
  });
  $("staffReload").addEventListener("click",loadTasks);
  $("staffLogout").addEventListener("click",async()=>{
    try{await api("auth/logout",{method:"POST",body:"{}",csrf:true});}catch(_){}
    currentUser=null;csrfToken="";showLogin();
  });
  $("staffTaskList").addEventListener("click",async event=>{
    const button=event.target.closest("[data-complete-task]");
    if(!button)return;
    button.disabled=true;
    try{
      await api("me/tasks/"+Number(button.dataset.completeTask)+"/complete",{method:"POST",body:"{}",csrf:true});
      message("staffWorkspaceMessage","انجام وظیفه ثبت شد.");
      await loadTasks();
    }catch(error){button.disabled=false;message("staffWorkspaceMessage",error.message,true);}
  });
  init();
})();
