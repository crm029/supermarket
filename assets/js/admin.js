"use strict";
const apiBase = new URL("api/v1/", document.baseURI).toString();
let csrfToken = "";
let products = [];
let categories = [];
let orders = [];
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
    const action=nextStatus?'<button class="admin-row-action" data-next-order="'+Number(o.id)+'" data-next-status="'+nextStatus+'">مرحله بعد</button>':"—";
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
    await request("admin/categories",{method:"POST",body:JSON.stringify({title:document.getElementById("categoryTitle").value.trim(),slug:document.getElementById("categorySlug").value.trim(),sort_order:categories.length+1,is_active:true}),csrf:true});
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
  const b=e.target.closest("[data-next-order]");if(!b)return;
  try{
    await request("admin/orders/"+b.dataset.nextOrder+"/transition",{method:"POST",body:JSON.stringify({status:b.dataset.nextStatus}),csrf:true});
    await loadOrders();message("مرحله سفارش به‌روزرسانی شد.","success");
  }catch(error){message(error.message,"error");}
});
