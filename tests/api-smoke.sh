#!/usr/bin/env bash
set -euo pipefail

BASE_URL="http://127.0.0.1:8099/api/v1"
OUT="/tmp/supermarket-api-response.json"
TOKEN="$(printf 'S%.0s' {1..43})"
CSRF="$(printf 'C%.0s' {1..43})"
UNPRIVILEGED_TOKEN="$(printf 'T%.0s' {1..43})"
CUSTOMER_TOKEN="$(printf 'U%.0s' {1..43})"
CUSTOMER_CSRF="$(printf 'W%.0s' {1..43})"

request() {
  local method="$1"
  local path="$2"
  local expected="$3"
  local body="${4:-}"
  local token="${5:-}"
  local csrf="${6:-}"
  local host="${7:-localhost}"
  local args=(--silent --show-error --output "$OUT" --write-out "%{http_code}" -X "$method"
    -H "Host: $host" -H "Accept: application/json")
  if [[ -n "$body" ]]; then
    args+=(-H "Content-Type: application/json" --data "$body")
  fi
  if [[ -n "$token" ]]; then args+=(-H "Cookie: sm_session=$token"); fi
  if [[ -n "$csrf" ]]; then args+=(-H "X-CSRF-Token: $csrf"); fi
  local status
  status="$(curl "${args[@]}" "$BASE_URL$path")"
  if [[ "$status" != "$expected" ]]; then
    echo "Expected HTTP $expected for $method $path; received $status"
    cat "$OUT"
    exit 1
  fi
}

