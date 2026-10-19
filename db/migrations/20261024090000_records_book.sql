-- The Records Book (docs/handoff/Records Book.md): a client's records as one
-- branded Excel workbook anyone can check at nfelia.com/verify, the free
-- plain copy, prices the team changes with notice, and requests for a
-- record's full document.
--
-- records_books         one row per workbook (kind 'book') or plain copy
--                       ('raw': CSV files in a ZIP, free for everyone, the
--                       data protection copy). The file is kept under
--                       storage/private/records/{business}/ until expires_at
--                       (books 12 months, plain copies 7 days); sha256 is
--                       what nfelia.com/verify checks an uploaded file
--                       against, and code is the one on the cover's QR.
--                       charge: 'free' the one free book in a paid month,
--                       'paid' bought, 'team' built by Nfelia Ops, 'raw'.
-- record_requests       someone who checked a code asking for the full
--                       document: they confirm their email, an Ops admin
--                       asks the client, the client approves or declines,
--                       and an approved request gets a 7-day download link.
-- price_changes         a new price for a country (any of the month, an
--                       extra seat, a Records Book). New sign-ups pay it from
--                       new_from; businesses that joined before then pay it
--                       for whatever starts on or after existing_from (a
--                       month that starts before keeps the old price).
-- price_notices         the clients told about a change, handed to the
--                       outbox a few at a time like campaigns.
-- countries.book_price  one Records Book before any change (Nigeria ₦7,500).
-- subscription_payments a Mobile Money receipt for a Records Book: kind
--                       'book', and the book it pays for.
--
-- Each statement is safe to run twice: migrate.php runs the file in one
-- exec(), which reports an error only from the first statement.

