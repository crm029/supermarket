"use strict";
const apiBase = new URL("api/v1/", document.baseURI).toString();
let csrfToken = "";
let products = [];
let categories = [];
let activeVariantParent = null;
let adminVariants = [];
let orders = [];
let warehouses = [];
let stockRows = [];
let lowStockRows = [];
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

async function downloadCsv(path, fallbackName) {
  const response = await fetch(apiBase + path, {credentials:"same-origin",headers:{Accept:"text/csv"}});
  if (!response.ok) {
    const payload = await response.json().catch(()=>({}));
    throw new Error(payload.error || "دریافت فایل گزارش ناموفق بود.");
  }
  const blob = await response.blob();
  const disposition = response.headers.get("Content-Disposition") || "";
  const match = disposition.match(/filename="([^"]+)"/i);
  const filename = match?.[1] || fallbackName;
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(()=>URL.revokeObjectURL(url),1000);
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
    await loadPurchasing();
    await loadScenes();
    await loadMediaLibrary();
    await loadReport();
    await loadSettings();
    await loadDeliveryZones();
    await loadAdminPickupSlots();
    await loadCoupons();
    await loadStaff();
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

let suppliers=[],purchaseOrders=[],selectedPurchaseOrder=null;
async function loadPurchasing(){
  try{
    const [supplierResult,orderResult]=await Promise.all([
      request("admin/suppliers"),request("admin/purchase-orders")
    ]);
    suppliers=supplierResult.data||[];
    purchaseOrders=orderResult.data||[];
    const supplierOptions=suppliers.filter(s=>Number(s.is_active)).map(s=>'<option value="'+Number(s.id)+'">'+escapeText(s.name)+'</option>').join("");
    document.getElementById("purchaseSupplier").innerHTML='<option value="">انتخاب تأمین‌کننده</option>'+supplierOptions;
    const purchaseProducts=products.filter(p=>Number(p.is_active)&&p.product_kind!=="variant_parent").map(p=>
      '<option value="'+Number(p.id)+'">'+escapeText(p.product_kind==="variant"?(p.parent_title||p.title)+" · "+(p.variant_label||"تنوع"):p.title)+' · '+escapeText(p.sku)+'</option>'
    ).join("");
    document.getElementById("purchaseProduct").innerHTML='<option value="">انتخاب کالا</option>'+purchaseProducts;
    document.getElementById("supplierRows").innerHTML=suppliers.map(s=>
      '<article><div><strong>'+escapeText(s.name)+'</strong><small>'+escapeText(s.contact_person||"بدون رابط")+' · '+escapeText(s.phone||"بدون تلفن")+'</small><small>'+escapeText(s.email||"")+'</small></div><span class="admin-status '+(s.is_active?"active":"inactive")+'">'+(s.is_active?"فعال":"غیرفعال")+'</span></article>'
    ).join("")||'<p>هنوز تأمین‌کننده‌ای ثبت نشده است.</p>';
    const orderOptions=purchaseOrders.filter(o=>["ordered","partially_received"].includes(o.status)).map(o=>'<option value="'+Number(o.id)+'">'+escapeText(o.order_number)+' · '+escapeText(o.supplier_name)+'</option>').join("");
    document.getElementById("receivePurchaseOrder").innerHTML='<option value="">انتخاب سفارش</option>'+orderOptions;
    document.getElementById("purchaseOrderRows").innerHTML=purchaseOrders.map(o=>{
      const status={ordered:"ثبت‌شده",partially_received:"دریافت ناقص",received:"دریافت کامل",cancelled:"لغوشده"}[o.status]||o.status;
      const canReceive=["ordered","partially_received"].includes(o.status);
      return '<article><div><strong>'+escapeText(o.order_number)+'</strong><small>'+escapeText(o.supplier_name)+' · '+escapeText(status)+'</small><small>اقلام: '+Number(o.item_count)+' · تعداد سفارش: '+Number(o.ordered_quantity)+' · دریافت‌شده: '+Number(o.received_quantity)+'</small><small>ارزش برآوردی: '+money(Number(o.estimated_cost_rial)/10)+'</small></div><span class="admin-status '+(o.status==="received"?"active":"inactive")+'">'+escapeText(status)+'</span>'+(canReceive?'<button class="admin-row-action" type="button" data-select-purchase-order="'+Number(o.id)+'">ثبت دریافت</button>':"")+'</article>';
    }).join("")||'<p>هنوز سفارش خریدی ثبت نشده است.</p>';
    const warehouseOptions=warehouses.filter(w=>Number(w.is_active)).map(w=>'<option value="'+Number(w.id)+'">'+escapeText(w.title)+'</option>').join("");
    document.getElementById("receiveWarehouse").innerHTML='<option value="">انتخاب انبار</option>'+warehouseOptions;
    document.getElementById("returnSupplier").innerHTML='<option value="">انتخاب تأمین‌کننده</option>'+supplierOptions;
    document.getElementById("returnWarehouse").innerHTML='<option value="">انتخاب انبار</option>'+warehouseOptions;
    await loadSupplierReturns();
    if(selectedPurchaseOrder) await loadPurchaseOrderItems(selectedPurchaseOrder);
  }catch(error){
    document.getElementById("purchaseOrderMessage").textContent=error.message;
    document.getElementById("purchaseOrderMessage").className="form-message error";
  }
}
async function loadPurchaseOrderItems(orderId){
  try{
    const result=await request("admin/purchase-orders/"+Number(orderId));
    selectedPurchaseOrder=Number(orderId);
    const items=(result.data.items||[]).filter(i=>Number(i.remaining_quantity)>0);
    document.getElementById("receivePurchaseItem").innerHTML=items.map(i=>
      '<option value="'+Number(i.id)+'" data-remaining="'+Number(i.remaining_quantity)+'">'+escapeText(i.product_title)+' · مانده '+Number(i.remaining_quantity)+'</option>'
    ).join("")||'<option value="">همه اقلام دریافت شده‌اند</option>';
    const selected=items[0];
    document.getElementById("receiveQuantity").max=selected?String(Number(selected.remaining_quantity)):"1";
    document.getElementById("receiveQuantity").value=selected?String(Number(selected.remaining_quantity)):"";
    document.getElementById("purchaseReceiveForm").scrollIntoView({behavior:"smooth"});
  }catch(error){
    document.getElementById("purchaseReceiveMessage").textContent=error.message;
    document.getElementById("purchaseReceiveMessage").className="form-message error";
  }
}
document.getElementById("reloadPurchasing").addEventListener("click",loadPurchasing);
document.getElementById("supplierForm").addEventListener("submit",async event=>{
  event.preventDefault();
  const payload={
    name:document.getElementById("supplierName").value.trim(),
    contact_person:document.getElementById("supplierContact").value.trim(),
    phone:document.getElementById("supplierPhone").value.trim(),
    email:document.getElementById("supplierEmail").value.trim(),
    address:document.getElementById("supplierAddress").value.trim(),
    notes:document.getElementById("supplierNotes").value.trim()
  };
  try{
    await request("admin/suppliers",{method:"POST",csrf:true,body:JSON.stringify(payload)});
    event.target.reset();await loadPurchasing();
    document.getElementById("supplierMessage").textContent="تأمین‌کننده ثبت شد.";
    document.getElementById("supplierMessage").className="form-message success";
  }catch(error){
    document.getElementById("supplierMessage").textContent=error.message;
    document.getElementById("supplierMessage").className="form-message error";
  }
});
document.getElementById("purchaseOrderForm").addEventListener("submit",async event=>{
  event.preventDefault();
  const quantity=Number(document.getElementById("purchaseQuantity").value);
  const cost=Number(document.getElementById("purchaseUnitCost").value);
  const costRial=Math.round(cost*10);
  if(!Number.isInteger(quantity)||quantity<1||quantity>1000000||!Number.isSafeInteger(costRial)||costRial<0){
    document.getElementById("purchaseOrderMessage").textContent="تعداد و قیمت خرید را بررسی کنید.";
    document.getElementById("purchaseOrderMessage").className="form-message error";return;
  }
  const payload={
    supplier_id:Number(document.getElementById("purchaseSupplier").value),
    expected_at:document.getElementById("purchaseExpected").value||null,
    notes:document.getElementById("purchaseNotes").value.trim(),
    items:[{product_id:Number(document.getElementById("purchaseProduct").value),quantity,unit_cost_rial:costRial}]
  };
  try{
    await request("admin/purchase-orders",{method:"POST",csrf:true,body:JSON.stringify(payload)});
    event.target.reset();document.getElementById("purchaseQuantity").value="1";document.getElementById("purchaseUnitCost").value="0";
    await loadPurchasing();
    document.getElementById("purchaseOrderMessage").textContent="سفارش خرید ثبت شد.";
    document.getElementById("purchaseOrderMessage").className="form-message success";
  }catch(error){
    document.getElementById("purchaseOrderMessage").textContent=error.message;
    document.getElementById("purchaseOrderMessage").className="form-message error";
  }
});
document.getElementById("purchaseOrderRows").addEventListener("click",event=>{
  const button=event.target.closest("[data-select-purchase-order]");
  if(!button)return;
  const id=Number(button.dataset.selectPurchaseOrder);
  document.getElementById("receivePurchaseOrder").value=String(id);
  loadPurchaseOrderItems(id);
});
document.getElementById("receivePurchaseOrder").addEventListener("change",event=>{
  const id=Number(event.target.value);
  if(id)loadPurchaseOrderItems(id);
  else{selectedPurchaseOrder=null;document.getElementById("receivePurchaseItem").innerHTML="";}
});
document.getElementById("receivePurchaseItem").addEventListener("change",event=>{
  const option=event.target.selectedOptions[0];
  if(!option)return;
  document.getElementById("receiveQuantity").max=option.dataset.remaining||"1";
  document.getElementById("receiveQuantity").value=option.dataset.remaining||"";
});
document.getElementById("purchaseReceiveForm").addEventListener("submit",async event=>{
  event.preventDefault();
  const orderId=Number(document.getElementById("receivePurchaseOrder").value);
  const itemId=Number(document.getElementById("receivePurchaseItem").value);
  const quantity=Number(document.getElementById("receiveQuantity").value);
  const max=Number(document.getElementById("receivePurchaseItem").selectedOptions[0]?.dataset.remaining||0);
  if(!orderId||!itemId||!Number.isInteger(quantity)||quantity<1||quantity>max){
    document.getElementById("purchaseReceiveMessage").textContent="سفارش، قلم کالا و تعداد دریافتی را بررسی کنید.";
    document.getElementById("purchaseReceiveMessage").className="form-message error";return;
  }
  try{
    await request("admin/purchase-orders/"+orderId+"/receive",{
      method:"POST",csrf:true,
      body:JSON.stringify({warehouse_id:Number(document.getElementById("receiveWarehouse").value),notes:document.getElementById("receiveNotes").value.trim(),items:[{item_id:itemId,quantity,batch_number:document.getElementById("receiveBatchNumber").value.trim(),expiry_date:document.getElementById("receiveExpiryDate").value||null}]})
    });
    await loadInventory();await loadPurchasing();await loadPurchaseOrderItems(orderId);
    document.getElementById("purchaseReceiveMessage").textContent="رسید ثبت شد و موجودی انبار به‌روزرسانی شد.";
    document.getElementById("purchaseReceiveMessage").className="form-message success";
  }catch(error){
    document.getElementById("purchaseReceiveMessage").textContent=error.message;
    document.getElementById("purchaseReceiveMessage").className="form-message error";
  }
});

