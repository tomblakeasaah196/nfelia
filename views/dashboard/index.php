<?php
/**
 * Home. Renders inside views/layouts/app.php.
 *
 * From DashboardController::index:
 *   $greeting, $money (in, owed, spent: this month), $overdue_count,
 *   $overdue_value, $orders_waiting, $low_stock_count, $deliveries_open,
 *   $quotes_accepted, $plan (Tasks and Calendar items, late first),
 *   $recent_orders
 * Money is shown to owners and admins only ($is_boss, from the layout).
 */
$currency = $business['currency_symbol'] ?? '₦';
$today    = planner_today();
$in       = $money['in'];
$owed     = $money['owed'];
$left     = $in - $money['spent'];
$in_hand  = ($in + $owed) > 0 ? (int)round($in / ($in + $owed) * 100) : 0;
$plural   = fn(int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);

// What needs someone, most urgent first. Each opens the list it counts.
$attention = [];
if ($is_boss && $overdue_count > 0) {
    $attention[] = ['alert', $plural((int)$overdue_count, 'invoice is overdue', 'invoices are overdue'), planner_money((float)$overdue_value, $currency) . ' to collect', 'invoices?status=overdue', 'is-owed'];
}
if ($orders_waiting > 0) {
    $attention[] = ['orders', $plural((int)$orders_waiting, 'shop order is waiting', 'shop orders are waiting'), 'Check the payment, then confirm', 'orders?view=confirm', ''];
}
if ($quotes_accepted > 0) {
    $attention[] = ['quote', $plural((int)$quotes_accepted, 'quote was accepted', 'quotes were accepted'), 'Ready to make into invoices', 'quotations?status=accepted', ''];
}
if ($low_stock_count > 0) {
    $attention[] = ['stock', $plural((int)$low_stock_count, 'item is low or out', 'items are low or out'), 'Restock before you run out', 'products?stock=restock', 'is-warn'];
}
if ($deliveries_open > 0) {
    $attention[] = ['delivery', $plural((int)$deliveries_open, 'delivery is on its way', 'deliveries are on their way'), 'Not signed for yet', 'deliveries', ''];
}
?>
<div class="nf-hello">
    <h1 class="nf-large-title"><?= e($greeting) ?>, <?= e($first_name) ?></h1>
    <p><?= e(planner_now()->format('l j F')) ?></p>
</div>

<div class="nf-find is-home" data-omni>
    <label class="nf-search"><?= nav_icon('search') ?>
        <input type="search" placeholder="Find a client, order or item" aria-label="Search your business" autocomplete="off" enterkeyhint="search"
               aria-controls="nf-omni" aria-expanded="false">
    </label>
    <div class="nf-results" id="nf-omni" hidden></div>
</div>

