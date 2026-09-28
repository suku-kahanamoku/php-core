-- One durable background request per tenant; per-job history remains in etymolog_sync_run.
CREATE TABLE IF NOT EXISTS etymolog_sync_batch (
  franchise_code VARCHAR(64) NOT NULL PRIMARY KEY,
  request_id CHAR(32) NOT NULL,
  status VARCHAR(20) NOT NULL,
  requested_by INT UNSIGNED NULL,
  total INT UNSIGNED NOT NULL DEFAULT 0,
  completed INT UNSIGNED NOT NULL DEFAULT 0,
  failed INT UNSIGNED NOT NULL DEFAULT 0,
  processed INT UNSIGNED NOT NULL DEFAULT 0,
  error_code VARCHAR(100) NULL,
  created_at DATETIME NOT NULL,
  heartbeat_at DATETIME NOT NULL,
  finished_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
