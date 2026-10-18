<?php
// Free trials that end within three days get a bell note. cron/planner.php
// runs this on every pass, so it needs no cron line of its own; this script
// stays for running it by hand.
//
// cron/ is copied into the public docroot: never run it from the web.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/subscription_helpers.php';

$sent = check_expiring_trials();
echo "Trial check complete: {$sent} note" . ($sent === 1 ? '' : 's') . " saved.\n";
