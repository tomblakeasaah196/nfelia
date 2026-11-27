-- Messages from the home page form that look like spam are kept, but held
-- out of Leads (app/ops/sales.php, lead_spam_reason): on 27 Nov 2026 every
-- one of the 39 "leads" from that form was spam (marketing pitches, fake
-- "search index" offers, price-probe bots named Apple, Google, Nokia).
--
-- general_feedback.spam_reason  why it was held ("has a link to another
--                               site", ...), or NULL for a real message.
-- general_feedback.reviewed_at  when someone in Ops looked at a held one:
--                               made it a lead, or left it as spam.

ALTER TABLE general_feedback
    ADD COLUMN IF NOT EXISTS spam_reason VARCHAR(160) NULL DEFAULT NULL AFTER ip_address,
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL DEFAULT NULL AFTER spam_reason;