CREATE TABLE IF NOT EXISTS `records_books` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   INT          NOT NULL,
    `kind`          ENUM('book','raw') NOT NULL DEFAULT 'book',
    `code`          CHAR(10)     NULL COMMENT 'the verify code on the cover; none for plain copies',
    `status`        ENUM('awaiting_payment','queued','building','ready','failed','expired','cancelled') NOT NULL DEFAULT 'queued',
    `charge`        ENUM('free','paid','team','raw') NOT NULL,
    `amount`        DECIMAL(15,2) NULL COMMENT 'what it cost, in currency_code',
    `currency_code` VARCHAR(10)  NULL,
    `payment_ref`   VARCHAR(100) NULL,
    `sheets`        VARCHAR(1000) NOT NULL COMMENT 'JSON: the sheets chosen',
    `date_from`     DATE         NULL COMMENT 'records dated from; null: from the start',
    `date_to`       DATE         NULL COMMENT 'records dated up to; null: today',
    `requested_by`  INT          NULL COMMENT 'users.id of the owner who asked',
    `staff_id`      INT UNSIGNED NULL COMMENT 'ops_staff.id when the team built it',
    `reason`        VARCHAR(255) NULL COMMENT 'why the team built it; the owner is told',
    `send_to_owner` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'email the owner a link once it is ready',
    `file_path`     VARCHAR(255) NULL COMMENT 'relative to the app',
    `file_name`     VARCHAR(190) NULL COMMENT 'what the download is called',
    `file_size`     INT UNSIGNED NULL,
    `sha256`        CHAR(64)     NULL COMMENT 'of the file as built',
    `counts`        TEXT         NULL COMMENT 'JSON: rows in each sheet',
    `facts`         TEXT         NULL COMMENT 'JSON: the figures on the cover, shown when the code is checked',
    `error`         VARCHAR(255) NULL,
    `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `downloads`     INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at`    DATETIME     NULL,
    `ready_at`      DATETIME     NULL,
    `expires_at`    DATETIME     NULL COMMENT 'the file is deleted after this',
    `last_download_at` DATETIME  NULL,
    UNIQUE KEY `uniq_records_books_code` (`code`),
    KEY `idx_records_books_business` (`business_id`, `kind`, `created_at`),
    KEY `idx_records_books_status` (`status`, `id`),
    KEY `idx_records_books_sha` (`sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `record_requests` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `book_id`         INT UNSIGNED NOT NULL,
    `business_id`     INT          NOT NULL,
    `code`            VARCHAR(40)  NOT NULL COMMENT 'what was checked: the book, or one record in it',
    `record_type`     VARCHAR(20)  NULL COMMENT 'null: the whole book',
    `record_id`       INT          NULL,
    `label`           VARCHAR(160) NOT NULL COMMENT 'e.g. Invoice ADA-INV-0400, as it was when asked for',
    `name`            VARCHAR(120) NOT NULL,
    `organisation`    VARCHAR(160) NULL,
    `email`           VARCHAR(190) NOT NULL,
    `phone`           VARCHAR(40)  NULL,
    `role`            VARCHAR(30)  NULL COMMENT 'bank, landlord, accountant, buyer, supplier, other',
    `reason`          VARCHAR(1000) NOT NULL,
    `token_hash`      CHAR(64)     NOT NULL COMMENT 'their own link: confirms the email, later downloads',
    `status`          ENUM('unconfirmed','new','asked','approved','declined','expired') NOT NULL DEFAULT 'unconfirmed',
    `confirmed_at`    DATETIME     NULL,
    `asked_at`        DATETIME     NULL,
    `asked_by`        INT UNSIGNED NULL COMMENT 'ops_staff.id',
    `decided_at`      DATETIME     NULL,
    `decided_by_user` INT          NULL COMMENT 'users.id of the owner',
    `decided_by_staff` INT UNSIGNED NULL COMMENT 'ops_staff.id, for junk turned down by Ops',
    `decision_note`   VARCHAR(500) NULL COMMENT 'shown to the person who asked',
    `sent_at`         DATETIME     NULL,
    `file_expires_at` DATETIME     NULL,
    `downloads`       INT UNSIGNED NOT NULL DEFAULT 0,
    `last_download_at` DATETIME    NULL,
    `ip`              VARCHAR(45)  NULL,
    `user_agent`      VARCHAR(255) NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_record_requests_token` (`token_hash`),
    KEY `idx_record_requests_status` (`status`, `created_at`),
    KEY `idx_record_requests_business` (`business_id`, `created_at`),
    KEY `idx_record_requests_email` (`email`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `price_changes` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `country_id`      INT UNSIGNED NOT NULL,
    `month_price`     DECIMAL(15,2) NULL COMMENT 'null: unchanged',
    `seat_price`      DECIMAL(15,2) NULL,
    `book_price`      DECIMAL(15,2) NULL,
    `old_month`       DECIMAL(15,2) NULL COMMENT 'what new sign-ups paid before',
    `old_seat`        DECIMAL(15,2) NULL,
    `old_book`        DECIMAL(15,2) NULL,
    `new_from`        DATETIME     NOT NULL COMMENT 'new sign-ups and the website, from',
    `existing_from`   DATE         NOT NULL COMMENT 'businesses that joined before new_from: what starts on or after this day',
    `reason`          VARCHAR(500) NULL COMMENT 'why, in the notice to clients',
    `created_by`      INT UNSIGNED NULL COMMENT 'ops_staff.id',
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cancelled_at`    DATETIME     NULL,
    `cancelled_by`    INT UNSIGNED NULL,
    `notice_sent_at`  DATETIME     NULL COMMENT 'when Tell clients was pressed',
    `notice_sent_by`  INT UNSIGNED NULL,
    `notice_audience` VARCHAR(255) NULL COMMENT 'JSON: the client groups told',
    KEY `idx_price_changes_country` (`country_id`, `new_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `price_notices` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `change_id`   INT UNSIGNED NOT NULL,
    `business_id` INT          NOT NULL,
    `email`       VARCHAR(190) NOT NULL,
    `name`        VARCHAR(160) NULL,
    `status`      ENUM('waiting','queued','sent','failed','skipped') NOT NULL DEFAULT 'waiting',
    `outbox_id`   BIGINT UNSIGNED NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `queued_at`   DATETIME     NULL,
    UNIQUE KEY `uniq_price_notice` (`change_id`, `business_id`),
    KEY `idx_price_notice_status` (`status`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `countries`
    ADD COLUMN IF NOT EXISTS `book_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00
        COMMENT 'one Records Book, before any price change' AFTER `extra_seat_price`;

UPDATE `countries` SET `book_price` = 7500 WHERE `iso_code` = 'NG' AND `book_price` = 0;

UPDATE `countries` SET `book_price` = ROUND(`monthly_price` * 0.75) WHERE `book_price` = 0;

ALTER TABLE `subscription_payments`
    MODIFY `kind` ENUM('month','seats','book') NOT NULL DEFAULT 'month';

ALTER TABLE `subscription_payments`
    ADD COLUMN IF NOT EXISTS `book_id` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'records_books.id a Records Book receipt pays for' AFTER `covered_user_ids`;
