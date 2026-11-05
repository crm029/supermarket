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
async function loadReport() {
  try {
    const result=await request("admin/reports/overview");
    const data=result.data||{};
    document.getElementById("statRevenue").textContent=money(Number(data.paid_revenue_rial_30d)/10);
    document.getElementById("statPending").textContent=new Intl.NumberFormat("fa-IR").format(Number(data.pending_payment_count)||0);
    document.getElementById("statLowStock").textContent=new Intl.NumberFormat("fa-IR").format(Number(data.low_stock_count)||0);
    const days=data.daily_sales||[];
    const max=Math.max(1,...days.map(d=>Number(d.revenue_rial)||0));
    document.getElementById("salesChart").innerHTML=days.map(d=>'<div class="sales-bar-row"><span>'+escapeText(d.day)+'</span><div><i style="width:'+Math.max(2,(Number(d.revenue_rial)||0)/max*100)+'%"></i></div><strong>'+money(Number(d.revenue_rial)/10)+'</strong></div>').join("")||'<p>در این بازه فروشی ثبت نشده است.</p>';
  } catch(error) {
    document.getElementById("salesChart").textContent=error.message;
  }
}
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
    const [w,s]=await Promise.all([request("admin/warehouses"),request("admin/stock")]);
    warehouses=w.data||[];stockRows=s.data||[];
    renderInventory();
  } catch(error) {
    document.getElementById("stockRows").innerHTML='<tr><td colspan="6">'+escapeText(error.message)+'</td></tr>';
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
  document.getElementById("stockRows").innerHTML=stockRows.map(s=>'<tr><td><strong>'+escapeText(s.product_title)+'</strong><small>'+escapeText(s.sku)+'</small></td><td>'+escapeText(s.warehouse_title)+'</td><td>'+new Intl.NumberFormat("fa-IR").format(Number(s.on_hand))+'</td><td>'+new Intl.NumberFormat("fa-IR").format(Number(s.reserved))+'</td><td><strong>'+new Intl.NumberFormat("fa-IR").format(Number(s.available_quantity))+'</strong></td><td><span class="admin-status '+(Number(s.is_low_stock)?"inactive":"active")+'">'+new Intl.NumberFormat("fa-IR").format(Number(s.low_stock_threshold))+(Number(s.is_low_stock)?" · هشدار":"")+'</span></td></tr>').join("")||'<tr><td colspan="6">هنوز موجودی ثبت نشده است.</td></tr>';
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
    '<tr><td><strong>'+escapeText(p.title)+'</strong><small>'+escapeText(p.brand||"")+'</small></td><td>'+escapeText(p.sku)+'<small>'+escapeText(p.barcode||"بدون بارکد")+'</small></td><td>'+money(Number(p.price_rial)/10)+'</td><td><span class="admin-status '+(Number(p.is_active)?"active":"inactive")+'">'+(Number(p.is_active)?"فعال":"غیرفعال")+'</span></td><td><button class="admin-row-action" data-edit-product="'+Number(p.id)+'">ویرایش</button>'+(Number(p.is_active)?'<button class="admin-row-action danger" data-disable-product="'+Number(p.id)+'">غیرفعال‌سازی</button>':"")+'</td></tr>'
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
  document.getElementById("productSku").readOnly=false;
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
    document.getElementById("productBarcode").value=p.barcode||"";
    document.getElementById("productSku").readOnly=true;
    document.getElementById("productTitle").value=p.title;
    document.getElementById("productPrice").value=Math.round(Number(p.price_rial)/10);
    document.getElementById("productCategory").value=p.category_id||"";
    document.getElementById("productBrand").value=p.brand||"";
    document.getElementById("productUnit").value=p.unit_label||"عدد";
    document.getElementById("productLowStockThreshold").value=Number(p.low_stock_threshold??5);
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
    barcode:document.getElementById("productBarcode").value.trim(),
    low_stock_threshold:Number(document.getElementById("productLowStockThreshold").value),
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
      const pickupTime=order.pickup_slot_starts_at_label?(order.pickup_slot_title+" · "+order.pickup_slot_starts_at_label+" تا "+order.pickup_slot_ends_at_label):"";
      const address=order.fulfillment==="delivery"?[order.delivery_recipient,order.delivery_mobile,order.province,order.city,order.delivery_address,order.postal_code].filter(Boolean).join(" · "):("تحویل حضوری"+(pickupTime?" · "+pickupTime:""));
      const box=document.getElementById("adminOrderDetail");
      const couponInfo=Number(order.discount_rial)>0?'<p>کد تخفیف: '+escapeText(order.coupon_code||"—")+' · مبلغ تخفیف: '+money(Number(order.discount_rial)/10)+'</p>':"";
      box.hidden=false;
      box.innerHTML='<h3>سفارش '+escapeText(order.order_number)+'</h3><p>مشتری: '+escapeText(order.customer_name)+' · '+escapeText(order.customer_mobile)+'</p><p>نشانی / روش دریافت: '+escapeText(address)+'</p>'+couponInfo+'<p>توضیحات: '+escapeText(order.customer_note||"—")+'</p>';
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
