let products=[
{id:1,name:"شیر پرچرب تازه",brand:"نمونه برند",category:"dairy",price:42000,unit:"بطری ۱ لیتری",image:"https://images.unsplash.com/photo-1563636619-e9143da7973b?w=500&q=85",description:"شیر پرچرب مناسب مصرف روزانه؛ تصویر و مشخصات نمونه هستند و از پنل فروشگاه قابل جایگزینی خواهند بود."},
{id:2,name:"پنیر صبحانه",brand:"نمونه برند",category:"dairy",price:68500,unit:"بسته ۴۰۰ گرمی",image:"https://images.unsplash.com/photo-1486297678162-eb2a19b0a32d?w=500&q=85",description:"پنیر صبحانه با بافت لطیف؛ اطلاعات تغذیه‌ای و ترکیبات در نسخه نهایی از کاتالوگ محصول نمایش داده می‌شود."},
{id:3,name:"برنج ایرانی",brand:"محصول نمونه",category:"grocery",price:890000,unit:"کیسه ۵ کیلویی",image:"https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&q=85",description:"برنج نمونه برای نمایش تجربه خرید. نوع، منطقه کشت و مشخصات دقیق توسط فروشگاه تکمیل می‌شود."},
{id:4,name:"بیسکویت کرمدار",brand:"نمونه برند",category:"snacks",price:39000,unit:"بسته",image:"https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=500&q=85",description:"بیسکویت میان‌وعده؛ تصویر نمایشی است."},
{id:5,name:"آبمیوه طبیعی",brand:"نمونه برند",category:"drinks",price:56000,unit:"بطری ۱ لیتری",image:"https://images.unsplash.com/photo-1600271886742-f049cd451bba?w=500&q=85",description:"نوشیدنی نمونه؛ ترکیبات و حجم واقعی از پنل مدیریت تنظیم می‌شود."},
{id:6,name:"مایع ظرفشویی",brand:"نمونه برند",category:"cleaning",price:74000,unit:"بطری",image:"https://images.unsplash.com/photo-1600857544200-b2f666a9a2ec?w=500&q=85",description:"محصول شوینده نمونه؛ هشدارهای مصرف و اطلاعات ایمنی در صفحه محصول نهایی نمایش داده می‌شوند."},
{id:7,name:"سبزیجات تازه",brand:"محصول روز",category:"fruit",price:35000,unit:"بسته",image:"https://images.unsplash.com/photo-1540420773420-3366772f4999?w=500&q=85",description:"سبزیجات تازه نمونه؛ قیمت نهایی می‌تواند بر اساس وزن محاسبه شود."},
{id:8,name:"بستنی وانیلی",brand:"نمونه برند",category:"frozen",price:95000,unit:"بسته",image:"https://images.unsplash.com/photo-1563805042-7684c019e1cb?w=500&q=85",description:"محصول منجمد نمونه؛ شرایط نگهداری سردخانه‌ای در انبارداری نهایی لحاظ می‌شود."}
];
let categoryNames={all:"همه محصولات",dairy:"لبنیات و صبحانه",grocery:"خواربار و مواد غذایی",snacks:"تنقلات و خوراکی",drinks:"نوشیدنی‌ها",cleaning:"شوینده و بهداشتی",frozen:"مواد غذایی منجمد",fruit:"میوه و سبزیجات"};
const money=n=>new Intl.NumberFormat("fa-IR").format(n)+" تومان";
const escapeHTML=value=>String(value??"").replace(/[&<>"']/g,char=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[char]));
const safeImageURL=value=>{try{const url=new URL(String(value),window.location.origin);return url.protocol==="https:"?url.href:"";}catch{return "";}};
const cart=new Map();try{const saved=JSON.parse(localStorage.getItem("sm360_cart")||"[]");if(Array.isArray(saved))saved.forEach(([id,q])=>{if(Number.isInteger(Number(id))&&Number(id)>0&&Number.isInteger(Number(q))&&Number(q)>0&&Number(q)<=99)cart.set(Number(id),Number(q));});}catch(_){}let activeCategory="all";let aisleIndex=0;let zoom=1;
const grid=document.getElementById("productGrid");
function renderProducts(list=products){grid.innerHTML=list.map(p=>{const image=safeImageURL(p.image);return `<article class="product-card" data-id="${Number(p.id)}" tabindex="0"><div class="product-image">${image?`<img src="${escapeHTML(image)}" alt="${escapeHTML(p.name)}" loading="lazy" decoding="async" referrerpolicy="no-referrer">`:""}</div><h4>${escapeHTML(p.name)}</h4><p>${escapeHTML(p.unit)}</p><div class="product-bottom"><span class="price">${money(Number(p.price)||0)}</span><button class="add-button" data-add="${Number(p.id)}" aria-label="افزودن ${escapeHTML(p.name)}">+</button></div></article>`;}).join("");document.getElementById("productCount").textContent=new Intl.NumberFormat("fa-IR").format(list.length)+" محصول";}
function selectCategory(category){activeCategory=category;document.querySelectorAll(".category").forEach(b=>b.classList.toggle("active",b.dataset.category===category));document.getElementById("aisleTitle").textContent=categoryNames[category];const list=category==="all"?products:products.filter(p=>p.category===category);renderProducts(list);aisleIndex=Object.keys(categoryNames).indexOf(category);document.getElementById("aisleScene").style.backgroundPosition=`${50+(aisleIndex%3-1)*8}% center`;}
function addToCart(id){const p=products.find(x=>x.id===Number(id));if(!p)return;cart.set(p.id,(cart.get(p.id)||0)+1);updateCart();}
function updateCart(){const count=[...cart.values()].reduce((a,b)=>a+b,0);document.getElementById("cartCount").textContent=new Intl.NumberFormat("fa-IR").format(count);try{localStorage.setItem("sm360_cart",JSON.stringify([...cart]));}catch(_){}}
async function openProduct(id){
  let p=products.find(x=>x.id===Number(id));
  if(!p)return;
  let stockLabel="";
  try {
    const detail=await apiGet("products/"+Number(id));
    if(detail){
      p={...p,name:detail.title||p.name,brand:detail.brand||p.brand,
        unit:detail.unit_label||p.unit,price:Math.round((Number(detail.price_rial)||0)/10),
        image:detail.image?.url||p.image,description:detail.description||p.description};
      stockLabel=detail.in_stock?"موجود در فروشگاه":"فعلاً ناموجود";
    }
  } catch (_) {}
  const image=safeImageURL(p.image);
  document.getElementById("productDetail").innerHTML=`${image?`<img class="detail-image" src="${escapeHTML(image)}" alt="${escapeHTML(p.name)}" referrerpolicy="no-referrer">`:""}<p class="eyebrow">${escapeHTML(p.brand)}</p><h2>${escapeHTML(p.name)}</h2><p class="detail-description">${escapeHTML(p.description)}</p><p>${escapeHTML(p.unit)}</p>${stockLabel?`<p class="stock-label">${stockLabel}</p>`:""}<h3>${money(Number(p.price)||0)}</h3><button class="primary-button full" id="detailAdd" ${stockLabel==="فعلاً ناموجود"?"disabled":""}>افزودن به سبد خرید</button>`;
  document.getElementById("detailAdd").onclick=()=>{addToCart(id);document.getElementById("productDialog").close();};
  document.getElementById("productDialog").showModal();
}
async function showCart(){
  const box=document.getElementById("cartItems");
  if(!cart.size){
    box.innerHTML='<p class="empty-cart">سبدت هنوز خالیه. یه دور توی قفسه‌ها بزن!</p>';
    document.getElementById("cartTotal").textContent=money(0);
  }else{
    const rows=[...cart].map(([id,quantity])=>({product_id:id,quantity}));
    let quote=null;
    try{
      const response=await fetch(apiBase+"cart/quote",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({items:rows})});
      const payload=await response.json();
      if(!response.ok)throw new Error(payload.error||"امکان بررسی سبد خرید نیست.");
      quote=payload.data;
    }catch(error){setFormMessage("checkoutMessage",error.message,"error");}
    if(quote){
      box.innerHTML=quote.items.map(item=>'<div class="cart-line"><span>'+escapeHTML(item.title)+' × '+new Intl.NumberFormat("fa-IR").format(item.quantity)+'</span><strong>'+money(Math.round(item.line_total_rial/10))+'</strong><button data-remove="'+Number(item.product_id)+'">حذف</button></div>').join("");
      document.getElementById("cartTotal").textContent=money(Math.round(quote.total_rial/10));
      document.querySelector(".demo-note").textContent="قیمت و موجودی توسط سرور بررسی شده‌اند؛ مبلغ هنگام ثبت سفارش دوباره محاسبه می‌شود.";
    }else{
      let total=0;
      box.innerHTML=[...cart].map(([id,q])=>{const p=products.find(x=>x.id===id);if(!p)return "";const lineTotal=(Number(p.price)||0)*q;total+=lineTotal;return '<div class="cart-line"><span>'+escapeHTML(p.name)+' × '+new Intl.NumberFormat("fa-IR").format(q)+'</span><strong>'+money(lineTotal)+'</strong><button data-remove="'+Number(id)+'">حذف</button></div>';}).join("");
      document.getElementById("cartTotal").textContent=money(total);
    }
  }
  document.getElementById("cartDialog").showModal();
}
document.getElementById("categoryList").addEventListener("click",e=>{const b=e.target.closest("[data-category]");if(b)selectCategory(b.dataset.category);});
grid.addEventListener("click",e=>{const add=e.target.closest("[data-add]");if(add){e.stopPropagation();addToCart(add.dataset.add);return;}const card=e.target.closest("[data-id]");if(card)openProduct(card.dataset.id);});
grid.addEventListener("keydown",e=>{if((e.key==="Enter"||e.key===" ")&&e.target.matches("[data-id]"))openProduct(e.target.dataset.id);});
document.getElementById("cartButton").addEventListener("click",showCart);
document.getElementById("cartItems").addEventListener("click",e=>{const b=e.target.closest("[data-remove]");if(b){cart.delete(Number(b.dataset.remove));updateCart();showCart();}});
document.querySelectorAll("[data-close]").forEach(b=>b.addEventListener("click",()=>document.getElementById(b.dataset.close).close()));
document.getElementById("searchInput").addEventListener("input",e=>{const q=e.target.value.trim();renderProducts(products.filter(p=>(activeCategory==="all"||p.category===activeCategory)&&p.name.includes(q)));});
document.getElementById("enterStore").addEventListener("click",()=>document.getElementById("store").scrollIntoView({behavior:"smooth"}));
document.getElementById("browseCategories").addEventListener("click",()=>document.getElementById("categoryList").scrollIntoView({behavior:"smooth",block:"center"}));
document.getElementById("prevAisle").addEventListener("click",()=>{if(publicScenes.length){currentScene=(currentScene-1+publicScenes.length)%publicScenes.length;renderScenes();return;}const keys=Object.keys(categoryNames);selectCategory(keys[(aisleIndex-1+keys.length)%keys.length]);});
document.getElementById("nextAisle").addEventListener("click",()=>{if(publicScenes.length){currentScene=(currentScene+1)%publicScenes.length;renderScenes();return;}const keys=Object.keys(categoryNames);selectCategory(keys[(aisleIndex+1)%keys.length]);});
document.querySelectorAll("[data-mode]").forEach(b=>b.addEventListener("click",()=>{document.querySelectorAll("[data-mode]").forEach(x=>x.classList.toggle("active",x===b));const hints={guided:"با انتخاب هر دسته، به قفسه مربوط هدایت می‌شی.",tour:"در نسخه بعدی، نقاط قابل کلیک برای جابه‌جایی بین تصاویر واقعی فعال می‌شن.",free:"حرکت آزاد سه‌بعدی در مرحله توسعه موتور تعاملی اضافه می‌شه."};document.getElementById("modeHint").textContent=hints[b.dataset.mode];}));
document.getElementById("zoomIn").addEventListener("click",()=>{zoom=Math.min(1.2,zoom+.05);document.getElementById("aisleScene").style.backgroundSize=(zoom*100)+"% auto";});
document.getElementById("zoomOut").addEventListener("click",()=>{zoom=Math.max(1,zoom-.05);document.getElementById("aisleScene").style.backgroundSize=(zoom*100)+"% auto";});
document.getElementById("checkoutButton").addEventListener("click",startCheckout);
document.getElementById("accountButton").addEventListener("click",openAccountDialog);
renderProducts();


// Connect the storefront to the tenant-scoped public catalog API.
// The built-in sample catalog remains a graceful local preview when no API is configured.
const apiBase = new URL("api/v1/", document.baseURI).toString();
let csrfToken = "";
let currentUser = null;
let pendingCheckout = false;
let pendingPaymentOrderId = null;
let savedAddresses = [];
let publicScenes = [];
let currentScene = 0;

async function apiPost(path, body, withCsrf = false) {
  const headers = { Accept: "application/json", "Content-Type": "application/json" };
  if (withCsrf && csrfToken) headers["X-CSRF-Token"] = csrfToken;
  const response = await fetch(apiBase + path, { method: "POST", headers, credentials: "same-origin", body: JSON.stringify(body) });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.error || "درخواست انجام نشد.");
  return payload;
}
async function ensureAccount() {
  try {
    currentUser = await apiGet("me");
    const csrf = await apiGet("auth/csrf");
    csrfToken = csrf?.csrf_token || "";
    document.getElementById("accountButton").textContent = currentUser?.full_name || "حساب کاربری";
    return Boolean(currentUser && csrfToken);
  } catch (_) { currentUser = null; csrfToken = ""; return false; }
}
function setFormMessage(id, message, type = "") {
  const node = document.getElementById(id);
  node.textContent = message;
  node.className = "form-message" + (type ? " " + type : "");
}
async function openAccountDialog() {
  document.getElementById("accountDialog").showModal();
  setFormMessage("accountMessage", "در حال بررسی وضعیت حساب...");
  if (await ensureAccount()) setFormMessage("accountMessage", "وارد حساب کاربری هستید.", "success");
  else setFormMessage("accountMessage", "برای ورود یا ساخت حساب، شماره موبایل را وارد کنید.");
}
async function requestOtp() {
  const mobile = document.getElementById("accountMobile").value.trim();
  if (!mobile) throw new Error("شماره موبایل را وارد کنید.");
  await apiPost("auth/request-otp", { mobile });
  setFormMessage("accountMessage", "اگر حساب فعالی با این شماره وجود داشته باشد، کد تأیید ارسال می‌شود. در حالت توسعه کد در گزارش سرور ثبت می‌شود.", "success");
}
async function startCheckout() {
  if (!cart.size) return;
  document.getElementById("cartDialog").close();
  if (!(await ensureAccount())) {
    pendingCheckout = true;
    document.getElementById("accountDialog").showModal();
    setFormMessage("accountMessage", "برای نهایی‌کردن خرید ابتدا وارد شوید یا حساب بسازید.");
    return;
  }
  document.getElementById("addressRecipient").value = currentUser.full_name || "";
  document.getElementById("addressMobile").value = currentUser.mobile || "";
  try { savedAddresses = await apiGet("me/addresses") || []; } catch (_) { savedAddresses = []; }
  const addressSelect=document.getElementById("savedAddressSelect");
  addressSelect.innerHTML='<option value="new">ثبت نشانی جدید</option>'+savedAddresses.map(a=>'<option value="'+Number(a.id)+'">'+escapeHTML(a.title)+' — '+escapeHTML(a.city||"")+'، '+escapeHTML(a.address)+'</option>').join("");
  if(savedAddresses.length){addressSelect.value=String(savedAddresses[0].id);document.getElementById("deliveryAddressFields").hidden=true;}
  else{addressSelect.value="new";document.getElementById("deliveryAddressFields").hidden=false;}
  document.getElementById("checkoutDialog").showModal();
}

