-- Shop Studio: the storefront's draft and published settings, the last ten
-- publishes, the "Shop by Nfelia" add-on, and old shop names on hold.
--
-- storefront_draft         what the owner is editing in Shop Studio. Shoppers
--                          never see it; the Studio preview does.
-- storefront_published     what shoppers see. Null until the first Publish:
--                          until then the shop is drawn from the older
--                          columns (tagline, about_text, delivery_info…), so
--                          nothing changes for a shop that never opens Studio.
-- storefront_published_at  when the published settings last changed
-- hide_nfelia_credit       the paid add-on: no "Shop by Nfelia" line in the
--                          shop's footer. The founder switches it in Admin.
--
-- Both settings columns hold the JSON written by storefront_normalise() in
-- app/helpers/storefront.php.
--
-- storefront_versions keeps every shop's last ten publishes so an owner can
-- bring an older one back into the draft. Older rows are deleted on publish.
--
-- shop_name_holds keeps a renamed shop's old name for 90 days. Links already
-- shared on WhatsApp or printed on a poster then reach the shop's new address
-- instead of whoever claims the old name next.

ALTER TABLE `businesses`
    ADD COLUMN IF NOT EXISTS `storefront_draft` JSON NULL DEFAULT NULL
        COMMENT 'Shop Studio settings being edited' AFTER `storefront_look`,
    ADD COLUMN IF NOT EXISTS `storefront_published` JSON NULL DEFAULT NULL
        COMMENT 'Shop Studio settings shoppers see' AFTER `storefront_draft`,
    ADD COLUMN IF NOT EXISTS `storefront_published_at` DATETIME NULL DEFAULT NULL AFTER `storefront_published`,
    ADD COLUMN IF NOT EXISTS `hide_nfelia_credit` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Paid add-on: no Shop by Nfelia line' AFTER `storefront_published_at`;

CREATE TABLE IF NOT EXISTS `storefront_versions` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  INT          NOT NULL,
    `settings`     JSON         NOT NULL,
    `published_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `published_by` INT          NULL COMMENT 'users.id',
    KEY `idx_storefront_versions_business` (`business_id`, `published_at`),
    CONSTRAINT `fk_storefront_versions_business` FOREIGN KEY (`business_id`)
        REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_name_holds` (
    `name`        VARCHAR(30) NOT NULL PRIMARY KEY,
    `business_id` INT         NOT NULL COMMENT 'The shop that used the name',
    `held_until`  DATETIME    NOT NULL,
    `created_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_shop_name_holds_business` (`business_id`),
    CONSTRAINT `fk_shop_name_holds_business` FOREIGN KEY (`business_id`)
        REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
