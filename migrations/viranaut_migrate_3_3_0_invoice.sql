-- ViraNaut 3.3.0 — invoice columns (MySQL 8 compatible — no IF NOT EXISTS on ADD COLUMN)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoice' AND COLUMN_NAME = 'code_product');
SET @sql := IF(@col = 0, 'ALTER TABLE invoice ADD COLUMN code_product VARCHAR(128) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoice' AND COLUMN_NAME = 'panel_sync_at');
SET @sql := IF(@col = 0, 'ALTER TABLE invoice ADD COLUMN panel_sync_at VARCHAR(100) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
