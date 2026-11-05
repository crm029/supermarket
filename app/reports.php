<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function app_admin_report_overview(PDO $db, array $tenant): never {
    $tenantId=(int)$tenant['id'];
    $orders=$db->prepare(
        "SELECT COUNT(*) AS order_count,
                COALESCE(SUM(CASE WHEN status IN ('paid','accepted','picking','packed','ready_pickup','dispatched','delivered') THEN total_rial ELSE 0 END),0) AS paid_revenue_rial,
                SUM(CASE WHEN status='pending_payment' THEN 1 ELSE 0 END) AS pending_payment_count,
                SUM(CASE WHEN DATE(created_at)=UTC_DATE() AND status IN ('paid','accepted','picking','packed','ready_pickup','dispatched','delivered') THEN total_rial ELSE 0 END) AS today_revenue_rial
         FROM orders WHERE tenant_id=? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)"
    );
    $orders->execute([$tenantId]);
    $summary=$orders->fetch() ?: [];
    $stock=$db->prepare(
        'SELECT COUNT(*) FROM stock_levels WHERE tenant_id=? AND on_hand-reserved <= 5'
    );
    $stock->execute([$tenantId]);
    $lowStock=(int)$stock->fetchColumn();

    $daily=$db->prepare(
        "SELECT DATE(created_at) AS day,
                COUNT(*) AS order_count,
                COALESCE(SUM(CASE WHEN status IN ('paid','accepted','picking','packed','ready_pickup','dispatched','delivered') THEN total_rial ELSE 0 END),0) AS revenue_rial
         FROM orders WHERE tenant_id=? AND created_at >= DATE_SUB(UTC_DATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at) ORDER BY day"
    );
    $daily->execute([$tenantId]);
    app_json(['data'=>[
        'period_days'=>30,
        'order_count_30d'=>(int)($summary['order_count']??0),
        'paid_revenue_rial_30d'=>(int)($summary['paid_revenue_rial']??0),
        'today_revenue_rial'=>(int)($summary['today_revenue_rial']??0),
        'pending_payment_count'=>(int)($summary['pending_payment_count']??0),
        'low_stock_count'=>$lowStock,
        'daily_sales'=>$daily->fetchAll(),
    ]]);
}
