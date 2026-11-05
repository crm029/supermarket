<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';
require_once __DIR__ . '/stock_batches.php';

function app_inventory_positive_int(mixed $value, string $label, int $max = 1000000): int {
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false || $number < 1 || $number > $max) app_json(['error' => $label . ' معتبر نیست.'], 422);
    return $number;
}

function app_inventory_warehouse_list(PDO $db, array $tenant): never {
    $stmt = $db->prepare('SELECT id,title,warehouse_type,address,is_active FROM warehouses WHERE tenant_id = ? ORDER BY id');
    $stmt->execute([$tenant['id']]);
    app_json(['data' => $stmt->fetchAll()]);
}

function app_inventory_warehouse_create(PDO $db, array $tenant, array $session): never {
    $body = app_auth_request_body();
    $title = trim((string)($body['title'] ?? ''));
    $type = (string)($body['warehouse_type'] ?? 'main');
    $address = trim((string)($body['address'] ?? ''));
    if ($title === '' || mb_strlen($title) > 120 || !in_array($type, ['main','store','cold','other'], true) || mb_strlen($address) > 2000) {
        app_json(['error' => 'اطلاعات انبار معتبر نیست.'], 422);
    }
    $stmt = $db->prepare('INSERT INTO warehouses (tenant_id,title,warehouse_type,address,is_active) VALUES (?,?,?,?,1)');
    $stmt->execute([$tenant['id'],$title,$type,$address === '' ? null : $address]);
    $id=(int)$db->lastInsertId();
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'inventory.warehouse.created','warehouse',$id);
    app_json(['data'=>['id'=>$id],'message'=>'انبار ثبت شد.'],201);
}

function app_inventory_stock_list(PDO $db, array $tenant): never {
    $query = trim((string)($_GET['q'] ?? ''));
    $warehouse = filter_input(INPUT_GET, 'warehouse_id', FILTER_VALIDATE_INT);
    $where = ['sl.tenant_id = :tenant'];
    $params = ['tenant'=>$tenant['id']];
    if ($warehouse !== null && $warehouse !== false) {
        $where[]='sl.warehouse_id = :warehouse';
        $params['warehouse']=$warehouse;
    }
    if ($query !== '') {
        $where[]='(p.title LIKE :title OR p.variant_label LIKE :variant_label OR p.sku LIKE :sku OR p.barcode LIKE :barcode)';
        $like='%'.mb_substr($query,0,80).'%';
        $params['title']=$like;$params['variant_label']=$like;$params['sku']=$like;$params['barcode']=$like;
    }
    $stmt=$db->prepare(
        "SELECT sl.warehouse_id,w.title AS warehouse_title,sl.product_id,p.sku,
                CASE WHEN p.product_kind='variant' THEN CONCAT(parent.title,' · ',p.variant_label) ELSE p.title END AS product_title,
                p.unit_label,sl.on_hand,sl.reserved,(sl.on_hand-sl.reserved) AS available_quantity,
                p.low_stock_threshold,
                CASE WHEN p.low_stock_threshold > 0 AND (sl.on_hand-sl.reserved) <= p.low_stock_threshold THEN 1 ELSE 0 END AS is_low_stock
         FROM stock_levels sl
         JOIN warehouses w ON w.tenant_id=sl.tenant_id AND w.id=sl.warehouse_id
         JOIN products p ON p.tenant_id=sl.tenant_id AND p.id=sl.product_id
         LEFT JOIN products parent ON parent.tenant_id=p.tenant_id AND parent.id=p.parent_product_id
         WHERE ".implode(' AND ',$where)."
         ORDER BY w.id,p.title LIMIT 500"
    );
    $stmt->execute($params);
    app_json(['data'=>$stmt->fetchAll()]);
}

function app_inventory_low_stock_list(PDO $db, array $tenant): never {
    $warehouse = filter_input(INPUT_GET, 'warehouse_id', FILTER_VALIDATE_INT);
    $sql = "SELECT w.id AS warehouse_id,w.title AS warehouse_title,p.id AS product_id,p.sku,
                   CASE WHEN p.product_kind='variant' THEN CONCAT(parent.title,' · ',p.variant_label) ELSE p.title END AS product_title,
                   p.unit_label,COALESCE(sl.on_hand,0) AS on_hand,COALESCE(sl.reserved,0) AS reserved,
                   CASE WHEN COALESCE(sl.on_hand,0)>COALESCE(sl.reserved,0)
                        THEN COALESCE(sl.on_hand,0)-COALESCE(sl.reserved,0) ELSE 0 END AS available_quantity,
                   p.low_stock_threshold,
                   p.low_stock_threshold-CASE WHEN COALESCE(sl.on_hand,0)>COALESCE(sl.reserved,0)
                        THEN COALESCE(sl.on_hand,0)-COALESCE(sl.reserved,0) ELSE 0 END AS shortage_quantity
            FROM warehouses w
            JOIN products p ON p.tenant_id=w.tenant_id AND p.is_active=1 AND p.product_kind<>'variant_parent'
            LEFT JOIN products parent ON parent.tenant_id=p.tenant_id AND parent.id=p.parent_product_id
            LEFT JOIN stock_levels sl ON sl.tenant_id=w.tenant_id AND sl.warehouse_id=w.id AND sl.product_id=p.id
            WHERE w.tenant_id=? AND w.is_active=1 AND p.low_stock_threshold>0
              AND (p.product_kind<>'variant' OR (parent.id IS NOT NULL AND parent.is_active=1))
              AND CASE WHEN COALESCE(sl.on_hand,0)>COALESCE(sl.reserved,0)
                        THEN COALESCE(sl.on_hand,0)-COALESCE(sl.reserved,0) ELSE 0 END<=p.low_stock_threshold";
    $params = [(int)$tenant['id']];
    if ($warehouse !== null && $warehouse !== false && $warehouse > 0) {
        $sql .= " AND w.id=?";
        $params[] = $warehouse;
    }
    $sql .= " ORDER BY shortage_quantity DESC,p.title,w.id LIMIT 500";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    app_json(['data'=>$stmt->fetchAll()]);
}

