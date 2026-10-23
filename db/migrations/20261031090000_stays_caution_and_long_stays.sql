-- Shop looks, step 9: what a stay costs (docs/handoff/Shop looks step 9.md).
--
-- products.caution_fee    stays only: a refundable caution fee, paid with the
--                         booking (with the deposit) and given back after
--                         check-out. Not part of the stay's total, and never
--                         income: no receipt is made for it.
-- products.stay_rates     stays only: cheaper for longer stays, as JSON
--                         [[from nights, price a night], …], up to three,
--                         fewest nights first (booking_clean_rates). A stay
--                         pays the cheapest step it reaches, never more than
--                         the nightly price.
-- bookings.caution_fee    the caution fee the booking asked for (0: none).
-- bookings.caution_refunded_at  when the owner marked it given back; until
--                         then, once the stay is over, the booking and the
--                         morning reminders say to refund it.
--
-- Safe to run twice. Old products and bookings ask for no caution fee and
-- have no long-stay prices.

ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `caution_fee` DECIMAL(15,2) NULL DEFAULT NULL
        COMMENT 'Stays: refundable caution fee, paid with the booking' AFTER `deposit_value`,
    ADD COLUMN IF NOT EXISTS `stay_rates` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Stays: JSON [[from nights, price a night], ...], up to 3' AFTER `caution_fee`;

ALTER TABLE `bookings`
    ADD COLUMN IF NOT EXISTS `caution_fee` DECIMAL(15,2) NOT NULL DEFAULT 0
        COMMENT 'Refundable, paid with the booking' AFTER `deposit_due`,
    ADD COLUMN IF NOT EXISTS `caution_refunded_at` DATETIME NULL DEFAULT NULL AFTER `caution_fee`;
