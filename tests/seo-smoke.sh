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

check_page "/robots.php" "Sitemap: http://localhost/sitemap.xml"
check_page "/sitemap.php" "http://localhost/product/1"
check_page "/sitemap.php" "http://localhost/category/dairy"
check_page "/product.php?id=1" "شیر پرچرب یک لیتری"
check_page "/product.php?id=1" 'application/ld+json'
check_page "/category.php?slug=dairy" "/product/1"
check_page "/index.php" 'rel="canonical" href="http://localhost/"'
check_page "/index.php" 'application/ld+json'

echo "SEO landing pages, canonical metadata, robots and sitemap smoke tests passed."
