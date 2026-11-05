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
        'SELECT COUNT(*) FROM (
           SELECT p.id,p.low_stock_threshold,
                  COALESCE(SUM(CASE WHEN w.is_active=1 THEN sl.on_hand-sl.reserved ELSE 0 END),0) AS available_quantity
           FROM products p
           LEFT JOIN stock_levels sl ON sl.tenant_id=p.tenant_id AND sl.product_id=p.id
           LEFT JOIN warehouses w ON w.tenant_id=sl.tenant_id AND w.id=sl.warehouse_id
           WHERE p.tenant_id=? AND p.is_active=1 AND p.low_stock_threshold>0
           GROUP BY p.id,p.low_stock_threshold
           HAVING available_quantity <= p.low_stock_threshold
         ) low_stock_products'
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
