<?php
/* views/emails/ops_reset.php — a forgotten Ops password. Variables: $name, $url. */
$headline = 'Choose a new password';
$message  = 'Someone asked to reset the password of your Nfelia Ops account. Choosing a new one signs you out everywhere else.';
$button   = 'Choose a new password';
$note     = 'This link works once, for an hour. If you didn\'t ask for it, ignore this email: your password stays as it is.';
require __DIR__ . '/ops_message.php';
