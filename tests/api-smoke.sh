#!/usr/bin/env bash
set -euo pipefail

BASE_URL="http://127.0.0.1:8099/api/v1"
OUT="/tmp/supermarket-api-response.json"
TOKEN="$(printf 'S%.0s' {1..43})"
CSRF="$(printf 'C%.0s' {1..43})"
UNPRIVILEGED_TOKEN="$(printf 'T%.0s' {1..43})"
CUSTOMER_TOKEN="$(printf 'U%.0s' {1..43})"
CUSTOMER_CSRF="$(printf 'W%.0s' {1..43})"
WORKER_TOKEN="$(printf 'V%.0s' {1..43})"
WORKER_CSRF="$(printf 'X%.0s' {1..43})"
POS_TOKEN="$(printf 'P%.0s' {1..43})"
POS_CSRF="$(printf 'Q%.0s' {1..43})"
POS_MANAGER_TOKEN="$(printf 'M%.0s' {1..43})"
POS_MANAGER_CSRF="$(printf 'N%.0s' {1..43})"

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
      $expectedTitle = $check === "category-seo-fields" ? "عنوان سئو دسته API" : "عنوان سئو ویرایش‌شده";
      $expectedSlug = $check === "category-seo-fields" ? "api-created-category" : "api-updated-category";
      foreach (($data["data"] ?? []) as $row) {
        if (($row["slug"] ?? "") === $expectedSlug) {
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
    if ($check === "wallet-payment") {
      if (($data["data"]["status"] ?? "") !== "paid" ||
          ($data["data"]["payment_method"] ?? "") !== "wallet" ||
          ($data["data"]["already_paid"] ?? null) !== false ||
          (int)($data["data"]["wallet_balance_rial"] ?? -1) !== 0) exit(1);
      exit(0);
    }
    if ($check === "wallet-payment-idempotent") {
      if (($data["data"]["status"] ?? "") !== "paid" ||
          ($data["data"]["payment_method"] ?? "") !== "wallet" ||
          ($data["data"]["already_paid"] ?? null) !== true) exit(1);
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
    if ($check === "platform-tenant-created") {
      $id = (int)($data["data"]["tenant_id"] ?? 0);
      $verification=$data["data"]["verification"]??[];
      if ($id < 1 || ($data["data"]["status"] ?? "") !== "pending" ||
          ($verification["record_type"]??"")!=="TXT" ||
          ($verification["record_name"]??"")!=="_supermarket360-verification.ci-platform-store.example.test" ||
          ($verification["record_value"]??"")!=="ci-only-domain-proof-2026") exit(1);
      echo $id;
      exit(0);
    }
    if ($check === "platform-challenge") {
      $verification=$data["data"]??[];
      if (($verification["record_type"]??"")!=="TXT" ||
          ($verification["record_name"]??"")!=="_supermarket360-verification.ci-platform-store.example.test" ||
          ($verification["record_value"]??"")!=="ci-only-domain-proof-2026") exit(1);
      exit(0);
    }
    if ($check === "platform-audit-events") {
      $actions = array_column($data["data"] ?? [], "action");
      foreach (["platform.tenant.created","platform.tenant.domain_challenge_issued","platform.tenant.domain_verified","platform.tenant.suspended"] as $expected) {
        if (!in_array($expected, $actions, true)) exit(1);
      }
      exit(0);
    }
    if ($check === "order-notification") {
      $rows=$data["data"]??[];
      foreach($rows as $row) if(($row["title"]??"")==="سفارش شما ثبت شد") exit(0);
      exit(1);
    }
    if ($check === "notification-id") {
      $id=(int)($data["data"][0]["id"]??0);
      if($id<1)exit(1);
      echo $id;exit(0);
    }
    if ($check === "assignment-notification") {
      $rows=$data["data"]??[];
      foreach($rows as $row) if(($row["notification_type"]??"")==="assignment") exit(0);
      exit(1);
    }
    if ($check === "order-status-notification") {
      $rows=$data["data"]??[];
      foreach($rows as $row) if(str_contains((string)($row["body"]??""),"پذیرفته‌شده")) exit(0);
      exit(1);
    }
    if ($check === "manager-task-notification") {
      $rows=$data["data"]??[];
      foreach($rows as $row) if(($row["title"]??"")==="وظیفه سفارش تکمیل شد") exit(0);
      exit(1);
    }
    if ($check === "refund-created") {
      if(($data["data"]["refund_method"]??"")!=="wallet_credit"||(int)($data["data"]["amount_rial"]??0)!==25000)exit(1);
      exit(0);
    }
    if ($check === "wallet-refund-entry") {
      foreach(($data["data"]["entries"]??[]) as $entry){
        if(($entry["source_type"]??"")==="order_refund"&&(int)($entry["amount_rial"]??0)===25000)exit(0);
      }
      exit(1);
    }
    if ($check === "refund-notification") {
      foreach(($data["data"]??[]) as $row) if(($row["title"]??"")==="اعتبار بازپرداخت به کیف پول اضافه شد")exit(0);
      exit(1);
    }
    if ($check === "unread-empty") {
      if((int)($data["unread_count"]??-1)!==0)exit(1);
      exit(0);
    }
    if ($check === "worker-tasks") {
      $tasks = $data["data"] ?? [];
      $picker = false; $courier = false;
      foreach ($tasks as $task) {
        if (($task["assignment_type"] ?? "") === "picker" && count($task["items"] ?? []) > 0) $picker = true;
        if (($task["assignment_type"] ?? "") === "courier" && !empty($task["delivery_address"])) $courier = true;
      }
      if (!$picker || !$courier) exit(1);
      exit(0);
    }
    if ($check === "pickup-handoff-waiting" || $check === "pickup-handoff-assigned") {
      $count = (int)($data["data"]["operations"]["waiting_pickup_handoff"] ?? -1);
      $expected = $check === "pickup-handoff-waiting" ? 1 : 0;
      if ($count !== $expected) exit(1);
      exit(0);
    }
    if ($check === "worker-pickup-task") {
      foreach (($data["data"] ?? []) as $task) {
        if (($task["assignment_type"] ?? null) === "pickup" &&
            ($task["order_status"] ?? null) === "ready_pickup") exit(0);
      }
      exit(1);
    }
    if ($check === "pickup-order-delivered") {
      if (($data["data"]["status"] ?? null) !== "delivered" ||
          ($data["data"]["fulfillment"] ?? null) !== "pickup") exit(1);
      exit(0);
    }
    if ($check === "delivery-timeline") {
      $events = $data["data"]["delivery_events"] ?? [];
      $types = array_column($events,"event_type");
      $assigned = array_search("assigned",$types,true);
      $started = array_search("out_for_delivery",$types,true);
      $delivered = array_search("delivered",$types,true);
      if ($assigned === false || $started === false || $delivered === false ||
          !($assigned < $started && $started < $delivered)) exit(1);
      exit(0);
    }
    if ($check === "operations-overview") {
      $operations = $data["data"]["operations"] ?? null;
      $required = ["waiting_picker","waiting_pack","waiting_courier","in_transit","ready_pickup","waiting_pickup_handoff","delivered_today"];
      if (!is_array($operations)) exit(1);
      foreach ($required as $key) if (!isset($operations[$key]) || !is_numeric($operations[$key])) exit(1);
      if (array_key_exists("average_delivery_minutes_30d",$operations) &&
          $operations["average_delivery_minutes_30d"] !== null && !is_numeric($operations["average_delivery_minutes_30d"])) exit(1);
      exit(0);
    }
    if ($check === "operations-delivery-completed") {
      $operations = $data["data"]["operations"] ?? [];
      if ((int)($operations["delivered_today"] ?? 0) < 1) exit(1);
      exit(0);
    }
    if ($check === "pos-empty-shift") { if (!array_key_exists("shift",$data["data"]??[]) || $data["data"]["shift"] !== null) exit(1); exit(0); }
    if ($check === "pos-shift-open") {
      $shift=$data["data"]["shift"]??[];
      if ((int)($shift["opening_float_rial"]??0)!==500000 || (int)($shift["expected_cash_rial"]??0)!==500000 ||
          ($shift["status"]??"")!=="open") exit(1);
      exit(0);
    }
    if ($check === "pos-sale") {
      $sale=$data["data"]??[];
      if ((int)($sale["total_rial"]??0)!==125000 || (int)($sale["cash_received_rial"]??0)!==150000 ||
          (int)($sale["change_rial"]??0)!==25000 || count($sale["items"]??[])!==1) exit(1);
      echo (int)($sale["id"]??0); exit(0);
    }
    if ($check === "pos-sale-idempotent") {
      if ((int)($data["data"]["total_rial"]??0)!==125000 || empty($data["data"]["sale_number"])) exit(1);
      exit(0);
    }
    if ($check === "pos-sales-list") {
      $rows=$data["data"]??[];
      if (count($rows)!==1 || (int)($rows[0]["total_rial"]??0)!==125000) exit(1);
      exit(0);
    }
    if ($check === "pos-sale-detail" || $check === "pos-sale-detail-refunded") {
      $items=$data["data"]["items"]??[];
      if (count($items)!==1 || (int)($items[0]["sale_item_id"]??0)<1) exit(1);
      $expectedRefunded=$check==="pos-sale-detail-refunded"?1:0;
      $expectedRemaining=$check==="pos-sale-detail-refunded"?0:1;
      if ((int)($items[0]["refunded_quantity"]??-1)!==$expectedRefunded ||
          (int)($items[0]["refundable_quantity"]??-1)!==$expectedRemaining) exit(1);
      exit(0);
    }
    if ($check === "pos-report-refunds") {
      $report=$data["data"]["in_person"]??[];
      if ((int)($report["sales_total_rial"]??-1)!==125000 ||
          (int)($report["refund_count"]??-1)!==1 ||
          (int)($report["refund_total_rial"]??-1)!==125000 ||
          (int)($report["net_sales_total_rial"]??-1)!==0) exit(1);
      $cashierGross=0; $cashierRefunds=0; $cashierNet=0;
      foreach (($data["data"]["cashiers"]??[]) as $cashier) {
        $cashierGross+=(int)($cashier["sales_total_rial"]??0);
        $cashierRefunds+=(int)($cashier["refund_total_rial"]??-1000000000000);
        $cashierNet+=(int)($cashier["net_sales_total_rial"]??-1000000000000);
      }
      if ($cashierGross!==125000 || $cashierRefunds!==125000 || $cashierNet!==0) exit(1);
      exit(0);
    }
    if ($check === "pos-refund-idempotent") {
      if (empty($data["data"]["idempotent"]) || (int)($data["data"]["amount_rial"]??0)!==125000) exit(1);
      exit(0);
    }
    if ($check === "pos-shift-closed") {
      $shift=$data["data"]??[];
      if (($shift["status"]??"")!=="closed" || (int)($shift["expected_cash_rial"]??-1)!==625000 ||
          (int)($shift["counted_cash_rial"]??-1)!==625000 || (int)($shift["cash_variance_rial"]??-1)!==0) exit(1);
      exit(0);
    }
    exit(0);
  ' "$OUT" "$1" || { echo "JSON assertion failed: $1"; cat "$OUT"; exit 1; }
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

# Global platform tenant provisioning and lifecycle.
request GET /platform/tenants 200 "" "$TOKEN"
request GET /platform/tenants 403 "" "$UNPRIVILEGED_TOKEN" "" tenant-two.localhost
request GET /platform/audit 403 "" "$UNPRIVILEGED_TOKEN" "" tenant-two.localhost
request POST /platform/tenants 419 '{"name":"نباید ساخته شود","slug":"csrf-store","host":"csrf.example.test","owner_name":"مالک تست","owner_mobile":"09120000995","timezone":"Asia/Tehran"}' "$TOKEN"
request POST /platform/tenants 422 '{"name":"دامنه نامعتبر","slug":"invalid-host-store","host":"https://invalid.example.test/path","owner_name":"مالک تست","owner_mobile":"09120000995","timezone":"Asia/Tehran"}' "$TOKEN" "$CSRF"
request POST /platform/tenants 201 '{"name":"فروشگاه تست پلتفرم","slug":"ci-platform-store","host":"ci-platform-store.example.test","owner_name":"مالک فروشگاه CI","owner_mobile":"09120000995","timezone":"Asia/Tehran"}' "$TOKEN" "$CSRF"
NEW_TENANT_ID="$(assert_json platform-tenant-created)"
NEW_OWNER_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM users WHERE tenant_id=$NEW_TENANT_ID AND mobile='09120000995' LIMIT 1")"
NEW_WAREHOUSE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM warehouses WHERE tenant_id=$NEW_TENANT_ID AND warehouse_type='main' AND is_active=1")"
NEW_OWNER_ROLE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id=ur.role_id AND r.role_scope_id=ur.role_scope_id WHERE ur.tenant_id=$NEW_TENANT_ID AND r.role_key='store_owner'")"
if [[ "$NEW_OWNER_STATUS" != "pending" || "$NEW_WAREHOUSE_COUNT" != "1" || "$NEW_OWNER_ROLE_COUNT" != "1" ]]; then echo "Platform tenant bootstrap did not create pending owner, owner role and main warehouse."; exit 1; fi
mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "UPDATE tenant_domains SET verification_token_hash=SHA2('incorrect-ci-token',256) WHERE tenant_id=$NEW_TENANT_ID AND is_primary=1"
request POST "/platform/tenants/$NEW_TENANT_ID/verify-domain" 422 '{}' "$TOKEN" "$CSRF"
ATTEMPTS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT verification_attempts FROM tenant_domains WHERE tenant_id=$NEW_TENANT_ID AND is_primary=1")"
if [[ "$ATTEMPTS" != "1" ]]; then echo "DNS verification mismatch was not counted."; exit 1; fi
request POST "/platform/tenants/$NEW_TENANT_ID/verification-challenge" 200 '{}' "$TOKEN" "$CSRF"
assert_json platform-challenge
request POST "/platform/tenants/$NEW_TENANT_ID/verify-domain" 200 '{}' "$TOKEN" "$CSRF"
TENANT_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM tenants WHERE id=$NEW_TENANT_ID")"
DOMAIN_VERIFIED="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM tenant_domains WHERE tenant_id=$NEW_TENANT_ID AND verified_at IS NOT NULL")"
if [[ "$TENANT_STATUS" != "active" || "$DOMAIN_VERIFIED" != "1" ]]; then echo "Verified domain did not activate the tenant."; exit 1; fi
request POST "/platform/tenants/$NEW_TENANT_ID/suspend" 200 '{"reason":"تعلیق آزمایشی برای تست گردش مدیریت پلتفرم"}' "$TOKEN" "$CSRF"
request POST /platform/tenants/1/suspend 409 '{"reason":"نباید فروشگاه میزبان تعلیق شود"}' "$TOKEN" "$CSRF"
TENANT_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM tenants WHERE id=$NEW_TENANT_ID")"
if [[ "$TENANT_STATUS" != "suspended" ]]; then echo "Platform tenant suspension did not persist."; exit 1; fi
request GET /platform/audit 200 "" "$TOKEN"
assert_json platform-audit-events
request GET /admin/reports/overview 200 "" "$TOKEN"
assert_json operations-overview
request GET /admin/reports/orders.csv 200 "" "$TOKEN"
grep -Fq "شماره سفارش" "$OUT"
request GET "/admin/reports/orders.csv?from=invalid" 422 "" "$TOKEN"
request GET /admin/reports/stock-movements.csv 200 "" "$TOKEN"
grep -Fq "شناسه" "$OUT"
request GET "/admin/reports/stock-movements.csv?movement_type=invalid" 422 "" "$TOKEN"
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
request GET /me/notifications 200 "" "$CUSTOMER_TOKEN"
assert_json order-notification
NOTIFICATION_ID="$(assert_json notification-id)"
request POST "/me/notifications/$NOTIFICATION_ID/read" 200 '{}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request GET "/me/notifications?unread=1" 200 "" "$CUSTOMER_TOKEN"
assert_json unread-empty
ORDER_FEE="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT delivery_fee_rial FROM orders WHERE tenant_id=1 AND id=$ORDER_ID")"
ORDER_TOTAL="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT total_rial FROM orders WHERE tenant_id=1 AND id=$ORDER_ID")"
ORDER_ZONE="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT delivery_zone_id FROM orders WHERE tenant_id=1 AND id=$ORDER_ID")"
if [[ "$ORDER_FEE" != "3000" || "$ORDER_TOTAL" != "253000" || "$ORDER_ZONE" != "$ZONE_ID" ]]; then echo "Most-specific delivery zone fee was not applied to the order."; exit 1; fi
# Full wallet checkout: ledger debit, stock commit and paid state are one transaction.
mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "
  SET @customer_id=(SELECT user_id FROM orders WHERE tenant_id=1 AND id=$ORDER_ID);
  SET @wallet_id=(SELECT id FROM wallet_accounts WHERE tenant_id=1 AND user_id=@customer_id);
  INSERT INTO wallet_entries (tenant_id,wallet_id,entry_type,amount_rial,source_type,source_id,idempotency_key,note)
    VALUES (1,@wallet_id,'credit',253000,'ci_funding',$ORDER_ID,CONCAT('ci-wallet-funding-',$ORDER_ID),'CI wallet funding');
