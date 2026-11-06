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
    $q=$db->prepare('SELECT COUNT(*) sale_count,COALESCE(SUM(total_rial),0) sales_total_rial FROM pos_sales WHERE tenant_id=? AND shift_id=?');
    $q->execute([$tenantId,$shiftId]);return $q->fetch(PDO::FETCH_ASSOC)?:['sale_count'=>0,'sales_total_rial'=>0];
}

function app_pos_shift_current(PDO $db,array $tenant,array $session): never {
    $shift=app_pos_active_shift($db,(int)$tenant['id'],(int)$session['id']);
    if(!$shift)app_json(['data'=>['shift'=>null]]);
    $summary=app_pos_summary($db,(int)$tenant['id'],(int)$shift['id']);
    $shift['summary']=$summary;$shift['expected_cash_rial']=(int)$shift['opening_float_rial']+(int)$summary['sales_total_rial'];
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
        $expected=(int)$shift['opening_float_rial']+(int)$summary['sales_total_rial'];$variance=$counted-$expected;
        $update=$db->prepare("UPDATE pos_shifts SET status='closed',expected_cash_rial=?,counted_cash_rial=?,cash_variance_rial=?,closing_note=?,closed_at=UTC_TIMESTAMP() WHERE tenant_id=? AND id=? AND status='open'");
        $update->execute([$expected,$counted,$variance,$note===''?null:$note,$tenant['id'],$shiftId]);
        if($update->rowCount()!==1)throw new RuntimeException('POS shift close conflict.');
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'pos.shift.closed','pos_shift',$shiftId,['expected_cash_rial'=>$expected,'counted_cash_rial'=>$counted,'cash_variance_rial'=>$variance,'sale_count'=>(int)$summary['sale_count']]);
        $db->commit();app_json(['data'=>['id'=>$shiftId,'status'=>'closed','sale_count'=>(int)$summary['sale_count'],'expected_cash_rial'=>$expected,'counted_cash_rial'=>$counted,'cash_variance_rial'=>$variance],'message'=>'شیفت بسته شد و مغایرت صندوق ثبت شد.']);
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
    $shiftId=filter_input(INPUT_GET,'shift_id',FILTER_VALIDATE_INT);$sql='SELECT id,sale_number,shift_id,total_rial,cash_received_rial,change_rial,note,created_at FROM pos_sales WHERE tenant_id=?';$params=[(int)$tenant['id']];
    if($shiftId!==null&&$shiftId!==false){$sql.=' AND shift_id=?';$params[]=$shiftId;}else{$sql.=' AND cashier_user_id=?';$params[]=(int)$session['id'];}
    $sql.=' ORDER BY id DESC LIMIT 100';$stmt=$db->prepare($sql);$stmt->execute($params);app_json(['data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
