-- Payment accounts: a bank account or a Mobile Money number.
--
-- Until now an account could only be typed in during onboarding, and every
-- one was treated as a bank account, so a shop whose only account is
-- Mobile Money told its customers to "pay by bank transfer". Each account now
-- says which kind it is, and the owner manages them in Settings.
--
-- Accounts already saved under a Mobile Money name become Mobile Money; the
-- rest stay bank accounts, as they always were.
--
-- The number grows from 20 to 40 characters: a Cameroonian RIB is 23 digits
-- and an IBAN up to 34, and people type them with spaces.

ALTER TABLE `bank_accounts`
  ADD COLUMN IF NOT EXISTS `kind` ENUM('bank','mobile_money') NOT NULL DEFAULT 'bank' AFTER `business_id`,
  MODIFY `account_number` VARCHAR(40) NOT NULL;

UPDATE `bank_accounts`
   SET `kind` = 'mobile_money'
 WHERE `kind` = 'bank'
   AND LOWER(`bank_name`) REGEXP 'mobile money|momo|orange money|airtel money|m-?pesa|^mtn';
