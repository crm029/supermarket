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
check_page "/product/1/test-product" "شیر پرچرب یک لیتری"
check_page "/product/1/test-product" "عنوان سئو محصول تست"
check_page "/product/1/test-product" "توضیحات متا محصول تست"
check_page "/product/1/test-product" 'application/ld+json'
check_page "/product/1/test-product" 'rel="canonical" href="http://localhost/product/1/test-product"'
check_redirect "/product/1" "/product/1/test-product"
check_redirect "/product/1/old-product-slug" "/product/1/test-product"
check_redirect "/product.php?id=1" "/product/1/test-product"
check_page "/category.php?slug=dairy" "/product/1/test-product"
check_page "/category.php?slug=dairy" "عنوان سئو دسته تست"
check_page "/category.php?slug=dairy" "توضیحات متا دسته تست"
check_page "/index.php" 'rel="canonical" href="http://localhost/"'
check_page "/index.php" 'application/ld+json'
check_page "/category.php?slug=dairy" 'application/ld+json'
grep -Fq 'RewriteRule ^robots\.txt$ robots.php' .htaccess
grep -Fq 'RewriteRule ^sitemap\.xml$ sitemap.php' .htaccess
grep -Fq 'RewriteRule ^product/' .htaccess
grep -Fq 'RewriteRule ^category/' .htaccess

echo "SEO landing pages, canonical metadata, robots and sitemap smoke tests passed."
