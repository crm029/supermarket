-- Short-lived DNS TXT ownership challenges for newly provisioned tenant domains.
ALTER TABLE tenant_domains
  ADD COLUMN verification_token_hash CHAR(64) NULL,
  ADD COLUMN verification_expires_at DATETIME NULL,
  ADD COLUMN verification_attempts INT UNSIGNED NOT NULL DEFAULT 0;
