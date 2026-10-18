-- Devices each person has signed in on, so a sign-in from a new one can be
-- told to them (security.new_device in app/helpers/notify.php). The device
-- carries a random id in a long-lived cookie; only its hash is kept here.
-- Nothing is known about anyone's devices until they next sign in, so the
-- first sign-in after this ships never raises an alarm.

CREATE TABLE IF NOT EXISTS `user_devices` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT          NOT NULL,
    `device_hash`   CHAR(64)     NOT NULL,
    `label`         VARCHAR(100) NULL,
    `first_seen_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_user_device` (`user_id`, `device_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
