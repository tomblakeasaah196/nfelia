<?php
/**
 * Prices (OpsPricesController::index): each country's prices today, changes
 * still to come, and a change form per country; then every change made.
 * $rows ([country, now, latest, coming]), $history, $notice_days.
 */
$day   = fn(?string $d) => $d ? date('j M Y', strtotime($d)) : '';
$state = fn(array $c) => match (prices_change_state($c)) {
    'cancelled' => '<span class="ops-chip ops-chip-muted">Called off</span>',
    'scheduled' => '<span class="ops-chip ops-chip-accent">Coming</span>',
    'new_only'  => '<span class="ops-chip ops-chip-warn">New sign-ups now</span>',
    default     => '<span class="ops-chip ops-chip-ok">Everyone</span>',
};
$what = function (array $c): string {
    $parts = [];
    foreach (PRICE_ITEMS as $item => $def) {
        if ($c[$def['change']] !== null) {
            $parts[] = ucfirst($def['short']) . ' ' . prices_money((float)$c[$def['old']], $c) . ' → ' . prices_money((float)$c[$def['change']], $c);
        }
    }
    return implode(' · ', $parts);
};
?>
<div class="ops-head">
    <div>
        <h1>Prices</h1>
        <p>Everything Nfelia charges, in each country. A price set here is the price everywhere: the website, sign-up, Billing, Paystack and Mobile Money.
           Clients already here keep today's price until the date you give them.</p>
    </div>
</div>

<div class="ops-stack">
<?php foreach ($rows as $row): $c = $row['country']; $cid = (int)$c['id']; ?>
    <section class="ops-card" aria-labelledby="pc-<?= $cid ?>">
        <div class="ops-card-body">
            <div class="ops-toolbar" style="margin-bottom:12px">
                <h2 id="pc-<?= $cid ?>" style="margin:0"><?= e(trim(($c['flag_emoji'] ?? '') . ' ' . $c['name'])) ?>
                    <span class="muted small"> · <?= e($c['currency_code']) ?> · <?= (int)$c['businesses'] ?> business<?= (int)$c['businesses'] === 1 ? '' : 'es' ?></span></h2>
                <span class="ops-chip <?= (int)$c['is_active'] ? 'ops-chip-ok' : 'ops-chip-muted' ?>"><?= (int)$c['is_active'] ? 'Offered' : 'Not offered' ?></span>
                <button type="button" class="ops-btn ops-btn-secondary ops-btn-sm" data-ops-dialog="change-<?= $cid ?>" style="margin-left:auto">Change prices</button>
            </div>
            <div class="ops-kpis ops-kpis-sm">
                <?php foreach (PRICE_ITEMS as $item => $def): ?>
                <div class="ops-kpi">
                    <div class="label"><?= e($def['label']) ?></div>
                    <div class="value"><?= e(prices_money($row['now'][$item], $c)) ?></div>
                    <?php if (abs($row['latest'][$item] - $row['now'][$item]) >= 0.005): ?><div class="note">Going to <?= e(prices_money($row['latest'][$item], $c)) ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($row['coming']): ?>
            <ul class="ops-plain" style="margin-top:12px">
                <?php foreach ($row['coming'] as $ch): $ch += $c; ?>
                <li><span class="ops-break"><a href="<?= e(ops_url('prices/' . (int)$ch['id'])) ?>"><?= e($what($ch)) ?></a>
                    <span class="muted small" style="display:block">New sign-ups from <?= e($day($ch['new_from'])) ?>, clients already here from <?= e($day($ch['existing_from'])) ?><?= $ch['notice_sent_at'] ? ' · told ' . e($day($ch['notice_sent_at'])) : ' · not told yet' ?></span></span>
                    <?= $state($ch) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </section>

    <dialog class="ops-dialog" id="change-<?= $cid ?>" aria-labelledby="change-<?= $cid ?>-title">
        <form method="post" action="<?= e(ops_url('prices')) ?>">
            <?= ops_csrf_field() ?>
            <input type="hidden" name="country" value="<?= $cid ?>">
            <div class="ops-dialog-head"><h2 id="change-<?= $cid ?>-title">New prices for <?= e($c['name']) ?></h2><button type="button" data-ops-close aria-label="Close"><?= ops_icon('close') ?></button></div>
            <div class="ops-dialog-body">
                <div class="ops-row">
                    <?php foreach (PRICE_ITEMS as $item => $def): ?>
                    <div class="ops-field"><label for="p-<?= $item ?>-<?= $cid ?>"><?= e($def['label']) ?> (<?= e($c['currency_code']) ?>)</label>
                        <input class="ops-input num" id="p-<?= $item ?>-<?= $cid ?>" name="<?= $item ?>" inputmode="decimal" required value="<?= e((string)(float)$row['latest'][$item]) ?>"></div>
                    <?php endforeach; ?>
                </div>
                <div class="ops-row">
                    <div class="ops-field"><label for="nf-<?= $cid ?>">New sign-ups and the website from</label>
                        <input class="ops-input" type="date" id="nf-<?= $cid ?>" name="new_from" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="ops-field"><label for="ef-<?= $cid ?>">Clients already here from</label>
                        <input class="ops-input" type="date" id="ef-<?= $cid ?>" name="existing_from" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+' . $notice_days . ' days')) ?>" required>
                        <span class="hint"><?= (int)$notice_days ?> days' notice by default. A month that starts before this keeps today's price.</span></div>
                </div>
                <div class="ops-field"><label for="rs-<?= $cid ?>">Why <span class="muted">(clients read this in the email)</span></label>
                    <textarea class="ops-textarea" id="rs-<?= $cid ?>" name="reason" maxlength="500" placeholder="e.g. To keep adding what you ask for: delivery tracking, more ways to get paid, and support on WhatsApp."></textarea></div>
                <p class="small muted" style="margin:0">Nobody is told yet: next you read the email, send yourself a test, and choose who hears.</p>
            </div>
            <div class="ops-dialog-foot"><button type="button" class="ops-btn ops-btn-secondary" data-ops-close>Cancel</button><button type="submit" class="ops-btn ops-btn-primary">Save the change</button></div>
        </form>
    </dialog>
<?php endforeach; ?>
</div>

<section class="ops-section" aria-labelledby="changes-h">
    <h2 id="changes-h">Every change</h2>
    <p>Who changed what, from when, and whether clients were told.</p>
    <div class="ops-table-wrap">
        <table class="ops-table is-stacked">
            <thead><tr><th scope="col">What</th><th scope="col">From</th><th scope="col">State</th><th scope="col">Told</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr class="is-link">
                    <td><a class="cell-main" href="<?= e(ops_url('prices/' . (int)$h['id'])) ?>"><?= e(trim(($h['flag_emoji'] ?? '') . ' ' . $h['country_name'])) ?></a>
                        <span class="cell-sub"><?= e($what($h)) ?></span></td>
                    <td data-label="From">New <?= e($day($h['new_from'])) ?><span class="cell-sub">Already here <?= e($day($h['existing_from'])) ?></span></td>
                    <td data-label="State"><?= $state($h) ?><span class="cell-sub"><?= e($h['author'] ?? '—') ?></span></td>
                    <td data-label="Told"><?= (int)$h['told'] ? number_format((int)$h['told']) . ' client' . ((int)$h['told'] === 1 ? '' : 's') : '<span class="muted">Not yet</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$history): ?>
                <tr><td colspan="4"><div class="ops-empty"><h3>No changes yet</h3><p>Every price is still the one each country started with.</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
