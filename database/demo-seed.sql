-- Optional local-only seed for a fresh schema. Do not use this as production merchant data.
INSERT INTO tenants (id,name,slug,status,timezone,currency)
VALUES (1,'سوپرمارکت نمونه','sample-market','active','Asia/Tehran','IRR');

INSERT INTO tenant_domains (tenant_id,host,is_primary,verified_at)
VALUES (1,'localhost',TRUE,CURRENT_TIMESTAMP);

INSERT INTO categories (id,tenant_id,parent_id,title,slug,sort_order,is_active) VALUES
(1,1,NULL,'لبنیات و صبحانه','dairy',1,TRUE),
(2,1,NULL,'خواربار و مواد غذایی','grocery',2,TRUE),
(3,1,NULL,'تنقلات و خوراکی','snacks',3,TRUE),
(4,1,NULL,'نوشیدنی‌ها','drinks',4,TRUE),
(5,1,NULL,'شوینده و بهداشتی','cleaning',5,TRUE),
(6,1,NULL,'میوه و سبزیجات','fruit',6,TRUE),
(7,1,NULL,'مواد غذایی منجمد','frozen',7,TRUE);

INSERT INTO products (tenant_id,category_id,sku,title,brand,description,unit_label,price_rial,is_active) VALUES
(1,1,'DEMO-DAIRY-001','شیر پرچرب یک لیتری','برند نمونه','کالای نمایشی برای تست کاتالوگ.','بطری ۱ لیتری',420000,TRUE),
(1,1,'DEMO-DAIRY-002','پنیر صبحانه','برند نمونه','کالای نمایشی برای تست کاتالوگ.','بسته ۴۰۰ گرمی',685000,TRUE),
(1,2,'DEMO-GROCERY-001','برنج ایرانی','محصول نمونه','کالای نمایشی برای تست کاتالوگ.','کیسه ۵ کیلویی',8900000,TRUE),
(1,3,'DEMO-SNACK-001','بیسکویت کرمدار','برند نمونه','کالای نمایشی برای تست کاتالوگ.','بسته',390000,TRUE),
(1,4,'DEMO-DRINK-001','آبمیوه','برند نمونه','کالای نمایشی برای تست کاتالوگ.','بطری ۱ لیتری',560000,TRUE),
(1,5,'DEMO-CLEAN-001','مایع ظرفشویی','برند نمونه','کالای نمایشی برای تست کاتالوگ.','بطری',740000,TRUE),
(1,6,'DEMO-FRUIT-001','سبزیجات تازه','محصول روز','کالای نمایشی برای تست کاتالوگ.','بسته',350000,TRUE),
(1,7,'DEMO-FROZEN-001','بستنی وانیلی','برند نمونه','کالای نمایشی برای تست کاتالوگ.','بسته',950000,TRUE);

INSERT INTO warehouses (tenant_id,title,warehouse_type,is_active)
VALUES (1,'انبار اصلی نمونه','main',TRUE);
