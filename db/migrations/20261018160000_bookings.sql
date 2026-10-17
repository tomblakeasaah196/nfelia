-- Selling, step 4: bookings with deposits.
--
-- products.booking_mode   'none' (bought), 'slot' (a time, booking_minutes
--                         long), 'nights' (a stay, priced per night) or 'day'
--                         (a whole day, booking_capacity bookings a day).
-- products.deposit_*      what it takes to book: a share of the price, a
--                         fixed amount, or nothing.
-- businesses.booking_settings  JSON: opening hours per day, notice, how far
--                         ahead, minutes between start times, how many at once,
--                         check-in and check-out times (app/helpers/bookings.php).
-- bookings                one per request, with its own private link
--                         (name.nfelia.com/booking/{token}). 'pending' holds the
--                         time while the owner checks the deposit; 'confirmed'
--                         puts it on the calendar (task_id); 'declined' and
--                         'cancelled' free it.

ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `booking_mode` ENUM('none','slot','nights','day') NOT NULL DEFAULT 'none' AFTER `is_service`,
    ADD COLUMN IF NOT EXISTS `booking_minutes` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `booking_mode`,
    ADD COLUMN IF NOT EXISTS `booking_capacity` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `booking_minutes`,
    ADD COLUMN IF NOT EXISTS `deposit_type` ENUM('none','percent','fixed') NOT NULL DEFAULT 'none' AFTER `booking_capacity`,
    ADD COLUMN IF NOT EXISTS `deposit_value` DECIMAL(15,2) NULL DEFAULT NULL AFTER `deposit_type`;

ALTER TABLE `businesses`
    ADD COLUMN IF NOT EXISTS `booking_settings` TEXT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `booking_number_seq` INT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `bookings` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`        INT          NOT NULL,
    `product_id`         INT          NULL,
    `product_name`       VARCHAR(150) NOT NULL,
    `booking_number`     VARCHAR(20)  NOT NULL,
    `public_token`       CHAR(32)     NOT NULL,
    `kind`               ENUM('slot','nights','day') NOT NULL,
    `starts_at`          DATETIME     NOT NULL,
    `ends_at`            DATETIME     NOT NULL,
    `units`              SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'nights for stays, else 1',
    `unit_price`         DECIMAL(15,2) NOT NULL DEFAULT 0,
    `total`              DECIMAL(15,2) NOT NULL DEFAULT 0,
    `deposit_due`        DECIMAL(15,2) NOT NULL DEFAULT 0,
    `currency_code`      CHAR(3)      NOT NULL DEFAULT 'NGN',
    `customer_id`        INT          NULL,
    `customer_name`      VARCHAR(100) NOT NULL,
    `customer_phone`     VARCHAR(20)  NOT NULL,
    `customer_email`     VARCHAR(100) NULL,
    `customer_note`      VARCHAR(1000) NULL,
    `channel`            ENUM('site','whatsapp') NOT NULL DEFAULT 'site',
    `payment_method`     ENUM('bank_transfer','none') NOT NULL DEFAULT 'none',
    `payment_proof_path` VARCHAR(255) NULL,
    `status`             ENUM('pending','confirmed','declined','cancelled','completed') NOT NULL DEFAULT 'pending',
    `owner_message`      VARCHAR(300) NULL COMMENT 'why it was declined or cancelled, shown to the shopper',
    `task_id`            INT UNSIGNED NULL,
    `decided_by`         INT          NULL,
    `decided_at`         DATETIME     NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_bookings_token` (`public_token`),
    UNIQUE KEY `idx_bookings_number` (`business_id`, `booking_number`),
    KEY `idx_bookings_when` (`business_id`, `status`, `starts_at`),
    KEY `idx_bookings_product` (`product_id`, `starts_at`),
    KEY `idx_bookings_task` (`task_id`),
    CONSTRAINT `fk_bookings_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bookings_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
