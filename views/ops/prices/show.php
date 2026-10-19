<?php
/**
 * One price change (OpsPricesController::show): what changes and from when,
 * the email clients get, and telling them. $change, $country, $state,
 * $groups (each with count and on), $people (the ticked groups), $sample
 * (the client the preview is written for), $notice (prices_notice_vars()), $preview, $sent
 * (price_notices by status), $hours.
 */
$day   = fn(?string $d) => $d ? date('j M Y', strtotime($d)) : '';
$days  = (int)round((strtotime((string)$change['existing_from']) - strtotime(date('Y-m-d'))) / 86400);
$open  = in_array($state, ['scheduled', 'new_only'], true);
$told  = $change['notice_sent_at'] !== null;
$total = array_sum($sent);
?>
<p class="ops-back"><a href="<?= e(ops_url('prices')) ?>">← Prices</a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <div class="ops-chips-row">
            <?= match ($state) {
                'cancelled' => '<span class="ops-chip ops-chip-muted">Called off</span>',
                'scheduled' => '<span class="ops-chip ops-chip-accent">Coming</span>',
                'new_only'  => '<span class="ops-chip ops-chip-warn">New sign-ups pay it now</span>',
                default     => '<span class="ops-chip ops-chip-ok">Everyone pays it</span>',
            } ?>
            <span class="ops-chip <?= $told ? 'ops-chip-ok' : 'ops-chip-muted' ?>"><?= $told ? 'Clients told' : 'Clients not told yet' ?></span>
        </div>
        <h1 class="ops-break"><?= e(trim(($country['flag_emoji'] ?? '') . ' ' . $country['name'])) ?>: new prices</h1>
        <p>Saved by <?= e($change['author'] ?? '—') ?> on <?= e(date('j M Y, g:ia', strtotime((string)$change['created_at']))) ?>.
           <?= $change['cancelled_at'] ? 'Called off by ' . e($change['canceller'] ?? '—') . ' on ' . e($day($change['cancelled_at'])) . '.' : '' ?></p>
    </div>
    <div class="ops-head-actions">
        <?php if ($open): ?>
        <form method="post" action="<?= e(ops_url('prices/' . (int)$change['id'] . '/test')) ?>"><?= ops_csrf_field() ?><button class="ops-btn ops-btn-secondary" type="submit">Send me a test</button></form>
        <form method="post" action="<?= e(ops_url('prices/' . (int)$change['id'] . '/cancel')) ?>"
              data-ops-confirm="Call off this change? <?= e($country['name']) ?>'s prices go back to what they were.<?= $told ? ' Clients already told are not told it is off: write to them yourself.' : '' ?>">
            <?= ops_csrf_field() ?><button class="ops-btn ops-btn-danger" type="submit">Call it off</button></form>
        <?php endif; ?>
    </div>
</div>