assert_json() {
  php -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($data)) { fwrite(STDERR, "Invalid JSON response\n"); exit(1); }
    $check = $argv[2];
    if ($check === "product-seo-fields" || $check === "product-seo-update") {
      foreach (($data["data"] ?? []) as $row) {
        if (($row["sku"] ?? "") === "API-CREATED-001") {
          $expectedTitle = $check === "product-seo-fields" ? "عنوان سئو API" : "عنوان سئوی ویرایش‌شده";
          $expectedSlug = $check === "product-seo-fields" ? "api-created-product" : "api-updated-product";
          if (($row["seo_title"] ?? "") !== $expectedTitle ||
              ($row["seo_slug"] ?? "") !== $expectedSlug ||
              ($row["seo_description"] ?? "") !== "توضیحات متای API") exit(1);
          exit(0);
        }
      }
      exit(1);
    }
    if ($check === "category-seo-fields" || $check === "category-seo-update") {
      foreach (($data["data"] ?? []) as $row) {
        if (($row["slug"] ?? "") === "api-created-category") {
          $expectedTitle = $check === "category-seo-fields" ? "عنوان سئو دسته API" : "عنوان سئو ویرایش‌شده";
          $expectedSlug = $check === "category-seo-fields" ? "api-created-category" : "api-updated-category";
          if (($row["slug"] ?? "") !== $expectedSlug ||
              ($row["description"] ?? "") !== "توضیح دسته API" ||
              ($row["seo_title"] ?? "") !== $expectedTitle ||
              ($row["seo_description"] ?? "") !== "متای دسته API") exit(1);
          exit(0);
        }
      }
      exit(1);
    }
    if ($check === "low-stock-zero") {
      foreach (($data["data"] ?? []) as $row) {
        if (($row["sku"] ?? "") === "API-SMOKE-LOW-001") {
          if ((int)($row["available_quantity"] ?? -1) !== 0 ||
              (int)($row["shortage_quantity"] ?? -1) !== 4 ||
              (int)($row["warehouse_id"] ?? 0) < 1) exit(1);
          exit(0);
        }
      }
      exit(1);
    }
    if ($check === "product-image") {
      foreach (($data["data"] ?? []) as $row) {
        if (($row["sku"] ?? "") === "API-SMOKE-001") {
          if (($row["image"]["url"] ?? null) !== "https://cdn.example.test/assets/products/ci%20milk%20bottle.jpg") exit(1);
          exit(0);
        }
      }
      exit(1);
    }
    if ($check === "pickup-slots") {
      if (($data["data"]["configured"] ?? null) !== true || count($data["data"]["slots"] ?? []) !== 1 ||
          (int)($data["data"]["slots"][0]["remaining_capacity"] ?? 0) !== 1) exit(1);
      exit(0);
    }
    if ($check === "pickup-slots-empty") {
      if (($data["data"]["configured"] ?? null) !== true || count($data["data"]["slots"] ?? []) !== 0) exit(1);
      exit(0);
    }
    if ($check === "advanced-search") {
      $rows = $data["data"] ?? [];
      if (count($rows) !== 1 || ($rows[0]["sku"] ?? "") !== "API-SMOKE-001" ||
          (int)($rows[0]["price_rial"] ?? 0) !== 125000 ||
          ($rows[0]["in_stock"] ?? null) !== true) exit(1);
      exit(0);
    }
    if ($check === "variant-parent") {
      foreach (($data["data"] ?? []) as $row) {
        if (($row["sku"] ?? "") === "API-VARIANT-GROUP") {
          if (($row["has_variants"] ?? null) !== true || (int)($row["price_rial"] ?? 0) !== 65000) exit(1);
          exit(0);
        }
      }
      exit(1);
    }
    if ($check === "variant-detail") {
      if (($data["data"]["has_variants"] ?? null) !== true || count($data["data"]["variants"] ?? []) !== 2) exit(1);
      exit(0);
    }
    if ($check === "variant-quote") {
      if (count($data["data"]["items"] ?? []) !== 1 || (int)($data["data"]["items"][0]["quantity"] ?? 0) !== 2) exit(1);
      exit(0);
    }
    if ($check === "product-detail") {
      if (($data["data"]["in_stock"] ?? null) !== true ||
          ($data["data"]["image"]["url"] ?? null) !== "https://cdn.example.test/assets/products/ci%20milk%20bottle.jpg") exit(1);
      exit(0);
    }
    if ($check === "category-image") {
      foreach (($data["data"] ?? []) as $row) {
        if (($row["slug"] ?? "") === "api-smoke-category") {
          if (($row["image"]["url"] ?? null) !== "https://cdn.example.test/assets/products/ci%20milk%20bottle.jpg") exit(1);
          exit(0);
        }
      }
      exit(1);
    }
    if ($check === "scene-hotspots") {
      $scene = ($data["data"] ?? [])[0] ?? null;
      if (!$scene || ($scene["media"]["url"] ?? null) !== "https://cdn.example.test/assets/products/ci%20milk%20bottle.jpg") exit(1);
      $product = false; $category = false;
      foreach (($scene["hotspots"] ?? []) as $hotspot) {
        if (($hotspot["product"]["id"] ?? null) !== null) $product = true;
        if (($hotspot["category"]["slug"] ?? "") === "api-smoke-category") $category = true;
      }
      if (!$product || !$category) exit(1);
      exit(0);
    }
    if ($check === "addresses-empty") { if (!is_array($data["data"] ?? null) || count($data["data"]) !== 0) exit(1); exit(0); }
    if ($check === "store-settings") {
      $profile = $data["data"]["profile"] ?? [];
      $checkout = $data["data"]["checkout"] ?? [];
      if (($profile["name"] ?? "") !== "فروشگاه آزمایشی" ||
          ($profile["street_address"] ?? "") !== "خیابان نمونه، پلاک ۱۰" ||
          ($profile["city"] ?? "") !== "تهران" ||
          ($profile["country_code"] ?? "") !== "IR" ||
          (float)($profile["latitude"] ?? 0) !== 35.6892 ||
          (float)($profile["longitude"] ?? 0) !== 51.389 ||
          ($profile["opening_hours"] ?? "") !== "Mo-Su 08:00-22:00" ||
          ($profile["google_site_verification"] ?? "") !== "testverificationtoken123" ||
          ($profile["ga4_measurement_id"] ?? "") !== "G-ABC1234567" ||
          (int)($checkout["delivery_fee_rial"] ?? -1) !== 1000 ||
          (int)($checkout["reservation_minutes"] ?? -1) !== 15) exit(1);
      exit(0);
    }
    if ($check === "public-store-settings") {
      if (($data["data"]["fulfillment"]["delivery"] ?? null) !== true ||
          ($data["data"]["settings"]["checkout"]["delivery_fee_rial"] ?? null) != 1000) exit(1);
      exit(0);
    }
    if ($check === "staff-list") { if (!is_array($data["data"]["roles"] ?? null) || !is_array($data["data"]["staff"] ?? null)) exit(1); exit(0); }
    if ($check === "media-library") {
      $items = $data["data"] ?? [];
      if (!is_array($items) || count($items) < 2) exit(1);
      foreach ($items as $item) if ((int)($item["usage_count"] ?? 0) > 0) exit(0);
      exit(1);
    }
    if ($check === "media-cleaned") {
      foreach (($data["data"] ?? []) as $item) {
        if (($item["alt_text"] ?? "") === "تصویر بدون استفاده") exit(1);
      }
      exit(0);
    }
    if ($check === "cart-empty") {
      if (!is_array($data["data"]["items"] ?? null) || count($data["data"]["items"]) !== 0) exit(1);
      exit(0);
    }
    if ($check === "cart-saved") {
      if ((int)($data["data"]["item_count"] ?? 0) !== 2) exit(1);
      exit(0);
    }
    if ($check === "cart-item") {
      if (count($data["data"]["items"] ?? []) !== 1 ||
          (int)($data["data"]["items"][0]["quantity"] ?? 0) !== 2) exit(1);
      exit(0);
    }
    if ($check === "wallet-summary") {
      if ((int)($data["data"]["balance_rial"] ?? -1) !== 0 || !is_array($data["data"]["entries"] ?? null)) exit(1);
      exit(0);
    }
    if ($check === "cart-quote") {
      if ((int)($data["data"]["total_rial"] ?? 0) !== 250000 || (int)($data["data"]["items"][0]["available_quantity"] ?? 0) !== 10) exit(1);
      exit(0);
    }
    if ($check === "registered-user") {
      if (($data["data"]["user"]["mobile"] ?? "") !== "09120000004") exit(1);
      exit(0);
    }
    if ($check === "order-created") {
      if (($data["data"]["status"] ?? "") !== "pending_payment" || empty($data["data"]["order_number"])) exit(1);
      echo (int)$data["data"]["id"];
      exit(0);
    }
    if ($check === "created-id") {
      $id = (int)($data["data"]["id"] ?? 0);
      if ($id < 1) exit(1);
      echo $id;
      exit(0);
    }
    exit(0);
  ' "$OUT" "$1"
}

