<?php
/**
 * What makes a page installable and launch like an app: the manifest, the
 * icons, and the iPhone and iPad launch screens. On iPhone the launch screen
 * comes from the page someone adds to the home screen, so every page that
 * can be installed from includes this: the app, the landing page, sign-in and
 * sign-up. The launch images are drawn by docs/brand/icons/make_icons.js.
 */
$nf_launch_screens = [
    [320, 568, 2], [375, 667, 2], [414, 736, 3], [375, 812, 3], [414, 896, 2], [414, 896, 3],
    [360, 780, 3], [390, 844, 3], [428, 926, 3], [393, 852, 3], [430, 932, 3], [402, 874, 3], [440, 956, 3],
    [744, 1133, 2], [768, 1024, 2], [810, 1080, 2], [820, 1180, 2], [834, 1112, 2], [834, 1194, 2], [1024, 1366, 2],
];
?>
    <link rel="manifest" href="<?= APP_URL ?>/site.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/favicon-16x16.png">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Nfelia">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
<?php foreach ($nf_launch_screens as [$w, $h, $s]): ?>
    <link rel="apple-touch-startup-image" media="(device-width: <?= $w ?>px) and (device-height: <?= $h ?>px) and (-webkit-device-pixel-ratio: <?= $s ?>) and (orientation: portrait)" href="<?= APP_URL ?>/icons/launch/<?= $w * $s ?>x<?= $h * $s ?>.png">
<?php endforeach; ?>
