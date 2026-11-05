<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';

function app_store_settings_read(PDO $db, array $tenant): array {
    $stmt = $db->prepare('SELECT setting_key, setting_value FROM store_settings WHERE tenant_id = ?');
    $stmt->execute([$tenant['id']]);
    $stored = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $decoded = json_decode((string)$row['setting_value'], true);
        if (is_array($decoded)) $stored[$row['setting_key']] = $decoded;
    }
    $profile = $stored['store.profile'] ?? [];
    $checkout = $stored['store.checkout'] ?? [];
    return [
        'profile' => [
            'name' => trim((string)($profile['name'] ?? $tenant['name'])) ?: $tenant['name'],
            'tagline' => (string)($profile['tagline'] ?? ''),
            'support_phone' => (string)($profile['support_phone'] ?? ''),
            'street_address' => (string)($profile['street_address'] ?? ''),
            'city' => (string)($profile['city'] ?? ''),
            'region' => (string)($profile['region'] ?? ''),
            'postal_code' => (string)($profile['postal_code'] ?? ''),
            'country_code' => strtoupper((string)($profile['country_code'] ?? 'IR')),
            'latitude' => isset($profile['latitude']) && is_numeric($profile['latitude']) ? (float)$profile['latitude'] : null,
            'longitude' => isset($profile['longitude']) && is_numeric($profile['longitude']) ? (float)$profile['longitude'] : null,
            'opening_hours' => (string)($profile['opening_hours'] ?? ''),
            'primary_color' => (string)($profile['primary_color'] ?? '#173D2D'),
            'accent_color' => (string)($profile['accent_color'] ?? '#E6A23C'),
        ],
        'checkout' => [
            'delivery_enabled' => array_key_exists('delivery_enabled', $checkout) ? (bool)$checkout['delivery_enabled'] : true,
            'pickup_enabled' => array_key_exists('pickup_enabled', $checkout) ? (bool)$checkout['pickup_enabled'] : true,
            'delivery_fee_rial' => max(0, (int)($checkout['delivery_fee_rial'] ?? 0)),
            'minimum_order_rial' => max(0, (int)($checkout['minimum_order_rial'] ?? 0)),
            'reservation_minutes' => min(120, max(5, (int)($checkout['reservation_minutes'] ?? 20))),
        ],
    ];
}

function app_admin_store_settings_read(PDO $db, array $tenant): never {
    app_json(['data' => app_store_settings_read($db, $tenant)]);
}

