-- Home's "Get started" card (app/helpers/first_steps.php): someone who
-- pressed "Hide" no longer sees it. Per person, so a new admin still gets it.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS first_steps_hidden_at DATETIME NULL DEFAULT NULL;
