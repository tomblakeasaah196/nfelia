-- Money paid in advance. A client sends money before they know what they
-- will buy: Clients › Kept with you › Add money takes it in. It comes in as
-- a receipt with no invoice, so money in counts it on the day it came in
-- (as a sale paid on the spot is counted), and it is kept for the client's
-- next order like money paid over. Using it later moves it ('kept'), so it
-- is never counted twice.
--
-- kept_money.receipt_id   the receipt for money paid in advance; empty for
--                         money kept from an order.
--
-- Safe to run twice.

ALTER TABLE `kept_money`
    ADD COLUMN IF NOT EXISTS `receipt_id` INT(11) NULL DEFAULT NULL AFTER `payment_id`,
    ADD INDEX IF NOT EXISTS `idx_kept_money_receipt` (`receipt_id`);
