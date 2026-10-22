<?php
/**
 * The opening's first colour (views/partials/nf_splash.php), decided in
 * <head> before anything is drawn. An iPhone or iPad launches the app on wine,
 * its launch screens (icons/launch/*.png), and the page arrives with a wine
 * status bar to match. Android and computers launch it on white, the
 * manifest's background_color (site.webmanifest), which Android also keeps
 * for its bottom bar: there the status bar starts white too, and the opening
 * fades in from white.
 */
?>
    <script>if (!('standalone' in navigator)) { document.documentElement.classList.add('nf-launch-light'); document.querySelector('meta[name="theme-color"]').setAttribute('content', '#FFFFFF'); }</script>
