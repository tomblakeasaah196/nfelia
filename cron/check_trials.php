<?php
// Load the correct core config and helpers
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/subscription_helpers.php';

check_expiring_trials();
echo "Trial check complete.\n";