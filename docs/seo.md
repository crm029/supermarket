# SEO foundation and acceptance checklist

This platform is multi-tenant. SEO output must be generated for the verified host that serves the store and must never mix catalogue data between tenants.

## Implemented foundation

- The SEO context resolves a store only through a verified tenant_domains host mapping.
- Canonical URLs use the tenant's verified primary domain when one is configured.
- Production canonical URLs use HTTPS; local/testing environments retain HTTP when TLS is not enabled.
- /robots.txt is served by robots.php and points to the tenant-aware /sitemap.xml.
- /sitemap.xml is generated from the current tenant's active categories and active non-variant product pages.
- /category/{slug} serves crawlable category pages with product links and CollectionPage / ItemList structured data; changing a category slug records a permanent redirect from its previous URL.
- /product/{id}/{seo-slug} serves crawlable product detail pages; the slug is optional until the merchant assigns one.
- The homepage includes a tenant-specific title, meta description, canonical, social metadata, GroceryStore structured data and server-rendered category links.
- Optional merchant-verified street address, postal address, geo coordinates and opening hours are emitted as local-business structured data.
- Google Search Console HTML verification and GA4 measurement IDs are configurable per tenant; GA4 is loaded only after the visitor accepts analytics.
- SEO landing pages link back into the interactive storefront; product and category deep links are opened by the storefront after the live catalogue loads.
- Query-string catalogue/filter URLs are excluded from crawling to reduce duplicate URL variants.

## Current limitations — do not treat these as complete

- Product SEO titles/descriptions and category descriptions/SEO titles/descriptions are merchant-editable in the seller dashboard. Their search quality still depends on accurate, unique merchant-written content.
- Optional store address, city/region, country code, postal code, geographic coordinates and opening hours are editable in store settings and included in GroceryStore structured data. Merchants must enter accurate, verified public details.
- Product image quality and descriptive alt text depend on each merchant's real catalogue/media uploads.
- The sitemap and metadata are code-ready, but a store is not discoverable until its domain is verified, deployed over HTTPS and crawled.
- When a store has a published virtual-store scene, its first published scene image is reused as the homepage hero and social-share image; its media alt text is used for accessibility and preview metadata.
- Each tenant sitemap is capped at the protocol limit of 50,000 URLs. If a catalogue grows beyond the available slots, the most recently updated product pages are included first; introduce a sitemap index and partitioned product sitemaps before a store approaches that scale.
- The merchant must still verify the production domain in Search Console and submit its sitemap. GA4 view_item, view_item_list, add_to_cart, begin_checkout and verified purchase events are available after analytics consent; event receipt must be checked against the merchant's real GA4 property.
- Core Web Vitals, mobile performance, image delivery and crawl behaviour must be measured on the final hosting environment with real catalogue volume.

## Merchant launch checklist

1. Verify the primary store domain and enable HTTPS.
2. Set the public store name, concise tagline and support phone.
3. Replace sample products with real titles, brands, prices, descriptions and images.
4. Write unique descriptions for important categories and products; do not copy supplier boilerplate.
5. Add meaningful alt text to every important product/category image.
6. Confirm /robots.txt, /sitemap.xml, /category/{slug} and /product/{id} return successfully on the production host.
7. Verify the domain in Google Search Console, submit the sitemap and review indexing coverage.
8. Configure GA4 and test product view, add-to-cart, checkout and purchase events.
9. Run mobile/Core Web Vitals checks after real images and hosting are configured.

## Validation

tests/seo-smoke.sh checks the tenant-aware robots output, sitemap URLs, product/category HTML, canonical metadata and structured-data blocks against the seeded MySQL tenant in CI.
