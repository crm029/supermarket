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

function app_report_date_range(array $tenant): array {
    $timezone = new DateTimeZone((string)($tenant['timezone'] ?? 'Asia/Tehran'));
    $today = new DateTimeImmutable('today',$timezone);
    $fromRaw = $_GET['from'] ?? '';
    $toRaw = $_GET['to'] ?? '';
    $parse = static function(mixed $value, string $label, DateTimeZone $timezone): ?DateTimeImmutable {
        if ($value === null || $value === '') return null;
        if (!is_string($value)) app_json(['error'=>$label.' معتبر نیست.'],422);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d',$value,$timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count']>0 || $errors['error_count']>0)) || $date->format('Y-m-d') !== $value) {
            app_json(['error'=>$label.' معتبر نیست.'],422);
        }
        return $date;
    };
    $from = $parse($fromRaw,'تاریخ شروع',$timezone) ?? $today->modify('-29 days');
    $to = $parse($toRaw,'تاریخ پایان',$timezone) ?? $today;
    if ($from > $to) app_json(['error'=>'تاریخ شروع نباید بعد از تاریخ پایان باشد.'],422);
    if ((int)$from->diff($to)->days > 365) app_json(['error'=>'بازه گزارش نمی‌تواند بیش از ۳۶۶ روز باشد.'],422);
    $utc = new DateTimeZone('UTC');
    return [
        $from->setTimezone($utc)->format('Y-m-d H:i:s'),
        $to->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
        $from->format('Ymd'),
        $to->format('Ymd'),
    ];
}

function app_report_csv_value(mixed $value): mixed {
    if (!is_string($value)) return $value;
    if (preg_match('/^[\t\r ]*[=+\-@]/u',$value)) return "'".$value;
    return $value;
}

function app_report_csv_start(string $filename): mixed {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: no-store, private');
    echo "\xEF\xBB\xBF";
    return fopen('php://output','wb');
}

function app_report_csv_row(mixed $stream, array $row): void {
    fputcsv($stream,array_map('app_report_csv_value',$row),',','"','\\');
}

function app_admin_report_orders_csv(PDO $db, array $tenant): never {
    [$fromUtc,$toUtc,$fromLabel,$toLabel] = app_report_date_range($tenant);
    $statuses = ['pending_payment','paid','accepted','picking','packed','ready_pickup','dispatched','delivered','cancelled','refunded'];
    $status = $_GET['status'] ?? '';
    if ($status !== '' && (!is_string($status) || !in_array($status,$statuses,true))) {
        app_json(['error'=>'وضعیت سفارش معتبر نیست.'],422);
    }
    $where = ['o.tenant_id=?','o.created_at>=?','o.created_at<?'];
    $params = [(int)$tenant['id'],$fromUtc,$toUtc];
    if ($status !== '') { $where[]='o.status=?'; $params[]=$status; }
    $stmt = $db->prepare(
        "SELECT o.order_number,COALESCE(u.full_name,''),COALESCE(u.mobile,''),o.status,o.fulfillment,
                o.subtotal_rial,o.discount_rial,o.delivery_fee_rial,o.total_rial,o.created_at
         FROM orders o
         JOIN users u ON u.tenant_id=o.tenant_id AND u.id=o.user_id
         WHERE ".implode(' AND ',$where)."
         ORDER BY o.created_at DESC,o.id DESC LIMIT 20000"
    );
    $stmt->execute($params);
    $stream = app_report_csv_start('orders-'.$fromLabel.'-'.$toLabel.'.csv');
    app_report_csv_row($stream,['شماره سفارش','نام مشتری','موبایل','وضعیت','روش دریافت','جمع کالا (ریال)','تخفیف (ریال)','هزینه ارسال (ریال)','مبلغ نهایی (ریال)','زمان ثبت UTC']);
    while ($row=$stmt->fetch(PDO::FETCH_NUM)) app_report_csv_row($stream,$row);
    fclose($stream);
    exit;
}

function app_admin_report_stock_movements_csv(PDO $db, array $tenant): never {
    [$fromUtc,$toUtc,$fromLabel,$toLabel] = app_report_date_range($tenant);
    $where = ['sm.tenant_id=?','sm.created_at>=?','sm.created_at<?'];
    $params = [(int)$tenant['id'],$fromUtc,$toUtc];
    foreach (['warehouse_id'=>'sm.warehouse_id','product_id'=>'sm.product_id'] as $key=>$column) {
        $raw = $_GET[$key] ?? '';
        if ($raw !== '') {
            $id = filter_var($raw,FILTER_VALIDATE_INT);
            if ($id===false || $id<1) app_json(['error'=>'یکی از شناسه‌های فیلتر معتبر نیست.'],422);
            $where[]=$column.'=?';
            $params[]=$id;
        }
    }
    $types = ['receipt','transfer_in','transfer_out','adjustment','reservation','release','sale','return','supplier_return','expiry_writeoff'];
    $type = $_GET['movement_type'] ?? '';
    if ($type !== '') {
        if (!is_string($type) || !in_array($type,$types,true)) app_json(['error'=>'نوع گردش موجودی معتبر نیست.'],422);
        $where[]='sm.movement_type=?';
        $params[]=$type;
    }
    $stmt = $db->prepare(
        "SELECT sm.id,sm.created_at,
                CASE WHEN p.product_kind='variant' THEN CONCAT(parent.title,' · ',p.variant_label) ELSE p.title END,
                p.sku,w.title,sm.movement_type,sm.quantity,sm.reference_type,sm.reference_id,sm.reason,
                COALESCE(u.full_name,'سیستم')
         FROM stock_movements sm
         JOIN warehouses w ON w.tenant_id=sm.tenant_id AND w.id=sm.warehouse_id
         JOIN products p ON p.tenant_id=sm.tenant_id AND p.id=sm.product_id
         LEFT JOIN products parent ON parent.tenant_id=p.tenant_id AND parent.id=p.parent_product_id
         LEFT JOIN users u ON u.tenant_id=sm.tenant_id AND u.id=sm.actor_user_id
         WHERE ".implode(' AND ',$where)."
         ORDER BY sm.created_at DESC,sm.id DESC LIMIT 20000"
    );
    $stmt->execute($params);
    $stream = app_report_csv_start('stock-movements-'.$fromLabel.'-'.$toLabel.'.csv');
    app_report_csv_row($stream,['شناسه','زمان ثبت UTC','محصول','کد کالا','انبار','نوع گردش','تعداد با علامت','نوع مرجع','شناسه مرجع','شرح','ثبت‌کننده']);
    while ($row=$stmt->fetch(PDO::FETCH_NUM)) app_report_csv_row($stream,$row);
    fclose($stream);
    exit;
}

