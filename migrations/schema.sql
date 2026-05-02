-- ============================================================
-- php-core database schema  (multi-tenant: franchise_code)
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── enumeration (ciselnik) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `enumeration` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL DEFAULT 'default' COMMENT 'multi-tenant project key',
    `type`         VARCHAR(64)  NOT NULL COMMENT 'e.g. order_status, invoice_status, payment_method',
    `code`         VARCHAR(64)  NOT NULL,
    `label`        VARCHAR(255) NOT NULL,
    `value`        VARCHAR(255) NOT NULL DEFAULT '',
    `position`   SMALLINT     NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_enum_franchise_type_code` (`franchise_code`, `type`, `code`),
    KEY `idx_enum_franchise` (`franchise_code`),
    KEY `idx_enum_type`      (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── role ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `role` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL DEFAULT 'default',
    `name`           VARCHAR(64)  NOT NULL COMMENT 'e.g. admin, user, manager',
    `label`          VARCHAR(255) NOT NULL,
    `position`     SMALLINT     NOT NULL DEFAULT 0,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_role_franchise_name` (`franchise_code`, `name`),
    KEY `idx_role_franchise` (`franchise_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── user ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code`  VARCHAR(64)  NOT NULL DEFAULT 'default',
    `first_name`    VARCHAR(100) NOT NULL,
    `last_name`     VARCHAR(100) NOT NULL,
    `email`         VARCHAR(255) NOT NULL,
    `phone`         VARCHAR(30)           DEFAULT NULL,
    `password`      VARCHAR(255) NOT NULL,
    `role_id`       INT UNSIGNED NOT NULL COMMENT 'FK → role.id',
    `status`        VARCHAR(32)  NOT NULL DEFAULT 'active' COMMENT 'active | inactive | deleted',
    `address_id`    INT UNSIGNED          DEFAULT NULL COMMENT 'default billing address',
    `last_login_at` DATETIME              DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME              DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_franchise_email` (`franchise_code`, `email`),
    KEY `idx_user_franchise` (`franchise_code`),
    KEY `idx_user_status`    (`status`),
    KEY `idx_user_role_id`   (`role_id`),
    CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── address ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `address` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL DEFAULT 'default',
    `user_id`      INT UNSIGNED NOT NULL,
    `type`         VARCHAR(20)  NOT NULL DEFAULT 'billing' COMMENT 'billing | shipping',
    `company`      VARCHAR(255)          DEFAULT NULL,
    `first_name`   VARCHAR(100) NOT NULL DEFAULT '',
    `last_name`    VARCHAR(100) NOT NULL DEFAULT '',
    `street`       VARCHAR(255) NOT NULL,
    `city`         VARCHAR(100) NOT NULL,
    `zip`          VARCHAR(20)  NOT NULL,
    `country`      VARCHAR(3)   NOT NULL DEFAULT 'CZ',
    `is_default`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_addr_franchise` (`franchise_code`),
    KEY `idx_addr_user`      (`user_id`),
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
    UNIQUE KEY `uq_token`      (`token`),
    KEY        `idx_token_user` (`user_id`),
    CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── category ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `category` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL DEFAULT 'default',
    `parent_id`    INT UNSIGNED          DEFAULT NULL,
    `name`         VARCHAR(255) NOT NULL,
    `slug`         VARCHAR(255) NOT NULL,
    `description`  TEXT                  DEFAULT NULL,
    `position`   SMALLINT     NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cat_franchise_slug` (`franchise_code`, `slug`),
    KEY `idx_cat_franchise` (`franchise_code`),
    KEY `idx_cat_parent`    (`parent_id`),
    CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── product ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `franchise_code`   VARCHAR(64)    NOT NULL DEFAULT 'default',
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
    UNIQUE KEY `uq_product_franchise_sku` (`franchise_code`, `sku`),
    KEY `idx_product_franchise` (`franchise_code`),
    KEY `idx_product_cat`       (`category_id`),
    KEY `idx_product_status`    (`status`),
    CONSTRAINT `fk_product_cat` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── text (CMS content blocks) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `text` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL DEFAULT 'default',
    `key`          VARCHAR(128) NOT NULL,
    `title`        VARCHAR(255) NOT NULL,
    `content`      LONGTEXT              DEFAULT NULL,
    `language`     VARCHAR(10)  NOT NULL DEFAULT 'cs',
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by`   INT UNSIGNED          DEFAULT NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_text_franchise_key_lang` (`franchise_code`, `key`, `language`),
    KEY `idx_text_franchise` (`franchise_code`),
    KEY `idx_text_lang`      (`language`),
    CONSTRAINT `fk_text_user` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── order ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order` (
    `id`                  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `franchise_code`        VARCHAR(64)    NOT NULL DEFAULT 'default',
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
    UNIQUE KEY `uq_order_franchise_number` (`franchise_code`, `order_number`),
    KEY `idx_order_franchise` (`franchise_code`),
    KEY `idx_order_user`      (`user_id`),
    KEY `idx_order_status`    (`status`),
    CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`)             REFERENCES `user`    (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_order_ship` FOREIGN KEY (`shipping_address_id`) REFERENCES `address` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_order_bill` FOREIGN KEY (`billing_address_id`)  REFERENCES `address` (`id`) ON DELETE SET NULL
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
    `franchise_code`       VARCHAR(64)    NOT NULL DEFAULT 'default',
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
    UNIQUE KEY `uq_inv_franchise_number` (`franchise_code`, `invoice_number`),
    KEY `idx_inv_franchise` (`franchise_code`),
    KEY `idx_inv_order`     (`order_id`),
    KEY `idx_inv_user`      (`user_id`),
    KEY `idx_inv_status`    (`status`),
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

-- ── Seed: default roles ───────────────────────────────────
INSERT INTO `role` (`franchise_code`, `name`, `label`, `position`) VALUES
  ('default', 'admin',   'Admin',   10),
  ('default', 'manager', 'Manager', 20),
  ('default', 'user',    'User',    30);

-- ── Seed: default enumerations ────────────────────────────
INSERT INTO `enumeration` (`franchise_code`, `type`, `code`, `label`, `value`, `position`) VALUES
  -- Order statuses
  ('default', 'order_status', 'pending',    'Pending',    'pending',    10),
  ('default', 'order_status', 'confirmed',  'Confirmed',  'confirmed',  20),
  ('default', 'order_status', 'processing', 'Processing', 'processing', 30),
  ('default', 'order_status', 'shipped',    'Shipped',    'shipped',    40),
  ('default', 'order_status', 'delivered',  'Delivered',  'delivered',  50),
  ('default', 'order_status', 'cancelled',  'Cancelled',  'cancelled',  60),
  ('default', 'order_status', 'refunded',   'Refunded',   'refunded',   70),
  -- Invoice statuses
  ('default', 'invoice_status', 'draft',     'Draft',     'draft',     10),
  ('default', 'invoice_status', 'issued',    'Issued',    'issued',    20),
  ('default', 'invoice_status', 'paid',      'Paid',      'paid',      30),
  ('default', 'invoice_status', 'overdue',   'Overdue',   'overdue',   40),
  ('default', 'invoice_status', 'cancelled', 'Cancelled', 'cancelled', 50),
  ('default', 'invoice_status', 'refunded',  'Refunded',  'refunded',  60),
  -- Payment methods
  ('default', 'payment_method', 'bank_transfer', 'Bank Transfer', 'bank_transfer', 10),
  ('default', 'payment_method', 'cash',          'Cash',          'cash',          20),
  ('default', 'payment_method', 'card',          'Card',          'card',          30),
  ('default', 'payment_method', 'online',        'Online',        'online',        40),
  -- Currencies
  ('default', 'currency', 'CZK', 'Czech Koruna', 'CZK', 10),
  ('default', 'currency', 'EUR', 'Euro',         'EUR', 20),
  ('default', 'currency', 'USD', 'US Dollar',    'USD', 30),
  -- VAT rates
  ('default', 'vat_rate', '0',  '0%',  '0',  10),
  ('default', 'vat_rate', '10', '10%', '10', 20),
  ('default', 'vat_rate', '12', '12%', '12', 30),
  ('default', 'vat_rate', '21', '21%', '21', 40);

-- ── Seed: admin user (password: password) ────────────────
INSERT INTO `user` (`franchise_code`, `first_name`, `last_name`, `email`, `password`, `role_id`, `status`) VALUES
  ('default', 'Admin', 'User', 'admin@example.com',
   '$2y$12$ubNeYmIWTWs4hXG6OQWQdO5rRStAzqrHM1C/xxU9H7vZx0LvMKI5q',
   (SELECT id FROM role WHERE franchise_code = 'default' AND name = 'admin'),
   'active');