"
request POST "/me/orders/$ORDER_ID/pay-wallet" 200 '{}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
assert_json wallet-payment
request POST "/me/orders/$ORDER_ID/pay-wallet" 200 '{}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
assert_json wallet-payment-idempotent
WALLET_DEBITS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM wallet_entries WHERE tenant_id=1 AND source_type='order_wallet_payment' AND source_id=$ORDER_ID")"
WALLET_PAYMENT_ATTEMPTS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM payment_attempts WHERE tenant_id=1 AND order_id=$ORDER_ID AND provider='wallet' AND status='verified'")"
PAID_ORDER_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM orders WHERE tenant_id=1 AND id=$ORDER_ID")"
COMMITTED_RESERVATIONS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM stock_reservations WHERE tenant_id=1 AND order_id=$ORDER_ID AND status='committed'")"
if [[ "$WALLET_DEBITS" != "1" || "$WALLET_PAYMENT_ATTEMPTS" != "1" || "$PAID_ORDER_STATUS" != "paid" || "$COMMITTED_RESERVATIONS" != "1" ]]; then echo "Wallet payment was not settled exactly once with stock commitment."; exit 1; fi
RESERVED="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT reserved FROM stock_levels WHERE tenant_id=1 AND product_id=$PRODUCT_ID LIMIT 1")"
if [[ "$RESERVED" != "0" ]]; then echo "Wallet-paid order still has reserved stock: $RESERVED"; exit 1; fi

