-- A new business's second colour is Nfelia's gold (#B69F5E), the one sign-up
-- gives it and onboarding starts from, not the old terracotta (#C27A62).
-- Only the column's default changes: every existing business keeps the
-- colour it has. Safe to run twice.

ALTER TABLE `businesses`
    ALTER `secondary_color` SET DEFAULT '#B69F5E';
