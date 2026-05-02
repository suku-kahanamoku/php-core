-- ============================================================
-- php-core database schema
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── enumeration (ciselnik) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `enumeration` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`       VARCHAR(64)  NOT NULL COMMENT 'e.g. order_status, invoice_status, user_role',
    `code`       VARCHAR(64)  NOT NULL,
    `label`      VARCHAR(255) NOT NULL,
    `value`      VARCHAR(255) NOT NULL DEFAULT '',
    `sort_order` SMALLINT     NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_enum_type_code` (`type`, `code`),
    KEY `idx_enum_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── user ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name`    VARCHAR(100) NOT NULL,
    `last_name`     VARCHAR(100) NOT NULL,
    `email`         VARCHAR(255) NOT NULL,
    `phone`         VARCHAR(30)           DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role`          VARCHAR(32)  NOT NULL DEFAULT 'user' COMMENT 'admin | user | manager',
    `status`        VARCHAR(32)  NOT NULL DEFAULT 'active' COMMENT 'active | inactive | deleted',
    `address_id`    INT UNSIGNED          DEFAULT NULL COMMENT 'default billing address',
    `last_login_at` DATETIME              DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME              DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_email` (`email`),
    KEY `idx_user_status` (`status`),
    KEY `idx_user_role`   (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── address ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `address` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `type`       VARCHAR(20)  NOT NULL DEFAULT 'billing' COMMENT 'billing | shipping',
    `company`    VARCHAR(255)          DEFAULT NULL,
    `first_name` VARCHAR(100) NOT NULL DEFAULT '',
    `last_name`  VARCHAR(100) NOT NULL DEFAULT '',
    `street`     VARCHAR(255) NOT NULL,
    `city`       VARCHAR(100) NOT NULL,
    `zip`        VARCHAR(20)  NOT NULL,
    `country`    VARCHAR(3)   NOT NULL DEFAULT 'CZ',
    `is_default` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_addr_user` (`user_id`),
    CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FK for user.address_id after address table exists
ALTER TABLE `user`
    ADD CONSTRAINT `fk_user_address`
    FOREIGN KEY (`address_id`) REFERENCES `address` (`id`) ON DELETE SET NULL;

-- ── user_token ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_token` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token`      VARCHAR(64)  NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`token`),
    KEY `idx_token_user` (`user_id`),
    CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── category ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `category` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id`   INT UNSIGNED          DEFAULT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `slug`        VARCHAR(255) NOT NULL,
    `description` TEXT                  DEFAULT NULL,
    `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cat_slug` (`slug`),
    KEY `idx_cat_parent` (`parent_id`),
    CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── product ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `category_id`    INT UNSIGNED            DEFAULT NULL,
    `sku`            VARCHAR(64)    NOT NULL,
    `name`           VARCHAR(255)   NOT NULL,
    `description`    TEXT                    DEFAULT NULL,
    `price`          DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `vat_rate`       DECIMAL(5, 2)  NOT NULL DEFAULT 21.00 COMMENT 'VAT percentage',
    `stock_quantity` INT            NOT NULL DEFAULT 0,
    `status`         VARCHAR(32)    NOT NULL DEFAULT 'active' COMMENT 'active | inactive | archived',
    `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME                DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_sku` (`sku`),
    KEY `idx_product_cat`    (`category_id`),
    KEY `idx_product_status` (`status`),
    CONSTRAINT `fk_product_cat` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── text (CMS content blocks) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `text` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(128) NOT NULL,
    `title`      VARCHAR(255) NOT NULL,
    `content`    LONGTEXT              DEFAULT NULL,
    `language`   VARCHAR(10)  NOT NULL DEFAULT 'cs',
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED          DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_text_key_lang` (`key`, `language`),
    KEY `idx_text_lang` (`language`),
    CONSTRAINT `fk_text_user` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── order ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order` (
    `id`                  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `order_number`        VARCHAR(64)    NOT NULL,
    `user_id`             INT UNSIGNED            DEFAULT NULL,
    `status`              VARCHAR(32)    NOT NULL DEFAULT 'pending'
                              COMMENT 'pending | confirmed | processing | shipped | delivered | cancelled | refunded',
    `total_amount`        DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `currency`            VARCHAR(3)     NOT NULL DEFAULT 'CZK',
    `payment_method`      VARCHAR(64)             DEFAULT 'bank_transfer',
    `shipping_address_id` INT UNSIGNED            DEFAULT NULL,
    `billing_address_id`  INT UNSIGNED            DEFAULT NULL,
    `note`                TEXT                    DEFAULT NULL,
    `created_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`          DATETIME                DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_order_number` (`order_number`),
    KEY `idx_order_user`   (`user_id`),
    KEY `idx_order_status` (`status`),
    CONSTRAINT `fk_order_user`     FOREIGN KEY (`user_id`)             REFERENCES `user`    (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_order_ship`     FOREIGN KEY (`shipping_address_id`) REFERENCES `address` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_order_bill`     FOREIGN KEY (`billing_address_id`)  REFERENCES `address` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── order_item ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_item` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `order_id`    INT UNSIGNED   NOT NULL,
    `product_id`  INT UNSIGNED            DEFAULT NULL,
    `quantity`    INT            NOT NULL DEFAULT 1,
    `unit_price`  DECIMAL(12, 2) NOT NULL,
    `total_price` DECIMAL(12, 2) NOT NULL,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_oi_order`   (`order_id`),
    KEY `idx_oi_product` (`product_id`),
    CONSTRAINT `fk_oi_order`   FOREIGN KEY (`order_id`)   REFERENCES `order`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── invoice ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invoice` (
    `id`                 INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `invoice_number`     VARCHAR(64)    NOT NULL,
    `order_id`           INT UNSIGNED            DEFAULT NULL,
    `user_id`            INT UNSIGNED            DEFAULT NULL,
    `status`             VARCHAR(32)    NOT NULL DEFAULT 'issued'
                             COMMENT 'draft | issued | paid | overdue | cancelled | refunded',
    `total_amount`       DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `currency`           VARCHAR(3)     NOT NULL DEFAULT 'CZK',
    `billing_address_id` INT UNSIGNED            DEFAULT NULL,
    `note`               TEXT                    DEFAULT NULL,
    `issued_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `due_at`             DATE                    DEFAULT NULL,
    `paid_at`            DATETIME                DEFAULT NULL,
    `created_at`         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`         DATETIME                DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invoice_number` (`invoice_number`),
    KEY `idx_inv_order`  (`order_id`),
    KEY `idx_inv_user`   (`user_id`),
    KEY `idx_inv_status` (`status`),
    CONSTRAINT `fk_inv_order` FOREIGN KEY (`order_id`)           REFERENCES `order`   (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_inv_user`  FOREIGN KEY (`user_id`)            REFERENCES `user`    (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_inv_addr`  FOREIGN KEY (`billing_address_id`) REFERENCES `address` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── invoice_item ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invoice_item` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `invoice_id`  INT UNSIGNED   NOT NULL,
    `product_id`  INT UNSIGNED            DEFAULT NULL,
    `description` VARCHAR(255)   NOT NULL DEFAULT '',
    `quantity`    INT            NOT NULL DEFAULT 1,
    `unit_price`  DECIMAL(12, 2) NOT NULL,
    `total_price` DECIMAL(12, 2) NOT NULL,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ii_invoice` (`invoice_id`),
    KEY `idx_ii_product` (`product_id`),
    CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ii_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ── Seed: default enumerations ────────────────────────────
INSERT INTO `enumeration` (`type`, `code`, `label`, `value`, `sort_order`) VALUES
  -- Order statuses
  ('order_status', 'pending',    'Pending',    'pending',    10),
  ('order_status', 'confirmed',  'Confirmed',  'confirmed',  20),
  ('order_status', 'processing', 'Processing', 'processing', 30),
  ('order_status', 'shipped',    'Shipped',    'shipped',    40),
  ('order_status', 'delivered',  'Delivered',  'delivered',  50),
  ('order_status', 'cancelled',  'Cancelled',  'cancelled',  60),
  ('order_status', 'refunded',   'Refunded',   'refunded',   70),
  -- Invoice statuses
  ('invoice_status', 'draft',     'Draft',     'draft',     10),
  ('invoice_status', 'issued',    'Issued',    'issued',    20),
  ('invoice_status', 'paid',      'Paid',      'paid',      30),
  ('invoice_status', 'overdue',   'Overdue',   'overdue',   40),
  ('invoice_status', 'cancelled', 'Cancelled', 'cancelled', 50),
  ('invoice_status', 'refunded',  'Refunded',  'refunded',  60),
  -- User roles
  ('user_role', 'admin',   'Admin',   'admin',   10),
  ('user_role', 'manager', 'Manager', 'manager', 20),
  ('user_role', 'user',    'User',    'user',    30),
  -- Payment methods
  ('payment_method', 'bank_transfer', 'Bank Transfer', 'bank_transfer', 10),
  ('payment_method', 'cash',          'Cash',          'cash',          20),
  ('payment_method', 'card',          'Card',          'card',          30),
  ('payment_method', 'online',        'Online',        'online',        40),
  -- Currencies
  ('currency', 'CZK', 'Czech Koruna', 'CZK', 10),
  ('currency', 'EUR', 'Euro',         'EUR', 20),
  ('currency', 'USD', 'US Dollar',    'USD', 30),
  -- VAT rates
  ('vat_rate', '0',  '0%',  '0',  10),
  ('vat_rate', '10', '10%', '10', 20),
  ('vat_rate', '12', '12%', '12', 30),
  ('vat_rate', '21', '21%', '21', 40);

-- ── Seed: admin user (password: Admin1234!) ───────────────
INSERT INTO `user` (`first_name`, `last_name`, `email`, `password`, `role`, `status`) VALUES
  ('Admin', 'User', 'admin@example.com',
   '$2y$12$ubNeYmIWTWs4hXG6OQWQdO5rRStAzqrHM1C/xxU9H7vZx0LvMKI5q', -- password: password
   'admin', 'active');
