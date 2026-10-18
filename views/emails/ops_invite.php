<?php
/* views/emails/ops_invite.php — joining Nfelia Ops. Variables: $name, $inviter, $role, $url. */
$headline = 'You\'re invited to Nfelia Ops';
$message  = $inviter . ' added you to the Nfelia team\'s Ops, as ' . $role . '. Choose a password to start; then add a passkey so signing in takes one touch.';
$button   = 'Join Nfelia Ops';
$note     = 'This link works once, for 72 hours. If you weren\'t expecting it, ignore this email.';
require __DIR__ . '/ops_message.php';
