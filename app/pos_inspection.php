<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/policies.php';
require_once __DIR__ . '/stock_batches.php';

function app_pos_inspection_list(PDO $db,array $tenant): never {
    $stmt=$db->prepare(
        "SELECT ri.id refund_item_id,ri.refund_id,ri.product_id,ri.product_title_snapshot title,
                ri.sku_snapshot sku,ri.quantity,ri.amount_rial,ri.stock_status,
                r.sale_id,r.reason refund_reason,r.created_at refund_created_at,
                s.sale_number,w.title warehouse_title
         FROM pos_refund_items ri
         JOIN pos_refunds r ON r.tenant_id=ri.tenant_id AND r.id=ri.refund_id
         JOIN pos_sales s ON s.tenant_id=r.tenant_id AND s.id=r.sale_id
         JOIN pos_shifts sh ON sh.tenant_id=r.tenant_id AND sh.id=r.shift_id
         JOIN warehouses w ON w.tenant_id=sh.tenant_id AND w.id=sh.warehouse_id
         WHERE ri.tenant_id=? AND ri.stock_status='pending_inspection'
         ORDER BY r.created_at,ri.id LIMIT 200"
    );
    $stmt->execute([(int)$tenant['id']]);
    app_json(['data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function app_pos_inspection_decide(PDO $db,array $tenant,array $session,int $refundItemId): never {
    $body=app_auth_request_body();
    $decision=(string)($body['decision']??'');
    $note=trim((string)($body['inspection_note']??''));
    $batch=trim((string)($body['batch_number']??''));
    $expiry=trim((string)($body['expiry_date']??''));
    if(!in_array($decision,['restock','reject'],true)||mb_strlen($note)<12||mb_strlen($note)>500){
        app_json(['error'=>'نتیجه بازرسی و شرح آن معتبر نیست.'],422);
    }
    if($decision==='restock'){
        if($batch===''||mb_strlen($batch)>80||$expiry===''){
            app_json(['error'=>'برای بازگشت به موجودی، شماره بچ و تاریخ انقضای معتبر الزامی است.'],422);
        }
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$expiry,new DateTimeZone('UTC'));
        $errors=DateTimeImmutable::getLastErrors();
        if(!$date||($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0))||
           $date->format('Y-m-d')!==$expiry||$expiry<gmdate('Y-m-d')){
            app_json(['error'=>'کالای منقضی یا تاریخ انقضای نامعتبر قابل بازگشت به موجودی نیست.'],422);
        }
    }elseif($batch!==''||$expiry!==''){
        app_json(['error'=>'برای رد کالا نباید بچ یا تاریخ انقضا ثبت شود.'],422);
    }

    $tenantId=(int)$tenant['id'];$actorId=(int)$session['id'];
    $db->beginTransaction();
    try{
        $lock=$db->prepare(
            "SELECT ri.id,ri.refund_id,ri.product_id,ri.quantity,ri.stock_status,
                    sh.warehouse_id,p.is_active,p.product_kind
             FROM pos_refund_items ri
             JOIN pos_refunds r ON r.tenant_id=ri.tenant_id AND r.id=ri.refund_id
             JOIN pos_shifts sh ON sh.tenant_id=r.tenant_id AND sh.id=r.shift_id
             JOIN products p ON p.tenant_id=ri.tenant_id AND p.id=ri.product_id
             WHERE ri.tenant_id=? AND ri.id=? FOR UPDATE"
        );
        $lock->execute([$tenantId,$refundItemId]);$item=$lock->fetch(PDO::FETCH_ASSOC);
        if(!$item){$db->rollBack();app_json(['error'=>'قلم مرجوعی پیدا نشد.'],404);}
        if($item['stock_status']!=='pending_inspection'){
            $db->rollBack();app_json(['error'=>'این قلم قبلاً بازرسی و تعیین تکلیف شده است.'],409);
        }
        if($decision==='restock'&&(!(bool)$item['is_active']||$item['product_kind']==='variant_parent')){
            $db->rollBack();app_json(['error'=>'کالای غیرفعال یا والدِ تنوع قابل بازگشت به موجودی فروش نیست.'],409);
        }
        $warehouseId=(int)$item['warehouse_id'];
        $insert=$db->prepare(
            'INSERT INTO pos_refund_inspections
             (tenant_id,refund_item_id,inspector_user_id,decision,inspection_note,warehouse_id,batch_number,expiry_date)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $insert->execute([$tenantId,$refundItemId,$actorId,$decision,$note,$warehouseId,$decision==='restock'?$batch:null,$decision==='restock'?$expiry:null]);
        $inspectionId=(int)$db->lastInsertId();

        if($decision==='restock'){
            $stock=$db->prepare(
                'INSERT INTO stock_levels (tenant_id,warehouse_id,product_id,on_hand,reserved)
                 VALUES (?,?,?, ?,0)
                 ON DUPLICATE KEY UPDATE on_hand=on_hand+VALUES(on_hand)'
            );
            $stock->execute([$tenantId,$warehouseId,(int)$item['product_id'],(int)$item['quantity']]);
            app_stock_batch_add($db,$tenantId,$warehouseId,(int)$item['product_id'],(int)$item['quantity'],
                $batch,$expiry,0,null);
            $movement=$db->prepare(
                "INSERT INTO stock_movements
                 (tenant_id,warehouse_id,product_id,actor_user_id,movement_type,quantity,reference_type,reference_id,reason)
                 VALUES (?,?,?,?,'return',?,'pos_refund_inspection',?,?)"
            );
            $movement->execute([$tenantId,$warehouseId,(int)$item['product_id'],$actorId,(int)$item['quantity'],$inspectionId,'بازگشت به موجودی پس از بازرسی: '.$note]);
        }
        $status=$decision==='restock'?'restocked':'rejected';
        $update=$db->prepare("UPDATE pos_refund_items SET stock_status=? WHERE tenant_id=? AND id=? AND stock_status='pending_inspection'");
        $update->execute([$status,$tenantId,$refundItemId]);
        if($update->rowCount()!==1)throw new RuntimeException('Refund inspection state changed concurrently.');
        app_audit_log($db,$tenantId,$actorId,'pos.refund.item.inspected','pos_refund_item',$refundItemId,[
            'refund_id'=>(int)$item['refund_id'],'inspection_id'=>$inspectionId,'decision'=>$decision,
            'quantity'=>(int)$item['quantity'],'warehouse_id'=>$warehouseId,
            'batch_number'=>$decision==='restock'?$batch:null,'expiry_date'=>$decision==='restock'?$expiry:null,
            'inspection_note'=>$note
        ]);
        $db->commit();
        app_json(['data'=>['inspection_id'=>$inspectionId,'refund_item_id'=>$refundItemId,'status'=>$status],
            'message'=>$decision==='restock'?'بازرسی ثبت شد و کالا با بچ و تاریخ انقضای جدید به موجودی اضافه شد.':'بازرسی ثبت شد؛ کالا رد شد و به موجودی قابل‌فروش اضافه نشد.']);
    }catch(PDOException $e){
        if($db->inTransaction())$db->rollBack();
        if($e->getCode()==='23000')app_json(['error'=>'این قلم قبلاً بازرسی شده یا اطلاعات تکراری است.'],409);
        throw $e;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
