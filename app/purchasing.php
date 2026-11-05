<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';
require_once __DIR__ . '/inventory.php';

function app_supplier_payload(array $body,bool $partial=false): array {
    $data=[];
    if(!$partial||array_key_exists('name',$body)){
        $name=trim((string)($body['name']??''));
        if($name===''||mb_strlen($name)>180)app_json(['error'=>'نام تأمین‌کننده الزامی است.'],422);
        $data['name']=$name;
    }
    foreach(['contact_person'=>160,'phone'=>30,'email'=>190] as $field=>$max){
        if(!$partial||array_key_exists($field,$body)){
            $value=trim((string)($body[$field]??''));
            if(mb_strlen($value)>$max)app_json(['error'=>'اطلاعات تماس تأمین‌کننده معتبر نیست.'],422);
            if($field==='email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))app_json(['error'=>'ایمیل تأمین‌کننده معتبر نیست.'],422);
            if($field==='phone'&&$value!==''&&!preg_match('/^[0-9+() .-]{7,30}$/',$value))app_json(['error'=>'شماره تماس تأمین‌کننده معتبر نیست.'],422);
            $data[$field]=$value===''?null:$value;
        }
    }
    foreach(['address'=>2000,'notes'=>2000] as $field=>$max){
        if(!$partial||array_key_exists($field,$body)){
            $value=trim((string)($body[$field]??''));
            if(mb_strlen($value)>$max)app_json(['error'=>'متن اطلاعات تأمین‌کننده بیش از حد طولانی است.'],422);
            $data[$field]=$value===''?null:$value;
        }
    }
    if(!$partial||array_key_exists('is_active',$body)){
        $active=filter_var($body['is_active']??true,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
        if($active===null)app_json(['error'=>'وضعیت تأمین‌کننده معتبر نیست.'],422);
        $data['is_active']=$active?1:0;
    }
    return $data;
}

function app_admin_supplier_list(PDO $db,array $tenant): never {
    $stmt=$db->prepare('SELECT id,name,contact_person,phone,email,address,notes,is_active,created_at FROM suppliers WHERE tenant_id=? ORDER BY is_active DESC,name LIMIT 300');
    $stmt->execute([$tenant['id']]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row)$row['is_active']=(bool)$row['is_active'];
    unset($row);
    app_json(['data'=>$rows]);
}

function app_admin_supplier_create(PDO $db,array $tenant,array $session): never {
    $data=app_supplier_payload(app_auth_request_body());
    $stmt=$db->prepare('INSERT INTO suppliers (tenant_id,name,contact_person,phone,email,address,notes,is_active) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$tenant['id'],$data['name'],$data['contact_person'],$data['phone'],$data['email'],$data['address'],$data['notes'],$data['is_active']]);
    $id=(int)$db->lastInsertId();
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'purchasing.supplier.created','supplier',$id);
    app_json(['data'=>['id'=>$id],'message'=>'تأمین‌کننده ثبت شد.'],201);
}

function app_admin_supplier_update(PDO $db,array $tenant,array $session,int $id): never {
    $currentQuery=$db->prepare('SELECT * FROM suppliers WHERE tenant_id=? AND id=? LIMIT 1');
    $currentQuery->execute([$tenant['id'],$id]);
    $current=$currentQuery->fetch(PDO::FETCH_ASSOC);
    if(!$current)app_json(['error'=>'تأمین‌کننده پیدا نشد.'],404);
    $current['is_active']=(bool)$current['is_active'];
    $data=app_supplier_payload(array_merge($current,app_auth_request_body()),true);
    $sets=[];$params=['tenant'=>$tenant['id'],'id'=>$id];
    foreach(['name','contact_person','phone','email','address','notes','is_active'] as $field){
        if(array_key_exists($field,$data)){$sets[]=$field.'=:'.$field;$params[$field]=$data[$field];}
    }
    if($sets===[])app_json(['error'=>'هیچ فیلدی برای ویرایش ارسال نشده است.'],422);
    $stmt=$db->prepare('UPDATE suppliers SET '.implode(',',$sets).' WHERE tenant_id=:tenant AND id=:id');
    $stmt->execute($params);
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'purchasing.supplier.updated','supplier',$id,['fields'=>array_keys($data)]);
    app_json(['data'=>['id'=>$id],'message'=>'اطلاعات تأمین‌کننده به‌روزرسانی شد.']);
}

function app_admin_supplier_disable(PDO $db,array $tenant,array $session,int $id): never {
    $stmt=$db->prepare('UPDATE suppliers SET is_active=0 WHERE tenant_id=? AND id=? AND is_active=1');
    $stmt->execute([$tenant['id'],$id]);
    if($stmt->rowCount()!==1)app_json(['error'=>'تأمین‌کننده فعال پیدا نشد.'],404);
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'purchasing.supplier.disabled','supplier',$id);
    app_json(['message'=>'تأمین‌کننده غیرفعال شد.']);
}

