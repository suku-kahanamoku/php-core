-- Durable HTTP-step cooldown and bounded source retries; preserves all import cursors.
SET @ety_sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='retry_at')=0,
 'ALTER TABLE etymolog_sync_batch ADD COLUMN retry_at DATETIME NULL','SELECT 1');
PREPARE ety_stmt FROM @ety_sql; EXECUTE ety_stmt; DEALLOCATE PREPARE ety_stmt;
SET @ety_sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='retry_count')=0,
 'ALTER TABLE etymolog_sync_batch ADD COLUMN retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0','SELECT 1');
PREPARE ety_stmt FROM @ety_sql; EXECUTE ety_stmt; DEALLOCATE PREPARE ety_stmt;
