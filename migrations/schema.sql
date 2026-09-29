-- schema: canonical definitions. Apply schema.sql before product schemas.
-- Run python3 scripts/build-schemas.py after editing CREATE definitions.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `enumeration` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'multi-tenant project key',
  `type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. order_status, invoice_status, payment_method',
  `syscode` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `position` smallint NOT NULL DEFAULT '0',
  `published` tinyint(1) NOT NULL DEFAULT '1',
  `data` json DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enum_franchise_type_syscode` (`franchise_code`,`type`,`syscode`),
  KEY `idx_enum_franchise` (`franchise_code`),
  KEY `idx_enum_type` (`type`),
  KEY `idx_enum_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_profile` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profile_number` smallint unsigned DEFAULT NULL,
  `syscode` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `selection_need` text COLLATE utf8mb4_unicode_ci,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `aura` text COLLATE utf8mb4_unicode_ci,
  `visual` text COLLATE utf8mb4_unicode_ci,
  `behavior` text COLLATE utf8mb4_unicode_ci,
  `business_potential` text COLLATE utf8mb4_unicode_ci,
  `typical_quote` text COLLATE utf8mb4_unicode_ci,
  `average_basket` decimal(12,2) DEFAULT NULL,
  `marketing_note` text COLLATE utf8mb4_unicode_ci,
  `position` smallint NOT NULL DEFAULT '0',
  `published` tinyint(1) NOT NULL DEFAULT '1',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_profile_tenant_syscode` (`franchise_code`,`syscode`),
  UNIQUE KEY `uq_customer_profile_tenant_number` (`franchise_code`,`profile_number`),
  KEY `idx_customer_profile_tenant` (`franchise_code`),
  KEY `idx_customer_profile_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_profile_question` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `customer_profile_id` int unsigned NOT NULL,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_profile_question_position` (`customer_profile_id`,`position`)
  -- deferred CONSTRAINT `fk_customer_profile_question_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_profile_objection` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `customer_profile_id` int unsigned NOT NULL,
  `objection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_profile_objection_position` (`customer_profile_id`,`position`)
  -- deferred CONSTRAINT `fk_customer_profile_objection_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_profile_preference` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `customer_profile_id` int unsigned NOT NULL,
  `preference_type` enum('animal','product_kind') COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`)
  -- deferred CONSTRAINT `fk_customer_profile_preference_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. admin, user, manager',
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_franchise_name` (`franchise_code`,`name`),
  KEY `idx_role_franchise` (`franchise_code`),
  KEY `idx_role_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` int unsigned NOT NULL COMMENT 'FK → role.id',
  `status` enum('active','inactive','banned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_franchise_email` (`franchise_code`,`email`),
  KEY `idx_user_franchise` (`franchise_code`),
  KEY `idx_user_role_id` (`role_id`),
  KEY `idx_user_deleted` (`deleted`)
  -- deferred CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_customer_profile` (
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `customer_profile_id` int unsigned NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`customer_profile_id`),
  UNIQUE KEY `uq_user_customer_profile_position` (`user_id`,`position`),
  KEY `idx_user_customer_profile_tenant` (`franchise_code`),
  KEY `idx_user_customer_profile_profile` (`customer_profile_id`)
  -- deferred CONSTRAINT `fk_user_customer_profile_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE
  -- deferred CONSTRAINT `fk_user_customer_profile_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `address` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `type` enum('billing','shipping') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'billing',
  `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zip` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CZ',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addr_franchise` (`franchise_code`),
  KEY `idx_addr_user` (`user_id`),
  KEY `idx_addr_deleted` (`deleted`)
  -- deferred CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_token` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_token_user` (`user_id`)
  -- deferred CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `syscode` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'machine-readable identifier, e.g. top, new, favourite',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `position` smallint NOT NULL DEFAULT '0',
  `published` tinyint(1) NOT NULL DEFAULT '1',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_franchise_syscode` (`franchise_code`,`syscode`),
  KEY `idx_cat_franchise` (`franchise_code`),
  KEY `idx_cat_parent` (`parent_id`),
  KEY `idx_cat_deleted` (`deleted`)
  -- deferred CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stock_quantity` int NOT NULL DEFAULT '0',
  `published` tinyint(1) NOT NULL DEFAULT '1',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `kind` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g. dry, sweet',
  `color` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g. white, red, rose',
  `variant` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'grape variety / product variant',
  `data` json DEFAULT NULL COMMENT 'extra attributes, e.g. quality, volume, year, batch',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_franchise_sku` (`franchise_code`,`sku`),
  KEY `idx_product_franchise` (`franchise_code`),
  KEY `idx_product_kind` (`kind`),
  KEY `idx_product_color` (`color`),
  KEY `idx_product_variant` (`variant`),
  KEY `idx_product_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_customer_profile_probability` (
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int unsigned NOT NULL,
  `customer_profile_id` int unsigned NOT NULL,
  `probability_percent` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`,`customer_profile_id`),
  KEY `idx_product_customer_profile_tenant` (`franchise_code`),
  KEY `idx_product_customer_profile_profile` (`customer_profile_id`)
  -- deferred CONSTRAINT `fk_product_customer_profile_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE
  -- deferred CONSTRAINT `fk_product_customer_profile_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE
  -- deferred CONSTRAINT `chk_product_customer_profile_probability` CHECK ((`probability_percent` between 0 and 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_alternative` (
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int unsigned NOT NULL,
  `alternative_product_id` int unsigned NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`,`alternative_product_id`),
  UNIQUE KEY `uq_product_alternative_position` (`product_id`,`position`),
  KEY `idx_product_alternative_tenant` (`franchise_code`),
  KEY `idx_product_alternative_product` (`alternative_product_id`)
  -- deferred CONSTRAINT `fk_product_alternative_source` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE
  -- deferred CONSTRAINT `fk_product_alternative_target` FOREIGN KEY (`alternative_product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE
  -- deferred CONSTRAINT `chk_product_alternative_different` CHECK ((`product_id` <> `alternative_product_id`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_category` (
  `product_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  PRIMARY KEY (`product_id`,`category_id`),
  KEY `idx_pc_category` (`category_id`)
  -- deferred CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE
  -- deferred CONSTRAINT `fk_pc_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `openai_vector_store` (
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vector_store_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`franchise_code`),
  UNIQUE KEY `uq_openai_vector_store_id` (`vector_store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `openai_vector_store_product` (
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int unsigned NOT NULL,
  `vector_store_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `openai_file_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`franchise_code`,`product_id`),
  UNIQUE KEY `uq_openai_vector_product_file` (`openai_file_id`),
  KEY `idx_openai_vector_product_store` (`vector_store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_file` (
  `product_id` int unsigned NOT NULL,
  `file_id` int unsigned NOT NULL,
  PRIMARY KEY (`product_id`,`file_id`),
  KEY `idx_pf_file` (`file_id`)
  -- deferred CONSTRAINT `fk_pf_file` FOREIGN KEY (`file_id`) REFERENCES `file` (`id`) ON DELETE CASCADE
  -- deferred CONSTRAINT `fk_pf_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `text` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `syscode` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `language` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cs',
  `published` tinyint(1) NOT NULL DEFAULT '1',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_text_franchise_syscode_lang` (`franchise_code`,`syscode`,`language`),
  KEY `idx_text_franchise` (`franchise_code`),
  KEY `idx_text_lang` (`language`),
  KEY `idx_text_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `customer` json DEFAULT NULL COMMENT 'guest customer and address snapshot',
  `status` enum('pending','confirmed','processing','shipped','delivered','cancelled','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `total_price` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'soucet order_items bez DPH',
  `total_price_with_vat` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'soucet order_items vcetne DPH',
  `total_price_all` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'total_price + shipping_price',
  `total_price_all_with_vat` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'total_price_with_vat + shipping_price',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CZK',
  `payment` json DEFAULT NULL COMMENT 'snapshot platebni metody {type,label,account,iban,swift,price,...}',
  `shipping` json DEFAULT NULL COMMENT 'snapshot zpusobu dopravy {type,label,price,icon,...}',
  `shipping_address_id` int unsigned DEFAULT NULL,
  `billing_address_id` int unsigned DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_franchise_number` (`franchise_code`,`order_number`),
  KEY `idx_order_franchise` (`franchise_code`),
  KEY `idx_order_user` (`user_id`),
  KEY `idx_order_status` (`status`),
  KEY `idx_order_deleted` (`deleted`),
  KEY `fk_order_ship` (`shipping_address_id`),
  KEY `fk_order_bill` (`billing_address_id`)
  -- deferred CONSTRAINT `fk_order_bill` FOREIGN KEY (`billing_address_id`) REFERENCES `address` (`id`) ON DELETE SET NULL
  -- deferred CONSTRAINT `fk_order_ship` FOREIGN KEY (`shipping_address_id`) REFERENCES `address` (`id`) ON DELETE SET NULL
  -- deferred CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `product_id` int unsigned DEFAULT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'snapshot nazvu produktu',
  `sku` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'snapshot SKU',
  `quantity` int NOT NULL DEFAULT '1',
  `price` decimal(12,2) NOT NULL COMMENT 'cena za kus bez DPH',
  `price_with_vat` decimal(12,2) NOT NULL COMMENT 'cena za kus vcetne DPH',
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'sazba DPH v %',
  `total_price` decimal(12,2) NOT NULL COMMENT 'price * quantity',
  `total_price_with_vat` decimal(12,2) NOT NULL COMMENT 'price_with_vat * quantity',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order` (`order_id`),
  KEY `idx_oi_product` (`product_id`)
  -- deferred CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE
  -- deferred CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` int unsigned DEFAULT NULL COMMENT 'reference na objednavku (soft FK)',
  `order_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'snapshot cisla objednavky',
  `status` enum('draft','issued','paid','overdue','cancelled','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'issued',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CZK',
  `payment` json DEFAULT NULL COMMENT 'snapshot platebni metody v dobe vystaveni',
  `shipping` json DEFAULT NULL COMMENT 'snapshot zpusobu dopravy v dobe vystaveni {type,label,price,...}',
  `total_price` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'soucet polozek bez DPH',
  `total_price_with_vat` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'soucet polozek vcetne DPH',
  `total_price_all` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'total_price + shipping_price',
  `total_price_all_with_vat` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'total_price_with_vat + shipping_price',
  `user` json DEFAULT NULL COMMENT 'snapshot uzivatele {id,first_name,last_name,email,phone}',
  `billing_address` json DEFAULT NULL COMMENT 'snapshot fakturacni adresy',
  `shipping_address` json DEFAULT NULL COMMENT 'snapshot dodaci adresy',
  `note` text COLLATE utf8mb4_unicode_ci,
  `issued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `due_at` date DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_franchise_number` (`franchise_code`,`invoice_number`),
  KEY `idx_inv_franchise` (`franchise_code`),
  KEY `idx_inv_order` (`order_id`),
  KEY `idx_inv_status` (`status`),
  KEY `idx_inv_deleted` (`deleted`)
  -- deferred CONSTRAINT `fk_inv_order` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'snapshot nazvu produktu',
  `sku` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'snapshot SKU',
  `quantity` int NOT NULL DEFAULT '1',
  `price` decimal(12,2) NOT NULL COMMENT 'cena za kus bez DPH',
  `price_with_vat` decimal(12,2) NOT NULL COMMENT 'cena za kus vcetne DPH',
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'sazba DPH v %',
  `total_price` decimal(12,2) NOT NULL COMMENT 'price * quantity',
  `total_price_with_vat` decimal(12,2) NOT NULL COMMENT 'price_with_vat * quantity',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ii_invoice` (`invoice_id`)
  -- deferred CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_file` (
  `invoice_id` int unsigned NOT NULL,
  `file_id` int unsigned NOT NULL,
  PRIMARY KEY (`invoice_id`,`file_id`),
  KEY `idx_if_file` (`file_id`)
  -- deferred CONSTRAINT `fk_if_file` FOREIGN KEY (`file_id`) REFERENCES `file` (`id`) ON DELETE CASCADE
  -- deferred CONSTRAINT `fk_if_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `file` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL COMMENT 'owner of user-uploaded file',
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'pripona: pdf, jpg, csv...',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'application/pdf, image/jpeg...',
  `path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'relativni cesta v /files/ po commitu',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'puvodni nazev souboru',
  `size` int unsigned NOT NULL DEFAULT '0' COMMENT 'velikost v bytech',
  `visibility` enum('public','private') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'private',
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'product, user, invoice...',
  `entity_id` int unsigned DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL COMMENT 'TTL pro tmp soubory, cron target',
  PRIMARY KEY (`id`),
  KEY `idx_file_franchise` (`franchise_code`),
  KEY `idx_file_user` (`user_id`),
  KEY `idx_file_entity` (`entity_type`,`entity_id`),
  KEY `idx_file_deleted` (`deleted`),
  KEY `idx_file_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oauth_identity` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `provider` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_oauth_tenant_provider_subject` (`franchise_code`,`provider`,`provider_subject`),
  KEY `idx_oauth_user` (`user_id`)
  -- deferred CONSTRAINT `fk_oauth_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_token` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`franchise_code`,`user_id`),
  KEY `idx_password_reset_expiry` (`expires_at`),
  KEY `fk_password_reset_user` (`user_id`)
  -- deferred CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_rate_limit` (
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `window_started_at` datetime NOT NULL,
  `attempts` int unsigned NOT NULL DEFAULT '1',
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`franchise_code`,`action`,`subject_hash`,`window_started_at`),
  KEY `idx_rate_limit_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BEGIN GENERATED ADDITIVE DDL (scripts/build-schemas.py)
-- Missing columns first, then indexes and foreign keys. Existing data stay intact.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='id'), 'ALTER TABLE `enumeration` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `enumeration` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT ''multi-tenant project key''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='type'), 'ALTER TABLE `enumeration` ADD COLUMN `type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT ''e.g. order_status, invoice_status, payment_method''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='syscode'), 'ALTER TABLE `enumeration` ADD COLUMN `syscode` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='label'), 'ALTER TABLE `enumeration` ADD COLUMN `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='value'), 'ALTER TABLE `enumeration` ADD COLUMN `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='position'), 'ALTER TABLE `enumeration` ADD COLUMN `position` smallint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='published'), 'ALTER TABLE `enumeration` ADD COLUMN `published` tinyint(1) NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='data'), 'ALTER TABLE `enumeration` ADD COLUMN `data` json DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='deleted'), 'ALTER TABLE `enumeration` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='created_at'), 'ALTER TABLE `enumeration` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `enumeration` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='id'), 'ALTER TABLE `customer_profile` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `customer_profile` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='profile_number'), 'ALTER TABLE `customer_profile` ADD COLUMN `profile_number` smallint unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='syscode'), 'ALTER TABLE `customer_profile` ADD COLUMN `syscode` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='name'), 'ALTER TABLE `customer_profile` ADD COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='selection_need'), 'ALTER TABLE `customer_profile` ADD COLUMN `selection_need` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='summary'), 'ALTER TABLE `customer_profile` ADD COLUMN `summary` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='aura'), 'ALTER TABLE `customer_profile` ADD COLUMN `aura` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='visual'), 'ALTER TABLE `customer_profile` ADD COLUMN `visual` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='behavior'), 'ALTER TABLE `customer_profile` ADD COLUMN `behavior` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='business_potential'), 'ALTER TABLE `customer_profile` ADD COLUMN `business_potential` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='typical_quote'), 'ALTER TABLE `customer_profile` ADD COLUMN `typical_quote` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='average_basket'), 'ALTER TABLE `customer_profile` ADD COLUMN `average_basket` decimal(12,2) DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='marketing_note'), 'ALTER TABLE `customer_profile` ADD COLUMN `marketing_note` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='position'), 'ALTER TABLE `customer_profile` ADD COLUMN `position` smallint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='published'), 'ALTER TABLE `customer_profile` ADD COLUMN `published` tinyint(1) NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='deleted'), 'ALTER TABLE `customer_profile` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='created_at'), 'ALTER TABLE `customer_profile` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `customer_profile` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_question' AND COLUMN_NAME='id'), 'ALTER TABLE `customer_profile_question` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_question' AND COLUMN_NAME='customer_profile_id'), 'ALTER TABLE `customer_profile_question` ADD COLUMN `customer_profile_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_question' AND COLUMN_NAME='question'), 'ALTER TABLE `customer_profile_question` ADD COLUMN `question` text COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_question' AND COLUMN_NAME='position'), 'ALTER TABLE `customer_profile_question` ADD COLUMN `position` smallint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_objection' AND COLUMN_NAME='id'), 'ALTER TABLE `customer_profile_objection` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_objection' AND COLUMN_NAME='customer_profile_id'), 'ALTER TABLE `customer_profile_objection` ADD COLUMN `customer_profile_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_objection' AND COLUMN_NAME='objection'), 'ALTER TABLE `customer_profile_objection` ADD COLUMN `objection` text COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_objection' AND COLUMN_NAME='position'), 'ALTER TABLE `customer_profile_objection` ADD COLUMN `position` smallint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_preference' AND COLUMN_NAME='id'), 'ALTER TABLE `customer_profile_preference` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_preference' AND COLUMN_NAME='customer_profile_id'), 'ALTER TABLE `customer_profile_preference` ADD COLUMN `customer_profile_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_preference' AND COLUMN_NAME='preference_type'), 'ALTER TABLE `customer_profile_preference` ADD COLUMN `preference_type` enum(''animal'',''product_kind'') COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_preference' AND COLUMN_NAME='value'), 'ALTER TABLE `customer_profile_preference` ADD COLUMN `value` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_preference' AND COLUMN_NAME='position'), 'ALTER TABLE `customer_profile_preference` ADD COLUMN `position` smallint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND COLUMN_NAME='id'), 'ALTER TABLE `role` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `role` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND COLUMN_NAME='name'), 'ALTER TABLE `role` ADD COLUMN `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT ''e.g. admin, user, manager''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND COLUMN_NAME='label'), 'ALTER TABLE `role` ADD COLUMN `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND COLUMN_NAME='position'), 'ALTER TABLE `role` ADD COLUMN `position` smallint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND COLUMN_NAME='deleted'), 'ALTER TABLE `role` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND COLUMN_NAME='created_at'), 'ALTER TABLE `role` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `role` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='id'), 'ALTER TABLE `user` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `user` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='first_name'), 'ALTER TABLE `user` ADD COLUMN `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='last_name'), 'ALTER TABLE `user` ADD COLUMN `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='email'), 'ALTER TABLE `user` ADD COLUMN `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='phone'), 'ALTER TABLE `user` ADD COLUMN `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='password'), 'ALTER TABLE `user` ADD COLUMN `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='role_id'), 'ALTER TABLE `user` ADD COLUMN `role_id` int unsigned NOT NULL COMMENT ''FK → role.id''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='status'), 'ALTER TABLE `user` ADD COLUMN `status` enum(''active'',''inactive'',''banned'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''active''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='last_login_at'), 'ALTER TABLE `user` ADD COLUMN `last_login_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='deleted'), 'ALTER TABLE `user` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='created_at'), 'ALTER TABLE `user` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `user` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `user_customer_profile` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND COLUMN_NAME='user_id'), 'ALTER TABLE `user_customer_profile` ADD COLUMN `user_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND COLUMN_NAME='customer_profile_id'), 'ALTER TABLE `user_customer_profile` ADD COLUMN `customer_profile_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND COLUMN_NAME='position'), 'ALTER TABLE `user_customer_profile` ADD COLUMN `position` smallint unsigned NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND COLUMN_NAME='created_at'), 'ALTER TABLE `user_customer_profile` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `user_customer_profile` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='id'), 'ALTER TABLE `address` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `address` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='user_id'), 'ALTER TABLE `address` ADD COLUMN `user_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='type'), 'ALTER TABLE `address` ADD COLUMN `type` enum(''billing'',''shipping'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''billing''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='company'), 'ALTER TABLE `address` ADD COLUMN `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='name'), 'ALTER TABLE `address` ADD COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='street'), 'ALTER TABLE `address` ADD COLUMN `street` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='city'), 'ALTER TABLE `address` ADD COLUMN `city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='zip'), 'ALTER TABLE `address` ADD COLUMN `zip` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='country'), 'ALTER TABLE `address` ADD COLUMN `country` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''CZ''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='is_default'), 'ALTER TABLE `address` ADD COLUMN `is_default` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='deleted'), 'ALTER TABLE `address` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='created_at'), 'ALTER TABLE `address` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `address` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_token' AND COLUMN_NAME='id'), 'ALTER TABLE `user_token` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_token' AND COLUMN_NAME='user_id'), 'ALTER TABLE `user_token` ADD COLUMN `user_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_token' AND COLUMN_NAME='token'), 'ALTER TABLE `user_token` ADD COLUMN `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_token' AND COLUMN_NAME='expires_at'), 'ALTER TABLE `user_token` ADD COLUMN `expires_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_token' AND COLUMN_NAME='deleted'), 'ALTER TABLE `user_token` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_token' AND COLUMN_NAME='created_at'), 'ALTER TABLE `user_token` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_token' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `user_token` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND COLUMN_NAME='id'), 'ALTER TABLE `category` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `category` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND COLUMN_NAME='parent_id'), 'ALTER TABLE `category` ADD COLUMN `parent_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND COLUMN_NAME='syscode'), 'ALTER TABLE `category` ADD COLUMN `syscode` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''machine-readable identifier, e.g. top, new, favourite''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND COLUMN_NAME='name'), 'ALTER TABLE `category` ADD COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND COLUMN_NAME='description'), 'ALTER TABLE `category` ADD COLUMN `description` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND COLUMN_NAME='position'), 'ALTER TABLE `category` ADD COLUMN `position` smallint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND COLUMN_NAME='published'), 'ALTER TABLE `category` ADD COLUMN `published` tinyint(1) NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND COLUMN_NAME='deleted'), 'ALTER TABLE `category` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND COLUMN_NAME='created_at'), 'ALTER TABLE `category` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `category` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='id'), 'ALTER TABLE `product` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `product` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='sku'), 'ALTER TABLE `product` ADD COLUMN `sku` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='name'), 'ALTER TABLE `product` ADD COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='description'), 'ALTER TABLE `product` ADD COLUMN `description` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='price'), 'ALTER TABLE `product` ADD COLUMN `price` decimal(12,2) NOT NULL DEFAULT ''0.00''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='stock_quantity'), 'ALTER TABLE `product` ADD COLUMN `stock_quantity` int NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='published'), 'ALTER TABLE `product` ADD COLUMN `published` tinyint(1) NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='deleted'), 'ALTER TABLE `product` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='kind'), 'ALTER TABLE `product` ADD COLUMN `kind` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''e.g. dry, sweet''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='color'), 'ALTER TABLE `product` ADD COLUMN `color` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''e.g. white, red, rose''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='variant'), 'ALTER TABLE `product` ADD COLUMN `variant` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''grape variety / product variant''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='data'), 'ALTER TABLE `product` ADD COLUMN `data` json DEFAULT NULL COMMENT ''extra attributes, e.g. quality, volume, year, batch''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='created_at'), 'ALTER TABLE `product` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `product` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `product_customer_profile_probability` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND COLUMN_NAME='product_id'), 'ALTER TABLE `product_customer_profile_probability` ADD COLUMN `product_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND COLUMN_NAME='customer_profile_id'), 'ALTER TABLE `product_customer_profile_probability` ADD COLUMN `customer_profile_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND COLUMN_NAME='probability_percent'), 'ALTER TABLE `product_customer_profile_probability` ADD COLUMN `probability_percent` tinyint unsigned NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND COLUMN_NAME='created_at'), 'ALTER TABLE `product_customer_profile_probability` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `product_customer_profile_probability` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `product_alternative` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND COLUMN_NAME='product_id'), 'ALTER TABLE `product_alternative` ADD COLUMN `product_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND COLUMN_NAME='alternative_product_id'), 'ALTER TABLE `product_alternative` ADD COLUMN `alternative_product_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND COLUMN_NAME='position'), 'ALTER TABLE `product_alternative` ADD COLUMN `position` smallint unsigned NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND COLUMN_NAME='created_at'), 'ALTER TABLE `product_alternative` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `product_alternative` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_category' AND COLUMN_NAME='product_id'), 'ALTER TABLE `product_category` ADD COLUMN `product_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_category' AND COLUMN_NAME='category_id'), 'ALTER TABLE `product_category` ADD COLUMN `category_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `openai_vector_store` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store' AND COLUMN_NAME='vector_store_id'), 'ALTER TABLE `openai_vector_store` ADD COLUMN `vector_store_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store' AND COLUMN_NAME='name'), 'ALTER TABLE `openai_vector_store` ADD COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store' AND COLUMN_NAME='created_at'), 'ALTER TABLE `openai_vector_store` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `openai_vector_store` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store_product' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `openai_vector_store_product` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store_product' AND COLUMN_NAME='product_id'), 'ALTER TABLE `openai_vector_store_product` ADD COLUMN `product_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store_product' AND COLUMN_NAME='vector_store_id'), 'ALTER TABLE `openai_vector_store_product` ADD COLUMN `vector_store_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store_product' AND COLUMN_NAME='openai_file_id'), 'ALTER TABLE `openai_vector_store_product` ADD COLUMN `openai_file_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store_product' AND COLUMN_NAME='source_hash'), 'ALTER TABLE `openai_vector_store_product` ADD COLUMN `source_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store_product' AND COLUMN_NAME='created_at'), 'ALTER TABLE `openai_vector_store_product` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store_product' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `openai_vector_store_product` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_file' AND COLUMN_NAME='product_id'), 'ALTER TABLE `product_file` ADD COLUMN `product_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_file' AND COLUMN_NAME='file_id'), 'ALTER TABLE `product_file` ADD COLUMN `file_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND COLUMN_NAME='id'), 'ALTER TABLE `text` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `text` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND COLUMN_NAME='syscode'), 'ALTER TABLE `text` ADD COLUMN `syscode` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND COLUMN_NAME='title'), 'ALTER TABLE `text` ADD COLUMN `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND COLUMN_NAME='content'), 'ALTER TABLE `text` ADD COLUMN `content` longtext COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND COLUMN_NAME='language'), 'ALTER TABLE `text` ADD COLUMN `language` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''cs''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND COLUMN_NAME='published'), 'ALTER TABLE `text` ADD COLUMN `published` tinyint(1) NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND COLUMN_NAME='deleted'), 'ALTER TABLE `text` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND COLUMN_NAME='created_at'), 'ALTER TABLE `text` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `text` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='id'), 'ALTER TABLE `order` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `order` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='order_number'), 'ALTER TABLE `order` ADD COLUMN `order_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='user_id'), 'ALTER TABLE `order` ADD COLUMN `user_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='customer'), 'ALTER TABLE `order` ADD COLUMN `customer` json DEFAULT NULL COMMENT ''guest customer and address snapshot''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='status'), 'ALTER TABLE `order` ADD COLUMN `status` enum(''pending'',''confirmed'',''processing'',''shipped'',''delivered'',''cancelled'',''refunded'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''pending''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='total_price'), 'ALTER TABLE `order` ADD COLUMN `total_price` decimal(12,2) NOT NULL DEFAULT ''0.00'' COMMENT ''soucet order_items bez DPH''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='total_price_with_vat'), 'ALTER TABLE `order` ADD COLUMN `total_price_with_vat` decimal(12,2) NOT NULL DEFAULT ''0.00'' COMMENT ''soucet order_items vcetne DPH''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='total_price_all'), 'ALTER TABLE `order` ADD COLUMN `total_price_all` decimal(12,2) NOT NULL DEFAULT ''0.00'' COMMENT ''total_price + shipping_price''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='total_price_all_with_vat'), 'ALTER TABLE `order` ADD COLUMN `total_price_all_with_vat` decimal(12,2) NOT NULL DEFAULT ''0.00'' COMMENT ''total_price_with_vat + shipping_price''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='currency'), 'ALTER TABLE `order` ADD COLUMN `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''CZK''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='payment'), 'ALTER TABLE `order` ADD COLUMN `payment` json DEFAULT NULL COMMENT ''snapshot platebni metody {type,label,account,iban,swift,price,...}''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='shipping'), 'ALTER TABLE `order` ADD COLUMN `shipping` json DEFAULT NULL COMMENT ''snapshot zpusobu dopravy {type,label,price,icon,...}''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='shipping_address_id'), 'ALTER TABLE `order` ADD COLUMN `shipping_address_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='billing_address_id'), 'ALTER TABLE `order` ADD COLUMN `billing_address_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='note'), 'ALTER TABLE `order` ADD COLUMN `note` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='deleted'), 'ALTER TABLE `order` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='created_at'), 'ALTER TABLE `order` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `order` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='id'), 'ALTER TABLE `order_item` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='order_id'), 'ALTER TABLE `order_item` ADD COLUMN `order_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='product_id'), 'ALTER TABLE `order_item` ADD COLUMN `product_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='product_name'), 'ALTER TABLE `order_item` ADD COLUMN `product_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''snapshot nazvu produktu''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='sku'), 'ALTER TABLE `order_item` ADD COLUMN `sku` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''snapshot SKU''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='quantity'), 'ALTER TABLE `order_item` ADD COLUMN `quantity` int NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='price'), 'ALTER TABLE `order_item` ADD COLUMN `price` decimal(12,2) NOT NULL COMMENT ''cena za kus bez DPH''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='price_with_vat'), 'ALTER TABLE `order_item` ADD COLUMN `price_with_vat` decimal(12,2) NOT NULL COMMENT ''cena za kus vcetne DPH''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='vat_rate'), 'ALTER TABLE `order_item` ADD COLUMN `vat_rate` decimal(5,2) NOT NULL DEFAULT ''0.00'' COMMENT ''sazba DPH v %''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='total_price'), 'ALTER TABLE `order_item` ADD COLUMN `total_price` decimal(12,2) NOT NULL COMMENT ''price * quantity''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='total_price_with_vat'), 'ALTER TABLE `order_item` ADD COLUMN `total_price_with_vat` decimal(12,2) NOT NULL COMMENT ''price_with_vat * quantity''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='deleted'), 'ALTER TABLE `order_item` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='created_at'), 'ALTER TABLE `order_item` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `order_item` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='id'), 'ALTER TABLE `invoice` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `invoice` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='invoice_number'), 'ALTER TABLE `invoice` ADD COLUMN `invoice_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='order_id'), 'ALTER TABLE `invoice` ADD COLUMN `order_id` int unsigned DEFAULT NULL COMMENT ''reference na objednavku (soft FK)''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='order_number'), 'ALTER TABLE `invoice` ADD COLUMN `order_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''snapshot cisla objednavky''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='status'), 'ALTER TABLE `invoice` ADD COLUMN `status` enum(''draft'',''issued'',''paid'',''overdue'',''cancelled'',''refunded'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''issued''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='currency'), 'ALTER TABLE `invoice` ADD COLUMN `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''CZK''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='payment'), 'ALTER TABLE `invoice` ADD COLUMN `payment` json DEFAULT NULL COMMENT ''snapshot platebni metody v dobe vystaveni''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='shipping'), 'ALTER TABLE `invoice` ADD COLUMN `shipping` json DEFAULT NULL COMMENT ''snapshot zpusobu dopravy v dobe vystaveni {type,label,price,...}''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='total_price'), 'ALTER TABLE `invoice` ADD COLUMN `total_price` decimal(12,2) NOT NULL DEFAULT ''0.00'' COMMENT ''soucet polozek bez DPH''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='total_price_with_vat'), 'ALTER TABLE `invoice` ADD COLUMN `total_price_with_vat` decimal(12,2) NOT NULL DEFAULT ''0.00'' COMMENT ''soucet polozek vcetne DPH''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='total_price_all'), 'ALTER TABLE `invoice` ADD COLUMN `total_price_all` decimal(12,2) NOT NULL DEFAULT ''0.00'' COMMENT ''total_price + shipping_price''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='total_price_all_with_vat'), 'ALTER TABLE `invoice` ADD COLUMN `total_price_all_with_vat` decimal(12,2) NOT NULL DEFAULT ''0.00'' COMMENT ''total_price_with_vat + shipping_price''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='user'), 'ALTER TABLE `invoice` ADD COLUMN `user` json DEFAULT NULL COMMENT ''snapshot uzivatele {id,first_name,last_name,email,phone}''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='billing_address'), 'ALTER TABLE `invoice` ADD COLUMN `billing_address` json DEFAULT NULL COMMENT ''snapshot fakturacni adresy''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='shipping_address'), 'ALTER TABLE `invoice` ADD COLUMN `shipping_address` json DEFAULT NULL COMMENT ''snapshot dodaci adresy''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='note'), 'ALTER TABLE `invoice` ADD COLUMN `note` text COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='issued_at'), 'ALTER TABLE `invoice` ADD COLUMN `issued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='due_at'), 'ALTER TABLE `invoice` ADD COLUMN `due_at` date DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='paid_at'), 'ALTER TABLE `invoice` ADD COLUMN `paid_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='deleted'), 'ALTER TABLE `invoice` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='created_at'), 'ALTER TABLE `invoice` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `invoice` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='id'), 'ALTER TABLE `invoice_item` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='invoice_id'), 'ALTER TABLE `invoice_item` ADD COLUMN `invoice_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='product_name'), 'ALTER TABLE `invoice_item` ADD COLUMN `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '''' COMMENT ''snapshot nazvu produktu''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='sku'), 'ALTER TABLE `invoice_item` ADD COLUMN `sku` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''snapshot SKU''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='quantity'), 'ALTER TABLE `invoice_item` ADD COLUMN `quantity` int NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='price'), 'ALTER TABLE `invoice_item` ADD COLUMN `price` decimal(12,2) NOT NULL COMMENT ''cena za kus bez DPH''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='price_with_vat'), 'ALTER TABLE `invoice_item` ADD COLUMN `price_with_vat` decimal(12,2) NOT NULL COMMENT ''cena za kus vcetne DPH''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='vat_rate'), 'ALTER TABLE `invoice_item` ADD COLUMN `vat_rate` decimal(5,2) NOT NULL DEFAULT ''0.00'' COMMENT ''sazba DPH v %''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='total_price'), 'ALTER TABLE `invoice_item` ADD COLUMN `total_price` decimal(12,2) NOT NULL COMMENT ''price * quantity''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='total_price_with_vat'), 'ALTER TABLE `invoice_item` ADD COLUMN `total_price_with_vat` decimal(12,2) NOT NULL COMMENT ''price_with_vat * quantity''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='deleted'), 'ALTER TABLE `invoice_item` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='created_at'), 'ALTER TABLE `invoice_item` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `invoice_item` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_file' AND COLUMN_NAME='invoice_id'), 'ALTER TABLE `invoice_file` ADD COLUMN `invoice_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_file' AND COLUMN_NAME='file_id'), 'ALTER TABLE `invoice_file` ADD COLUMN `file_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='id'), 'ALTER TABLE `file` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `file` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='user_id'), 'ALTER TABLE `file` ADD COLUMN `user_id` int unsigned DEFAULT NULL COMMENT ''owner of user-uploaded file''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='type'), 'ALTER TABLE `file` ADD COLUMN `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT ''pripona: pdf, jpg, csv...''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='mime_type'), 'ALTER TABLE `file` ADD COLUMN `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT ''application/pdf, image/jpeg...''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='path'), 'ALTER TABLE `file` ADD COLUMN `path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT ''relativni cesta v /files/ po commitu''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='name'), 'ALTER TABLE `file` ADD COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT ''puvodni nazev souboru''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='size'), 'ALTER TABLE `file` ADD COLUMN `size` int unsigned NOT NULL DEFAULT ''0'' COMMENT ''velikost v bytech''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='visibility'), 'ALTER TABLE `file` ADD COLUMN `visibility` enum(''public'',''private'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''private''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='entity_type'), 'ALTER TABLE `file` ADD COLUMN `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''product, user, invoice...''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='entity_id'), 'ALTER TABLE `file` ADD COLUMN `entity_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='deleted'), 'ALTER TABLE `file` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='created_at'), 'ALTER TABLE `file` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `file` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND COLUMN_NAME='expires_at'), 'ALTER TABLE `file` ADD COLUMN `expires_at` datetime DEFAULT NULL COMMENT ''TTL pro tmp soubory, cron target''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND COLUMN_NAME='id'), 'ALTER TABLE `oauth_identity` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `oauth_identity` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND COLUMN_NAME='user_id'), 'ALTER TABLE `oauth_identity` ADD COLUMN `user_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND COLUMN_NAME='provider'), 'ALTER TABLE `oauth_identity` ADD COLUMN `provider` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND COLUMN_NAME='provider_subject'), 'ALTER TABLE `oauth_identity` ADD COLUMN `provider_subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND COLUMN_NAME='email'), 'ALTER TABLE `oauth_identity` ADD COLUMN `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND COLUMN_NAME='created_at'), 'ALTER TABLE `oauth_identity` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `oauth_identity` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND COLUMN_NAME='id'), 'ALTER TABLE `password_reset_token` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `password_reset_token` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND COLUMN_NAME='user_id'), 'ALTER TABLE `password_reset_token` ADD COLUMN `user_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND COLUMN_NAME='token_hash'), 'ALTER TABLE `password_reset_token` ADD COLUMN `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND COLUMN_NAME='expires_at'), 'ALTER TABLE `password_reset_token` ADD COLUMN `expires_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND COLUMN_NAME='used_at'), 'ALTER TABLE `password_reset_token` ADD COLUMN `used_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND COLUMN_NAME='created_at'), 'ALTER TABLE `password_reset_token` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_rate_limit' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `api_rate_limit` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_rate_limit' AND COLUMN_NAME='action'), 'ALTER TABLE `api_rate_limit` ADD COLUMN `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_rate_limit' AND COLUMN_NAME='subject_hash'), 'ALTER TABLE `api_rate_limit` ADD COLUMN `subject_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_rate_limit' AND COLUMN_NAME='window_started_at'), 'ALTER TABLE `api_rate_limit` ADD COLUMN `window_started_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_rate_limit' AND COLUMN_NAME='attempts'), 'ALTER TABLE `api_rate_limit` ADD COLUMN `attempts` int unsigned NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_rate_limit' AND COLUMN_NAME='expires_at'), 'ALTER TABLE `api_rate_limit` ADD COLUMN `expires_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

-- Missing indexes. Conflicting existing rows cause an error, never data removal.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `enumeration` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND INDEX_NAME='uq_enum_franchise_type_syscode'), 'ALTER TABLE `enumeration` ADD UNIQUE KEY `uq_enum_franchise_type_syscode` (`franchise_code`,`type`,`syscode`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND INDEX_NAME='idx_enum_franchise'), 'ALTER TABLE `enumeration` ADD KEY `idx_enum_franchise` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND INDEX_NAME='idx_enum_type'), 'ALTER TABLE `enumeration` ADD KEY `idx_enum_type` (`type`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enumeration' AND INDEX_NAME='idx_enum_deleted'), 'ALTER TABLE `enumeration` ADD KEY `idx_enum_deleted` (`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `customer_profile` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND INDEX_NAME='uq_customer_profile_tenant_syscode'), 'ALTER TABLE `customer_profile` ADD UNIQUE KEY `uq_customer_profile_tenant_syscode` (`franchise_code`,`syscode`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND INDEX_NAME='uq_customer_profile_tenant_number'), 'ALTER TABLE `customer_profile` ADD UNIQUE KEY `uq_customer_profile_tenant_number` (`franchise_code`,`profile_number`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND INDEX_NAME='idx_customer_profile_tenant'), 'ALTER TABLE `customer_profile` ADD KEY `idx_customer_profile_tenant` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile' AND INDEX_NAME='idx_customer_profile_deleted'), 'ALTER TABLE `customer_profile` ADD KEY `idx_customer_profile_deleted` (`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_question' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `customer_profile_question` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_question' AND INDEX_NAME='uq_customer_profile_question_position'), 'ALTER TABLE `customer_profile_question` ADD UNIQUE KEY `uq_customer_profile_question_position` (`customer_profile_id`,`position`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_objection' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `customer_profile_objection` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_objection' AND INDEX_NAME='uq_customer_profile_objection_position'), 'ALTER TABLE `customer_profile_objection` ADD UNIQUE KEY `uq_customer_profile_objection_position` (`customer_profile_id`,`position`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_preference' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `customer_profile_preference` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_preference' AND INDEX_NAME='uq_customer_profile_preference'), 'ALTER TABLE `customer_profile_preference` ADD UNIQUE KEY `uq_customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `role` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND INDEX_NAME='uq_role_franchise_name'), 'ALTER TABLE `role` ADD UNIQUE KEY `uq_role_franchise_name` (`franchise_code`,`name`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND INDEX_NAME='idx_role_franchise'), 'ALTER TABLE `role` ADD KEY `idx_role_franchise` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role' AND INDEX_NAME='idx_role_deleted'), 'ALTER TABLE `role` ADD KEY `idx_role_deleted` (`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `user` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND INDEX_NAME='uq_user_franchise_email'), 'ALTER TABLE `user` ADD UNIQUE KEY `uq_user_franchise_email` (`franchise_code`,`email`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND INDEX_NAME='idx_user_franchise'), 'ALTER TABLE `user` ADD KEY `idx_user_franchise` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND INDEX_NAME='idx_user_role_id'), 'ALTER TABLE `user` ADD KEY `idx_user_role_id` (`role_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND INDEX_NAME='idx_user_deleted'), 'ALTER TABLE `user` ADD KEY `idx_user_deleted` (`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `user_customer_profile` ADD PRIMARY KEY (`user_id`,`customer_profile_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND INDEX_NAME='uq_user_customer_profile_position'), 'ALTER TABLE `user_customer_profile` ADD UNIQUE KEY `uq_user_customer_profile_position` (`user_id`,`position`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND INDEX_NAME='idx_user_customer_profile_tenant'), 'ALTER TABLE `user_customer_profile` ADD KEY `idx_user_customer_profile_tenant` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND INDEX_NAME='idx_user_customer_profile_profile'), 'ALTER TABLE `user_customer_profile` ADD KEY `idx_user_customer_profile_profile` (`customer_profile_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `address` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND INDEX_NAME='idx_addr_franchise'), 'ALTER TABLE `address` ADD KEY `idx_addr_franchise` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND INDEX_NAME='idx_addr_user'), 'ALTER TABLE `address` ADD KEY `idx_addr_user` (`user_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND INDEX_NAME='idx_addr_deleted'), 'ALTER TABLE `address` ADD KEY `idx_addr_deleted` (`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_token' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `user_token` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_token' AND INDEX_NAME='uq_token'), 'ALTER TABLE `user_token` ADD UNIQUE KEY `uq_token` (`token`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_token' AND INDEX_NAME='idx_token_user'), 'ALTER TABLE `user_token` ADD KEY `idx_token_user` (`user_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `category` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND INDEX_NAME='uq_cat_franchise_syscode'), 'ALTER TABLE `category` ADD UNIQUE KEY `uq_cat_franchise_syscode` (`franchise_code`,`syscode`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND INDEX_NAME='idx_cat_franchise'), 'ALTER TABLE `category` ADD KEY `idx_cat_franchise` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND INDEX_NAME='idx_cat_parent'), 'ALTER TABLE `category` ADD KEY `idx_cat_parent` (`parent_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND INDEX_NAME='idx_cat_deleted'), 'ALTER TABLE `category` ADD KEY `idx_cat_deleted` (`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `product` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND INDEX_NAME='uq_product_franchise_sku'), 'ALTER TABLE `product` ADD UNIQUE KEY `uq_product_franchise_sku` (`franchise_code`,`sku`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND INDEX_NAME='idx_product_franchise'), 'ALTER TABLE `product` ADD KEY `idx_product_franchise` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND INDEX_NAME='idx_product_kind'), 'ALTER TABLE `product` ADD KEY `idx_product_kind` (`kind`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND INDEX_NAME='idx_product_color'), 'ALTER TABLE `product` ADD KEY `idx_product_color` (`color`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND INDEX_NAME='idx_product_variant'), 'ALTER TABLE `product` ADD KEY `idx_product_variant` (`variant`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product' AND INDEX_NAME='idx_product_deleted'), 'ALTER TABLE `product` ADD KEY `idx_product_deleted` (`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `product_customer_profile_probability` ADD PRIMARY KEY (`product_id`,`customer_profile_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND INDEX_NAME='idx_product_customer_profile_tenant'), 'ALTER TABLE `product_customer_profile_probability` ADD KEY `idx_product_customer_profile_tenant` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND INDEX_NAME='idx_product_customer_profile_profile'), 'ALTER TABLE `product_customer_profile_probability` ADD KEY `idx_product_customer_profile_profile` (`customer_profile_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `product_alternative` ADD PRIMARY KEY (`product_id`,`alternative_product_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND INDEX_NAME='uq_product_alternative_position'), 'ALTER TABLE `product_alternative` ADD UNIQUE KEY `uq_product_alternative_position` (`product_id`,`position`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND INDEX_NAME='idx_product_alternative_tenant'), 'ALTER TABLE `product_alternative` ADD KEY `idx_product_alternative_tenant` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND INDEX_NAME='idx_product_alternative_product'), 'ALTER TABLE `product_alternative` ADD KEY `idx_product_alternative_product` (`alternative_product_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_category' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `product_category` ADD PRIMARY KEY (`product_id`,`category_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_category' AND INDEX_NAME='idx_pc_category'), 'ALTER TABLE `product_category` ADD KEY `idx_pc_category` (`category_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `openai_vector_store` ADD PRIMARY KEY (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store' AND INDEX_NAME='uq_openai_vector_store_id'), 'ALTER TABLE `openai_vector_store` ADD UNIQUE KEY `uq_openai_vector_store_id` (`vector_store_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store_product' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `openai_vector_store_product` ADD PRIMARY KEY (`franchise_code`,`product_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store_product' AND INDEX_NAME='uq_openai_vector_product_file'), 'ALTER TABLE `openai_vector_store_product` ADD UNIQUE KEY `uq_openai_vector_product_file` (`openai_file_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='openai_vector_store_product' AND INDEX_NAME='idx_openai_vector_product_store'), 'ALTER TABLE `openai_vector_store_product` ADD KEY `idx_openai_vector_product_store` (`vector_store_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_file' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `product_file` ADD PRIMARY KEY (`product_id`,`file_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_file' AND INDEX_NAME='idx_pf_file'), 'ALTER TABLE `product_file` ADD KEY `idx_pf_file` (`file_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `text` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND INDEX_NAME='uq_text_franchise_syscode_lang'), 'ALTER TABLE `text` ADD UNIQUE KEY `uq_text_franchise_syscode_lang` (`franchise_code`,`syscode`,`language`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND INDEX_NAME='idx_text_franchise'), 'ALTER TABLE `text` ADD KEY `idx_text_franchise` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND INDEX_NAME='idx_text_lang'), 'ALTER TABLE `text` ADD KEY `idx_text_lang` (`language`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='text' AND INDEX_NAME='idx_text_deleted'), 'ALTER TABLE `text` ADD KEY `idx_text_deleted` (`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `order` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND INDEX_NAME='uq_order_franchise_number'), 'ALTER TABLE `order` ADD UNIQUE KEY `uq_order_franchise_number` (`franchise_code`,`order_number`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND INDEX_NAME='idx_order_franchise'), 'ALTER TABLE `order` ADD KEY `idx_order_franchise` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND INDEX_NAME='idx_order_user'), 'ALTER TABLE `order` ADD KEY `idx_order_user` (`user_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND INDEX_NAME='idx_order_status'), 'ALTER TABLE `order` ADD KEY `idx_order_status` (`status`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND INDEX_NAME='idx_order_deleted'), 'ALTER TABLE `order` ADD KEY `idx_order_deleted` (`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND INDEX_NAME='fk_order_ship'), 'ALTER TABLE `order` ADD KEY `fk_order_ship` (`shipping_address_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order' AND INDEX_NAME='fk_order_bill'), 'ALTER TABLE `order` ADD KEY `fk_order_bill` (`billing_address_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `order_item` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND INDEX_NAME='idx_oi_order'), 'ALTER TABLE `order_item` ADD KEY `idx_oi_order` (`order_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND INDEX_NAME='idx_oi_product'), 'ALTER TABLE `order_item` ADD KEY `idx_oi_product` (`product_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `invoice` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND INDEX_NAME='uq_inv_franchise_number'), 'ALTER TABLE `invoice` ADD UNIQUE KEY `uq_inv_franchise_number` (`franchise_code`,`invoice_number`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND INDEX_NAME='idx_inv_franchise'), 'ALTER TABLE `invoice` ADD KEY `idx_inv_franchise` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND INDEX_NAME='idx_inv_order'), 'ALTER TABLE `invoice` ADD KEY `idx_inv_order` (`order_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND INDEX_NAME='idx_inv_status'), 'ALTER TABLE `invoice` ADD KEY `idx_inv_status` (`status`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND INDEX_NAME='idx_inv_deleted'), 'ALTER TABLE `invoice` ADD KEY `idx_inv_deleted` (`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `invoice_item` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND INDEX_NAME='idx_ii_invoice'), 'ALTER TABLE `invoice_item` ADD KEY `idx_ii_invoice` (`invoice_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_file' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `invoice_file` ADD PRIMARY KEY (`invoice_id`,`file_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoice_file' AND INDEX_NAME='idx_if_file'), 'ALTER TABLE `invoice_file` ADD KEY `idx_if_file` (`file_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `file` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND INDEX_NAME='idx_file_franchise'), 'ALTER TABLE `file` ADD KEY `idx_file_franchise` (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND INDEX_NAME='idx_file_user'), 'ALTER TABLE `file` ADD KEY `idx_file_user` (`user_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND INDEX_NAME='idx_file_entity'), 'ALTER TABLE `file` ADD KEY `idx_file_entity` (`entity_type`,`entity_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND INDEX_NAME='idx_file_deleted'), 'ALTER TABLE `file` ADD KEY `idx_file_deleted` (`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='file' AND INDEX_NAME='idx_file_expires'), 'ALTER TABLE `file` ADD KEY `idx_file_expires` (`expires_at`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `oauth_identity` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND INDEX_NAME='uq_oauth_tenant_provider_subject'), 'ALTER TABLE `oauth_identity` ADD UNIQUE KEY `uq_oauth_tenant_provider_subject` (`franchise_code`,`provider`,`provider_subject`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND INDEX_NAME='idx_oauth_user'), 'ALTER TABLE `oauth_identity` ADD KEY `idx_oauth_user` (`user_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `password_reset_token` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND INDEX_NAME='uq_password_reset_hash'), 'ALTER TABLE `password_reset_token` ADD UNIQUE KEY `uq_password_reset_hash` (`token_hash`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND INDEX_NAME='idx_password_reset_user'), 'ALTER TABLE `password_reset_token` ADD KEY `idx_password_reset_user` (`franchise_code`,`user_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND INDEX_NAME='idx_password_reset_expiry'), 'ALTER TABLE `password_reset_token` ADD KEY `idx_password_reset_expiry` (`expires_at`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND INDEX_NAME='fk_password_reset_user'), 'ALTER TABLE `password_reset_token` ADD KEY `fk_password_reset_user` (`user_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_rate_limit' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `api_rate_limit` ADD PRIMARY KEY (`franchise_code`,`action`,`subject_hash`,`window_started_at`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_rate_limit' AND INDEX_NAME='idx_rate_limit_expiry'), 'ALTER TABLE `api_rate_limit` ADD KEY `idx_rate_limit_expiry` (`expires_at`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

-- Missing constraints.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_question' AND CONSTRAINT_NAME='fk_customer_profile_question_profile'), 'ALTER TABLE `customer_profile_question` ADD CONSTRAINT `fk_customer_profile_question_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_objection' AND CONSTRAINT_NAME='fk_customer_profile_objection_profile'), 'ALTER TABLE `customer_profile_objection` ADD CONSTRAINT `fk_customer_profile_objection_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_preference' AND CONSTRAINT_NAME='fk_customer_profile_preference_profile'), 'ALTER TABLE `customer_profile_preference` ADD CONSTRAINT `fk_customer_profile_preference_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='user' AND CONSTRAINT_NAME='fk_user_role'), 'ALTER TABLE `user` ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE RESTRICT', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND CONSTRAINT_NAME='fk_user_customer_profile_profile'), 'ALTER TABLE `user_customer_profile` ADD CONSTRAINT `fk_user_customer_profile_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='user_customer_profile' AND CONSTRAINT_NAME='fk_user_customer_profile_user'), 'ALTER TABLE `user_customer_profile` ADD CONSTRAINT `fk_user_customer_profile_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='address' AND CONSTRAINT_NAME='fk_addr_user'), 'ALTER TABLE `address` ADD CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='user_token' AND CONSTRAINT_NAME='fk_token_user'), 'ALTER TABLE `user_token` ADD CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='category' AND CONSTRAINT_NAME='fk_cat_parent'), 'ALTER TABLE `category` ADD CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`) ON DELETE SET NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND CONSTRAINT_NAME='fk_product_customer_profile_product'), 'ALTER TABLE `product_customer_profile_probability` ADD CONSTRAINT `fk_product_customer_profile_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND CONSTRAINT_NAME='fk_product_customer_profile_profile'), 'ALTER TABLE `product_customer_profile_probability` ADD CONSTRAINT `fk_product_customer_profile_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='product_customer_profile_probability' AND CONSTRAINT_NAME='chk_product_customer_profile_probability'), 'ALTER TABLE `product_customer_profile_probability` ADD CONSTRAINT `chk_product_customer_profile_probability` CHECK ((`probability_percent` between 0 and 100))', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND CONSTRAINT_NAME='fk_product_alternative_source'), 'ALTER TABLE `product_alternative` ADD CONSTRAINT `fk_product_alternative_source` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND CONSTRAINT_NAME='fk_product_alternative_target'), 'ALTER TABLE `product_alternative` ADD CONSTRAINT `fk_product_alternative_target` FOREIGN KEY (`alternative_product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='product_alternative' AND CONSTRAINT_NAME='chk_product_alternative_different'), 'ALTER TABLE `product_alternative` ADD CONSTRAINT `chk_product_alternative_different` CHECK ((`product_id` <> `alternative_product_id`))', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='product_category' AND CONSTRAINT_NAME='fk_pc_category'), 'ALTER TABLE `product_category` ADD CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='product_category' AND CONSTRAINT_NAME='fk_pc_product'), 'ALTER TABLE `product_category` ADD CONSTRAINT `fk_pc_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='product_file' AND CONSTRAINT_NAME='fk_pf_file'), 'ALTER TABLE `product_file` ADD CONSTRAINT `fk_pf_file` FOREIGN KEY (`file_id`) REFERENCES `file` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='product_file' AND CONSTRAINT_NAME='fk_pf_product'), 'ALTER TABLE `product_file` ADD CONSTRAINT `fk_pf_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='order' AND CONSTRAINT_NAME='fk_order_bill'), 'ALTER TABLE `order` ADD CONSTRAINT `fk_order_bill` FOREIGN KEY (`billing_address_id`) REFERENCES `address` (`id`) ON DELETE SET NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='order' AND CONSTRAINT_NAME='fk_order_ship'), 'ALTER TABLE `order` ADD CONSTRAINT `fk_order_ship` FOREIGN KEY (`shipping_address_id`) REFERENCES `address` (`id`) ON DELETE SET NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='order' AND CONSTRAINT_NAME='fk_order_user'), 'ALTER TABLE `order` ADD CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND CONSTRAINT_NAME='fk_oi_order'), 'ALTER TABLE `order_item` ADD CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='order_item' AND CONSTRAINT_NAME='fk_oi_product'), 'ALTER TABLE `order_item` ADD CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE SET NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='invoice' AND CONSTRAINT_NAME='fk_inv_order'), 'ALTER TABLE `invoice` ADD CONSTRAINT `fk_inv_order` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE SET NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='invoice_item' AND CONSTRAINT_NAME='fk_ii_invoice'), 'ALTER TABLE `invoice_item` ADD CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='invoice_file' AND CONSTRAINT_NAME='fk_if_file'), 'ALTER TABLE `invoice_file` ADD CONSTRAINT `fk_if_file` FOREIGN KEY (`file_id`) REFERENCES `file` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='invoice_file' AND CONSTRAINT_NAME='fk_if_invoice'), 'ALTER TABLE `invoice_file` ADD CONSTRAINT `fk_if_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='oauth_identity' AND CONSTRAINT_NAME='fk_oauth_user'), 'ALTER TABLE `oauth_identity` ADD CONSTRAINT `fk_oauth_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_token' AND CONSTRAINT_NAME='fk_password_reset_user'), 'ALTER TABLE `password_reset_token` ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;