function app_purchase_order_number(string $prefix): string {
    return $prefix.'-'.gmdate('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
}

function app_purchase_order_input(array $body): array {
    $supplierId=app_inventory_positive_int($body['supplier_id']??null,'شناسه تأمین‌کننده');
    $expected=trim((string)($body['expected_at']??''));
    $expectedAt=null;
    if($expected!==''){
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$expected,new DateTimeZone('UTC'));
        $errors=DateTimeImmutable::getLastErrors();
        if(!$date||($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0))||$date->format('Y-m-d')!==$expected)app_json(['error'=>'تاریخ مورد انتظار معتبر نیست.'],422);
        $expectedAt=$expected;
    }
    $notes=trim((string)($body['notes']??''));
    if(mb_strlen($notes)>4000)app_json(['error'=>'توضیحات سفارش خرید بیش از حد طولانی است.'],422);
    $rawItems=$body['items']??null;
    if(!is_array($rawItems)||count($rawItems)<1||count($rawItems)>100)app_json(['error'=>'سفارش خرید باید حداقل یک و حداکثر ۱۰۰ قلم کالا داشته باشد.'],422);
    $items=[];$seen=[];
    foreach($rawItems as $raw){
        if(!is_array($raw))app_json(['error'=>'قلم سفارش خرید معتبر نیست.'],422);
        $productId=app_inventory_positive_int($raw['product_id']??null,'شناسه کالا');
        $quantity=app_inventory_positive_int($raw['quantity']??null,'تعداد',1000000);
        $cost=filter_var($raw['unit_cost_rial']??0,FILTER_VALIDATE_INT);
        if($cost===false||$cost<0)app_json(['error'=>'بهای خرید هر واحد معتبر نیست.'],422);
        if(isset($seen[$productId]))app_json(['error'=>'هر کالا باید فقط یک بار در سفارش خرید درج شود.'],422);
        $seen[$productId]=true;
        $itemNotes=trim((string)($raw['notes']??''));
        if(mb_strlen($itemNotes)>255)app_json(['error'=>'توضیحات قلم سفارش بیش از حد طولانی است.'],422);
        if($cost>0&&$quantity>intdiv(PHP_INT_MAX,$cost))app_json(['error'=>'مبلغ قلم سفارش از محدوده مجاز بیشتر است.'],422);
        $items[]=['product_id'=>$productId,'quantity'=>$quantity,'unit_cost_rial'=>$cost,'notes'=>$itemNotes===''?null:$itemNotes];
    }
    return ['supplier_id'=>$supplierId,'expected_at'=>$expectedAt,'notes'=>$notes===''?null:$notes,'items'=>$items];
}

