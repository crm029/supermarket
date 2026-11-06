"use strict";
const refundApiBase=new URL("api/v1/",document.baseURI).toString();
let refundCsrf="";
let refundShift=null;
let refundSale=null;
let refundItems=[];
function refundEscape(v){return String(v??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));}
function refundMoney(rial){return new Intl.NumberFormat("fa-IR").format(Math.round(Number(rial||0)/10))+" تومان";}
function refundMessage(message,type=""){const el=document.getElementById("refundMessage");el.textContent=message;el.className="refund-message "+type;}
async function refundRequest(path,options={}){const headers={Accept:"application/json",...(options.body?{"Content-Type":"application/json"}:{}),...(options.csrf&&refundCsrf?{"X-CSRF-Token":refundCsrf}:{})};const response=await fetch(refundApiBase+path,{credentials:"same-origin",...options,headers});const data=await response.json().catch(()=>({}));if(!response.ok)throw new Error(data.error||"درخواست انجام نشد.");return data;}
async function refundInit(){
  try{
    const me=await refundRequest("me");const csrf=await refundRequest("auth/csrf");refundCsrf=csrf.data.csrf_token;
    const shift=await refundRequest("admin/pos/shift");refundShift=shift.data.shift;
    document.getElementById("refundShift").textContent=refundShift?"شیفت فعال: "+refundShift.warehouse_title+" · وجه مورد انتظار: "+refundMoney(refundShift.expected_cash_rial):"برای ثبت بازپرداخت ابتدا از صفحه صندوق یک شیفت باز کنید.";
    const result=await refundRequest("admin/pos/sales");refundRenderSales(result.data||[]);
  }catch(error){refundMessage(error.message+" — دسترسی pos.read و pos.refund.create لازم است.","error");document.getElementById("refundSales").innerHTML="<p class='refund-empty'>دسترسی کافی وجود ندارد.</p>";}
}
function refundRenderSales(rows){
  const target=document.getElementById("refundSales");
  target.innerHTML=rows.length?rows.map(s=>'<button class="refund-sale" type="button" data-sale="'+Number(s.id)+'" aria-pressed="false"><span><strong>'+refundEscape(s.sale_number)+'</strong><small>'+refundEscape(s.created_at)+'</small></span><b>'+refundMoney(s.total_rial)+'</b></button>').join(""):"<p class='refund-empty'>فروشی برای نمایش وجود ندارد.</p>";
  target.querySelectorAll("[data-sale]").forEach(b=>b.addEventListener("click",()=>refundLoadSale(Number(b.dataset.sale),b)));
}
async function refundLoadSale(id,button){
  try{
    const result=await refundRequest("admin/pos/sales/"+id);refundSale=result.data.sale;refundItems=result.data.items||[];
    document.querySelectorAll(".refund-sale").forEach(b=>b.setAttribute("aria-pressed",b===button?"true":"false"));
    refundRenderDetail();document.getElementById("refundForm").hidden=false;
  }catch(error){refundMessage(error.message,"error");}
}
function refundRenderDetail(){
  const target=document.getElementById("refundDetail");
  target.innerHTML='<p><strong>فاکتور '+refundEscape(refundSale.sale_number)+'</strong> · مبلغ '+refundMoney(refundSale.total_rial)+'</p>'+
    refundItems.map(i=>'<div class="refund-line"><div><strong>'+refundEscape(i.title)+'</strong><div class="refund-hint">'+refundEscape(i.sku)+' · قیمت واحد '+refundMoney(i.unit_price_rial)+' · باقی‌مانده قابل بازپرداخت: '+Number(i.refundable_quantity)+'</div></div><input type="number" min="0" max="'+Number(i.refundable_quantity)+'" step="1" value="0" data-item="'+Number(i.sale_item_id)+'" data-price="'+Number(i.unit_price_rial)+'" aria-label="تعداد مرجوعی '+refundEscape(i.title)+'" '+(Number(i.refundable_quantity)<1?"disabled":"")+'></div>').join("");
  target.querySelectorAll("[data-item]").forEach(input=>input.addEventListener("input",refundUpdateTotal));
  refundUpdateTotal();
}
function refundUpdateTotal(){
  let total=0;document.querySelectorAll("[data-item]").forEach(input=>{const qty=Math.max(0,Math.min(Number(input.max),Math.floor(Number(input.value)||0)));if(qty!==Number(input.value))input.value=String(qty);total+=qty*Number(input.dataset.price);});
  document.getElementById("refundTotal").textContent=refundMoney(total);
  document.getElementById("refundSubmit").disabled=total<1||!refundShift;
}
document.getElementById("refundForm").addEventListener("submit",async e=>{
  e.preventDefault();if(!refundSale||!refundShift)return;
  const items=[];document.querySelectorAll("[data-item]").forEach(input=>{const quantity=Math.floor(Number(input.value)||0);if(quantity>0)items.push({sale_item_id:Number(input.dataset.item),quantity});});
  const reason=document.getElementById("refundReason").value.trim();
  if(!items.length||reason.length<12){refundMessage("حداقل یک قلم و دلیل حداقل ۱۲ نویسه لازم است.","error");return;}
  const button=document.getElementById("refundSubmit");button.disabled=true;
  try{
    const result=await refundRequest("admin/pos/sales/"+Number(refundSale.id)+"/refunds",{method:"POST",csrf:true,body:JSON.stringify({items,reason,idempotency_key:crypto.randomUUID()})});
    refundMessage("بازپرداخت "+refundMoney(result.data.amount_rial)+" ثبت شد. کالاها در انتظار بازرسی هستند و موجودی فروش تغییر نکرده است.","success");
    document.getElementById("refundReason").value="";
    await refundLoadSale(Number(refundSale.id),document.querySelector('[data-sale="'+Number(refundSale.id)+'"]'));
    const sales=await refundRequest("admin/pos/sales");refundRenderSales(sales.data||[]);
  }catch(error){refundMessage(error.message,"error");}
  finally{button.disabled=false;refundUpdateTotal();}
});
refundInit();
