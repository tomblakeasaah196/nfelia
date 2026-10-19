<?php
/** Mobile money payments to check (inbox): $pending, $decided. */
$money = fn(array $p) => $p['currency_symbol'] . number_format((float)$p['amount']) . ' ' . $p['currency_code'];
?>
<div class="ops-head">
    <div>
        <h1>Payments to check</h1>
        <p>Mobile money payments clients sent with a receipt. Check the receipt against your account, then approve or turn it down with a reason they will see. Nothing opens for them until it is approved.</p>
    </div>
</div>
<?php if (!$pending): ?>
    <div class="ops-card ops-empty"><h3>Nothing to check</h3><p>When a client sends a mobile money receipt, it shows here and Admin and Support are told.</p></div>
<?php else: ?>
    <ul class="ops-queue">
    <?php foreach ($pending as $p): ?>
        <li id="payment-<?= (int)$p['id'] ?>">
            <div class="what">
                <b><?= e($p['business_name']) ?></b> sent <b class="num"><?= e($money($p)) ?></b>
                for <?= ($p['kind'] ?? 'month') === 'book' ? 'a Records Book' : (($p['kind'] ?? 'month') === 'seats' ? e((int)$p['extra_seats'] . ' more seat' . ((int)$p['extra_seats'] === 1 ? '' : 's') . ' this month')
                    : 'a month' . ($p['covered_user_ids'] !== null ? ' for the owner' . ((int)$p['extra_seats'] ? ' and ' . (int)$p['extra_seats'] . ' staff' : ' alone') : '')) ?>
                <div class="meta"><?= e(trim(($p['flag_emoji'] ?? '') . ' ' . $p['country_name'])) ?> · <?= e($p['momo_provider_label'] ?: 'Mobile money') ?>
                    <?= $p['reference_number'] ? ' · ref ' . e($p['reference_number']) : '' ?> · <?= e($p['owner_name'] ?? '') ?> · sent <?= e(ops_ago($p['submitted_at'])) ?></div>
                <a class="small" href="<?= e(ops_url('payments/' . (int)$p['id'] . '/receipt')) ?>" target="_blank" rel="noopener">Open the receipt</a>
                · <a class="small" href="<?= e(ops_url('clients/' . (int)$p['business_id'])) ?>">The client</a>
            </div>
            <div class="ops-queue-actions">
                <form method="post" action="<?= e(ops_url('payments/' . (int)$p['id'] . '/approve')) ?>" data-ops-confirm="Approve <?= e($money($p)) ?> from <?= e($p['business_name']) ?>? <?= ($p['kind'] ?? 'month') === 'book' ? 'Their Records Book is made straight away.' : (($p['kind'] ?? 'month') === 'seats' ? 'The seats are added to the month they have paid for.' : 'A month starts when their current paid month ends; staff they did not tick are switched off.') ?>">
                    <?= ops_csrf_field() ?><button class="ops-btn ops-btn-primary ops-btn-sm" type="submit">Approve</button>
                </form>
                <details class="ops-more"><summary>Turn down</summary>
                    <form method="post" action="<?= e(ops_url('payments/' . (int)$p['id'] . '/reject')) ?>" style="margin-top:8px">
                        <?= ops_csrf_field() ?>
                        <div class="ops-field"><label for="why-<?= (int)$p['id'] ?>">Why (they see this)</label>
                            <input class="ops-input" id="why-<?= (int)$p['id'] ?>" name="reason" required minlength="5" placeholder="e.g. the amount on the receipt is GH₵100"></div>
                        <button class="ops-btn ops-btn-danger ops-btn-sm" type="submit">Turn it down</button>
                    </form>
                </details>
            </div>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($decided): ?>
<section class="ops-section">
    <h2>Decided lately</h2>
    <ul class="ops-feed">
        <?php foreach ($decided as $p): ?>
            <li>
                <span class="ops-dot ops-dot-<?= $p['status'] === 'approved' ? 'ok' : 'bad' ?>" aria-hidden="true"></span>
                <div class="what"><b><?= e($p['business_name']) ?></b> · <?= e($money($p)) ?> · <?= $p['status'] === 'approved' ? 'approved' : 'turned down: ' . e($p['rejection_reason'] ?? '') ?>
                    <div class="meta"><?= e($p['reviewer'] ?? 'Before Ops') ?></div></div>
                <time class="when"><?= e(ops_ago($p['reviewed_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
