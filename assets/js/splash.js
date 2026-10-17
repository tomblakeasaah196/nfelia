/**
 * The opening, in the installed app (views/partials/nf_splash.php): the brand
 * film the first time the app is opened after install, the gold thread on
 * every later start. A tap skips it, and it never stays more than 9 seconds.
 * Loaded only by a page that shows it: the app's first page of a launch, or
 * sign-in when the app starts signed out.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var splash = document.getElementById('nf-splash');
    if (!splash || !root.classList.contains('nf-splashing')) return;

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var store = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    };
    var themeMeta = document.querySelector('meta[name="theme-color"]');
    var finished = false;

    function finish() {
        if (finished) return;
        finished = true;
        try { sessionStorage.setItem('nf.launched', '1'); } catch (e) {}
        if (themeMeta) themeMeta.setAttribute('content', themeMeta.getAttribute('data-theme-color') || '#FFFFFF');
        // The address no longer needs to say how the app was opened.
        history.replaceState(history.state, '', location.pathname + location.search.replace(/([?&])source=pwa&?/, '$1').replace(/[?&]$/, '') + location.hash);
        splash.classList.add('is-done');
        setTimeout(function () { root.classList.remove('nf-splashing'); splash.remove(); }, reduceMotion ? 0 : 300);
    }
    splash.addEventListener('click', finish);
    setTimeout(finish, 9000);

    var threading = false;
    function playThread() {
        if (threading) return;
        threading = true;
        if (reduceMotion) { setTimeout(finish, 350); return; }
        splash.querySelectorAll('.glint use').forEach(function (u) {
            var len = +u.getAttribute('data-len');
            u.animate([{ strokeDashoffset: String(len) }, { strokeDashoffset: String(len - 1060) }],
                { duration: 1050, delay: 250, easing: 'cubic-bezier(.45,0,.25,1)', fill: 'forwards' });
        });
        var sheen = splash.querySelector('.sheen');
        if (sheen) sheen.animate([{ opacity: 0, transform: 'translateX(-560px)' }, { opacity: 1, offset: .5 }, { opacity: 0, transform: 'translateX(840px)' }],
            { duration: 700, delay: 630, easing: 'ease-in-out', fill: 'forwards' });
        var word = splash.querySelector('.sp-word');
        if (word) word.animate([{ opacity: 0, transform: 'translateY(40px)' }, { opacity: 1, transform: 'none' }],
            { duration: 420, delay: 1150, easing: 'cubic-bezier(.16,1,.3,1)', fill: 'forwards' });
        setTimeout(finish, 1900);
    }

    var film = splash.querySelector('video');
    if (!reduceMotion && film && !store.get('nf.filmSeen')) {
        // The first launch after install: the brand film, as a welcome.
        film.src = film.getAttribute('data-src');
        film.preload = 'auto';
        var started = false;
        film.addEventListener('playing', function () {
            started = true;
            splash.classList.add('is-film');
            if (themeMeta) themeMeta.setAttribute('content', '#E9E5DD');
        }, { once: true });
        film.addEventListener('ended', function () { store.set('nf.filmSeen', '1'); setTimeout(finish, 250); }, { once: true });
        film.addEventListener('error', function () { if (!started) playThread(); }, { once: true });
        var playing = film.play();
        if (playing && playing.catch) playing.catch(function () { if (!started) playThread(); });
        // A slow network must not hold the app: after 2.5 seconds, the thread instead.
        setTimeout(function () { if (!started) { film.removeAttribute('src'); film.load(); playThread(); } }, 2500);
    } else {
        playThread();
    }
})();
