<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/delivery_tracking.php';

function app_admin_order_assignment_list(PDO $db, array $tenant, int $orderId): never {
    $order = $db->prepare('SELECT id,fulfillment,status FROM orders WHERE tenant_id=? AND id=? LIMIT 1');
    $order->execute([$tenant['id'],$orderId]);
    $orderRow = $order->fetch(PDO::FETCH_ASSOC);
    if (!$orderRow) app_json(['error'=>'سفارش پیدا نشد.'],404);

    $assignments = $db->prepare(
        "SELECT a.id,a.assignment_type,a.status,a.assigned_at,a.completed_at,
                u.id AS user_id,u.full_name,u.mobile,actor.full_name AS assigned_by_name
         FROM order_assignments a
         JOIN users u ON u.tenant_id=a.tenant_id AND u.id=a.assignee_user_id
         JOIN users actor ON actor.tenant_id=a.tenant_id AND actor.id=a.assigned_by_user_id
         WHERE a.tenant_id=? AND a.order_id=?
         ORDER BY a.id DESC"
    );
    $assignments->execute([$tenant['id'],$orderId]);
    $deliveryEvents = app_delivery_event_list($db,(int)$tenant['id'],$orderId);

    $staff = [];
    foreach (['picker'=>'orders.pick','courier'=>'orders.deliver'] as $type=>$permission) {
        $query = $db->prepare(
            "SELECT DISTINCT u.id,u.full_name,u.mobile
             FROM users u
             JOIN user_roles ur ON ur.tenant_id=u.tenant_id AND ur.user_id=u.id
             JOIN role_permissions rp ON rp.role_scope_id=ur.role_scope_id AND rp.role_id=ur.role_id
             JOIN permissions p ON p.id=rp.permission_id AND p.scope='tenant' AND p.permission_key=?
             WHERE u.tenant_id=? AND u.status='active'
             ORDER BY u.full_name,u.id"
        );
        $query->execute([$permission,$tenant['id']]);
        $staff[$type]=$query->fetchAll(PDO::FETCH_ASSOC);
    }
    app_json(['data'=>['order'=>$orderRow,'assignments'=>$assignments->fetchAll(PDO::FETCH_ASSOC),'staff'=>$staff,'delivery_events'=>$deliveryEvents]]);
}

