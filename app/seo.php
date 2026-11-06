<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/store_settings.php';

/**
 * Resolve SEO context only from a verified host mapping.
 * Unlike the API tenant resolver, this helper returns null so the demo homepage
 * can still render when a local database has not been configured.
 */
function app_seo_context(): ?array {
    try {
        $host = app_host();
        $db = app_db();
        $stmt = $db->prepare(
            "SELECT t.id,t.name,t.slug,t.timezone,t.currency,d.host AS request_host,
                    COALESCE((SELECT primary_domain.host FROM tenant_domains primary_domain
                              WHERE primary_domain.tenant_id=t.id AND primary_domain.is_primary=1
                                AND primary_domain.verified_at IS NOT NULL
                              ORDER BY primary_domain.id LIMIT 1),d.host) AS canonical_host
             FROM tenant_domains d
             JOIN tenants t ON t.id=d.tenant_id
             WHERE d.host=? AND d.verified_at IS NOT NULL AND t.status='active'
             LIMIT 1"
        );
        $stmt->execute([$host]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tenant) return null;
        $environment = strtolower(app_env('APP_ENV','development') ?? 'development');
        $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        $scheme = $https || $environment === 'production' ? 'https' : 'http';
        return [
            'db' => $db,
            'tenant' => $tenant,
            'host' => (string)($tenant['canonical_host'] ?? $host),
            'request_host' => $host,
            'scheme' => $scheme,
            'base_url' => $scheme . '://' . (string)($tenant['canonical_host'] ?? $host),
        ];
    } catch (Throwable $error) {
        return null;
    }
}

function app_seo_url(array $context, string $path = ''): string {
    return rtrim((string)$context['base_url'],'/') . '/' . ltrim($path,'/');
}

function app_seo_store_profile(array $context): array {
    return app_store_settings_read($context['db'],$context['tenant'])['profile'] ?? [];
}

function app_seo_category_list(array $context): array {
    $stmt = $context['db']->prepare(
        'SELECT id,title,slug,sort_order,updated_at
         FROM categories
         WHERE tenant_id=? AND is_active=1
           AND EXISTS (
             SELECT 1 FROM products p
             WHERE p.tenant_id=categories.tenant_id
               AND p.category_id=categories.id
               AND p.is_active=1 AND p.product_kind<>'variant'
           )
         ORDER BY sort_order,id
         LIMIT 49999'
    );
    $stmt->execute([(int)$context['tenant']['id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function app_seo_product_count(array $context): int {
    $stmt = $context['db']->prepare(
        "SELECT COUNT(*) FROM products
         WHERE tenant_id=? AND is_active=1 AND product_kind<>'variant'"
    );
    $stmt->execute([(int)$context['tenant']['id']]);
    return (int)$stmt->fetchColumn();
}

function app_seo_product_list(array $context, int $limit = 45000, int $offset = 0): array {
    $limit = max(1,min(45000,$limit));
    $offset = max(0,$offset);
    $stmt = $context['db']->prepare(
        "SELECT p.id,p.title,p.sku,p.seo_slug,p.updated_at,p.created_at
         FROM products p
         WHERE p.tenant_id=? AND p.is_active=1 AND p.product_kind<>'variant'
         ORDER BY p.id ASC
         LIMIT " . $limit . " OFFSET " . $offset
    );
    $stmt->execute([(int)$context['tenant']['id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



function app_seo_tracking_markup(array $profile): string {
    $markup = '';
    $verification = trim((string)($profile['google_site_verification'] ?? ''));
    if ($verification !== '' && preg_match('/^[A-Za-z0-9_=-]{10,200}$/',$verification)) {
        $markup .= '<meta name="google-site-verification" content="' . htmlspecialchars($verification,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') . '">' . "\n";
    }
    $measurementId = strtoupper(trim((string)($profile['ga4_measurement_id'] ?? '')));
    if ($measurementId !== '' && preg_match('/^G-[A-Z0-9]{6,20}$/',$measurementId)) {
        $markup .= '<script defer src="/assets/js/analytics-consent.js" data-ga4-measurement="' . htmlspecialchars($measurementId,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') . '"></script>' . "\n";
    }
    return $markup;
}

function app_seo_product_path(array $product): string {
    $id = (int)($product['id'] ?? 0);
    $slug = trim((string)($product['seo_slug'] ?? ''));
    return 'product/'.$id.($slug !== '' ? '/'.rawurlencode($slug) : '');
}

function app_seo_lastmod(?string $value): string {
    if (!$value) return gmdate('Y-m-d');
    $timestamp = strtotime($value);
    return $timestamp === false ? gmdate('Y-m-d') : gmdate('Y-m-d',$timestamp);
}

function app_seo_escape(string $value): string {
    return htmlspecialchars($value,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
}
