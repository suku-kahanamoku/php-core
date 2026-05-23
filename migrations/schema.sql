-- ============================================================
-- php-core database schema  (multi-tenant: franchise_code)
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── Drop existing tables ───────────────────────────────────
DROP TABLE IF EXISTS `invoice_item`;
DROP TABLE IF EXISTS `invoice_file`;
DROP TABLE IF EXISTS `invoice`;
DROP TABLE IF EXISTS `order_item`;
DROP TABLE IF EXISTS `order`;
DROP TABLE IF EXISTS `product_file`;
DROP TABLE IF EXISTS `product_category`;
DROP TABLE IF EXISTS `product`;
DROP TABLE IF EXISTS `category`;
DROP TABLE IF EXISTS `text`;
DROP TABLE IF EXISTS `file`;
DROP TABLE IF EXISTS `user_token`;
DROP TABLE IF EXISTS `address`;
DROP TABLE IF EXISTS `user`;
DROP TABLE IF EXISTS `enumeration`;
DROP TABLE IF EXISTS `role`;

-- ── enumeration (ciselnik) ────────────────────────────────
CREATE TABLE `enumeration` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL COMMENT 'multi-tenant project key',
    `type`         VARCHAR(64)  NOT NULL COMMENT 'e.g. order_status, invoice_status, payment_method',
    `syscode`       VARCHAR(64)  NOT NULL,
    `label`        VARCHAR(255) NOT NULL,
    `value`        VARCHAR(255) NOT NULL DEFAULT '',
    `position`   SMALLINT     NOT NULL DEFAULT 0,
    `published`    TINYINT(1)   NOT NULL DEFAULT 1,
    `data`         JSON                  DEFAULT NULL,
    `deleted`      TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_enum_franchise_type_syscode` (`franchise_code`, `type`, `syscode`),
    KEY `idx_enum_franchise` (`franchise_code`),
    KEY `idx_enum_type`      (`type`),
    KEY `idx_enum_deleted`   (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── role ──────────────────────────────────────────────────
CREATE TABLE `role` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL,
    `name`           VARCHAR(64)  NOT NULL COMMENT 'e.g. admin, user, manager',
    `label`          VARCHAR(255) NOT NULL,
    `position`     SMALLINT     NOT NULL DEFAULT 0,
    `deleted`        TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_role_franchise_name` (`franchise_code`, `name`),
    KEY `idx_role_franchise` (`franchise_code`),
    KEY `idx_role_deleted`   (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── user ─────────────────────────────────────────────────
CREATE TABLE `user` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code`  VARCHAR(64)  NOT NULL,
    `first_name`    VARCHAR(100) NOT NULL,
    `last_name`     VARCHAR(100) NOT NULL,
    `email`         VARCHAR(255) NOT NULL,
    `phone`         VARCHAR(30)           DEFAULT NULL,
    `password`      VARCHAR(255) NOT NULL,
    `role_id`       INT UNSIGNED NOT NULL COMMENT 'FK → role.id',
    `status`        ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME              DEFAULT NULL,
    `deleted`       TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_franchise_email` (`franchise_code`, `email`),
    KEY `idx_user_franchise` (`franchise_code`),
    KEY `idx_user_role_id`   (`role_id`),
    KEY `idx_user_deleted`   (`deleted`),
    CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── address ───────────────────────────────────────────────
CREATE TABLE `address` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL,
    `user_id`      INT UNSIGNED NOT NULL,
    `type`         ENUM('billing','shipping') NOT NULL DEFAULT 'billing',
    `company`      VARCHAR(255)          DEFAULT NULL,
    `name`         VARCHAR(255)          DEFAULT NULL,
    `street`       VARCHAR(255) NOT NULL,
    `city`         VARCHAR(100) NOT NULL,
    `zip`          VARCHAR(20)  NOT NULL,
    `country`      VARCHAR(3)   NOT NULL DEFAULT 'CZ',
    `is_default`   TINYINT(1)   NOT NULL DEFAULT 0,
    `deleted`      TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_addr_franchise` (`franchise_code`),
    KEY `idx_addr_user`      (`user_id`),
    KEY `idx_addr_deleted`   (`deleted`),
    CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── user_token ────────────────────────────────────────────
CREATE TABLE `user_token` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token`      VARCHAR(64)  NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `deleted`    TINYINT(1)   NOT NULL DEFAULT 0,
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
    `franchise_code` VARCHAR(64)  NOT NULL,
    `parent_id`    INT UNSIGNED          DEFAULT NULL,
    `syscode`      VARCHAR(64)           DEFAULT NULL COMMENT 'machine-readable identifier, e.g. top, new, favourite',
    `name`         VARCHAR(255) NOT NULL,
    `description`  TEXT                  DEFAULT NULL,
    `position`   SMALLINT     NOT NULL DEFAULT 0,
    `deleted`      TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cat_franchise_syscode` (`franchise_code`, `syscode`),
    KEY `idx_cat_franchise` (`franchise_code`),
    KEY `idx_cat_parent`    (`parent_id`),
    KEY `idx_cat_deleted`   (`deleted`),
    CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── product ───────────────────────────────────────────────
CREATE TABLE `product` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `franchise_code`   VARCHAR(64)    NOT NULL,
    `sku`            VARCHAR(64)    NOT NULL,
    `name`           VARCHAR(255)   NOT NULL,
    `description`    TEXT                    DEFAULT NULL,
    `price`          DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `stock_quantity` INT            NOT NULL DEFAULT 0,
    `published`      TINYINT(1)     NOT NULL DEFAULT 1,
    `deleted`        TINYINT(1)     NOT NULL DEFAULT 0,
    -- filterable VARCHAR attributes
    `kind`           VARCHAR(64)             DEFAULT NULL COMMENT 'e.g. dry, sweet',
    `color`          VARCHAR(64)             DEFAULT NULL COMMENT 'e.g. white, red, rose',
    `variant`        VARCHAR(64)             DEFAULT NULL COMMENT 'grape variety / product variant',
    -- flexible JSON attributes (project-specific, filter via dot-notation e.g. data.year)
    `data`           JSON                    DEFAULT NULL COMMENT 'extra attributes, e.g. quality, volume, year, batch',
    `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_franchise_sku` (`franchise_code`, `sku`),
    KEY `idx_product_franchise` (`franchise_code`),
    KEY `idx_product_kind`      (`kind`),
    KEY `idx_product_color`     (`color`),
    KEY `idx_product_variant`   (`variant`),
    KEY `idx_product_deleted`   (`deleted`)
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

-- ── product_file (M:N pivot) ───────────────────────────────
CREATE TABLE `product_file` (
    `product_id` INT UNSIGNED NOT NULL,
    `file_id`    INT UNSIGNED NOT NULL,
    PRIMARY KEY (`product_id`, `file_id`),
    KEY `idx_pf_file` (`file_id`),
    CONSTRAINT `fk_pf_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pf_file`    FOREIGN KEY (`file_id`)    REFERENCES `file`    (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── text (CMS content blocks) ─────────────────────────────
CREATE TABLE `text` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)  NOT NULL,
    `syscode`      VARCHAR(128) NOT NULL,
    `title`        VARCHAR(255) NOT NULL,
    `content`      LONGTEXT              DEFAULT NULL,
    `language`     VARCHAR(10)  NOT NULL DEFAULT 'cs',
    `published`    TINYINT(1)   NOT NULL DEFAULT 1,
    `deleted`      TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_text_franchise_syscode_lang` (`franchise_code`, `syscode`, `language`),
    KEY `idx_text_franchise` (`franchise_code`),
    KEY `idx_text_lang`      (`language`),
    KEY `idx_text_deleted`   (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── order ─────────────────────────────────────────────────
CREATE TABLE `order` (
    `id`                     INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `franchise_code`         VARCHAR(64)    NOT NULL,
    `order_number`           VARCHAR(64)    NOT NULL,
    `user_id`                INT UNSIGNED            DEFAULT NULL,
    `status`                 ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
    `total_price`            DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'soucet order_items bez DPH',
    `total_price_with_vat`   DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'soucet order_items vcetne DPH',
    `total_price_all`        DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'total_price + shipping_price',
    `total_price_all_with_vat` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'total_price_with_vat + shipping_price',
    `currency`               VARCHAR(3)     NOT NULL DEFAULT 'CZK',
    `payment`               JSON                    DEFAULT NULL COMMENT 'snapshot platebni metody {type,label,account,iban,swift,price,...}',
    `shipping`              JSON                    DEFAULT NULL COMMENT 'snapshot zpusobu dopravy {type,label,price,icon,...}',
    `shipping_address_id`    INT UNSIGNED            DEFAULT NULL,
    `billing_address_id`     INT UNSIGNED            DEFAULT NULL,
    `note`                   TEXT                    DEFAULT NULL,
    `deleted`                TINYINT(1)     NOT NULL DEFAULT 0,
    `created_at`             DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_order_franchise_number` (`franchise_code`, `order_number`),
    KEY `idx_order_franchise` (`franchise_code`),
    KEY `idx_order_user`      (`user_id`),
    KEY `idx_order_status`    (`status`),
    KEY `idx_order_deleted`   (`deleted`),
    CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`)             REFERENCES `user`    (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_order_ship` FOREIGN KEY (`shipping_address_id`) REFERENCES `address` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_order_bill` FOREIGN KEY (`billing_address_id`)  REFERENCES `address` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── order_item ────────────────────────────────────────────
CREATE TABLE `order_item` (
    `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `order_id`        INT UNSIGNED   NOT NULL,
    `product_id`      INT UNSIGNED            DEFAULT NULL,
    `product_name`    VARCHAR(255)            DEFAULT NULL COMMENT 'snapshot nazvu produktu',
    `sku`             VARCHAR(128)            DEFAULT NULL COMMENT 'snapshot SKU',
    `quantity`        INT            NOT NULL DEFAULT 1,
    `price`           DECIMAL(12, 2) NOT NULL COMMENT 'cena za kus bez DPH',
    `price_with_vat`  DECIMAL(12, 2) NOT NULL COMMENT 'cena za kus vcetne DPH',
    `vat_rate`        DECIMAL(5, 2)  NOT NULL DEFAULT 0.00 COMMENT 'sazba DPH v %',
    `total_price`     DECIMAL(12, 2) NOT NULL COMMENT 'price * quantity',
    `total_price_with_vat` DECIMAL(12, 2) NOT NULL COMMENT 'price_with_vat * quantity',
    `deleted`         TINYINT(1)     NOT NULL DEFAULT 0,
    `created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_oi_order`   (`order_id`),
    KEY `idx_oi_product` (`product_id`),
    CONSTRAINT `fk_oi_order`   FOREIGN KEY (`order_id`)   REFERENCES `order`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── invoice ───────────────────────────────────────────────
-- Faktura je snapshot objednavky – uchovava vsechna data v dobe vystaveni.
-- FK na order/user/address zustavaji pro referenci, ale data se uchovavaji jako JSON.
CREATE TABLE `invoice` (
    `id`                       INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `franchise_code`           VARCHAR(64)    NOT NULL,
    `invoice_number`           VARCHAR(64)    NOT NULL,
    `order_id`                 INT UNSIGNED            DEFAULT NULL COMMENT 'reference na objednavku (soft FK)',
    `order_number`             VARCHAR(64)             DEFAULT NULL COMMENT 'snapshot cisla objednavky',
    `status`                   ENUM('draft','issued','paid','overdue','cancelled','refunded') NOT NULL DEFAULT 'issued',
    `currency`                 VARCHAR(3)     NOT NULL DEFAULT 'CZK',
    `payment`                  JSON                    DEFAULT NULL COMMENT 'snapshot platebni metody v dobe vystaveni',
    `shipping`                 JSON                    DEFAULT NULL COMMENT 'snapshot zpusobu dopravy v dobe vystaveni {type,label,price,...}',
    `total_price`              DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'soucet polozek bez DPH',
    `total_price_with_vat`     DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'soucet polozek vcetne DPH',
    `total_price_all`          DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'total_price + shipping_price',
    `total_price_all_with_vat` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'total_price_with_vat + shipping_price',
    `user`                     JSON                    DEFAULT NULL COMMENT 'snapshot uzivatele {id,first_name,last_name,email,phone}',
    `billing_address`          JSON                    DEFAULT NULL COMMENT 'snapshot fakturacni adresy',
    `shipping_address`         JSON                    DEFAULT NULL COMMENT 'snapshot dodaci adresy',
    `note`                     TEXT                    DEFAULT NULL,
    `issued_at`                DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `due_at`                   DATE                    DEFAULT NULL,
    `paid_at`                  DATETIME                DEFAULT NULL,
    `deleted`                  TINYINT(1)     NOT NULL DEFAULT 0,
    `created_at`               DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inv_franchise_number` (`franchise_code`, `invoice_number`),
    KEY `idx_inv_franchise` (`franchise_code`),
    KEY `idx_inv_order`     (`order_id`),
    KEY `idx_inv_status`    (`status`),
    KEY `idx_inv_deleted`   (`deleted`),
    CONSTRAINT `fk_inv_order` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── invoice_item ──────────────────────────────────────────
-- Snapshot polozek objednavky v dobe vystaveni faktury.
CREATE TABLE `invoice_item` (
    `id`                   INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `invoice_id`           INT UNSIGNED   NOT NULL,
    `product_name`         VARCHAR(255)   NOT NULL DEFAULT '' COMMENT 'snapshot nazvu produktu',
    `sku`                  VARCHAR(128)            DEFAULT NULL COMMENT 'snapshot SKU',
    `quantity`             INT            NOT NULL DEFAULT 1,
    `price`                DECIMAL(12, 2) NOT NULL COMMENT 'cena za kus bez DPH',
    `price_with_vat`       DECIMAL(12, 2) NOT NULL COMMENT 'cena za kus vcetne DPH',
    `vat_rate`             DECIMAL(5, 2)  NOT NULL DEFAULT 0.00 COMMENT 'sazba DPH v %',
    `total_price`          DECIMAL(12, 2) NOT NULL COMMENT 'price * quantity',
    `total_price_with_vat` DECIMAL(12, 2) NOT NULL COMMENT 'price_with_vat * quantity',
    `deleted`              TINYINT(1)     NOT NULL DEFAULT 0,
    `created_at`           DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ii_invoice` (`invoice_id`),
    CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── invoice_file (M:N pivot) ───────────────────────────────
CREATE TABLE `invoice_file` (
    `invoice_id` INT UNSIGNED NOT NULL,
    `file_id`    INT UNSIGNED NOT NULL,
    PRIMARY KEY (`invoice_id`, `file_id`),
    KEY `idx_if_file` (`file_id`),
    CONSTRAINT `fk_if_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_if_file`    FOREIGN KEY (`file_id`)    REFERENCES `file`    (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── file ─────────────────────────────────────────────────
CREATE TABLE `file` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(64)    NOT NULL,
    `type`           VARCHAR(32)    NOT NULL COMMENT 'pripona: pdf, jpg, csv...',
    `mime_type`      VARCHAR(100)   NOT NULL COMMENT 'application/pdf, image/jpeg...',
    `path`           VARCHAR(512)   NOT NULL COMMENT 'relativni cesta v /files/ po commitu',
    `name`           VARCHAR(255)   NOT NULL COMMENT 'puvodni nazev souboru',
    `size`           INT UNSIGNED   NOT NULL DEFAULT 0 COMMENT 'velikost v bytech',
    `visibility`     ENUM('public','private') NOT NULL DEFAULT 'private',
    `entity_type`    VARCHAR(64)             DEFAULT NULL COMMENT 'product, user, invoice...',
    `entity_id`      INT UNSIGNED            DEFAULT NULL,
    `deleted`        TINYINT(1)     NOT NULL DEFAULT 0,
    `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME                DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `expires_at`     DATETIME                DEFAULT NULL COMMENT 'TTL pro tmp soubory, cron target',
    PRIMARY KEY (`id`),
    KEY `idx_file_franchise`   (`franchise_code`),
    KEY `idx_file_entity`      (`entity_type`, `entity_id`),
    KEY `idx_file_deleted`     (`deleted`),
    KEY `idx_file_expires`     (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ── Seed: default roles ───────────────────────────────────
INSERT INTO `role` (`franchise_code`, `name`, `label`, `position`) VALUES
  ('zajeci', 'admin',   'Admin',   10),
  ('zajeci', 'manager', 'Manager', 20),
  ('zajeci', 'user',    'User',    30);

-- ── Seed: default enumerations (only currency) ────────────────────────────
INSERT INTO `enumeration` (`franchise_code`, `type`, `syscode`, `label`, `value`, `position`) VALUES
  -- Currencies only
  ('zajeci', 'currency', 'CZK', 'Czech Koruna', 'CZK', 10),
  ('zajeci', 'currency', 'EUR', 'Euro',         'EUR', 20),
  ('zajeci', 'currency', 'USD', 'US Dollar',    'USD', 30),
  -- Wine kinds
  ('zajeci', 'wine_kind', 'dry',           'Dry',             'dry',           10),
  ('zajeci', 'wine_kind', 'semi_dry',      'Semi-dry',        'semi_dry',      20),
  ('zajeci', 'wine_kind', 'sweet',         'Sweet',           'sweet',         30),
  ('zajeci', 'wine_kind', 'semi_sweet',    'Semi-sweet',      'semi_sweet',    40),
  ('zajeci', 'wine_kind', 'extra_dry',     'Extra dry',       'extra_dry',     50),
  ('zajeci', 'wine_kind', 'off_dry',       'Off-dry',         'off_dry',       60),
  ('zajeci', 'wine_kind', 'medium_dry',    'Medium dry',      'medium_dry',    70),
  ('zajeci', 'wine_kind', 'medium_sweet',  'Medium sweet',    'medium_sweet',  80),
  ('zajeci', 'wine_kind', 'very_sweet',    'Very sweet',      'very_sweet',    90),
  ('zajeci', 'wine_kind', 'dessert',       'Dessert',         'dessert',       100),
  -- Wine colors
  ('zajeci', 'wine_color', 'white',  'White',  'white',  10),
  ('zajeci', 'wine_color', 'red',    'Red',    'red',    20),
  ('zajeci', 'wine_color', 'rose',   'Rosé',   'rose',   30),
  ('zajeci', 'wine_color', 'orange', 'Orange', 'orange', 40),
  -- Languages
  ('zajeci', 'language', 'cs', 'Čeština', 'cs', 10),
  ('zajeci', 'language', 'en', 'English',  'en', 20),
  -- Country codes
  ('zajeci', 'country_code', 'cs', 'CZ', 'cs', 10),
  ('zajeci', 'country_code', 'sk', 'SK', 'sk', 20),
  -- Order status
  ('zajeci', 'order_status', 'pending', 'Pending', 'pending', 10),
  ('zajeci', 'order_status', 'confirmed', 'Confirmed', 'confirmed', 20),
  ('zajeci', 'order_status', 'shipped', 'Shipped', 'shipped', 30),
  ('zajeci', 'order_status', 'delivered', 'Delivered', 'delivered', 40),
  ('zajeci', 'order_status', 'cancelled', 'Cancelled', 'cancelled', 50),
  -- Invoice status
  ('zajeci', 'invoice_status', 'draft', 'Draft', 'draft', 10),
  ('zajeci', 'invoice_status', 'issued', 'Issued', 'issued', 20),
  ('zajeci', 'invoice_status', 'paid', 'Paid', 'paid', 30),
  ('zajeci', 'invoice_status', 'overdue', 'Overdue', 'overdue', 40),
  ('zajeci', 'invoice_status', 'cancelled', 'Cancelled', 'cancelled', 50);

-- ── Seed: admin user (password: password) ────────────────
SET @admin_role_id = (SELECT id FROM `role` WHERE franchise_code = 'zajeci' AND name = 'admin' LIMIT 1);
INSERT INTO `user` (`franchise_code`, `first_name`, `last_name`, `email`, `password`, `role_id`) VALUES
  ('zajeci', 'Admin', 'User', 'admin@example.com',
   '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',
   @admin_role_id);

-- ── Seed: category "top" ──────────────────────────────────
INSERT INTO `category` (`franchise_code`, `parent_id`, `syscode`, `name`, `description`, `position`) VALUES
  ('zajeci', NULL, 'top', 'Top Produkty', 'Nejlepší vína z nabídky', 10);

-- ── Seed: 6 wines ────────────────────────────────────────
INSERT INTO `product` (`franchise_code`, `sku`, `name`, `description`, `price`, `stock_quantity`, `published`, `kind`, `color`, `variant`, `data`) VALUES
  ('zajeci', 'ZAJ-MT-2025', 'Müller Thurgau 2025',
   'Moravské zemské víno. Vinařská obec Zaječí, viniční trať U Kapličky. Cukernatost hroznů při sběru 21 °NM, zbytkový cukr do 4 g/l. Kvašeno a školeno v dubovém sudu, bez použití selektovaných kvasinek a enzymů.',
   190.00, 50, 1, 'dry', 'white', 'Müller-Thurgau',
   JSON_OBJECT('year', 2025, 'volume', 0.75, 'alcohol', 12.0, 'batch', '12025', 'winery', 'Vinařství Zaječí', 'region', 'Zaječí – U Kapličky', 'sugar_at_harvest', 21, 'quality', 'Moravské zemské víno')),

  ('zajeci', 'ZAJ-SZ-2025', 'Sylvánské zelené 2025',
   'Moravské zemské víno. Vinařská obec Zaječí, viniční trať Stará Hora. Cukernatost hroznů při sběru 22 °NM, zbytkový cukr do 4 g/l. Kvašeno a školeno ve skle, bez použití selektovaných kvasinek a enzymů.',
   190.00, 50, 1, 'dry', 'white', 'Sylvánské zelené',
   JSON_OBJECT('year', 2025, 'volume', 0.75, 'alcohol', 12.0, 'batch', '52025', 'winery', 'Vinařství Zaječí', 'region', 'Zaječí – Stará Hora', 'sugar_at_harvest', 22, 'quality', 'Moravské zemské víno')),

  ('zajeci', 'ZAJ-NB-2025', 'Neuburské 2025',
   'Moravské zemské víno. Vinařská obec Zaječí, viniční trať U Kapličky, severní svah. Cukernatost hroznů při sběru 22 °NM, zbytkový cukr do 9 g/l. Kvašeno a školeno ve skle, bez použití selektovaných kvasinek a enzymů.',
   200.00, 40, 1, 'semi_dry', 'white', 'Neuburské',
   JSON_OBJECT('year', 2025, 'volume', 0.75, 'alcohol', 12.0, 'batch', '82025', 'winery', 'Vinařství Zaječí', 'region', 'Zaječí – U Kapličky (sever)', 'sugar_at_harvest', 22, 'quality', 'Moravské zemské víno')),

  ('zajeci', 'ZAJ-RR-2024', 'Rýnský ryzlink 2024',
   'Moravské zemské víno. Vinařská obec Přítluky, viniční trať U křížku. Cukernatost hroznů při sběru 23 °NM, zbytkový cukr do 1 g/l. Kvašeno a školeno v akátovém sudu, bez použití selektovaných kvasinek a enzymů.',
   240.00, 40, 1, 'dry', 'white', 'Rýnský ryzlink',
   JSON_OBJECT('year', 2024, 'volume', 0.75, 'alcohol', 12.5, 'batch', '92024', 'winery', 'Vinařství Zaječí', 'region', 'Přítluky – U křížku', 'sugar_at_harvest', 23, 'quality', 'Moravské zemské víno')),

  ('zajeci', 'ZAJ-SK-2025', 'Slovakia 2025',
   'Experimentální odrůda vzniklá křížením Rýnského ryzlinku a Muškátu Ottonel (šlechtitelka Ing. Dorota Pospíšilová, CSc., VÚVV Bratislava). Odrůda není dosud uznána v ČR ani na Slovensku – zkušební výsadba. Moravské zemské víno, vinařská obec Moravská Nová Ves, viniční trať Stará hora. Cukernatost hroznů při sběru 23 °NM, zbytkový cukr do 4 g/l. Kvašeno a školeno ve skle, bez použití selektovaných kvasinek a enzymů.',
   240.00, 30, 1, 'dry', 'white', 'Slovakia',
   JSON_OBJECT('year', 2025, 'volume', 0.75, 'alcohol', 12.5, 'batch', '92025', 'winery', 'Vinařství Zaječí', 'region', 'Moravská Nová Ves – Stará hora', 'sugar_at_harvest', 23, 'quality', 'Moravské zemské víno')),

  ('zajeci', 'ZAJ-MM-2025', 'Moravský muškát 2025',
   'Moravské zemské víno. Vinařská obec Zaječí, viniční trať Nová Hora. Cukernatost hroznů při sběru 23 °NM, zbytkový cukr cca 25 g/l. Kvašeno a školeno ve skle, bez použití selektovaných kvasinek a enzymů.',
   220.00, 35, 1, 'semi_sweet', 'white', 'Moravský muškát',
   JSON_OBJECT('year', 2025, 'volume', 0.75, 'alcohol', 13.0, 'batch', '22025', 'winery', 'Vinařství Zaječí', 'region', 'Zaječí – Nová Hora', 'sugar_at_harvest', 23, 'quality', 'Moravské zemské víno'));

-- ── Seed: link products 1, 2, 3 to category "top" ──────────
INSERT INTO `product_category` (`product_id`, `category_id`) VALUES
  ((SELECT id FROM product WHERE franchise_code = 'zajeci' AND sku = 'ZAJ-MT-2025'), (SELECT id FROM category WHERE franchise_code = 'zajeci' AND syscode = 'top')),
  ((SELECT id FROM product WHERE franchise_code = 'zajeci' AND sku = 'ZAJ-SZ-2025'), (SELECT id FROM category WHERE franchise_code = 'zajeci' AND syscode = 'top')),
  ((SELECT id FROM product WHERE franchise_code = 'zajeci' AND sku = 'ZAJ-NB-2025'), (SELECT id FROM category WHERE franchise_code = 'zajeci' AND syscode = 'top'));

-- ── Seed: 3 tasting packages (as enumerations) ───────────
INSERT INTO `enumeration` (`franchise_code`, `type`, `syscode`, `label`, `value`, `position`, `published`, `data`) VALUES
  ('zajeci', 'taste', 'basic',              'Basic',              'basic',              10, 1, JSON_OBJECT('price', 250.00, 'drink', 'Ochutnávka 6 vzorků', 'food', 'Pečivo, voda', 'time', 'Doba trvání 1 hodina', 'description', '')),
  ('zajeci', 'taste', 'medium',             'Medium',             'medium',             20, 1, JSON_OBJECT('price', 500.00, 'drink', 'Ochutnávka 10 vzorků', 'food', 'Občerstvení, pečivo, voda', 'time', 'Doba trvání 2 až 2,5 hodiny', 'description', '')),
  ('zajeci', 'taste', 'all_you_can_drink',  'All you can drink',  'all_you_can_drink',  30, 1, JSON_OBJECT('price', 900.00, 'drink', 'Ochutnávka všech vzorků (min. 9 bílých, 4 růžové, 4 červené)', 'food', 'Bohaté občerstvení, voda, nealko, pivo, cider, šláftruňk', 'time', 'Doba trvání podle nálady, max 5 hodin', 'description', ''));

-- ── Seed: shipping methods (as enumerations) ─────────────
INSERT INTO `enumeration` (`franchise_code`, `type`, `syscode`, `label`, `value`, `position`, `published`, `data`) VALUES
  ('zajeci', 'shipping', 'free',      'Vyzvednutí v Zaječí', 'free',      10, 1, JSON_OBJECT('price', 0,   'icon', 'mdi:home-city-outline', 'help', '$.shipping.brno_free',      'disabled', false)),
  ('zajeci', 'shipping', 'post',      'Česká pošta',         'post',      20, 1, JSON_OBJECT('price', 209, 'icon', '/img/shipping/post.jpg', 'help', '$.shipping.not_quaranteed', 'disabled', false)),
  ('zajeci', 'shipping', 'dpd',       'DPD',                 'dpd',       30, 1, JSON_OBJECT('price', 150, 'icon', 'mdi:truck-outline',      'help', '$.shipping.not_quaranteed', 'disabled', false)),
  ('zajeci', 'shipping', 'messenger', 'Vlastní doručení',    'messenger', 40, 1, JSON_OBJECT('price', 175, 'icon', 'mdi:truck-outline',      'help', '$.shipping.third_day',      'disabled', false));

-- ── Seed: VAT rate (always use newest record) ────────────
INSERT INTO `enumeration` (`franchise_code`, `type`, `syscode`, `label`, `value`, `position`, `published`, `data`) VALUES
  ('zajeci', 'vat_rate', 'vat_21', 'DPH 21 %', 'vat_21', 10, 1, JSON_OBJECT('rate', 21.00));

-- ── Seed: payment methods (as enumerations) ──────────────
INSERT INTO `enumeration` (`franchise_code`, `type`, `syscode`, `label`, `value`, `position`, `published`, `data`) VALUES
  ('zajeci', 'payment', 'bank',       'Bankovní převod', 'bank',       10, 1, JSON_OBJECT('price', 0,    'icon', 'mdi:bank-outline',            'disabled', false, 'account', '2403322687/2010', 'iban', 'CZ0220100000002403322687', 'swift', 'FIOBCZPPXXX')),
  ('zajeci', 'payment', 'cash',       'Hotovost',        'cash',       20, 1, JSON_OBJECT('price', 0,    'icon', 'mdi:cash-100',                 'disabled', false)),
  ('zajeci', 'payment', 'card',       'Platební karta',  'card',       30, 1, JSON_OBJECT('price', 0,    'icon', 'mdi:credit-card-outline',      'disabled', true)),
  ('zajeci', 'payment', 'paypal',     'PayPal',          'paypal',     40, 1, JSON_OBJECT('price', 0,    'icon', 'logos:paypal',                 'disabled', true)),
  ('zajeci', 'payment', 'gopay',      'GoPay',           'gopay',      50, 1, JSON_OBJECT('price', 0,    'icon', 'arcticons:gopay',              'disabled', true)),
  ('zajeci', 'payment', 'apple_pay',  'Apple Pay',       'apple_pay',  60, 1, JSON_OBJECT('price', 0,    'icon', 'simple-icons:applepay',        'disabled', true)),
  ('zajeci', 'payment', 'google_pay', 'Google Pay',      'google_pay', 70, 1, JSON_OBJECT('price', 0,    'icon', 'simple-icons:googlepay',       'disabled', true));
-- ── Seed: contact info ───────────────────────────────────
INSERT INTO `enumeration` (`franchise_code`, `type`, `syscode`, `label`, `value`, `position`, `published`, `data`) VALUES
  ('zajeci', 'contact', 'contact', 'Kontakt', 'contact', 10, 1, JSON_OBJECT(
    'email',   'vyborne@vinozezajeci.cz',
    'phone1',  '+420 770 199 999',
    'phone2',  '+420 778 711 111',
    'street',  'Školní 156',
    'city',    'Zaječí',
    'zip',     '69105',
    'ic',     '19737491',
    'dic',    'CZ7951084053'
  ));
