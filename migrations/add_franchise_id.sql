-- ============================================================
-- Migration: add franchise_code for multi-tenant isolation
-- Run against EXISTING databases (fresh installs use schema.sql)
-- ============================================================

ALTER TABLE `enumeration`
    ADD COLUMN `franchise_code` VARCHAR(64) NOT NULL DEFAULT 'default' AFTER `id`,
    DROP INDEX  `uq_enum_type_code`,
    ADD  UNIQUE KEY `uq_enum_franchise_type_code` (`franchise_code`, `type`, `code`),
    ADD        KEY `idx_enum_franchise` (`franchise_code`);

ALTER TABLE `user`
    ADD COLUMN `franchise_code` VARCHAR(64) NOT NULL DEFAULT 'default' AFTER `id`,
    DROP INDEX  `uq_user_email`,
    ADD  UNIQUE KEY `uq_user_franchise_email` (`franchise_code`, `email`),
    ADD        KEY `idx_user_franchise` (`franchise_code`);

ALTER TABLE `address`
    ADD COLUMN `franchise_code` VARCHAR(64) NOT NULL DEFAULT 'default' AFTER `id`,
    ADD        KEY `idx_addr_franchise` (`franchise_code`);

ALTER TABLE `category`
    ADD COLUMN `franchise_code` VARCHAR(64) NOT NULL DEFAULT 'default' AFTER `id`,
    DROP INDEX  `uq_cat_slug`,
    ADD  UNIQUE KEY `uq_cat_franchise_slug` (`franchise_code`, `slug`),
    ADD        KEY `idx_cat_franchise` (`franchise_code`);

ALTER TABLE `product`
    ADD COLUMN `franchise_code` VARCHAR(64) NOT NULL DEFAULT 'default' AFTER `id`,
    DROP INDEX  `uq_product_sku`,
    ADD  UNIQUE KEY `uq_product_franchise_sku` (`franchise_code`, `sku`),
    ADD        KEY `idx_product_franchise` (`franchise_code`);

ALTER TABLE `text`
    ADD COLUMN `franchise_code` VARCHAR(64) NOT NULL DEFAULT 'default' AFTER `id`,
    DROP INDEX  `uq_text_key_lang`,
    ADD  UNIQUE KEY `uq_text_franchise_key_lang` (`franchise_code`, `key`, `language`),
    ADD        KEY `idx_text_franchise` (`franchise_code`);

ALTER TABLE `order`
    ADD COLUMN `franchise_code` VARCHAR(64) NOT NULL DEFAULT 'default' AFTER `id`,
    DROP INDEX  `uq_order_number`,
    ADD  UNIQUE KEY `uq_order_franchise_number` (`franchise_code`, `order_number`),
    ADD        KEY `idx_order_franchise` (`franchise_code`);

ALTER TABLE `invoice`
    ADD COLUMN `franchise_code` VARCHAR(64) NOT NULL DEFAULT 'default' AFTER `id`,
    DROP INDEX  `uq_invoice_number`,
    ADD  UNIQUE KEY `uq_inv_franchise_number` (`franchise_code`, `invoice_number`),
    ADD        KEY `idx_inv_franchise` (`franchise_code`);
