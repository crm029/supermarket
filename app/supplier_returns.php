<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';
require_once __DIR__ . '/inventory.php';

function app_admin_supplier_return_list(PDO $db,array $tenant): never {
    $stmt=$db->prepare(
        "SELECT sr.id,sr.return_number,sr.reason,sr.notes,sr.created_at,s.name AS supplier_name,
                w.title AS warehouse_title,COUNT(sri.id) AS item_count,
                COALESCE(SUM(sri.quantity),0) AS returned_quantity,
                COALESCE(SUM(sri.quantity*sri.unit_cost_rial),0) AS return_value_rial
         FROM supplier_returns sr
         JOIN suppliers s ON s.tenant_id=sr.tenant_id AND s.id=sr.supplier_id
         JOIN warehouses w ON w.tenant_id=sr.tenant_id AND w.id=sr.warehouse_id
         LEFT JOIN supplier_return_items sri ON sri.tenant_id=sr.tenant_id AND sri.supplier_return_id=sr.id
         WHERE sr.tenant_id=?
         GROUP BY sr.id,sr.return_number,sr.reason,sr.notes,sr.created_at,s.name,w.title
         ORDER BY sr.created_at DESC LIMIT 200"
    );
    $stmt->execute([$tenant['id']]);
    app_json(['data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function app_admin_supplier_return_eligible_lines(PDO $db,array $tenant): never {
    $supplierId=app_inventory_positive_int($_GET['supplier_id']??null,'شناسه تأمین‌کننده');
    $warehouseId=app_inventory_positive_int($_GET['warehouse_id']??null,'شناسه انبار');
    $stmt=$db->prepare(
        "SELECT pri.id AS receipt_item_id,pri.product_id,pri.quantity AS received_quantity,
                pri.unit_cost_rial,po.order_number,por.receipt_number,p.sku,
                CASE WHEN p.product_kind='variant' THEN CONCAT(parent.title,' · ',p.variant_label) ELSE p.title END AS product_title,
                pri.quantity-COALESCE((
                    SELECT SUM(sri.quantity) FROM supplier_return_items sri
                    WHERE sri.tenant_id=pri.tenant_id AND sri.receipt_item_id=pri.id
                ),0) AS remaining_quantity
         FROM purchase_order_receipt_items pri
         JOIN purchase_order_receipts por ON por.tenant_id=pri.tenant_id AND por.id=pri.receipt_id
         JOIN purchase_orders po ON po.tenant_id=por.tenant_id AND po.id=por.purchase_order_id
         JOIN products p ON p.tenant_id=pri.tenant_id AND p.id=pri.product_id
         LEFT JOIN products parent ON parent.tenant_id=p.tenant_id AND parent.id=p.parent_product_id
         WHERE pri.tenant_id=? AND po.supplier_id=? AND por.warehouse_id=?
         HAVING remaining_quantity>0
         ORDER BY por.created_at DESC,pri.id DESC LIMIT 300"
    );
    $stmt->execute([$tenant['id'],$supplierId,$warehouseId]);
    app_json(['data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function app_admin_supplier_return_create(PDO $db,array $tenant,array $session): never {
    $body=app_auth_request_body();
    $warehouseId=app_inventory_positive_int($body['warehouse_id']??null,'شناسه انبار');
    $supplierId=app_inventory_positive_int($body['supplier_id']??null,'شناسه تأمین‌کننده');
    $reason=trim((string)($body['reason']??''));
    $notes=trim((string)($body['notes']??''));
    if($reason===''||mb_strlen($reason)>255)app_json(['error'=>'علت مرجوعی الزامی و حداکثر ۲۵۵ نویسه است.'],422);
    if(mb_strlen($notes)>1000)app_json(['error'=>'توضیحات مرجوعی بیش از حد طولانی است.'],422);
    $rawItems=$body['items']??null;
    if(!is_array($rawItems)||count($rawItems)<1||count($rawItems)>100)app_json(['error'=>'مرجوعی باید حداقل یک و حداکثر ۱۰۰ قلم داشته باشد.'],422);
    $items=[];$seen=[];
    foreach($rawItems as $raw){
        if(!is_array($raw))app_json(['error'=>'قلم مرجوعی معتبر نیست.'],422);
        $receiptItemId=app_inventory_positive_int($raw['receipt_item_id']??null,'شناسه قلم رسید');
        $quantity=app_inventory_positive_int($raw['quantity']??null,'تعداد مرجوعی',1000000);
        if(isset($seen[$receiptItemId]))app_json(['error'=>'هر قلم رسید فقط یک بار در مرجوعی درج شود.'],422);
        $seen[$receiptItemId]=true;
        $items[]=['receipt_item_id'=>$receiptItemId,'quantity'=>$quantity];
    }

    $db->beginTransaction();
    try{
        $supplier=$db->prepare('SELECT id FROM suppliers WHERE tenant_id=? AND id=? FOR UPDATE');
        $supplier->execute([$tenant['id'],$supplierId]);
        if(!$supplier->fetchColumn()){$db->rollBack();app_json(['error'=>'تأمین‌کننده پیدا نشد.'],404);}
        $warehouse=$db->prepare('SELECT id FROM warehouses WHERE tenant_id=? AND id=? AND is_active=1 FOR UPDATE');
        $warehouse->execute([$tenant['id'],$warehouseId]);
        if(!$warehouse->fetchColumn()){$db->rollBack();app_json(['error'=>'انبار فعال پیدا نشد.'],404);}

        $number=app_purchase_order_number('SRET');
        $header=$db->prepare('INSERT INTO supplier_returns (tenant_id,supplier_id,warehouse_id,return_number,reason,notes,created_by) VALUES (?,?,?,?,?,?,?)');
        $header->execute([$tenant['id'],$supplierId,$warehouseId,$number,$reason,$notes===''?null:$notes,$session['id']]);
        $returnId=(int)$db->lastInsertId();

        $receipt=$db->prepare(
            "SELECT pri.id,pri.product_id,pri.quantity,pri.unit_cost_rial,por.warehouse_id,po.supplier_id
             FROM purchase_order_receipt_items pri
             JOIN purchase_order_receipts por ON por.tenant_id=pri.tenant_id AND por.id=pri.receipt_id
             JOIN purchase_orders po ON po.tenant_id=por.tenant_id AND po.id=por.purchase_order_id
             WHERE pri.tenant_id=? AND pri.id=? FOR UPDATE"
        );
        $prior=$db->prepare('SELECT COALESCE(SUM(quantity),0) FROM supplier_return_items WHERE tenant_id=? AND receipt_item_id=?');
        $stock=$db->prepare('SELECT on_hand,reserved FROM stock_levels WHERE tenant_id=? AND warehouse_id=? AND product_id=? FOR UPDATE');
        $stockUpdate=$db->prepare('UPDATE stock_levels SET on_hand=on_hand-? WHERE tenant_id=? AND warehouse_id=? AND product_id=? AND on_hand-reserved>=?');
        $returnItem=$db->prepare('INSERT INTO supplier_return_items (tenant_id,supplier_return_id,receipt_item_id,product_id,quantity,unit_cost_rial) VALUES (?,?,?,?,?,?)');
        $movement=$db->prepare("INSERT INTO stock_movements (tenant_id,warehouse_id,product_id,actor_user_id,movement_type,quantity,reference_type,reference_id,reason) VALUES (?,?,?,?,'supplier_return',?,'supplier_return',?,?)");

        foreach($items as $requested){
            $receipt->execute([$tenant['id'],$requested['receipt_item_id']]);
            $line=$receipt->fetch(PDO::FETCH_ASSOC);
            if(!$line|| (int)$line['supplier_id']!==$supplierId || (int)$line['warehouse_id']!==$warehouseId){
                $db->rollBack();app_json(['error'=>'قلم رسید متعلق به این تأمین‌کننده یا انبار نیست.'],422);
            }
            $prior->execute([$tenant['id'],$line['id']]);
            $remaining=(int)$line['quantity']-(int)$prior->fetchColumn();
            if($requested['quantity']>$remaining){
                $db->rollBack();app_json(['error'=>'تعداد مرجوعی از مقدار دریافت‌شده و مرجوع‌نشده بیشتر است.'],409);
            }
            $stock->execute([$tenant['id'],$warehouseId,$line['product_id']]);
            $balance=$stock->fetch(PDO::FETCH_ASSOC);
            if(!$balance||(int)$balance['on_hand']-(int)$balance['reserved']<$requested['quantity']){
                $db->rollBack();app_json(['error'=>'موجودی آزاد انبار برای مرجوعی کافی نیست.'],409);
            }
            $stockUpdate->execute([$requested['quantity'],$tenant['id'],$warehouseId,$line['product_id'],$requested['quantity']]);
            if($stockUpdate->rowCount()!==1)throw new RuntimeException('Stock changed during supplier return.');
            $returnItem->execute([$tenant['id'],$returnId,$line['id'],$line['product_id'],$requested['quantity'],$line['unit_cost_rial']]);
            $movement->execute([$tenant['id'],$warehouseId,$line['product_id'],$session['id'],-$requested['quantity'],$returnId,$reason]);
        }
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'purchasing.supplier_return.created','supplier_return',$returnId,[
            'return_number'=>$number,'supplier_id'=>$supplierId,'warehouse_id'=>$warehouseId,'item_count'=>count($items)
        ]);
        $db->commit();
        app_json(['data'=>['id'=>$returnId,'return_number'=>$number],'message'=>'مرجوعی ثبت شد و موجودی آزاد انبار کاهش یافت.'],201);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
