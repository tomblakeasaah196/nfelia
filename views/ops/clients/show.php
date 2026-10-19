<?php
/**
 * One client (detail): $b, $people, $use, $payments, $plan_log, $reviews, $referred_by, $referred,
 * $errors (null: role can't), $tickets (null: role can't), $notes, $views, $history (null: role can't), $plans,
 * $books (their Records Books; null: role can't), $asks (requests for their records; null: role can't).
 */
$act    = ops_can('clients.act');
$owner  = array_values(array_filter($people, fn($p) => $p['role'] === 'owner'))[0] ?? ($people[0] ?? null);
$active = array_reduce($people, fn($max, $p) => $p['last_login_at'] && $p['last_login_at'] > (string)$max ? $p['last_login_at'] : $max, null);
$host   = $b['subdomain'] ? $b['subdomain'] . '.' . shop_base_host() : null;
$day    = fn(?string $d) => $d ? date('j M Y', strtotime($d)) : '—';
?>
<p class="ops-back"><a href="<?= e(ops_url('clients')) ?>">← Clients</a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <div class="ops-chips-row"><?= ops_plan_chip($b) ?>
            <?php if ($b['country']): ?><span class="ops-chip"><?= e(trim(($b['flag_emoji'] ?? '') . ' ' . $b['country'])) ?></span><?php endif; ?>
            <?php if (!(int)$b['is_active']): ?><span class="ops-chip ops-chip-bad">Closed</span><?php endif; ?>
            <span class="ops-chip ops-chip-muted">#<?= (int)$b['id'] ?></span></div>
        <h1 class="ops-break"><?= e($b['name']) ?></h1>
        <p><?= $owner ? e($owner['full_name']) . ' · ' : '' ?><?= e($b['email'] ?? '') ?><?= $b['phone'] ? ' · ' . e($b['phone']) : '' ?>.
           Joined <?= e($day($b['created_at'])) ?>, last active <?= $active ? e(ops_ago($active)) : 'never' ?>.</p>
    </div>
    <div class="ops-head-actions">
        <?php if ($b['phone']): ?><a class="ops-btn ops-btn-secondary" href="https://wa.me/<?= e(preg_replace('/\D/', '', $b['whatsapp'] ?? $b['phone'])) ?>" rel="noopener noreferrer" target="_blank">WhatsApp</a><?php endif; ?>
        <?php if (ops_can('clients.view_as')): ?><button type="button" class="ops-btn ops-btn-primary" data-ops-dialog="view-as"><?= ops_icon('eye') ?> View as <?= e($owner['full_name'] ?? 'them') ?></button><?php endif; ?>
    </div>
</div>

