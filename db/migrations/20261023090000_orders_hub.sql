-- The orders hub (docs/ORDERS_HUB.md, section 2). Every sale is an order:
-- its invoice, payments and receipts, delivery, debt, tasks, refunds and
-- history all hang off it.
--
-- orders            takes orders typed in by the owner (manual), made from a
--                   quote, and quick sales; a 'ready' step (ready for pickup,
--                   or on its way); a walk-in sale has no phone. Amounts are
--                   the same size as the invoice's. The owner's own note,
--                   discount, when it was changed, finished or cancelled and
--                   why.
-- order_items       quantities like 2.5 (metres, kilos), longer names, and
--                   the order the lines were typed in.
-- order_id          on invoices, receipts, deliveries, debts and tasks, so
--                   each document knows its order. orders.invoice_id,
--                   receipt_id and delivery_id stay as mirrors of the first
--                   of each, for older code and the tracking page.
-- method 'kept'     money moved between one customer's orders (kept from one,
--                   used on the next). Never new money: money_in() leaves it
--                   out.
-- document_counters orders (ORD), bookings (BKG) and refunds (RFD) are
--                   numbered by the one generator now, carrying on from the
--                   old per-business counters. Without this seed the
--                   generator's own fallback would not see the old
--                   ORD-0001 numbers and would start again at 1.
-- businesses.prefix_nudge_seen
--                   the new prefix the owner already said no to, so the
--                   suggestion after a rename isn't shown again.
-- customers.is_walk_in
--                   the one hidden "Walk-in customer" per business, for
--                   quick sales to someone who gives no name.
-- order_events      each order's history, for its Log.
-- refunds           money given back: asked for account details, paid, with
--                   the proof or reference that goes on the refund slip.
-- kept_money        a customer's money kept for their next order: plus when
--                   kept, minus when used or given back.
--
-- No foreign keys, as with the existing links (purge cleans links itself).
-- Each statement is safe to run twice: migrate.php runs the file in one go
-- and stops at the first statement that fails, so a second run must find
-- every change already made and change nothing.