for _ in $(seq 1 20); do
  status="$(curl --silent --output /dev/null --write-out "%{http_code}" -H 'Host: localhost' "$BASE_URL/store" || true)"
  [[ "$status" == "200" ]] && break
  sleep 1
done

subdirectory_status="$(curl --silent --output "$OUT" --write-out "%{http_code}" -H 'Host: localhost' "http://127.0.0.1:8099/supermarket/api/v1/store")"
if [[ "$subdirectory_status" != "200" ]]; then
  echo "API front controller failed under a subdirectory path: $subdirectory_status"
  cat "$OUT"
  exit 1
fi

request GET /categories 200
assert_json category-image
request GET /products 200
assert_json product-image
request GET "/products?brand=%D8%A8%D8%B1%D9%86%D8%AF%20%D8%AA%D8%B3%D8%AA&min_price=120000&max_price=130000&sort=price_asc&in_stock=1" 200
assert_json advanced-search
request GET "/products?min_price=invalid" 422

PRODUCT_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT id FROM products WHERE tenant_id=1 AND sku='API-SMOKE-001' LIMIT 1")"
CATEGORY_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT id FROM categories WHERE tenant_id=1 AND slug='api-smoke-category' LIMIT 1")"

request GET "/products/$PRODUCT_ID" 200
assert_json product-detail
request GET /scenes 200
assert_json scene-hotspots

