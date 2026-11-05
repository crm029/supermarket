#!/usr/bin/env bash
set -euo pipefail

BASE_URL="http://127.0.0.1:8100"
HOST_HEADER="localhost"
OUT="$(mktemp)"
trap 'rm -f "$OUT"' EXIT

check_page() {
  local path="$1"
  local expected="$2"
  local status
  status="$(curl -sS -o "$OUT" -w '%{http_code}' -H "Host: $HOST_HEADER" "$BASE_URL$path")"
  if [[ "$status" != "200" ]]; then
    echo "SEO page $path returned HTTP $status"
    cat "$OUT"
    exit 1
  fi
  if ! grep -Fq "$expected" "$OUT"; then
    echo "SEO page $path did not contain expected marker: $expected"
    cat "$OUT"
    exit 1
  fi
}

check_redirect() {
  local path="$1"
  local expected="$2"
  local status
  status="$(curl -sS -D "$OUT" -o /dev/null -w '%{http_code}' -H "Host: $HOST_HEADER" "$BASE_URL$path")"
  if [[ "$status" != "301" ]] || ! grep -Fiq "Location: http://localhost$expected" "$OUT"; then
    echo "Expected permanent redirect from $path to $expected; received HTTP $status"
    cat "$OUT"
    exit 1
  fi
}

check_page "/robots.php" "Sitemap: http://localhost/sitemap.xml"
check_page "/sitemap.php" "http://localhost/product/1/test-product"
check_page "/sitemap.php" "http://localhost/category/dairy"
status="$(curl -sS -o "$OUT" -w '%{http_code}' -H "Host: $HOST_HEADER" "$BASE_URL/sitemap.php")"
if [[ "$status" != "200" ]]; then
  echo "Sitemap returned HTTP $status"
  cat "$OUT"
  exit 1
fi
sitemap_count="$(grep -o '<url>' "$OUT" | wc -l | tr -d ' ')"
if (( sitemap_count > 50000 )); then
  echo "Sitemap exceeded the 50,000 URL protocol limit: $sitemap_count"
  exit 1
fi
check_page "/product/1/test-product" "شیر پرچرب یک لیتری"
check_page "/product/1/test-product" "عنوان سئو محصول تست"
check_page "/product/1/test-product" "توضیحات متا محصول تست"
check_page "/product/1/test-product" 'application/ld+json'
check_page "/product/1/test-product" 'rel="canonical" href="http://localhost/product/1/test-product"'
check_redirect "/product/1" "/product/1/test-product"
check_redirect "/product/1/old-product-slug" "/product/1/test-product"
check_redirect "/product.php?id=1" "/product/1/test-product"
check_page "/category/dairy" "/product/1/test-product"
check_redirect "/category.php?slug=dairy" "/category/dairy"
check_page "/category/dairy" "عنوان سئو دسته تست"
check_page "/category/dairy" "توضیحات متا دسته تست"
check_redirect "/category/api-created-category" "/category/api-updated-category"
check_page "/category/api-updated-category" "دسته ویرایش‌شده"
check_page "/index.php" 'rel="canonical" href="http://localhost/"'
check_page "/index.php" 'application/ld+json'
check_page "/index.php" 'property="og:locale" content="fa_IR"'
check_page "/index.php" 'name="twitter:title"'
check_page "/index.php" 'name="twitter:description"'
check_page "/product/1/test-product" 'name="twitter:title"'
check_page "/product/1/test-product" 'name="twitter:description"'
check_page "/category/dairy" 'name="twitter:title"'
check_page "/category/dairy" 'name="twitter:description"'
check_page "/index.php" 'name="google-site-verification" content="testverificationtoken123"'
check_page "/index.php" 'data-ga4-measurement="G-ABC1234567"'
check_page "/product/1/test-product" 'data-ga4-measurement="G-ABC1234567"'
check_page "/category/dairy" 'data-ga4-measurement="G-ABC1234567"'
check_page "/payment-result.php?status=success&order=999999" 'data-ga4-measurement="G-ABC1234567"'
if grep -Fq "sm360Track('purchase'" "$OUT"; then
  echo "Unverified payment result must not emit a purchase event"
  exit 1
fi
check_page "/index.php" 'PostalAddress'
check_page "/index.php" 'خیابان نمونه، پلاک ۱۰'
check_page "/index.php" 'GeoCoordinates'
check_page "/index.php" 'openingHours'
check_page "/category/dairy" 'application/ld+json'
grep -Fq 'RewriteRule ^robots\.txt$ robots.php' .htaccess
grep -Fq 'RewriteRule ^sitemap\.xml$ sitemap.php' .htaccess
grep -Fq 'RewriteRule ^product/' .htaccess
grep -Fq 'RewriteRule ^category/' .htaccess

echo "SEO landing pages, canonical metadata, robots and sitemap smoke tests passed."