# Order picker/courier assignment workflow.
request POST /checkout 201 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":1}],"fulfillment":"delivery","address_id":'$ADDRESS_ID'}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
ASSIGNMENT_ORDER_ID="$(assert_json order-created)"
mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "
  UPDATE orders SET status='paid' WHERE tenant_id=1 AND id=$ASSIGNMENT_ORDER_ID;
  INSERT INTO users (tenant_id,full_name,mobile,referral_code,status)
    VALUES (1,'کارمند عملیات تست','09120000993','ASSIGNMENT003','active');
  SET @worker_id=LAST_INSERT_ID();
  INSERT INTO roles (tenant_id,role_key,title) VALUES (1,'ci_fulfillment_worker','نیروی عملیات CI');
  SET @worker_role=LAST_INSERT_ID();
  INSERT INTO user_roles (tenant_id,role_scope_id,user_id,role_id) VALUES (1,1,@worker_id,@worker_role);
  INSERT INTO role_permissions (role_scope_id,role_id,permission_id)
    SELECT 1,@worker_role,id FROM permissions
    WHERE permission_key IN ('orders.pick','orders.deliver','orders.pickup_handoff') AND scope='tenant';
  INSERT INTO auth_sessions (tenant_id,user_id,token_hash,csrf_hash,expires_at)
    VALUES (1,@worker_id,SHA2(REPEAT('V',43),256),SHA2(REPEAT('X',43),256),DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY));
