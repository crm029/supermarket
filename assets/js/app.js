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
let movementMode="guided";
let freeWorld=null;
let freeAnimationFrame=0;
const freeMovementKeys=new Set();
let freePointerLook=null;
const freeCategoryPositions={
  dairy:{side:-1,z:-1},grocery:{side:-1,z:-6.2},snacks:{side:-1,z:-11.4},drinks:{side:-1,z:-16.6},
  cleaning:{side:1,z:-1},frozen:{side:1,z:-6.2},fruit:{side:1,z:-11.4}
};

const grid=document.getElementById("productGrid");
function renderProducts(list=products){grid.innerHTML=list.map(p=>{const image=safeImageURL(p.image);const unavailable=p.inStock===false;return `<article class="product-card" data-id="${Number(p.id)}" tabindex="0"><div class="product-image">${image?`<img src="${escapeHTML(image)}" alt="${escapeHTML(p.name)}" loading="lazy" decoding="async" referrerpolicy="no-referrer">`:""}</div><h4>${escapeHTML(p.name)}</h4><p>${escapeHTML(p.unit)}</p>${unavailable?'<small class="product-stock-badge">فعلاً ناموجود</small>':""}<div class="product-bottom"><span class="price">${money(Number(p.price)||0)}</span><button class="add-button" data-add="${Number(p.id)}" aria-label="افزودن ${escapeHTML(p.name)}" ${unavailable?"disabled":""}>+</button></div></article>`;}).join("");document.getElementById("productCount").textContent=new Intl.NumberFormat("fa-IR").format(list.length)+" محصول";}
function selectCategory(category){activeCategory=category;document.querySelectorAll(".category").forEach(b=>b.classList.toggle("active",b.dataset.category===category));document.getElementById("aisleTitle").textContent=categoryNames[category];const list=category==="all"?products:products.filter(p=>p.category===category);renderProducts(list);aisleIndex=Object.keys(categoryNames).indexOf(category);document.getElementById("aisleScene").style.backgroundPosition=(50+(aisleIndex%3-1)*8)+"% center";if(movementMode==="free"&&freeWorld)focusFreeCategory(category);}
function clampNumber(value,min,max){return Math.max(min,Math.min(max,value));}
function makeFreeLabel(THREE,text){
  const canvas=document.createElement("canvas");canvas.width=512;canvas.height=128;
  const ctx=canvas.getContext("2d");
  ctx.fillStyle="#10251d";ctx.beginPath();ctx.roundRect(8,8,496,112,24);ctx.fill();
  ctx.fillStyle="#ffffff";ctx.font="bold 46px Tahoma, sans-serif";ctx.textAlign="center";ctx.textBaseline="middle";ctx.fillText(text,256,66,460);
  const texture=new THREE.CanvasTexture(canvas);texture.colorSpace=THREE.SRGBColorSpace;
  const sprite=new THREE.Sprite(new THREE.SpriteMaterial({map:texture,transparent:true,depthWrite:false}));
  sprite.scale.set(2.2,.55,1);return sprite;
}
function buildFreeMovementWorld(){
  const THREE=window.THREE,view=document.getElementById("aisleScene");
  const width=Math.max(320,view.clientWidth),height=Math.max(210,view.clientHeight);
  const scene=new THREE.Scene();scene.background=new THREE.Color(0xe8e4d8);scene.fog=new THREE.Fog(0xe8e4d8,17,34);
  const camera=new THREE.PerspectiveCamera(66,width/height,.1,70);camera.position.set(0,1.62,4.4);
  const renderer=new THREE.WebGLRenderer({antialias:window.devicePixelRatio<2,powerPreference:"low-power",alpha:false});
  renderer.setPixelRatio(Math.min(window.devicePixelRatio||1,1.5));renderer.setSize(width,height);
  renderer.outputColorSpace=THREE.SRGBColorSpace;renderer.shadowMap.enabled=false;
  renderer.domElement.className="free-movement-canvas";renderer.domElement.setAttribute("aria-label","محیط سه‌بعدی فروشگاه؛ برای چرخش دید بکشید");
  const ambient=new THREE.HemisphereLight(0xffffff,0x8d887a,2.15);scene.add(ambient);
  const keyLight=new THREE.DirectionalLight(0xfff3d8,1.15);keyLight.position.set(-4,8,5);scene.add(keyLight);
  const floor=new THREE.Mesh(new THREE.PlaneGeometry(12,42),new THREE.MeshStandardMaterial({color:0xd6d0c2,roughness:.95}));
  floor.rotation.x=-Math.PI/2;floor.position.set(0,-.04,-9);scene.add(floor);
  const ceiling=new THREE.Mesh(new THREE.PlaneGeometry(12,42),new THREE.MeshStandardMaterial({color:0xf5f2e9,roughness:1,side:THREE.DoubleSide}));
  ceiling.rotation.x=Math.PI/2;ceiling.position.set(0,4,-9);scene.add(ceiling);
  const wallMaterial=new THREE.MeshStandardMaterial({color:0xe7e3d8,roughness:1});
  for(const x of [-5.5,5.5]){const wall=new THREE.Mesh(new THREE.BoxGeometry(.18,4,42),wallMaterial);wall.position.set(x,2,-9);scene.add(wall);}
  const colors={dairy:[0xe8f1ee,0x9bc7b7,0xf1d8a8],grocery:[0xc59a58,0xe0c58c,0x9e7341],snacks:[0xd96b3b,0xe8b34c,0x8e4d37],drinks:[0x4e9cbe,0x77bd83,0xd1a4d8],cleaning:[0x68a9cf,0x8cc6ad,0xe7e6d8],frozen:[0x91c7e3,0xc6e5ef,0x719bc7],fruit:[0x6eaa5b,0xe6a44a,0xc95c4b]};
  const clickables=[],shelfPositions={};
  Object.entries(freeCategoryPositions).forEach(([category,position])=>{
    const side=position.side,centerX=side*2.85,z=position.z;shelfPositions[category]={side,z};
    const rack=new THREE.Group();rack.position.set(centerX,0,z);scene.add(rack);
    const wood=new THREE.MeshStandardMaterial({color:0x8b6747,roughness:.82}),dark=new THREE.MeshStandardMaterial({color:0x594331,roughness:.9});
    const back=new THREE.Mesh(new THREE.BoxGeometry(.12,2.25,3.75),dark);back.position.set(0,1.13,0);rack.add(back);
    for(const y of [.28,.88,1.48,2.08]){const board=new THREE.Mesh(new THREE.BoxGeometry(1.02,.09,3.85),wood);board.position.set(-side*.42,y,0);rack.add(board);}
    for(const zSide of [-1.78,1.78]){const panel=new THREE.Mesh(new THREE.BoxGeometry(1.02,2.2,.09),wood);panel.position.set(-side*.42,1.12,zSide);rack.add(panel);}
    const palette=colors[category]||[0x8cae8a,0xd6ad6b,0xc98264];
    for(let level=0;level<3;level++)for(let item=0;item<6;item++){
      const material=new THREE.MeshStandardMaterial({color:palette[(item+level)%palette.length],roughness:.72});
      const heightBox=.28+(item%3)*.045;
      const product=new THREE.Mesh(new THREE.BoxGeometry(.22,heightBox,.25),material);
      product.position.set(-side*(.12+level*.02),.47+level*.6+heightBox/2,-1.45+item*.57);rack.add(product);
    }
    const label=makeFreeLabel(THREE,categoryNames[category]||category);label.position.set(-side*.72,2.62,0);rack.add(label);
    const hitMaterial=new THREE.MeshBasicMaterial({transparent:true,opacity:0,depthWrite:false,side:THREE.DoubleSide});
    const hit=new THREE.Mesh(new THREE.BoxGeometry(.16,2.4,3.9),hitMaterial);
    hit.position.set(-side*.66,1.2,0);hit.userData.category=category;rack.add(hit);clickables.push(hit);
  });
  view.appendChild(renderer.domElement);
  return {THREE,scene,camera,renderer,clickables,shelfPositions,yaw:0,pitch:0,resizeHandler:null};
}
function focusFreeCategory(category){
  const position=freeWorld&&freeWorld.shelfPositions[category];if(!position)return;
  freeWorld.camera.position.set(0,1.62,position.z+2.4);
  freeWorld.yaw=position.side === -1 ? 0.62 : -0.62;freeWorld.camera.rotation.set(0,freeWorld.yaw,0);
  document.getElementById("freeMovementStatus").textContent="راهرو: "+(categoryNames[category]||"فروشگاه");
}
function startFreeMovement(){
  const view=document.getElementById("aisleScene"),controls=document.getElementById("freeMovementControls");
  controls.hidden=false;view.classList.add("free-movement-active");view.classList.remove("free-movement-fallback");
  view.querySelectorAll(".scene-hotspot").forEach(node=>node.hidden=true);
  if(!window.THREE){movementMode="tour";document.querySelectorAll("[data-mode]").forEach(button=>button.classList.toggle("active",button.dataset.mode==="tour"));view.classList.add("free-movement-fallback");controls.hidden=true;document.getElementById("freeMovementStatus").textContent="مرورگر امکان اجرای سه‌بعدی را فراهم نکرد؛ از تور تصویری و فلش‌های راهرو استفاده کن.";document.getElementById("modeHint").textContent="حالت سه‌بعدی روی این دستگاه در دسترس نیست؛ تور تصویری و راهنمای دسته‌ها همچنان فعال‌اند.";view.classList.remove("free-movement-active");return;}
  if(freeWorld)return;
  try{
    freeWorld=buildFreeMovementWorld();const canvas=freeWorld.renderer.domElement;
    freeWorld.resizeHandler=()=>{if(!freeWorld)return;const width=Math.max(320,view.clientWidth),height=Math.max(210,view.clientHeight);freeWorld.camera.aspect=width/height;freeWorld.camera.updateProjectionMatrix();freeWorld.renderer.setSize(width,height);};
    window.addEventListener("resize",freeWorld.resizeHandler);
    const raycaster=new freeWorld.THREE.Raycaster(),pointer=new freeWorld.THREE.Vector2();let pointerMoved=false;
    canvas.addEventListener("pointerdown",event=>{freePointerLook={id:event.pointerId,x:event.clientX,y:event.clientY,lastX:event.clientX,lastY:event.clientY};pointerMoved=false;canvas.setPointerCapture(event.pointerId);});
    canvas.addEventListener("pointermove",event=>{
      if(!freePointerLook||freePointerLook.id!==event.pointerId)return;
      const dx=event.clientX-freePointerLook.lastX,dy=event.clientY-freePointerLook.lastY;
      if(Math.abs(event.clientX-freePointerLook.x)+Math.abs(event.clientY-freePointerLook.y)>5)pointerMoved=true;
      if(pointerMoved){freeWorld.yaw=clampNumber(freeWorld.yaw-dx*.004,-1.15,1.15);freeWorld.pitch=clampNumber(freeWorld.pitch-dy*.0025,-.22,.22);freeWorld.camera.rotation.set(freeWorld.pitch,freeWorld.yaw,0);}
      freePointerLook.lastX=event.clientX;freePointerLook.lastY=event.clientY;
    });
    canvas.addEventListener("pointerup",event=>{
      if(!freePointerLook||freePointerLook.id!==event.pointerId)return;
      if(!pointerMoved){const rect=canvas.getBoundingClientRect();pointer.x=((event.clientX-rect.left)/rect.width)*2-1;pointer.y=-((event.clientY-rect.top)/rect.height)*2+1;raycaster.setFromCamera(pointer,freeWorld.camera);const hit=raycaster.intersectObjects(freeWorld.clickables,false)[0];if(hit&&hit.object.userData.category)selectCategory(hit.object.userData.category);}
      freePointerLook=null;
    });
    canvas.addEventListener("pointercancel",()=>{freePointerLook=null;});
    const moveMap={forward:"w",back:"s",left:"a",right:"d"};
    document.querySelectorAll("[data-move]").forEach(button=>{
      if(button.dataset.moveBound==="1")return;
      button.dataset.moveBound="1";
      const key=moveMap[button.dataset.move];
      button.addEventListener("pointerdown",event=>{event.preventDefault();freeMovementKeys.add(key);});
      ["pointerup","pointercancel","pointerleave"].forEach(type=>button.addEventListener(type,()=>freeMovementKeys.delete(key)));
    });
    focusFreeCategory(activeCategory==="all"?"dairy":activeCategory);
    const animate=()=>{
      if(!freeWorld||movementMode!=="free")return;freeAnimationFrame=requestAnimationFrame(animate);
      const speed=.075,yaw=freeWorld.yaw;let dx=0,dz=0;
      if(freeMovementKeys.has("w")||freeMovementKeys.has("arrowup")){dx-=Math.sin(yaw)*speed;dz-=Math.cos(yaw)*speed;}
      if(freeMovementKeys.has("s")||freeMovementKeys.has("arrowdown")){dx+=Math.sin(yaw)*speed;dz+=Math.cos(yaw)*speed;}
      if(freeMovementKeys.has("a")||freeMovementKeys.has("arrowleft")){dx-=Math.cos(yaw)*speed;dz+=Math.sin(yaw)*speed;}
      if(freeMovementKeys.has("d")||freeMovementKeys.has("arrowright")){dx+=Math.cos(yaw)*speed;dz-=Math.sin(yaw)*speed;}
      freeWorld.camera.position.x=clampNumber(freeWorld.camera.position.x+dx,-1.55,1.55);
      freeWorld.camera.position.z=clampNumber(freeWorld.camera.position.z+dz,-20,4.4);
      freeWorld.renderer.render(freeWorld.scene,freeWorld.camera);
    };
    animate();
  }catch(error){stopFreeMovement();movementMode="tour";document.querySelectorAll("[data-mode]").forEach(button=>button.classList.toggle("active",button.dataset.mode==="tour"));view.classList.add("free-movement-fallback");document.getElementById("modeHint").textContent="اجرای سه‌بعدی در این دستگاه ممکن نشد؛ از تور تصویری یا راهنمای دسته‌ها استفاده کن.";console.warn("3D store mode unavailable",error);}
}
function stopFreeMovement(){
  cancelAnimationFrame(freeAnimationFrame);freeAnimationFrame=0;freeMovementKeys.clear();freePointerLook=null;
  const view=document.getElementById("aisleScene"),controls=document.getElementById("freeMovementControls");
  controls.hidden=true;view.classList.remove("free-movement-active","free-movement-fallback");view.querySelectorAll(".scene-hotspot").forEach(node=>node.hidden=false);
  if(freeWorld){window.removeEventListener("resize",freeWorld.resizeHandler);freeWorld.renderer.dispose();freeWorld.renderer.domElement.remove();freeWorld.scene.traverse(object=>{if(object.geometry)object.geometry.dispose();if(object.material){const materials=Array.isArray(object.material)?object.material:[object.material];materials.forEach(material=>{if(material.map)material.map.dispose();material.dispose();});}});freeWorld=null;}
}
document.addEventListener("keydown",event=>{
  if(movementMode!=="free"||document.querySelector("dialog[open]")||event.target.matches("input,textarea,select,[contenteditable=true]"))return;
  const key=event.key.toLowerCase();if(["w","a","s","d","arrowup","arrowdown","arrowleft","arrowright"].includes(key)){freeMovementKeys.add(key);event.preventDefault();}
});
document.addEventListener("keyup",event=>freeMovementKeys.delete(event.key.toLowerCase()));
window.addEventListener("blur",()=>freeMovementKeys.clear());
function addToCart(id){const p=products.find(x=>x.id===Number(id));if(!p||p.inStock===false)return;cart.set(p.id,Math.min(99,(cart.get(p.id)||0)+1));updateCart();persistCustomerCart();}
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
        image:detail.image?.url||p.image,description:detail.description||p.description,inStock:detail.in_stock!==false};
      stockLabel=detail.in_stock?"موجود در فروشگاه":"فعلاً ناموجود";
    }
  } catch (_) {}
  const image=safeImageURL(p.image);
  document.getElementById("productDetail").innerHTML=`${image?`<img class="detail-image" src="${escapeHTML(image)}" alt="${escapeHTML(p.name)}" referrerpolicy="no-referrer">`:""}<p class="eyebrow">${escapeHTML(p.brand)}</p><h2>${escapeHTML(p.name)}</h2><p class="detail-description">${escapeHTML(p.description)}</p><p>${escapeHTML(p.unit)}</p>${stockLabel?`<p class="stock-label">${stockLabel}</p>`:""}<h3>${money(Number(p.price)||0)}</h3><button class="primary-button full" id="detailAdd" ${p.inStock===false||stockLabel==="فعلاً ناموجود"?"disabled":""}>افزودن به سبد خرید</button>`;
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
document.getElementById("cartItems").addEventListener("click",e=>{const b=e.target.closest("[data-remove]");if(b){cart.delete(Number(b.dataset.remove));updateCart();persistCustomerCart();showCart();}});
document.querySelectorAll("[data-close]").forEach(b=>b.addEventListener("click",()=>document.getElementById(b.dataset.close).close()));
document.getElementById("searchInput").addEventListener("input",e=>{const q=e.target.value.trim();renderProducts(products.filter(p=>(activeCategory==="all"||p.category===activeCategory)&&p.name.includes(q)));});
document.getElementById("enterStore").addEventListener("click",()=>document.getElementById("store").scrollIntoView({behavior:"smooth"}));
document.getElementById("browseCategories").addEventListener("click",()=>document.getElementById("categoryList").scrollIntoView({behavior:"smooth",block:"center"}));
document.getElementById("prevAisle").addEventListener("click",()=>{if(publicScenes.length){currentScene=(currentScene-1+publicScenes.length)%publicScenes.length;renderScenes();return;}const keys=Object.keys(categoryNames);selectCategory(keys[(aisleIndex-1+keys.length)%keys.length]);});
document.getElementById("nextAisle").addEventListener("click",()=>{if(publicScenes.length){currentScene=(currentScene+1)%publicScenes.length;renderScenes();return;}const keys=Object.keys(categoryNames);selectCategory(keys[(aisleIndex+1)%keys.length]);});
function setMovementMode(mode){
  movementMode=mode;
  document.querySelectorAll("[data-mode]").forEach(button=>button.classList.toggle("active",button.dataset.mode===mode));
  const hints={guided:"با انتخاب هر دسته، به قفسه مربوط هدایت می‌شی.",tour:"با کشیدن تصویر می‌تونی محیط واقعی فروشگاه رو تماشا کنی و نقاط راهنما رو انتخاب کنی.",free:"در محیط سه‌بعدی با کلیدهای جهت‌دار یا دکمه‌های لمسی بین قفسه‌ها حرکت کن."};
  document.getElementById("modeHint").textContent=hints[mode];
  if(mode==="free")startFreeMovement();else stopFreeMovement();
}
document.querySelectorAll("[data-mode]").forEach(button=>button.addEventListener("click",()=>setMovementMode(button.dataset.mode)));
document.getElementById("exitFreeMovement").addEventListener("click",()=>setMovementMode("guided"));
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
let cartSyncReady = false;
let cartSaveChain = Promise.resolve();
let pendingCheckout = false;
let pendingPaymentOrderId = null;
let savedAddresses = [];
let pickupSlotsConfigured = false;
let availablePickupSlots = [];
let editingAddressId = null;
let publicScenes = [];
let currentScene = 0;
let panoramaOffset = 50;
let panoramaPointer = null;

