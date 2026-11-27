-- Several services in one appointment (a nail salon: Acrylic and Gel toes
-- together, one deposit). Shoppers add services to a time-slot booking on
-- its booking page; it stays one booking: one time, as long as the services
-- added up, one total, and the highest of their deposits
-- (app/helpers/bookings.php, booking_combine).
--
-- bookings.product_name   now the services joined with " + ", so every list,
--                         email and calendar entry that names a booking names
--                         them all; up to 255 characters.
-- booking_items           one row per service of a booking with several
--                         (none for a booking of one): what it cost, how long
--                         it takes and its deposit, as they were when booked.
--                         product_id is the first service's, as bookings has.

ALTER TABLE `bookings`
    MODIFY COLUMN `product_name` VARCHAR(255) NOT NULL;

CREATE TABLE IF NOT EXISTS `booking_items` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `booking_id`   INT UNSIGNED NOT NULL,
    `business_id`  INT          NOT NULL,
    `product_id`   INT          NULL,
    `product_name` VARCHAR(150) NOT NULL,
    `minutes`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `unit_price`   DECIMAL(15,2) NOT NULL DEFAULT 0,
    `deposit`      DECIMAL(15,2) NOT NULL DEFAULT 0,
    `sort_order`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    KEY `idx_booking_items_booking` (`booking_id`, `sort_order`),
    KEY `idx_booking_items_business` (`business_id`),
    CONSTRAINT `fk_booking_items_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_booking_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