"
WORKER_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT id FROM users WHERE tenant_id=1 AND mobile='09120000993' LIMIT 1")"
request GET "/admin/orders/$ASSIGNMENT_ORDER_ID/assignments" 200 "" "$TOKEN"
request POST "/admin/orders/$ASSIGNMENT_ORDER_ID/assignments" 201 '{"assignment_type":"picker","assignee_user_id":'$WORKER_ID'}' "$TOKEN" "$CSRF"
PICKER_ASSIGNMENT_ID="$(assert_json created-id)"
request POST "/admin/orders/$ASSIGNMENT_ORDER_ID/assignments" 201 '{"assignment_type":"courier","assignee_user_id":'$WORKER_ID'}' "$TOKEN" "$CSRF"
COURIER_ASSIGNMENT_ID="$(assert_json created-id)"
request GET "/me/notifications" 200 "" "$WORKER_TOKEN"
assert_json assignment-notification
request POST "/admin/orders/$ASSIGNMENT_ORDER_ID/transition" 200 '{"status":"accepted"}' "$TOKEN" "$CSRF"
request GET "/me/notifications" 200 "" "$CUSTOMER_TOKEN"
assert_json order-status-notification
request GET /me/tasks 200 "" "$WORKER_TOKEN"
assert_json worker-tasks
request POST "/me/tasks/$PICKER_ASSIGNMENT_ID/complete" 404 '{}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request POST "/me/tasks/$PICKER_ASSIGNMENT_ID/complete" 409 '{}' "$WORKER_TOKEN" "$WORKER_CSRF"
request POST "/admin/orders/$ASSIGNMENT_ORDER_ID/transition" 200 '{"status":"picking"}' "$TOKEN" "$CSRF"
request POST "/me/tasks/$PICKER_ASSIGNMENT_ID/complete" 200 '{}' "$WORKER_TOKEN" "$WORKER_CSRF"
request GET /me/notifications 200 "" "$TOKEN"
assert_json manager-task-notification
request POST /me/notifications/read-all 200 '{}' "$TOKEN" "$CSRF"
request GET "/me/notifications?unread=1" 200 "" "$TOKEN"
assert_json unread-empty
request POST "/admin/orders/$ASSIGNMENT_ORDER_ID/assignments/$COURIER_ASSIGNMENT_ID/complete" 409 '{}' "$TOKEN" "$CSRF"
request POST "/admin/orders/$ASSIGNMENT_ORDER_ID/transition" 200 '{"status":"packed"}' "$TOKEN" "$CSRF"
request POST "/admin/orders/$ASSIGNMENT_ORDER_ID/transition" 200 '{"status":"dispatched"}' "$TOKEN" "$CSRF"
# An administrator must not start delivery while the order has no active courier.
mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "UPDATE order_assignments SET status='replaced' WHERE tenant_id=1 AND id=$COURIER_ASSIGNMENT_ID AND status='active'"
request POST "/admin/orders/$ASSIGNMENT_ORDER_ID/transition" 409 '{"status":"out_for_delivery"}' "$TOKEN" "$CSRF"
mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "UPDATE order_assignments SET status='active' WHERE tenant_id=1 AND id=$COURIER_ASSIGNMENT_ID AND status='replaced'"
# A duplicate active courier must also block dispatch; exactly one is required.
DUPLICATE_COURIER_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "INSERT INTO order_assignments (tenant_id,order_id,assignee_user_id,assignment_type,status,assigned_by_user_id) SELECT tenant_id,order_id,assignee_user_id,assignment_type,'active',assigned_by_user_id FROM order_assignments WHERE tenant_id=1 AND id=$COURIER_ASSIGNMENT_ID; SELECT LAST_INSERT_ID()")"
request POST "/admin/orders/$ASSIGNMENT_ORDER_ID/transition" 409 '{"status":"out_for_delivery"}' "$TOKEN" "$CSRF"
mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "UPDATE order_assignments SET status='replaced' WHERE tenant_id=1 AND id=$DUPLICATE_COURIER_ID AND status='active'"
request POST "/me/tasks/$COURIER_ASSIGNMENT_ID/start-delivery" 200 '{}' "$WORKER_TOKEN" "$WORKER_CSRF"
request POST "/me/tasks/$COURIER_ASSIGNMENT_ID/complete" 422 '{}' "$WORKER_TOKEN" "$WORKER_CSRF"
request POST "/me/tasks/$COURIER_ASSIGNMENT_ID/complete" 200 '{"recipient_name":"مشتری تحویل‌گیرنده","note":"تحویل سالم"}' "$WORKER_TOKEN" "$WORKER_CSRF"
request GET "/me/orders/$ASSIGNMENT_ORDER_ID" 200 "" "$CUSTOMER_TOKEN"
assert_json delivery-timeline
request GET /admin/reports/overview 200 "" "$TOKEN"
assert_json operations-delivery-completed
request POST "/admin/orders/$ASSIGNMENT_ORDER_ID/assignments" 409 '{"assignment_type":"courier","assignee_user_id":999999999}' "$TOKEN" "$CSRF"
mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "UPDATE orders SET status='pending_payment' WHERE tenant_id=1 AND id=$ASSIGNMENT_ORDER_ID; UPDATE stock_reservations SET expires_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE tenant_id=1 AND order_id=$ASSIGNMENT_ORDER_ID AND status='active'"
php bin/expire-reservations.php

