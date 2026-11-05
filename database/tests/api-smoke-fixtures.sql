-- Disposable HTTP API smoke fixtures. Run after all schema migrations/tests.
SELECT id INTO @api_user_one FROM users WHERE tenant_id = 1 AND mobile = '09120000001' LIMIT 1;
SELECT id INTO @api_user_two FROM users WHERE tenant_id = 2 AND mobile = '09120000002' LIMIT 1;

INSERT INTO tenant_domains (tenant_id, host, is_primary, verified_at)
VALUES (2, 'tenant-two.localhost', TRUE, UTC_TIMESTAMP());

INSERT INTO media_assets
  (tenant_id,media_type,storage_key,alt_text,width,height)
VALUES
  (1,'product','products/ci milk bottle.jpg','تصویر تست شیر',600,600);
SET @api_media_id = LAST_INSERT_ID();

INSERT INTO categories
  (tenant_id,title,slug,image_media_id,sort_order,is_active)
VALUES
  (1,'دسته تست API','api-smoke-category',@api_media_id,90,TRUE);
SET @api_category_id = LAST_INSERT_ID();

INSERT INTO products
  (tenant_id,category_id,sku,title,brand,description,unit_label,price_rial,
   compare_at_rial,image_media_id,is_weighted,is_active)
VALUES
  (1,@api_category_id,'API-SMOKE-001','محصول تست API','برند تست',
   'شرح محصول تست','عدد',125000,150000,@api_media_id,FALSE,TRUE);
SET @api_product_id = LAST_INSERT_ID();

INSERT INTO store_scenes
  (tenant_id,title,scene_type,media_id,fallback_media_id,sort_order,is_published,metadata)
VALUES
  (1,'راهروی تست API','aisle_photo',@api_media_id,@api_media_id,90,TRUE,JSON_OBJECT('camera','front'));
SET @api_scene_id = LAST_INSERT_ID();

INSERT INTO scene_hotspots
  (tenant_id,scene_id,product_id,label,x,y,action,payload)
VALUES
  (1,@api_scene_id,@api_product_id,'مشاهده محصول',0.50000,0.45000,'product',NULL);

INSERT INTO scene_hotspots
  (tenant_id,scene_id,label,x,y,action,payload)
VALUES
  (1,@api_scene_id,'رفتن به دسته',0.70000,0.50000,'category',JSON_OBJECT('category_id',@api_category_id));

INSERT INTO auth_sessions
  (tenant_id,user_id,token_hash,csrf_hash,expires_at)
VALUES
  (1,@api_user_one,SHA2(REPEAT('S',43),256),SHA2(REPEAT('C',43),256),DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY)),
  (2,@api_user_two,SHA2(REPEAT('T',43),256),SHA2(REPEAT('D',43),256),DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY));

SELECT 'API smoke fixtures created' AS result;

-- Customer account and stock for end-to-end commerce smoke coverage.
INSERT INTO users (tenant_id,full_name,mobile,status,referral_code)
VALUES (1,'مشتری تست API','09120000003','active','API-CUSTOMER');
SET @api_customer_id = LAST_INSERT_ID();
INSERT INTO auth_sessions (tenant_id,user_id,token_hash,csrf_hash,expires_at)
VALUES (1,@api_customer_id,SHA2(REPEAT('U',43),256),SHA2(REPEAT('W',43),256),DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY));
INSERT INTO stock_levels (tenant_id,warehouse_id,product_id,on_hand,reserved)
SELECT 1,w.id,@api_product_id,10,0 FROM warehouses w
WHERE w.tenant_id=1 AND w.is_active=1 ORDER BY w.id LIMIT 1;