request GET /admin/products 200 "" "$TOKEN"
request GET /admin/categories 200 "" "$TOKEN"
request GET /admin/orders 200 "" "$TOKEN"
request GET /admin/reports/overview 200 "" "$TOKEN"
request GET /admin/warehouses 200 "" "$TOKEN"
request GET /admin/stock 200 "" "$TOKEN"
request GET /admin/stock/low 200 "" "$TOKEN"
assert_json low-stock-zero
request GET /admin/scenes 200 "" "$TOKEN"
request GET /admin/media 200 "" "$TOKEN"
assert_json media-library
MEDIA_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT id FROM media_assets WHERE tenant_id=1 AND alt_text='تصویر تست شیر' LIMIT 1")"
UNUSED_MEDIA_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT id FROM media_assets WHERE tenant_id=1 AND alt_text='تصویر بدون استفاده' LIMIT 1")"
request DELETE "/admin/media/$MEDIA_ID" 409 "" "$TOKEN" "$CSRF"
request DELETE "/admin/media/$UNUSED_MEDIA_ID" 419 "" "$TOKEN"
request DELETE "/admin/media/$UNUSED_MEDIA_ID" 200 "" "$TOKEN" "$CSRF"
QUEUE_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM media_cleanup_queue WHERE tenant_id=1 AND storage_key='tenant-1/product/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg' LIMIT 1")"
if [[ "$QUEUE_STATUS" != "completed" ]]; then echo "Unused media cleanup queue did not complete."; exit 1; fi
request GET /admin/media 200 "" "$TOKEN"
assert_json media-cleaned
request GET /admin/settings 403 "" "$UNPRIVILEGED_TOKEN" "" tenant-two.localhost
request GET /admin/settings 200 "" "$TOKEN"
request PUT /admin/settings 419 '{"profile":{},"checkout":{}}' "$TOKEN"
request PUT /admin/settings 422 '{"profile":{"name":"اطلاعات نامعتبر","primary_color":"#173D2D","accent_color":"#E6A23C","latitude":95,"longitude":51.389},"checkout":{"delivery_enabled":true,"pickup_enabled":true,"delivery_fee_rial":1000,"minimum_order_rial":1,"reservation_minutes":15}}' "$TOKEN" "$CSRF"
request PUT /admin/settings 422 '{"profile":{"name":"شناسه نامعتبر","primary_color":"#173D2D","accent_color":"#E6A23C","ga4_measurement_id":"INVALID"},"checkout":{"delivery_enabled":true,"pickup_enabled":true,"delivery_fee_rial":1000,"minimum_order_rial":1,"reservation_minutes":15}}' "$TOKEN" "$CSRF"
request PUT /admin/settings 200 '{"profile":{"name":"فروشگاه آزمایشی","tagline":"تست تنظیمات","support_phone":"02112345678","street_address":"خیابان نمونه، پلاک ۱۰","city":"تهران","region":"تهران","postal_code":"1234567890","country_code":"IR","latitude":35.6892,"longitude":51.389,"opening_hours":"Mo-Su 08:00-22:00","google_site_verification":"testverificationtoken123","ga4_measurement_id":"G-ABC1234567","primary_color":"#173D2D","accent_color":"#E6A23C"},"checkout":{"delivery_enabled":true,"pickup_enabled":true,"delivery_fee_rial":1000,"minimum_order_rial":1,"reservation_minutes":15}}' "$TOKEN" "$CSRF"
request GET /admin/settings 200 "" "$TOKEN"
assert_json store-settings
request GET /store 200
assert_json public-store-settings
request GET /admin/products 403 "" "$UNPRIVILEGED_TOKEN" "" tenant-two.localhost
request POST /admin/products 419 '{"sku":"NO-CSRF-1","title":"نباید ثبت شود","price_rial":1000}' "$TOKEN"

request POST /admin/products 201 "{\"sku\":\"API-CREATED-001\",\"title\":\"کالای ساخته‌شده با تست\",\"price_rial\":120000,\"category_id\":$CATEGORY_ID,\"unit_label\":\"عدد\",\"is_weighted\":false,\"is_active\":true,\"seo_title\":\"عنوان سئو API\",\"seo_slug\":\"api-created-product\",\"seo_description\":\"توضیحات متای API\"}" "$TOKEN" "$CSRF"
NEW_PRODUCT_ID="$(assert_json created-id)"
request GET "/admin/products" 200 "" "$TOKEN"
assert_json product-seo-fields

request PATCH "/admin/products/$NEW_PRODUCT_ID" 200 '{"title":"کالای ویرایش‌شده","seo_title":"عنوان سئوی ویرایش‌شده","seo_slug":"api-updated-product"}' "$TOKEN" "$CSRF"
request GET "/admin/products" 200 "" "$TOKEN"
assert_json product-seo-update
request POST /admin/products 409 '{"sku":"API-DUPLICATE-SEO-SLUG","title":"نامک تکراری","price_rial":1000,"seo_slug":"api-updated-product"}' "$TOKEN" "$CSRF"
request DELETE "/admin/products/$NEW_PRODUCT_ID" 200 "" "$TOKEN" "$CSRF"
request POST /admin/products 201 '{"sku":"API-VARIANT-GROUP","title":"شیر پرچرب","price_rial":0,"category_id":'$CATEGORY_ID',"unit_label":"گزینه‌ها","product_kind":"variant_parent","is_weighted":false,"is_active":true}' "$TOKEN" "$CSRF"
VARIANT_PARENT_ID="$(assert_json created-id)"
request POST "/admin/products/$VARIANT_PARENT_ID/variants" 201 '{"sku":"API-VAR-1L","variant_label":"۱ لیتری","price_rial":100000,"unit_label":"بطری","low_stock_threshold":2,"is_weighted":false,"is_active":true}' "$TOKEN" "$CSRF"
VARIANT_1L_ID="$(assert_json created-id)"
request POST "/admin/products/$VARIANT_PARENT_ID/variants" 201 '{"sku":"API-VAR-500","variant_label":"۵۰۰ گرمی","price_rial":70000,"unit_label":"بطری","low_stock_threshold":2,"is_weighted":false,"is_active":true}' "$TOKEN" "$CSRF"
VARIANT_500_ID="$(assert_json created-id)"
request PATCH "/admin/products/$VARIANT_PARENT_ID/variants/$VARIANT_500_ID" 200 '{"price_rial":65000}' "$TOKEN" "$CSRF"

