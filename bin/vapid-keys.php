<?php
/**
 * Prints a new VAPID key pair for phone notifications, ready to paste into
 * the docroot .env. Run once:  php bin/vapid-keys.php
 *
 * The private key is a secret: it goes in .env only, never in git or chat.
 * Keep the pair for good; replacing it silently cuts off every phone that
 * already turned notifications on.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

require __DIR__ . '/../app/helpers/webpush.php';

$pair = webpush_new_keypair();
echo "VAPID_PUBLIC_KEY="  . b64url_encode($pair['public']) . "\n";
echo "VAPID_PRIVATE_KEY=" . b64url_encode($pair['d']) . "\n";
echo "VAPID_SUBJECT=mailto:support@nfelia.com\n";