async function apiGet(path) {
  const response = await fetch(apiBase + path, {
    headers: { Accept: "application/json" },
    credentials: "same-origin"
  });
  if (!response.ok) throw new Error("Catalog API returned " + response.status);
  const payload = await response.json();
  return payload.data;
}

function renderCategories(categories) {
  const list = document.getElementById("categoryList");
  const rows = [{ slug: "all", title: "همه محصولات", icon: "✳" },
    ...categories.map((c, i) => ({
      slug: String(c.slug || c.id),
      title: String(c.title || "دسته‌بندی"),
      icon: ["🥛", "🍚", "🍪", "🧃", "🧴", "🧊", "🍎", "🛒"][i % 8]
    }))];
  categoryNames = Object.fromEntries(rows.map(row => [row.slug, row.title]));
  list.innerHTML = rows.map((row, i) =>
    '<button class="category' + (i === 0 ? ' active' : '') +
    '" data-category="' + escapeHTML(row.slug) + '"><span>' + row.icon +
    '</span> ' + escapeHTML(row.title) + ' <b>›</b></button>'
  ).join("");
  const heading = document.querySelector(".panel-heading > span");
  if (heading) heading.textContent = new Intl.NumberFormat("fa-IR", {minimumIntegerDigits:2}).format(categories.length);
}