request POST /admin/categories 201 '{"title":"دسته ساخته‌شده با تست","slug":"api-created-category","description":"توضیح دسته API","seo_title":"عنوان سئو دسته API","seo_description":"متای دسته API","sort_order":100,"is_active":true}' "$TOKEN" "$CSRF"
NEW_CATEGORY_ID="$(assert_json created-id)"
request GET "/admin/categories" 200 "" "$TOKEN"
assert_json category-seo-fields
request PATCH "/admin/categories/$NEW_CATEGORY_ID" 200 '{"title":"دسته ویرایش‌شده","slug":"api-updated-category","seo_title":"عنوان سئو ویرایش‌شده"}' "$TOKEN" "$CSRF"
request GET "/admin/categories" 200 "" "$TOKEN"
assert_json category-seo-update

request GET "/products/$PRODUCT_ID" 404 "" "" "" "tenant-two.localhost"


request POST /cart/quote 200 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}]}' 
assert_json cart-quote
request GET /me/wallet 200 "" "$CUSTOMER_TOKEN"
assert_json wallet-summary
request GET /me/cart 200 "" "$CUSTOMER_TOKEN"
assert_json cart-empty
request PUT /me/cart 200 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}]}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
assert_json cart-saved
request GET /me/cart 200 "" "$CUSTOMER_TOKEN"
assert_json cart-item
request PUT /me/cart 419 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}]}' "$CUSTOMER_TOKEN"
request GET /me/cart 401 "" "$CUSTOMER_TOKEN" "" tenant-two.localhost
request PUT /me/cart 200 '{"items":[]}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request GET /me/cart 200 "" "$CUSTOMER_TOKEN"
assert_json cart-empty
request POST /me/addresses 201 '{"title":"خانه تست","recipient":"مشتری تست","mobile":"09120000003","province":"تهران","city":"تهران","address":"خیابان نمونه، پلاک ۱","postal_code":"1234567890"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
ADDRESS_ID="$(assert_json created-id)"
request PATCH "/me/addresses/$ADDRESS_ID" 419 "" "$CUSTOMER_TOKEN"
request PATCH "/me/addresses/$ADDRESS_ID" 200 '{"title":"خانه ویرایش‌شده","city":"کرج"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request POST /admin/delivery-zones 201 '{"title":"کل شهر کرج","province":"تهران","city":"کرج","postal_prefix":"","delivery_fee_rial":5000,"minimum_order_rial":1,"sort_order":1,"is_active":true}' "$TOKEN" "$CSRF"
GENERAL_ZONE_ID="$(assert_json created-id)"
request POST /admin/delivery-zones 201 '{"title":"مرکز کرج","province":"تهران","city":"کرج","postal_prefix":"123","delivery_fee_rial":3000,"minimum_order_rial":1,"sort_order":2,"is_active":true}' "$TOKEN" "$CSRF"
ZONE_ID="$(assert_json created-id)"
request POST /admin/delivery-zones 409 '{"title":"محدوده تکراری","province":"تهران","city":"کرج","postal_prefix":"123","delivery_fee_rial":4000,"minimum_order_rial":1,"sort_order":3,"is_active":true}' "$TOKEN" "$CSRF"
request PATCH "/admin/delivery-zones/$ZONE_ID" 200 '{"delivery_fee_rial":3000}' "$TOKEN" "$CSRF"
request POST /checkout 201 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}],"fulfillment":"delivery","address_id":'$ADDRESS_ID'}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
ORDER_ID="$(assert_json order-created)"
ORDER_FEE="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT delivery_fee_rial FROM orders WHERE tenant_id=1 AND id=$ORDER_ID")"
ORDER_TOTAL="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT total_rial FROM orders WHERE tenant_id=1 AND id=$ORDER_ID")"
ORDER_ZONE="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT delivery_zone_id FROM orders WHERE tenant_id=1 AND id=$ORDER_ID")"
if [[ "$ORDER_FEE" != "3000" || "$ORDER_TOTAL" != "253000" || "$ORDER_ZONE" != "$ZONE_ID" ]]; then echo "Most-specific delivery zone fee was not applied to the order."; exit 1; fi
RESERVED="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT reserved FROM stock_levels WHERE tenant_id=1 AND product_id=$PRODUCT_ID LIMIT 1")"
if [[ "$RESERVED" != "2" ]]; then echo "Expected 2 reserved units, received $RESERVED"; exit 1; fi
request POST "/me/orders/$ORDER_ID/cancel" 200 "" "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request POST /admin/coupons 201 '{"code":"WELCOME10","title":"تخفیف خوش‌آمدگویی","discount_type":"percentage","discount_value":10,"max_discount_rial":15000,"minimum_order_rial":1,"usage_limit":1,"per_user_limit":1,"starts_at":"2020-01-01T00:00","ends_at":"2030-01-01T00:00","is_active":true}' "$TOKEN" "$CSRF"
COUPON_ID="$(assert_json created-id)"
request POST /admin/coupons 409 '{"code":"WELCOME10","title":"کد تکراری","discount_type":"percentage","discount_value":10,"minimum_order_rial":1,"per_user_limit":1,"starts_at":"2020-01-01T00:00","ends_at":"2030-01-01T00:00","is_active":true}' "$TOKEN" "$CSRF"
request GET /admin/coupons 200 "" "$TOKEN"
request PATCH "/admin/coupons/$COUPON_ID" 200 '{"per_user_limit":1}' "$TOKEN" "$CSRF"
request POST /checkout 201 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}],"fulfillment":"delivery","address_id":'$ADDRESS_ID',"coupon_code":"WELCOME10"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
COUPON_ORDER_ID="$(assert_json order-created)"
COUPON_TOTAL="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT total_rial FROM orders WHERE tenant_id=1 AND id=$COUPON_ORDER_ID")"
COUPON_DISCOUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT discount_rial FROM orders WHERE tenant_id=1 AND id=$COUPON_ORDER_ID")"
COUPON_LINK="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT coupon_id FROM orders WHERE tenant_id=1 AND id=$COUPON_ORDER_ID")"
if [[ "$COUPON_TOTAL" != "238000" || "$COUPON_DISCOUNT" != "15000" || "$COUPON_LINK" != "$COUPON_ID" ]]; then echo "Coupon discount cap or order snapshot was not applied."; exit 1; fi
request POST /checkout 409 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}],"fulfillment":"delivery","address_id":'$ADDRESS_ID',"coupon_code":"WELCOME10"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request POST "/me/orders/$COUPON_ORDER_ID/cancel" 200 "" "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request POST /checkout 201 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}],"fulfillment":"delivery","address_id":'$ADDRESS_ID',"coupon_code":"WELCOME10"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
COUPON_REUSE_ORDER_ID="$(assert_json order-created)"
request POST "/me/orders/$COUPON_REUSE_ORDER_ID/cancel" 200 "" "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request POST /admin/coupons 201 '{"code":"FIXED5000","title":"پنج هزار تومان تخفیف","discount_type":"fixed","discount_value":50000,"minimum_order_rial":1,"per_user_limit":2,"starts_at":"2020-01-01T00:00","ends_at":"2030-01-01T00:00","is_active":true}' "$TOKEN" "$CSRF"
FIXED_COUPON_ID="$(assert_json created-id)"
request POST /checkout 201 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":1}],"fulfillment":"delivery","address_id":'$ADDRESS_ID',"coupon_code":"FIXED5000"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
FIXED_COUPON_ORDER_ID="$(assert_json order-created)"
FIXED_DISCOUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT discount_rial FROM orders WHERE tenant_id=1 AND id=$FIXED_COUPON_ORDER_ID")"
if [[ "$FIXED_DISCOUNT" != "50000" ]]; then echo "Fixed coupon amount was not applied."; exit 1; fi
request POST "/me/orders/$FIXED_COUPON_ORDER_ID/cancel" 200 "" "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request DELETE "/admin/coupons/$FIXED_COUPON_ID" 200 "" "$TOKEN" "$CSRF"
request POST /checkout 422 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":1}],"fulfillment":"delivery","address_id":'$ADDRESS_ID',"coupon_code":"FIXED5000"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request POST /admin/pickup-slots 201 '{"title":"نوبت صبح","starts_at":"2030-01-01T10:00","ends_at":"2030-01-01T11:00","capacity":1}' "$TOKEN" "$CSRF"
PICKUP_SLOT_ID="$(assert_json created-id)"
request POST /admin/pickup-slots 409 '{"title":"نوبت تکراری","starts_at":"2030-01-01T10:00","ends_at":"2030-01-01T11:00","capacity":1}' "$TOKEN" "$CSRF"
request GET /pickup-slots 200
assert_json pickup-slots
request POST /checkout 201 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}],"fulfillment":"pickup","pickup_slot_id":'$PICKUP_SLOT_ID'}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
PICKUP_ORDER_ID="$(assert_json order-created)"
request POST /checkout 409 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}],"fulfillment":"pickup","pickup_slot_id":'$PICKUP_SLOT_ID'}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request POST "/me/orders/$PICKUP_ORDER_ID/cancel" 200 "" "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request DELETE "/admin/pickup-slots/$PICKUP_SLOT_ID" 200 "" "$TOKEN" "$CSRF"
request GET /pickup-slots 200
assert_json pickup-slots-empty
request POST /checkout 422 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}],"fulfillment":"pickup"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request PATCH "/me/addresses/$ADDRESS_ID" 200 '{"city":"شیراز"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request POST /checkout 422 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}],"fulfillment":"delivery","address_id":'$ADDRESS_ID'}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request PATCH "/me/addresses/$ADDRESS_ID" 200 '{"city":"کرج"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request PUT /admin/settings 200 '{"profile":{"name":"فروشگاه آزمایشی","tagline":"تست تنظیمات","support_phone":"02112345678","street_address":"خیابان نمونه، پلاک ۱۰","city":"تهران","region":"تهران","postal_code":"1234567890","country_code":"IR","latitude":35.6892,"longitude":51.389,"opening_hours":"Mo-Su 08:00-22:00","google_site_verification":"testverificationtoken123","ga4_measurement_id":"G-ABC1234567","primary_color":"#173D2D","accent_color":"#E6A23C"},"checkout":{"delivery_enabled":false,"pickup_enabled":true,"delivery_fee_rial":1000,"minimum_order_rial":1,"reservation_minutes":15}}' "$TOKEN" "$CSRF"
request POST /checkout 409 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}],"fulfillment":"delivery","address_id":'$ADDRESS_ID'}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request PUT /admin/settings 200 '{"profile":{"name":"فروشگاه آزمایشی","tagline":"تست تنظیمات","support_phone":"02112345678","street_address":"خیابان نمونه، پلاک ۱۰","city":"تهران","region":"تهران","postal_code":"1234567890","country_code":"IR","latitude":35.6892,"longitude":51.389,"opening_hours":"Mo-Su 08:00-22:00","google_site_verification":"testverificationtoken123","ga4_measurement_id":"G-ABC1234567","primary_color":"#173D2D","accent_color":"#E6A23C"},"checkout":{"delivery_enabled":true,"pickup_enabled":true,"delivery_fee_rial":1000,"minimum_order_rial":1,"reservation_minutes":15}}' "$TOKEN" "$CSRF"
RESERVED="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT reserved FROM stock_levels WHERE tenant_id=1 AND product_id=$PRODUCT_ID LIMIT 1")"
ORDER_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM orders WHERE tenant_id=1 AND id=$ORDER_ID")"
if [[ "$RESERVED" != "0" || "$ORDER_STATUS" != "cancelled" ]]; then echo "Customer cancellation did not release stock or update order"; exit 1; fi

