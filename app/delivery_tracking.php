<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function app_delivery_event_create(
    PDO $db,
    int $tenantId,
    int $orderId,
    ?int $assignmentId,
    int $actorUserId,
    string $eventType,
    ?string $note = null
): void {
    if (!in_array($eventType,['assigned','out_for_delivery','delivered'],true)) {
        throw new InvalidArgumentException('Invalid delivery event type.');
    }
    $note = $note === null ? null : trim($note);
    if ($note !== null && mb_strlen($note) > 500) throw new InvalidArgumentException('Delivery note is too long.');
    $stmt = $db->prepare(
        'INSERT INTO order_delivery_events
         (tenant_id,order_id,assignment_id,actor_user_id,event_type,note)
         VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([$tenantId,$orderId,$assignmentId,$actorUserId,$eventType,$note]);
}

function app_delivery_event_list(PDO $db, int $tenantId, int $orderId): array {
    $stmt = $db->prepare(
        'SELECT event_type,note,created_at
         FROM order_delivery_events
         WHERE tenant_id=? AND order_id=?
         ORDER BY id ASC'
    );
    $stmt->execute([$tenantId,$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