ALTER TABLE `orders`
    MODIFY `channel` ENUM('cart_pop','whatsapp','manual','quote','quick') NOT NULL DEFAULT 'cart_pop',
    MODIFY `status` ENUM('pending','pending_verification','approved','rejected','processing','ready','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
    MODIFY `customer_phone` VARCHAR(30) NULL DEFAULT NULL,
    MODIFY `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    MODIFY `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `quotation_id` INT(11) NULL DEFAULT NULL AFTER `customer_id`,
    ADD COLUMN IF NOT EXISTS `created_by` INT(11) NULL DEFAULT NULL AFTER `reviewed_at`,
    ADD COLUMN IF NOT EXISTS `notes` TEXT NULL DEFAULT NULL AFTER `customer_note`,
    ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`,
    ADD COLUMN IF NOT EXISTS `edited_at` DATETIME NULL DEFAULT NULL AFTER `created_at`,
    ADD COLUMN IF NOT EXISTS `done_at` DATETIME NULL DEFAULT NULL AFTER `created_at`,
    ADD COLUMN IF NOT EXISTS `cancel_reason` VARCHAR(300) NULL DEFAULT NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
    ADD INDEX IF NOT EXISTS `idx_orders_list` (`business_id`, `status`, `created_at`),
    ADD INDEX IF NOT EXISTS `idx_orders_customer` (`business_id`, `customer_id`),
    ADD INDEX IF NOT EXISTS `idx_orders_quotation` (`quotation_id`);

ALTER TABLE `order_items`
    MODIFY `product_name` VARCHAR(500) NOT NULL,
    MODIFY `unit_price` DECIMAL(15,2) NOT NULL,
    MODIFY `quantity` DECIMAL(15,2) NOT NULL DEFAULT 1.00,
    MODIFY `line_total` DECIMAL(15,2) NOT NULL,
    ADD COLUMN IF NOT EXISTS `sort_order` INT(11) NOT NULL DEFAULT 1 AFTER `line_total`;

ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `order_id` INT(11) NULL DEFAULT NULL AFTER `quotation_id`,
    ADD INDEX IF NOT EXISTS `idx_invoices_order` (`business_id`, `order_id`);

ALTER TABLE `receipts`
    ADD COLUMN IF NOT EXISTS `order_id` INT(11) NULL DEFAULT NULL AFTER `payment_id`,
    ADD INDEX IF NOT EXISTS `idx_receipts_order` (`business_id`, `order_id`),
    MODIFY `method` ENUM('cash','bank_transfer','pos','mobile_money','cheque','other','kept') NOT NULL DEFAULT 'cash';

ALTER TABLE `payments`
    MODIFY `method` ENUM('cash','bank_transfer','pos','mobile_money','cheque','other','kept') NOT NULL DEFAULT 'cash';

ALTER TABLE `deliveries`
    ADD COLUMN IF NOT EXISTS `order_id` INT(11) NULL DEFAULT NULL AFTER `invoice_id`,
    ADD INDEX IF NOT EXISTS `idx_deliveries_order` (`business_id`, `order_id`);

ALTER TABLE `debts`
    ADD COLUMN IF NOT EXISTS `order_id` INT(11) NULL DEFAULT NULL AFTER `invoice_id`,
    ADD INDEX IF NOT EXISTS `idx_debts_order` (`business_id`, `order_id`);

ALTER TABLE `tasks`
    ADD COLUMN IF NOT EXISTS `order_id` INT(11) NULL DEFAULT NULL AFTER `invoice_id`,
    ADD INDEX IF NOT EXISTS `idx_tasks_order` (`business_id`, `order_id`);

ALTER TABLE `document_counters`
    MODIFY `doc_type` ENUM('QUO','INV','REC','DEL','DEA','EXP','ORD','BKG','RFD') NOT NULL;

-- Orders and bookings carry on from their old counters (or from the highest
-- number already used, old ORD-0007 or new ADA-ORD-008, should that be
-- higher). GREATEST keeps a counter that already moved on.
INSERT INTO `document_counters` (business_id, doc_type, last_number)
SELECT b.id, 'ORD', GREATEST(b.order_number_seq, COALESCE((
           SELECT MAX(CAST(SUBSTRING_INDEX(o.order_number, '-', -1) AS UNSIGNED))
           FROM orders o WHERE o.business_id = b.id AND o.order_number LIKE '%ORD-%'), 0))
FROM businesses b
ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number));

INSERT INTO `document_counters` (business_id, doc_type, last_number)
SELECT b.id, 'BKG', GREATEST(b.booking_number_seq, COALESCE((
           SELECT MAX(CAST(SUBSTRING_INDEX(k.booking_number, '-', -1) AS UNSIGNED))
           FROM bookings k WHERE k.business_id = b.id AND k.booking_number LIKE '%BKG-%'), 0))
FROM businesses b
ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number));

ALTER TABLE `businesses`
    ADD COLUMN IF NOT EXISTS `prefix_nudge_seen` VARCHAR(6) NULL DEFAULT NULL AFTER `doc_prefix`;

ALTER TABLE `customers`
    ADD COLUMN IF NOT EXISTS `is_walk_in` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

CREATE TABLE IF NOT EXISTS `order_events` (
    `id`          INT(11)      NOT NULL AUTO_INCREMENT,
    `business_id` INT(11)      NOT NULL,
    `order_id`    INT(11)      NOT NULL,
    `kind`        VARCHAR(30)  NOT NULL,
    `text`        VARCHAR(500) NOT NULL,
    `data`        TEXT         NULL DEFAULT NULL,
    `user_id`     INT(11)      NULL DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_events_order` (`order_id`, `id`),
    KEY `idx_order_events_business` (`business_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `refunds` (
    `id`           INT(11)       NOT NULL AUTO_INCREMENT,
    `business_id`  INT(11)       NOT NULL,
    `order_id`     INT(11)       NULL DEFAULT NULL,
    `invoice_id`   INT(11)       NULL DEFAULT NULL,
    `customer_id`  INT(11)       NULL DEFAULT NULL,
    `doc_number`   VARCHAR(30)   NULL DEFAULT NULL,
    `status`       ENUM('asked','details_in','paid','cancelled') NOT NULL DEFAULT 'asked',
    `amount`       DECIMAL(15,2) NOT NULL,
    `method`       ENUM('cash','bank_transfer','mobile_money','pos','other') NULL DEFAULT NULL,
    `reference`    VARCHAR(100)  NULL DEFAULT NULL,
    `proof_path`   VARCHAR(255)  NULL DEFAULT NULL,
    `reason`       VARCHAR(300)  NULL DEFAULT NULL,
    `token`        CHAR(32)      NOT NULL,
    `asked_at`     DATETIME      NULL DEFAULT NULL,
    `asked_email`  TINYINT(1)    NOT NULL DEFAULT 0,
    `payee_kind`   ENUM('bank','momo') NULL DEFAULT NULL,
    `payee_name`   VARCHAR(150)  NULL DEFAULT NULL,
    `payee_bank`   VARCHAR(100)  NULL DEFAULT NULL,
    `payee_number` VARCHAR(40)   NULL DEFAULT NULL,
    `details_at`   DATETIME      NULL DEFAULT NULL,
    `payment_id`   INT(11)       NULL DEFAULT NULL,
    `paid_at`      DATETIME      NULL DEFAULT NULL,
    `user_id`      INT(11)       NULL DEFAULT NULL,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_refunds_token` (`token`),
    UNIQUE KEY `uq_refunds_doc_number` (`business_id`, `doc_number`),
    KEY `idx_refunds_order` (`business_id`, `order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kept_money` (
    `id`          INT(11)       NOT NULL AUTO_INCREMENT,
    `business_id` INT(11)       NOT NULL,
    `customer_id` INT(11)       NOT NULL,
    `order_id`    INT(11)       NULL DEFAULT NULL,
    `payment_id`  INT(11)       NULL DEFAULT NULL,
    `amount`      DECIMAL(15,2) NOT NULL COMMENT 'plus when kept, minus when used or given back',
    `note`        VARCHAR(255)  NULL DEFAULT NULL,
    `user_id`     INT(11)       NULL DEFAULT NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_kept_money_customer` (`business_id`, `customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Past shop orders are linked to their documents. Each only where the link
-- is still empty, so a second run changes nothing.
UPDATE `invoices` i
JOIN `orders` o ON o.invoice_id = i.id AND o.business_id = i.business_id
SET i.order_id = o.id
WHERE i.order_id IS NULL;

UPDATE `receipts` r
JOIN `orders` o ON o.receipt_id = r.id AND o.business_id = r.business_id
SET r.order_id = o.id
WHERE r.order_id IS NULL;

UPDATE `receipts` r
JOIN `invoices` i ON i.id = r.invoice_id AND i.business_id = r.business_id
SET r.order_id = i.order_id
WHERE r.order_id IS NULL AND i.order_id IS NOT NULL;

UPDATE `deliveries` d
JOIN `orders` o ON o.delivery_id = d.id AND o.business_id = d.business_id
SET d.order_id = o.id
WHERE d.order_id IS NULL;

UPDATE `deliveries` d
JOIN `invoices` i ON i.id = d.invoice_id AND i.business_id = d.business_id
SET d.order_id = i.order_id
WHERE d.order_id IS NULL AND i.order_id IS NOT NULL;

UPDATE `debts` d
JOIN `invoices` i ON i.id = d.invoice_id AND i.business_id = d.business_id
SET d.order_id = i.order_id
WHERE d.order_id IS NULL AND i.order_id IS NOT NULL;

UPDATE `tasks` t
JOIN `invoices` i ON i.id = t.invoice_id AND i.business_id = t.business_id
SET t.order_id = i.order_id
WHERE t.order_id IS NULL AND i.order_id IS NOT NULL;
