<?php
/* views/emails/ops_security.php — a change to an Ops account. Variables: $name, $what, $url. */
$headline = 'Your Nfelia Ops account changed';
$message  = $what;
$button   = 'Open your account';
$note     = 'If this wasn\'t you, tell an Ops admin straight away: they can sign you out everywhere and reset your access.';
require __DIR__ . '/ops_message.php';
