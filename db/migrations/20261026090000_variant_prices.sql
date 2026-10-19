-- Per-option price adjustments for product variants.
--
-- Each variant option (a colour, a size) can nudge the item's price up or
-- down. The delta is added to the product's base sell_price when that option
-- is chosen, and deltas from several groups add up. 0 means the option costs
-- the same as the base, which is how every option behaved before this.
--
-- Stock stays one shared count per product — variants change price only.

ALTER TABLE `product_variant_options`
  ADD COLUMN IF NOT EXISTS `price_delta` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `label`;
