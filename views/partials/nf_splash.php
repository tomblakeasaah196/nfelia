<?php
/**
 * The opening, shown when the installed app starts (its start URL carries
 * ?source=pwa): on the app's first page, or on sign-in when the app starts
 * signed out. The page puts class="nf-splashing" on <html>. The first frame
 * is the launch screen the phone has just shown (icons/launch/*.png, drawn
 * the same way by docs/brand/icons/make_icons.js), so nothing jumps.
 * assets/js/splash.js then plays the film the first time, and the gold
 * thread after that. Everything it needs is here, so any page can show it.
 * The mark's parts come from assets/img/brand/nfelia-mark-parts.svg.
 */
$mark_svg = (string)file_get_contents(BASE_PATH . '/assets/img/brand/nfelia-mark-parts.svg');
preg_match('#<defs>.*</defs>#s', $mark_svg, $m);
$splash_js = BASE_PATH . '/assets/js/splash.js';
?>
<style>
    .nf-splash { position: fixed; inset: 0; z-index: 200; background: #6E0039; display: none; overflow: hidden; }
    html.nf-splashing .nf-splash { display: block; }
    html.nf-splashing body { overflow: hidden; }
    .nf-splash svg { position: absolute; inset: 0; width: 100%; height: 100%; }
    .nf-splash .sp-word { font: 600 104px "Plus Jakarta Sans", system-ui, sans-serif; letter-spacing: 34px; fill: #F7F3EA; opacity: 0; }
    .nf-splash .sheen { fill: url(#nf-sheen); opacity: 0; }
    .nf-splash .film-bg { position: absolute; inset: 0; background: #E9E5DD; opacity: 0; transition: opacity 300ms ease; }
    .nf-splash video { position: absolute; left: 0; right: 0; top: 50%; width: 100%; transform: translateY(-50%); opacity: 0; transition: opacity 300ms ease;
        -webkit-mask-image: radial-gradient(ellipse 72% 68% at 50% 50%, #000 62%, transparent 100%); mask-image: radial-gradient(ellipse 72% 68% at 50% 50%, #000 62%, transparent 100%); }
    .nf-splash.is-film .film-bg, .nf-splash.is-film video { opacity: 1; }
    .nf-splash.is-done { opacity: 0; transition: opacity 300ms ease; pointer-events: none; }
    @media (prefers-reduced-motion: reduce) { .nf-splash.is-done { transition: none; } }
</style>
<div class="nf-splash" id="nf-splash" aria-hidden="true">
    <svg viewBox="-399 -1818 2097 4537" preserveAspectRatio="xMidYMid slice" focusable="false">
        <?= $m[0] ?? '' ?>
        <defs>
            <mask id="nf-m-thread" maskUnits="userSpaceOnUse" x="-200" y="-200" width="1700" height="1350"><use href="#nf-thread" fill="#fff"/><use href="#nf-band" fill="#fff"/></mask>
            <mask id="nf-m-box" maskUnits="userSpaceOnUse" x="-200" y="-200" width="1700" height="1350"><use href="#nf-box" fill="#fff"/></mask>
            <linearGradient id="nf-sheen" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#E9C46A" stop-opacity="0"/><stop offset=".5" stop-color="#E9C46A" stop-opacity=".7"/><stop offset="1" stop-color="#E9C46A" stop-opacity="0"/></linearGradient>
            <filter id="nf-glow" x="-10%" y="-20%" width="120%" height="140%" color-interpolation-filters="sRGB"><feGaussianBlur stdDeviation="10" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
        </defs>
        <use href="#nf-box" fill="#F7F3EA"/>
        <use href="#nf-thread" fill="#D2B46C"/>
        <use href="#nf-band" fill="#D2B46C"/>
        <g mask="url(#nf-m-box)"><g transform="skewX(-22)"><rect class="sheen" x="560" y="-60" width="300" height="1100"/></g></g>
        <g filter="url(#nf-glow)"><g class="glint" mask="url(#nf-m-thread)">
            <?php foreach ([[320, .6, 46, '#F0CF7E'], [150, .85, 34, '#FFEFC2'], [55, 1, 30, '#FFFFFF']] as [$len, $op, $width, $colour]): ?>
            <use href="#nf-centre" pathLength="1000" data-len="<?= $len ?>" style="fill:none;stroke:<?= $colour ?>;stroke-opacity:<?= $op ?>;stroke-width:<?= $width ?>;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:<?= $len ?> 3000;stroke-dashoffset:<?= $len ?>"/>
            <?php endforeach; ?>
        </g></g>
        <text class="sp-word" x="667" y="1190" text-anchor="middle">NFELIA</text>
    </svg>
    <div class="film-bg"></div>
    <video muted playsinline preload="none" data-src="<?= APP_URL ?>/assets/video/nfelia-intro.mp4"></video>
</div>
<script>
// Frame the mark exactly as the launch screen did, before the first paint.
// Within one launch the opening plays once: later pages skip it.
(function () {
    var s = document.getElementById('nf-splash'), svg = s.querySelector('svg');
    var seen = false;
    try { seen = sessionStorage.getItem('nf.launched') === '1'; } catch (e) {}
    if (seen) {
        document.documentElement.classList.remove('nf-splashing');
        s.remove();
        var m = document.querySelector('meta[name="theme-color"]');
        if (m) m.setAttribute('content', m.getAttribute('data-theme-color') || '#FFFFFF');
        history.replaceState(history.state, '', location.pathname + location.search.replace(/([?&])source=pwa&?/, '$1').replace(/[?&]$/, '') + location.hash);
        return;
    }
    var w = 1300 / 0.62, h = w * innerHeight / Math.max(1, innerWidth);
    svg.setAttribute('viewBox', [650 - w / 2, 475 - h / 2 - 60, w, h].join(' '));
})();
</script>
<script src="<?= APP_URL ?>/assets/js/splash.js?v=<?= is_file($splash_js) ? filemtime($splash_js) : 1 ?>" defer></script>