async function apiPost(path, body, withCsrf = false) {
  const headers = { Accept: "application/json", "Content-Type": "application/json" };
  if (withCsrf && csrfToken) headers["X-CSRF-Token"] = csrfToken;
  const response = await fetch(apiBase + path, { method: "POST", headers, credentials: "same-origin", body: JSON.stringify(body) });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.error || "درخواست انجام نشد.");
  return payload;
}
async function apiPatch(path, body, withCsrf = false) {
  const headers = { Accept: "application/json", "Content-Type": "application/json" };
  if (withCsrf && csrfToken) headers["X-CSRF-Token"] = csrfToken;
  const response = await fetch(apiBase + path, { method: "PATCH", headers, credentials: "same-origin", body: JSON.stringify(body) });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.error || "درخواست انجام نشد.");
  return payload;
}
async function apiDelete(path, withCsrf = false) {
  const headers = { Accept: "application/json" };
  if (withCsrf && csrfToken) headers["X-CSRF-Token"] = csrfToken;
  const response = await fetch(apiBase + path, { method: "DELETE", headers, credentials: "same-origin" });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.error || "درخواست انجام نشد.");
  return payload;
}
async function apiPut(path, body, withCsrf = false) {
  const headers = { Accept: "application/json", "Content-Type": "application/json" };
  if (withCsrf && csrfToken) headers["X-CSRF-Token"] = csrfToken;
  const response = await fetch(apiBase + path, { method: "PUT", headers, credentials: "same-origin", body: JSON.stringify(body) });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.error || "درخواست انجام نشد.");
  return payload;
}
async function syncCustomerCart() {
  let synchronized = false;
  try {
    const owner = localStorage.getItem("sm360_cart_owner");
    const remote = await apiGet("me/cart");
    if (owner) cart.clear();
    (remote?.items || []).forEach(item => {
      const id = Number(item.product_id), quantity = Number(item.quantity);
      if (Number.isInteger(id) && id > 0 && Number.isInteger(quantity) && quantity > 0) {
        cart.set(id, Math.min(99, Math.max(cart.get(id) || 0, quantity)));
      }
    });
    updateCart();
    await apiPut("me/cart", {items:[...cart].map(([product_id,quantity])=>({product_id,quantity}))}, true);
    localStorage.setItem("sm360_cart_owner", String(currentUser.id));
    synchronized = true;
  } catch (_) {
    // Keep the local cart usable if the server cart is temporarily unavailable.
  } finally {
    cartSyncReady = synchronized;
    if (synchronized) persistCustomerCart();
  }
}
function persistCustomerCart() {
  if (!currentUser || !csrfToken || !cartSyncReady) return;
  const items = [...cart].map(([product_id,quantity])=>({product_id,quantity}));
  cartSaveChain = cartSaveChain.catch(() => {}).then(() => apiPut("me/cart", {items}, true)).catch(() => {});
}

