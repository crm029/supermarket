"use strict";
(()=>{
  const $=id=>document.getElementById(id);
  const fa=new Intl.NumberFormat("fa-IR");
  const money=value=>fa.format(Math.round(Number(value||0)/10))+" تومان";
  const escape=value=>String(value??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  const params=new URLSearchParams(window.location.search);
  const saleId=Number(params.get("sale_id"));
  const paper=$("receiptPaper");
  $("receiptWidth").addEventListener("change",()=>{paper.classList.toggle("receipt-58",$("receiptWidth").value==="58");paper.classList.toggle("receipt-80",$("receiptWidth").value==="80");});
  $("receiptPrint").addEventListener("click",()=>window.print());
  async function load(){
    if(!Number.isSafeInteger(saleId)||saleId<1){$("receiptMessage").textContent="شناسه رسید معتبر نیست.";return;}
    try{
      const response=await fetch("api/v1/admin/pos/sales/"+saleId,{headers:{Accept:"application/json"},credentials:"same-origin"});
      const payload=await response.json().catch(()=>({}));
      if(!response.ok)throw new Error(payload.error||"دریافت رسید انجام نشد.");
      const sale=payload.data.sale,items=payload.data.items||[],refunds=payload.data.refunds||[];
      paper.innerHTML='<header class="receipt-brand"><h1>سوپرمارکت ۳۶۰</h1><p>رسید فروش حضوری</p></header>'+
        '<section class="receipt-meta"><div><span>شماره فاکتور</span><strong>'+escape(sale.sale_number)+'</strong></div><div><span>تاریخ</span><span>'+escape(sale.created_at)+'</span></div><div><span>صندوق‌دار</span><span>'+escape(sale.cashier_name||"—")+'</span></div><div><span>شماره شیفت</span><span>'+fa.format(Number(sale.shift_id))+'</span></div></section>'+
        '<table class="receipt-items"><thead><tr><th>کالا</th><th>تعداد</th><th>مبلغ</th></tr></thead><tbody>'+items.map(item=>'<tr><td><span class="receipt-item-name">'+escape(item.title)+'</span><span class="receipt-item-sub">'+escape(item.sku)+'</span></td><td>'+fa.format(Number(item.quantity)/1000)+'</td><td>'+money(item.line_total_rial)+'</td></tr>').join("")+'</tbody></table>'+
        '<section class="receipt-totals"><div><span>جمع جزء</span><strong>'+money(sale.subtotal_rial)+'</strong></div><div><span>تخفیف</span><strong>'+money(sale.discount_rial)+'</strong></div><div class="receipt-grand"><span>مبلغ نهایی</span><strong>'+money(sale.total_rial)+'</strong></div><div><span>وجه نقد دریافتی</span><strong>'+money(sale.cash_received_rial)+'</strong></div><div><span>باقی‌پول</span><strong>'+money(sale.change_rial)+'</strong></div></section>'+
        (sale.note?'<div class="receipt-note">توضیح: '+escape(sale.note)+'</div>':"")+
        (refunds.length?'<section class="receipt-refunds"><strong>سوابق مرجوعی</strong>'+refunds.map(r=>'<div>'+money(r.amount_rial)+' · '+escape(r.reason)+' · '+escape(r.created_at)+'</div>').join("")+'</section>':"")+
        '<footer class="receipt-footer"><p>از خرید شما سپاسگزاریم</p><p>سوپرمارکت ۳۶۰</p></footer>';
      paper.hidden=false;
      $("receiptMessage").textContent="";
      $("receiptPrint").disabled=false;
      document.title="رسید "+sale.sale_number+" | سوپرمارکت ۳۶۰";
    }catch(error){$("receiptMessage").textContent=error.message;}
  }
  load();
})();
