<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/delivery_tracking.php';

function app_staff_task_list(PDO $db, array $tenant, array $session): never {
    $stmt=$db->prepare(
        "SELECT a.id AS assignment_id,a.assignment_type,a.assigned_at,
                o.id AS order_id,o.order_number,o.status AS order_status,o.fulfillment,o.created_at,
                CASE WHEN a.assignment_type='courier' THEN ca.recipient ELSE NULL END AS recipient,
                CASE WHEN a.assignment_type='courier' THEN ca.mobile ELSE NULL END AS recipient_mobile,
                CASE WHEN a.assignment_type='courier' THEN ca.province ELSE NULL END AS province,
                CASE WHEN a.assignment_type='courier' THEN ca.city ELSE NULL END AS city,
                CASE WHEN a.assignment_type='courier' THEN ca.address ELSE NULL END AS delivery_address,
                CASE WHEN a.assignment_type='courier' THEN ca.postal_code ELSE NULL END AS postal_code,
                CASE WHEN a.assignment_type='courier' THEN u.full_name ELSE NULL END AS customer_name,
                CASE WHEN a.assignment_type='courier' THEN u.mobile ELSE NULL END AS customer_mobile,
                COUNT(oi.id) AS item_count
         FROM order_assignments a
         JOIN orders o ON o.tenant_id=a.tenant_id AND o.id=a.order_id
         JOIN users u ON u.tenant_id=o.tenant_id AND u.id=o.user_id
         LEFT JOIN customer_addresses ca ON ca.tenant_id=o.tenant_id AND ca.id=o.address_id
         LEFT JOIN order_items oi ON oi.tenant_id=o.tenant_id AND oi.order_id=o.id
         WHERE a.tenant_id=? AND a.assignee_user_id=? AND a.status='active'
           AND o.status NOT IN ('cancelled','refunded','delivered')
           AND EXISTS (
             SELECT 1 FROM user_roles ur
             JOIN role_permissions rp ON rp.role_scope_id=ur.role_scope_id AND rp.role_id=ur.role_id
             JOIN permissions p ON p.id=rp.permission_id AND p.scope='tenant'
             WHERE ur.tenant_id=a.tenant_id AND ur.user_id=a.assignee_user_id
               AND p.permission_key=CASE WHEN a.assignment_type='picker' THEN 'orders.pick' ELSE 'orders.deliver' END
           )
         GROUP BY a.id,a.assignment_type,a.assigned_at,o.id,o.order_number,o.status,o.fulfillment,o.created_at,
                  ca.recipient,ca.mobile,ca.province,ca.city,ca.address,ca.postal_code,u.full_name,u.mobile
         ORDER BY a.assigned_at DESC,a.id DESC LIMIT 100"
    );
    $stmt->execute([$tenant['id'],$session['id']]);
    $tasks=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $itemsQuery=$db->prepare(
        'SELECT product_title_snapshot AS title,quantity,unit_price_rial
         FROM order_items WHERE tenant_id=? AND order_id=? ORDER BY id'
    );
    foreach($tasks as &$task){
        $task['assignment_id']=(int)$task['assignment_id'];
        $task['order_id']=(int)$task['order_id'];
        $task['item_count']=(int)$task['item_count'];
        $task['items']=[];
        if($task['assignment_type']==='picker'){
            $itemsQuery->execute([$tenant['id'],$task['order_id']]);
            $task['items']=$itemsQuery->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    unset($task);
    app_json(['data'=>$tasks]);
}

function app_staff_task_start_delivery(PDO $db, array $tenant, array $session, int $assignmentId): never {
    $lookup=$db->prepare(
        "SELECT order_id FROM order_assignments
         WHERE tenant_id=? AND assignee_user_id=? AND id=? AND assignment_type='courier' AND status='active'
         LIMIT 1"
    );
    $lookup->execute([$tenant['id'],$session['id'],$assignmentId]);
    $orderId=$lookup->fetchColumn();
    if(!$orderId) app_json(['error'=>'وظیفه فعال ارسال متعلق به حساب شما پیدا نشد.'],404);
    app_auth_require_permission($db,$tenant,$session,'orders.deliver');

    $body=app_auth_request_body();
    $note=trim((string)($body['note']??''));
    if(mb_strlen($note)>500) {
        app_json(['error'=>'یادداشت ارسال معتبر نیست.'],422);
    }

    $db->beginTransaction();
    try{
        $order=$db->prepare('SELECT id,user_id,order_number,status,fulfillment FROM orders WHERE tenant_id=? AND id=? FOR UPDATE');
        $order->execute([$tenant['id'],$orderId]);
        $orderRow=$order->fetch(PDO::FETCH_ASSOC);
        if(!$orderRow){
            $db->rollBack();
            app_json(['error'=>'سفارش پیدا نشد.'],404);
        }
        $assignment=$db->prepare(
            "SELECT id FROM order_assignments
             WHERE tenant_id=? AND order_id=? AND assignee_user_id=? AND id=?
               AND assignment_type='courier' AND status='active' FOR UPDATE"
        );
        $assignment->execute([$tenant['id'],$orderId,$session['id'],$assignmentId]);
        if(!$assignment->fetchColumn()){
            $db->rollBack();
            app_json(['error'=>'وظیفه ارسال دیگر فعال یا متعلق به شما نیست.'],409);
        }
        if($orderRow['fulfillment']!=='delivery' || $orderRow['status']!=='dispatched'){
            $db->rollBack();
            app_json(['error'=>'شروع مسیر فقط پس از آماده‌سازی و ثبت ارسال سفارش ممکن است.'],409);
        }
        $update=$db->prepare(
            "UPDATE orders SET status='out_for_delivery'
             WHERE tenant_id=? AND id=? AND status='dispatched'"
        );
        $update->execute([$tenant['id'],$orderId]);
        if($update->rowCount()!==1) throw new RuntimeException('Unable to start delivery.');
        app_delivery_event_create(
            $db,(int)$tenant['id'],(int)$orderId,$assignmentId,(int)$session['id'],
            'out_for_delivery',$note!==''?$note:null
        );
        app_notification_create($db,(int)$tenant['id'],(int)$orderRow['user_id'],
            'order-status-'.$orderId.'-out_for_delivery','order','سفارش شما در مسیر ارسال است',
            'پیک سفارش '.$orderRow['order_number'].' حرکت به سمت نشانی شما را آغاز کرد.','order',(int)$orderId);
        app_notification_notify_permission($db,(int)$tenant['id'],'orders.assign',
            'courier-started-'.$assignmentId,'assignment','ارسال سفارش آغاز شد',
            'پیک سفارش '.$orderRow['order_number'].' شروع مسیر را ثبت کرد.','order',(int)$orderId,(int)$session['id']);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'staff.delivery.started','order',(int)$orderId,[
            'assignment_id'=>$assignmentId
        ]);
        $db->commit();
        app_json(['message'=>'شروع مسیر ارسال ثبت شد.','data'=>['order_id'=>(int)$orderId,'status'=>'out_for_delivery']]);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}

function app_staff_task_complete(PDO $db, array $tenant, array $session, int $assignmentId): never {
    $lookup=$db->prepare(
        "SELECT order_id,assignment_type FROM order_assignments
         WHERE tenant_id=? AND assignee_user_id=? AND id=? AND status='active' LIMIT 1"
    );
    $lookup->execute([$tenant['id'],$session['id'],$assignmentId]);
    $lookupRow=$lookup->fetch(PDO::FETCH_ASSOC);
    if(!$lookupRow) app_json(['error'=>'وظیفه فعال متعلق به حساب شما پیدا نشد.'],404);
    $assignmentType=(string)$lookupRow['assignment_type'];
    app_auth_require_permission($db,$tenant,$session,$assignmentType==='picker'?'orders.pick':'orders.deliver');

    $body=app_auth_request_body();
    $recipientName=trim((string)($body['recipient_name']??''));
    $note=trim((string)($body['note']??''));
    if(mb_strlen($note)>300) app_json(['error'=>'یادداشت تحویل بیش از حد مجاز است.'],422);

    $db->beginTransaction();
    try{
        $order=$db->prepare('SELECT id,user_id,order_number,status,fulfillment FROM orders WHERE tenant_id=? AND id=? FOR UPDATE');
        $order->execute([$tenant['id'],$lookupRow['order_id']]);
        $orderRow=$order->fetch(PDO::FETCH_ASSOC);
        if(!$orderRow){
            $db->rollBack();
            app_json(['error'=>'سفارش پیدا نشد.'],404);
        }
        $assignment=$db->prepare(
            'SELECT id FROM order_assignments
             WHERE tenant_id=? AND order_id=? AND assignee_user_id=? AND id=? AND status=\'active\' FOR UPDATE'
        );
        $assignment->execute([$tenant['id'],$lookupRow['order_id'],$session['id'],$assignmentId]);
        if(!$assignment->fetchColumn()){
            $db->rollBack();
            app_json(['error'=>'وظیفه فعال دیگر متعلق به حساب شما نیست.'],409);
        }
        if($assignmentType==='picker' && $orderRow['status']!=='picking'){
            $db->rollBack();
            app_json(['error'=>'وظیفه جمع‌آوری فقط در مرحله آماده‌سازی قابل تکمیل است.'],409);
        }
        if($assignmentType==='courier' &&
            ($orderRow['fulfillment']!=='delivery' || $orderRow['status']!=='out_for_delivery')){
            $db->rollBack();
            app_json(['error'=>'ثبت تحویل فقط پس از شروع مسیر ارسال ممکن است.'],409);
        }
        if($assignmentType==='courier' && (mb_strlen($recipientName)<2 || mb_strlen($recipientName)>160)){
            $db->rollBack();
            app_json(['error'=>'نام تحویل‌گیرنده را وارد کنید.'],422);
        }
        $update=$db->prepare(
            "UPDATE order_assignments SET status='completed',completed_at=UTC_TIMESTAMP()
             WHERE tenant_id=? AND order_id=? AND id=? AND status='active'"
        );
        $update->execute([$tenant['id'],$lookupRow['order_id'],$assignmentId]);
        if($update->rowCount()!==1){
            $db->rollBack();
            app_json(['error'=>'وظیفه قبلاً تکمیل یا لغو شده است.'],409);
        }
        if($assignmentType==='courier'){
            $updateOrder=$db->prepare(
                "UPDATE orders SET status='delivered'
                 WHERE tenant_id=? AND id=? AND status='out_for_delivery'"
            );
            $updateOrder->execute([$tenant['id'],$lookupRow['order_id']]);
            if($updateOrder->rowCount()!==1) throw new RuntimeException('Unable to finalize courier delivery.');
            $deliveryNote='تحویل به: '.$recipientName.($note!==''?' — '.$note:'');
            app_delivery_event_create($db,(int)$tenant['id'],(int)$lookupRow['order_id'],$assignmentId,
                (int)$session['id'],'delivered',$deliveryNote);
            app_notification_create($db,(int)$tenant['id'],(int)$orderRow['user_id'],
                'order-status-'.$lookupRow['order_id'].'-delivered','order','سفارش شما تحویل شد',
                'سفارش '.$orderRow['order_number'].' به تحویل‌گیرنده ثبت‌شده تحویل داده شد.','order',(int)$lookupRow['order_id']);
        }
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'staff.task.completed','order',(int)$lookupRow['order_id'],[
            'assignment_id'=>$assignmentId,'assignment_type'=>$assignmentType
        ]);
        $taskLabel=$assignmentType==='picker'?'جمع‌آوری اقلام':'ارسال و تحویل';
        app_notification_notify_permission($db,(int)$tenant['id'],'orders.assign',
            'staff-task-completed-'.$assignmentId,'assignment','وظیفه سفارش تکمیل شد',
            $taskLabel.' سفارش '.$orderRow['order_number'].' توسط نیروی مسئول تکمیل شد.',
            'order',(int)$lookupRow['order_id'],(int)$session['id']);
        $db->commit();
        app_json(['message'=>$assignmentType==='courier'?'تحویل سفارش ثبت شد.':'وظیفه جمع‌آوری تکمیل شد.']);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