function renderScenes() {
  const scene = publicScenes[currentScene];
  const view = document.getElementById("aisleScene");
  if (!scene || !scene.media || !scene.media.url) return;
  const url = safeImageURL(scene.media.url);
  if (!url) return;
  view.style.backgroundImage = "linear-gradient(180deg,#ffffff00 55%,#17241c88),url('" + url.replace(/'/g, "%27") + "')";
  view.style.backgroundSize = "cover";
  view.querySelectorAll(".scene-hotspot").forEach(node => node.remove());
  (scene.hotspots || []).forEach(hotspot => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "scene-hotspot";
    button.textContent = hotspot.label || hotspot.product?.title || hotspot.category?.title || "مشاهده";
    const x = Number(hotspot.x), y = Number(hotspot.y);
    button.style.left = (x <= 1 ? x * 100 : x) + "%";
    button.style.top = (y <= 1 ? y * 100 : y) + "%";
    button.setAttribute("aria-label", button.textContent);
    button.addEventListener("click", () => {
      if (hotspot.product?.id) openProduct(hotspot.product.id);
      else if (hotspot.category?.slug) selectCategory(hotspot.category.slug);
      else if (hotspot.target_scene?.id) {
        const next = publicScenes.findIndex(item => Number(item.id) === Number(hotspot.target_scene.id));
        if (next >= 0) { currentScene = next; renderScenes(); }
      }
    });
    view.appendChild(button);
  });
  document.getElementById("aisleTitle").textContent = scene.title || categoryNames[activeCategory] || "فروشگاه";
  const overlay = view.querySelector(".scene-overlay");
  if (overlay) overlay.innerHTML = "<span>تور تصویری فروشگاه</span><span>برای مشاهده محصول یا رفتن به بخش دیگر، نقاط روی تصویر را انتخاب کن.</span>";
  const counter = document.querySelector(".aisle-number b");
  if (counter) counter.textContent = new Intl.NumberFormat("fa-IR").format(currentScene + 1).padStart(2, "۰");
}