let supplierReturnLines=[];
async function loadSupplierReturnLines(){
  const supplierId=Number(document.getElementById("returnSupplier").value);
  const warehouseId=Number(document.getElementById("returnWarehouse").value);
  const select=document.getElementById("returnReceiptItem");
  select.innerHTML='<option value="">ابتدا تأمین‌کننده و انبار را انتخاب کنید</option>';
  if(!supplierId||!warehouseId)return;
  try{
    const result=await request("admin/supplier-returns/eligible-lines?supplier_id="+supplierId+"&warehouse_id="+warehouseId);
    supplierReturnLines=result.data||[];
    select.innerHTML=supplierReturnLines.map(item=>
      '<option value="'+Number(item.receipt_item_id)+'" data-remaining="'+Number(item.remaining_quantity)+'">'+
      escapeText(item.product_title)+' · '+escapeText(item.order_number)+' · مانده قابل مرجوعی '+Number(item.remaining_quantity)+'</option>'
    ).join("")||'<option value="">رسید قابل مرجوعی وجود ندارد</option>';
    const first=supplierReturnLines[0];
    document.getElementById("returnQuantity").max=first?String(Number(first.remaining_quantity)):"1";
    document.getElementById("returnQuantity").value=first?String(Number(first.remaining_quantity)):"";
  }catch(error){
    document.getElementById("supplierReturnMessage").textContent=error.message;
    document.getElementById("supplierReturnMessage").className="form-message error";
  }
}
async function loadSupplierReturns(){
  try{
    const result=await request("admin/supplier-returns");
    const rows=result.data||[];
    document.getElementById("supplierReturnRows").innerHTML=rows.map(item=>
      '<article><div><strong>'+escapeText(item.return_number)+'</strong>'+
      '<small>'+escapeText(item.supplier_name)+' · '+escapeText(item.warehouse_title)+'</small>'+
      '<small>'+escapeText(item.reason)+' · تعداد: '+Number(item.returned_quantity)+'</small>'+
      '<small>ارزش مرجوعی: '+money(Number(item.return_value_rial)/10)+'</small></div>'+
      '<span class="admin-status inactive">ثبت‌شده</span></article>'
    ).join("")||'<p>هنوز مرجوعی ثبت نشده است.</p>';
  }catch(error){
    document.getElementById("supplierReturnRows").textContent=error.message;
  }
}
document.getElementById("returnSupplier").addEventListener("change",loadSupplierReturnLines);
document.getElementById("returnWarehouse").addEventListener("change",loadSupplierReturnLines);
document.getElementById("returnReceiptItem").addEventListener("change",event=>{
  const option=event.target.selectedOptions[0];
  if(!option)return;
  document.getElementById("returnQuantity").max=option.dataset.remaining||"1";
  document.getElementById("returnQuantity").value=option.dataset.remaining||"";
});
document.getElementById("supplierReturnForm").addEventListener("submit",async event=>{
  event.preventDefault();
  const receiptItemId=Number(document.getElementById("returnReceiptItem").value);
  const quantity=Number(document.getElementById("returnQuantity").value);
  const max=Number(document.getElementById("returnReceiptItem").selectedOptions[0]?.dataset.remaining||0);
  const supplierId=Number(document.getElementById("returnSupplier").value);
  const warehouseId=Number(document.getElementById("returnWarehouse").value);
  if(!supplierId||!warehouseId||!receiptItemId||!Number.isInteger(quantity)||quantity<1||quantity>max){
    document.getElementById("supplierReturnMessage").textContent="تأمین‌کننده، انبار، قلم رسید و تعداد مرجوعی را بررسی کنید.";
    document.getElementById("supplierReturnMessage").className="form-message error";return;
  }
  try{
    const result=await request("admin/supplier-returns",{method:"POST",csrf:true,body:JSON.stringify({
      supplier_id:supplierId,warehouse_id:warehouseId,reason:document.getElementById("returnReason").value.trim(),
      notes:document.getElementById("returnNotes").value.trim(),items:[{receipt_item_id:receiptItemId,quantity}]
    })});
    event.target.reset();
    await loadInventory();await loadPurchasing();await loadSupplierReturnLines();await loadSupplierReturns();
    document.getElementById("supplierReturnMessage").textContent="مرجوعی "+result.data.return_number+" ثبت شد و موجودی آزاد کاهش یافت.";
    document.getElementById("supplierReturnMessage").className="form-message success";
  }catch(error){
    document.getElementById("supplierReturnMessage").textContent=error.message;
    document.getElementById("supplierReturnMessage").className="form-message error";
  }
});
let staffData = {roles:[],staff:[],permissions:[]};
async function loadStaff() {
  try {
    const result=await request("admin/staff");
    staffData=result.data||{roles:[],staff:[],permissions:[]};
    const permissions=document.getElementById("staffPermissionList");
    permissions.innerHTML=(staffData.permissions||[]).map(p=>
      '<label class="staff-permission"><input type="checkbox" value="'+escapeText(p.permission_key)+'" data-staff-permission><span><strong>'+escapeText(p.permission_key)+'</strong><small>'+escapeText(p.description||"")+'</small></span></label>'
    ).join("")||'<p class="admin-hint">مجوزی برای انتخاب وجود ندارد.</p>';
    const roleSelect=document.getElementById("staffRoleSelect");
    roleSelect.innerHTML='<option value="">انتخاب نقش</option>'+(staffData.roles||[]).map(r=>
      '<option value="'+Number(r.id)+'">'+escapeText(r.title)+'</option>'
    ).join("");
    const rows=document.getElementById("staffRows");
    rows.innerHTML=(staffData.staff||[]).map(person=>
      '<tr><td>'+escapeText(person.full_name||"—")+'</td><td>'+escapeText(person.mobile||"")+'</td><td>'+escapeText(person.status||"")+'</td><td><div class="staff-role-tags">'+(person.role_ids||[]).map(id=>{
        const role=(staffData.roles||[]).find(item=>Number(item.id)===Number(id));
        return role?'<span class="staff-role-tag">'+escapeText(role.title)+' <button type="button" aria-label="حذف نقش" data-remove-staff-role="'+Number(id)+'" data-staff-user="'+Number(person.id)+'">×</button></span>':"";
      }).join("")+'</div></td></tr>'
    ).join("")||'<tr><td colspan="4">هنوز کارمندی با نقش ثبت نشده است.</td></tr>';
  } catch(error) {
    document.getElementById("staffMessage").textContent=error.message;
    document.getElementById("staffMessage").className="form-message error";
  }
}
document.getElementById("reloadStaff").addEventListener("click",loadStaff);
document.getElementById("staffRoleForm").addEventListener("submit",async event=>{
  event.preventDefault();
  const permissionKeys=[...document.querySelectorAll("[data-staff-permission]:checked")].map(input=>input.value);
  try{
    await request("admin/roles",{method:"POST",csrf:true,body:JSON.stringify({
      role_key:document.getElementById("staffRoleKey").value.trim(),
      title:document.getElementById("staffRoleTitle").value.trim(),
      permission_keys:permissionKeys
    })});
    event.target.reset();await loadStaff();
    document.getElementById("staffMessage").textContent="نقش جدید ساخته شد.";
    document.getElementById("staffMessage").className="form-message success";
  }catch(error){
    document.getElementById("staffMessage").textContent=error.message;
    document.getElementById("staffMessage").className="form-message error";
  }
});
document.getElementById("staffAssignForm").addEventListener("submit",async event=>{
  event.preventDefault();
  try{
    await request("admin/staff/assign",{method:"POST",csrf:true,body:JSON.stringify({
      mobile:document.getElementById("staffMobile").value.trim(),
      role_id:Number(document.getElementById("staffRoleSelect").value)
    })});
    document.getElementById("staffMobile").value="";
    await loadStaff();
    document.getElementById("staffMessage").textContent="نقش به کارمند اختصاص یافت.";
    document.getElementById("staffMessage").className="form-message success";
  }catch(error){
    document.getElementById("staffMessage").textContent=error.message;
    document.getElementById("staffMessage").className="form-message error";
  }
});
document.getElementById("staffRows").addEventListener("click",async event=>{
  const button=event.target.closest("[data-remove-staff-role]");
  if(!button||!confirm("این نقش از حساب کارمند برداشته شود؟"))return;
  try{
    await request("admin/staff/"+Number(button.dataset.staffUser)+"/roles/"+Number(button.dataset.removeStaffRole),{method:"DELETE",csrf:true});
    await loadStaff();
    document.getElementById("staffMessage").textContent="نقش از حساب کارمند برداشته شد.";
    document.getElementById("staffMessage").className="form-message success";
  }catch(error){
    document.getElementById("staffMessage").textContent=error.message;
    document.getElementById("staffMessage").className="form-message error";
  }
});
let deliveryZones = [];
let adminPickupSlots=[];
let adminCoupons=[];
function resetCouponForm(){
  document.getElementById("couponForm").reset();
  document.getElementById("couponId").value="";
  document.getElementById("couponActive").checked=true;
  document.getElementById("couponType").value="percentage";
  document.getElementById("couponPerUserLimit").value="1";
  document.getElementById("couponMinimum").value="0";
  document.getElementById("couponFormTitle").textContent="ایجاد کد تخفیف";
  document.getElementById("couponValueLabel").firstChild.textContent="درصد تخفیف";
  document.getElementById("couponType").dispatchEvent(new Event("change"));
}
document.getElementById("couponType").addEventListener("change",()=>{
  const fixed=document.getElementById("couponType").value==="fixed";
  document.getElementById("couponValueLabel").firstChild.textContent=fixed?"مبلغ تخفیف (تومان)":"درصد تخفیف";
  document.getElementById("couponValue").max=fixed?"100000000":"100";
  document.getElementById("couponMaxDiscount").disabled=fixed;
  if(fixed)document.getElementById("couponMaxDiscount").value="";
});
async function loadCoupons(){
  try{
    const result=await request("admin/coupons");adminCoupons=result.data||[];
    document.getElementById("couponRows").innerHTML=adminCoupons.map(coupon=>{
      const value=coupon.discount_type==="percentage"?Number(coupon.discount_value)+" درصد":money(Number(coupon.discount_value)/10);
      const usage=coupon.usage_limit===null?"نامحدود":Number(coupon.used_count)+" از "+Number(coupon.usage_limit);
      return '<article><div><strong dir="ltr">'+escapeText(coupon.code)+'</strong><small>'+escapeText(coupon.title)+' · '+value+'</small><small>استفاده: '+usage+' · سهمیه هر مشتری: '+Number(coupon.per_user_limit)+'</small><small>اعتبار: '+escapeText(coupon.starts_at_local)+' تا '+escapeText(coupon.ends_at_local)+'</small></div><span class="admin-status '+(coupon.is_active?"active":"inactive")+'">'+(coupon.is_active?"فعال":"غیرفعال")+'</span><button class="admin-row-action" data-edit-coupon="'+Number(coupon.id)+'">ویرایش</button>'+(coupon.is_active?'<button class="admin-row-action danger" data-disable-coupon="'+Number(coupon.id)+'">غیرفعال‌سازی</button>':"")+'</article>';
    }).join("")||'<p>هنوز کد تخفیفی ثبت نشده است.</p>';
  }catch(error){
    document.getElementById("couponMessage").textContent=error.message;
    document.getElementById("couponMessage").className="form-message error";
  }
}
document.getElementById("reloadCoupons").addEventListener("click",loadCoupons);
document.getElementById("cancelCouponEdit").addEventListener("click",resetCouponForm);
document.getElementById("couponForm").addEventListener("submit",async event=>{
  event.preventDefault();
  const id=document.getElementById("couponId").value;
  const type=document.getElementById("couponType").value;
  const rawValue=Number(document.getElementById("couponValue").value);
  const rawMax=document.getElementById("couponMaxDiscount").value.trim();
  const rawMinimum=Number(document.getElementById("couponMinimum").value);
  const rawUsage=document.getElementById("couponUsageLimit").value.trim();
  const perUser=Number(document.getElementById("couponPerUserLimit").value);
  const value=type==="fixed"?Math.round(rawValue*10):rawValue;
  const maximum=rawMax===""?null:Math.round(Number(rawMax)*10);
  const minimum=Math.round(rawMinimum*10);
  if(!Number.isSafeInteger(value)||value<1||(type==="percentage"&&value>100)||
     !Number.isSafeInteger(minimum)||minimum<0||
     (maximum!==null&&(!Number.isSafeInteger(maximum)||maximum<1))||
     (rawUsage!==""&&(!Number.isInteger(Number(rawUsage))||Number(rawUsage)<1))||
     !Number.isInteger(perUser)||perUser<1||perUser>1000){
    document.getElementById("couponMessage").textContent="مقادیر تخفیف، مبلغ سفارش و محدودیت استفاده را بررسی کنید.";
    document.getElementById("couponMessage").className="form-message error";return;
  }
  const payload={
    code:document.getElementById("couponCode").value.trim().toUpperCase(),
    title:document.getElementById("couponTitle").value.trim(),
    discount_type:type,discount_value:value,max_discount_rial:maximum,
    minimum_order_rial:minimum,usage_limit:rawUsage===""?null:Number(rawUsage),
    per_user_limit:perUser,starts_at:document.getElementById("couponStart").value,
    ends_at:document.getElementById("couponEnd").value,
    is_active:document.getElementById("couponActive").checked
  };
  try{
    await request(id?"admin/coupons/"+id:"admin/coupons",{
      method:id?"PATCH":"POST",csrf:true,body:JSON.stringify(payload)
    });
    resetCouponForm();await loadCoupons();
    document.getElementById("couponMessage").textContent="کد تخفیف ذخیره شد.";
    document.getElementById("couponMessage").className="form-message success";
  }catch(error){
    document.getElementById("couponMessage").textContent=error.message;
    document.getElementById("couponMessage").className="form-message error";
  }
});
document.getElementById("couponRows").addEventListener("click",async event=>{
  const edit=event.target.closest("[data-edit-coupon]");
  const disable=event.target.closest("[data-disable-coupon]");
  if(edit){
    const coupon=adminCoupons.find(item=>Number(item.id)===Number(edit.dataset.editCoupon));if(!coupon)return;
    document.getElementById("couponId").value=coupon.id;
    document.getElementById("couponCode").value=coupon.code;
    document.getElementById("couponTitle").value=coupon.title;
    document.getElementById("couponType").value=coupon.discount_type;
    document.getElementById("couponValue").value=coupon.discount_type==="fixed"?Math.round(Number(coupon.discount_value)/10):Number(coupon.discount_value);
    document.getElementById("couponMaxDiscount").value=coupon.max_discount_rial===null?"":Math.round(Number(coupon.max_discount_rial)/10);
    document.getElementById("couponMinimum").value=Math.round(Number(coupon.minimum_order_rial)/10);
    document.getElementById("couponUsageLimit").value=coupon.usage_limit??"";
    document.getElementById("couponPerUserLimit").value=Number(coupon.per_user_limit);
    document.getElementById("couponStart").value=coupon.starts_at_local;
    document.getElementById("couponEnd").value=coupon.ends_at_local;
    document.getElementById("couponActive").checked=Boolean(coupon.is_active);
    document.getElementById("couponFormTitle").textContent="ویرایش کد تخفیف";
    document.getElementById("couponType").dispatchEvent(new Event("change"));
    document.getElementById("couponForm").scrollIntoView({behavior:"smooth"});
  }
  if(disable&&confirm("این کد تخفیف غیرفعال شود؟")){
    try{
      await request("admin/coupons/"+Number(disable.dataset.disableCoupon),{method:"DELETE",csrf:true});
      await loadCoupons();
      document.getElementById("couponMessage").textContent="کد تخفیف غیرفعال شد.";
      document.getElementById("couponMessage").className="form-message success";
    }catch(error){
      document.getElementById("couponMessage").textContent=error.message;
      document.getElementById("couponMessage").className="form-message error";
    }
  }
});
async function loadAdminPickupSlots(){
  try{
    const result=await request("admin/pickup-slots");
    adminPickupSlots=result.data||[];
    document.getElementById("pickupSlotRows").innerHTML=adminPickupSlots.map(slot=>{
      return '<article><div><strong>'+escapeText(slot.title)+'</strong><small>'+escapeText(slot.starts_at_label||slot.starts_at)+' تا '+escapeText(slot.ends_at_label||slot.ends_at)+'</small><small>ظرفیت: '+Number(slot.capacity)+' · باقی‌مانده: '+Math.max(0,Number(slot.remaining_capacity??slot.capacity))+' · وضعیت: '+(slot.is_active?"فعال":"غیرفعال")+'</small></div><span class="admin-status '+(slot.is_active?"active":"inactive")+'">'+(slot.is_active?"فعال":"غیرفعال")+'</span>'+(slot.is_active?'<button class="admin-row-action danger" data-disable-pickup-slot="'+Number(slot.id)+'">غیرفعال‌سازی</button>':"")+'</article>';
    }).join("")||'<p>هنوز بازه دریافت حضوری ثبت نشده است.</p>';
  }catch(error){
    document.getElementById("pickupSlotAdminMessage").textContent=error.message;
    document.getElementById("pickupSlotAdminMessage").className="form-message error";
  }
}
document.getElementById("reloadPickupSlots").addEventListener("click",loadAdminPickupSlots);
document.getElementById("pickupSlotForm").addEventListener("submit",async event=>{
  event.preventDefault();
  const payload={
    title:document.getElementById("pickupSlotTitle").value.trim(),
    starts_at:document.getElementById("pickupSlotStart").value,
    ends_at:document.getElementById("pickupSlotEnd").value,
    capacity:Number(document.getElementById("pickupSlotCapacity").value)
  };
  if(!Number.isInteger(payload.capacity)||payload.capacity<1||payload.capacity>1000){
    document.getElementById("pickupSlotAdminMessage").textContent="ظرفیت باید عددی بین ۱ تا ۱۰۰۰ باشد.";return;
  }
  try{
    await request("admin/pickup-slots",{method:"POST",csrf:true,body:JSON.stringify(payload)});
    event.target.reset();
    document.getElementById("pickupSlotCapacity").value="10";
    await loadAdminPickupSlots();
    document.getElementById("pickupSlotAdminMessage").textContent="بازه دریافت ثبت شد.";
    document.getElementById("pickupSlotAdminMessage").className="form-message success";
  }catch(error){
    document.getElementById("pickupSlotAdminMessage").textContent=error.message;
    document.getElementById("pickupSlotAdminMessage").className="form-message error";
  }
});
document.getElementById("pickupSlotRows").addEventListener("click",async event=>{
  const button=event.target.closest("[data-disable-pickup-slot]");
  if(!button||!confirm("این بازه دریافت غیرفعال شود؟"))return;
  try{
    await request("admin/pickup-slots/"+Number(button.dataset.disablePickupSlot),{method:"DELETE",csrf:true});
    await loadAdminPickupSlots();
    document.getElementById("pickupSlotAdminMessage").textContent="بازه دریافت غیرفعال شد.";
    document.getElementById("pickupSlotAdminMessage").className="form-message success";
  }catch(error){
    document.getElementById("pickupSlotAdminMessage").textContent=error.message;
    document.getElementById("pickupSlotAdminMessage").className="form-message error";
  }
});
async function loadDeliveryZones() {
  try {
    const result=await request("admin/delivery-zones");
    deliveryZones=result.data||[];
    document.getElementById("deliveryZoneRows").innerHTML=deliveryZones.map(zone=>
      '<article><div><strong>'+escapeText(zone.title)+'</strong><small>'+escapeText(zone.province)+'، '+escapeText(zone.city)+' · پیش‌شماره: '+escapeText(zone.postal_prefix||"همه")+'</small><small>ارسال: '+money(Number(zone.delivery_fee_rial)/10)+' · حداقل سفارش: '+money(Number(zone.minimum_order_rial)/10)+'</small></div><span class="admin-status '+(Number(zone.is_active)?"active":"inactive")+'">'+(Number(zone.is_active)?"فعال":"غیرفعال")+'</span><button class="admin-row-action" data-edit-delivery-zone="'+Number(zone.id)+'">ویرایش</button>'+(Number(zone.is_active)?'<button class="admin-row-action danger" data-disable-delivery-zone="'+Number(zone.id)+'">غیرفعال‌سازی</button>':"")+'</article>'
    ).join("")||'<p>هنوز محدوده ارسالی تعریف نشده است.</p>';
  }catch(error){
    document.getElementById("deliveryZoneMessage").textContent=error.message;
    document.getElementById("deliveryZoneMessage").className="form-message error";
  }
}
function resetDeliveryZoneForm(){
  document.getElementById("deliveryZoneForm").reset();
  document.getElementById("deliveryZoneId").value="";
  document.getElementById("deliveryZoneActive").checked=true;
  document.getElementById("deliveryZoneSort").value="0";
  document.getElementById("deliveryZoneFormTitle").textContent="افزودن محدوده جدید";
}
document.getElementById("reloadDeliveryZones").addEventListener("click",loadDeliveryZones);
document.getElementById("cancelDeliveryZoneEdit").addEventListener("click",resetDeliveryZoneForm);
document.getElementById("deliveryZoneForm").addEventListener("submit",async event=>{
  event.preventDefault();
  const id=document.getElementById("deliveryZoneId").value;
  const fee=Number(document.getElementById("deliveryZoneFee").value);
  const minimum=Number(document.getElementById("deliveryZoneMinimum").value);
  const sort=Number(document.getElementById("deliveryZoneSort").value);
  if(!Number.isSafeInteger(fee)||!Number.isSafeInteger(minimum)||!Number.isInteger(sort)){
    document.getElementById("deliveryZoneMessage").textContent="هزینه، حداقل سفارش و ترتیب باید عدد صحیح باشند.";return;
  }
  const payload={
    title:document.getElementById("deliveryZoneTitle").value.trim(),
    province:document.getElementById("deliveryZoneProvince").value.trim(),
    city:document.getElementById("deliveryZoneCity").value.trim(),
    postal_prefix:document.getElementById("deliveryZonePostalPrefix").value.trim(),
    delivery_fee_rial:fee*10,
    minimum_order_rial:minimum*10,
    sort_order:sort,
    is_active:document.getElementById("deliveryZoneActive").checked
  };
  try{
    await request(id?"admin/delivery-zones/"+id:"admin/delivery-zones",{
      method:id?"PATCH":"POST",csrf:true,body:JSON.stringify(payload)
    });
    resetDeliveryZoneForm();await loadDeliveryZones();
    document.getElementById("deliveryZoneMessage").textContent="محدوده ارسال ذخیره شد.";
    document.getElementById("deliveryZoneMessage").className="form-message success";
  }catch(error){
    document.getElementById("deliveryZoneMessage").textContent=error.message;
    document.getElementById("deliveryZoneMessage").className="form-message error";
  }
});
document.getElementById("deliveryZoneRows").addEventListener("click",async event=>{
  const edit=event.target.closest("[data-edit-delivery-zone]");
  const disable=event.target.closest("[data-disable-delivery-zone]");
  if(edit){
    const zone=deliveryZones.find(item=>Number(item.id)===Number(edit.dataset.editDeliveryZone));
    if(!zone)return;
    document.getElementById("deliveryZoneId").value=zone.id;
    document.getElementById("deliveryZoneTitle").value=zone.title;
    document.getElementById("deliveryZoneProvince").value=zone.province;
    document.getElementById("deliveryZoneCity").value=zone.city;
    document.getElementById("deliveryZonePostalPrefix").value=zone.postal_prefix||"";
    document.getElementById("deliveryZoneFee").value=Math.round(Number(zone.delivery_fee_rial)/10);
    document.getElementById("deliveryZoneMinimum").value=Math.round(Number(zone.minimum_order_rial)/10);
    document.getElementById("deliveryZoneSort").value=Number(zone.sort_order);
    document.getElementById("deliveryZoneActive").checked=Boolean(Number(zone.is_active));
    document.getElementById("deliveryZoneFormTitle").textContent="ویرایش محدوده ارسال";
    document.getElementById("deliveryZoneForm").scrollIntoView({behavior:"smooth"});
  }
  if(disable&&confirm("این محدوده ارسال غیرفعال شود؟")){
    try{
      await request("admin/delivery-zones/"+Number(disable.dataset.disableDeliveryZone),{method:"DELETE",csrf:true});
      await loadDeliveryZones();
      document.getElementById("deliveryZoneMessage").textContent="محدوده ارسال غیرفعال شد.";
      document.getElementById("deliveryZoneMessage").className="form-message success";
    }catch(error){
      document.getElementById("deliveryZoneMessage").textContent=error.message;
      document.getElementById("deliveryZoneMessage").className="form-message error";
    }
  }
});
async function loadSettings() {
  try {
    const result=await request("admin/settings");
    const data=result.data||{};
    const profile=data.profile||{}, checkout=data.checkout||{};
    document.getElementById("settingStoreName").value=profile.name||"";
    document.getElementById("settingTagline").value=profile.tagline||"";
    document.getElementById("settingSupportPhone").value=profile.support_phone||"";
    document.getElementById("settingStreetAddress").value=profile.street_address||"";
    document.getElementById("settingCity").value=profile.city||"";
    document.getElementById("settingRegion").value=profile.region||"";
    document.getElementById("settingPostalCode").value=profile.postal_code||"";
    document.getElementById("settingCountryCode").value=profile.country_code||"IR";
    document.getElementById("settingLatitude").value=profile.latitude??"";
    document.getElementById("settingLongitude").value=profile.longitude??"";
    document.getElementById("settingOpeningHours").value=profile.opening_hours||"";
    document.getElementById("settingGoogleVerification").value=profile.google_site_verification||"";
    document.getElementById("settingGa4MeasurementId").value=profile.ga4_measurement_id||"";
    document.getElementById("settingPrimaryColor").value=profile.primary_color||"#173D2D";
    document.getElementById("settingAccentColor").value=profile.accent_color||"#E6A23C";
    document.getElementById("settingDeliveryEnabled").checked=checkout.delivery_enabled!==false;
    document.getElementById("settingPickupEnabled").checked=checkout.pickup_enabled!==false;
    document.getElementById("settingDeliveryFee").value=Math.round(Number(checkout.delivery_fee_rial||0)/10);
    document.getElementById("settingMinimumOrder").value=Math.round(Number(checkout.minimum_order_rial||0)/10);
    document.getElementById("settingReservationMinutes").value=Number(checkout.reservation_minutes||20);
  } catch(error) {
    document.getElementById("storeSettingsMessage").textContent=error.message;
    document.getElementById("storeSettingsMessage").className="form-message error";
  }
}
document.getElementById("reloadSettings").addEventListener("click",loadSettings);
document.getElementById("storeSettingsForm").addEventListener("submit",async event=>{
  event.preventDefault();
  const fee=Number(document.getElementById("settingDeliveryFee").value);
  const minimum=Number(document.getElementById("settingMinimumOrder").value);
  const reservation=Number(document.getElementById("settingReservationMinutes").value);
  if(!Number.isSafeInteger(fee)||!Number.isSafeInteger(minimum)||!Number.isInteger(reservation)){
    message("مقادیر مالی و زمان رزرو باید عدد صحیح باشند.","error");return;
  }
  try{
    await request("admin/settings",{method:"PUT",csrf:true,body:JSON.stringify({
      profile:{
        name:document.getElementById("settingStoreName").value.trim(),
        tagline:document.getElementById("settingTagline").value.trim(),
        support_phone:document.getElementById("settingSupportPhone").value.trim(),
        street_address:document.getElementById("settingStreetAddress").value.trim(),
        city:document.getElementById("settingCity").value.trim(),
        region:document.getElementById("settingRegion").value.trim(),
        postal_code:document.getElementById("settingPostalCode").value.trim(),
        country_code:document.getElementById("settingCountryCode").value.trim().toUpperCase(),
        latitude:document.getElementById("settingLatitude").value.trim()===""?null:Number(document.getElementById("settingLatitude").value),
        longitude:document.getElementById("settingLongitude").value.trim()===""?null:Number(document.getElementById("settingLongitude").value),
        opening_hours:document.getElementById("settingOpeningHours").value.trim(),
        google_site_verification:document.getElementById("settingGoogleVerification").value.trim(),
        ga4_measurement_id:document.getElementById("settingGa4MeasurementId").value.trim().toUpperCase(),
        primary_color:document.getElementById("settingPrimaryColor").value,
        accent_color:document.getElementById("settingAccentColor").value
      },
      checkout:{
        delivery_enabled:document.getElementById("settingDeliveryEnabled").checked,
        pickup_enabled:document.getElementById("settingPickupEnabled").checked,
        delivery_fee_rial:fee*10,
        minimum_order_rial:minimum*10,
        reservation_minutes:reservation
      }
    })});
    document.getElementById("storeSettingsMessage").textContent="تنظیمات با موفقیت ذخیره شد.";
    document.getElementById("storeSettingsMessage").className="form-message success";
  }catch(error){
    document.getElementById("storeSettingsMessage").textContent=error.message;
    document.getElementById("storeSettingsMessage").className="form-message error";
  }
});
document.getElementById("exportOrdersCsv").addEventListener("click",async()=>{
  const query=new URLSearchParams();
  const from=document.getElementById("reportFromDate").value;
  const to=document.getElementById("reportToDate").value;
  if(from)query.set("from",from);
  if(to)query.set("to",to);
  const button=document.getElementById("exportOrdersCsv");
  button.disabled=true;
  try{
    await downloadCsv("admin/reports/orders.csv"+(query.toString()?"?"+query.toString():""),"orders.csv");
    message("گزارش سفارش‌ها آماده شد.","success");
  }catch(error){message(error.message,"error");}
  finally{button.disabled=false;}
});
document.getElementById("exportStockMovementsCsv").addEventListener("click",async()=>{
  const query=new URLSearchParams();
  const values={
    warehouse_id:document.getElementById("movementWarehouseFilter").value,
    product_id:document.getElementById("movementProductFilter").value,
    movement_type:document.getElementById("movementTypeFilter").value,
    from:document.getElementById("movementFromDate").value,
    to:document.getElementById("movementToDate").value
  };
  Object.entries(values).forEach(([key,value])=>{if(value)query.set(key,value);});
  const button=document.getElementById("exportStockMovementsCsv");
  button.disabled=true;
  try{
    await downloadCsv("admin/reports/stock-movements.csv"+(query.toString()?"?"+query.toString():""),"stock-movements.csv");
    message("گزارش گردش موجودی آماده شد.","success");
  }catch(error){message(error.message,"error");}
  finally{button.disabled=false;}
});