<div class="nf-cols">
    <div>
        <?php if ($is_boss): ?>
            <section class="nf-money" aria-labelledby="nf-month-h">
                <p class="nf-money-k" id="nf-month-h">Came in this month</p>
                <p class="nf-money-v<?= $in > 0 ? ' is-in' : '' ?>"><?= planner_money($in, $currency) ?></p>
                <?php if ($in + $owed > 0): ?>
                    <div class="nf-thread<?= $in > 0 ? '' : ' is-empty' ?>" style="--done: <?= $in_hand ?>%" role="img"
                         aria-label="<?= $in_hand ?>% in hand, the rest still owed"><i></i></div>
                    <p class="nf-thread-key">
                        <span><b><?= planner_money($in, $currency) ?></b> in hand</span>
                        <a href="<?= APP_URL ?>/orders?view=balance"><b class="<?= $owed > 0 ? 'is-owed' : '' ?>"><?= planner_money($owed, $currency) ?></b> still owed</a>
                    </p>
                <?php endif; ?>
                <dl class="nf-figs">
                    <div><dt>Spent this month</dt><dd><?= planner_money($money['spent'], $currency) ?></dd></div>
                    <div><dt>Left after spending</dt><dd class="<?= $left < 0 ? 'is-owed' : ($left > 0 ? 'is-in' : '') ?>"><?= $left < 0 ? '−' : '' ?><?= planner_money(abs($left), $currency) ?></dd></div>
                </dl>
            </section>
        <?php endif; ?>

        <section class="nf-card is-flush" aria-labelledby="nf-attn-h">
            <div class="nf-card-h"><h2 id="nf-attn-h">Needs you</h2></div>
            <?php if ($attention): ?>
                <div class="nf-items">
                    <?php foreach ($attention as [$icon, $what, $detail, $url, $tone]): ?>
                        <a class="nf-item" href="<?= APP_URL ?>/<?= $url ?>" data-prefetch>
                            <span class="nf-face is-square <?= $tone ?>"><?= nav_icon($icon) ?></span>
                            <span class="nf-item-main"><b><?= e($what) ?></b><small><?= e($detail) ?></small></span>
                            <span class="nf-go"><?= nav_icon('chevron') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="nf-card-note">All clear: nothing overdue, no orders waiting, and stock is fine.</p>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="nf-card is-flush nf-today" aria-labelledby="nf-today-h">
            <div class="nf-card-h"><h2 id="nf-today-h">Today</h2><a class="nf-link" href="<?= APP_URL ?>/tasks" data-prefetch>Open Tasks</a></div>
            <?php if ($plan): ?>
                <div class="nf-items">
                    <?php foreach (array_slice($plan, 0, 5) as $it):
                        $late = $it['date'] && $it['date'] < $today;
                        $when = $it['countdown']['label'] ?? ($it['time'] ? planner_fmt_time($it['time']) : '');
                        $line = implode(' · ', array_filter([$when, $it['subtitle']])); ?>
                        <a class="nf-item" href="<?= APP_URL ?>/<?= e($it['url'] ?: 'tasks') ?>">
                            <span class="nf-item-main">
                                <b><?= e($it['title']) ?></b>
                                <?php if ($line !== ''): ?><small class="<?= $late || in_array($it['countdown']['tone'] ?? '', ['late', 'today'], true) ? 'is-late' : '' ?>"><?= e($line) ?></small><?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($plan) > 5): ?>
                    <p class="nf-card-note"><a class="nf-link" href="<?= APP_URL ?>/tasks">And <?= count($plan) - 5 ?> more in Tasks</a></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="nf-card-note">Nothing planned for today. <a class="nf-link" href="<?= APP_URL ?>/tasks?new=task">Add a task</a></p>
            <?php endif; ?>
        </section>

        <section class="nf-card is-flush" aria-labelledby="nf-recent-h">
            <div class="nf-card-h"><h2 id="nf-recent-h">Latest orders</h2><a class="nf-link" href="<?= APP_URL ?>/orders" data-prefetch>See all</a></div>
            <?php if ($recent_orders): ?>
                <div class="nf-items">
                    <?php foreach ($recent_orders as $o) require BASE_PATH . '/views/partials/order_row.php'; ?>
                </div>
            <?php else: ?>
                <p class="nf-card-note">No orders yet. <a class="nf-link" href="<?= APP_URL ?>/orders/create">Take your first order</a></p>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>
// Search across clients, stock, orders, invoices and quotes (DashboardController::search).
// Results are built as elements, never as HTML: names come from customers.
(function () {
    var box = document.querySelector('[data-omni]');
    if (!box) return;
    var input = box.querySelector('input');
    var list = box.querySelector('.nf-results');
    var timer = null, seq = 0;
    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); }
    function note(text) { list.textContent = ''; var p = document.createElement('p'); p.textContent = text; list.appendChild(p); open(); }
    function open() { list.hidden = false; input.setAttribute('aria-expanded', 'true'); }
    function show(results) {
        if (!results.length) return note('Nothing found. Try part of a name, or a number.');
        list.textContent = '';
        results.forEach(function (r) {
            var a = document.createElement('a'); a.className = 'nf-item'; a.href = r.url;
            var main = document.createElement('span'); main.className = 'nf-item-main';
            var b = document.createElement('b'); b.textContent = r.text;
            var small = document.createElement('small'); small.textContent = r.sub;
            main.appendChild(b); main.appendChild(small);
            var tag = document.createElement('span'); tag.className = 'nf-tag'; tag.textContent = r.type;
            a.appendChild(main); a.appendChild(tag);
            list.appendChild(a);
        });
        open();
    }
    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) return close();
        timer = setTimeout(function () {
            var mine = ++seq;
            fetch(window.Nfelia.url + '/dashboard/search', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ query: q, csrf_token: window.Nfelia.csrf })
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (mine === seq) show(d.results || []);
            }).catch(function () {
                if (mine === seq) note('Search needs the internet. Try again when you are connected.');
            });
        }, 250);
    });
    input.addEventListener('focus', function () { if (list.childNodes.length && input.value.trim().length > 1) open(); });
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && (e.key || '').toLowerCase() === 'k') { e.preventDefault(); input.focus(); }
        else if (e.key === 'Escape' && !list.hidden) close();
    });
    document.addEventListener('click', function (e) { if (!box.contains(e.target)) close(); });
})();
</script>
