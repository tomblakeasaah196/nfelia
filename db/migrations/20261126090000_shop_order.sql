-- The order a shop shows what it sells (app/helpers/storefront.php,
-- shop_sort_items). Until now every list was A to Z by name, and categories
-- came in the order their first product was added, with no way to change
-- either.
--
-- products.pin_rank           1 to 8 for an item pinned to the top of the
--                             shop (1 shows first), else NULL. Set from Shop
--                             Studio's "Order on your shop" or the pin on a
--                             Catalogue row (shop_pin).
-- businesses.category_order   JSON list of category names, first to last
--                             (shop_set_category_order). Matched ignoring
--                             case; a category not in it follows those that
--                             are.
--
-- Everything else follows by what was booked or ordered most in the last 90
-- days, then the newest. Nothing to fill in: NULL means "not placed".

ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `pin_rank` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `on_shop`;

ALTER TABLE `businesses`
    ADD COLUMN IF NOT EXISTS `category_order` TEXT NULL DEFAULT NULL;
