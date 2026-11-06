"use strict";
const posApiBase=new URL("api/v1/",document.baseURI).toString();
let posCsrf="";
let posShift=null;
let posCart=[];
let posWarehouses=[];

async function posRequest(path,options={}){
  const headers={Accept:"application/json",...(options.body?{"Content-Type":"application/json"}:{}),...(options.csrf&&posCsrf?{"X-CSRF-Token":posCsrf}:{})};
  const response=await fetch(posApiBase+path,{credentials:"same-origin",...options,headers});
  const payload=await response.json().catch(()=>({}));
  if(!response.ok)throw new Error(payload.error||"درخواست انجام نشد.");
  return payload;
}
function posMoney(rial){return new Intl.NumberFormat("fa-IR").format(Math.round(Number(rial||0)/10))+" تومان";}
function posMessage(value,type=""){const n=document.getElementById("posMessage");n.textContent=value;n.className="pos-message "+type;}
function posEscape(value){return String(value??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));}
async function posInit(){
  try{
    const me=await posRequest("me");
    const csrf=await posRequest("auth/csrf");posCsrf=csrf.data.csrf_token;
    document.getElementById("posIdentity").textContent=me.data.full_name+" · "+me.data.mobile;
    const warehouses=await posRequest("admin/pos/warehouses");posWarehouses=warehouses.data||[];
    const select=document.getElementById("posWarehouse");
    select.innerHTML=posWarehouses.map(w=>'<option value="'+Number(w.id)+'">'+posEscape(w.title)+'</option>').join("");
    await posLoadShift();
  }catch(error){
    posMessage(error.message+" — برای ورود ابتدا پنل مدیریت را باز کنید.","error");
    document.getElementById("posLoginLink").hidden=false;
  }
}
async function posLoadShift(){
  const result=await posRequest("admin/pos/shift");posShift=result.data.shift;
  document.getElementById("posOpenShift").hidden=!!posShift;
  document.getElementById("posActiveShift").hidden=!posShift;
  document.getElementById("posSaleArea").hidden=!posShift;
  if(posShift){
    document.getElementById("posShiftWarehouse").textContent=posShift.warehouse_title;
    document.getElementById("posShiftOpening").textContent=posMoney(posShift.opening_float_rial);
    document.getElementById("posShiftExpected").textContent=posMoney(posShift.expected_cash_rial);
    document.getElementById("posShiftSales").textContent=new Intl.NumberFormat("fa-IR").format(posShift.summary.sale_count);
  }
}
document.getElementById("posOpenForm").addEventListener("submit",async e=>{
  e.preventDefault();
  try{
    const warehouseId=Number(document.getElementById("posWarehouse").value);
    const opening=Math.round(Number(document.getElementById("posOpeningFloat").value||0)*10);
    await posRequest("admin/pos/shifts",{method:"POST",csrf:true,body:JSON.stringify({warehouse_id:warehouseId,opening_float_rial:opening,note:document.getElementById("posOpeningNote").value})});
    posMessage("شیفت صندوق باز شد.","success");await posLoadShift();
  }catch(error){posMessage(error.message,"error");}
});
document.getElementById("posCloseForm").addEventListener("submit",async e=>{
  e.preventDefault();if(!posShift)return;
  if(!confirm("از بستن شیفت و ثبت شمارش وجه مطمئن هستید؟"))return;
  try{
    const counted=Math.round(Number(document.getElementById("posCountedCash").value||0)*10);
    const result=await posRequest("admin/pos/shifts/"+Number(posShift.id)+"/close",{method:"POST",csrf:true,body:JSON.stringify({counted_cash_rial:counted,note:document.getElementById("posClosingNote").value})});
    const v=result.data.cash_variance_rial;
    posMessage("شیفت بسته شد. "+(v===0?"صندوق بدون مغایرت است.":(v>0?"اضافه صندوق: ":"کسری صندوق: ")+posMoney(Math.abs(v))),v===0?"success":"warning");
    posCart=[];posRenderCart();await posLoadShift();
  }catch(error){posMessage(error.message,"error");}
});
async function posSearch(){
  const q=document.getElementById("posSearch").value.trim();if(!q)return;
  try{
    const result=await posRequest("admin/pos/products?q="+encodeURIComponent(q));
    const rows=result.data||[];const target=document.getElementById("posSearchResults");
    target.innerHTML=rows.length?rows.map(p=>'<button class="pos-result" type="button" data-id="'+Number(p.id)+'"><span><strong>'+posEscape(p.title)+'</strong><small>'+posEscape(p.sku||"")+" · "+posEscape(p.barcode||"بدون بارکد")+'</small></span><b>'+posMoney(p.price_rial)+'</b></button>').join(""):"<p>کالایی پیدا نشد.</p>";
    target.querySelectorAll("[data-id]").forEach(button=>button.addEventListener("click",()=>posAddProduct(rows.find(p=>Number(p.id)===Number(button.dataset.id)))));
  }catch(error){posMessage(error.message,"error");}
}
document.getElementById("posSearchButton").addEventListener("click",posSearch);
document.getElementById("posSearch").addEventListener("keydown",e=>{if(e.key==="Enter"){e.preventDefault();posSearch();}});
function posAddProduct(product){
  if(!product)return;const found=posCart.find(x=>x.product_id===Number(product.id));
  if(found)found.quantity=Math.min(99,found.quantity+1);
  else posCart.push({product_id:Number(product.id),title:product.title,sku:product.sku,unit_price_rial:Number(product.price_rial),quantity:1});
  posRenderCart();
}
function posRenderCart(){
  const body=document.getElementById("posCartRows");
  body.innerHTML=posCart.length?posCart.map((p,i)=>'<div class="pos-cart-row"><div><strong>'+posEscape(p.title)+'</strong><small>'+posMoney(p.unit_price_rial)+' × '+new Intl.NumberFormat("fa-IR").format(p.quantity)+'</small></div><div class="pos-qty"><button type="button" data-minus="'+i+'" aria-label="کم کردن">−</button><b>'+new Intl.NumberFormat("fa-IR").format(p.quantity)+'</b><button type="button" data-plus="'+i+'" aria-label="زیاد کردن">+</button><button type="button" data-remove="'+i+'" aria-label="حذف">×</button></div></div>').join(""):"<p class='pos-empty'>هنوز کالایی به فاکتور اضافه نشده است.</p>";
  body.querySelectorAll("[data-minus]").forEach(b=>b.addEventListener("click",()=>{const p=posCart[Number(b.dataset.minus)];p.quantity--;if(p.quantity<1)posCart.splice(Number(b.dataset.minus),1);posRenderCart();}));
  body.querySelectorAll("[data-plus]").forEach(b=>b.addEventListener("click",()=>{const p=posCart[Number(b.dataset.plus)];p.quantity=Math.min(99,p.quantity+1);posRenderCart();}));
  body.querySelectorAll("[data-remove]").forEach(b=>b.addEventListener("click",()=>{posCart.splice(Number(b.dataset.remove),1);posRenderCart();}));
  const total=posCart.reduce((s,p)=>s+p.unit_price_rial*p.quantity,0);
  document.getElementById("posTotal").textContent=posMoney(total);
  const received=Number(document.getElementById("posCashReceived").value||0)*10;
  document.getElementById("posChange").textContent=received>=total?posMoney(received-total):"—";
}
document.getElementById("posCashReceived").addEventListener("input",posRenderCart);
document.getElementById("posSaleForm").addEventListener("submit",async e=>{
  e.preventDefault();if(!posCart.length){posMessage("سبد فروش خالی است.","error");return;}
  const total=posCart.reduce((s,p)=>s+p.unit_price_rial*p.quantity,0);
  const received=Math.round(Number(document.getElementById("posCashReceived").value||0)*10);
  if(received<total){posMessage("وجه دریافتی کمتر از مبلغ فاکتور است.","error");return;}
  const button=document.getElementById("posCompleteSale");button.disabled=true;
  try{
    const result=await posRequest("admin/pos/sales",{method:"POST",csrf:true,body:JSON.stringify({items:posCart.map(p=>({product_id:p.product_id,quantity:p.quantity})),cash_received_rial:received,idempotency_key:crypto.randomUUID(),note:document.getElementById("posSaleNote").value})});
    posMessage("فروش "+result.data.sale_number+" ثبت شد. باقی‌پول: "+posMoney(result.data.change_rial),"success");
    posCart=[];document.getElementById("posCashReceived").value="";document.getElementById("posSaleNote").value="";posRenderCart();await posLoadShift();
  }catch(error){posMessage(error.message,"error");}
  finally{button.disabled=false;}
});
posInit();
