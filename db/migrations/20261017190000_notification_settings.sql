-- What reaches each person's phone (More > Notifications). Every alert still
-- lands in the bell; these choose what also makes the phone buzz.
--
-- Money in and reminders start on. Low stock and quiet hours exist but start
-- off. Quiet hours are Lagos time, like every other time in Nfelia.

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `notify_money`     TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS `notify_reminders` TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS `notify_low_stock` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `quiet_hours`      TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `quiet_from`       TIME       NOT NULL DEFAULT '21:00:00',
    ADD COLUMN IF NOT EXISTS `quiet_to`         TIME       NOT NULL DEFAULT '07:00:00';