# Full-order wallet credit refund, including referral reward reversal.
REFUND_ORDER_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "
  SET @customer_id=(SELECT id FROM users WHERE tenant_id=1 AND mobile='09120000003' LIMIT 1);
  SET @manager_id=(SELECT id FROM users WHERE tenant_id=1 AND mobile='09120000001' LIMIT 1);
  INSERT INTO orders (tenant_id,user_id,order_number,status,fulfillment,subtotal_rial,discount_rial,delivery_fee_rial,total_rial)
    VALUES (1,@customer_id,'CI-REFUND-ORDER','paid','delivery',25000,0,0,25000);
  SET @refund_order_id=LAST_INSERT_ID();
  INSERT INTO payment_attempts (tenant_id,order_id,provider,authority,idempotency_key,amount_rial,status,verified_at)
    VALUES (1,@refund_order_id,'ci',CONCAT('CI-REFUND-',@refund_order_id),CONCAT('ci-refund-',@refund_order_id),25000,'verified',UTC_TIMESTAMP());
  INSERT IGNORE INTO wallet_accounts (tenant_id,user_id) VALUES (1,@manager_id);
  SET @referrer_wallet=(SELECT id FROM wallet_accounts WHERE tenant_id=1 AND user_id=@manager_id LIMIT 1);
  INSERT IGNORE INTO wallet_entries (tenant_id,wallet_id,entry_type,amount_rial,source_type,source_id,idempotency_key,note)
    VALUES (1,@referrer_wallet,'credit',5000,'referral_reward',@refund_order_id,CONCAT('referral-order-',@refund_order_id),'CI referral reward');
  INSERT INTO referral_events (tenant_id,referrer_user_id,referred_user_id,qualifying_order_id,reward_rial,status)
    VALUES (1,@manager_id,@customer_id,@refund_order_id,5000,'rewarded')
    ON DUPLICATE KEY UPDATE referrer_user_id=VALUES(referrer_user_id),qualifying_order_id=VALUES(qualifying_order_id),reward_rial=VALUES(reward_rial),status='rewarded';
  SELECT @refund_order_id;
