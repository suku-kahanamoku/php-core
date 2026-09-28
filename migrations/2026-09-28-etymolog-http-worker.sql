-- Additive HTTP worker state; no imports or scheduled requests are created.
SET @ety_sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='pending_jobs')=0,
 'ALTER TABLE etymolog_sync_batch ADD COLUMN pending_jobs JSON NULL','SELECT 1');
PREPARE ety_stmt FROM @ety_sql; EXECUTE ety_stmt; DEALLOCATE PREPARE ety_stmt;
CREATE TABLE IF NOT EXISTS etymolog_sync_schedule (
 franchise_code VARCHAR(64) NOT NULL,
 scheduled_date DATE NOT NULL,
 request_id CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(franchise_code,scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
