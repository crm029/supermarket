<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/commerce.php';

function app_pos_body(): array { return app_auth_request_body(); }

function app_pos_amount(mixed $value, string $label): int {
    $number=filter_var($value,FILTER_VALIDATE_INT);
    if($number===false || $number<0 || $number>1000000000000) app_json(['error'=>$label.' معتبر نیست.'],422);
    return (int)$number;
}

function app_pos_active_shift(PDO $db,int $tenantId,int $cashierId,bool $lock=false): ?array {
    $sql="SELECT s.id,s.cashier_user_id,s.warehouse_id,s.status,s.opening_float_rial,s.opened_at,w.title warehouse_title
          FROM pos_shifts s JOIN warehouses w ON w.tenant_id=s.tenant_id AND w.id=s.warehouse_id
          WHERE s.tenant_id=? AND s.cashier_user_id=? AND s.status='open' LIMIT 1".($lock?' FOR UPDATE':'');
    $q=$db->prepare($sql);$q->execute([$tenantId,$cashierId]);$row=$q->fetch(PDO::FETCH_ASSOC);return $row?:null;
}

function app_pos_summary(PDO $db,int $tenantId,int $shiftId): array {
    $q=$db->prepare("SELECT COUNT(*) sale_count,COALESCE(SUM(total_rial),0) sales_total_rial FROM pos_sales WHERE tenant_id=? AND shift_id=?");
    $q->execute([$tenantId,$shiftId]);$row=$q->fetch(PDO::FETCH_ASSOC)?:['sale_count'=>0,'sales_total_rial'=>0];
    $refunds=$db->prepare("SELECT COALESCE(SUM(amount_rial),0) refund_total_rial FROM pos_refunds WHERE tenant_id=? AND shift_id=?");
    $refunds->execute([$tenantId,$shiftId]);$row['refund_total_rial']=(int)$refunds->fetchColumn();
    return $row;
}

function app_pos_shift_current(PDO $db,array $tenant,array $session): never {
    $shift=app_pos_active_shift($db,(int)$tenant['id'],(int)$session['id']);
    if(!$shift)app_json(['data'=>['shift'=>null]]);
    $summary=app_pos_summary($db,(int)$tenant['id'],(int)$shift['id']);
    $shift['summary']=$summary;$shift['expected_cash_rial']=(int)$shift['opening_float_rial']+(int)$summary['sales_total_rial']-(int)$summary['refund_total_rial'];
    app_json(['data'=>['shift'=>$shift]]);
}