<div class="ops-detail">
    <div class="ops-detail-main">
        <section class="ops-card"><div class="ops-card-body">
            <h2>What changes</h2>
            <div class="ops-table-wrap" style="margin:4px 0 14px">
                <table class="ops-table">
                    <thead><tr><th scope="col">What</th><th scope="col" style="text-align:right">Before</th><th scope="col" style="text-align:right">New</th></tr></thead>
                    <tbody>
                    <?php foreach (PRICE_ITEMS as $item => $def): if ($change[$def['change']] === null) continue;
                        $old = (float)$change[$def['old']]; $new = (float)$change[$def['change']]; ?>
                        <tr><td><?= e($def['label']) ?></td>
                            <td class="num" style="text-align:right"><?= e(prices_money($old, $country)) ?></td>
                            <td class="num" style="text-align:right"><b><?= e(prices_money($new, $country)) ?></b>
                                <span class="cell-sub"><?= $new > $old ? '+' : '−' ?><?= e(number_format(abs($new - $old) / max($old, 0.01) * 100, 0)) ?>%</span></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <dl class="ops-facts">
                <dt>New sign-ups</dt><dd>From <?= e(date('j M Y', strtotime((string)$change['new_from']))) ?>, and the website shows it from then.</dd>
                <dt>Clients already here</dt><dd>From <?= e($day($change['existing_from'])) ?><?= $days > 0 ? ' (' . $days . ' day' . ($days === 1 ? '' : 's') . ' from today)' : '' ?>. A month that starts before then keeps today's price.</dd>
                <?php if ($change['reason']): ?><dt>Why</dt><dd><?= nl2br(e($change['reason'])) ?></dd><?php endif; ?>
            </dl>
        </div></section>

        <section class="ops-card"><div class="ops-card-body">
            <h2>The email</h2>
            <p class="muted">Subject: <b><?= e($notice['subject']) ?></b>. Written for <?= e($sample['business'] ?? 'a client') ?>: each client sees their own figures.</p>
            <iframe class="ops-preview" title="The email clients get" sandbox srcdoc="<?= e($preview) ?>"></iframe>
        </div></section>
    </div>

    <aside class="ops-detail-side">
        <section class="ops-card"><div class="ops-card-body">
        <?php if (!$told && $open): ?>
            <h2>Tell clients</h2>
            <p class="small muted">Clients in <?= e($country['name']) ?> who joined before <?= e($day($change['new_from'])) ?>. It is about their account, so it reaches those who turned off news emails too. Each also gets a note in the app.</p>
            <form method="post" action="<?= e(ops_url('prices/' . (int)$change['id'] . '/notify')) ?>"
                  data-ops-confirm="Email the clients you ticked about this change? Each gets it once, and it can't be taken back.">
                <?= ops_csrf_field() ?>
                <div class="ops-checks-grid" style="margin:10px 0 14px"><div>
                    <?php foreach ($groups as $g => $def): ?>
                    <label class="ops-check"><input type="checkbox" name="groups[]" value="<?= e($g) ?>"<?= $def['on'] ? ' checked' : '' ?><?= $def['count'] ? '' : ' disabled' ?>>
                        <span><?= e($def['label']) ?> <span class="muted num">(<?= number_format($def['count']) ?>)</span></span></label>
                    <?php endforeach; ?>
                </div></div>
                <button class="ops-btn ops-btn-primary ops-btn-block" type="submit">Tell clients</button>
                <p class="small muted" style="margin:8px 0 0">A few every 5 minutes<?= $hours > 1 ? ', about ' . $hours . ' hours for everyone' : '' ?>.</p>
            </form>
            <?php if ($people): ?>
                <h3 style="margin-top:16px">Ticked now: <?= number_format(count($people)) ?></h3>
                <ul class="ops-plain"><?php foreach (array_slice($people, 0, 8, true) as $p): ?><li><span class="ops-break"><?= e($p['business']) ?></span><span class="muted ops-break"><?= e($p['name']) ?></span></li><?php endforeach; ?></ul>
                <?php if (count($people) > 8): ?><p class="small muted">and <?= number_format(count($people) - 8) ?> more</p><?php endif; ?>
            <?php endif; ?>
        <?php elseif ($told): ?>
            <h2>Clients told</h2>
            <p class="small muted">By <?= e($change['teller'] ?? '—') ?> on <?= e(date('j M Y, g:ia', strtotime((string)$change['notice_sent_at']))) ?>:
                <?= e(implode(', ', array_map(fn($g) => PRICE_NOTICE_GROUPS[$g]['label'] ?? $g, json_decode((string)$change['notice_audience'], true) ?: []))) ?>.</p>
            <dl class="ops-facts" style="margin-top:10px">
                <dt>Delivered</dt><dd class="num"><?= number_format($sent['sent'] ?? 0) ?> of <?= number_format($total) ?></dd>
                <dt>Still to go</dt><dd class="num"><?= number_format(($sent['waiting'] ?? 0) + ($sent['queued'] ?? 0)) ?></dd>
                <?php if (!empty($sent['failed'])): ?><dt>Failed</dt><dd class="num"><?= number_format($sent['failed']) ?> <a href="<?= e(ops_url('health/sends')) ?>">why</a></dd><?php endif; ?>
                <?php if (!empty($sent['skipped'])): ?><dt>Not sent</dt><dd class="num"><?= number_format($sent['skipped']) ?> (called off)</dd><?php endif; ?>
            </dl>
        <?php else: ?>
            <h2>Clients</h2>
            <p class="small muted"><?= $state === 'cancelled' ? 'This change was called off before clients were told.' : 'Clients already pay this. There is nobody left to tell.' ?></p>
        <?php endif; ?>
        </div></section>
    </aside>
</div>
