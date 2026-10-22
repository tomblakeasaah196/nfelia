<?php
/**
 * Off camera, halfway through tutorial 2: the rest of Adanna's catalogue,
 * "added the same way" as the bundle made on camera. It runs while the app
 * is filmed under NF_VIDEO_DOMAIN, so it checks APP_ENV only (it adds rows and
 * wipes nothing; the seed before it made the full check).
 */
require __DIR__ . '/../adanna.php';
if (!in_array(APP_ENV, ['local', 'testing'], true)) {
    fwrite(STDERR, "Refusing: APP_ENV must be local or testing.\n");
    exit(2);
}
adanna_products(get_db(), ['bone']);
