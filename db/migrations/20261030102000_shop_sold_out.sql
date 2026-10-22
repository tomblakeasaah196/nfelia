-- "Show items as sold out when their stock runs out", a shop setting
-- (Shop Studio › More). When it is on, a product whose stock is 0 or less
-- says "Sold out" on the shop, can't be added to the bag, is refused at
-- checkout with the usual "no longer available", and its structured data
-- says OutOfStock (storefront_catalogue, PublicController::place_order).
-- Services and things people book are never sold out.
--
-- Off for every business that exists now: many count no stock, and their
-- products sit at 0 or below as they sell, so switching it on for them
-- would empty their shop overnight. On for every business made from now on.
--
-- The column is added with 0, so existing rows get 0, then its default
-- becomes 1. Safe to run twice: a second run changes nobody's choice.

ALTER TABLE `businesses`
    ADD COLUMN IF NOT EXISTS `shop_sold_out` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Shop shows products with no stock left as sold out' AFTER `landing_enabled`;

ALTER TABLE `businesses`
    ALTER COLUMN `shop_sold_out` SET DEFAULT 1;
