<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';

function app_admin_product_payload(array $body, bool $partial = false): array {
    $data = [];

    if (!$partial || array_key_exists('sku', $body)) {
        $sku = strtoupper(trim((string)($body['sku'] ?? '')));
        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,79}$/', $sku)) {
            app_json(['error' => 'کد کالا معتبر نیست.'], 422);
        }
        $data['sku'] = $sku;
    }

    if (!$partial || array_key_exists('title', $body)) {
        $title = trim((string)($body['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180) {
            app_json(['error' => 'نام کالا الزامی است و حداکثر ۱۸۰ نویسه دارد.'], 422);
        }
        $data['title'] = $title;
    }

    if (!$partial || array_key_exists('price_rial', $body)) {
        $priceRaw = $body['price_rial'] ?? null;
        $price = filter_var($priceRaw, FILTER_VALIDATE_INT);
        if ($price === false || $price < 0) {
            app_json(['error' => 'قیمت باید عدد صحیح و غیرمنفی به ریال باشد.'], 422);
        }
        $data['price_rial'] = $price;
    }

    foreach (['category_id', 'compare_at_rial'] as $field) {
        if (!$partial || array_key_exists($field, $body)) {
            $raw = $body[$field] ?? null;
            if ($raw === null || $raw === '') {
                $data[$field] = null;
            } else {
                $value = filter_var($raw, FILTER_VALIDATE_INT);
                if ($value === false || $value < 0 || ($field === 'category_id' && $value === 0)) {
                    app_json(['error' => 'مقدار یکی از فیلدهای عددی معتبر نیست.'], 422);
                }
                $data[$field] = $value;
            }
        }
    }

    foreach (['barcode' => 80, 'brand' => 120, 'unit_label' => 80, 'description' => 10000] as $field => $max) {
        if (!$partial || array_key_exists($field, $body)) {
            $value = trim((string)($body[$field] ?? ''));
            if (mb_strlen($value) > $max) {
                app_json(['error' => 'طول یکی از مشخصات کالا بیش از حد مجاز است.'], 422);
            }
            $data[$field] = $value === '' ? null : $value;
        }
    }

    if (!$partial || array_key_exists('low_stock_threshold', $body)) {
        $threshold = filter_var($body['low_stock_threshold'] ?? 5, FILTER_VALIDATE_INT);
        if ($threshold === false || $threshold < 0 || $threshold > 1000000) {
            app_json(['error' => 'حد هشدار موجودی باید عددی بین صفر تا یک میلیون باشد.'], 422);
        }
        $data['low_stock_threshold'] = $threshold;
    }

    foreach (['is_weighted', 'is_active'] as $field) {
        if (!$partial || array_key_exists($field, $body)) {
            $value = $body[$field] ?? ($field === 'is_active');
            if (!is_bool($value)) {
                app_json(['error' => 'وضعیت کالا باید مقدار درست یا نادرست باشد.'], 422);
            }
            $data[$field] = $value ? 1 : 0;
        }
    }

    if (isset($data['compare_at_rial'], $data['price_rial']) &&
        $data['compare_at_rial'] !== null && $data['compare_at_rial'] < $data['price_rial']) {
        app_json(['error' => 'قیمت قبل از تخفیف نمی‌تواند از قیمت فعلی کمتر باشد.'], 422);
    }

    return $data;
}

function app_admin_validate_product_category(PDO $db, int $tenantId, array $data): void {
    if (!array_key_exists('category_id', $data) || $data['category_id'] === null) return;
    $stmt = $db->prepare('SELECT id FROM categories WHERE tenant_id = ? AND id = ? AND is_active = 1 LIMIT 1 FOR UPDATE');
    $stmt->execute([$tenantId, $data['category_id']]);
    if (!$stmt->fetchColumn()) {
        app_json(['error' => 'دسته‌بندی انتخاب‌شده متعلق به این فروشگاه نیست.'], 422);
    }
}

function app_admin_product_list(PDO $db, array $tenant): never {
    $query = trim((string)($_GET['q'] ?? ''));
    $category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
    $page = max(1, min(1000, (int)($_GET['page'] ?? 1)));
    $limit = 50;
    $where = ['tenant_id = :tenant'];
    $params = ['tenant' => $tenant['id']];

    if ($query !== '') {
        $where[] = '(title LIKE :q_title OR sku LIKE :q_sku OR barcode LIKE :q_barcode)';
        $search = '%' . mb_substr($query, 0, 80) . '%';
        $params['q_title'] = $search;
        $params['q_sku'] = $search;
        $params['q_barcode'] = $search;
    }
    if ($category !== null && $category !== false) {
        $where[] = 'category_id = :category';
        $params['category'] = $category;
    }

    $sqlWhere = implode(' AND ', $where);
    $count = $db->prepare("SELECT COUNT(*) FROM products WHERE {$sqlWhere}");
    $count->execute($params);
    $stmt = $db->prepare(
        "SELECT id, sku, barcode, title, brand, description, unit_label,
                price_rial, compare_at_rial, category_id, is_weighted, is_active, low_stock_threshold
         FROM products WHERE {$sqlWhere}
         ORDER BY id DESC LIMIT {$limit} OFFSET " . (($page - 1) * $limit)
    );
    $stmt->execute($params);
    app_json(['data' => $stmt->fetchAll(), 'meta' => [
        'page' => $page,
        'per_page' => $limit,
        'total' => (int)$count->fetchColumn(),
    ]]);
}

function app_admin_product_create(PDO $db, array $tenant, array $session): never {
    $data = app_admin_product_payload(app_auth_request_body());
    $db->beginTransaction();
    try {
        app_admin_validate_product_category($db, (int)$tenant['id'], $data);
        $stmt = $db->prepare(
            "INSERT INTO products
              (tenant_id, category_id, sku, barcode, title, brand, description,
               unit_label, price_rial, compare_at_rial, is_weighted, is_active, low_stock_threshold)
             VALUES
              (:tenant, :category_id, :sku, :barcode, :title, :brand, :description,
               :unit_label, :price_rial, :compare_at_rial, :is_weighted, :is_active, :low_stock_threshold)"
        );
        $stmt->execute([
            'tenant' => $tenant['id'],
            'category_id' => $data['category_id'],
            'sku' => $data['sku'],
            'barcode' => $data['barcode'],
            'title' => $data['title'],
            'brand' => $data['brand'],
            'description' => $data['description'],
            'unit_label' => $data['unit_label'] ?? 'عدد',
            'price_rial' => $data['price_rial'],
            'compare_at_rial' => $data['compare_at_rial'],
            'is_weighted' => $data['is_weighted'],
            'is_active' => $data['is_active'],
            'low_stock_threshold' => $data['low_stock_threshold'],
        ]);
        $id = (int)$db->lastInsertId();
        app_audit_log($db, (int)$tenant['id'], (int)$session['id'], 'catalog.product.created', 'product', $id, [
            'sku' => $data['sku'],
        ]);
        $db->commit();
        app_json(['data' => ['id' => $id, 'sku' => $data['sku']], 'message' => 'کالا با موفقیت ثبت شد.'], 201);
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e->getCode() === '23000' && (int)($e->errorInfo[1] ?? 0) === 1062) {
            app_json(['error' => 'کد کالا یا بارکد قبلاً در این فروشگاه ثبت شده است.'], 409);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function app_admin_product_update(PDO $db, array $tenant, array $session, int $id): never {
    $data = app_admin_product_payload(app_auth_request_body(), true);
    if ($data === []) app_json(['error' => 'هیچ فیلدی برای ویرایش ارسال نشده است.'], 422);
    $allowed = [
        'sku', 'title', 'price_rial', 'category_id', 'compare_at_rial',
        'barcode', 'brand', 'unit_label', 'description', 'is_weighted', 'is_active', 'low_stock_threshold',
    ];
    $sets = [];
    $params = ['tenant' => $tenant['id'], 'id' => $id];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $sets[] = "{$field} = :{$field}";
            $params[$field] = $data[$field];
        }
    }

    $db->beginTransaction();
    try {
        $exists = $db->prepare('SELECT id, price_rial, compare_at_rial FROM products WHERE tenant_id = ? AND id = ? FOR UPDATE');
        $exists->execute([$tenant['id'], $id]);
        $current = $exists->fetch();
        if (!$current) {
            $db->rollBack();
            app_json(['error' => 'کالا پیدا نشد.'], 404);
        }
        app_admin_validate_product_category($db, (int)$tenant['id'], $data);
        $nextPrice = $data['price_rial'] ?? (int)$current['price_rial'];
        $nextCompare = array_key_exists('compare_at_rial', $data)
            ? $data['compare_at_rial']
            : ($current['compare_at_rial'] === null ? null : (int)$current['compare_at_rial']);
        if ($nextCompare !== null && $nextCompare < $nextPrice) {
            $db->rollBack();
            app_json(['error' => 'قیمت قبل از تخفیف نمی‌تواند از قیمت فعلی کمتر باشد.'], 422);
        }

        $stmt = $db->prepare('UPDATE products SET ' . implode(', ', $sets) . ' WHERE tenant_id = :tenant AND id = :id');
        $stmt->execute($params);
        app_audit_log($db, (int)$tenant['id'], (int)$session['id'], 'catalog.product.updated', 'product', $id, [
            'fields' => array_keys($data),
        ]);
        $db->commit();
        app_json(['message' => 'تغییرات کالا ذخیره شد.']);
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e->getCode() === '23000' && (int)($e->errorInfo[1] ?? 0) === 1062) {
            app_json(['error' => 'کد کالا یا بارکد با کالای دیگری تداخل دارد.'], 409);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function app_admin_product_deactivate(PDO $db, array $tenant, array $session, int $id): never {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE products SET is_active = 0 WHERE tenant_id = ? AND id = ? AND is_active = 1');
        $stmt->execute([$tenant['id'], $id]);
        if ($stmt->rowCount() === 0) {
            $db->rollBack();
            app_json(['error' => 'کالا پیدا نشد یا از قبل غیرفعال است.'], 404);
        }
        app_audit_log($db, (int)$tenant['id'], (int)$session['id'], 'catalog.product.deactivated', 'product', $id);
        $db->commit();
        app_json(['message' => 'کالا غیرفعال شد.']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}


function app_admin_category_payload(array $body, bool $partial = false): array {
    $data = [];

    if (!$partial || array_key_exists('title', $body)) {
        $title = trim((string)($body['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 120) {
            app_json(['error' => 'نام دسته‌بندی الزامی است و حداکثر ۱۲۰ نویسه دارد.'], 422);
        }
        $data['title'] = $title;
    }

    if (!$partial || array_key_exists('slug', $body)) {
        $slug = strtolower(trim((string)($body['slug'] ?? '')));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 140) {
            app_json(['error' => 'شناسه یکتای دسته‌بندی معتبر نیست.'], 422);
        }
        $data['slug'] = $slug;
    }

    foreach (['parent_id', 'image_media_id'] as $field) {
        if (!$partial || array_key_exists($field, $body)) {
            $raw = $body[$field] ?? null;
            if ($raw === null || $raw === '') {
                $data[$field] = null;
            } else {
                $value = filter_var($raw, FILTER_VALIDATE_INT);
                if ($value === false || $value < 1) {
                    app_json(['error' => 'شناسه والد یا تصویر معتبر نیست.'], 422);
                }
                $data[$field] = $value;
            }
        }
    }

    if (!$partial || array_key_exists('sort_order', $body)) {
        $sort = filter_var($body['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        if ($sort === false || $sort < 0 || $sort > 100000) {
            app_json(['error' => 'ترتیب نمایش معتبر نیست.'], 422);
        }
        $data['sort_order'] = $sort;
    }

    if (!$partial || array_key_exists('is_active', $body)) {
        $active = $body['is_active'] ?? true;
        if (!is_bool($active)) app_json(['error' => 'وضعیت دسته‌بندی باید درست یا نادرست باشد.'], 422);
        $data['is_active'] = $active ? 1 : 0;
    }

    return $data;
}

function app_admin_validate_category_links(PDO $db, int $tenantId, array $data, ?int $currentId = null): void {
    if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
        $parentId = (int)$data['parent_id'];
        if ($currentId !== null && $parentId === $currentId) {
            app_json(['error' => 'یک دسته‌بندی نمی‌تواند والد خودش باشد.'], 422);
        }
        $parent = $db->prepare(
            'SELECT id FROM categories WHERE tenant_id = ? AND id = ? AND is_active = 1 LIMIT 1 FOR UPDATE'
        );
        $parent->execute([$tenantId, $parentId]);
        if (!$parent->fetchColumn()) {
            app_json(['error' => 'دسته‌بندی والد فعال و متعلق به این فروشگاه نیست.'], 422);
        }

        if ($currentId !== null) {
            $cycle = $db->prepare(
                "WITH RECURSIVE ancestors (id, parent_id) AS (
                    SELECT id, parent_id FROM categories WHERE tenant_id = ? AND id = ?
                    UNION ALL
                    SELECT c.id, c.parent_id
                    FROM categories c
                    JOIN ancestors a ON c.id = a.parent_id
                    WHERE c.tenant_id = ?
                 )
                 SELECT 1 FROM ancestors WHERE id = ? LIMIT 1"
            );
            $cycle->execute([$tenantId, $parentId, $tenantId, $currentId]);
            if ($cycle->fetchColumn()) {
                app_json(['error' => 'این تغییر باعث ایجاد چرخه در درخت دسته‌بندی می‌شود.'], 422);
            }
        }
    }

    if (array_key_exists('image_media_id', $data) && $data['image_media_id'] !== null) {
        $media = $db->prepare('SELECT id FROM media_assets WHERE tenant_id = ? AND id = ? LIMIT 1 FOR UPDATE');
        $media->execute([$tenantId, $data['image_media_id']]);
        if (!$media->fetchColumn()) {
            app_json(['error' => 'تصویر انتخاب‌شده متعلق به این فروشگاه نیست.'], 422);
        }
    }
}

function app_admin_category_list(PDO $db, array $tenant): never {
    $stmt = $db->prepare(
        "SELECT id, parent_id, title, slug, image_media_id, sort_order, is_active
         FROM categories WHERE tenant_id = ? ORDER BY sort_order, title"
    );
    $stmt->execute([$tenant['id']]);
    app_json(['data' => $stmt->fetchAll()]);
}

function app_admin_category_create(PDO $db, array $tenant, array $session): never {
    $data = app_admin_category_payload(app_auth_request_body());
    $db->beginTransaction();
    try {
        app_admin_validate_category_links($db, (int)$tenant['id'], $data);
        $stmt = $db->prepare(
            "INSERT INTO categories
              (tenant_id, parent_id, title, slug, image_media_id, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $tenant['id'],
            $data['parent_id'],
            $data['title'],
            $data['slug'],
            $data['image_media_id'],
            $data['sort_order'],
            $data['is_active'],
        ]);
        $id = (int)$db->lastInsertId();
        app_audit_log($db, (int)$tenant['id'], (int)$session['id'], 'catalog.category.created', 'category', $id, [
            'slug' => $data['slug'],
        ]);
        $db->commit();
        app_json(['data' => ['id' => $id, 'slug' => $data['slug']], 'message' => 'دسته‌بندی ثبت شد.'], 201);
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e->getCode() === '23000' && (int)($e->errorInfo[1] ?? 0) === 1062) {
            app_json(['error' => 'این شناسه دسته‌بندی قبلاً استفاده شده است.'], 409);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function app_admin_category_update(PDO $db, array $tenant, array $session, int $id): never {
    $data = app_admin_category_payload(app_auth_request_body(), true);
    if ($data === []) app_json(['error' => 'هیچ فیلدی برای ویرایش ارسال نشده است.'], 422);
    $allowed = ['parent_id', 'title', 'slug', 'image_media_id', 'sort_order', 'is_active'];
    $sets = [];
    $params = ['tenant' => $tenant['id'], 'id' => $id];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $sets[] = "{$field} = :{$field}";
            $params[$field] = $data[$field];
        }
    }

    $db->beginTransaction();
    try {
        $exists = $db->prepare('SELECT id FROM categories WHERE tenant_id = ? AND id = ? FOR UPDATE');
        $exists->execute([$tenant['id'], $id]);
        if (!$exists->fetchColumn()) {
            $db->rollBack();
            app_json(['error' => 'دسته‌بندی پیدا نشد.'], 404);
        }
        app_admin_validate_category_links($db, (int)$tenant['id'], $data, $id);
        if (($data['is_active'] ?? 1) === 0) {
            $usage = $db->prepare(
                "SELECT
                   (SELECT COUNT(*) FROM categories WHERE tenant_id = ? AND parent_id = ?) +
                   (SELECT COUNT(*) FROM products WHERE tenant_id = ? AND category_id = ? AND is_active = 1)"
            );
            $usage->execute([$tenant['id'], $id, $tenant['id'], $id]);
            if ((int)$usage->fetchColumn() > 0) {
                $db->rollBack();
                app_json(['error' => 'ابتدا کالاها یا زیر‌دسته‌های این دسته‌بندی را جابه‌جا یا غیرفعال کنید.'], 409);
            }
        }
        $stmt = $db->prepare('UPDATE categories SET ' . implode(', ', $sets) . ' WHERE tenant_id = :tenant AND id = :id');
        $stmt->execute($params);
        app_audit_log($db, (int)$tenant['id'], (int)$session['id'], 'catalog.category.updated', 'category', $id, [
            'fields' => array_keys($data),
        ]);
        $db->commit();
        app_json(['message' => 'دسته‌بندی به‌روزرسانی شد.']);
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e->getCode() === '23000' && (int)($e->errorInfo[1] ?? 0) === 1062) {
            app_json(['error' => 'این شناسه دسته‌بندی قبلاً استفاده شده است.'], 409);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function app_admin_category_deactivate(PDO $db, array $tenant, array $session, int $id): never {
    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT id FROM categories WHERE tenant_id = ? AND id = ? FOR UPDATE');
        $lock->execute([$tenant['id'], $id]);
        if (!$lock->fetchColumn()) {
            $db->rollBack();
            app_json(['error' => 'دسته‌بندی پیدا نشد.'], 404);
        }

        $usage = $db->prepare(
            "SELECT
               (SELECT COUNT(*) FROM categories WHERE tenant_id = ? AND parent_id = ?) +
               (SELECT COUNT(*) FROM products WHERE tenant_id = ? AND category_id = ? AND is_active = 1)"
        );
        $usage->execute([$tenant['id'], $id, $tenant['id'], $id]);
        if ((int)$usage->fetchColumn() > 0) {
            $db->rollBack();
            app_json(['error' => 'ابتدا کالاها یا زیر‌دسته‌های فعال این دسته‌بندی را جابه‌جا یا غیرفعال کنید.'], 409);
        }

        $update = $db->prepare('UPDATE categories SET is_active = 0 WHERE tenant_id = ? AND id = ?');
        $update->execute([$tenant['id'], $id]);
        app_audit_log($db, (int)$tenant['id'], (int)$session['id'], 'catalog.category.deactivated', 'category', $id);
        $db->commit();
        app_json(['message' => 'دسته‌بندی غیرفعال شد.']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