function app_admin_purchase_order_create(PDO $db,array $tenant,array $session): never {
    $data=app_purchase_order_input(app_auth_request_body());
    $db->beginTransaction();
    try{
        $supplier=$db->prepare('SELECT id FROM suppliers WHERE tenant_id=? AND id=? AND is_active=1 FOR UPDATE');
        $supplier->execute([$tenant['id'],$data['supplier_id']]);
        if(!$supplier->fetchColumn()){$db->rollBack();app_json(['error'=>'تأمین‌کننده فعال پیدا نشد.'],404);}
        $productIds=array_column($data['items'],'product_id');
        $placeholders=implode(',',array_fill(0,count($productIds),'?'));
        $products=$db->prepare(
            "SELECT p.id FROM products p
             LEFT JOIN products parent ON parent.tenant_id=p.tenant_id AND parent.id=p.parent_product_id
             WHERE p.tenant_id=? AND p.id IN ({$placeholders}) AND p.is_active=1
               AND p.product_kind<>'variant_parent'
               AND (p.product_kind<>'variant' OR parent.is_active=1)
             ORDER BY p.id FOR UPDATE"
        );
        $products->execute(array_merge([$tenant['id']],$productIds));
        if(count($products->fetchAll(PDO::FETCH_COLUMN))!==count($productIds)){$db->rollBack();app_json(['error'=>'یک یا چند کالا غیرفعال یا نامعتبر است.'],422);}
        $number=app_purchase_order_number('PO');
        $order=$db->prepare('INSERT INTO purchase_orders (tenant_id,supplier_id,order_number,status,expected_at,notes,created_by) VALUES (?,?,?,\'ordered\',?,?,?)');
        $order->execute([$tenant['id'],$data['supplier_id'],$number,$data['expected_at'],$data['notes'],$session['id']]);
        $orderId=(int)$db->lastInsertId();
        $itemInsert=$db->prepare('INSERT INTO purchase_order_items (tenant_id,purchase_order_id,product_id,ordered_quantity,unit_cost_rial,notes) VALUES (?,?,?,?,?,?)');
        foreach($data['items'] as $item)$itemInsert->execute([$tenant['id'],$orderId,$item['product_id'],$item['quantity'],$item['unit_cost_rial'],$item['notes']]);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'purchasing.order.created','purchase_order',$orderId,['order_number'=>$number,'supplier_id'=>$data['supplier_id'],'item_count'=>count($data['items'])]);
        $db->commit();
        app_json(['data'=>['id'=>$orderId,'order_number'=>$number],'message'=>'سفارش خرید ثبت شد.'],201);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_admin_purchase_order_list(PDO $db,array $tenant): never {
    $stmt=$db->prepare(
        "SELECT po.id,po.order_number,po.status,po.expected_at,po.created_at,s.name AS supplier_name,
                COUNT(poi.id) AS item_count,COALESCE(SUM(poi.ordered_quantity),0) AS ordered_quantity,
                COALESCE(SUM(poi.received_quantity),0) AS received_quantity,
                COALESCE(SUM(poi.ordered_quantity*poi.unit_cost_rial),0) AS estimated_cost_rial
         FROM purchase_orders po
         JOIN suppliers s ON s.tenant_id=po.tenant_id AND s.id=po.supplier_id
         LEFT JOIN purchase_order_items poi ON poi.tenant_id=po.tenant_id AND poi.purchase_order_id=po.id
         WHERE po.tenant_id=?
         GROUP BY po.id,po.order_number,po.status,po.expected_at,po.created_at,s.name
         ORDER BY po.created_at DESC LIMIT 200"
    );
    $stmt->execute([$tenant['id']]);
    app_json(['data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function app_admin_purchase_order_detail(PDO $db,array $tenant,int $orderId): never {
    $stmt=$db->prepare(
        "SELECT po.id,po.order_number,po.status,po.expected_at,po.notes,po.created_at,s.name AS supplier_name
         FROM purchase_orders po JOIN suppliers s ON s.tenant_id=po.tenant_id AND s.id=po.supplier_id
         WHERE po.tenant_id=? AND po.id=? LIMIT 1"
    );
    $stmt->execute([$tenant['id'],$orderId]);
    $order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order)app_json(['error'=>'سفارش خرید پیدا نشد.'],404);
    $items=$db->prepare(
        "SELECT poi.id,poi.product_id,poi.ordered_quantity,poi.received_quantity,
                poi.ordered_quantity-poi.received_quantity AS remaining_quantity,poi.unit_cost_rial,poi.notes,
                p.sku,CASE WHEN p.product_kind='variant' THEN CONCAT(parent.title,' · ',p.variant_label) ELSE p.title END AS product_title
         FROM purchase_order_items poi
         JOIN products p ON p.tenant_id=poi.tenant_id AND p.id=poi.product_id
         LEFT JOIN products parent ON parent.tenant_id=p.tenant_id AND parent.id=p.parent_product_id
         WHERE poi.tenant_id=? AND poi.purchase_order_id=? ORDER BY poi.id"
    );
    $items->execute([$tenant['id'],$orderId]);
    $order['items']=$items->fetchAll(PDO::FETCH_ASSOC);
    $receipts=$db->prepare('SELECT id,receipt_number,warehouse_id,notes,created_at FROM purchase_order_receipts WHERE tenant_id=? AND purchase_order_id=? ORDER BY id DESC');
    $receipts->execute([$tenant['id'],$orderId]);
    $order['receipts']=$receipts->fetchAll(PDO::FETCH_ASSOC);
    app_json(['data'=>$order]);
}

function app_admin_purchase_order_receive(PDO $db,array $tenant,array $session,int $orderId): never {
    $body=app_auth_request_body();
    $warehouseId=app_inventory_positive_int($body['warehouse_id']??null,'شناسه انبار');
    $notes=trim((string)($body['notes']??''));
    if(mb_strlen($notes)>500)app_json(['error'=>'توضیحات رسید بیش از حد طولانی است.'],422);
    $rawItems=$body['items']??null;
    if(!is_array($rawItems)||count($rawItems)<1||count($rawItems)>100)app_json(['error'=>'حداقل یک قلم برای دریافت انتخاب کنید.'],422);
    $requested=[];$seen=[];
    foreach($rawItems as $raw){
        if(!is_array($raw))app_json(['error'=>'قلم دریافتی معتبر نیست.'],422);
        $itemId=app_inventory_positive_int($raw['item_id']??null,'شناسه قلم سفارش');
        $quantity=app_inventory_positive_int($raw['quantity']??null,'تعداد دریافتی',1000000);
        if(isset($seen[$itemId]))app_json(['error'=>'هر قلم سفارش فقط یک بار در رسید وارد شود.'],422);
        $seen[$itemId]=true;$requested[]=['item_id'=>$itemId,'quantity'=>$quantity];
    }
    $db->beginTransaction();
    try{
        $po=$db->prepare('SELECT id,order_number,status FROM purchase_orders WHERE tenant_id=? AND id=? FOR UPDATE');
        $po->execute([$tenant['id'],$orderId]);
        $order=$po->fetch(PDO::FETCH_ASSOC);
        if(!$order){$db->rollBack();app_json(['error'=>'سفارش خرید پیدا نشد.'],404);}
        if(!in_array($order['status'],['ordered','partially_received'],true)){$db->rollBack();app_json(['error'=>'این سفارش در وضعیت قابل دریافت نیست.'],409);}
        $warehouse=$db->prepare('SELECT id FROM warehouses WHERE tenant_id=? AND id=? AND is_active=1 FOR UPDATE');
        $warehouse->execute([$tenant['id'],$warehouseId]);
        if(!$warehouse->fetchColumn()){$db->rollBack();app_json(['error'=>'انبار فعال پیدا نشد.'],404);}
        $receiptNumber=app_purchase_order_number('RCV');
        $receipt=$db->prepare('INSERT INTO purchase_order_receipts (tenant_id,purchase_order_id,warehouse_id,receipt_number,received_by,notes) VALUES (?,?,?,?,?,?)');
        $receipt->execute([$tenant['id'],$orderId,$warehouseId,$receiptNumber,$session['id'],$notes===''?null:$notes]);
        $receiptId=(int)$db->lastInsertId();
        $itemQuery=$db->prepare('SELECT id,product_id,ordered_quantity,received_quantity,unit_cost_rial FROM purchase_order_items WHERE tenant_id=? AND purchase_order_id=? AND id=? FOR UPDATE');
        $itemUpdate=$db->prepare('UPDATE purchase_order_items SET received_quantity=received_quantity+? WHERE tenant_id=? AND id=?');
        $stock=$db->prepare('INSERT INTO stock_levels (tenant_id,warehouse_id,product_id,on_hand,reserved) VALUES (?,?,?, ?,0) ON DUPLICATE KEY UPDATE on_hand=on_hand+VALUES(on_hand)');
        $movement=$db->prepare("INSERT INTO stock_movements (tenant_id,warehouse_id,product_id,actor_user_id,movement_type,quantity,reference_type,reference_id,reason) VALUES (?,?,?,?,'receipt',?,'purchase_order',?,?)");
        $receiptItem=$db->prepare('INSERT INTO purchase_order_receipt_items (tenant_id,receipt_id,purchase_order_item_id,product_id,quantity,unit_cost_rial) VALUES (?,?,?,?,?,?)');
        foreach($requested as $request){
            $itemQuery->execute([$tenant['id'],$orderId,$request['item_id']]);
            $item=$itemQuery->fetch(PDO::FETCH_ASSOC);
            if(!$item){$db->rollBack();app_json(['error'=>'قلمی از این سفارش خرید پیدا نشد.'],404);}
            $remaining=(int)$item['ordered_quantity']-(int)$item['received_quantity'];
            if($request['quantity']>$remaining){$db->rollBack();app_json(['error'=>'تعداد دریافتی از مانده سفارش بیشتر است.'],409);}
            app_inventory_validate_refs($db,(int)$tenant['id'],$warehouseId,(int)$item['product_id']);
            $itemUpdate->execute([$request['quantity'],$tenant['id'],$item['id']]);
            $stock->execute([$tenant['id'],$warehouseId,$item['product_id'],$request['quantity']]);
            $movement->execute([$tenant['id'],$warehouseId,$item['product_id'],$session['id'],$request['quantity'],$orderId,$notes===''?'دریافت سفارش '.$order['order_number']:$notes]);
            $receiptItem->execute([$tenant['id'],$receiptId,$item['id'],$item['product_id'],$request['quantity'],$item['unit_cost_rial']]);
        }
        $remainingQuery=$db->prepare('SELECT COUNT(*) FROM purchase_order_items WHERE tenant_id=? AND purchase_order_id=? AND received_quantity<ordered_quantity');
        $remainingQuery->execute([$tenant['id'],$orderId]);
        $newStatus=(int)$remainingQuery->fetchColumn()===0?'received':'partially_received';
        $updateOrder=$db->prepare('UPDATE purchase_orders SET status=? WHERE tenant_id=? AND id=?');
        $updateOrder->execute([$newStatus,$tenant['id'],$orderId]);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'purchasing.order.received','purchase_order',$orderId,['receipt_id'=>$receiptId,'receipt_number'=>$receiptNumber,'status'=>$newStatus]);
        $db->commit();
        app_json(['data'=>['receipt_id'=>$receiptId,'receipt_number'=>$receiptNumber,'status'=>$newStatus],'message'=>'دریافت کالا و افزایش موجودی ثبت شد.'],201);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_admin_purchase_order_cancel(PDO $db,array $tenant,array $session,int $orderId): never {
    $db->beginTransaction();
    try{
        $stmt=$db->prepare('SELECT status FROM purchase_orders WHERE tenant_id=? AND id=? FOR UPDATE');
        $stmt->execute([$tenant['id'],$orderId]);
        $status=$stmt->fetchColumn();
        if($status===false){$db->rollBack();app_json(['error'=>'سفارش خرید پیدا نشد.'],404);}
        if(!in_array($status,['ordered','partially_received'],true)){$db->rollBack();app_json(['error'=>'این سفارش قابل لغو نیست.'],409);}
        $update=$db->prepare("UPDATE purchase_orders SET status='cancelled' WHERE tenant_id=? AND id=?");
        $update->execute([$tenant['id'],$orderId]);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'purchasing.order.cancelled','purchase_order',$orderId);
        $db->commit();
        app_json(['message'=>'سفارش خرید لغو شد؛ موجودی دریافتی قبلی در انبار باقی می‌ماند.']);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