")"
request POST "/admin/orders/$REFUND_ORDER_ID/refund-wallet" 422 '{"reason":"کوتاه"}' "$TOKEN" "$CSRF"
request POST "/admin/orders/$REFUND_ORDER_ID/refund-wallet" 201 '{"reason":"بازپرداخت کامل طبق درخواست مشتری و بررسی مدیر"}' "$TOKEN" "$CSRF"
assert_json refund-created
request POST "/admin/orders/$REFUND_ORDER_ID/refund-wallet" 409 '{"reason":"تلاش برای بازپرداخت تکراری سفارش"}' "$TOKEN" "$CSRF"
request POST "/admin/orders/$REFUND_ORDER_ID/refund-wallet" 403 '{"reason":"مشتری اجازه بازپرداخت ندارد"}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request GET /me/wallet 200 "" "$CUSTOMER_TOKEN"
assert_json wallet-refund-entry
request GET /me/notifications 200 "" "$CUSTOMER_TOKEN"
assert_json refund-notification
REFUND_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM orders WHERE tenant_id=1 AND id=$REFUND_ORDER_ID")"
REFUND_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM order_refunds WHERE tenant_id=1 AND order_id=$REFUND_ORDER_ID")"
REFERRAL_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM referral_events WHERE tenant_id=1 AND qualifying_order_id=$REFUND_ORDER_ID")"
REFERRAL_REVERSAL_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM wallet_entries WHERE tenant_id=1 AND source_id=$REFUND_ORDER_ID AND source_type='referral_reward_reversal'")"
if [[ "$REFUND_STATUS" != "refunded" || "$REFUND_COUNT" != "1" || "$REFERRAL_STATUS" != "reversed" || "$REFERRAL_REVERSAL_COUNT" != "1" ]]; then echo "Wallet refund or referral reversal ledger was not finalized."; exit 1; fi
NO_PAYMENT_ORDER_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "INSERT INTO orders (tenant_id,user_id,order_number,status,fulfillment,subtotal_rial,discount_rial,delivery_fee_rial,total_rial) SELECT 1,id,'CI-NO-PAYMENT-REFUND','paid','delivery',10000,0,0,10000 FROM users WHERE tenant_id=1 AND mobile='09120000003'; SELECT LAST_INSERT_ID();")"
request POST "/admin/orders/$NO_PAYMENT_ORDER_ID/refund-wallet" 409 '{"reason":"بازپرداخت بدون پرداخت تأییدشده"}' "$TOKEN" "$CSRF"
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
# Pickup orders require a named staff handoff; admins cannot silently mark them delivered.
mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "
  SET @pickup_customer=(SELECT user_id FROM orders WHERE tenant_id=1 AND id=$PICKUP_ORDER_ID);
  SET @pickup_wallet=(SELECT id FROM wallet_accounts WHERE tenant_id=1 AND user_id=@pickup_customer);
  SET @pickup_amount=(SELECT total_rial FROM orders WHERE tenant_id=1 AND id=$PICKUP_ORDER_ID);
  SET @pickup_balance=(SELECT COALESCE(SUM(CASE WHEN entry_type='credit' THEN amount_rial ELSE -amount_rial END),0)
    FROM wallet_entries WHERE tenant_id=1 AND wallet_id=@pickup_wallet);
  SET @pickup_funding=GREATEST(0,@pickup_amount-@pickup_balance);
  INSERT INTO wallet_entries (tenant_id,wallet_id,entry_type,amount_rial,source_type,source_id,idempotency_key,note)
    SELECT 1,@pickup_wallet,'credit',@pickup_funding,'ci_funding',$PICKUP_ORDER_ID,CONCAT('ci-pickup-funding-',$PICKUP_ORDER_ID),'CI pickup handoff funding'
    WHERE @pickup_funding>0;
