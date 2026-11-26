-- Meetings: people book a call with the Nfelia team at nfelia.com/book,
-- and Ops looks after them under Sales → Meetings (app/ops/meetings.php).
--
-- Not the shops' bookings (the bookings table, app/helpers/bookings.php):
-- those belong to one business each. These belong to Nfelia itself.
--
-- Safe to run more than once: tables are created only if missing, the
-- meeting types are seeded only if their slug is new, and leads.source is
-- widened only while it is an ENUM without 'booking'.
--
-- Run it in phpMyAdmin with the Nfelia database selected (SQL tab), or keep
-- it under db/migrations/ for php db/migrate.php once the repo is back.

CREATE TABLE IF NOT EXISTS meeting_types (
    id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(60)       NOT NULL,
    name        VARCHAR(120)      NOT NULL,
    description VARCHAR(255)      NULL,
    minutes     SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    is_active   TINYINT(1)        NOT NULL DEFAULT 1,
    sort_order  SMALLINT          NOT NULL DEFAULT 0,
    deleted_at  DATETIME          NULL,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_meeting_types_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meetings (
    id                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    meeting_type_id   INT UNSIGNED      NULL,
    type_name         VARCHAR(120)      NOT NULL,
    minutes           SMALLINT UNSIGNED NOT NULL,
    starts_at         DATETIME          NOT NULL,
    ends_at           DATETIME          NOT NULL,
    status            ENUM('booked', 'confirmed', 'reschedule', 'cancelled', 'done', 'no_show') NOT NULL DEFAULT 'booked',
    name              VARCHAR(120)      NOT NULL,
    email             VARCHAR(190)      NOT NULL,
    phone             VARCHAR(40)       NULL,
    business_name     VARCHAR(160)      NULL,
    note              TEXT              NULL,
    meet_url          VARCHAR(255)      NULL,
    lead_id           INT UNSIGNED      NULL,
    move_token_hash   CHAR(64)          NULL,
    move_note         VARCHAR(500)      NULL,
    cancel_reason     VARCHAR(500)      NULL,
    confirmed_at      DATETIME          NULL,
    confirmed_by      INT UNSIGNED      NULL,
    reminded_day_at   DATETIME          NULL,
    reminded_hour_at  DATETIME          NULL,
    ip                VARCHAR(45)       NULL,
    created_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_meetings_time (status, starts_at),
    KEY idx_meetings_email (email),
    UNIQUE KEY uq_meetings_move (move_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row: opening hours, notice, how far ahead, the gap, closed dates (JSON).
CREATE TABLE IF NOT EXISTS meeting_settings (
    id          TINYINT UNSIGNED NOT NULL,
    settings    TEXT             NOT NULL,
    updated_by  INT UNSIGNED     NULL,
    updated_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monday to Saturday, 9am to 5pm Lagos time; 4 hours' notice; 30 days ahead;
-- 15 minutes between meetings; a start time every 30 minutes.
INSERT IGNORE INTO meeting_settings (id, settings) VALUES (1,
 '{"hours":{"mon":{"open":"09:00","close":"17:00"},"tue":{"open":"09:00","close":"17:00"},"wed":{"open":"09:00","close":"17:00"},"thu":{"open":"09:00","close":"17:00"},"fri":{"open":"09:00","close":"17:00"},"sat":{"open":"09:00","close":"17:00"},"sun":null},"notice":4,"ahead":30,"gap":15,"step":30,"closed":[]}');

INSERT IGNORE INTO meeting_types (slug, name, description, minutes, sort_order) VALUES
 ('demo',         'Nfelia demo',            'See how Nfelia handles invoices, receipts, debts and stock for your kind of business.', 30, 10),
 ('setup-help',   'Setup help',             'We set up your business, your products and your first invoice together with you.',     30, 20),
 ('shop-website', 'Your shop website',      'Get yourname.nfelia.com ready: photos, prices, delivery, and orders on WhatsApp.',      45, 30),
 ('bookings',     'Bookings and stays',     'For salons, tailors, shortlets and events: take bookings and deposits online.',          30, 40),
 ('pricing',      'Pricing and plans',      'Questions about the price, paying by transfer or mobile money, and team seats.',        15, 50),
 ('move-records', 'Moving your records',    'Bring your customers, products and debts over from a notebook or Excel.',               45, 60),
 ('partnership',  'Partnership talk',       'Referrals, reselling Nfelia, or working with us on something bigger.',                  30, 70);

-- Leads that come from a booking say so (LEAD_SOURCES 'booking', app/ops/sales.php).
-- If leads.source is an ENUM, add 'booking' to it, keeping whether it allows NULL.
SET @nf_col  := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND COLUMN_NAME = 'source');
SET @nf_null := (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND COLUMN_NAME = 'source');
SET @nf_sql  := IF(@nf_col LIKE 'enum(%' AND @nf_col NOT LIKE '%''booking''%',
                   CONCAT('ALTER TABLE leads MODIFY source ', LEFT(@nf_col, CHAR_LENGTH(@nf_col) - 1), ',''booking'')',
                          IF(@nf_null = 'NO', ' NOT NULL', ' NULL'), ' DEFAULT ''manual'''),
                   'DO 0');
PREPARE nf_stmt FROM @nf_sql;
EXECUTE nf_stmt;
DEALLOCATE PREPARE nf_stmt;
