-- Delivery notes' lines belong to deliveries.
--
-- Every screen reads and writes the lines of a delivery note through
-- delivery_items.delivery_id (DeliveryController, the public delivery and
-- sign-off pages), but production's table only had delivery_note_id, tied by
-- a foreign key to the retired delivery_notes table. So saving a delivery
-- with lines failed, and so did approving a shop order for delivery (it
-- creates the delivery and its lines), which the step 3 review flow needs.
--
-- This adds delivery_id (tied to deliveries) and the notes column the
-- delivery form writes, and frees delivery_note_id from the old table. Old
-- lines keep their delivery_note_id; nothing is deleted.

ALTER TABLE `delivery_items`
    ADD COLUMN IF NOT EXISTS `delivery_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `notes` VARCHAR(500) NULL DEFAULT NULL AFTER `unit`;

ALTER TABLE `delivery_items` DROP FOREIGN KEY IF EXISTS `fk_delivery_items_note`;

ALTER TABLE `delivery_items` MODIFY `delivery_note_id` INT(11) NULL DEFAULT NULL;

CREATE INDEX IF NOT EXISTS `idx_delivery_items_delivery` ON `delivery_items` (`delivery_id`);

ALTER TABLE `delivery_items`
    ADD CONSTRAINT `fk_delivery_items_delivery` FOREIGN KEY IF NOT EXISTS (`delivery_id`)
        REFERENCES `deliveries` (`id`) ON DELETE CASCADE;
