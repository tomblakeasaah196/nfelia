<?php
/**
 * Your records (RecordsController::index): the Records Book, the plain copy,
 * and requests from people who checked a code. Rendered in the app layout.
 * Variables: $offer (records_offer()), $books, $plain, $requests, $checking
 * (a Mobile Money receipt for a book being checked), $sheets
 * (records_sheets()), $month_price.
 */
$csrf  = e($_SESSION['csrf_token'] ?? '');
$price = records_money($offer['price'], $offer['symbol']);
$month = records_money((float)$month_price, $offer['symbol']);
$when  = fn(?string $d, bool $time = false) => $d ? date($time ? 'j M Y, g:ia' : 'j M Y', strtotime($d)) : '';
$counts = fn(array $b) => array_sum(json_decode((string)$b['counts'], true) ?: []);
$waiting = array_filter($books, fn($b) => in_array($b['status'], ['queued', 'building'], true));
$groups = [];
foreach ($sheets as $key => $s) {
    if (!isset($s['line_of'])) $groups[$s['group']][$key] = $s;
}
$status_tag = function (array $b): string {
    return match ($b['status']) {
        'queued', 'building'  => '<span class="nf-tag is-accent" data-building="' . (int)$b['id'] . '">Making it…</span>',
        'awaiting_payment'    => '<span class="nf-tag is-warn">Waiting for payment</span>',
        'failed'              => '<span class="nf-tag is-owed">Didn\'t work</span>',
        'expired'             => '<span class="nf-tag is-line">No longer kept</span>',
        default               => '',
    };
};
?>
<div class="nf-page">
<div class="nf-head">
    <div>
        <h1 class="nf-large-title">Your records</h1>
        <p>Everything you have recorded in Nfelia is yours: take it with you, show it, keep it.</p>
    </div>
</div>

<section class="nf-card nf-rb" aria-labelledby="rb-h">
    <div class="nf-rb-hero">
        <div class="nf-rb-cover" aria-hidden="true">
            <span class="nf-rb-mark"></span>
            <b><?= e($business['name'] ?? 'Your business') ?></b>
            <small>Records Book</small>
            <i></i>
        </div>
        <div class="nf-rb-copy">
            <h2 id="rb-h">Your Records Book</h2>
            <p>All your records in one Excel workbook, in your colours: customers, sales, money and stock, with totals, a chart of your months,
               a link to every invoice and receipt, and a code anyone can check at nfelia.com/verify. Show it to your bank, your accountant, or take it wherever you go next.</p>
        </div>
    </div>

    <?php if ($checking): ?>
        <p class="nf-banner is-info" role="status">We are checking your Mobile Money payment for a Records Book. We make it as soon as it is approved.</p>
    <?php endif; ?>

    <form method="POST" action="<?= APP_URL ?>/records/make" class="nf-rb-form">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <p class="nf-rb-offer">
        <?php if ($offer['free']): ?>
            <b>Free this month</b>, as part of your plan. One book in every month you pay for.
        <?php elseif ($offer['access'] === 'paid'): ?>
            You made this month's free book on <?= e($when($offer['free_used'])) ?>.<?= $offer['next_free'] ? ' Your next free one comes with your next month, from ' . e($when($offer['next_free'])) . '.' : '' ?>
            Need another now? <b><?= e($price) ?></b>.
        <?php elseif ($offer['access'] === 'trial'): ?>
            <b><?= e($price) ?></b> for one book. Or pay for a month of Nfelia (<?= e($month) ?>), and a book every month is free.
        <?php else: ?>
            Your plan has ended, but your records are safe with us. Get them all in one book for <b><?= e($price) ?></b>, or pay for a month (<?= e($month) ?>) and your book is free.
        <?php endif; ?>
        </p>

        <details class="nf-rb-pick">
            <summary>Choose what goes in <small>Everything, all time</small></summary>
            <fieldset class="nf-rb-period">
                <legend>Which records</legend>
                <?php foreach (['all' => 'All time', 'this_year' => 'This year', '12_months' => 'The last 12 months', 'last_year' => 'Last year', 'custom' => 'Dates I choose'] as $k => $label): ?>
                    <label><input type="radio" name="period" value="<?= $k ?>"<?= $k === 'all' ? ' checked' : '' ?>><span><?= $label ?></span></label>
                <?php endforeach; ?>
                <div class="nf-rb-dates">
                    <label>From<input type="date" name="from" max="<?= date('Y-m-d') ?>"></label>
                    <label>To<input type="date" name="to" max="<?= date('Y-m-d') ?>"></label>
                </div>
            </fieldset>
            <?php foreach (RECORDS_GROUPS as $g => $glabel): if (empty($groups[$g])) continue; ?>
            <fieldset class="nf-rb-sheets">
                <legend><?= e($glabel) ?></legend>
                <?php foreach ($groups[$g] as $key => $s): ?>
                    <label><input type="checkbox" name="sheets[]" value="<?= e($key) ?>" checked><span><?= e($s['title']) ?><?= !empty($s['lines']) ? ' <small>with their lines</small>' : '' ?></span></label>
                <?php endforeach; ?>
            </fieldset>
            <?php endforeach; ?>
        </details>

        <div class="nf-actions-flush">
            <?php if ($waiting): ?>
                <button type="button" class="nf-btn nf-btn-primary nf-wide" disabled>Making your book…</button>
            <?php else: ?>
                <button type="submit" class="nf-btn nf-btn-primary nf-wide"><?= $offer['free'] ? 'Make my Records Book' : 'Get my Records Book for ' . e($price) ?></button>
            <?php endif; ?>
            <?php if (!$offer['free'] && in_array($offer['access'], ['trial', 'locked', 'grace'], true)): ?>
                <a class="nf-btn nf-btn-ghost nf-wide" href="<?= APP_URL ?>/settings?tab=billing">Pay for a month instead</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php if ($books): ?>
