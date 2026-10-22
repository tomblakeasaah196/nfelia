-- Hair & Beauty, a business category (BUSINESS_CATEGORIES in config/app.php).
-- Hair vendors, salons and make-up artists had no category of their own and
-- picked Fashion or Other; a new shop in this one starts in the Glow look
-- (STOREFRONT_LOOK_BY_CATEGORY in app/helpers/shop_links.php).
--
-- Widening an ENUM keeps every existing value, and 'beauty' goes last so
-- MariaDB only adds it. Safe to run twice.

ALTER TABLE `businesses`
    MODIFY `category` ENUM('fashion','food_beverage','tech_repair','distribution','services','wholesale_retail','creative','other','beauty') NOT NULL DEFAULT 'other';