"
request POST "/me/orders/$PICKUP_ORDER_ID/pay-wallet" 200 '{}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
assert_json wallet-payment
request POST "/admin/orders/$PICKUP_ORDER_ID/transition" 200 '{"status":"accepted"}' "$TOKEN" "$CSRF"
request POST "/admin/orders/$PICKUP_ORDER_ID/transition" 200 '{"status":"picking"}' "$TOKEN" "$CSRF"
request POST "/admin/orders/$PICKUP_ORDER_ID/transition" 200 '{"status":"packed"}' "$TOKEN" "$CSRF"
request POST "/admin/orders/$PICKUP_ORDER_ID/transition" 200 '{"status":"ready_pickup"}' "$TOKEN" "$CSRF"
request GET /admin/reports/overview 200 "" "$TOKEN"
assert_json pickup-handoff-waiting
request POST "/admin/orders/$PICKUP_ORDER_ID/assignments" 201 '{"assignment_type":"pickup","assignee_user_id":'$WORKER_ID'}' "$TOKEN" "$CSRF"
PICKUP_ASSIGNMENT_ID="$(assert_json created-id)"
request GET /admin/reports/overview 200 "" "$TOKEN"
assert_json pickup-handoff-assigned
request POST "/admin/orders/$PICKUP_ORDER_ID/transition" 409 '{"status":"delivered"}' "$TOKEN" "$CSRF"
request GET /me/tasks 200 "" "$WORKER_TOKEN"
assert_json worker-pickup-task
request POST "/admin/orders/$PICKUP_ORDER_ID/assignments/$PICKUP_ASSIGNMENT_ID/complete" 409 '{}' "$TOKEN" "$CSRF"
request POST "/me/tasks/$PICKUP_ASSIGNMENT_ID/complete" 404 '{}' "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
request POST "/me/tasks/$PICKUP_ASSIGNMENT_ID/complete" 422 '{}' "$WORKER_TOKEN" "$WORKER_CSRF"
request POST "/me/tasks/$PICKUP_ASSIGNMENT_ID/complete" 200 '{"recipient_name":"مشتری دریافت حضوری","note":"تحویل با تأیید نام"}' "$WORKER_TOKEN" "$WORKER_CSRF"
PICKUP_HANDOFF_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM orders WHERE tenant_id=1 AND id=$PICKUP_ORDER_ID")"
PICKUP_ASSIGNMENT_STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM order_assignments WHERE tenant_id=1 AND id=$PICKUP_ASSIGNMENT_ID")"
PICKUP_AUDIT_RECIPIENT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT JSON_UNQUOTE(JSON_EXTRACT(details,'$.recipient_name')) FROM audit_logs WHERE tenant_id=1 AND action='staff.task.completed' AND entity_type='order' AND entity_id=$PICKUP_ORDER_ID ORDER BY id DESC LIMIT 1")"
if [[ "$PICKUP_HANDOFF_STATUS" != "delivered" || "$PICKUP_ASSIGNMENT_STATUS" != "completed" || "$PICKUP_AUDIT_RECIPIENT" != "مشتری دریافت حضوری" ]]; then echo "Pickup handoff was not finalized with staff confirmation and recipient audit."; exit 1; fi
request GET "/me/orders/$PICKUP_ORDER_ID" 200 "" "$CUSTOMER_TOKEN"
assert_json pickup-order-delivered
request POST "/me/orders/$PICKUP_ORDER_ID/cancel" 409 "" "$CUSTOMER_TOKEN" "$CUSTOMER_CSRF"
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
if [[ "$RESERVED" != "0" || "$ORDER_STATUS" != "paid" ]]; then echo "Wallet payment invariant failed: reserved=$RESERVED order_status=$ORDER_STATUS"; exit 1; fi

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
if [[ "$TOTAL_STOCK" != "8" ]]; then echo "Expected 8 total units after wallet and pickup sales, receipt, adjustment and transfer; received $TOTAL_STOCK"; exit 1; fi

# Cashier POS: isolated permissions, shift lifecycle, cash sale, idempotency and stock posting.
request GET /admin/pos/reports 200 "" "$TOKEN"
request GET /admin/pos/reports 403 "" "$CUSTOMER_TOKEN"