<section class="nf-card is-flush" aria-labelledby="books-h">
    <div class="nf-card-h"><h2 id="books-h">Your books</h2></div>
    <p class="nf-card-note">Kept for a year. Download as often as you like.</p>
    <div class="nf-items nf-rb-list">
        <?php foreach ($books as $bk): ?>
        <div class="nf-item is-wrap" id="book-<?= (int)$bk['id'] ?>">
            <span class="nf-face is-square" aria-hidden="true"><?= nav_icon('book') ?></span>
            <div class="nf-item-main">
                <b><?= e($when($bk['created_at'])) ?></b>
                <small><?= e(records_period_text($bk)) ?><?= $bk['status'] === 'ready' ? ' · ' . number_format($counts($bk)) . ' records' : '' ?>
                    · <?= e(['free' => 'free with your plan', 'paid' => 'paid', 'team' => 'made by Nfelia support', 'raw' => ''][$bk['charge']] ?? '') ?></small>
                <?php if ($bk['status'] === 'ready'): ?><small>Code <?= e(records_code_display((string)$bk['code'])) ?></small><?php endif; ?>
            </div>
            <div class="nf-item-end">
                <?php if ($bk['status'] === 'ready'): ?>
                    <a class="nf-btn nf-btn-ghost" href="<?= APP_URL ?>/records/download/<?= (int)$bk['id'] ?>" download data-no-prefetch>Download</a>
                <?php else: ?>
                    <?= $status_tag($bk) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="nf-card" id="plain" aria-labelledby="plain-h">
    <div class="nf-card-h">
        <div>
            <h2 id="plain-h">Your plain copy</h2>
            <p>Every record as it is kept, in CSV files any spreadsheet opens. Free, always, whatever your plan.</p>
        </div>
    </div>
    <?php foreach (array_slice($plain, 0, 3) as $pc): ?>
        <div class="nf-item" style="padding-inline:0">
            <div class="nf-item-main"><b>Plain copy · <?= e($when($pc['created_at'], true)) ?></b>
                <small><?= $pc['status'] === 'ready' ? 'Kept until ' . e($when($pc['expires_at'])) : '' ?></small></div>
            <div class="nf-item-end">
                <?php if ($pc['status'] === 'ready'): ?>
                    <a class="nf-btn nf-btn-ghost" href="<?= APP_URL ?>/records/download/<?= (int)$pc['id'] ?>" download data-no-prefetch>Download</a>
                <?php else: ?><?= $status_tag($pc) ?><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <form method="POST" action="<?= APP_URL ?>/records/plain">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <button type="submit" class="nf-link">Get my plain copy</button>
    </form>
</section>

<?php if ($requests): ?>
<section class="nf-card is-flush" id="requests" aria-labelledby="req-h">
    <div class="nf-card-h"><h2 id="req-h">People who asked for your records</h2></div>
    <p class="nf-card-note">From someone who checked a code at nfelia.com/verify. Nothing is shared unless you say yes.</p>
    <div class="nf-items">
        <?php foreach ($requests as $rq): ?>
        <a class="nf-item" href="<?= APP_URL ?>/records/request/<?= (int)$rq['id'] ?>">
            <div class="nf-item-main">
                <b><?= e($rq['organisation'] ? $rq['organisation'] . ' (' . $rq['name'] . ')' : $rq['name']) ?></b>
                <small><?= e($rq['label']) ?> · <?= e($when($rq['created_at'])) ?></small>
            </div>
            <div class="nf-item-end">
                <?= match ($rq['status']) {
                    'asked'    => '<span class="nf-tag is-warn">Waiting for you</span>',
                    'approved' => '<span class="nf-tag is-in">Shared</span>',
                    'declined' => '<span class="nf-tag">Declined</span>',
                    default    => '<span class="nf-tag is-line">Closed</span>',
                } ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
</div>

<?php if ($waiting): ?>
<script>
// While a book is being made, look every few seconds and show it when it is ready.
(function () {
    var ids = Array.prototype.map.call(document.querySelectorAll('[data-building]'), function (el) { return el.getAttribute('data-building'); });
    var tries = 0;
    function look() {
        if (!ids.length || ++tries > 100) return;
        Promise.all(ids.map(function (id) {
            return fetch('<?= APP_URL ?>/records/status/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
        })).then(function (all) {
            if (all.some(function (s) { return s.ready || s.status === 'failed'; })) { location.reload(); return; }
            setTimeout(look, 3000);
        }).catch(function () { setTimeout(look, 6000); });
    }
    setTimeout(look, 2500);
})();
</script>
<?php endif; ?>
