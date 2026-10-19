-- Signatures and verify codes on documents (docs/handoff/Signatures.md).
--
-- Tom's rules: no signature unless someone wants one. Settings › Signatures
-- turns them on by default per kind of document and picks how the business
-- signs (a stamp, or the signer's drawn signature); each document can be
-- switched on or off. Owners and admins sign for the business. A customer
-- signs only a quote (to accept it) and a delivery note (to receive it), and
-- only when that document is signed. Every signature carries the hash of
-- what was signed; a change signs it again, as a new version.
--
-- doc_codes          one verify code per document: on its page, its PDF and
--                    its QR code, read at nfelia.com/verify/<code>.
-- doc_signatures     each signature: who, how, when, the hash of the
--                    document's content then (content_hash, and the facts
--                    themselves, to say what changed), and a seal of all of
--                    it keyed with a secret kept on the server, not here
--                    (storage/private/seal.key). A new version supersedes
--                    the one before (superseded_at).
-- sign_on            on quotations, invoices, receipts, deliveries and
--                    deeds: whether the document is signed.
-- businesses.signing Settings › Signatures, as JSON: per kind, on by default
--                    and the style.
-- users.signature_path   an owner's or admin's drawn signature, drawn once
--                    (a private file), and when.
--
-- Quotes and delivery notes signed before this keep their signatures and
-- are marked signed. Every other document was never really signed (the
-- "Authorized By" lines were blank), so nothing is.
-- Each statement is safe to run twice.

CREATE TABLE IF NOT EXISTS `doc_codes` (
    `id`          INT(11)  NOT NULL AUTO_INCREMENT,
    `business_id` INT(11)  NOT NULL,
    `doc_type`    ENUM('quote','invoice','receipt','delivery','deed') NOT NULL,
    `doc_id`      INT(11)  NOT NULL,
    `code`        CHAR(10) NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_doc_codes_code` (`code`),
    UNIQUE KEY `uq_doc_codes_doc` (`doc_type`, `doc_id`),
    KEY `idx_doc_codes_business` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doc_signatures` (
    `id`            INT(11)      NOT NULL AUTO_INCREMENT,
    `business_id`   INT(11)      NOT NULL,
    `doc_type`      ENUM('quote','invoice','receipt','delivery','deed') NOT NULL,
    `doc_id`        INT(11)      NOT NULL,
    `version`       INT(11)      NOT NULL DEFAULT 1,
    `party`         ENUM('business','customer') NOT NULL,
    `user_id`       INT(11)      NULL DEFAULT NULL,
    `signer_name`   VARCHAR(150) NOT NULL,
    `signer_role`   VARCHAR(40)  NULL DEFAULT NULL,
    `style`         ENUM('stamp','drawn') NOT NULL DEFAULT 'stamp',
    `image_path`    VARCHAR(255) NULL DEFAULT NULL,
    `content_hash`  CHAR(64)     NOT NULL,
    `facts`         MEDIUMTEXT   NOT NULL,
    `seal`          CHAR(64)     NOT NULL,
    `ip`            VARCHAR(45)  NULL DEFAULT NULL,
    `signed_at`     DATETIME     NOT NULL,
    `superseded_at` DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_doc_signatures_doc` (`doc_type`, `doc_id`, `party`, `version`),
    KEY `idx_doc_signatures_business` (`business_id`, `signed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `quotations` ADD COLUMN IF NOT EXISTS `sign_on` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `invoices`   ADD COLUMN IF NOT EXISTS `sign_on` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `receipts`   ADD COLUMN IF NOT EXISTS `sign_on` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `deliveries` ADD COLUMN IF NOT EXISTS `sign_on` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `deeds`      ADD COLUMN IF NOT EXISTS `sign_on` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `businesses`
    ADD COLUMN IF NOT EXISTS `signing` TEXT NULL DEFAULT NULL;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `signature_path` VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `signature_at` DATETIME NULL DEFAULT NULL;

-- Signed before: an authorized quote, a signed acceptance, a signed delivery
UPDATE `quotations` SET `sign_on` = 1
WHERE `sign_on` = 0 AND (`owner_signature_data` IS NOT NULL OR `signature_data` IS NOT NULL);

UPDATE `deliveries` SET `sign_on` = 1
WHERE `sign_on` = 0 AND `signoff_signature_path` IS NOT NULL;
