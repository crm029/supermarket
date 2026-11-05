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
         ORDER BY sort_order,id
         LIMIT 500'
    );
    $stmt->execute([(int)$context['tenant']['id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function app_seo_product_list(array $context, int $limit = 50000): array {
    $limit = max(1,min(50000,$limit));
    $stmt = $context['db']->prepare(
        "SELECT p.id,p.title,p.sku,p.updated_at,p.created_at
         FROM products p
         WHERE p.tenant_id=? AND p.is_active=1 AND p.product_kind<>'variant'
         ORDER BY COALESCE(p.updated_at,p.created_at) DESC,p.id DESC
         LIMIT " . $limit
    );
    $stmt->execute([(int)$context['tenant']['id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function app_seo_lastmod(?string $value): string {
    if (!$value) return gmdate('Y-m-d');
    $timestamp = strtotime($value);
    return $timestamp === false ? gmdate('Y-m-d') : gmdate('Y-m-d',$timestamp);
}

function app_seo_escape(string $value): string {
    return htmlspecialchars($value,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
}
