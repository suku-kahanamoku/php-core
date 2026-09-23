-- Persistent mapping between tenant products and OpenAI Vector Store files.
-- Safe to run repeatedly. Product/catalog data remains the source of truth.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `openai_vector_store` (
    `franchise_code` VARCHAR(64) NOT NULL,
    `vector_store_id` VARCHAR(128) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`franchise_code`),
    UNIQUE KEY `uq_openai_vector_store_id` (`vector_store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `openai_vector_store_product` (
    `franchise_code` VARCHAR(64) NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `vector_store_id` VARCHAR(128) NOT NULL,
    `openai_file_id` VARCHAR(128) NOT NULL,
    `source_hash` CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`franchise_code`, `product_id`),
    UNIQUE KEY `uq_openai_vector_product_file` (`openai_file_id`),
    KEY `idx_openai_vector_product_store` (`vector_store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep cleanup metadata after a hard product delete so the next sync can
-- remove the corresponding remote OpenAI file. Also repairs an early local
-- version of this not-yet-deployed migration that used ON DELETE CASCADE.
SET @drop_vector_product_fk = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'openai_vector_store_product'
          AND CONSTRAINT_NAME = 'fk_openai_vector_product_product'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'ALTER TABLE `openai_vector_store_product` DROP FOREIGN KEY `fk_openai_vector_product_product`',
    'SELECT 1'
);
PREPARE drop_vector_product_fk_statement FROM @drop_vector_product_fk;
EXECUTE drop_vector_product_fk_statement;
DEALLOCATE PREPARE drop_vector_product_fk_statement;

SELECT
  (SELECT COUNT(*) FROM `openai_vector_store`) AS vector_stores,
  (SELECT COUNT(*) FROM `openai_vector_store_product`) AS indexed_products;