function app_admin_order_assignment_create(PDO $db, array $tenant, array $session, int $orderId): never {
    $body=app_auth_request_body();
    $type=trim((string)($body['assignment_type']??''));
    $assigneeId=filter_var($body['assignee_user_id']??null,FILTER_VALIDATE_INT);
    if(!in_array($type,['picker','courier'],true)||$assigneeId===false||$assigneeId<1){
        app_json(['error'=>'نوع مسئولیت یا شناسه کارمند معتبر نیست.'],422);
    }
    $permission=$type==='picker'?'orders.pick':'orders.deliver';
    $db->beginTransaction();
    try{
        $orderQuery=$db->prepare('SELECT id,order_number,status,fulfillment FROM orders WHERE tenant_id=? AND id=? FOR UPDATE');
        $orderQuery->execute([$tenant['id'],$orderId]);
        $order=$orderQuery->fetch(PDO::FETCH_ASSOC);
        if(!$order){
            $db->rollBack();
            app_json(['error'=>'سفارش پیدا نشد.'],404);
        }
        if(in_array($order['status'],['pending_payment','cancelled','refunded','delivered'],true)){
            $db->rollBack();
            app_json(['error'=>'در وضعیت فعلی سفارش امکان تخصیص نیرو وجود ندارد.'],409);
        }
        if($type==='courier'&&$order['fulfillment']!=='delivery'){
            $db->rollBack();
            app_json(['error'=>'برای سفارش دریافت حضوری امکان تخصیص پیک وجود ندارد.'],409);
        }
        if($type==='picker'&&!in_array($order['status'],['paid','accepted','picking'],true)){
            $db->rollBack();
            app_json(['error'=>'مسئول جمع‌آوری فقط پیش از بسته‌بندی قابل تخصیص است.'],409);
        }
        if($type==='courier'&&!in_array($order['status'],['paid','accepted','picking','packed','dispatched'],true)){
            $db->rollBack();
            app_json(['error'=>'این سفارش در مرحله قابل تخصیص به پیک نیست.'],409);
        }
        $person=$db->prepare(
            "SELECT u.id FROM users u
             WHERE u.tenant_id=? AND u.id=? AND u.status='active'
               AND EXISTS(
                 SELECT 1 FROM user_roles ur
                 JOIN role_permissions rp ON rp.role_scope_id=ur.role_scope_id AND rp.role_id=ur.role_id
                 JOIN permissions p ON p.id=rp.permission_id
                 WHERE ur.tenant_id=u.tenant_id AND ur.user_id=u.id
                   AND p.scope='tenant' AND p.permission_key=?
               )
             LIMIT 1"
        );
        $person->execute([$tenant['id'],$assigneeId,$permission]);
        if(!$person->fetchColumn()){
            $db->rollBack();
            app_json(['error'=>'کارمند فعال با مجوز لازم در همین فروشگاه پیدا نشد.'],422);
        }
        $oldAssignments=$db->prepare(
            "SELECT id,assignee_user_id FROM order_assignments
             WHERE tenant_id=? AND order_id=? AND assignment_type=? AND status='active' FOR UPDATE"
        );
        $oldAssignments->execute([$tenant['id'],$orderId,$type]);
        $previous=$oldAssignments->fetchAll(PDO::FETCH_ASSOC);
        $replace=$db->prepare(
            "UPDATE order_assignments SET status='replaced'
             WHERE tenant_id=? AND order_id=? AND assignment_type=? AND status='active'"
        );
        $replace->execute([$tenant['id'],$orderId,$type]);
        foreach($previous as $old){
            if((int)$old['assignee_user_id']!==$assigneeId){
                app_notification_create($db,(int)$tenant['id'],(int)$old['assignee_user_id'],
                    'assignment-replaced-'.$old['id'],'assignment','وظیفه سفارش تغییر کرد',
                    'مسئولیت قبلی شما برای سفارش '.$order['order_number'].' به کارمند دیگری واگذار شد.',
                    'order',$orderId);
            }
        }
        $insert=$db->prepare(
            "INSERT INTO order_assignments
             (tenant_id,order_id,assignee_user_id,assignment_type,status,assigned_by_user_id)
             VALUES (?,?,?,?,'active',?)"
        );
        $insert->execute([$tenant['id'],$orderId,$assigneeId,$type,$session['id']]);
        $id=(int)$db->lastInsertId();
        if ($type === 'courier') {
            app_delivery_event_create($db,(int)$tenant['id'],$orderId,$id,(int)$session['id'],'assigned','پیک برای ارسال سفارش تعیین شد.');
        }
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'order.assignment.'.$type,'order',$orderId,[
            'assignment_id'=>$id,'assignee_user_id'=>$assigneeId
        ]);
        $assignmentLabel=$type==='picker'?'جمع‌آوری اقلام':'ارسال و تحویل';
        app_notification_create($db,(int)$tenant['id'],$assigneeId,'assignment-created-'.$id,'assignment',
            'وظیفه جدید به شما واگذار شد',
            $assignmentLabel.' سفارش '.$order['order_number'].' به شما واگذار شده است.','order',$orderId);
        $db->commit();
        app_json(['data'=>['id'=>$id,'order_id'=>$orderId,'assignee_user_id'=>$assigneeId,'assignment_type'=>$type],
            'message'=>'مسئول سفارش با موفقیت تخصیص یافت.'],201);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}