function app_inventory_validate_refs(PDO $db, int $tenantId, int $warehouseId, int $productId): void {
    $stmt=$db->prepare(
        "SELECT 1 FROM warehouses w JOIN products p ON p.tenant_id=w.tenant_id
         WHERE w.tenant_id=? AND w.id=? AND w.is_active=1 AND p.id=? AND p.is_active=1
           AND p.product_kind <> 'variant_parent'
           AND (p.product_kind <> 'variant' OR EXISTS (
             SELECT 1 FROM products parent WHERE parent.tenant_id=p.tenant_id AND parent.id=p.parent_product_id AND parent.is_active=1
           )) LIMIT 1"
    );
    $stmt->execute([$tenantId,$warehouseId,$productId]);
    if (!$stmt->fetchColumn()) app_json(['error'=>'انبار یا کالای انتخاب‌شده در این فروشگاه فعال نیست.'],422);
}

function app_inventory_receipt(PDO $db, array $tenant, array $session): never {
    $body=app_auth_request_body();
    $warehouseId=app_inventory_positive_int($body['warehouse_id']??null,'شناسه انبار');
    $productId=app_inventory_positive_int($body['product_id']??null,'شناسه کالا');
    $quantity=app_inventory_positive_int($body['quantity']??null,'تعداد');
    $reason=trim((string)($body['reason']??''));
    if(mb_strlen($reason)>255)app_json(['error'=>'شرح رسید بیش از حد طولانی است.'],422);
    $batch=app_stock_batch_metadata_input($body);
    $db->beginTransaction();
    try{
        app_inventory_validate_refs($db,(int)$tenant['id'],$warehouseId,$productId);
        $stmt=$db->prepare(
            'INSERT INTO stock_levels (tenant_id,warehouse_id,product_id,on_hand,reserved)
             VALUES (?,?,?, ?,0)
             ON DUPLICATE KEY UPDATE on_hand=on_hand+VALUES(on_hand)'
        );
        $stmt->execute([$tenant['id'],$warehouseId,$productId,$quantity]);
        app_stock_batch_add($db,(int)$tenant['id'],$warehouseId,$productId,$quantity,$batch['batch_number']!==''?$batch['batch_number']:'MANUAL-'.gmdate('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3))),$batch['expiry_date']);
        $movement=$db->prepare(
            "INSERT INTO stock_movements
             (tenant_id,warehouse_id,product_id,actor_user_id,movement_type,quantity,reference_type,reason)
             VALUES (?,?,?,?,'receipt',?,'manual',?)"
        );
        $movement->execute([$tenant['id'],$warehouseId,$productId,$session['id'],$quantity,$reason===''?null:$reason]);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'inventory.stock.received','stock_level',null,[
            'warehouse_id'=>$warehouseId,'product_id'=>$productId,'quantity'=>$quantity,
        ]);
        $db->commit();
        app_json(['message'=>'موجودی با موفقیت افزایش یافت.']);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_inventory_adjustment(PDO $db, array $tenant, array $session): never {
    $body=app_auth_request_body();
    $warehouseId=app_inventory_positive_int($body['warehouse_id']??null,'شناسه انبار');
    $productId=app_inventory_positive_int($body['product_id']??null,'شناسه کالا');
    $quantity=filter_var($body['quantity']??null,FILTER_VALIDATE_INT);
    $reason=trim((string)($body['reason']??''));
    if($quantity===false||$quantity===0||abs($quantity)>1000000||$reason===''||mb_strlen($reason)>255){
        app_json(['error'=>'مقدار اصلاح موجودی و دلیل آن معتبر نیست.'],422);
    }
    $batch=['batch_number'=>'','expiry_date'=>null];
    if($quantity>0){$batch=app_stock_batch_metadata_input($body);}
    elseif(trim((string)($body['batch_number']??''))!==''||trim((string)($body['expiry_date']??''))!==''){
        app_json(['error'=>'اطلاعات بچ فقط برای افزایش موجودی قابل ثبت است.'],422);
    }
    $db->beginTransaction();
    try{
        app_inventory_validate_refs($db,(int)$tenant['id'],$warehouseId,$productId);
        $lock=$db->prepare('SELECT on_hand,reserved FROM stock_levels WHERE tenant_id=? AND warehouse_id=? AND product_id=? FOR UPDATE');
        $lock->execute([$tenant['id'],$warehouseId,$productId]);
        $stock=$lock->fetch();
        if(!$stock){$db->rollBack();app_json(['error'=>'برای این کالا در انبار انتخاب‌شده موجودی ثبت نشده است.'],404);}
        if((int)$stock['on_hand']+$quantity<(int)$stock['reserved']){
            $db->rollBack();app_json(['error'=>'موجودی جدید نمی‌تواند از مقدار رزروشده کمتر باشد.'],409);
        }
        $update=$db->prepare('UPDATE stock_levels SET on_hand=on_hand+? WHERE tenant_id=? AND warehouse_id=? AND product_id=?');
        $update->execute([$quantity,$tenant['id'],$warehouseId,$productId]);
        if($quantity>0){
            app_stock_batch_add($db,(int)$tenant['id'],$warehouseId,$productId,$quantity,$batch['batch_number']!==''?$batch['batch_number']:'ADJ-'.gmdate('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3))),$batch['expiry_date']);
        }else{
            app_stock_batch_consume($db,(int)$tenant['id'],$warehouseId,$productId,abs($quantity));
        }
        $movement=$db->prepare(
            "INSERT INTO stock_movements
             (tenant_id,warehouse_id,product_id,actor_user_id,movement_type,quantity,reference_type,reason)
             VALUES (?,?,?,?,'adjustment',?,'manual',?)"
        );
        $movement->execute([$tenant['id'],$warehouseId,$productId,$session['id'],$quantity,$reason]);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'inventory.stock.adjusted','stock_level',null,[
            'warehouse_id'=>$warehouseId,'product_id'=>$productId,'quantity'=>$quantity,'reason'=>$reason,
        ]);
        $db->commit();
        app_json(['message'=>'اصلاح موجودی ثبت شد.']);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_inventory_transfer(PDO $db, array $tenant, array $session): never {
    $body=app_auth_request_body();
    $from=app_inventory_positive_int($body['from_warehouse_id']??null,'انبار مبدأ');
    $to=app_inventory_positive_int($body['to_warehouse_id']??null,'انبار مقصد');
    $product=app_inventory_positive_int($body['product_id']??null,'شناسه کالا');
    $qty=app_inventory_positive_int($body['quantity']??null,'تعداد');
    if($from===$to)app_json(['error'=>'انبار مبدأ و مقصد باید متفاوت باشند.'],422);
    $db->beginTransaction();
    try{
        app_inventory_validate_refs($db,(int)$tenant['id'],$from,$product);
        app_inventory_validate_refs($db,(int)$tenant['id'],$to,$product);
        $lock=$db->prepare('SELECT on_hand,reserved FROM stock_levels WHERE tenant_id=? AND warehouse_id=? AND product_id=? FOR UPDATE');
        $lock->execute([$tenant['id'],$from,$product]);
        $source=$lock->fetch();
        if(!$source||(int)$source['on_hand']-(int)$source['reserved']<$qty){
            $db->rollBack();app_json(['error'=>'موجودی آزاد انبار مبدأ کافی نیست.'],409);
        }
        $take=$db->prepare('UPDATE stock_levels SET on_hand=on_hand-? WHERE tenant_id=? AND warehouse_id=? AND product_id=? AND on_hand-reserved>=?');
        $take->execute([$qty,$tenant['id'],$from,$product,$qty]);
        if($take->rowCount()!==1)throw new RuntimeException('Source stock changed during transfer.');
        $add=$db->prepare(
            'INSERT INTO stock_levels (tenant_id,warehouse_id,product_id,on_hand,reserved) VALUES (?,?,?, ?,0)
             ON DUPLICATE KEY UPDATE on_hand=on_hand+VALUES(on_hand)'
        );
        $add->execute([$tenant['id'],$to,$product,$qty]);
        app_stock_batch_transfer($db,(int)$tenant['id'],$from,$to,$product,$qty);
        $movement=$db->prepare(
            "INSERT INTO stock_movements
             (tenant_id,warehouse_id,product_id,actor_user_id,movement_type,quantity,reference_type,reason)
             VALUES (?,?,?,?,'transfer_out',?,'manual','انتقال بین انبارها'),(?,?,?,?,'transfer_in',?,'manual','انتقال بین انبارها')"
        );
        $movement->execute([$tenant['id'],$from,$product,$session['id'],-$qty,$tenant['id'],$to,$product,$session['id'],$qty]);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'inventory.stock.transferred','stock_transfer',null,[
            'from_warehouse_id'=>$from,'to_warehouse_id'=>$to,'product_id'=>$product,'quantity'=>$qty,
        ]);
        $db->commit();
        app_json(['message'=>'انتقال موجودی با موفقیت ثبت شد.']);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
