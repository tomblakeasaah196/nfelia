<?php
// Free trials that end within three days: the owner is told (bell, phone,
// email). cron/planner.php runs this on every pass, so it needs no cron line
// of its own; this script stays for running it by hand.
//
// cron/ is copied into the public docroot: never run it from the web.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/mail.php';
require_once __DIR__ . '/../app/helpers/webpush.php';
require_once __DIR__ . '/../app/helpers/planner.php';
require_once __DIR__ . '/../app/helpers/notify.php';
require_once __DIR__ . '/../app/helpers/subscription_helpers.php';

// Never alongside cron/planner.php, which does this too (P19)
if ((int)get_db()->query("SELECT GET_LOCK('nfelia_planner', 0)")->fetchColumn() !== 1) {
    echo "The planner is running; it checks trials itself.\n";
    exit(0);
}
$sent = check_expiring_trials();
notify_flush();
echo "Trial check complete: {$sent} business" . ($sent === 1 ? '' : 'es') . " told.\n";