async function ensureAccount() {
  try {
    currentUser = await apiGet("me");
    const csrf = await apiGet("auth/csrf");
    csrfToken = csrf?.csrf_token || "";
    document.getElementById("accountButton").textContent = currentUser?.full_name || "حساب کاربری";
    document.getElementById("ordersButton").hidden = false;
    document.getElementById("walletButton").hidden = false;
    if (!cartSyncReady) await syncCustomerCart();
    return Boolean(currentUser && csrfToken);
  } catch (_) { currentUser = null; csrfToken = ""; cartSyncReady = false; document.getElementById("ordersButton").hidden = true; document.getElementById("walletButton").hidden = true; return false; }
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
function renderSavedAddressManager() {
  const manager = document.getElementById("savedAddressManager");
  if (!manager) return;
  manager.innerHTML = savedAddresses.map(a =>
    '<article class="saved-address-row"><div><strong>'+escapeHTML(a.title)+'</strong><small>'+escapeHTML(a.recipient)+' · '+escapeHTML(a.mobile)+' · '+escapeHTML(a.city||"")+'</small><small>'+escapeHTML(a.address)+'</small></div><div class="saved-address-actions"><button type="button" class="text-button" data-address-edit="'+Number(a.id)+'">ویرایش</button><button type="button" class="text-button danger-text" data-address-delete="'+Number(a.id)+'">حذف</button></div></article>'
  ).join("") || '<p class="address-empty">هنوز نشانی ذخیره‌شده‌ای ندارید.</p>';
}
function resetAddressEditor() {
  editingAddressId = null;
  document.getElementById("addressEditActions").hidden = true;
  document.getElementById("deliveryAddressFields").hidden = document.getElementById("savedAddressSelect").value !== "new";
}
function fillAddressEditor(address) {
  document.getElementById("addressTitle").value = address.title || "";
  document.getElementById("addressRecipient").value = address.recipient || "";
  document.getElementById("addressMobile").value = address.mobile || "";
  document.getElementById("addressProvince").value = address.province || "";
  document.getElementById("addressCity").value = address.city || "";
  document.getElementById("addressText").value = address.address || "";
  document.getElementById("addressPostal").value = address.postal_code || "";
}
function addressEditorPayload() {
  return {
    title:document.getElementById("addressTitle").value.trim(),
    recipient:document.getElementById("addressRecipient").value.trim(),
    mobile:document.getElementById("addressMobile").value.trim(),
    province:document.getElementById("addressProvince").value.trim(),
    city:document.getElementById("addressCity").value.trim(),
    address:document.getElementById("addressText").value.trim(),
    postal_code:document.getElementById("addressPostal").value.trim()
  };
}
document.getElementById("savedAddressManager").addEventListener("click", async event => {
  const edit = event.target.closest("[data-address-edit]");
  const remove = event.target.closest("[data-address-delete]");
  try {
    if (edit) {
      const id = Number(edit.dataset.addressEdit);
      const address = savedAddresses.find(item => Number(item.id) === id);
      if (!address) return;
      editingAddressId = id;
      document.getElementById("savedAddressSelect").value = String(id);
      fillAddressEditor(address);
      document.getElementById("deliveryAddressFields").hidden = false;
      document.getElementById("addressEditActions").hidden = false;
      return;
    }
    if (remove) {
      const id = Number(remove.dataset.addressDelete);
      if (!window.confirm("این نشانی از فهرست نشانی‌های فعال حذف شود؟")) return;
      await apiDelete("me/addresses/"+id, true);
      savedAddresses = await apiGet("me/addresses") || [];
      renderSavedAddressManager();
      const select = document.getElementById("savedAddressSelect");
      select.innerHTML='<option value="new">ثبت نشانی جدید</option>'+savedAddresses.map(a=>'<option value="'+Number(a.id)+'">'+escapeHTML(a.title)+' — '+escapeHTML(a.city||"")+'، '+escapeHTML(a.address)+'</option>').join("");
      if (savedAddresses.length) select.value=String(savedAddresses[0].id);
      resetAddressEditor();
      setFormMessage("checkoutMessage","نشانی حذف شد.","success");
    }
  } catch (error) { setFormMessage("checkoutMessage",error.message); }
});
document.getElementById("savedAddressSelect").addEventListener("change", () => resetAddressEditor());
document.getElementById("saveAddressChanges").addEventListener("click", async () => {
  if (!editingAddressId) return;
  try {
    await apiPatch("me/addresses/"+editingAddressId,addressEditorPayload(),true);
    savedAddresses = await apiGet("me/addresses") || [];
    renderSavedAddressManager();
    const select = document.getElementById("savedAddressSelect");
    select.innerHTML='<option value="new">ثبت نشانی جدید</option>'+savedAddresses.map(a=>'<option value="'+Number(a.id)+'">'+escapeHTML(a.title)+' — '+escapeHTML(a.city||"")+'، '+escapeHTML(a.address)+'</option>').join("");
    select.value=String(editingAddressId);
    resetAddressEditor();
    setFormMessage("checkoutMessage","تغییرات نشانی ذخیره شد.","success");
  } catch (error) { setFormMessage("checkoutMessage",error.message); }
});
document.getElementById("cancelAddressEdit").addEventListener("click", () => resetAddressEditor());

async function loadPickupSlots() {
  const field=document.getElementById("pickupSlotField");
  const select=document.getElementById("pickupSlotSelect");
  const note=document.getElementById("pickupSlotNote");
  try{
    const result=await apiGet("pickup-slots");
    pickupSlotsConfigured=Boolean(result?.configured);
    availablePickupSlots=Array.isArray(result?.slots)?result.slots:[];
    select.innerHTML='<option value="">انتخاب بازه زمانی</option>'+availablePickupSlots.map(slot=>{
      return '<option value="'+Number(slot.id)+'">'+escapeHTML(slot.title)+' · '+escapeHTML(slot.starts_at_label||slot.starts_at)+' تا '+escapeHTML(slot.ends_at_label||slot.ends_at)+' · ظرفیت '+Number(slot.remaining_capacity)+'</option>';
    }).join("");
    note.textContent=!pickupSlotsConfigured?"تحویل حضوری بدون نیاز به انتخاب زمان انجام می‌شود.":availablePickupSlots.length?"یکی از بازه‌های دارای ظرفیت را انتخاب کنید.":"در حال حاضر بازه خالی برای تحویل حضوری وجود ندارد.";
  }catch(error){
    pickupSlotsConfigured=true;
    availablePickupSlots=[];
    select.innerHTML='<option value="">بازه‌ها بارگذاری نشدند</option>';
    note.textContent="دریافت زمان‌های تحویل ممکن نیست؛ برای ثبت سفارش حضوری دوباره تلاش کنید.";
  }
  field.hidden=document.getElementById("fulfillmentSelect").value!=="pickup"||!pickupSlotsConfigured;
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
  await loadPickupSlots();
  renderSavedAddressManager();
  const addressSelect=document.getElementById("savedAddressSelect");
  addressSelect.innerHTML='<option value="new">ثبت نشانی جدید</option>'+savedAddresses.map(a=>'<option value="'+Number(a.id)+'">'+escapeHTML(a.title)+' — '+escapeHTML(a.city||"")+'، '+escapeHTML(a.address)+'</option>').join("");
  if(savedAddresses.length){addressSelect.value=String(savedAddresses[0].id);document.getElementById("deliveryAddressFields").hidden=true;}
  else{addressSelect.value="new";document.getElementById("deliveryAddressFields").hidden=false;}
  resetAddressEditor();
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
  view.style.backgroundSize = scene.scene_type === "panorama" ? "200% 100%" : "cover";
  view.style.backgroundPosition = scene.scene_type === "panorama" ? panoramaOffset + "% center" : "center";
  view.classList.toggle("panorama-mode", scene.scene_type === "panorama");
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

function mapProductRows(rows) {
  return rows.map(row => ({
    id:Number(row.id),
    name:row.title||"کالای بدون نام",
    brand:row.brand||"",
    category:row.category_slug||"all",
    price:Math.round((Number(row.price_rial)||0)/10),
    unit:row.unit_label||"عدد",
    image:row.image?.url||"",
    inStock:row.in_stock !== false,
    description:row.description||"اطلاعات بیشتر این محصول در فروشگاه ثبت نشده است."
  }));
}
async function applyCatalogFilters() {
  const params=[];
  const query=document.getElementById("searchInput").value.trim();
  const brand=document.getElementById("productBrandFilter").value.trim();
  const minimum=document.getElementById("productMinPriceFilter").value;
  const maximum=document.getElementById("productMaxPriceFilter").value;
  const sort=document.getElementById("productSortFilter").value;
  if ((minimum!=="" && (!Number.isSafeInteger(Number(minimum)) || Number(minimum)<0)) ||
      (maximum!=="" && (!Number.isSafeInteger(Number(maximum)) || Number(maximum)<0)) ||
      (minimum!=="" && maximum!=="" && Number(minimum)>Number(maximum))) {
    document.getElementById("productCount").textContent="بازه قیمت را بررسی کنید.";
    return;
  }
  if(query)params.push("q="+encodeURIComponent(query));
  if(brand)params.push("brand="+encodeURIComponent(brand));
  if(minimum!=="")params.push("min_price="+Math.round(Number(minimum)*10));
  if(maximum!=="")params.push("max_price="+Math.round(Number(maximum)*10));
  if(sort)params.push("sort="+encodeURIComponent(sort));
  if(document.getElementById("productInStockFilter").checked)params.push("in_stock=1");
  try{
    const rows=await apiGet("products?"+params.join("&")+"&per_page=48");
    products=mapProductRows(Array.isArray(rows)?rows:[]);
    selectCategory("all");
  }catch(error){
    document.getElementById("productCount").textContent=error.message;
  }
}
document.getElementById("applyProductFilters").addEventListener("click",applyCatalogFilters);
document.getElementById("clearProductFilters").addEventListener("click",()=>{
  ["searchInput","productBrandFilter","productMinPriceFilter","productMaxPriceFilter"].forEach(id=>document.getElementById(id).value="");
  document.getElementById("productSortFilter").value="title_asc";
  document.getElementById("productInStockFilter").checked=false;
  applyCatalogFilters();
});
document.getElementById("searchInput").addEventListener("keydown",event=>{
  if(event.key==="Enter"){event.preventDefault();applyCatalogFilters();}
});

async function loadLiveCatalog() {
  try {
    const [store, categories, rows] = await Promise.all([
      apiGet("store"), apiGet("categories"), apiGet("products")
    ]);
    const profile = store?.settings?.profile || {};
    const checkout = store?.settings?.checkout || {};
    if (store?.name) {
      document.title = store.name + " | سوپرمارکت ۳۶۰";
      const brandName = document.querySelector(".brand > span:last-child");
      if (brandName) brandName.textContent = store.name;
    }
    if (profile.primary_color) {
      document.documentElement.style.setProperty("--forest", profile.primary_color);
      document.documentElement.style.setProperty("--forest-2", profile.primary_color);
    }
    if (profile.accent_color) {
      document.documentElement.style.setProperty("--lime", profile.accent_color);
      document.documentElement.style.setProperty("--orange", profile.accent_color);
    }
    if (profile.tagline) document.querySelector(".welcome-copy").textContent = profile.tagline;
    const deliveryOption = document.querySelector('#fulfillmentSelect option[value="delivery"]');
    const pickupOption = document.querySelector('#fulfillmentSelect option[value="pickup"]');
    if (deliveryOption) deliveryOption.disabled = store?.fulfillment?.delivery === false;
    if (pickupOption) pickupOption.disabled = store?.fulfillment?.pickup === false;
    const policyNote = document.getElementById("checkoutPolicyNote");
    if (policyNote) {
      const fee = Number(checkout.delivery_fee_rial || 0) / 10;
      const minimum = Number(checkout.minimum_order_rial || 0) / 10;
      const reservation = Number(checkout.reservation_minutes || 20);
      policyNote.textContent = "هزینه ارسال: " + money(fee) + " · حداقل سفارش: " + money(minimum) + " · مهلت پرداخت: " + reservation + " دقیقه";
    }
    const fulfillmentSelect = document.getElementById("fulfillmentSelect");
    if (fulfillmentSelect && fulfillmentSelect.selectedOptions[0]?.disabled) {
      fulfillmentSelect.value = deliveryOption && !deliveryOption.disabled ? "delivery" : "pickup";
    }
    const trustItems = document.querySelectorAll(".trust-row span");
    if (trustItems[1]) trustItems[1].hidden = store?.fulfillment?.delivery === false;
    if (trustItems[2]) trustItems[2].hidden = store?.fulfillment?.pickup === false;
    if (Array.isArray(categories)) renderCategories(categories);
    if (Array.isArray(rows)) {
      products = mapProductRows(rows);
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
    if (!profile.tagline) document.querySelector(".welcome-copy").textContent =
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
    document.getElementById("ordersButton").hidden=false;
    setFormMessage("accountMessage","ورود با موفقیت انجام شد.","success");
    if(pendingCheckout){pendingCheckout=false;document.getElementById("accountDialog").close();startCheckout();}
  } catch(error){setFormMessage("accountMessage",error.message,"error");}
});
document.getElementById("fulfillmentSelect").addEventListener("change",e=>{
  const useDelivery=e.target.value==="delivery";
  document.getElementById("savedAddressSelect").parentElement.hidden=!useDelivery;
  document.getElementById("deliveryAddressFields").hidden=!useDelivery||document.getElementById("savedAddressSelect").value!=="new";
});
document.getElementById("fulfillmentSelect").addEventListener("change",e=>{
  document.getElementById("pickupSlotField").hidden=e.target.value!=="pickup"||!pickupSlotsConfigured;
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
    const pickupSlotValue=document.getElementById("pickupSlotSelect").value;
    if(fulfillment==="pickup"&&pickupSlotsConfigured&&!pickupSlotValue){
      throw new Error("برای تحویل حضوری یک بازه زمانی دارای ظرفیت انتخاب کنید.");
    }
    const pickupSlotId=fulfillment==="pickup"&&pickupSlotsConfigured?Number(pickupSlotValue):null;
    if(fulfillment==="delivery"){
      const selectedAddress=document.getElementById("savedAddressSelect").value;
      if(selectedAddress!=="new"){
        addressId=Number(selectedAddress);
      }else{
      const address=await apiPost("me/addresses",addressEditorPayload(),true);
      addressId=address.data.id;
      }
    }
    const order=await apiPost("checkout",{
      items:[...cart].map(([product_id,quantity])=>({product_id,quantity})),
      fulfillment,address_id:addressId,pickup_slot_id:pickupSlotId,
      customer_note:document.getElementById("orderNote").value.trim()
    },true);
    pendingPaymentOrderId=Number(order.data.id);
    cart.clear();updateCart();persistCustomerCart();
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

// Customer wallet: balance is server-derived from the append-only ledger.
async function loadCustomerWallet() {
  const wallet = await apiGet("me/wallet");
  document.getElementById("walletBalance").textContent = money(Math.round(Number(wallet.balance_rial || 0) / 10));
  const typeLabels = {credit:"افزایش اعتبار",debit:"مصرف اعتبار",reversal:"برگشت اعتبار"};
  const sourceLabels = {referral_reward:"پاداش دعوت دوستان"};
  const entries = Array.isArray(wallet.entries) ? wallet.entries : [];
  document.getElementById("walletEntries").innerHTML = entries.map(entry =>
    '<article class="customer-order"><div><strong>'+escapeHTML(sourceLabels[entry.source_type]||typeLabels[entry.entry_type]||"تراکنش کیف پول")+'</strong><small>'+escapeHTML(entry.note||"")+' · '+escapeHTML(entry.created_at||"")+'</small></div><strong>'+money(Math.round(Number(entry.amount_rial||0)/10))+'</strong></article>'
  ).join("") || '<p class="empty-cart">هنوز تراکنشی ثبت نشده است.</p>';
  const statusLabels = {pending:"در انتظار خرید واجد شرایط",qualified:"واجد شرایط",rewarded:"پاداش واریز شد",rejected:"دعوت تأیید نشد"};
  const referrals = Array.isArray(wallet.referrals) ? wallet.referrals : [];
  document.getElementById("walletReferrals").innerHTML = referrals.map(referral =>
    '<article class="customer-order"><div><strong>'+escapeHTML(statusLabels[referral.status]||referral.status)+'</strong><small>'+escapeHTML(referral.created_at||"")+'</small></div><strong>'+money(Math.round(Number(referral.reward_rial||0)/10))+'</strong></article>'
  ).join("") || '<p class="empty-cart">هنوز دعوتی ثبت نشده است.</p>';
}
document.getElementById("walletButton").addEventListener("click",async()=>{
  if(!(await ensureAccount())){openAccountDialog();return;}
  document.getElementById("walletDialog").showModal();
  document.getElementById("walletBalance").textContent="در حال بارگذاری...";
  try{await loadCustomerWallet();}
  catch(error){document.getElementById("walletEntries").textContent=error.message;document.getElementById("walletReferrals").textContent="";}
});

// Customer order history and owner-only order detail/cancellation.
async function loadCustomerOrders() {
  const list=document.getElementById("customerOrdersList");
  const detail=document.getElementById("customerOrderDetail");
  detail.innerHTML="";
  try {
    const rows=await apiGet("me/orders");
    const labels={pending_payment:"در انتظار پرداخت",paid:"پرداخت‌شده",accepted:"پذیرفته‌شده",picking:"در حال آماده‌سازی",packed:"بسته‌بندی‌شده",ready_pickup:"آماده تحویل",dispatched:"ارسال‌شده",delivered:"تحویل‌شده",cancelled:"لغوشده",refunded:"مستردشده"};
    list.innerHTML=(rows||[]).map(o=>'<article class="customer-order"><div><strong>'+escapeHTML(o.order_number)+'</strong><small>'+escapeHTML(labels[o.status]||o.status)+' · '+money(Math.round(Number(o.total_rial)/10))+'</small>'+(o.pickup_slot_starts_at_label?'<small>تحویل: '+escapeHTML(o.pickup_slot_title)+' · '+escapeHTML(o.pickup_slot_starts_at_label)+'</small>':"")+'</div><button data-order-detail="'+Number(o.id)+'">جزئیات</button>'+(o.status==="pending_payment"?'<button data-order-pay="'+Number(o.id)+'">پرداخت</button><button data-order-cancel="'+Number(o.id)+'">لغو</button>':"")+'</article>').join("")||'<p class="empty-cart">هنوز سفارشی ثبت نکرده‌اید.</p>';
  } catch(error) { list.textContent=error.message; }
}
document.getElementById("ordersButton").addEventListener("click",async()=>{
  if(!(await ensureAccount())){openAccountDialog();return;}
  await loadCustomerOrders();
  document.getElementById("ordersDialog").showModal();
});
document.getElementById("customerOrdersList").addEventListener("click",async e=>{
  const detail=e.target.closest("[data-order-detail]");
  const cancel=e.target.closest("[data-order-cancel]");
  if(detail){
    try{
      const order=await apiGet("me/orders/"+detail.dataset.orderDetail);
      const pickupInfo=order.pickup_slot_starts_at_label?'<p>زمان تحویل حضوری: '+escapeHTML(order.pickup_slot_title)+' · '+escapeHTML(order.pickup_slot_starts_at_label)+' تا '+escapeHTML(order.pickup_slot_ends_at_label)+'</p>':"";
      document.getElementById("customerOrderDetail").innerHTML='<div class="customer-order-detail"><strong>جزئیات سفارش '+escapeHTML(order.order_number)+'</strong>'+pickupInfo+order.items.map(item=>'<p>'+escapeHTML(item.title)+' × '+new Intl.NumberFormat("fa-IR").format(Number(item.quantity))+' — '+money(Math.round(Number(item.line_total_rial)/10))+'</p>').join("")+'</div>';
    }catch(error){document.getElementById("customerOrderDetail").textContent=error.message;}
  }
  const pay=e.target.closest("[data-order-pay]");
  if(pay){
    try{
      const payment=await apiPost("payments/zarinpal/request",{order_id:Number(pay.dataset.orderPay)},true);
      if(payment.data?.redirect_url)window.location.assign(payment.data.redirect_url);
      else document.getElementById("customerOrderDetail").textContent="درگاه پرداخت هنوز پیکربندی نشده است.";
    }catch(error){document.getElementById("customerOrderDetail").textContent=error.message;}
  }
  if(cancel&&confirm("این سفارش لغو شود؟")){
    try{await apiPost("me/orders/"+cancel.dataset.orderCancel+"/cancel",{},true);await loadCustomerOrders();}
    catch(error){document.getElementById("customerOrderDetail").textContent=error.message;}
  }
});

// Drag-to-pan support for uploaded equirectangular panorama scenes.
const panoramaView=document.getElementById("aisleScene");
panoramaView.addEventListener("pointerdown",event=>{
  if(publicScenes[currentScene]?.scene_type!=="panorama")return;
  panoramaPointer={id:event.pointerId,startX:event.clientX,startOffset:panoramaOffset};
  panoramaView.setPointerCapture(event.pointerId);
});
panoramaView.addEventListener("pointermove",event=>{
  if(!panoramaPointer||panoramaPointer.id!==event.pointerId)return;
  const delta=(event.clientX-panoramaPointer.startX)/Math.max(1,panoramaView.clientWidth)*100;
  panoramaOffset=(panoramaPointer.startOffset-delta)%100;
  if(panoramaOffset<0)panoramaOffset+=100;
  panoramaView.style.backgroundPosition=panoramaOffset+"% center";
});
const stopPanoramaDrag=()=>{panoramaPointer=null;};
panoramaView.addEventListener("pointerup",stopPanoramaDrag);
panoramaView.addEventListener("pointercancel",stopPanoramaDrag);

// Restore the signed-in customer controls when returning to the storefront.
ensureAccount();