async function loadReport() {
  try {
    const result=await request("admin/reports/overview");
    const data=result.data||{};
    document.getElementById("statRevenue").textContent=money(Number(data.paid_revenue_rial_30d)/10);
    document.getElementById("statPending").textContent=new Intl.NumberFormat("fa-IR").format(Number(data.pending_payment_count)||0);
    document.getElementById("statLowStock").textContent=new Intl.NumberFormat("fa-IR").format(Number(data.low_stock_count)||0);
    const operations=data.operations||{};
    const operationNumber=(key)=>new Intl.NumberFormat("fa-IR").format(Number(operations[key])||0);
    document.getElementById("statWaitingPicker").textContent=operationNumber("waiting_picker");
    document.getElementById("statWaitingPack").textContent=operationNumber("waiting_pack");
    document.getElementById("statWaitingCourier").textContent=operationNumber("waiting_courier");
    document.getElementById("statInTransit").textContent=operationNumber("in_transit");
    document.getElementById("statReadyPickup").textContent=operationNumber("ready_pickup");
    document.getElementById("statDeliveredToday").textContent=operationNumber("delivered_today");
    document.getElementById("statAverageDelivery").textContent=operations.average_delivery_minutes_30d===null||operations.average_delivery_minutes_30d===undefined
      ?"—":operationNumber("average_delivery_minutes_30d")+" دقیقه";
    const days=data.daily_sales||[];
    const max=Math.max(1,...days.map(d=>Number(d.revenue_rial)||0));
    document.getElementById("salesChart").innerHTML=days.map(d=>'<div class="sales-bar-row"><span>'+escapeText(d.day)+'</span><div><i style="width:'+Math.max(2,(Number(d.revenue_rial)||0)/max*100)+'%"></i></div><strong>'+money(Number(d.revenue_rial)/10)+'</strong></div>').join("")||'<p>در این بازه فروشی ثبت نشده است.</p>';
  } catch(error) {
    document.getElementById("salesChart").textContent=error.message;
  }
}
document.getElementById("reloadOperations").addEventListener("click",loadReport);
async function loadMediaLibrary() {
  try {
    const result=await request("admin/media");
    const items=result.data||[];
    const labels={logo:"لوگو",hero:"بنر",aisle:"راهرو",panorama:"پانوراما",category:"دسته‌بندی",product:"محصول",other:"سایر"};
    document.getElementById("mediaLibraryRows").innerHTML=items.map(item=>
      '<article class="media-library-card">'+
        (item.url?'<img src="'+escapeText(item.url)+'" alt="'+escapeText(item.alt_text||"تصویر فروشگاه")+'" loading="lazy">':'<div class="media-library-placeholder">بدون نشانی عمومی</div>')+
        '<div class="media-library-meta"><strong>'+escapeText(item.alt_text||"تصویر بدون عنوان")+'</strong>'+
        '<small>شناسه: '+Number(item.id)+' · '+escapeText(labels[item.media_type]||item.media_type)+'</small>'+
        '<small>'+Number(item.width||0)+' × '+Number(item.height||0)+' پیکسل · '+Number(item.usage_count)+' محل استفاده</small></div>'+
        '<button type="button" class="admin-row-action" data-copy-media-id="'+Number(item.id)+'">کپی شناسه</button>'+
      '</article>'
    ).join("")||'<p>هنوز تصویری بارگذاری نشده است.</p>';
  } catch(error) {
    document.getElementById("mediaLibraryRows").textContent=error.message;
  }
}
document.getElementById("reloadMediaLibrary").addEventListener("click",loadMediaLibrary);
document.getElementById("mediaLibraryRows").addEventListener("click",async event=>{
  const button=event.target.closest("[data-copy-media-id]");
  if(!button)return;
  try{
    await navigator.clipboard.writeText(String(Number(button.dataset.copyMediaId)));
    message("شناسه رسانه کپی شد.","success");
  }catch(_){message("کپی خودکار در این مرورگر در دسترس نیست؛ شناسه را از کارت بخوانید.","error");}
});
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
    const [w,s,l]=await Promise.all([request("admin/warehouses"),request("admin/stock"),request("admin/stock/low")]);
    warehouses=w.data||[];stockRows=s.data||[];lowStockRows=l.data||[];
    renderInventory();
    await Promise.all([loadStockBatches(),loadStockMovements()]);
  } catch(error) {
    document.getElementById("stockRows").innerHTML='<tr><td colspan="6">'+escapeText(error.message)+'</td></tr>';
  }
}
async function loadStockBatches() {
  const state=document.getElementById("stockBatchState").value;
  const days=Number(document.getElementById("stockBatchDays").value)||30;
  const rows=document.getElementById("stockBatchRows");
  try {
    const [batchResult,summaryResult]=await Promise.all([
      request("admin/stock-batches?state="+encodeURIComponent(state)+"&days="+days),
      request("admin/stock-expiry?days="+days)
    ]);
    const summary=summaryResult.data||{};
    const number=value=>new Intl.NumberFormat("fa-IR").format(Number(value)||0);
    document.getElementById("stockExpirySummary").textContent=
      "منقضی‌شده: "+number(summary.expired_on_hand)+" واحد ("+number(summary.expired_batches)+" بچ) · "+
      "در آستانه انقضا: "+number(summary.expiring_available)+" واحد ("+number(summary.expiring_batches)+" بچ) · "+
      "بدون تاریخ انقضا: "+number(summary.unknown_expiry_batches)+" بچ";
    const labels={expired:"منقضی‌شده",expiring:"نزدیک انقضا",valid:"دارای مهلت",unknown:"نامشخص"};
    const classes={expired:"inactive",expiring:"inactive",valid:"active",unknown:""};
    rows.innerHTML=(batchResult.data||[]).map(item=>
      '<tr><td><strong>'+escapeText(item.product_title)+'</strong><small>'+escapeText(item.sku||"")+'</small></td>'+
      '<td>'+escapeText(item.warehouse_title)+'</td><td>'+escapeText(item.batch_number)+'</td>'+
      '<td>'+escapeText(item.expiry_date||"ثبت نشده")+'</td>'+
      '<td>'+number(item.on_hand)+'</td><td>'+number(item.reserved)+'</td>'+
      '<td><strong>'+number(item.available_quantity)+'</strong></td>'+
      '<td><span class="admin-status '+(classes[item.expiry_state]||"")+'">'+(labels[item.expiry_state]||"نامشخص")+'</span></td>'+
      '<td>'+(item.expiry_state==="expired"&&Number(item.available_quantity)>0?'<button class="admin-row-action danger" data-writeoff-batch="'+Number(item.id)+'" data-available="'+Number(item.available_quantity)+'">ثبت امحا</button>':"—")+'</td></tr>'
    ).join("")||'<tr><td colspan="9">برای این فیلتر بچی پیدا نشد.</td></tr>';
  } catch(error) {
    document.getElementById("stockExpirySummary").textContent=error.message;
    rows.innerHTML='<tr><td colspan="9">'+escapeText(error.message)+'</td></tr>';
  }
}
function renderInventory() {
  const warehouseOptions=warehouses.filter(w=>Number(w.is_active)).map(w=>'<option value="'+Number(w.id)+'">'+escapeText(w.title)+'</option>').join("");
  ["receiptWarehouse","adjustWarehouse","transferFrom","transferTo"].forEach(id=>{
    const select=document.getElementById(id);const previous=select.value;
    select.innerHTML=warehouseOptions; if(previous)select.value=previous;
  });
  const productOptions=products.filter(p=>Number(p.is_active)&&p.product_kind!=="variant_parent").map(p=>'<option value="'+Number(p.id)+'">'+escapeText(p.product_kind==="variant"?(p.parent_title||p.title)+" · "+(p.variant_label||"تنوع"):p.title)+' · '+escapeText(p.sku)+'</option>').join("");
  ["receiptProduct","adjustProduct","transferProduct"].forEach(id=>{
    const select=document.getElementById(id);const previous=select.value;
    select.innerHTML=productOptions;if(previous)select.value=previous;
  });
  const movementWarehouse=document.getElementById("movementWarehouseFilter");
  const previousMovementWarehouse=movementWarehouse.value;
  movementWarehouse.innerHTML='<option value="">همه انبارها</option>'+warehouseOptions;
  if(previousMovementWarehouse)movementWarehouse.value=previousMovementWarehouse;
  const movementProduct=document.getElementById("movementProductFilter");
  const previousMovementProduct=movementProduct.value;
  movementProduct.innerHTML='<option value="">همه کالاها</option>'+productOptions;
  if(previousMovementProduct)movementProduct.value=previousMovementProduct;
  document.getElementById("stockRows").innerHTML=stockRows.map(s=>'<tr><td><strong>'+escapeText(s.product_title)+'</strong><small>'+escapeText(s.sku)+'</small></td><td>'+escapeText(s.warehouse_title)+'</td><td>'+new Intl.NumberFormat("fa-IR").format(Number(s.on_hand))+'</td><td>'+new Intl.NumberFormat("fa-IR").format(Number(s.reserved))+'</td><td><strong>'+new Intl.NumberFormat("fa-IR").format(Number(s.available_quantity))+'</strong></td><td><span class="admin-status '+(Number(s.is_low_stock)?"inactive":"active")+'">'+new Intl.NumberFormat("fa-IR").format(Number(s.low_stock_threshold))+(Number(s.is_low_stock)?" · هشدار":"")+'</span></td></tr>').join("")||'<tr><td colspan="6">هنوز موجودی ثبت نشده است.</td></tr>';
  const formatQuantity=value=>new Intl.NumberFormat("fa-IR").format(Number(value)||0);
  document.getElementById("stockLowSummary").textContent=lowStockRows.length
    ? formatQuantity(lowStockRows.length)+" هشدار فعال در انبارها شناسایی شد. برای هر مورد می‌توانید فرم سفارش خرید را با کالای مربوطه آماده کنید."
    : "در حال حاضر هیچ کالایی پایین‌تر از حد هشدار تعریف‌شده نیست.";
  document.getElementById("stockLowRows").innerHTML=lowStockRows.map(item=>
    '<tr><td><strong>'+escapeText(item.product_title)+'</strong><small>'+escapeText(item.sku)+'</small></td>'+
    '<td>'+escapeText(item.warehouse_title)+'</td>'+
    '<td>'+formatQuantity(item.available_quantity)+' '+escapeText(item.unit_label||"")+'</td>'+
    '<td>'+formatQuantity(item.low_stock_threshold)+'</td>'+
    '<td><strong>'+formatQuantity(item.shortage_quantity)+'</strong></td>'+
    '<td><button class="admin-row-action" type="button" data-reorder-product="'+Number(item.product_id)+'" data-reorder-quantity="'+Number(item.shortage_quantity)+'">آماده‌سازی سفارش خرید</button></td></tr>'
  ).join("")||'<tr><td colspan="6">هشدار کمبود موجودی ثبت نشده است.</td></tr>';
}

