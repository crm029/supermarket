ALTER TABLE products
  ADD COLUMN seo_slug VARCHAR(180) NULL AFTER seo_title,
  ADD UNIQUE KEY uq_product_seo_slug (tenant_id, seo_slug);