function app_admin_store_settings_update(PDO $db, array $tenant, array $session): never {
    $body = app_auth_request_body();
    $profile = is_array($body['profile'] ?? null) ? $body['profile'] : [];
    $checkout = is_array($body['checkout'] ?? null) ? $body['checkout'] : [];
    $name = trim((string)($profile['name'] ?? ''));
    $tagline = trim((string)($profile['tagline'] ?? ''));
    $phone = trim((string)($profile['support_phone'] ?? ''));
    $streetAddress = trim((string)($profile['street_address'] ?? ''));
    $city = trim((string)($profile['city'] ?? ''));
    $region = trim((string)($profile['region'] ?? ''));
    $postalCode = trim((string)($profile['postal_code'] ?? ''));
    $countryCode = strtoupper(trim((string)($profile['country_code'] ?? 'IR')));
    $latitudeRaw = trim((string)($profile['latitude'] ?? ''));
    $longitudeRaw = trim((string)($profile['longitude'] ?? ''));
    $openingHours = trim((string)($profile['opening_hours'] ?? ''));
    $primary = strtoupper(trim((string)($profile['primary_color'] ?? '')));
    $accent = strtoupper(trim((string)($profile['accent_color'] ?? '')));
    $deliveryEnabled = filter_var($checkout['delivery_enabled'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $pickupEnabled = filter_var($checkout['pickup_enabled'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $deliveryFee = filter_var($checkout['delivery_fee_rial'] ?? null, FILTER_VALIDATE_INT);
    $minimumOrder = filter_var($checkout['minimum_order_rial'] ?? null, FILTER_VALIDATE_INT);
    $reservationMinutes = filter_var($checkout['reservation_minutes'] ?? null, FILTER_VALIDATE_INT);

    if ($name === '' || mb_strlen($name) > 120 || mb_strlen($tagline) > 180 ||
        ($phone !== '' && !preg_match('/^[0-9+()\\- ]{7,24}$/', $phone)) ||
        mb_strlen($streetAddress)>500 || mb_strlen($city)>100 || mb_strlen($region)>100 ||
        ($postalCode!=='' && !preg_match('/^[A-Za-z0-9 -]{3,20}$/',$postalCode)) ||
        !preg_match('/^[A-Z]{2}$/',$countryCode) ||
        (($latitudeRaw==='') !== ($longitudeRaw==='')) ||
        ($latitudeRaw!=='' && (!is_numeric($latitudeRaw) || (float)$latitudeRaw < -90 || (float)$latitudeRaw > 90)) ||
        ($longitudeRaw!=='' && (!is_numeric($longitudeRaw) || (float)$longitudeRaw < -180 || (float)$longitudeRaw > 180)) ||
        mb_strlen($openingHours)>180 ||
        ($openingHours!=='' && !preg_match('/^(?:(?:Mo|Tu|We|Th|Fr|Sa|Su)(?:-(?:Mo|Tu|We|Th|Fr|Sa|Su))? (?:[01][0-9]|2[0-3]):[0-5][0-9]-(?:[01][0-9]|2[0-3]):[0-5][0-9])(?:,\\s*(?:Mo|Tu|We|Th|Fr|Sa|Su)(?:-(?:Mo|Tu|We|Th|Fr|Sa|Su))? (?:[01][0-9]|2[0-3]):[0-5][0-9]-(?:[01][0-9]|2[0-3]):[0-5][0-9])*$/',$openingHours)) ||
        !preg_match('/^#[0-9A-F]{6}$/', $primary) || !preg_match('/^#[0-9A-F]{6}$/', $accent) ||
        $deliveryEnabled === null || $pickupEnabled === null ||
        ($deliveryEnabled === false && $pickupEnabled === false) ||
        $deliveryFee === false || $deliveryFee < 0 || $deliveryFee > 1000000000 ||
        $minimumOrder === false || $minimumOrder < 0 || $minimumOrder > 10000000000 ||
        $reservationMinutes === false || $reservationMinutes < 5 || $reservationMinutes > 120) {
        app_json(['error' => 'تنظیمات فروشگاه معتبر نیست؛ اطلاعات را بررسی کنید.'], 422);
    }

    $profileData = [
        'name' => $name,
        'tagline' => $tagline,
        'support_phone' => $phone,
        'street_address' => $streetAddress,
        'city' => $city,
        'region' => $region,
        'postal_code' => $postalCode,
        'country_code' => $countryCode,
        'latitude' => $latitudeRaw==='' ? null : (float)$latitudeRaw,
        'longitude' => $longitudeRaw==='' ? null : (float)$longitudeRaw,
        'opening_hours' => $openingHours,
        'primary_color' => $primary,
        'accent_color' => $accent,
    ];
    $checkoutData = [
        'delivery_enabled' => $deliveryEnabled,
        'pickup_enabled' => $pickupEnabled,
        'delivery_fee_rial' => $deliveryFee,
        'minimum_order_rial' => $minimumOrder,
        'reservation_minutes' => $reservationMinutes,
    ];
    $db->beginTransaction();
    try {
        $save = $db->prepare(
            'INSERT INTO store_settings (tenant_id,setting_key,setting_value) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
        );
        $save->execute([$tenant['id'], 'store.profile', json_encode($profileData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $save->execute([$tenant['id'], 'store.checkout', json_encode($checkoutData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        app_audit_log($db, (int)$tenant['id'], (int)$session['id'], 'store.settings.updated', 'store_settings', null, [
            'profile_fields' => ['name','tagline','support_phone','street_address','city','region','postal_code','country_code','latitude','longitude','opening_hours','primary_color','accent_color'],
            'checkout_fields' => ['delivery_enabled','pickup_enabled','delivery_fee_rial','minimum_order_rial','reservation_minutes'],
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    app_json(['data' => app_store_settings_read($db, $tenant), 'message' => 'تنظیمات فروشگاه ذخیره شد.']);
}
