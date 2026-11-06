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
      const statusLabels={paid:"پرداخت‌شده",accepted:"پذیرفته‌شده",picking:"در حال جمع‌آوری",packed:"بسته‌بندی‌شده",ready_pickup:"آماده تحویل",dispatched:"آماده ارسال",out_for_delivery:"در مسیر ارسال",delivered:"تحویل‌شده"};
      list.innerHTML=tasks.map(task=>{
        const picker=task.assignment_type==="picker";
        const pickup=task.assignment_type==="pickup";
        const title=picker?"جمع‌آوری اقلام سفارش":pickup?"تحویل حضوری سفارش":"ارسال و تحویل سفارش";
        const roleLabel=picker?"نیروی جمع‌آوری":pickup?"مسئول تحویل حضوری":"پیک";
        const items=(picker||pickup)?'<h3>فهرست اقلام</h3><ul>'+(task.items||[]).map(item=>'<li>'+escapeText(item.title)+' · '+new Intl.NumberFormat("fa-IR").format(Number(item.quantity)||0)+' عدد</li>').join("")+'</ul>':"";
        const address=pickup?[task.customer_name,task.customer_mobile].filter(Boolean).join(" · "):!picker?[task.recipient,task.recipient_mobile,task.customer_name,task.customer_mobile,task.province,task.city,task.delivery_address,task.postal_code].filter(Boolean).join(" · "):"";
        const contact=!picker?'<p><strong>'+(pickup?"مشتری: ":"مشخصات تحویل: ")+'</strong>'+escapeText(address||"اطلاعات ثبت نشده")+'</p>':"";
        let action="";
        if(picker){
          action=task.order_status==="picking"
            ?'<button class="primary-button task-complete" data-complete-task="'+Number(task.assignment_id)+'" data-assignment-type="picker" type="button">ثبت پایان جمع‌آوری</button>'
            :'<p class="staff-task-wait">پس از ورود سفارش به مرحله جمع‌آوری، امکان تکمیل وظیفه فعال می‌شود.</p>';
        }else if(pickup){
          action=task.order_status==="ready_pickup"
            ?'<div class="staff-delivery-proof"><label>نام تحویل‌گیرنده<input type="text" maxlength="160" data-recipient-name placeholder="نام شخص تحویل‌گیرنده"></label><label>یادداشت اختیاری<input type="text" maxlength="300" data-delivery-note placeholder="توضیح کوتاه در صورت نیاز"></label><button class="primary-button task-complete" data-complete-task="'+Number(task.assignment_id)+'" data-assignment-type="pickup" type="button">تأیید تحویل حضوری</button></div>'
            :'<p class="staff-task-wait">پس از آماده‌شدن سفارش برای دریافت حضوری، امکان ثبت تحویل فعال می‌شود.</p>';
        }else if(task.order_status==="dispatched"){
          action='<button class="primary-button task-start-delivery" data-start-delivery="'+Number(task.assignment_id)+'" type="button">شروع مسیر ارسال</button>';
        }else if(task.order_status==="out_for_delivery"){
          action='<div class="staff-delivery-proof"><label>نام تحویل‌گیرنده<input type="text" maxlength="160" data-recipient-name placeholder="نام شخصی که سفارش را تحویل می‌گیرد"></label><label>یادداشت اختیاری<input type="text" maxlength="300" data-delivery-note placeholder="توضیح کوتاه در صورت نیاز"></label><button class="primary-button task-complete" data-complete-task="'+Number(task.assignment_id)+'" data-assignment-type="courier" type="button">ثبت تحویل سفارش</button></div>';
        }else{
          action='<p class="staff-task-wait">سفارش هنوز آماده ارسال نیست. پس از بسته‌بندی و ثبت ارسال، دکمه شروع مسیر فعال می‌شود.</p>';
        }
        return '<article class="staff-task"><div class="staff-task-head"><div><span class="task-type">'+roleLabel+'</span><h2>'+title+' · '+escapeText(task.order_number)+'</h2></div><span class="admin-status">'+escapeText(statusLabels[task.order_status]||task.order_status)+'</span></div><p>ثبت سفارش: '+escapeText(task.created_at||"")+'</p><p>تعداد اقلام: '+new Intl.NumberFormat("fa-IR").format(Number(task.item_count)||0)+'</p>'+contact+items+action+'</article>';
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
    const start=event.target.closest("[data-start-delivery]");
    if(start){
      start.disabled=true;
      try{
        await api("me/tasks/"+Number(start.dataset.startDelivery)+"/start-delivery",{method:"POST",body:"{}",csrf:true});
        message("staffWorkspaceMessage","شروع مسیر ارسال ثبت شد.");
        await loadTasks();
      }catch(error){start.disabled=false;message("staffWorkspaceMessage",error.message,true);}
      return;
    }
    const button=event.target.closest("[data-complete-task]");
    if(!button)return;
    const type=button.dataset.assignmentType||"picker";
    const card=button.closest(".staff-task");
    let body={};
    if(type==="courier"||type==="pickup"){
      const recipientName=card.querySelector("[data-recipient-name]")?.value.trim()||"";
      const note=card.querySelector("[data-delivery-note]")?.value.trim()||"";
      if(recipientName.length<2){message("staffWorkspaceMessage","نام تحویل‌گیرنده را وارد کنید.",true);return;}
      body={recipient_name:recipientName,note};
    }
    button.disabled=true;
    try{
      await api("me/tasks/"+Number(button.dataset.completeTask)+"/complete",{method:"POST",body:JSON.stringify(body),csrf:true});
      message("staffWorkspaceMessage",type==="courier"?"تحویل سفارش ثبت شد.":"پایان جمع‌آوری ثبت شد.");
      await loadTasks();
    }catch(error){button.disabled=false;message("staffWorkspaceMessage",error.message,true);}
  });
  init();
})();
