<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';
require_once __DIR__ . '/notifications.php';

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

function app_staff_task_complete(PDO $db, array $tenant, array $session, int $assignmentId): never {
    $db->beginTransaction();
    try{
        $assignment=$db->prepare(
            'SELECT order_id,assignment_type FROM order_assignments
             WHERE tenant_id=? AND assignee_user_id=? AND id=? AND status=\'active\' FOR UPDATE'
        );
        $assignment->execute([$tenant['id'],$session['id'],$assignmentId]);
        $assignmentRow=$assignment->fetch(PDO::FETCH_ASSOC);
        $orderId=$assignmentRow['order_id']??null;
        if(!$orderId){
            $db->rollBack();
            app_json(['error'=>'وظیفه فعال متعلق به حساب شما پیدا نشد.'],404);
        }
        $order=$db->prepare('SELECT status,order_number FROM orders WHERE tenant_id=? AND id=? FOR UPDATE');
        $order->execute([$tenant['id'],$orderId]);
        $orderRow=$order->fetch(PDO::FETCH_ASSOC);
        $orderStatus=$orderRow['status']??false;
        if($orderStatus===false||in_array($orderStatus,['cancelled','refunded','delivered'],true)){
            $db->rollBack();
            app_json(['error'=>'این سفارش دیگر در وضعیت قابل انجام نیست.'],409);
        }
        $update=$db->prepare(
            "UPDATE order_assignments SET status='completed',completed_at=UTC_TIMESTAMP()
             WHERE tenant_id=? AND id=? AND assignee_user_id=? AND status='active'"
        );
        $update->execute([$tenant['id'],$assignmentId,$session['id']]);
        if($update->rowCount()!==1){
            $db->rollBack();
            app_json(['error'=>'وظیفه قبلاً تکمیل یا لغو شده است.'],409);
        }
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'staff.task.completed','order',(int)$orderId,[
            'assignment_id'=>$assignmentId
        ]);
        $taskLabel=($assignmentRow['assignment_type']??'')==='picker'?'جمع‌آوری اقلام':'ارسال و تحویل';
        app_notification_notify_permission($db,(int)$tenant['id'],'orders.assign',
            'staff-task-completed-'.$assignmentId,'assignment','وظیفه سفارش تکمیل شد',
            $taskLabel.' سفارش '.$orderRow['order_number'].' توسط نیروی مسئول تکمیل شد.',
            'order',(int)$orderId,(int)$session['id']);
        $db->commit();
        app_json(['message'=>'انجام وظیفه شما ثبت شد.']);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