request POST /admin/pickup-slots 201 '{"title":"نوبت آزمون انقضای رزرو","starts_at":"2030-01-02T10:00","ends_at":"2030-01-02T11:00","capacity":1}' "$TOKEN" "$CSRF"
EXPIRY_PICKUP_SLOT_ID="$(assert_json created-id)"
request POST /checkout 201 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":1}],"fulfillment":"pickup","pickup_slot_id":'$EXPIRY_PICKUP_SLOT_ID'}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
EXPIRING_ORDER_ID="$(assert_json order-created)"
mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "UPDATE stock_reservations SET expires_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE tenant_id=1 AND order_id=$EXPIRING_ORDER_ID AND status='active'"
php bin/expire-reservations.php
EXPIRED_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM orders WHERE tenant_id=1 AND id=$EXPIRING_ORDER_ID")"
RESERVED="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT reserved FROM stock_levels WHERE tenant_id=1 AND product_id=$PRODUCT_ID LIMIT 1")"
if [[ "$EXPIRED_STATUS" != "cancelled" || "$RESERVED" != "0" ]]; then echo "Reservation expiry worker failed to cancel order and release stock"; exit 1; fi

request POST /admin/stock/receipts 200 '{"warehouse_id":1,"product_id":'$PRODUCT_ID',"quantity":3,"reason":"رسید تست"}' "$TOKEN" "$CSRF"
request POST /admin/stock/adjustments 200 '{"warehouse_id":1,"product_id":'$PRODUCT_ID',"quantity":-1,"reason":"اصلاح تست"}' "$TOKEN" "$CSRF"
request POST /admin/warehouses 201 '{"title":"انبار انتقال تست","warehouse_type":"other"}' "$TOKEN" "$CSRF"
NEW_WAREHOUSE_ID="$(assert_json created-id)"
request GET "/admin/products/$VARIANT_PARENT_ID/variants" 200 "" "$TOKEN"
request POST /admin/stock/receipts 200 '{"warehouse_id":1,"product_id":'$VARIANT_1L_ID',"quantity":5,"reason":"موجودی تنوع یک لیتری"}' "$TOKEN" "$CSRF"
request POST /admin/stock/receipts 200 '{"warehouse_id":1,"product_id":'$VARIANT_500_ID',"quantity":3,"reason":"موجودی تنوع نیم لیتری"}' "$TOKEN" "$CSRF"
request GET /products 200
assert_json variant-parent
request GET "/products/$VARIANT_PARENT_ID" 200
assert_json variant-detail
request POST /cart/quote 409 '{"items":[{"product_id":'$VARIANT_PARENT_ID',"quantity":1}]}'
request POST /cart/quote 200 '{"items":[{"product_id":'$VARIANT_1L_ID',"quantity":2}]}'
assert_json variant-quote
request POST /checkout 201 '{"items":[{"product_id":'$VARIANT_1L_ID',"quantity":2}],"fulfillment":"delivery","address_id":'$ADDRESS_ID'}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
VARIANT_ORDER_ID="$(assert_json order-created)"
request POST "/me/orders/$VARIANT_ORDER_ID/cancel" 200 "" "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request DELETE "/admin/products/$VARIANT_PARENT_ID/variants/$VARIANT_500_ID" 200 "" "$TOKEN" "$CSRF"
request DELETE "/admin/products/$VARIANT_PARENT_ID" 200 "" "$TOKEN" "$CSRF"
request POST /cart/quote 409 '{"items":[{"product_id":'$VARIANT_1L_ID',"quantity":1}]}'
request PATCH "/admin/products/$VARIANT_PARENT_ID" 200 '{"is_active":true}' "$TOKEN" "$CSRF"
request POST /admin/stock/transfers 200 '{"from_warehouse_id":1,"to_warehouse_id":'$NEW_WAREHOUSE_ID',"product_id":'$PRODUCT_ID',"quantity":2}' "$TOKEN" "$CSRF"
TOTAL_STOCK="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT SUM(on_hand) FROM stock_levels WHERE tenant_id=1 AND product_id=$PRODUCT_ID")"
if [[ "$TOTAL_STOCK" != "12" ]]; then echo "Expected 12 total units after receipt, adjustment and transfer; received $TOTAL_STOCK"; exit 1; fi