async function loadLiveCatalog() {
  try {
    const [store, categories, rows] = await Promise.all([
      apiGet("store"), apiGet("categories"), apiGet("products")
    ]);
    if (store?.name) document.title = store.name + " | سوپرمارکت ۳۶۰";
    if (Array.isArray(categories)) renderCategories(categories);
    if (Array.isArray(rows)) {
      products = rows.map(row => ({
        id: Number(row.id),
        name: row.title || "کالای بدون نام",
        brand: row.brand || "",
        category: row.category_slug || "all",
        price: Math.round((Number(row.price_rial) || 0) / 10),
        unit: row.unit_label || "عدد",
        image: row.image?.url || "",
        description: row.description || "اطلاعات بیشتر این محصول در فروشگاه ثبت نشده است."
      }));
      renderProducts(products);
      selectCategory("all");
    }
    const scenes = await apiGet("scenes");
    if (Array.isArray(scenes) && scenes.length) {
      publicScenes = scenes;
      currentScene = 0;
      renderScenes();
      document.querySelector(".scene-controls > span").textContent = "● تور تصویری فعال";
    }
    document.querySelector(".welcome-copy").textContent =
      "از میان دسته‌بندی‌ها انتخاب کن، محصولات واقعی فروشگاه را ببین و در محیط تصویری فروشگاه گردش کن.";
  } catch (error) {
    console.info("Public catalog API unavailable; showing sample preview.", error);
    const note = document.createElement("p");
    note.className = "api-preview-note";
    note.textContent = "حالت پیش‌نمایش: برای نمایش اطلاعات زنده، API و پایگاه داده فروشگاه باید راه‌اندازی شوند.";
    document.querySelector(".aisle-top").appendChild(note);
  }
}

