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
    if ($check === "product-image") {
      foreach (($data["data"] ?? []) as $row) {
        if (($row["sku"] ?? "") === "API-SMOKE-001") {
          if (($row["image"]["url"] ?? null) !== "https://cdn.example.test/assets/products/ci%20milk%20bottle.jpg") exit(1);
          exit(0);
        }
      }
      exit(1);
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
request GET /admin/scenes 200 "" "$TOKEN"
request GET /admin/products 403 "" "$UNPRIVILEGED_TOKEN" "" tenant-two.localhost
request POST /admin/products 419 '{"sku":"NO-CSRF-1","title":"نباید ثبت شود","price_rial":1000}' "$TOKEN"

request POST /admin/products 201 "{\"sku\":\"API-CREATED-001\",\"title\":\"کالای ساخته‌شده با تست\",\"price_rial\":120000,\"category_id\":$CATEGORY_ID,\"unit_label\":\"عدد\",\"is_weighted\":false,\"is_active\":true}" "$TOKEN" "$CSRF"
NEW_PRODUCT_ID="$(assert_json created-id)"

request PATCH "/admin/products/$NEW_PRODUCT_ID" 200 '{"title":"کالای ویرایش‌شده"}' "$TOKEN" "$CSRF"
request DELETE "/admin/products/$NEW_PRODUCT_ID" 200 "" "$TOKEN" "$CSRF"

request POST /admin/categories 201 '{"title":"دسته ساخته‌شده با تست","slug":"api-created-category","sort_order":100,"is_active":true}' "$TOKEN" "$CSRF"
NEW_CATEGORY_ID="$(assert_json created-id)"
request PATCH "/admin/categories/$NEW_CATEGORY_ID" 200 '{"title":"دسته ویرایش‌شده"}' "$TOKEN" "$CSRF"
request DELETE "/admin/categories/$NEW_CATEGORY_ID" 200 "" "$TOKEN" "$CSRF"

request GET "/products/$PRODUCT_ID" 404 "" "" "" "tenant-two.localhost"


request POST /cart/quote 200 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}]}' 
assert_json cart-quote
request POST /me/addresses 201 '{"title":"خانه تست","recipient":"مشتری تست","mobile":"09120000003","province":"تهران","city":"تهران","address":"خیابان نمونه، پلاک ۱","postal_code":"1234567890"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
ADDRESS_ID="$(assert_json created-id)"
request POST /checkout 201 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":2}],"fulfillment":"delivery","address_id":'$ADDRESS_ID'}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
ORDER_ID="$(assert_json order-created)"
RESERVED="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT reserved FROM stock_levels WHERE tenant_id=1 AND product_id=$PRODUCT_ID LIMIT 1")"
if [[ "$RESERVED" != "2" ]]; then echo "Expected 2 reserved units, received $RESERVED"; exit 1; fi
request POST "/me/orders/$ORDER_ID/cancel" 200 "" "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
RESERVED="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT reserved FROM stock_levels WHERE tenant_id=1 AND product_id=$PRODUCT_ID LIMIT 1")"
ORDER_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM orders WHERE tenant_id=1 AND id=$ORDER_ID")"
if [[ "$RESERVED" != "0" || "$ORDER_STATUS" != "cancelled" ]]; then echo "Customer cancellation did not release stock or update order"; exit 1; fi

request POST /checkout 201 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":1}],"fulfillment":"pickup"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
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