request POST /admin/scenes 201 '{"title":"راهرو تست","scene_type":"aisle_photo","is_published":false}' "$TOKEN" "$CSRF"
SCENE_ID="$(assert_json created-id)"
request POST "/admin/scenes/$SCENE_ID/hotspots" 201 '{"label":"کالای تست","action":"product","product_id":'$PRODUCT_ID',"x":0.4,"y":0.6}' "$TOKEN" "$CSRF"
HOTSPOT_ID="$(assert_json created-id)"
request GET "/admin/scenes/$SCENE_ID/hotspots" 200 "" "$TOKEN"
request DELETE "/admin/hotspots/$HOTSPOT_ID" 200 "" "$TOKEN" "$CSRF"
request POST /auth/register 201 '{"full_name":"مشتری ثبت‌نامی","mobile":"09120000004","referral_code":"APICUSTOMER1"}'
request POST /auth/request-otp 202 '{"mobile":"09120000004"}'
OTP_HASH="$(php -r 'echo hash_hmac("sha256", "123456", "ci-test-secret-with-more-than-thirty-two-characters");')"
mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "INSERT INTO auth_challenges (tenant_id,mobile,purpose,otp_hash,attempts,expires_at) VALUES (1,'09120000004','login','$OTP_HASH',0,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE))"
request POST /auth/verify-otp 200 '{"mobile":"09120000004","code":"123456"}'
assert_json registered-user
REGISTERED_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM users WHERE tenant_id=1 AND mobile='09120000004'")"
REFERRAL_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM referral_events re JOIN users u ON u.id=re.referred_user_id WHERE u.tenant_id=1 AND u.mobile='09120000004'")"
if [[ "$REGISTERED_STATUS" != "active" || "$REFERRAL_COUNT" != "1" ]]; then echo "OTP registration or referral activation failed"; exit 1; fi
echo "HTTP API and commerce smoke tests passed."

# Product variant integration coverage follows the same tenant-scoped checkout fixtures.