<div class="ops-detail">
    <div class="ops-detail-main">
        <section class="ops-card" id="plan"><div class="ops-card-body">
            <h2>Plan and payments</h2>
            <dl class="ops-facts">
                <dt>Plan</dt><dd><?= e($plans[ops_plan_state($b)] ?? 'No plan') ?><?= $b['payment_method'] ? ' · ' . e($b['payment_method'] === 'paystack' ? 'card (Paystack)' : str_replace('_', ' ', $b['payment_method'])) : '' ?></dd>
                <?php if ($b['plan_status'] === 'trial'): ?><dt>Trial ends</dt><dd><?= e($day($b['trial_ends_at'])) ?></dd><?php endif; ?>
                <?php if ($b['current_period_end']): ?><dt>Paid to</dt><dd><?= e($day($b['current_period_end'])) ?><?= ops_plan_state($b) === 'due' ? ', ' . BILLING_GRACE_DAYS . ' extra days to ' . e($day($b['plan_status'] === 'past_due' && $b['grace_ends_at'] ? $b['grace_ends_at'] : billing_add_days((string)$b['current_period_end'], BILLING_GRACE_DAYS))) : '' ?><?= (int)($b['expiry_exempt'] ?? 0) ? ' (left as it was when paid months started to expire)' : '' ?></dd><?php endif; ?>
                <?php if ($b['monthly_amount']): ?><dt>Monthly</dt><dd class="num"><?= e(($b['country_currency'] ?: '₦') . number_format((float)$b['monthly_amount'])) ?><?= (int)$b['extra_seats'] ? ' + ' . (int)$b['extra_seats'] . ' extra seat' . ((int)$b['extra_seats'] === 1 ? '' : 's') : '' ?></dd><?php endif; ?>
                <?php if ($b['cancelled_at']): ?><dt>Cancelled</dt><dd><?= e($day($b['cancelled_at'])) ?><?= $b['cancel_reason'] ? ': ' . e($b['cancel_reason']) : '' ?></dd><?php endif; ?>
            </dl>
            <?php if ($payments): ?>
                <h3 style="margin-top:18px">Payments</h3>
                <ul class="ops-plain">
                    <?php foreach ($payments as $p): ?>
                        <li><span><?= e($p['how'] === 'card' ? str_replace(['charge.success', 'invoice.payment_failed', 'subscription.create', 'subscription.disable', 'subscription.not_renew'], ['Card payment', 'Card payment failed', 'Card subscription started', 'Card subscription stopped', 'Will not renew'], $p['what']) : $p['what']) ?>
                            <?php if ((float)$p['amount'] > 0): ?><b class="num"> <?= e(number_format((float)$p['amount'])) ?></b><?php endif; ?>
                            <span class="muted">· <?= e(str_replace('_', ' ', (string)$p['status'])) ?></span></span>
                            <span class="muted"><?= e($day($p['created_at'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted" style="margin-top:12px">No payments yet.</p>
            <?php endif; ?>
            <?php if ($plan_log): ?>
                <details class="ops-more"><summary>Plan changes</summary>
                    <ul class="ops-plain"><?php foreach ($plan_log as $l): ?><li><span><?= e(($l['old_status'] ?: '—') . ' → ' . $l['new_status']) ?><?= $l['notes'] ? ': ' . e($l['notes']) : '' ?></span> <span class="muted"><?= e($day($l['changed_at'])) ?></span></li><?php endforeach; ?></ul>
                </details>
            <?php endif; ?>
        </div></section>

        <section class="ops-card" id="people"><div class="ops-card-body">
            <h2>People</h2>
            <ul class="ops-plain">
                <?php foreach ($people as $p): ?>
                    <li><span><b><?= e($p['full_name']) ?></b> <span class="muted">· <?= e(ucfirst($p['role'])) ?><?= (int)$p['is_active'] ? '' : ', left' ?> · <?= e($p['email']) ?></span></span>
                        <span class="muted"><?= $p['last_login_at'] ? e(ops_ago($p['last_login_at'])) : 'never signed in' ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div></section>

        <section class="ops-card" id="use"><div class="ops-card-body">
            <h2>Using Nfelia</h2>
            <p class="muted">The last 30 days, and in all.</p>
            <div class="ops-kpis ops-kpis-sm">
                <div class="ops-kpi"><div class="label">Invoices</div><div class="value"><?= (int)$use['invoices_30'] ?></div><div class="note"><?= (int)$use['invoices'] ?> in all</div></div>
                <div class="ops-kpi"><div class="label">Receipts</div><div class="value"><?= (int)$use['receipts_30'] ?></div></div>
                <div class="ops-kpi"><div class="label">Quotes</div><div class="value"><?= (int)$use['quotes_30'] ?></div></div>
                <div class="ops-kpi"><div class="label">Shop orders</div><div class="value"><?= (int)$use['orders_30'] ?></div><div class="note"><?= (int)$use['orders'] ?> in all</div></div>
                <div class="ops-kpi"><div class="label">Customers</div><div class="value"><?= (int)$use['customers'] ?></div><div class="note"><?= (int)$use['products'] ?> products</div></div>
            </div>
        </div></section>

        <?php if ($errors !== null): ?>
        <section class="ops-card" id="errors"><div class="ops-card-body">
            <h2>Errors they hit</h2>
            <?php if ($errors): ?>
                <ul class="ops-plain">
                    <?php foreach ($errors as $g): ?>
                        <li><a class="ops-break" href="<?= e(ops_url('errors/' . (int)$g['id'])) ?>"><?= e(mb_strimwidth($g['title'], 0, 110, '…')) ?></a> <span class="muted num"><?= (int)$g['n'] ?>×, <?= e(ops_ago($g['last_at'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?><p class="muted">None in the last 30 days.</p><?php endif; ?>
        </div></section>
        <?php endif; ?>

        <?php if ($tickets !== null): ?>
        <section class="ops-card" id="tickets"><div class="ops-card-body">
            <h2>Support tickets</h2>
            <?php if ($tickets): ?>
                <ul class="ops-plain">
                    <?php foreach ($tickets as $t): ?>
                        <li><a href="<?= e(ops_url('support/' . (int)$t['id'])) ?>"><?= e($t['subject']) ?></a> <span class="muted"><?= e($t['status']) ?> · <?= e($day($t['created_at'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?><p class="muted">None.</p><?php endif; ?>
        </div></section>
        <?php endif; ?>

        <section class="ops-card" id="notes"><div class="ops-card-body">
            <h2>Notes</h2>
            <?php if ($notes): ?>
                <ul class="ops-notes"><?php foreach ($notes as $n): ?><li><b><?= e($n['full_name'] ?? 'Nfelia') ?></b> <span class="muted small"><?= e(ops_ago($n['created_at'])) ?></span><p><?= nl2br(e($n['body'])) ?></p></li><?php endforeach; ?></ul>
            <?php else: ?><p class="muted">Calls, promises, what they asked for: for whoever talks to them next.</p><?php endif; ?>
            <form method="post" action="<?= e(ops_url('clients/' . (int)$b['id'] . '/notes')) ?>">
                <?= ops_csrf_field() ?>
                <div class="ops-field"><label for="note">Add a note</label><textarea class="ops-textarea" id="note" name="body" required maxlength="4000"></textarea></div>
                <button class="ops-btn ops-btn-secondary" type="submit">Save the note</button>
            </form>
        </div></section>
    </div>

    <aside class="ops-detail-side">
        <?php if ($act): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>Change the plan</h2>
            <div class="ops-stack">
                <form method="post" action="<?= e(ops_url('clients/' . (int)$b['id'] . '/plan')) ?>" data-ops-confirm="Give <?= e($b['name']) ?> a month? It starts when their paid month ends (today if it has), like a payment. They are told.">
                    <?= ops_csrf_field() ?><input type="hidden" name="action" value="activate">
                    <button class="ops-btn ops-btn-secondary ops-btn-block" type="submit">Activate for a month</button>
                </form>
                <form method="post" action="<?= e(ops_url('clients/' . (int)$b['id'] . '/plan')) ?>" class="ops-inline-form">
                    <?= ops_csrf_field() ?><input type="hidden" name="action" value="extend_trial">
                    <label class="sr-only" for="trial-days">Days</label>
                    <select class="ops-select" id="trial-days" name="days"><?php foreach ([3, 7, 14, 30] as $d): ?><option value="<?= $d ?>"<?= $d === 7 ? ' selected' : '' ?>><?= $d ?> days</option><?php endforeach; ?></select>
                    <button class="ops-btn ops-btn-secondary" type="submit">Extend the trial</button>
                </form>
                <?php if ($b['plan_status'] !== 'suspended'): ?>
                <details class="ops-more"><summary>Suspend</summary>
                    <form method="post" action="<?= e(ops_url('clients/' . (int)$b['id'] . '/plan')) ?>" style="margin-top:10px">
                        <?= ops_csrf_field() ?><input type="hidden" name="action" value="suspend">
                        <div class="ops-field"><label for="suspend-why">Why</label><input class="ops-input" id="suspend-why" name="reason" required minlength="5" placeholder="e.g. fraud report, asked us to"></div>
                        <button class="ops-btn ops-btn-danger ops-btn-block" type="submit">Suspend <?= e($b['name']) ?></button>
                    </form>
                </details>
                <?php endif; ?>
            </div>
            <p class="small muted" style="margin-top:10px">The owner is told each time, and it goes in the audit log.</p>
        </div></section>
        <?php endif; ?>

        <section class="ops-card" id="shop"><div class="ops-card-body">
            <h2>Shop</h2>
            <dl class="ops-facts">
                <dt>Link</dt><dd class="ops-break"><?php if ($host && $b['subdomain_status'] === 'active'): ?><a href="<?= e(shop_url($b['subdomain'])) ?>" target="_blank" rel="noopener noreferrer"><?= e($host) ?></a><?php else: ?><?= e($host ?? 'None yet') ?><?php endif; ?></dd>
                <?php if ($b['subdomain_requested']): ?><dt>Asked for</dt><dd class="ops-break"><?= e($b['subdomain_requested'] . '.' . shop_base_host()) ?> (<?= e($b['subdomain_status']) ?>)</dd><?php endif; ?>
                <dt>Reviews</dt><dd><?= (int)$reviews['n'] ? (int)$reviews['n'] . ', ' . e((string)$reviews['avg']) . ' stars on average' : 'None yet' ?></dd>
                <dt>Footer</dt><dd><?= (int)$b['hide_nfelia_credit'] ? 'No "Shop by Nfelia" (paid add-on)' : 'Shows "Shop by Nfelia"' ?></dd>
            </dl>
            <?php if ($act): ?>
            <form method="post" action="<?= e(ops_url('clients/' . (int)$b['id'] . '/credit')) ?>" style="margin-top:12px">
                <?= ops_csrf_field() ?><input type="hidden" name="hide" value="<?= (int)$b['hide_nfelia_credit'] ? '0' : '1' ?>">
                <button class="ops-btn ops-btn-secondary ops-btn-sm" type="submit"><?= (int)$b['hide_nfelia_credit'] ? 'Show "Shop by Nfelia" again' : 'Take "Shop by Nfelia" off' ?></button>
            </form>
            <?php endif; ?>
        </div></section>

        <?php if ($books !== null): ?>
        <section class="ops-card" id="records"><div class="ops-card-body">
            <h2>Records Books</h2>
            <?php if ($books): ?>
            <ul class="ops-plain"><?php foreach ($books as $bk): ?>
                <li><a href="<?= e(ops_url('records/' . (int)$bk['id'])) ?>"><?= e($day($bk['created_at'])) ?></a>
                    <span class="muted"><?= e(['ready' => ['free' => 'free', 'paid' => 'bought', 'team' => 'by the team'][$bk['charge']] ?? 'ready', 'queued' => 'being made', 'building' => 'being made',
                                              'awaiting_payment' => 'waiting for payment', 'failed' => 'failed', 'expired' => 'no longer kept'][$bk['status']] ?? $bk['status']) ?></span></li>
            <?php endforeach; ?></ul>
            <?php else: ?><p class="muted">None yet.</p><?php endif; ?>
            <a class="ops-btn ops-btn-secondary ops-btn-sm" style="margin-top:10px" href="<?= e(ops_url('records/new?client=' . (int)$b['id'])) ?>"><?= ops_icon('download') ?> Make a Records Book</a>
            <?php if ($asks): ?>
                <h3 style="margin-top:14px">Asked for a record</h3>
                <ul class="ops-plain"><?php foreach ($asks as $rq): ?>
                    <li><a class="ops-break" href="<?= e(ops_url('requests/' . (int)$rq['id'])) ?>"><?= e($rq['organisation'] ?: $rq['name']) ?></a>
                        <span class="muted"><?= e(['new' => 'to look at', 'asked' => 'with the owner', 'approved' => 'shared', 'declined' => 'declined', 'expired' => 'no answer'][$rq['status']] ?? $rq['status']) ?></span></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div></section>
        <?php endif; ?>

        <section class="ops-card"><div class="ops-card-body">
            <h2>Referrals</h2>
            <p><?= $referred_by ? 'Brought by ' . e($referred_by['referrer'] ?? 'another business') . ' (' . e($referred_by['status']) . ').' : 'Came on their own.' ?></p>
            <?php if ($referred): ?><ul class="ops-plain" style="margin-top:8px"><?php foreach ($referred as $r): ?><li><span>Brought <?= e($r['name'] ?? 'someone') ?></span> <span class="muted"><?= e($r['status']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
        </div></section>

        <?php if ($views): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>Viewed as</h2>
            <ul class="ops-plain"><?php foreach ($views as $v): ?><li><span><?= e(($v['full_name'] ?? 'Someone') . ': ' . $v['reason']) ?></span> <span class="muted"><?= e(ops_ago($v['started_at'])) ?></span></li><?php endforeach; ?></ul>
        </div></section>
        <?php endif; ?>

        <?php if ($history): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>Done in Ops</h2>
            <ul class="ops-plain"><?php foreach ($history as $h): ?><li><span><?= e(($h['full_name'] ?? 'Nfelia') . ': ' . $h['summary']) ?></span> <span class="muted"><?= e(ops_ago($h['created_at'])) ?></span></li><?php endforeach; ?></ul>
            <p class="small" style="margin-top:8px"><a href="<?= e(ops_url('audit?business=' . (int)$b['id'])) ?>">All of it</a></p>
        </div></section>
        <?php endif; ?>
    </aside>
</div>

<?php if (ops_can('clients.view_as')): ?>
<dialog class="ops-dialog" id="view-as" aria-labelledby="view-as-title">
    <form method="post" action="<?= e(ops_url('clients/' . (int)$b['id'] . '/view-as')) ?>" target="_blank">
        <?= ops_csrf_field() ?>
        <div class="ops-dialog-head">
            <h2 id="view-as-title">View as <?= e($owner['full_name'] ?? 'the owner') ?></h2>
            <button type="button" data-ops-close aria-label="Close"><?= ops_icon('close') ?></button>
        </div>
        <div class="ops-dialog-body">
            <p style="margin-bottom:14px">See <?= e($b['name']) ?>'s Nfelia exactly as they do, in a new tab. <b>Read-only</b>: nothing can be saved, sent or deleted, and it ends after 30 minutes.
               <?= e($owner['full_name'] ?? 'The owner') ?> is told it was you, and why.</p>
            <div class="ops-field">
                <label for="view-why">Why you need to see it</label>
                <input class="ops-input" id="view-why" name="reason" required minlength="5" maxlength="255" placeholder="e.g. ticket #12: invoice totals look wrong">
                <span class="hint">They see this, and it stays in the audit log.</span>
            </div>
        </div>
        <div class="ops-dialog-foot">
            <button type="button" class="ops-btn ops-btn-secondary" data-ops-close>Cancel</button>
            <button type="submit" class="ops-btn ops-btn-primary">Open their Nfelia</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
