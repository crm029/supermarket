<?php
declare(strict_types=1);

/**
 * Example white-label store configuration.
 * Copy to store.php for a local demo; never commit live merchant secrets.
 */
return [
    'name' => 'سوپرمارکت نمونه',
    'slug' => 'sample-market',
    'branding' => [
        'logo' => 'assets/media/logo-placeholder.svg',
        'primary' => '#173d2c',
        'accent' => '#c8e36a',
        'surface' => '#fffdf7',
    ],
    'contact' => [
        'phone' => '',
        'address' => '',
        'support_hours' => '',
    ],
    'fulfillment' => [
        'delivery_enabled' => true,
        'pickup_enabled' => true,
        'delivery_fee_rial' => 0,
        'minimum_order_rial' => 0,
    ],
    'media' => [
        'hero' => 'assets/media/store-hero-placeholder.jpg',
        'aisles' => [],
    ],
];