loadLiveCatalog();

// Customer registration, OTP login, server-validated checkout and order confirmation.
document.getElementById("registerButton").addEventListener("click", async () => {
  try {
    await apiPost("auth/register", {
      full_name: document.getElementById("accountName").value.trim(),
      mobile: document.getElementById("accountMobile").value.trim(),
      referral_code: document.getElementById("accountReferral").value.trim()
    });
    await requestOtp();
  } catch (error) { setFormMessage("accountMessage", error.message, "error"); }
});
document.getElementById("requestOtpButton").addEventListener("click", async () => {
  try { await requestOtp(); } catch (error) { setFormMessage("accountMessage", error.message, "error"); }
});
document.getElementById("verifyOtpButton").addEventListener("click", async () => {
  try {
    const result=await apiPost("auth/verify-otp",{
      mobile:document.getElementById("accountMobile").value.trim(),
      code:document.getElementById("accountOtp").value.trim()
    });
    csrfToken=result.data?.csrf_token||"";
    currentUser=result.data?.user||null;
    document.getElementById("accountButton").textContent=currentUser?.full_name||"حساب کاربری";
    setFormMessage("accountMessage","ورود با موفقیت انجام شد.","success");
    if(pendingCheckout){pendingCheckout=false;document.getElementById("accountDialog").close();startCheckout();}
  } catch(error){setFormMessage("accountMessage",error.message,"error");}
});
document.getElementById("fulfillmentSelect").addEventListener("change",e=>{
  const useDelivery=e.target.value==="delivery";
  document.getElementById("savedAddressSelect").parentElement.hidden=!useDelivery;
  document.getElementById("deliveryAddressFields").hidden=!useDelivery||document.getElementById("savedAddressSelect").value!=="new";
});
document.getElementById("savedAddressSelect").addEventListener("change",e=>{
  document.getElementById("deliveryAddressFields").hidden=e.target.value!=="new"||document.getElementById("fulfillmentSelect").value==="pickup";
});
document.getElementById("placeOrderButton").addEventListener("click",async()=>{
  const button=document.getElementById("placeOrderButton");
  button.disabled=true;
  try{
    if(pendingPaymentOrderId){
      setFormMessage("checkoutMessage","در حال اتصال به درگاه پرداخت...");
      const payment=await apiPost("payments/zarinpal/request",{order_id:pendingPaymentOrderId},true);
      if(payment.data?.redirect_url){window.location.assign(payment.data.redirect_url);return;}
      if(payment.data?.already_paid){window.location.assign("payment-result.php?status=success");return;}
      throw new Error("درگاه پرداخت هنوز آماده نیست.");
    }
    setFormMessage("checkoutMessage","در حال اعتبارسنجی قیمت و موجودی...");
    let addressId=null;
    const fulfillment=document.getElementById("fulfillmentSelect").value;
    if(fulfillment==="delivery"){
      const selectedAddress=document.getElementById("savedAddressSelect").value;
      if(selectedAddress!=="new"){
        addressId=Number(selectedAddress);
      }else{
      const address=await apiPost("me/addresses",{
        title:document.getElementById("addressTitle").value.trim(),
        recipient:document.getElementById("addressRecipient").value.trim(),
        mobile:document.getElementById("addressMobile").value.trim(),
        province:document.getElementById("addressProvince").value.trim(),
        city:document.getElementById("addressCity").value.trim(),
        address:document.getElementById("addressText").value.trim(),
        postal_code:document.getElementById("addressPostal").value.trim()
      },true);
      addressId=address.data.id;
      }
    }
    const order=await apiPost("checkout",{
      items:[...cart].map(([product_id,quantity])=>({product_id,quantity})),
      fulfillment,address_id:addressId,
      customer_note:document.getElementById("orderNote").value.trim()
    },true);
    pendingPaymentOrderId=Number(order.data.id);
    cart.clear();updateCart();
    document.getElementById("cartItems").innerHTML='<p class="empty-cart">سفارش با موفقیت ثبت شد.</p>';
    document.getElementById("cartTotal").textContent=money(0);
    setFormMessage("checkoutMessage","سفارش "+order.data.order_number+" ثبت شد؛ در حال انتقال به درگاه پرداخت...","success");
    const payment=await apiPost("payments/zarinpal/request",{order_id:pendingPaymentOrderId},true);
    if(payment.data?.redirect_url){window.location.assign(payment.data.redirect_url);return;}
    throw new Error("درگاه پرداخت هنوز پیکربندی نشده است. سفارش ثبت شده و می‌توانید بعداً پرداخت را دوباره امتحان کنید.");
  }catch(error){
    setFormMessage("checkoutMessage",error.message,"error");
    if(pendingPaymentOrderId)button.textContent="تلاش دوباره برای پرداخت";
  }finally{button.disabled=false;}
});
updateCart();
