-- Customer OTP challenges, rate limits and opaque database-backed sessions.
-- Apply after the tenant-isolation migration. Safe for a fresh schema.

CREATE TABLE auth_challenges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  purpose ENUM('login') NOT NULL DEFAULT 'login',
  otp_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  request_ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_auth_challenge_lookup (tenant_id, mobile, purpose, id),
  KEY ix_auth_challenge_expiry (expires_at, consumed_at),
  CONSTRAINT fk_auth_challenges_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_rate_limits (
  tenant_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(40) NOT NULL,
  bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  window_started_at DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, action, bucket_hash),
  CONSTRAINT fk_auth_rate_limits_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  csrf_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME NOT NULL,
  last_seen_at DATETIME NULL,
  revoked_at DATETIME NULL,
  ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  user_agent_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_auth_session_token (token_hash),
  KEY ix_auth_sessions_user (tenant_id, user_id, revoked_at, expires_at),
  KEY ix_auth_sessions_expiry (expires_at, revoked_at),
  CONSTRAINT fk_auth_sessions_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_auth_sessions_user_same_tenant
    FOREIGN KEY (tenant_id, user_id) REFERENCES users(tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
