-- ============================================================
-- php-core database schema  (multi-tenant: franchise_code)
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── Drop existing tables ───────────────────────────────────
/* DROP TABLE IF EXISTS `invoice_item`;
DROP TABLE IF EXISTS `invoice`;
DROP TABLE IF EXISTS `order_item`;
DROP TABLE IF EXISTS `order`;
DROP TABLE IF EXISTS `product_category`;
DROP TABLE IF EXISTS `product`;
DROP TABLE IF EXISTS `category`;
DROP TABLE IF EXISTS `text`;
DROP TABLE IF EXISTS `user_token`;
DROP TABLE IF EXISTS `address`;
DROP TABLE IF EXISTS `user`;
DROP TABLE IF EXISTS `enumeration`;
DROP TABLE IF EXISTS `role`; */

-- ── enumeration (ciselnik) ────────────────────────────────
CREATE TABLE `enumeration` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL DEFAULT 'default' COMMENT 'multi-tenant project key',
    `type`         VARCHAR(64)  NOT NULL COMMENT 'e.g. order_status, invoice_status, payment_method',
    `syscode`       VARCHAR(64)  NOT NULL,
    `label`        VARCHAR(255) NOT NULL,
    `value`        VARCHAR(255) NOT NULL DEFAULT '',
    `position`   SMALLINT     NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_enum_franchise_type_syscode` (`franchise_code`, `type`, `syscode`),
    KEY `idx_enum_franchise` (`franchise_code`),
    KEY `idx_enum_type`      (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── role ──────────────────────────────────────────────────
CREATE TABLE `role` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL DEFAULT 'default',
    `name`           VARCHAR(64)  NOT NULL COMMENT 'e.g. admin, user, manager',
    `label`          VARCHAR(255) NOT NULL,
    `position`     SMALLINT     NOT NULL DEFAULT 0,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_role_franchise_name` (`franchise_code`, `name`),
    KEY `idx_role_franchise` (`franchise_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── user ─────────────────────────────────────────────────
CREATE TABLE `user` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code`  VARCHAR(64)  NOT NULL DEFAULT 'default',
    `first_name`    VARCHAR(100) NOT NULL,
    `last_name`     VARCHAR(100) NOT NULL,
    `email`         VARCHAR(255) NOT NULL,
    `phone`         VARCHAR(30)           DEFAULT NULL,
    `password`      VARCHAR(255) NOT NULL,
    `role_id`       INT UNSIGNED NOT NULL COMMENT 'FK → role.id',
    `status`        ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME              DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_franchise_email` (`franchise_code`, `email`),
    KEY `idx_user_franchise` (`franchise_code`),
    KEY `idx_user_role_id`   (`role_id`),
    CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── address ───────────────────────────────────────────────
CREATE TABLE `address` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL DEFAULT 'default',
    `user_id`      INT UNSIGNED NOT NULL,
    `type`         ENUM('billing','shipping') NOT NULL DEFAULT 'billing',
    `company`      VARCHAR(255)          DEFAULT NULL,
    `name`         VARCHAR(255)          DEFAULT NULL,
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

-- ── user_token ────────────────────────────────────────────
CREATE TABLE `user_token` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token`      VARCHAR(64)  NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token`      (`token`),
    KEY        `idx_token_user` (`user_id`),
    CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── category ─────────────────────────────────────────────
CREATE TABLE `category` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL DEFAULT 'default',
    `parent_id`    INT UNSIGNED          DEFAULT NULL,
    `syscode`      VARCHAR(64)           DEFAULT NULL COMMENT 'machine-readable identifier, e.g. top, new, favourite',
    `name`         VARCHAR(255) NOT NULL,
    `description`  TEXT                  DEFAULT NULL,
    `position`   SMALLINT     NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cat_franchise_syscode` (`franchise_code`, `syscode`),
    KEY `idx_cat_franchise` (`franchise_code`),
    KEY `idx_cat_parent`    (`parent_id`),
    CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── product ───────────────────────────────────────────────
CREATE TABLE `product` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `franchise_code`   VARCHAR(64)    NOT NULL DEFAULT 'default',
    `sku`            VARCHAR(64)    NOT NULL,
    `name`           VARCHAR(255)   NOT NULL,
    `description`    TEXT                    DEFAULT NULL,
    `price`          DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `vat_rate`       DECIMAL(5, 2)  NOT NULL DEFAULT 21.00 COMMENT 'VAT percentage',
    `stock_quantity` INT            NOT NULL DEFAULT 0,
    `is_active`      TINYINT(1)     NOT NULL DEFAULT 1,
    -- filterable VARCHAR attributes
    `kind`           VARCHAR(64)             DEFAULT NULL COMMENT 'e.g. dry, sweet',
    `color`          VARCHAR(64)             DEFAULT NULL COMMENT 'e.g. white, red, rose',
    `variant`        VARCHAR(64)             DEFAULT NULL COMMENT 'grape variety / product variant',
    -- flexible JSON attributes (project-specific, filter via dot-notation e.g. data.year)
    `data`           JSON                    DEFAULT NULL COMMENT 'extra attributes, e.g. quality, volume, year',
    `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_franchise_sku` (`franchise_code`, `sku`),
    KEY `idx_product_franchise` (`franchise_code`),
    KEY `idx_product_kind`      (`kind`),
    KEY `idx_product_color`     (`color`),
    KEY `idx_product_variant`   (`variant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── product_category (M:N pivot) ──────────────────────────
CREATE TABLE `product_category` (
    `product_id`  INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`product_id`, `category_id`),
    KEY `idx_pc_category` (`category_id`),
    CONSTRAINT `fk_pc_product`  FOREIGN KEY (`product_id`)  REFERENCES `product`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── text (CMS content blocks) ─────────────────────────────
CREATE TABLE `text` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL DEFAULT 'default',
    `syscode`      VARCHAR(128) NOT NULL,
    `title`        VARCHAR(255) NOT NULL,
    `content`      LONGTEXT              DEFAULT NULL,
    `language`     VARCHAR(10)  NOT NULL DEFAULT 'cs',
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by`   INT UNSIGNED          DEFAULT NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_text_franchise_syscode_lang` (`franchise_code`, `syscode`, `language`),
    KEY `idx_text_franchise` (`franchise_code`),
    KEY `idx_text_lang`      (`language`),
    CONSTRAINT `fk_text_user` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── order ─────────────────────────────────────────────────
CREATE TABLE `order` (
    `id`                  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `franchise_code`        VARCHAR(64)    NOT NULL DEFAULT 'default',
    `order_number`        VARCHAR(64)    NOT NULL,
    `user_id`             INT UNSIGNED            DEFAULT NULL,
    `status`              ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
    `total_amount`        DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `currency`            VARCHAR(3)     NOT NULL DEFAULT 'CZK',
    `payment_method`      VARCHAR(64)             DEFAULT 'bank_transfer',
    `shipping_type`       VARCHAR(64)             DEFAULT NULL,
    `shipping_cost`       DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `shipping_address_id` INT UNSIGNED            DEFAULT NULL,
    `billing_address_id`  INT UNSIGNED            DEFAULT NULL,
    `note`                TEXT                    DEFAULT NULL,
    `created_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
CREATE TABLE `order_item` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `order_id`    INT UNSIGNED   NOT NULL,
    `product_id`  INT UNSIGNED            DEFAULT NULL,
    `quantity`    INT            NOT NULL DEFAULT 1,
    `unit_price`  DECIMAL(12, 2) NOT NULL,
    `total_price` DECIMAL(12, 2) NOT NULL,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_oi_order`   (`order_id`),
    KEY `idx_oi_product` (`product_id`),
    CONSTRAINT `fk_oi_order`   FOREIGN KEY (`order_id`)   REFERENCES `order`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── invoice ───────────────────────────────────────────────
CREATE TABLE `invoice` (
    `id`                 INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `franchise_code`       VARCHAR(64)    NOT NULL DEFAULT 'default',
    `invoice_number`     VARCHAR(64)    NOT NULL,
    `order_id`           INT UNSIGNED            DEFAULT NULL,
    `user_id`            INT UNSIGNED            DEFAULT NULL,
    `status`             ENUM('draft','issued','paid','overdue','cancelled','refunded') NOT NULL DEFAULT 'issued',
    `total_amount`       DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `currency`           VARCHAR(3)     NOT NULL DEFAULT 'CZK',
    `billing_address_id` INT UNSIGNED            DEFAULT NULL,
    `note`               TEXT                    DEFAULT NULL,
    `issued_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `due_at`             DATE                    DEFAULT NULL,
    `paid_at`            DATETIME                DEFAULT NULL,
    `created_at`         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
CREATE TABLE `invoice_item` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `invoice_id`  INT UNSIGNED   NOT NULL,
    `product_id`  INT UNSIGNED            DEFAULT NULL,
    `description` VARCHAR(255)   NOT NULL DEFAULT '',
    `quantity`    INT            NOT NULL DEFAULT 1,
    `unit_price`  DECIMAL(12, 2) NOT NULL,
    `total_price` DECIMAL(12, 2) NOT NULL,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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

-- ── Seed: default enumerations (only currency) ────────────────────────────
INSERT INTO `enumeration` (`franchise_code`, `type`, `syscode`, `label`, `value`, `position`) VALUES
  -- Currencies only
  ('default', 'currency', 'CZK', 'Czech Koruna', 'CZK', 10),
  ('default', 'currency', 'EUR', 'Euro',         'EUR', 20),
  ('default', 'currency', 'USD', 'US Dollar',    'USD', 30),
  -- Wine kinds
  ('default', 'wine_kind', 'dry',           'Dry',             'dry',           10),
  ('default', 'wine_kind', 'semi_dry',      'Semi-dry',        'semi_dry',      20),
  ('default', 'wine_kind', 'sweet',         'Sweet',           'sweet',         30),
  ('default', 'wine_kind', 'semi_sweet',    'Semi-sweet',      'semi_sweet',    40),
  ('default', 'wine_kind', 'extra_dry',     'Extra dry',       'extra_dry',     50),
  ('default', 'wine_kind', 'off_dry',       'Off-dry',         'off_dry',       60),
  ('default', 'wine_kind', 'medium_dry',    'Medium dry',      'medium_dry',    70),
  ('default', 'wine_kind', 'medium_sweet',  'Medium sweet',    'medium_sweet',  80),
  ('default', 'wine_kind', 'very_sweet',    'Very sweet',      'very_sweet',    90),
  ('default', 'wine_kind', 'dessert',       'Dessert',         'dessert',       100),
  -- Wine quality
  ('default', 'wine_quality', 'kabinett',                'Kabinett',                    'kabinett',                10),
  ('default', 'wine_quality', 'late_harvest',            'Late harvest',                'late_harvest',            20),
  ('default', 'wine_quality', 'selection_of_grapes',     'Selection of grapes',         'selection_of_grapes',     30),
  ('default', 'wine_quality', 'selection_of_berries',    'Selection of berries',        'selection_of_berries',    40),
  ('default', 'wine_quality', 'ice_wine',                'Ice wine',                    'ice_wine',                50),
  ('default', 'wine_quality', 'straw_wine',              'Straw wine',                  'straw_wine',              60),
  ('default', 'wine_quality', 'quality_wine',            'Quality wine',                'quality_wine',            70),
  ('default', 'wine_quality', 'archive_wine',            'Archive wine',                'archive_wine',            80),
  ('default', 'wine_quality', 'table_wine',              'Table wine',                  'table_wine',              90),
  -- Wine colors
  ('default', 'wine_color', 'white',  'White',  'white',  10),
  ('default', 'wine_color', 'red',    'Red',    'red',    20),
  ('default', 'wine_color', 'rose',   'Rosé',   'rose',   30),
  ('default', 'wine_color', 'orange', 'Orange', 'orange', 40),
  -- Wine varieties
  ('default', 'wine_variety', 'cabernet_sauvignon',  'Cabernet Sauvignon',  'cabernet_sauvignon',  10),
  ('default', 'wine_variety', 'chardonnay',          'Chardonnay',          'chardonnay',          20),
  ('default', 'wine_variety', 'frankovka',           'Frankovka',           'frankovka',           30),
  ('default', 'wine_variety', 'gruner_veltliner',    'Grüner Veltliner',    'gruner_veltliner',    40),
  ('default', 'wine_variety', 'merlot',              'Merlot',              'merlot',              50),
  ('default', 'wine_variety', 'modry_portugal',      'Modrý portugal',      'modry_portugal',      60),
  ('default', 'wine_variety', 'mueller_thurgau',     'Müller-Thurgau',      'mueller_thurgau',     70),
  ('default', 'wine_variety', 'muscat',              'Muscat',              'muscat',              80),
  ('default', 'wine_variety', 'other',               'Other',               'other',               90),
  ('default', 'wine_variety', 'pinot_blanc',         'Pinot Blanc',         'pinot_blanc',         100),
  ('default', 'wine_variety', 'pinot_gris',          'Pinot Gris',          'pinot_gris',          110),
  ('default', 'wine_variety', 'pinot_noir',          'Pinot Noir',          'pinot_noir',          120),
  ('default', 'wine_variety', 'riesling',            'Riesling',            'riesling',            130),
  ('default', 'wine_variety', 'sauvignon_blanc',     'Sauvignon Blanc',     'sauvignon_blanc',     140),
  ('default', 'wine_variety', 'st_laurent',          'St. Laurent',         'st_laurent',          150),
  ('default', 'wine_variety', 'traminer',            'Traminer',            'traminer',            160),
  ('default', 'wine_variety', 'welschriesling',      'Welschriesling',      'welschriesling',      170),
  ('default', 'wine_variety', 'zweigelt',            'Zweigelt',            'zweigelt',            180),
  -- Languages
  ('default', 'language', 'cs', 'Čeština', 'cs', 10),
  ('default', 'language', 'en', 'English',  'en', 20),
  -- Country codes
  ('default', 'country_code', 'cs', 'CZ', 'cs', 10),
  ('default', 'country_code', 'sk', 'SK', 'sk', 20),
  -- Order status
  ('default', 'order_status', 'pending', 'Pending', 'pending', 10),
  ('default', 'order_status', 'confirmed', 'Confirmed', 'confirmed', 20),
  ('default', 'order_status', 'shipped', 'Shipped', 'shipped', 30),
  ('default', 'order_status', 'delivered', 'Delivered', 'delivered', 40),
  ('default', 'order_status', 'cancelled', 'Cancelled', 'cancelled', 50),
  -- Invoice status
  ('default', 'invoice_status', 'draft', 'Draft', 'draft', 10),
  ('default', 'invoice_status', 'issued', 'Issued', 'issued', 20),
  ('default', 'invoice_status', 'paid', 'Paid', 'paid', 30),
  ('default', 'invoice_status', 'overdue', 'Overdue', 'overdue', 40),
  ('default', 'invoice_status', 'cancelled', 'Cancelled', 'cancelled', 50);

-- ── Seed: admin user (password: password) ────────────────
INSERT INTO `user` (`franchise_code`, `first_name`, `last_name`, `email`, `password`, `role_id`) VALUES
  ('default', 'Admin', 'User', 'admin@example.com',
   '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',
   (SELECT id FROM role WHERE franchise_code = 'default' AND name = 'admin'));

-- ── Seed: category "top" ──────────────────────────────────
INSERT INTO `category` (`franchise_code`, `parent_id`, `syscode`, `name`, `description`, `position`) VALUES
  ('default', NULL, 'top', 'Top Produkty', 'Nejlepší vína z nabídky', 10);

-- ── Seed: 4 wines ────────────────────────────────────────
INSERT INTO `product` (`franchise_code`, `sku`, `name`, `description`, `price`, `vat_rate`, `stock_quantity`, `is_active`, `kind`, `color`, `variant`, `data`) VALUES
  ('default', 'ZAJ-WHI-001', 'Zaječské Bílé', 'Jemné bílé víno ze Zaječí', 299.00, 21.00, 50, 1, 'dry', 'white', '0.75l', JSON_OBJECT('year', 2022, 'volume', 0.75, 'quality', 'kabinett', 'winery', 'Vinařství Zaječí', 'region', 'Moravie', 'alcohol', 12.5, 'serving_temp', '8-10°C')),
  ('default', 'ZAJ-RED-001', 'Zaječské Červené', 'Kvalitní červené víno tradičního stylu', 349.00, 21.00, 40, 1, 'dry', 'red', '0.75l', JSON_OBJECT('year', 2021, 'volume', 0.75, 'quality', 'selection_of_grapes', 'winery', 'Vinařství Zaječí', 'region', 'Moravie', 'alcohol', 13.5, 'serving_temp', '16-18°C')),
  ('default', 'ZAJ-ROE-001', 'Zaječské Rosé', 'Lehké rosé víno s ovocnými tóny', 329.00, 21.00, 35, 1, 'semi_dry', 'rose', '0.75l', JSON_OBJECT('year', 2023, 'volume', 0.75, 'quality', 'late_harvest', 'winery', 'Vinařství Zaječí', 'region', 'Moravie', 'alcohol', 12.0, 'serving_temp', '6-8°C')),
  ('default', 'ZAJ-SWE-001', 'Zaječské Sladké', 'Sladké desertní víno s bohatou chutí', 399.00, 21.00, 20, 1, 'dessert', 'red', '0.5l', JSON_OBJECT('year', 2020, 'volume', 0.5, 'quality', 'ice_wine', 'winery', 'Vinařství Zaječí', 'region', 'Moravie', 'alcohol', 11.0, 'serving_temp', '4-6°C'));

-- ── Seed: link products 1, 2, 3 to category "top" ──────────
INSERT INTO `product_category` (`product_id`, `category_id`) VALUES
  ((SELECT id FROM product WHERE franchise_code = 'default' AND sku = 'ZAJ-WHI-001'), (SELECT id FROM category WHERE franchise_code = 'default' AND syscode = 'top')),
  ((SELECT id FROM product WHERE franchise_code = 'default' AND sku = 'ZAJ-RED-001'), (SELECT id FROM category WHERE franchise_code = 'default' AND syscode = 'top')),
  ((SELECT id FROM product WHERE franchise_code = 'default' AND sku = 'ZAJ-ROE-001'), (SELECT id FROM category WHERE franchise_code = 'default' AND syscode = 'top'));

