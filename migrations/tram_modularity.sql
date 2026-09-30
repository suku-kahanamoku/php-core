-- Shared outbound quotas. Scope keys are hashes of non-secret server configuration.
-- Additive/idempotent; never store tokens, request URLs, payloads or positions.
CREATE TABLE IF NOT EXISTS transport_provider_quota (
  scope_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  policy_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  next_request_at datetime(6) DEFAULT NULL,
  blocked_until datetime(6) DEFAULT NULL,
  last_used_at datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS transport_provider_quota_usage (
  id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  scope_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  requested_at datetime(6) NOT NULL,
  KEY idx_transport_quota_window (scope_key, requested_at)
) ENGINE=InnoDB;
