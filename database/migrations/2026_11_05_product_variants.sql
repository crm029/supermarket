-- Product variant groups. Each sellable variant remains a product row,
-- so existing stock, reservations, barcode and order-line flows stay intact.
ALTER TABLE products
  ADD COLUMN product_kind ENUM('simple','variant_parent','variant') NOT NULL DEFAULT 'simple' AFTER is_weighted,
  ADD COLUMN parent_product_id BIGINT UNSIGNED NULL AFTER product_kind,
  ADD COLUMN variant_label VARCHAR(120) NULL AFTER parent_product_id,
  ADD KEY ix_products_parent (tenant_id,parent_product_id,is_active),
  ADD CONSTRAINT fk_products_parent_same_tenant
    FOREIGN KEY (tenant_id,parent_product_id) REFERENCES products (tenant_id,id),
  ADD CONSTRAINT chk_product_variant_shape CHECK (
    (product_kind='variant' AND parent_product_id IS NOT NULL AND variant_label IS NOT NULL AND variant_label<>'')
    OR (product_kind<>'variant' AND parent_product_id IS NULL AND variant_label IS NULL)
  );
