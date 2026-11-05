-- Keep historical order address references intact while allowing customers to retire addresses.
ALTER TABLE customer_addresses
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER longitude,
  ADD KEY ix_customer_address_owner_active (tenant_id,user_id,is_active,id);
