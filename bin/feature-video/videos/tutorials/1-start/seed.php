<?php
/**
 * Tutorial 1 (start your free trial): nothing to show yet, since the video
 * signs Adanna Luxe Hair up from scratch. A made-up business on the test
 * fixture, never a real one. Clears any earlier take of it and the caught
 * emails, so the video's "mail" step finds this take's welcome email.
 * WIPES the database, so it refuses unless APP_ENV is local or testing.
 */
require __DIR__ . '/../../../../../tests/notifications/lib.php';
t_guard();
fixture_reset();

$mail = getenv('NF_MAIL_DIR');
if ($mail && is_dir($mail)) {
    array_map('unlink', glob($mail . '/*.eml') ?: []);
}
echo json_encode(new stdClass()), "\n";