async function loadStockMovements(){
  const params=new URLSearchParams();
  const filters=[
    ["warehouse_id","movementWarehouseFilter"],["product_id","movementProductFilter"],
    ["movement_type","movementTypeFilter"],["from","movementFromDate"],["to","movementToDate"]
  ];
  filters.forEach(([key,id])=>{const value=document.getElementById(id).value;if(value)params.set(key,value);});
  const rows=document.getElementById("stockMovementRows");
  try{
    const result=await request("admin/stock-movements"+(params.toString()?"?"+params.toString():""));
    const data=result.data||[];
    const labels={receipt:"ورود موجودی",transfer_in:"ورود انتقال",transfer_out:"خروج انتقال",adjustment:"اصلاح موجودی",reservation:"رزرو سفارش",release:"آزادسازی رزرو",sale:"فروش",return:"مرجوعی مشتری",supplier_return:"مرجوعی تأمین‌کننده",expiry_writeoff:"امحای کالای منقضی"};
    const number=value=>new Intl.NumberFormat("fa-IR").format(Math.abs(Number(value)||0));
    rows.innerHTML=data.map(item=>{
      const quantity=Number(item.quantity)||0;
      const signed=(quantity>0?"+":quantity<0?"−":"")+number(quantity);
      const reference=item.reference_type?(item.reference_type+(item.reference_id?" #"+Number(item.reference_id):"")):"—";
      return '<tr><td>'+escapeText(item.created_at||"")+'</td>'+
        '<td><strong>'+escapeText(item.product_title)+'</strong><small>'+escapeText(item.sku||"")+'</small></td>'+
        '<td>'+escapeText(item.warehouse_title)+'</td>'+
        '<td>'+escapeText(labels[item.movement_type]||item.movement_type)+'</td>'+
        '<td><strong>'+signed+'</strong></td>'+
        '<td>'+escapeText(item.actor_name||"سیستم")+'</td>'+
        '<td>'+escapeText(item.reason||reference)+'</td></tr>';
    }).join("")||'<tr><td colspan="7">برای این فیلتر سابقه‌ای ثبت نشده است.</td></tr>';
    document.getElementById("stockMovementSummary").textContent=
      new Intl.NumberFormat("fa-IR").format(data.length)+" گردش اخیر نمایش داده می‌شود؛ حداکثر ۳۰۰ رکورد در هر جست‌وجو.";
  }catch(error){
    document.getElementById("stockMovementSummary").textContent=error.message;
    rows.innerHTML='<tr><td colspan="7">'+escapeText(error.message)+'</td></tr>';
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
  const labels={pending_payment:"در انتظار پرداخت",paid:"پرداخت‌شده",accepted:"پذیرفته‌شده",picking:"در حال جمع‌آوری",packed:"بسته‌بندی‌شده",ready_pickup:"آماده تحویل",dispatched:"آماده ارسال",out_for_delivery:"در مسیر ارسال",delivered:"تحویل‌شده",cancelled:"لغوشده",refunded:"مستردشده"};
  const next={paid:"accepted",accepted:"picking",picking:"packed",packed:"dispatched",ready_pickup:"delivered",dispatched:"out_for_delivery",out_for_delivery:"delivered"};
  document.getElementById("orderRows").innerHTML=orders.map(o=>{
    let nextStatus=next[o.status];
    if(o.status==="packed"&&o.fulfillment==="pickup")nextStatus="ready_pickup";
    const action='<button class="admin-row-action" data-admin-order-detail="'+Number(o.id)+'">جزئیات</button>'+(nextStatus?'<button class="admin-row-action" data-next-order="'+Number(o.id)+'" data-next-status="'+nextStatus+'">مرحله بعد</button>':"");
    return '<tr><td><strong>'+escapeText(o.order_number)+'</strong><small>'+escapeText(o.created_at||"")+'</small></td><td>'+escapeText(o.customer_name)+'<small>'+escapeText(o.customer_mobile)+'</small></td><td>'+new Intl.NumberFormat("fa-IR").format(Number(o.item_count)||0)+'</td><td>'+money(Number(o.total_rial)/10)+'</td><td><span class="admin-status '+(["delivered","out_for_delivery"].includes(o.status)?"active":"inactive")+'">'+(labels[o.status]||escapeText(o.status))+'</span></td><td>'+action+'</td></tr>';
  }).join("")||'<tr><td colspan="6">هنوز سفارشی ثبت نشده است.</td></tr>';
}
function renderOrderAssignments(order,data) {
  const panel=document.getElementById("orderAssignmentPanel");
  if(!panel)return;
  const staff=data.staff||{};
  const assignments=data.assignments||[];
  const renderOptions=(rows)=>'<option value="">انتخاب کارمند</option>'+(rows||[]).map(person=>'<option value="'+Number(person.id)+'">'+escapeText(person.full_name)+' · '+escapeText(person.mobile)+'</option>').join("");
  const renderHistory=assignments.map(item=>{
    const type=item.assignment_type==="picker"?"جمع‌آوری":"پیک";
    const status={active:"فعال",completed:"تکمیل‌شده",cancelled:"لغوشده",replaced:"جایگزین‌شده"}[item.status]||item.status;
    const canComplete=item.status==="active"&&(item.assignment_type==="picker"?order.status==="picking":order.status==="out_for_delivery");
    const action=canComplete?'<button class="admin-row-action" data-complete-assignment="'+Number(item.id)+'" data-assignment-type="'+escapeText(item.assignment_type)+'">ثبت تکمیل وظیفه</button>':
      (item.status==="active"?"<small>در انتظار مرحله بعدی سفارش</small>":"");
    return '<li><strong>'+type+': '+escapeText(item.full_name)+'</strong> · '+status+' · '+escapeText(item.assigned_at||"")+action+'</li>';
  }).join("")||"<li>هنوز نیرویی به این سفارش تخصیص داده نشده است.</li>";
  const canAssign=!["pending_payment","cancelled","refunded","delivered"].includes(order.status);
  const picker=canAssign&&["paid","accepted","picking"].includes(order.status)
    ?'<label>مسئول جمع‌آوری<select id="orderPickerSelect">'+renderOptions(staff.picker)+'</select></label><button class="admin-row-action" data-assign-type="picker">تخصیص جمع‌آورنده</button>':"";
  const courier=canAssign&&order.fulfillment==="delivery"&&["paid","accepted","picking","packed","dispatched"].includes(order.status)
    ?'<label>پیک سفارش<select id="orderCourierSelect">'+renderOptions(staff.courier)+'</select></label><button class="admin-row-action" data-assign-type="courier">تخصیص پیک</button>':"";
  const deliveryLabels={assigned:"پیک تخصیص یافت",out_for_delivery:"شروع مسیر ارسال",delivered:"تحویل سفارش"};
  const deliveryEvents=data.delivery_events||[];
  const tracking=deliveryEvents.length
    ?'<div class="admin-delivery-events"><h5>تاریخچه ارسال</h5><ol>'+deliveryEvents.map(event=>'<li><strong>'+escapeText(deliveryLabels[event.event_type]||event.event_type)+'</strong><small>'+escapeText(event.created_at||"")+'</small>'+(event.note?'<small>'+escapeText(event.note)+'</small>':"")+'</li>').join("")+'</ol></div>'
    :'';
  panel.innerHTML='<h4>تخصیص نیرو و تحویل</h4><ul>'+renderHistory+'</ul><div class="admin-form-grid">'+picker+courier+'</div>'+tracking+'<p class="admin-hint">فقط کارکنان فعال دارای مجوز جمع‌آوری یا ارسال در همین فروشگاه نمایش داده می‌شوند.</p>';
}
function renderProducts() {
  const query=document.getElementById("productSearch").value.trim().toLocaleLowerCase();
  const rows=products.filter(p=>!query||String(p.title+" "+p.parent_title+" "+p.variant_label+" "+p.sku).toLocaleLowerCase().includes(query));
  document.getElementById("productRows").innerHTML=rows.map(p=>{
    const isVariant=p.product_kind==="variant";
    const isParent=p.product_kind==="variant_parent";
    const title=isVariant?(p.parent_title||p.title)+" · "+(p.variant_label||"تنوع"):p.title;
    const price=isParent?(Number(p.price_rial)>0?"از "+money(Number(p.price_rial)/10):"تنوع تعریف نشده"):money(Number(p.price_rial)/10);
    const actions=isParent?'<button class="admin-row-action" data-manage-variants="'+Number(p.id)+'">مدیریت تنوع‌ها</button><button class="admin-row-action" data-edit-product="'+Number(p.id)+'">ویرایش</button>'+(Number(p.is_active)?'<button class="admin-row-action danger" data-disable-product="'+Number(p.id)+'">غیرفعال‌سازی</button>':""):
      (isVariant?'<span class="admin-status">تنوع</span>':'<button class="admin-row-action" data-edit-product="'+Number(p.id)+'">ویرایش</button>')+
      (Number(p.is_active)?'<button class="admin-row-action danger" data-disable-product="'+Number(p.id)+'">غیرفعال‌سازی</button>':"");
    return '<tr><td><strong>'+escapeText(title)+'</strong><small>'+escapeText(p.brand||"")+'</small></td><td>'+escapeText(p.sku)+'<small>'+escapeText(p.barcode||"بدون بارکد")+'</small></td><td>'+price+'</td><td><span class="admin-status '+(Number(p.is_active)?"active":"inactive")+'">'+(Number(p.is_active)?"فعال":"غیرفعال")+'</span></td><td>'+actions+'</td></tr>';
  }).join("")||'<tr><td colspan="5">محصولی پیدا نشد.</td></tr>';
}
function renderCategories() {
  document.getElementById("productCategory").innerHTML='<option value="">بدون دسته‌بندی</option>'+categories.filter(c=>Number(c.is_active)).map(c=>'<option value="'+Number(c.id)+'">'+escapeText(c.title)+'</option>').join("");
  document.getElementById("categoryRows").innerHTML=categories.map(c=>'<article><div><strong>'+escapeText(c.title)+'</strong><small>'+escapeText(c.slug)+'</small><small>'+escapeText(c.seo_title||"بدون عنوان سئو")+'</small></div><span class="admin-status '+(Number(c.is_active)?"active":"inactive")+'">'+(Number(c.is_active)?"فعال":"غیرفعال")+'</span><button class="admin-row-action" data-edit-category="'+Number(c.id)+'">ویرایش</button>'+(Number(c.is_active)?'<button class="admin-row-action danger" data-disable-category="'+Number(c.id)+'">غیرفعال‌سازی</button>':"")+'</article>').join("")||'<p>هنوز دسته‌بندی ثبت نشده است.</p>';
}
function escapeText(value) {
  return String(value??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
}
function money(value) {return new Intl.NumberFormat("fa-IR").format(Math.round(value||0))+" تومان";}
function syncProductKindFields(){
  const parent=document.getElementById("productKind").value==="variant_parent";
  const price=document.getElementById("productPrice"),barcode=document.getElementById("productBarcode"),unit=document.getElementById("productUnit");
  price.disabled=parent;price.required=!parent;if(parent)price.value="0";
  barcode.disabled=parent;if(parent)barcode.value="";
  if(parent)unit.value="گزینه‌ها";
  unit.disabled=parent;
}
document.getElementById("productKind").addEventListener("change",syncProductKindFields);
function resetProductForm() {
  document.getElementById("productForm").reset();
  document.getElementById("productId").value="";
  document.getElementById("productSku").readOnly=false;
  document.getElementById("productKind").disabled=false;
  document.getElementById("productKind").value="simple";
  document.getElementById("productFormTitle").textContent="افزودن محصول";
  document.getElementById("productForm").hidden=true;
  syncProductKindFields();
}
document.getElementById("newProductButton").addEventListener("click",()=>{
  resetProductForm();document.getElementById("productForm").hidden=false;document.getElementById("productForm").scrollIntoView({behavior:"smooth"});
});
document.getElementById("cancelProductForm").addEventListener("click",resetProductForm);
document.getElementById("productSearch").addEventListener("input",renderProducts);
document.getElementById("reloadProducts").addEventListener("click",loadAll);
function resetVariantForm(){
  document.getElementById("variantForm").reset();
  document.getElementById("variantId").value="";
  document.getElementById("variantUnit").value="عدد";
  document.getElementById("variantLowStock").value="5";
  document.getElementById("variantActive").checked=true;
  document.getElementById("variantWeighted").checked=false;
}
async function loadAdminVariants(){
  if(!activeVariantParent)return;
  try{
    const result=await request("admin/products/"+Number(activeVariantParent.id)+"/variants");
    adminVariants=result.data||[];
    document.getElementById("variantRows").innerHTML=adminVariants.map(v=>
      '<article><div><strong>'+escapeText(v.variant_label)+'</strong><small>'+escapeText(v.sku)+' · '+money(Number(v.price_rial)/10)+' / '+escapeText(v.unit_label)+'</small><small>موجودی قابل فروش: '+Number(v.available_quantity)+'</small></div><span class="admin-status '+(v.is_active?"active":"inactive")+'">'+(v.is_active?"فعال":"غیرفعال")+'</span><button class="admin-row-action" data-edit-variant="'+Number(v.id)+'">ویرایش</button>'+(v.is_active?'<button class="admin-row-action danger" data-disable-variant="'+Number(v.id)+'">غیرفعال‌سازی</button>':"")+'</article>'
    ).join("")||'<p>برای این محصول هنوز تنوعی تعریف نشده است.</p>';
  }catch(error){
    document.getElementById("variantMessage").textContent=error.message;
    document.getElementById("variantMessage").className="form-message error";
  }
}
function openVariantManager(parent){
  activeVariantParent=parent;
  document.getElementById("variantManagerTitle").textContent="تنوع‌های "+parent.title;
  document.getElementById("variantManager").hidden=false;
  resetVariantForm();loadAdminVariants();
  document.getElementById("variantManager").scrollIntoView({behavior:"smooth"});
}
document.getElementById("closeVariantManager").addEventListener("click",()=>{
  document.getElementById("variantManager").hidden=true;activeVariantParent=null;adminVariants=[];
});
document.getElementById("resetVariantForm").addEventListener("click",resetVariantForm);
document.getElementById("variantForm").addEventListener("submit",async event=>{
  event.preventDefault();if(!activeVariantParent)return;
  const id=document.getElementById("variantId").value;
  const price=Number(document.getElementById("variantPrice").value);
  const compareRaw=document.getElementById("variantCompareAt").value.trim();
  const threshold=Number(document.getElementById("variantLowStock").value);
  if(!Number.isSafeInteger(price)||price<1||(compareRaw!==""&&(!Number.isSafeInteger(Number(compareRaw))||Number(compareRaw)<price))||!Number.isInteger(threshold)||threshold<0||threshold>1000000){
    document.getElementById("variantMessage").textContent="قیمت و حد هشدار موجودی را بررسی کنید؛ قیمت قبل از تخفیف باید از قیمت فروش کمتر نباشد.";
    document.getElementById("variantMessage").className="form-message error";return;
  }
  const payload={
    variant_label:document.getElementById("variantLabel").value.trim(),
    sku:document.getElementById("variantSku").value.trim(),
    barcode:document.getElementById("variantBarcode").value.trim(),
    price_rial:Math.round(price*10),
    compare_at_rial:compareRaw===""?null:Math.round(Number(compareRaw)*10),
    unit_label:document.getElementById("variantUnit").value.trim(),
    low_stock_threshold:threshold,
    is_weighted:document.getElementById("variantWeighted").checked,
    is_active:document.getElementById("variantActive").checked
  };
  try{
    await request("admin/products/"+Number(activeVariantParent.id)+"/variants"+(id?"/"+id:""),{
      method:id?"PATCH":"POST",body:JSON.stringify(payload),csrf:true
    });
    resetVariantForm();await loadAdminVariants();await loadAll();
    document.getElementById("variantMessage").textContent="تنوع محصول ذخیره شد.";
    document.getElementById("variantMessage").className="form-message success";
  }catch(error){
    document.getElementById("variantMessage").textContent=error.message;
    document.getElementById("variantMessage").className="form-message error";
  }
});
document.getElementById("variantRows").addEventListener("click",async event=>{
  const edit=event.target.closest("[data-edit-variant]");
  const disable=event.target.closest("[data-disable-variant]");
  if(edit){
    const v=adminVariants.find(item=>Number(item.id)===Number(edit.dataset.editVariant));if(!v)return;
    document.getElementById("variantId").value=v.id;
    document.getElementById("variantLabel").value=v.variant_label;
    document.getElementById("variantSku").value=v.sku;
    document.getElementById("variantBarcode").value=v.barcode||"";
    document.getElementById("variantPrice").value=Math.round(Number(v.price_rial)/10);
    document.getElementById("variantCompareAt").value=v.compare_at_rial===null?"":Math.round(Number(v.compare_at_rial)/10);
    document.getElementById("variantUnit").value=v.unit_label;
    document.getElementById("variantLowStock").value=Number(v.low_stock_threshold);
    document.getElementById("variantWeighted").checked=Boolean(v.is_weighted);
    document.getElementById("variantActive").checked=Boolean(v.is_active);
    document.getElementById("variantForm").scrollIntoView({behavior:"smooth"});
  }
  if(disable&&confirm("این تنوع از فروش خارج شود؟")){
    try{
      await request("admin/products/"+Number(activeVariantParent.id)+"/variants/"+Number(disable.dataset.disableVariant),{method:"DELETE",csrf:true});
      await loadAdminVariants();await loadAll();
      document.getElementById("variantMessage").textContent="تنوع غیرفعال شد.";
      document.getElementById("variantMessage").className="form-message success";
    }catch(error){
      document.getElementById("variantMessage").textContent=error.message;
      document.getElementById("variantMessage").className="form-message error";
    }
  }
});
document.getElementById("productRows").addEventListener("click",async e=>{
  const manage=e.target.closest("[data-manage-variants]");
  const edit=e.target.closest("[data-edit-product]");
  const disable=e.target.closest("[data-disable-product]");
  if(manage){const parent=products.find(item=>Number(item.id)===Number(manage.dataset.manageVariants));if(parent)openVariantManager(parent);return;}
  if(edit){
    const p=products.find(x=>Number(x.id)===Number(edit.dataset.editProduct));if(!p)return;
    document.getElementById("productId").value=p.id;
    document.getElementById("productSku").value=p.sku;
    document.getElementById("productBarcode").value=p.barcode||"";
    document.getElementById("productSku").readOnly=true;
    document.getElementById("productTitle").value=p.title;
    document.getElementById("productKind").value=p.product_kind==="variant_parent"?"variant_parent":"simple";
    document.getElementById("productKind").disabled=true;
    document.getElementById("productPrice").value=p.product_kind==="variant_parent"?"0":Math.round(Number(p.price_rial)/10);
    document.getElementById("productCategory").value=p.category_id||"";
    document.getElementById("productBrand").value=p.brand||"";
    document.getElementById("productUnit").value=p.product_kind==="variant_parent"?"گزینه‌ها":(p.unit_label||"عدد");
    syncProductKindFields();
    document.getElementById("productLowStockThreshold").value=Number(p.low_stock_threshold??5);
    document.getElementById("productDescription").value=p.description||"";
    document.getElementById("productSeoSlug").value=p.seo_slug||"";
    document.getElementById("productSeoTitle").value=p.seo_title||"";
    document.getElementById("productSeoDescription").value=p.seo_description||"";
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
    barcode:document.getElementById("productBarcode").value.trim(),
    low_stock_threshold:Number(document.getElementById("productLowStockThreshold").value),
    title:document.getElementById("productTitle").value.trim(),
    product_kind:document.getElementById("productKind").value,
    price_rial:document.getElementById("productKind").value==="variant_parent"?0:Math.round(Number(document.getElementById("productPrice").value)*10),
    category_id:document.getElementById("productCategory").value||null,
    brand:document.getElementById("productBrand").value.trim(),
    unit_label:document.getElementById("productUnit").value.trim()||"عدد",
    description:document.getElementById("productDescription").value.trim(),
    seo_slug:document.getElementById("productSeoSlug").value.trim().toLowerCase(),
    seo_title:document.getElementById("productSeoTitle").value.trim(),
    seo_description:document.getElementById("productSeoDescription").value.trim(),
    media_id:document.getElementById("productMediaId").value?Number(document.getElementById("productMediaId").value):null,
    is_weighted:false,is_active:true
  };
  try{
    await request(id?"admin/products/"+id:"admin/products",{method:id?"PATCH":"POST",body:JSON.stringify(payload),csrf:true});
    resetProductForm();await loadAll();message("اطلاعات محصول ذخیره شد.","success");
  }catch(error){message(error.message,"error");}
});
function resetCategoryForm(){
  document.getElementById("categoryForm").reset();
  document.getElementById("categoryId").value="";
  document.getElementById("categoryFormTitle").textContent="افزودن دسته‌بندی";
  document.getElementById("categorySubmitButton").textContent="افزودن دسته";
  document.getElementById("categoryCancelButton").hidden=true;
}
document.getElementById("categoryCancelButton").addEventListener("click",resetCategoryForm);
document.getElementById("categoryForm").addEventListener("submit",async e=>{
  e.preventDefault();
  const id=document.getElementById("categoryId").value;
  const payload={
    title:document.getElementById("categoryTitle").value.trim(),
    slug:document.getElementById("categorySlug").value.trim(),
    description:document.getElementById("categoryDescription").value.trim(),
    seo_title:document.getElementById("categorySeoTitle").value.trim(),
    seo_description:document.getElementById("categorySeoDescription").value.trim(),
    sort_order:categories.find(c=>Number(c.id)===Number(id))?.sort_order??(categories.length+1),
    is_active:id?Boolean(categories.find(c=>Number(c.id)===Number(id))?.is_active):true,
    media_id:document.getElementById("categoryMediaId").value?Number(document.getElementById("categoryMediaId").value):null
  };
  try{
    await request(id?"admin/categories/"+id:"admin/categories",{method:id?"PATCH":"POST",body:JSON.stringify(payload),csrf:true});
    resetCategoryForm();await loadAll();message(id?"دسته‌بندی و اطلاعات سئوی آن به‌روزرسانی شد.":"دسته‌بندی افزوده شد.","success");
  }catch(error){message(error.message,"error");}
});
document.getElementById("categoryRows").addEventListener("click",async e=>{
  const edit=e.target.closest("[data-edit-category]");
  if(edit){
    const category=categories.find(item=>Number(item.id)===Number(edit.dataset.editCategory));
    if(!category)return;
    document.getElementById("categoryId").value=category.id;
    document.getElementById("categoryTitle").value=category.title||"";
    document.getElementById("categorySlug").value=category.slug||"";
    document.getElementById("categoryDescription").value=category.description||"";
    document.getElementById("categorySeoTitle").value=category.seo_title||"";
    document.getElementById("categorySeoDescription").value=category.seo_description||"";
    document.getElementById("categoryMediaId").value=category.image_media_id||"";
    document.getElementById("categoryFormTitle").textContent="ویرایش دسته‌بندی و سئو";
    document.getElementById("categorySubmitButton").textContent="ذخیره تغییرات";
    document.getElementById("categoryCancelButton").hidden=false;
    document.getElementById("categoryForm").scrollIntoView({behavior:"smooth"});
    return;
  }
  const b=e.target.closest("[data-disable-category]");
  if(!b||!confirm("این دسته‌بندی غیرفعال شود؟"))return;
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

document.getElementById("adminOrderDetail").addEventListener("click",async e=>{
  const box=document.getElementById("adminOrderDetail");
  const orderId=Number(box.dataset.orderId);
  const assign=e.target.closest("[data-assign-type]");
  const complete=e.target.closest("[data-complete-assignment]");
  const refund=e.target.closest("[data-refund-order]");
  try{
    if(refund){
      const reason=window.prompt("دلیل بازپرداخت کامل به کیف پول را وارد کنید (حداقل ۱۲ نویسه):");
      if(!reason)return;
      if(reason.trim().length<12){message("دلیل بازپرداخت باید حداقل ۱۲ نویسه باشد.","error");return;}
      const result=await request("admin/orders/"+orderId+"/refund-wallet",{method:"POST",body:JSON.stringify({reason:reason.trim()}),csrf:true});
      message(result.message+" مبلغ: "+money(Number(result.data?.amount_rial||0)/10),"success");
      await loadOrders();
      const updated=orders.find(o=>Number(o.id)===orderId);
      if(updated){
        const box=document.getElementById("adminOrderDetail");
        box.innerHTML='<h3>سفارش '+escapeText(updated.order_number)+'</h3><p>وضعیت جدید: '+escapeText(updated.status)+'</p><p>بازپرداخت کامل به کیف پول ثبت شد.</p>';
      }
    }else if(assign){
      const type=assign.dataset.assignType;
      const select=document.getElementById(type==="picker"?"orderPickerSelect":"orderCourierSelect");
      const assigneeId=Number(select?.value||0);
      if(!assigneeId){message("ابتدا کارمند موردنظر را انتخاب کنید.","error");return;}
      await request("admin/orders/"+orderId+"/assignments",{method:"POST",body:JSON.stringify({assignment_type:type,assignee_user_id:assigneeId}),csrf:true});
      const refreshed=await request("admin/orders/"+orderId+"/assignments");
      const order=orders.find(o=>Number(o.id)===orderId);
      if(order)renderOrderAssignments(order,refreshed.data||{});
      message("مسئول سفارش تخصیص یافت.","success");
    }else if(complete){
      let body={};
      if(complete.dataset.assignmentType==="courier"){
        const recipientName=window.prompt("نام تحویل‌گیرنده را وارد کنید:");
        if(recipientName===null)return;
        if(recipientName.trim().length<2){message("نام تحویل‌گیرنده معتبر نیست.","error");return;}
        const note=window.prompt("یادداشت اختیاری تحویل (در صورت نیاز):")||"";
        body={recipient_name:recipientName.trim(),note:note.trim()};
      }
      await request("admin/orders/"+orderId+"/assignments/"+Number(complete.dataset.completeAssignment)+"/complete",{method:"POST",body:JSON.stringify(body),csrf:true});
      await loadOrders();
      const refreshed=await request("admin/orders/"+orderId+"/assignments");
      const order=orders.find(o=>Number(o.id)===orderId);
      if(order)renderOrderAssignments(order,refreshed.data||{});
      message(complete.dataset.assignmentType==="courier"?"تحویل سفارش ثبت شد.":"تکمیل وظیفه ثبت شد.","success");
    }
  }catch(error){message(error.message,"error");}
});
document.getElementById("orderRows").addEventListener("click",async e=>{
  const detail=e.target.closest("[data-admin-order-detail]");
  if(detail){
    const order=orders.find(o=>Number(o.id)===Number(detail.dataset.adminOrderDetail));
    if(order){
      const pickupTime=order.pickup_slot_starts_at_label?(order.pickup_slot_title+" · "+order.pickup_slot_starts_at_label+" تا "+order.pickup_slot_ends_at_label):"";
      const address=order.fulfillment==="delivery"?[order.delivery_recipient,order.delivery_mobile,order.province,order.city,order.delivery_address,order.postal_code].filter(Boolean).join(" · "):("تحویل حضوری"+(pickupTime?" · "+pickupTime:""));
      const box=document.getElementById("adminOrderDetail");
      const couponInfo=Number(order.discount_rial)>0?'<p>کد تخفیف: '+escapeText(order.coupon_code||"—")+' · مبلغ تخفیف: '+money(Number(order.discount_rial)/10)+'</p>':"";
      box.hidden=false;
      box.dataset.orderId=String(order.id);
      const refundable=["paid","accepted","picking","packed","ready_pickup","dispatched","out_for_delivery","delivered"].includes(order.status);
      const refundControl=refundable?'<div class="refund-warning"><p>بازپرداخت فقط به اعتبار کیف پول انجام می‌شود؛ تراکنش بانکی به‌صورت خودکار برگشت نمی‌خورد و موجودی کالا نیز خودکار به انبار برنمی‌گردد.</p><button class="admin-row-action" data-refund-order="'+Number(order.id)+'">بازپرداخت کامل به کیف پول</button></div>':"";
      box.innerHTML='<h3>سفارش '+escapeText(order.order_number)+'</h3><p>مشتری: '+escapeText(order.customer_name)+' · '+escapeText(order.customer_mobile)+'</p><p>نشانی / روش دریافت: '+escapeText(address)+'</p>'+couponInfo+'<p>توضیحات: '+escapeText(order.customer_note||"—")+'</p>'+refundControl+'<section id="orderAssignmentPanel"><p>در حال دریافت مسئولیت‌های سفارش...</p></section>';
      try{
        const result=await request("admin/orders/"+Number(order.id)+"/assignments");
        renderOrderAssignments(order,result.data||{});
      }catch(error){
        document.getElementById("orderAssignmentPanel").innerHTML='<p>'+escapeText(error.message)+'</p>';
      }
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
document.getElementById("reloadStockMovements").addEventListener("click",loadStockMovements);
document.getElementById("applyMovementFilters").addEventListener("click",()=>{
  const from=document.getElementById("movementFromDate").value;
  const to=document.getElementById("movementToDate").value;
  if(from&&to&&from>to){message("تاریخ شروع نباید بعد از تاریخ پایان باشد.","error");return;}
  loadStockMovements();
});
document.getElementById("stockLowRows").addEventListener("click",event=>{
  const button=event.target.closest("[data-reorder-product]");
  if(!button)return;
  const productId=Number(button.dataset.reorderProduct);
  const suggestedQuantity=Math.max(1,Number(button.dataset.reorderQuantity)||1);
  showPanel("purchasing");
  document.getElementById("purchaseProduct").value=String(productId);
  document.getElementById("purchaseQuantity").value=String(suggestedQuantity);
  document.getElementById("purchaseOrderForm").scrollIntoView({behavior:"smooth",block:"center"});
  message("کالا و تعداد پیشنهادی در فرم سفارش خرید قرار گرفت؛ تأمین‌کننده و قیمت را تکمیل کنید.");
});
document.getElementById("reloadStockBatches").addEventListener("click",loadStockBatches);
document.getElementById("stockBatchRows").addEventListener("click",async e=>{
  const button=e.target.closest("[data-writeoff-batch]");
  if(!button)return;
  const available=Number(button.dataset.available)||0;
  const rawQuantity=window.prompt("تعداد امحا (حداکثر "+available+")",String(available));
  if(rawQuantity===null)return;
  const quantity=Number(rawQuantity);
  if(!Number.isSafeInteger(quantity)||quantity<1||quantity>available){message("تعداد امحا معتبر نیست.","error");return;}
  const reason=window.prompt("دلیل امحا را وارد کنید:","کالای منقضی‌شده");
  if(reason===null||!reason.trim())return;
  if(!window.confirm("امحای "+quantity+" واحد از این بچ ثبت شود؟ این عملیات قابل برگشت خودکار نیست."))return;
  button.disabled=true;
  try{
    await request("admin/stock-batches/writeoff",{method:"POST",body:JSON.stringify({batch_id:Number(button.dataset.writeoffBatch),quantity,reason:reason.trim()}),csrf:true});
    await loadInventory();await loadStockBatches();message("امحای کالای منقضی‌شده ثبت شد.","success");
  }catch(error){message(error.message,"error");button.disabled=false;}
});
document.getElementById("stockBatchState").addEventListener("change",loadStockBatches);
document.getElementById("stockBatchDays").addEventListener("change",loadStockBatches);
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
      batch_number:document.getElementById("receiptBatchNumber").value.trim(),
      expiry_date:document.getElementById("receiptExpiryDate").value||null,
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
      batch_number:document.getElementById("adjustBatchNumber").value.trim(),
      expiry_date:document.getElementById("adjustExpiryDate").value||null,
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
    await loadMediaLibrary();
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
