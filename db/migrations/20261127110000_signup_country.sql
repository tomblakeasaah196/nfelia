-- Where an owner signed up from (Cloudflare's country header, two letters),
-- kept at sign-up (AuthController::register): owners in Cameroon get the
-- welcome and getting-started emails in French (app/helpers/getting_started.php)
-- before they choose their country in onboarding.

ALTER TABLE businesses
    ADD COLUMN IF NOT EXISTS signup_country CHAR(2) NULL DEFAULT NULL;
