-- Shop links: every shop gets its own address, name.nfelia.com, and one of
-- the ten storefront looks.
--
-- subdomain            the live name; a shop is served at subdomain.nfelia.com
-- subdomain_requested  a name waiting for the founder's approval (it looks
--                      like a bank, telco, government body or official
--                      account). While a rename waits, the old name stays live.
-- subdomain_status     none     no link chosen yet
--                      pending  a requested name is waiting for review
--                      active   the live link, nothing waiting
--                      rejected the last request was turned down
-- subdomain_renames    renames after the first claim; owners get one, then
--                      support does it
-- storefront_look      the look the shop is drawn in
--
-- Both name columns are unique, so two shops can never hold or wait for the
-- same name, even if they ask for it at the same moment.
--
-- The old /biz/{slug} links are retired: owners choose a new name at their
-- next sign-in. The slug column stays; it is internal now.

ALTER TABLE `businesses`
    ADD COLUMN IF NOT EXISTS `subdomain` VARCHAR(30) NULL DEFAULT NULL
        COMMENT 'Live shop link: subdomain.nfelia.com' AFTER `slug`,
    ADD COLUMN IF NOT EXISTS `subdomain_requested` VARCHAR(30) NULL DEFAULT NULL
        COMMENT 'Name waiting for review' AFTER `subdomain`,
    ADD COLUMN IF NOT EXISTS `subdomain_status` ENUM('none','pending','active','rejected') NOT NULL DEFAULT 'none'
        AFTER `subdomain_requested`,
    ADD COLUMN IF NOT EXISTS `subdomain_renames` TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Renames after the first claim' AFTER `subdomain_status`,
    ADD COLUMN IF NOT EXISTS `subdomain_requested_at` DATETIME NULL DEFAULT NULL AFTER `subdomain_renames`,
    ADD COLUMN IF NOT EXISTS `subdomain_reviewed_at` DATETIME NULL DEFAULT NULL AFTER `subdomain_requested_at`,
    ADD COLUMN IF NOT EXISTS `subdomain_reviewed_by` INT NULL DEFAULT NULL
        COMMENT 'users.id of the reviewer' AFTER `subdomain_reviewed_at`,
    ADD COLUMN IF NOT EXISTS `storefront_look`
        ENUM('atelier','buka','glow','volt','market','stage','harvest','shea','haven','desk') NULL DEFAULT NULL
        AFTER `subdomain_reviewed_by`;

CREATE UNIQUE INDEX IF NOT EXISTS `uq_businesses_subdomain` ON `businesses` (`subdomain`);
CREATE UNIQUE INDEX IF NOT EXISTS `uq_businesses_subdomain_requested` ON `businesses` (`subdomain_requested`);
CREATE INDEX IF NOT EXISTS `idx_businesses_subdomain_status` ON `businesses` (`subdomain_status`);
