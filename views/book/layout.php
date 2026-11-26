<?php
/**
 * The frame of nfelia.com/book (MeetingBookController::render()): the
 * Nfelia mark, the flash, the page, and how else to reach us.
 * From render(): $title, $view_file, $flash, and the page's own vars.
 */
$css = BASE_PATH . '/assets/css/book.css';
header('Cache-Control: no-store');   // free times change by the minute

// Only nfelia.com/book itself is for search engines. Each kind of meeting
// (/book/demo, /book/pricing...), each day and time picked (?date, ?time),
// the "pick a new time" links and the thanks page are steps of the same
// booking, and Google had indexed many copies of them: they are kept out of
// search and all name /book as the one address.
$bk_path    = rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$bk_query   = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
$bk_indexed = $view === 'index' && $bk_path === '/book' && $bk_query === '' && !array_diff_key($_GET, ['url' => true]);
if (!$bk_indexed) {
    header('X-Robots-Tag: noindex, follow');
}
if ($view === 'index') {
    $title = 'Book a free call with the Nfelia team: demo or setup help';
}
$bk_desc = $view === 'index'
    ? 'Book a free Google Meet call with the Nfelia team. See a demo, get help setting up your business or shop website, or ask about prices. Pick a day and time.'
    : 'Book a Google Meet call with the Nfelia team: a demo, setup help, your shop website, pricing and more.';
?>
<!DOCTYPE html>
<html lang="en-NG">
<head><meta charset="utf-8">
    
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($bk_desc) ?>">
    <meta name="robots" content="<?= $bk_indexed ? 'index, follow' : 'noindex, follow' ?>">
    <link rel="canonical" href="<?= e(APP_URL . '/book') ?>">
    <meta name="theme-color" content="#8B004A">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Nfelia">
    <meta property="og:locale" content="en_NG">
    <meta property="og:title" content="Book a meeting with the Nfelia team">
    <meta property="og:description" content="Pick a time that suits you. We meet on Google Meet.">
    <meta property="og:url" content="<?= e(APP_URL . '/book') ?>">
    <meta property="og:image" content="<?= e(APP_URL) ?>/assets/img/nfelia-og-banner.png">
    <link rel="icon" href="<?= e(APP_URL) ?>/favicon.ico">
    <link rel="preload" href="<?= e(APP_URL) ?>/assets/fonts/plus-jakarta-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/book.css?v=<?= (int)@filemtime($css) ?>">
</head>
<body>
    <header class="bk-top">
        <a class="bk-brand" href="<?= e(APP_URL) ?>/" aria-label="Nfelia home">
            <img src="<?= e(APP_URL) ?>/assets/img/nfelia-mark-email.png" alt="" width="33" height="24">
            <span>NFELIA</span>
        </a>
    </header>
    <main class="bk-wrap" id="main">
        <?php if ($flash): ?>
            <p class="bk-flash<?= $flash['type'] === 'error' ? '' : ' is-info' ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($flash['message']) ?></p>
        <?php endif; ?>
        <?php require $view_file; ?>
    </main>
    <footer class="bk-foot">
        <p>Questions? Write to <a href="mailto:sales@nfelia.com">sales@nfelia.com</a>, or for help with your account, <a href="mailto:support@nfelia.com">support@nfelia.com</a>.
           <a href="<?= e(APP_URL) ?>/">Back to Nfelia</a></p>
    </footer>
</body>
</html>