function app_admin_order_assignment_complete(PDO $db, array $tenant, array $session, int $orderId, int $assignmentId): never {
    $body=app_auth_request_body();
    $recipientValue=$body['recipient_name']??'';
    $noteValue=$body['note']??'';
    if(!is_string($recipientValue)||!is_string($noteValue)) app_json(['error'=>'اطلاعات تحویل معتبر نیست.'],422);
    $recipientName=trim($recipientValue);
    $note=trim($noteValue);
    if (mb_strlen($note)>300) app_json(['error'=>'یادداشت تحویل بیش از حد مجاز است.'],422);

    $db->beginTransaction();
    try {
        $order=$db->prepare('SELECT id,user_id,order_number,status,fulfillment FROM orders WHERE tenant_id=? AND id=? FOR UPDATE');
        $order->execute([$tenant['id'],$orderId]);
        $orderRow=$order->fetch(PDO::FETCH_ASSOC);
        if(!$orderRow){
            $db->rollBack();
            app_json(['error'=>'سفارش پیدا نشد.'],404);
        }
        $assignment=$db->prepare(
            "SELECT assignment_type FROM order_assignments
             WHERE tenant_id=? AND order_id=? AND id=? AND status='active' FOR UPDATE"
        );
        $assignment->execute([$tenant['id'],$orderId,$assignmentId]);
        $assignmentType=$assignment->fetchColumn();
        if(!$assignmentType){
            $db->rollBack();
            app_json(['error'=>'تخصیص فعال پیدا نشد.'],404);
        }
        if($assignmentType==='picker' && $orderRow['status']!=='picking'){
            $db->rollBack();
            app_json(['error'=>'وظیفه جمع‌آوری فقط در زمان آماده‌سازی قابل تکمیل است.'],409);
        }
        if($assignmentType==='courier' &&
            ($orderRow['fulfillment']!=='delivery' || $orderRow['status']!=='out_for_delivery')){
            $db->rollBack();
            app_json(['error'=>'تکمیل وظیفه پیک پس از شروع مسیر ارسال امکان‌پذیر است.'],409);
        }
        if($assignmentType==='courier' && (mb_strlen($recipientName)<2 || mb_strlen($recipientName)>160)){
            $db->rollBack();
            app_json(['error'=>'نام تحویل‌گیرنده را وارد کنید.'],422);
        }
        $stmt=$db->prepare(
            "UPDATE order_assignments SET status='completed',completed_at=UTC_TIMESTAMP()
             WHERE tenant_id=? AND order_id=? AND id=? AND status='active'"
        );
        $stmt->execute([$tenant['id'],$orderId,$assignmentId]);
        if($stmt->rowCount()!==1){
            $db->rollBack();
            app_json(['error'=>'تخصیص فعال پیدا نشد.'],404);
        }
        if($assignmentType==='courier'){
            $updateOrder=$db->prepare(
                "UPDATE orders SET status='delivered'
                 WHERE tenant_id=? AND id=? AND status='out_for_delivery'"
            );
            $updateOrder->execute([$tenant['id'],$orderId]);
            if($updateOrder->rowCount()!==1) throw new RuntimeException('Unable to finalize courier delivery.');
            $deliveryNote='تحویل به: '.$recipientName.($note!==''?' — '.$note:'');
            app_delivery_event_create($db,(int)$tenant['id'],$orderId,$assignmentId,(int)$session['id'],'delivered',$deliveryNote);
            app_notification_create($db,(int)$tenant['id'],(int)$orderRow['user_id'],
                'order-status-'.$orderId.'-delivered','order','سفارش شما تحویل شد',
                'سفارش '.$orderRow['order_number'].' به تحویل‌گیرنده ثبت‌شده تحویل داده شد.','order',$orderId);
        }
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'order.assignment.completed','order',$orderId,[
            'assignment_id'=>$assignmentId,'assignment_type'=>$assignmentType
        ]);
        $taskLabel=$assignmentType==='picker'?'جمع‌آوری اقلام':'ارسال و تحویل';
        app_notification_notify_permission($db,(int)$tenant['id'],'orders.assign',
            'staff-task-completed-'.$assignmentId,'assignment','وظیفه سفارش تکمیل شد',
            $taskLabel.' سفارش '.$orderRow['order_number'].' توسط مسئول ثبت شد.',
            'order',$orderId,(int)$session['id']);
        $db->commit();
        app_json(['message'=>$assignmentType==='courier'?'تحویل سفارش ثبت شد.':'وظیفه جمع‌آوری تکمیل شد.']);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
