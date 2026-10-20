/**
 * The password checklist (views/partials/password_rules.php): each rule
 * ticks as it is met, and the form waits until all of them are. The same
 * rules live in app/helpers/auth.php (password_rules), which checks again
 * on the server.
 */
(function () {
    'use strict';
    var MIN = 9;
    var tests = {
        length: function (p) { return p.length >= MIN; },
        upper:  function (p) { return /\p{Lu}/u.test(p); },
        number: function (p) { return /\p{N}/u.test(p); },
        symbol: function (p) { return /[^\p{L}\p{N}\s]/u.test(p); }
    };
    function wire(list) {
        var input = document.querySelector(list.getAttribute('data-nf-pw'));
        if (!input) return;
        var confirmSel = list.getAttribute('data-nf-pw-confirm');
        var confirm = confirmSel ? document.querySelector(confirmSel) : null;
        var items = list.querySelectorAll('li[data-rule]');
        function update() {
            var p = input.value, all = true;
            for (var i = 0; i < items.length; i++) {
                var rule = items[i].getAttribute('data-rule');
                var met = rule === 'match'
                    ? (p !== '' && confirm && confirm.value === p)
                    : (tests[rule] ? tests[rule](p) : true);
                items[i].classList.toggle('is-met', !!met);
                items[i].setAttribute('aria-checked', met ? 'true' : 'false');
                if (!met && rule !== 'match') all = false;
            }
            // The browser's own validation then shows the rule that is missing
            input.setCustomValidity(p === '' || all ? '' : 'Meet every line in the list below the password.');
            if (confirm) confirm.setCustomValidity(confirm.value === '' || confirm.value === p ? '' : 'Type the same password again.');
        }
        input.addEventListener('input', update);
        if (confirm) confirm.addEventListener('input', update);
        update();
    }
    function init() { document.querySelectorAll('[data-nf-pw]').forEach(wire); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