request GET /admin/pos/shift 403 "" "$CUSTOMER_TOKEN"
request POST /admin/pos/shifts 419 '{"warehouse_id":1,"opening_float_rial":500000}' "$POS_TOKEN"
request GET /admin/pos/warehouses 200 "" "$POS_TOKEN"
request GET /admin/pos/shift 200 "" "$POS_TOKEN"
assert_json pos-empty-shift
request POST /admin/pos/shifts 201 '{"warehouse_id":1,"opening_float_rial":500000,"note":"شروع تست صندوق"}' "$POS_TOKEN" "$POS_CSRF"
POS_SHIFT_ID="$(assert_json created-id)"
request GET /admin/pos/shift 200 "" "$POS_TOKEN"
assert_json pos-shift-open
POS_IDEMPOTENCY_KEY="ci-pos-sale-00000001"
request POST /admin/pos/sales 201 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":1}],"cash_received_rial":150000,"idempotency_key":"'$POS_IDEMPOTENCY_KEY'"}' "$POS_TOKEN" "$POS_CSRF"
POS_SALE_ID="$(assert_json pos-sale)"
request POST /admin/pos/sales 200 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":1}],"cash_received_rial":150000,"idempotency_key":"'$POS_IDEMPOTENCY_KEY'"}' "$POS_TOKEN" "$POS_CSRF"
assert_json pos-sale-idempotent
request GET /admin/pos/sales 200 "" "$POS_TOKEN"
assert_json pos-sales-list
request GET "/admin/pos/sales/$POS_SALE_ID" 200 "" "$POS_TOKEN"
assert_json pos-sale-detail
request GET "/admin/pos/sales/$POS_SALE_ID" 403 "" "$CUSTOMER_TOKEN"
request POST /admin/pos/sales 409 '{"items":[{"product_id":'$PRODUCT_ID',"quantity":1}],"cash_received_rial":160000,"idempotency_key":"'$POS_IDEMPOTENCY_KEY'"}' "$POS_TOKEN" "$POS_CSRF"
request POST "/admin/pos/sales/$POS_SALE_ID/refunds" 403 '{"reason":"بازپرداخت آزمایشی معتبر","idempotency_key":"pos-refund-noauth-01","items":[]}' "$POS_TOKEN" "$POS_CSRF"
request POST /admin/pos/shifts 201 '{"warehouse_id":1,"opening_float_rial":200000,"note":"صندوق مدیر برای تست بازپرداخت"}' "$POS_MANAGER_TOKEN" "$POS_MANAGER_CSRF"
OWNER_REFUND_SHIFT_ID="$(assert_json created-id)"
request GET /admin/pos/sales 200 "" "$POS_MANAGER_TOKEN"
assert_json pos-sales-list
POS_SALE_ITEM_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT id FROM pos_sale_items WHERE tenant_id=1 AND sale_id=$POS_SALE_ID ORDER BY id LIMIT 1")"
request POST "/admin/pos/sales/$POS_SALE_ID/refunds" 201 '{"reason":"مرجوعی کالای بازنشده توسط مشتری","idempotency_key":"ci-pos-refund-00001","items":[{"sale_item_id":'$POS_SALE_ITEM_ID',"quantity":1}]}' "$POS_MANAGER_TOKEN" "$POS_MANAGER_CSRF"
POS_REFUND_ID="$(assert_json created-id)"
request POST "/admin/pos/sales/$POS_SALE_ID/refunds" 200 '{"reason":"مرجوعی کالای بازنشده توسط مشتری","idempotency_key":"ci-pos-refund-00001","items":[{"sale_item_id":'$POS_SALE_ITEM_ID',"quantity":1}]}' "$POS_MANAGER_TOKEN" "$POS_MANAGER_CSRF"
request POST "/admin/pos/sales/$POS_SALE_ID/refunds" 409 '{"reason":"مرجوعی کالای بازنشده توسط مشتری","idempotency_key":"ci-pos-refund-00002","items":[{"sale_item_id":'$POS_SALE_ITEM_ID',"quantity":1}]}' "$POS_MANAGER_TOKEN" "$POS_MANAGER_CSRF"
request POST "/admin/pos/shifts/$OWNER_REFUND_SHIFT_ID/close" 200 '{"counted_cash_rial":75000,"note":"تطبیق پس از بازپرداخت"}' "$POS_MANAGER_TOKEN" "$POS_MANAGER_CSRF"
request POST "/admin/pos/sales/$POS_SALE_ID/refunds" 200 '{"reason":"مرجوعی کالای بازنشده توسط مشتری","idempotency_key":"ci-pos-refund-00001","items":[{"sale_item_id":'$POS_SALE_ITEM_ID',"quantity":1}]}' "$POS_MANAGER_TOKEN" "$POS_MANAGER_CSRF"
assert_json pos-refund-idempotent
POS_REFUND_CHECK="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM pos_refunds r JOIN pos_refund_items i ON i.tenant_id=r.tenant_id AND i.refund_id=r.id WHERE r.tenant_id=1 AND r.id=$POS_REFUND_ID AND r.stock_status='pending_inspection' AND i.stock_status='pending_inspection' AND r.amount_rial=125000")"
if [[ "$POS_REFUND_CHECK" != "1" ]]; then echo "POS refund ledger or inspection hold was not recorded."; exit 1; fi
request GET "/admin/pos/sales/$POS_SALE_ID" 200 "" "$POS_MANAGER_TOKEN"
assert_json pos-sale-detail-refunded
request GET /admin/pos/reports 200 "" "$TOKEN"
assert_json pos-report-refunds

request POST "/admin/pos/shifts/$POS_SHIFT_ID/close" 200 '{"counted_cash_rial":625000,"note":"تطبیق کامل صندوق"}' "$POS_TOKEN" "$POS_CSRF"
assert_json pos-shift-closed
request POST "/admin/pos/shifts/$POS_SHIFT_ID/close" 409 '{"counted_cash_rial":625000}' "$POS_TOKEN" "$POS_CSRF"
POS_SALE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM pos_sales WHERE tenant_id=1 AND id=$POS_SALE_ID AND request_hash IS NOT NULL")"
POS_MOVEMENT_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM stock_movements WHERE tenant_id=1 AND reference_type='pos_sale' AND reference_id=$POS_SALE_ID AND movement_type='sale' AND quantity=-1")"
if [[ "$POS_SALE_COUNT" != "1" || "$POS_MOVEMENT_COUNT" != "1" ]]; then echo "POS sale ledger or stock movement was not recorded exactly once."; exit 1; fi

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
