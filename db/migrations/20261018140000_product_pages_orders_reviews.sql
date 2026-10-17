-- Selling, step 3: order tracking links and reviews from delivered orders.
--
-- orders.public_token   the private link a shopper gets with every order
--                       (name.nfelia.com/order/{token}): the order's progress,
--                       its items, the receipt once paid, and the review link
--                       once delivered. 32 hex characters from random_bytes;
--                       orders placed before this step get one the first time
--                       the owner opens them.
--
-- reviews               one per order, created only when the order is done:
--                       its delivery was signed for, or the owner marked it
--                       fulfilled. The shopper rates it from the order page or
--                       the link after signing. Owners can't write or edit
--                       reviews; the founder can hide one that breaks the rules
--                       (status 'hidden'). Product pages need no column: their
--                       address is /p/{id}-{name}.

ALTER TABLE `orders`
    ADD COLUMN IF NOT EXISTS `public_token` CHAR(32) NULL DEFAULT NULL
        COMMENT 'Private tracking link for the shopper' AFTER `order_number`;

CREATE UNIQUE INDEX IF NOT EXISTS `idx_orders_public_token` ON `orders` (`public_token`);

CREATE TABLE IF NOT EXISTS `reviews` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   INT          NOT NULL,
    `order_id`      INT          NOT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `token`         CHAR(32)     NOT NULL,
    `rating`        TINYINT UNSIGNED NULL COMMENT '1 to 5; null until the shopper answers',
    `comment`       VARCHAR(600) NULL,
    `status`        ENUM('invited','published','hidden') NOT NULL DEFAULT 'invited',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `submitted_at`  DATETIME     NULL,
    `hidden_at`     DATETIME     NULL,
    UNIQUE KEY `idx_reviews_order` (`order_id`),
    UNIQUE KEY `idx_reviews_token` (`token`),
    KEY `idx_reviews_shop` (`business_id`, `status`, `submitted_at`),
    CONSTRAINT `fk_reviews_business` FOREIGN KEY (`business_id`)
        REFERENCES `businesses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
