-- A sale on the spot to a walk-in customer. The receipt form has always
-- offered "Walk-in Customer" and called the customer optional, but the
-- column refused NULL, so every such sale failed with "Save failed" and its
-- money-in alert never went. The foreign key still holds for receipts that
-- name a customer.

ALTER TABLE `receipts` MODIFY `customer_id` INT(11) NULL DEFAULT NULL;