function app_pos_shift_open(PDO $db,array $tenant,array $session): never {
    $body=app_pos_body();$warehouseId=filter_var($body['warehouse_id']??null,FILTER_VALIDATE_INT);
    if($warehouseId===false||$warehouseId<1)app_json(['error'=>'انبار صندوق معتبر نیست.'],422);
    $opening=app_pos_amount($body['opening_float_rial']??0,'موجودی اولیه صندوق');$note=trim((string)($body['note']??''));
    if(mb_strlen($note)>255)app_json(['error'=>'شرح شیفت بیش از حد طولانی است.'],422);
    $db->beginTransaction();
    try{
        $warehouse=$db->prepare('SELECT id FROM warehouses WHERE tenant_id=? AND id=? AND is_active=1 FOR UPDATE');
        $warehouse->execute([$tenant['id'],$warehouseId]);
        if(!$warehouse->fetchColumn()){$db->rollBack();app_json(['error'=>'انبار فعال این فروشگاه انتخاب نشده است.'],422);}
        if(app_pos_active_shift($db,(int)$tenant['id'],(int)$session['id'],true)){$db->rollBack();app_json(['error'=>'برای این صندوق‌دار شیفت باز وجود دارد.'],409);}
        $insert=$db->prepare("INSERT INTO pos_shifts (tenant_id,cashier_user_id,warehouse_id,status,opening_float_rial,opening_note) VALUES (?,?,?,'open',?,?)");
        $insert->execute([$tenant['id'],$session['id'],$warehouseId,$opening,$note===''?null:$note]);$id=(int)$db->lastInsertId();
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'pos.shift.opened','pos_shift',$id,['warehouse_id'=>$warehouseId,'opening_float_rial'=>$opening]);
        $db->commit();app_json(['data'=>['id'=>$id,'status'=>'open','opening_float_rial'=>$opening],'message'=>'شیفت صندوق باز شد.'],201);
    }catch(PDOException $e){if($db->inTransaction())$db->rollBack();if($e->getCode()==='23000')app_json(['error'=>'برای این صندوق‌دار هم‌اکنون شیفت بازی وجود دارد.'],409);throw $e;}
    catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_pos_shift_close(PDO $db,array $tenant,array $session,int $shiftId): never {
    $body=app_pos_body();$counted=app_pos_amount($body['counted_cash_rial']??null,'وجه شمارش‌شده');$note=trim((string)($body['note']??''));
    if(mb_strlen($note)>255)app_json(['error'=>'شرح بستن شیفت طولانی است.'],422);
    $db->beginTransaction();
    try{
        $find=$db->prepare('SELECT id,opening_float_rial,status FROM pos_shifts WHERE tenant_id=? AND id=? AND cashier_user_id=? FOR UPDATE');
        $find->execute([$tenant['id'],$shiftId,$session['id']]);$shift=$find->fetch(PDO::FETCH_ASSOC);
        if(!$shift){$db->rollBack();app_json(['error'=>'شیفت صندوق پیدا نشد.'],404);}
        if($shift['status']!=='open'){$db->rollBack();app_json(['error'=>'این شیفت قبلاً بسته شده است.'],409);}
        $summary=app_pos_summary($db,(int)$tenant['id'],$shiftId);
        $expected=(int)$shift['opening_float_rial']+(int)$summary['sales_total_rial']-(int)$summary['refund_total_rial'];$variance=$counted-$expected;
        $update=$db->prepare("UPDATE pos_shifts SET status='closed',expected_cash_rial=?,counted_cash_rial=?,cash_variance_rial=?,closing_note=?,closed_at=UTC_TIMESTAMP() WHERE tenant_id=? AND id=? AND status='open'");
        $update->execute([$expected,$counted,$variance,$note===''?null:$note,$tenant['id'],$shiftId]);
        if($update->rowCount()!==1)throw new RuntimeException('POS shift close conflict.');
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'pos.shift.closed','pos_shift',$shiftId,['expected_cash_rial'=>$expected,'counted_cash_rial'=>$counted,'cash_variance_rial'=>$variance,'sale_count'=>(int)$summary['sale_count'],'refund_total_rial'=>(int)$summary['refund_total_rial']]);
        $db->commit();app_json(['data'=>['id'=>$shiftId,'status'=>'closed','sale_count'=>(int)$summary['sale_count'],'refund_total_rial'=>(int)$summary['refund_total_rial'],'expected_cash_rial'=>$expected,'counted_cash_rial'=>$counted,'cash_variance_rial'=>$variance],'message'=>'شیفت بسته شد و مغایرت صندوق ثبت شد.']);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_pos_catalog_search(PDO $db,array $tenant): never {
    $query=trim((string)($_GET['q']??''));if($query===''||mb_strlen($query)>100)app_json(['error'=>'بارکد یا عبارت جست‌وجو معتبر نیست.'],422);
    $stmt=$db->prepare("SELECT p.id,p.sku,p.barcode,CASE WHEN p.product_kind='variant' THEN CONCAT(parent.title,' · ',p.variant_label) ELSE p.title END title,p.unit_label,p.price_rial,p.is_weighted
      FROM products p LEFT JOIN products parent ON parent.tenant_id=p.tenant_id AND parent.id=p.parent_product_id
      WHERE p.tenant_id=? AND p.is_active=1 AND p.product_kind<>'variant_parent' AND (p.barcode=? OR p.sku=? OR p.title LIKE ?)
      ORDER BY (p.barcode=?) DESC,(p.sku=?) DESC,p.title LIMIT 30");
    $like='%'.$query.'%';$stmt->execute([$tenant['id'],$query,$query,$like,$query,$query]);app_json(['data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function app_pos_create_sale(PDO $db,array $tenant,array $session): never {
    $body=app_pos_body();$key=trim((string)($body['idempotency_key']??''));
    if(!preg_match('/^[A-Za-z0-9._:-]{12,100}$/',$key))app_json(['error'=>'کلید یکتای فروش معتبر نیست.'],422);
    $received=app_pos_amount($body['cash_received_rial']??null,'وجه دریافتی');$note=trim((string)($body['note']??''));
    if(mb_strlen($note)>255)app_json(['error'=>'شرح فروش بیش از حد طولانی است.'],422);
    $lines=app_commerce_lines($body);ksort($lines,SORT_NUMERIC);$requestHash=hash('sha256',(string)json_encode(['items'=>$lines,'cash_received_rial'=>$received,'note'=>$note],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$db->beginTransaction();
    try{
        $shift=app_pos_active_shift($db,(int)$tenant['id'],(int)$session['id'],true);
        if(!$shift){$db->rollBack();app_json(['error'=>'ابتدا شیفت صندوق را باز کنید.'],409);}
        $existing=$db->prepare('SELECT id,sale_number,total_rial,cash_received_rial,change_rial,request_hash FROM pos_sales WHERE tenant_id=? AND cashier_user_id=? AND idempotency_key=? LIMIT 1');
        $existing->execute([$tenant['id'],$session['id'],$key]);$prior=$existing->fetch(PDO::FETCH_ASSOC);
        if($prior){if(!hash_equals((string)$prior['request_hash'],$requestHash)){$db->rollBack();app_json(['error'=>'کلید تکراری فروش با اطلاعات متفاوت استفاده شده است.'],409);}$db->commit();unset($prior['request_hash']);app_json(['data'=>$prior,'message'=>'این فروش قبلاً ثبت شده است.']);}
        $ids=array_keys($lines);sort($ids,SORT_NUMERIC);$marks=implode(',',array_fill(0,count($ids),'?'));
        $lock=$db->prepare("SELECT id FROM products WHERE tenant_id=? AND is_active=1 AND id IN ({$marks}) ORDER BY id FOR UPDATE");
        $lock->execute(array_merge([$tenant['id']],$ids));
        if(count($lock->fetchAll())!==count($ids)){$db->rollBack();app_json(['error'=>'یکی از کالاها غیرفعال یا متعلق به فروشگاه دیگری است.'],409);}
        $quote=app_commerce_quote($db,(int)$tenant['id'],$lines,false);$total=(int)$quote['subtotal_rial'];
        if($total<1||$received<$total){$db->rollBack();app_json(['error'=>'وجه دریافتی باید حداقل برابر مبلغ فروش باشد.'],422);}
        $change=$received-$total;$saleNumber='POS'.gmdate('ymdHis').strtoupper(bin2hex(random_bytes(3)));
        $sale=$db->prepare('INSERT INTO pos_sales (tenant_id,shift_id,cashier_user_id,sale_number,idempotency_key,request_hash,subtotal_rial,discount_rial,total_rial,cash_received_rial,change_rial,note) VALUES (?,?,?,?,?,?,?,0,?,?,?,?)');
        $sale->execute([$tenant['id'],$shift['id'],$session['id'],$saleNumber,$key,$requestHash,$total,$total,$received,$change,$note===''?null:$note]);$saleId=(int)$db->lastInsertId();
        $itemInsert=$db->prepare('INSERT INTO pos_sale_items (tenant_id,sale_id,product_id,product_title_snapshot,sku_snapshot,unit_price_rial,quantity,line_total_rial) VALUES (?,?,?,?,?,?,?,?)');
        $stockLock=$db->prepare('SELECT on_hand,reserved FROM stock_levels WHERE tenant_id=? AND warehouse_id=? AND product_id=? FOR UPDATE');
        $batchLock=$db->prepare('SELECT id,on_hand,reserved FROM stock_batches WHERE tenant_id=? AND warehouse_id=? AND product_id=? AND on_hand>reserved AND (expiry_date IS NULL OR expiry_date>=UTC_DATE()) ORDER BY (expiry_date IS NULL),expiry_date,received_at,id FOR UPDATE');
        $batchUpdate=$db->prepare('UPDATE stock_batches SET on_hand=on_hand-? WHERE tenant_id=? AND id=? AND on_hand-reserved>=?');
        $stockUpdate=$db->prepare('UPDATE stock_levels SET on_hand=on_hand-? WHERE tenant_id=? AND warehouse_id=? AND product_id=? AND on_hand-reserved>=?');
        $movement=$db->prepare("INSERT INTO stock_movements (tenant_id,warehouse_id,product_id,actor_user_id,movement_type,quantity,reference_type,reference_id,reason) VALUES (?,?,?,?,'sale',?,'pos_sale',?,?)");
        foreach($quote['items'] as $item){
            $pid=(int)$item['product_id'];$qty=(int)$item['quantity'];$stockLock->execute([$tenant['id'],$shift['warehouse_id'],$pid]);$stock=$stockLock->fetch(PDO::FETCH_ASSOC);
            if(!$stock||(int)$stock['on_hand']-(int)$stock['reserved']<$qty){$db->rollBack();app_json(['error'=>'موجودی آزاد کالا در انبار صندوق کافی نیست: '.$item['title']],409);}
            $batchLock->execute([$tenant['id'],$shift['warehouse_id'],$pid]);$remaining=$qty;
            foreach($batchLock->fetchAll(PDO::FETCH_ASSOC) as $batch){if($remaining===0)break;$take=min($remaining,(int)$batch['on_hand']-(int)$batch['reserved']);if($take<1)continue;$batchUpdate->execute([$take,$tenant['id'],$batch['id'],$take]);if($batchUpdate->rowCount()!==1)throw new RuntimeException('POS batch stock changed during sale.');$remaining-=$take;}
            if($remaining>0){$db->rollBack();app_json(['error'=>'موجودی بچ‌های معتبر و تاریخ‌دار کافی نیست: '.$item['title']],409);}
            $stockUpdate->execute([$qty,$tenant['id'],$shift['warehouse_id'],$pid,$qty]);if($stockUpdate->rowCount()!==1)throw new RuntimeException('POS stock level changed during sale.');
            $itemInsert->execute([$tenant['id'],$saleId,$pid,$item['title'],$item['sku'],$item['unit_price_rial'],$qty,$item['line_total_rial']]);
            $movement->execute([$tenant['id'],$shift['warehouse_id'],$pid,$session['id'],-$qty,$saleId,'فروش نقدی حضوری '.$saleNumber]);
        }
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'pos.sale.created','pos_sale',$saleId,['sale_number'=>$saleNumber,'shift_id'=>(int)$shift['id'],'total_rial'=>$total,'cash_received_rial'=>$received,'change_rial'=>$change,'item_count'=>count($quote['items'])]);
        $db->commit();app_json(['data'=>['id'=>$saleId,'sale_number'=>$saleNumber,'total_rial'=>$total,'cash_received_rial'=>$received,'change_rial'=>$change,'items'=>$quote['items'],'currency'=>'IRR'],'message'=>'فروش نقدی ثبت شد و موجودی انبار به‌روزرسانی شد.'],201);
    }catch(PDOException $e){if($db->inTransaction())$db->rollBack();if($e->getCode()==='23000')app_json(['error'=>'این درخواست فروش قبلاً ثبت شده یا با یک درخواست هم‌زمان برخورد کرده است.'],409);throw $e;}
    catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_pos_sales_list(PDO $db,array $tenant,array $session): never {
    $shiftId=filter_input(INPUT_GET,'shift_id',FILTER_VALIDATE_INT);
    $sql='SELECT id,sale_number,shift_id,total_rial,cash_received_rial,change_rial,note,created_at FROM pos_sales WHERE tenant_id=?';
    $params=[(int)$tenant['id']];
    if(!app_auth_has_permission($db,$tenant,$session,'pos.refund.create')){$sql.=' AND cashier_user_id=?';$params[]=(int)$session['id'];}
    if($shiftId!==null&&$shiftId!==false){$sql.=' AND shift_id=?';$params[]=$shiftId;}
    $sql.=' ORDER BY id DESC LIMIT 100';$stmt=$db->prepare($sql);$stmt->execute($params);app_json(['data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}



function app_pos_sale_detail(PDO $db,array $tenant,array $session,int $saleId): never {
    $saleQuery=$db->prepare(
        'SELECT id,sale_number,shift_id,cashier_user_id,subtotal_rial,discount_rial,total_rial,
                cash_received_rial,change_rial,note,created_at
         FROM pos_sales WHERE tenant_id=? AND id=? LIMIT 1'
    );
    $saleQuery->execute([(int)$tenant['id'],$saleId]);
    $sale=$saleQuery->fetch(PDO::FETCH_ASSOC);
    if(!$sale)app_json(['error'=>'فروش حضوری پیدا نشد.'],404);
    if((int)$sale['cashier_user_id']!==(int)$session['id']&&!app_auth_has_permission($db,$tenant,$session,'pos.refund.create'))app_json(['error'=>'فروش حضوری پیدا نشد.'],404);
    $itemsQuery=$db->prepare(
        "SELECT i.id sale_item_id,i.product_id,i.product_title_snapshot title,i.sku_snapshot sku,
                i.unit_price_rial,i.quantity,i.line_total_rial,
                COALESCE(r.refunded_quantity,0) refunded_quantity,
                GREATEST(CAST(i.quantity AS SIGNED)-CAST(COALESCE(r.refunded_quantity,0) AS SIGNED),0) refundable_quantity
         FROM pos_sale_items i
         LEFT JOIN (
            SELECT ri.tenant_id,ri.sale_item_id,SUM(ri.quantity) refunded_quantity
            FROM pos_refund_items ri
            JOIN pos_refunds rf ON rf.tenant_id=ri.tenant_id AND rf.id=ri.refund_id
            WHERE ri.tenant_id=? AND rf.sale_id=?
            GROUP BY ri.tenant_id,ri.sale_item_id
         ) r ON r.tenant_id=i.tenant_id AND r.sale_item_id=i.id
         WHERE i.tenant_id=? AND i.sale_id=? ORDER BY i.id"
    );
    $itemsQuery->execute([(int)$tenant['id'],$saleId,(int)$tenant['id'],$saleId]);
    $refundsQuery=$db->prepare(
        'SELECT id,amount_rial,reason,stock_status,created_at
         FROM pos_refunds WHERE tenant_id=? AND sale_id=? ORDER BY id DESC'
    );
    $refundsQuery->execute([(int)$tenant['id'],$saleId]);
    app_json(['data'=>['sale'=>$sale,'items'=>$itemsQuery->fetchAll(PDO::FETCH_ASSOC),'refunds'=>$refundsQuery->fetchAll(PDO::FETCH_ASSOC)]]);
}


function app_pos_management_report(PDO $db,array $tenant): never {
    [$fromUtc,$toUtc,$fromKey,$toKey]=app_report_date_range($tenant);
    $tenantId=(int)$tenant['id'];
    $summary=$db->prepare(
        "SELECT COUNT(*) sale_count,COALESCE(SUM(total_rial),0) sales_total_rial,
                COALESCE(SUM(cash_received_rial),0) cash_received_rial,
                COALESCE(SUM(change_rial),0) change_rial
         FROM pos_sales WHERE tenant_id=? AND created_at>=? AND created_at<?"
    );
    $summary->execute([$tenantId,$fromUtc,$toUtc]);
    $sales=$summary->fetch(PDO::FETCH_ASSOC)?:[];
    $refundSummary=$db->prepare(
        "SELECT COUNT(*) refund_count,COALESCE(SUM(amount_rial),0) refund_total_rial
         FROM pos_refunds WHERE tenant_id=? AND created_at>=? AND created_at<?"
    );
    $refundSummary->execute([$tenantId,$fromUtc,$toUtc]);
    $refunds=$refundSummary->fetch(PDO::FETCH_ASSOC)?:[];
    $grossSales=(int)($sales['sales_total_rial']??0);
    $refundTotal=(int)($refunds['refund_total_rial']??0);
    $netSales=$grossSales-$refundTotal;
    $shifts=$db->prepare(
        "SELECT COUNT(*) shift_count,
                SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) open_shift_count,
                SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) closed_shift_count,
                SUM(CASE WHEN status='closed' AND cash_variance_rial<>0 THEN 1 ELSE 0 END) variance_shift_count,
                COALESCE(SUM(CASE WHEN status='closed' THEN cash_variance_rial ELSE 0 END),0) net_variance_rial
         FROM pos_shifts WHERE tenant_id=? AND opened_at>=? AND opened_at<?"
    );
    $shifts->execute([$tenantId,$fromUtc,$toUtc]);
    $shiftSummary=$shifts->fetch(PDO::FETCH_ASSOC)?:[];
    $cashiers=$db->prepare(
        "SELECT s.cashier_user_id,u.full_name,COUNT(DISTINCT s.id) shift_count,
                (SELECT COUNT(*) FROM pos_shifts os WHERE os.tenant_id=s.tenant_id AND os.cashier_user_id=s.cashier_user_id
                   AND os.status='open' AND os.opened_at>=? AND os.opened_at<?) open_shift_count,
                COUNT(DISTINCT ps.id) sale_count,COALESCE(SUM(ps.total_rial),0) sales_total_rial,
                (SELECT COUNT(*) FROM pos_shifts vs WHERE vs.tenant_id=s.tenant_id AND vs.cashier_user_id=s.cashier_user_id
                   AND vs.status='closed' AND vs.cash_variance_rial<>0 AND vs.opened_at>=? AND vs.opened_at<?) variance_shift_count,
                (SELECT COALESCE(SUM(vs.cash_variance_rial),0) FROM pos_shifts vs WHERE vs.tenant_id=s.tenant_id
                   AND vs.cashier_user_id=s.cashier_user_id AND vs.status='closed' AND vs.opened_at>=? AND vs.opened_at<?) net_variance_rial
         FROM pos_shifts s JOIN users u ON u.tenant_id=s.tenant_id AND u.id=s.cashier_user_id
         LEFT JOIN pos_sales ps ON ps.tenant_id=s.tenant_id AND ps.shift_id=s.id
             AND ps.created_at>=? AND ps.created_at<?
         WHERE s.tenant_id=? AND s.opened_at>=? AND s.opened_at<?
         GROUP BY s.cashier_user_id,u.full_name ORDER BY sales_total_rial DESC,u.full_name"
    );
    $cashiers->execute([$fromUtc,$toUtc,$fromUtc,$toUtc,$fromUtc,$toUtc,$fromUtc,$toUtc,$tenantId,$fromUtc,$toUtc]);
    $topItems=$db->prepare(
        "SELECT i.product_id,i.product_title_snapshot title,i.sku_snapshot sku,
                SUM(i.quantity) quantity_sold,SUM(i.line_total_rial) revenue_rial
         FROM pos_sale_items i JOIN pos_sales s ON s.tenant_id=i.tenant_id AND s.id=i.sale_id
         WHERE i.tenant_id=? AND s.created_at>=? AND s.created_at<?
         GROUP BY i.product_id,i.product_title_snapshot,i.sku_snapshot
         ORDER BY quantity_sold DESC,revenue_rial DESC LIMIT 10"
    );
    $topItems->execute([$tenantId,$fromUtc,$toUtc]);
    $online=$db->prepare(
        "SELECT COUNT(*) order_count,COALESCE(SUM(total_rial),0) revenue_rial
         FROM orders WHERE tenant_id=? AND status IN ('paid','accepted','picking','packed','ready_pickup','dispatched','out_for_delivery','delivered')
           AND created_at>=? AND created_at<?"
    );
    $online->execute([$tenantId,$fromUtc,$toUtc]);
    $onlineSummary=$online->fetch(PDO::FETCH_ASSOC)?:[];
    app_json(['data'=>[
        'period'=>['from'=>substr($fromKey,0,4).'-'.substr($fromKey,4,2).'-'.substr($fromKey,6,2),'to'=>substr($toKey,0,4).'-'.substr($toKey,4,2).'-'.substr($toKey,6,2),'timezone'=>(string)($tenant['timezone']??'Asia/Tehran')],
        'in_person'=>[
            'sale_count'=>(int)($sales['sale_count']??0),
            'sales_total_rial'=>$grossSales,
            'refund_count'=>(int)($refunds['refund_count']??0),
            'refund_total_rial'=>$refundTotal,
            'net_sales_total_rial'=>$netSales,
            'cash_received_rial'=>(int)($sales['cash_received_rial']??0),
            'change_rial'=>(int)($sales['change_rial']??0),
        ],
        'shifts'=>[
            'shift_count'=>(int)($shiftSummary['shift_count']??0),
            'open_shift_count'=>(int)($shiftSummary['open_shift_count']??0),
            'closed_shift_count'=>(int)($shiftSummary['closed_shift_count']??0),
            'variance_shift_count'=>(int)($shiftSummary['variance_shift_count']??0),
            'net_variance_rial'=>(int)($shiftSummary['net_variance_rial']??0),
        ],
        'online'=>['order_count'=>(int)($onlineSummary['order_count']??0),'revenue_rial'=>(int)($onlineSummary['revenue_rial']??0)],
        'cashiers'=>$cashiers->fetchAll(PDO::FETCH_ASSOC),
        'top_items'=>$topItems->fetchAll(PDO::FETCH_ASSOC),
    ]]);
}


/**
 * Record a cash POS refund without silently returning goods to sellable stock.
 * Returned items remain pending physical inspection; stock is restored only by
 * a separate, audited inventory receipt after inspection.
 */
function app_pos_create_refund(PDO $db,array $tenant,array $session,int $saleId): never {
    $body=app_pos_body();
    $key=trim((string)($body['idempotency_key']??''));
    if(!preg_match('/^[A-Za-z0-9._:-]{12,100}$/',$key))app_json(['error'=>'کلید یکتای بازپرداخت معتبر نیست.'],422);
    $reason=trim((string)($body['reason']??''));
    if(mb_strlen($reason)<12||mb_strlen($reason)>500)app_json(['error'=>'دلیل بازپرداخت باید بین ۱۲ تا ۵۰۰ نویسه باشد.'],422);
    $rawItems=$body['items']??null;
    if(!is_array($rawItems)||count($rawItems)<1||count($rawItems)>100)app_json(['error'=>'اقلام بازپرداخت معتبر نیستند.'],422);
    $requested=[];
    foreach($rawItems as $line){
        if(!is_array($line))app_json(['error'=>'قلم بازپرداخت معتبر نیست.'],422);
        $itemId=filter_var($line['sale_item_id']??null,FILTER_VALIDATE_INT);
        $qty=filter_var($line['quantity']??null,FILTER_VALIDATE_INT);
        if($itemId===false||$itemId<1||$qty===false||$qty<1||$qty>1000000)app_json(['error'=>'شناسه قلم یا تعداد بازپرداخت معتبر نیست.'],422);
        if(isset($requested[$itemId]))app_json(['error'=>'هر قلم فروش باید فقط یک بار در فهرست بازپرداخت بیاید.'],422);
        $requested[$itemId]=$qty;
    }
    ksort($requested,SORT_NUMERIC);
    $hash=hash('sha256',(string)json_encode(['sale_id'=>$saleId,'items'=>$requested,'reason'=>$reason],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $tenantId=(int)$tenant['id'];$actorId=(int)$session['id'];
    $db->beginTransaction();
    try{
        $shift=app_pos_active_shift($db,$tenantId,$actorId,true);
        if(!$shift){$db->rollBack();app_json(['error'=>'برای ثبت بازپرداخت، صندوق‌دار باید شیفت باز داشته باشد.'],409);}
        $saleQuery=$db->prepare('SELECT id,sale_number,shift_id,total_rial FROM pos_sales WHERE tenant_id=? AND id=? FOR UPDATE');
        $saleQuery->execute([$tenantId,$saleId]);$sale=$saleQuery->fetch(PDO::FETCH_ASSOC);
        if(!$sale){$db->rollBack();app_json(['error'=>'فروش حضوری پیدا نشد.'],404);}
        $prior=$db->prepare('SELECT id,request_hash,amount_rial FROM pos_refunds WHERE tenant_id=? AND cashier_user_id=? AND idempotency_key=? LIMIT 1');
        $prior->execute([$tenantId,$actorId,$key]);$existing=$prior->fetch(PDO::FETCH_ASSOC);
        if($existing){
            if(!hash_equals((string)$existing['request_hash'],$hash)){$db->rollBack();app_json(['error'=>'کلید بازپرداخت قبلاً با اطلاعات متفاوت استفاده شده است.'],409);}
            $db->commit();app_json(['data'=>['id'=>(int)$existing['id'],'amount_rial'=>(int)$existing['amount_rial'],'idempotent'=>true],'message'=>'این بازپرداخت قبلاً ثبت شده است.']);
        }
        $shiftSummary=app_pos_summary($db,$tenantId,(int)$shift['id']);
        $availableCash=(int)$shift['opening_float_rial']+(int)$shiftSummary['sales_total_rial']-(int)$shiftSummary['refund_total_rial'];
        $lineQuery=$db->prepare('SELECT id,product_id,product_title_snapshot,sku_snapshot,unit_price_rial,quantity FROM pos_sale_items WHERE tenant_id=? AND sale_id=? AND id=? FOR UPDATE');
        $already=$db->prepare('SELECT COALESCE(SUM(ri.quantity),0) FROM pos_refund_items ri JOIN pos_refunds r ON r.tenant_id=ri.tenant_id AND r.id=ri.refund_id WHERE ri.tenant_id=? AND ri.sale_item_id=?');
        $selected=[];$amount=0;
        foreach($requested as $itemId=>$qty){
            $lineQuery->execute([$tenantId,$saleId,$itemId]);$line=$lineQuery->fetch(PDO::FETCH_ASSOC);
            if(!$line){$db->rollBack();app_json(['error'=>'یکی از اقلام به این فروش تعلق ندارد.'],422);}
            $already->execute([$tenantId,$itemId]);$refunded=(int)$already->fetchColumn();
            $remaining=(int)$line['quantity']-$refunded;
            if($qty>$remaining){$db->rollBack();app_json(['error'=>'تعداد درخواستی از تعداد باقی‌مانده قابل بازپرداخت بیشتر است.'],409);}
            $unitPrice=(int)$line['unit_price_rial'];
            if($unitPrice<1||$qty>intdiv(1000000000000,$unitPrice)){$db->rollBack();app_json(['error'=>'مبلغ بازپرداخت خارج از محدوده مجاز است.'],422);}
            $lineAmount=$unitPrice*$qty;
            if($lineAmount<1||$amount>1000000000000-$lineAmount){$db->rollBack();app_json(['error'=>'مبلغ بازپرداخت خارج از محدوده مجاز است.'],422);}
            $amount+=$lineAmount;$selected[]=['line'=>$line,'quantity'=>$qty,'amount'=>$lineAmount];
        }
        if($amount<1||(int)$sale['total_rial']<$amount){$db->rollBack();app_json(['error'=>'مبلغ بازپرداخت معتبر نیست.'],422);}
        if($amount>$availableCash){$db->rollBack();app_json(['error'=>'وجه موردنیاز در مانده مورد انتظار صندوق کافی نیست.'],409);}
        $insert=$db->prepare("INSERT INTO pos_refunds (tenant_id,sale_id,shift_id,cashier_user_id,idempotency_key,request_hash,amount_rial,reason,stock_status) VALUES (?,?,?,?,?,?,?,?,'pending_inspection')");
        $insert->execute([$tenantId,$saleId,$shift['id'],$actorId,$key,$hash,$amount,$reason]);$refundId=(int)$db->lastInsertId();
        $itemInsert=$db->prepare("INSERT INTO pos_refund_items (tenant_id,refund_id,sale_item_id,product_id,product_title_snapshot,sku_snapshot,quantity,amount_rial,stock_status) VALUES (?,?,?,?,?,?,?,?,'pending_inspection')");
        foreach($selected as $entry){$line=$entry['line'];$itemInsert->execute([$tenantId,$refundId,$line['id'],$line['product_id'],$line['product_title_snapshot'],$line['sku_snapshot'],$entry['quantity'],$entry['amount']]);}
        app_audit_log($db,$tenantId,$actorId,'pos.sale.refunded','pos_refund',$refundId,[
            'sale_id'=>$saleId,'sale_number'=>$sale['sale_number'],'amount_rial'=>$amount,
            'reason'=>$reason,'item_count'=>count($selected),'stock_status'=>'pending_inspection'
        ]);
        $db->commit();
        app_json(['data'=>['id'=>$refundId,'sale_id'=>$saleId,'amount_rial'=>$amount,'currency'=>'IRR','stock_status'=>'pending_inspection'],
            'message'=>'بازپرداخت نقدی ثبت شد؛ اقلام مرجوعی تا زمان بازرسی به موجودی قابل‌فروش اضافه نمی‌شوند.'],201);
    }catch(PDOException $e){
        if($db->inTransaction())$db->rollBack();
        if($e->getCode()==='23000')app_json(['error'=>'درخواست بازپرداخت تکراری یا هم‌زمان است.'],409);
        throw $e;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
