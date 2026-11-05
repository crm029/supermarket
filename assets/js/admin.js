"use strict";
const apiBase = new URL("api/v1/", document.baseURI).toString();
let csrfToken = "";
let products = [];
let categories = [];
let orders = [];
let warehouses = [];
let stockRows = [];
let scenes = [];
let hotspots = [];
let activePanel = "overview";

async function request(path, options = {}) {
  const headers = { Accept: "application/json", ...(options.body ? {"Content-Type":"application/json"} : {}), ...(options.csrf && csrfToken ? {"X-CSRF-Token":csrfToken} : {}) };
  const response = await fetch(apiBase + path, { credentials:"same-origin", ...options, headers });
  const payload = await response.json().catch(()=>({}));
  if (!response.ok) throw new Error(payload.error || "درخواست انجام نشد.");
  return payload;
}
function message(value,type="") {
  const node=document.getElementById("adminMessage");
  node.textContent=value;node.className="form-message"+(type?" "+type:"");
}
function loginMessage(value,type="") {
  const node=document.getElementById("adminLoginMessage");
  node.textContent=value;node.className="form-message"+(type?" "+type:"");
}
function showPanel(panel) {
  activePanel=panel;
  document.querySelectorAll(".admin-nav").forEach(b=>b.classList.toggle("active",b.dataset.panel===panel));
  document.querySelectorAll(".admin-panel").forEach(p=>p.classList.toggle("active",p.id==="panel-"+panel));
}
document.querySelectorAll(".admin-nav").forEach(b=>b.addEventListener("click",()=>showPanel(b.dataset.panel)));
async function loadSession() {
  try {
    const me=await request("me");
    const csrf=await request("auth/csrf");
    csrfToken=csrf.data.csrf_token;
    document.getElementById("adminIdentity").textContent=me.data.full_name+" · "+me.data.mobile;
    document.getElementById("adminLoginDialog").close();
    await loadAll();
  } catch(error) {
    document.getElementById("adminLoginDialog").showModal();
    loginMessage("برای ورود به پنل، حساب مدیر فروشگاه را تأیید کنید. "+error.message);
  }
}
async function loadAll() {
  try {
    const [p,c]=await Promise.all([request("admin/products"),request("admin/categories")]);
    products=p.data||[];categories=c.data||[];
    renderProducts();renderCategories();
    await loadOrders();
    await loadInventory();
    await loadScenes();
    document.getElementById("statProducts").textContent=new Intl.NumberFormat("fa-IR").format(products.filter(x=>Number(x.is_active)).length);
    document.getElementById("statCategories").textContent=new Intl.NumberFormat("fa-IR").format(categories.filter(x=>Number(x.is_active)).length);
    document.getElementById("statConnection").textContent="متصل";
    document.getElementById("statConnection").className="status-good";
    message("اطلاعات فروشگاه با موفقیت دریافت شد.","success");
  } catch(error) {
    document.getElementById("statConnection").textContent="محدود";
    message(error.message,"error");
  }
}
async function loadScenes() {
  try {
    const result=await request("admin/scenes");
    scenes=result.data||[];
    renderScenes();
    const sceneOptions=scenes.map(s=>'<option value="'+Number(s.id)+'">'+escapeText(s.title)+'</option>').join("");
    document.getElementById("hotspotScene").innerHTML=sceneOptions;
    document.getElementById("hotspotTarget").innerHTML=scenes.filter(s=>s.is_published).map(s=>'<option value="'+Number(s.id)+'">'+escapeText(s.title)+'</option>').join("");
    document.getElementById("hotspotProduct").innerHTML=products.filter(p=>Number(p.is_active)).map(p=>'<option value="'+Number(p.id)+'">'+escapeText(p.title)+'</option>').join("");
    document.getElementById("hotspotCategory").innerHTML=categories.filter(c=>Number(c.is_active)).map(c=>'<option value="'+Number(c.id)+'">'+escapeText(c.title)+'</option>').join("");
    if(scenes.length){document.getElementById("hotspotScene").value=String(scenes[0].id);await loadHotspots(scenes[0].id);}else{document.getElementById("hotspotRows").innerHTML="";renderScenePreview(null);}
  } catch(error) {
    document.getElementById("sceneRows").textContent=error.message;
  }
}
function renderScenes() {
  document.getElementById("sceneRows").innerHTML=scenes.map(s=>'<article><div><strong>'+escapeText(s.title)+'</strong><small>'+escapeText(s.scene_type)+' · '+Number(s.hotspot_count)+' نقطه · رسانه '+(s.media_id||"ندارد")+'</small></div><span class="admin-status '+(s.is_published?"active":"inactive")+'">'+(s.is_published?"منتشرشده":"پیش‌نویس")+'</span><button class="admin-row-action" data-select-scene="'+Number(s.id)+'">انتخاب صحنه</button><button class="admin-row-action" data-publish-scene="'+Number(s.id)+'" data-published="'+(s.is_published?"1":"0")+'">'+(s.is_published?"خروج از انتشار":"انتشار")+'</button></article>').join("")||'<p>هنوز صحنه‌ای ساخته نشده است. ابتدا تصویر راهرو را بارگذاری کنید.</p>';
}
function renderScenePreview(scene) {
  const preview=document.getElementById("scenePreview");
  if(!scene||!scene.media?.url){preview.innerHTML='<p>این صحنه تصویر ندارد. ابتدا تصویر بارگذاری و به صحنه متصل کنید.</p>';return;}
  preview.innerHTML='<img src="'+escapeText(scene.media.url)+'" alt="'+escapeText(scene.title)+'">'+(hotspots||[]).map(h=>'<span class="scene-preview-hotspot" style="left:'+(Number(h.x)*100)+'%;top:'+(Number(h.y)*100)+'%">'+escapeText(h.label)+'</span>').join("");
}
async function loadHotspots(sceneId) {
  try {
    const result=await request("admin/scenes/"+sceneId+"/hotspots");
    hotspots=result.data||[];
    renderScenePreview(scenes.find(s=>Number(s.id)===Number(sceneId))||null);
    document.getElementById("hotspotRows").innerHTML=hotspots.map(h=>'<article><div><strong>'+escapeText(h.label)+'</strong><small>'+escapeText(h.action)+' · X '+Number(h.x).toFixed(2)+' / Y '+Number(h.y).toFixed(2)+'</small></div><button class="admin-row-action danger" data-delete-hotspot="'+Number(h.id)+'">حذف نقطه</button></article>').join("")||'<p>برای این صحنه نقطه تعاملی ثبت نشده است.</p>';
  } catch(error) { document.getElementById("hotspotRows").textContent=error.message; }
}
function updateHotspotFields() {
  const action=document.getElementById("hotspotAction").value;
  document.getElementById("hotspotProductField").hidden=action!=="product";
  document.getElementById("hotspotCategoryField").hidden=action!=="category";
  document.getElementById("hotspotTargetField").hidden=action!=="navigate";
}
async function loadInventory() {
  try {
    const [w,s]=await Promise.all([request("admin/warehouses"),request("admin/stock")]);
    warehouses=w.data||[];stockRows=s.data||[];
    renderInventory();
  } catch(error) {
    document.getElementById("stockRows").innerHTML='<tr><td colspan="5">'+escapeText(error.message)+'</td></tr>';
  }
}
function renderInventory() {
  const warehouseOptions=warehouses.filter(w=>Number(w.is_active)).map(w=>'<option value="'+Number(w.id)+'">'+escapeText(w.title)+'</option>').join("");
  ["receiptWarehouse","adjustWarehouse","transferFrom","transferTo"].forEach(id=>{
    const select=document.getElementById(id);const previous=select.value;
    select.innerHTML=warehouseOptions; if(previous)select.value=previous;
  });
  const productOptions=products.filter(p=>Number(p.is_active)).map(p=>'<option value="'+Number(p.id)+'">'+escapeText(p.title)+' · '+escapeText(p.sku)+'</option>').join("");
  ["receiptProduct","adjustProduct","transferProduct"].forEach(id=>{
    const select=document.getElementById(id);const previous=select.value;
    select.innerHTML=productOptions;if(previous)select.value=previous;
  });
  document.getElementById("stockRows").innerHTML=stockRows.map(s=>'<tr><td><strong>'+escapeText(s.product_title)+'</strong><small>'+escapeText(s.sku)+'</small></td><td>'+escapeText(s.warehouse_title)+'</td><td>'+new Intl.NumberFormat("fa-IR").format(Number(s.on_hand))+'</td><td>'+new Intl.NumberFormat("fa-IR").format(Number(s.reserved))+'</td><td><strong>'+new Intl.NumberFormat("fa-IR").format(Number(s.available_quantity))+'</strong></td></tr>').join("")||'<tr><td colspan="5">هنوز موجودی ثبت نشده است.</td></tr>';
}
async function loadOrders() {
  try {
    const result=await request("admin/orders");
    orders=result.data||[];
    renderOrders();
  } catch(error) {
    orders=[];
    document.getElementById("orderRows").innerHTML='<tr><td colspan="6">'+escapeText(error.message)+'</td></tr>';
  }
}
function renderOrders() {
  const labels={pending_payment:"در انتظار پرداخت",paid:"پرداخت‌شده",accepted:"پذیرفته‌شده",picking:"در حال جمع‌آوری",packed:"بسته‌بندی‌شده",ready_pickup:"آماده تحویل",dispatched:"ارسال‌شده",delivered:"تحویل‌شده",cancelled:"لغوشده",refunded:"مستردشده"};
  const next={paid:"accepted",accepted:"picking",picking:"packed",packed:"dispatched",ready_pickup:"delivered",dispatched:"delivered"};
  document.getElementById("orderRows").innerHTML=orders.map(o=>{
    let nextStatus=next[o.status];
    if(o.status==="packed"&&o.fulfillment==="pickup")nextStatus="ready_pickup";
    const action='<button class="admin-row-action" data-admin-order-detail="'+Number(o.id)+'">جزئیات</button>'+(nextStatus?'<button class="admin-row-action" data-next-order="'+Number(o.id)+'" data-next-status="'+nextStatus+'">مرحله بعد</button>':"");
    return '<tr><td><strong>'+escapeText(o.order_number)+'</strong><small>'+escapeText(o.created_at||"")+'</small></td><td>'+escapeText(o.customer_name)+'<small>'+escapeText(o.customer_mobile)+'</small></td><td>'+new Intl.NumberFormat("fa-IR").format(Number(o.item_count)||0)+'</td><td>'+money(Number(o.total_rial)/10)+'</td><td><span class="admin-status '+(o.status==="delivered"?"active":"inactive")+'">'+(labels[o.status]||escapeText(o.status))+'</span></td><td>'+action+'</td></tr>';
  }).join("")||'<tr><td colspan="6">هنوز سفارشی ثبت نشده است.</td></tr>';
}
function renderProducts() {
  const query=document.getElementById("productSearch").value.trim().toLocaleLowerCase();
  const rows=products.filter(p=>!query||String(p.title+" "+p.sku).toLocaleLowerCase().includes(query));
  document.getElementById("productRows").innerHTML=rows.map(p=>
    '<tr><td><strong>'+escapeText(p.title)+'</strong><small>'+escapeText(p.brand||"")+'</small></td><td>'+escapeText(p.sku)+'</td><td>'+money(Number(p.price_rial)/10)+'</td><td><span class="admin-status '+(Number(p.is_active)?"active":"inactive")+'">'+(Number(p.is_active)?"فعال":"غیرفعال")+'</span></td><td><button class="admin-row-action" data-edit-product="'+Number(p.id)+'">ویرایش</button>'+(Number(p.is_active)?'<button class="admin-row-action danger" data-disable-product="'+Number(p.id)+'">غیرفعال‌سازی</button>':"")+'</td></tr>'
  ).join("")||'<tr><td colspan="5">محصولی پیدا نشد.</td></tr>';
}
function renderCategories() {
  document.getElementById("productCategory").innerHTML='<option value="">بدون دسته‌بندی</option>'+categories.filter(c=>Number(c.is_active)).map(c=>'<option value="'+Number(c.id)+'">'+escapeText(c.title)+'</option>').join("");
  document.getElementById("categoryRows").innerHTML=categories.map(c=>'<article><div><strong>'+escapeText(c.title)+'</strong><small>'+escapeText(c.slug)+'</small></div><span class="admin-status '+(Number(c.is_active)?"active":"inactive")+'">'+(Number(c.is_active)?"فعال":"غیرفعال")+'</span>'+(Number(c.is_active)?'<button class="admin-row-action danger" data-disable-category="'+Number(c.id)+'">غیرفعال‌سازی</button>':"")+'</article>').join("")||'<p>هنوز دسته‌بندی ثبت نشده است.</p>';
}
function escapeText(value) {
  return String(value??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
}
function money(value) {return new Intl.NumberFormat("fa-IR").format(Math.round(value||0))+" تومان";}
function resetProductForm() {
  document.getElementById("productForm").reset();
  document.getElementById("productId").value="";
  document.getElementById("productFormTitle").textContent="افزودن محصول";
  document.getElementById("productForm").hidden=true;
}
document.getElementById("newProductButton").addEventListener("click",()=>{
  resetProductForm();document.getElementById("productForm").hidden=false;document.getElementById("productForm").scrollIntoView({behavior:"smooth"});
});
document.getElementById("cancelProductForm").addEventListener("click",resetProductForm);
document.getElementById("productSearch").addEventListener("input",renderProducts);
document.getElementById("reloadProducts").addEventListener("click",loadAll);
document.getElementById("productRows").addEventListener("click",async e=>{
  const edit=e.target.closest("[data-edit-product]");
  const disable=e.target.closest("[data-disable-product]");
  if(edit){
    const p=products.find(x=>Number(x.id)===Number(edit.dataset.editProduct));if(!p)return;
    document.getElementById("productId").value=p.id;
    document.getElementById("productSku").value=p.sku;
    document.getElementById("productSku").readOnly=true;
    document.getElementById("productTitle").value=p.title;
    document.getElementById("productPrice").value=Math.round(Number(p.price_rial)/10);
    document.getElementById("productCategory").value=p.category_id||"";
    document.getElementById("productBrand").value=p.brand||"";
    document.getElementById("productUnit").value=p.unit_label||"عدد";
    document.getElementById("productDescription").value=p.description||"";
    document.getElementById("productMediaId").value=p.media_id||"";
    document.getElementById("productFormTitle").textContent="ویرایش محصول";
    document.getElementById("productForm").hidden=false;
    document.getElementById("productForm").scrollIntoView({behavior:"smooth"});
  }
  if(disable&&confirm("این محصول از فروشگاه غیرفعال شود؟")){
    try{await request("admin/products/"+disable.dataset.disableProduct,{method:"DELETE",csrf:true});await loadAll();message("محصول غیرفعال شد.","success");}
    catch(error){message(error.message,"error");}
  }
});
document.getElementById("productForm").addEventListener("submit",async e=>{
  e.preventDefault();
  const id=document.getElementById("productId").value;
  const payload={
    sku:document.getElementById("productSku").value.trim(),
    title:document.getElementById("productTitle").value.trim(),
    price_rial:Math.round(Number(document.getElementById("productPrice").value)*10),
    category_id:document.getElementById("productCategory").value||null,
    brand:document.getElementById("productBrand").value.trim(),
    unit_label:document.getElementById("productUnit").value.trim()||"عدد",
    description:document.getElementById("productDescription").value.trim(),
    media_id:document.getElementById("productMediaId").value?Number(document.getElementById("productMediaId").value):null,
    is_weighted:false,is_active:true
  };
  try{
    await request(id?"admin/products/"+id:"admin/products",{method:id?"PATCH":"POST",body:JSON.stringify(payload),csrf:true});
    resetProductForm();await loadAll();message("اطلاعات محصول ذخیره شد.","success");
  }catch(error){message(error.message,"error");}
});
document.getElementById("categoryForm").addEventListener("submit",async e=>{
  e.preventDefault();
  try{
    await request("admin/categories",{method:"POST",body:JSON.stringify({title:document.getElementById("categoryTitle").value.trim(),slug:document.getElementById("categorySlug").value.trim(),sort_order:categories.length+1,is_active:true,media_id:document.getElementById("categoryMediaId").value?Number(document.getElementById("categoryMediaId").value):null}),csrf:true});
    e.target.reset();await loadAll();message("دسته‌بندی افزوده شد.","success");
  }catch(error){message(error.message,"error");}
});
document.getElementById("categoryRows").addEventListener("click",async e=>{
  const b=e.target.closest("[data-disable-category]");if(!b||!confirm("این دسته‌بندی غیرفعال شود؟"))return;
  try{await request("admin/categories/"+b.dataset.disableCategory,{method:"DELETE",csrf:true});await loadAll();message("دسته‌بندی غیرفعال شد.","success");}
  catch(error){message(error.message,"error");}
});
document.getElementById("adminOtpRequest").addEventListener("click",async()=>{
  try{await request("auth/request-otp",{method:"POST",body:JSON.stringify({mobile:document.getElementById("adminMobile").value.trim()})});loginMessage("در صورت فعال بودن حساب، کد ورود ارسال می‌شود.","success");}
  catch(error){loginMessage(error.message,"error");}
});
document.getElementById("adminOtpVerify").addEventListener("click",async()=>{
  try{
    const result=await request("auth/verify-otp",{method:"POST",body:JSON.stringify({mobile:document.getElementById("adminMobile").value.trim(),code:document.getElementById("adminOtpCode").value.trim()})});
    csrfToken=result.data.csrf_token;
    document.getElementById("adminLoginDialog").close();
    document.getElementById("adminIdentity").textContent=result.data.user.full_name;
    await loadAll();
  }catch(error){loginMessage(error.message,"error");}
});
loadSession();

document.getElementById("reloadOrders").addEventListener("click",loadOrders);
document.getElementById("orderStatusFilter").addEventListener("change",async e=>{
  try{const suffix=e.target.value?"?status="+encodeURIComponent(e.target.value):"";const result=await request("admin/orders"+suffix);orders=result.data||[];renderOrders();}
  catch(error){message(error.message,"error");}
});
document.getElementById("orderRows").addEventListener("click",async e=>{
  const detail=e.target.closest("[data-admin-order-detail]");
  if(detail){
    const order=orders.find(o=>Number(o.id)===Number(detail.dataset.adminOrderDetail));
    if(order){
      const address=order.fulfillment==="delivery"?[order.delivery_recipient,order.delivery_mobile,order.province,order.city,order.delivery_address,order.postal_code].filter(Boolean).join(" · "):"تحویل حضوری";
      const box=document.getElementById("adminOrderDetail");
      box.hidden=false;
      box.innerHTML='<h3>سفارش '+escapeText(order.order_number)+'</h3><p>مشتری: '+escapeText(order.customer_name)+' · '+escapeText(order.customer_mobile)+'</p><p>نشانی / روش دریافت: '+escapeText(address)+'</p><p>توضیحات: '+escapeText(order.customer_note||"—")+'</p>';
    }
    return;
  }
  const b=e.target.closest("[data-next-order]");if(!b)return;
  try{
    await request("admin/orders/"+b.dataset.nextOrder+"/transition",{method:"POST",body:JSON.stringify({status:b.dataset.nextStatus}),csrf:true});
    await loadOrders();message("مرحله سفارش به‌روزرسانی شد.","success");
  }catch(error){message(error.message,"error");}
});

document.getElementById("reloadInventory").addEventListener("click",loadInventory);
document.getElementById("warehouseForm").addEventListener("submit",async e=>{
  e.preventDefault();
  try{
    await request("admin/warehouses",{method:"POST",body:JSON.stringify({title:document.getElementById("warehouseTitle").value.trim(),warehouse_type:document.getElementById("warehouseType").value}),csrf:true});
    e.target.reset();await loadInventory();message("انبار جدید ثبت شد.","success");
  }catch(error){message(error.message,"error");}
});
document.getElementById("receiptForm").addEventListener("submit",async e=>{
  e.preventDefault();
  try{
    await request("admin/stock/receipts",{method:"POST",body:JSON.stringify({
      warehouse_id:Number(document.getElementById("receiptWarehouse").value),
      product_id:Number(document.getElementById("receiptProduct").value),
      quantity:Number(document.getElementById("receiptQuantity").value),
      reason:document.getElementById("receiptReason").value.trim()
    }),csrf:true});
    e.target.reset();await loadInventory();message("رسید انبار ثبت شد.","success");
  }catch(error){message(error.message,"error");}
});
document.getElementById("adjustmentForm").addEventListener("submit",async e=>{
  e.preventDefault();
  try{
    await request("admin/stock/adjustments",{method:"POST",body:JSON.stringify({
      warehouse_id:Number(document.getElementById("adjustWarehouse").value),
      product_id:Number(document.getElementById("adjustProduct").value),
      quantity:Number(document.getElementById("adjustQuantity").value),
      reason:document.getElementById("adjustReason").value.trim()
    }),csrf:true});
    e.target.reset();await loadInventory();message("اصلاح موجودی ثبت شد.","success");
  }catch(error){message(error.message,"error");}
});
document.getElementById("transferForm").addEventListener("submit",async e=>{
  e.preventDefault();
  try{
    await request("admin/stock/transfers",{method:"POST",body:JSON.stringify({
      from_warehouse_id:Number(document.getElementById("transferFrom").value),
      to_warehouse_id:Number(document.getElementById("transferTo").value),
      product_id:Number(document.getElementById("transferProduct").value),
      quantity:Number(document.getElementById("transferQuantity").value)
    }),csrf:true});
    e.target.reset();await loadInventory();message("انتقال موجودی ثبت شد.","success");
  }catch(error){message(error.message,"error");}
});

document.getElementById("reloadScenes").addEventListener("click",loadScenes);
document.getElementById("mediaUploadForm").addEventListener("submit",async e=>{
  e.preventDefault();
  const file=document.getElementById("mediaFile").files[0];
  if(!file){document.getElementById("mediaUploadMessage").textContent="ابتدا تصویر را انتخاب کنید.";return;}
  const form=new FormData();form.append("file",file);form.append("media_type",document.getElementById("mediaType").value);form.append("alt_text",document.getElementById("mediaAlt").value.trim());
  try{
    const response=await fetch(apiBase+"admin/media",{method:"POST",credentials:"same-origin",headers:{"X-CSRF-Token":csrfToken,"Accept":"application/json"},body:form});
    const payload=await response.json().catch(()=>({}));
    if(!response.ok)throw new Error(payload.error||"بارگذاری تصویر ناموفق بود.");
    document.getElementById("sceneMediaId").value=payload.data.id;
    if(document.getElementById("mediaType").value==="product")document.getElementById("productMediaId").value=payload.data.id;
    if(document.getElementById("mediaType").value==="category")document.getElementById("categoryMediaId").value=payload.data.id;
    document.getElementById("mediaUploadMessage").textContent="تصویر ذخیره شد؛ شناسه رسانه "+payload.data.id+" در فرم صحنه قرار گرفت.";
    document.getElementById("mediaUploadMessage").className="form-message success";
  }catch(error){document.getElementById("mediaUploadMessage").textContent=error.message;document.getElementById("mediaUploadMessage").className="form-message error";}
});
document.getElementById("sceneForm").addEventListener("submit",async e=>{
  e.preventDefault();
  try{
    await request("admin/scenes",{method:"POST",body:JSON.stringify({
      title:document.getElementById("sceneTitle").value.trim(),
      scene_type:document.getElementById("sceneType").value,
      media_id:Number(document.getElementById("sceneMediaId").value),
      fallback_media_id:document.getElementById("sceneFallbackId").value?Number(document.getElementById("sceneFallbackId").value):null,
      sort_order:Number(document.getElementById("sceneSort").value),
      is_published:document.getElementById("scenePublished").checked
    }),csrf:true});
    e.target.reset();await loadScenes();message("صحنه فروشگاه ساخته شد.","success");
  }catch(error){message(error.message,"error");}
});
document.getElementById("sceneRows").addEventListener("click",async e=>{
  const select=e.target.closest("[data-select-scene]");
  if(select){document.getElementById("hotspotScene").value=select.dataset.selectScene;await loadHotspots(Number(select.dataset.selectScene));return;}
  const b=e.target.closest("[data-publish-scene]");if(!b)return;
  const id=Number(b.dataset.publishScene);const currently=b.dataset.published==="1";
  try{
    if(currently)await request("admin/scenes/"+id,{method:"DELETE",csrf:true});
    else await request("admin/scenes/"+id,{method:"PATCH",body:JSON.stringify({is_published:true}),csrf:true});
    await loadScenes();message(currently?"صحنه از انتشار خارج شد.":"صحنه منتشر شد.","success");
  }catch(error){message(error.message,"error");}
});
document.getElementById("hotspotScene").addEventListener("change",e=>loadHotspots(Number(e.target.value)));
document.getElementById("scenePreview").addEventListener("click",event=>{
  if(event.target.closest(".scene-preview-hotspot"))return;
  const rect=event.currentTarget.getBoundingClientRect();
  document.getElementById("hotspotX").value=Math.max(0,Math.min(1,(event.clientX-rect.left)/rect.width)).toFixed(2);
  document.getElementById("hotspotY").value=Math.max(0,Math.min(1,(event.clientY-rect.top)/rect.height)).toFixed(2);
  message("موقعیت نقطه روی تصویر انتخاب شد؛ حالا عنوان و عملکرد آن را ثبت کنید.","success");
});
document.getElementById("hotspotAction").addEventListener("change",updateHotspotFields);
document.getElementById("hotspotForm").addEventListener("submit",async e=>{
  e.preventDefault();
  const action=document.getElementById("hotspotAction").value;
  const payload={
    label:document.getElementById("hotspotLabel").value.trim(),
    action,
    x:Number(document.getElementById("hotspotX").value),
    y:Number(document.getElementById("hotspotY").value)
  };
  if(action==="product")payload.product_id=Number(document.getElementById("hotspotProduct").value);
  if(action==="category")payload.category_id=Number(document.getElementById("hotspotCategory").value);
  if(action==="navigate")payload.target_scene_id=Number(document.getElementById("hotspotTarget").value);
  try{
    const sceneId=Number(document.getElementById("hotspotScene").value);
    await request("admin/scenes/"+sceneId+"/hotspots",{method:"POST",body:JSON.stringify(payload),csrf:true});
    document.getElementById("hotspotLabel").value="";await loadHotspots(sceneId);await loadScenes();message("نقطه تعاملی افزوده شد.","success");
  }catch(error){message(error.message,"error");}
});
document.getElementById("hotspotRows").addEventListener("click",async e=>{
  const b=e.target.closest("[data-delete-hotspot]");if(!b||!confirm("این نقطه تعاملی حذف شود؟"))return;
  try{await request("admin/hotspots/"+b.dataset.deleteHotspot,{method:"DELETE",csrf:true});await loadHotspots(Number(document.getElementById("hotspotScene").value));await loadScenes();message("نقطه حذف شد.","success");}
  catch(error){message(error.message,"error");}
});
updateHotspotFields();
