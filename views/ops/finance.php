<?php
/**
 * Finance (a glance, then the expense list): $card (this_month, all_time), $momo (by currency), $mrr, $plans,
 * $signups, $months, $spend, $expenses, $model, $categories.
 */
$naira = fn($n) => '₦' . number_format((float)$n);
$max = max(1, ...array_column($months, 'total'));
?>
<div class="ops-head">
    <div>
        <h1>Finance</h1>
        <p>What clients pay Nfelia, and what Nfelia spends. Card payments in naira; mobile money in each country's own currency.</p>
    </div>
</div>
<div class="ops-kpis">
    <div class="ops-kpi"><div class="label">Monthly recurring (card)</div><div class="value"><?= e($naira($mrr)) ?></div><div class="note"><?= (int)$model['paying'] ?> paying in all</div></div>
    <div class="ops-kpi"><div class="label">Card, this month</div><div class="value"><?= e($naira($card['this_month'])) ?></div><div class="note"><?= e($naira($card['all_time'])) ?> in all</div></div>
    <div class="ops-kpi"><div class="label">Mobile money, this month</div><div class="value"><?= $momo ? e(implode(' + ', array_map(fn($m) => $m['currency_symbol'] . number_format((float)$m['total']), $momo))) : '—' ?></div></div>
    <div class="ops-kpi"><div class="label">On a free trial</div><div class="value"><?= (int)($plans['trial'] ?? 0) ?></div><div class="note"><?= (int)$signups ?> joined in 30 days</div></div>
    <div class="ops-kpi <?= $model['profit'] >= 0 ? 'is-ok' : 'is-bad' ?>"><div class="label">Recurring, less fixed costs</div><div class="value"><?= e(($model['profit'] < 0 ? '−' : '') . $naira(abs($model['profit']))) ?></div>
        <div class="note"><?= $model['breakeven'] ? 'Break-even at ' . (int)$model['breakeven'] . ' paying' : 'No fixed costs entered' ?></div></div>
</div>

<section class="ops-section">
    <h2>Card payments, six months</h2>
    <div class="ops-card"><div class="ops-card-body">
        <div class="ops-bars ops-bars-6" role="img" aria-label="<?= e(implode(', ', array_map(fn($m) => $m['label'] . ': ' . $naira($m['total']), $months))) ?>">
            <?php foreach ($months as $m): ?>
                <div class="ops-bar" title="<?= e($m['label'] . ': ' . $naira($m['total'])) ?>">
                    <span class="ops-bar-value num"><?= $m['total'] ? e($naira($m['total'])) : '' ?></span>
                    <span class="ops-bar-fill" style="height: <?= $m['total'] ? max(4, (int)round($m['total'] / $max * 100)) : 0 ?>%"></span>
                    <span class="ops-bar-label"><?= e($m['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div></div>
</section>

<section class="ops-section" id="expenses">
    <h2>Expenses</h2>
    <p>This month <?= e($naira($spend['this_month'])) ?>; fixed every month <?= e($naira($spend['monthly_recurring'])) ?>. The last three months are listed.</p>
    <div class="ops-detail">
        <div class="ops-table-wrap">
            <table class="ops-table is-stacked is-compact">
                <thead><tr><th scope="col">What</th><th scope="col">Date</th><th scope="col" class="num">Amount</th><th scope="col" class="cell-actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($expenses as $x): ?>
                    <tr>
                        <td><span class="cell-main"><?= e($categories[$x['category']] ?? $x['category']) ?><?= (int)$x['recurring'] ? ' <span class="ops-chip">Every month</span>' : '' ?></span><span class="cell-sub"><?= e($x['description'] ?? '') ?></span></td>
                        <td data-label="Date"><?= e(date('j M Y', strtotime($x['expense_date']))) ?></td>
                        <td data-label="Amount" class="num"><?= e('₦' . number_format($x['amount'] / 100, 2)) ?></td>
                        <td class="cell-actions"><form method="post" action="<?= e(ops_url('finance/expenses/' . (int)$x['id'] . '/delete')) ?>" data-ops-confirm="Remove this expense?"><?= ops_csrf_field() ?>
                            <button class="ops-btn ops-btn-ghost ops-btn-sm" type="submit">Remove</button></form></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$expenses): ?><tr><td colspan="4"><div class="ops-empty"><p>No expenses in the last three months.</p></div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ops-card"><div class="ops-card-body">
            <h3>Add an expense</h3>
            <form method="post" action="<?= e(ops_url('finance/expenses')) ?>" style="margin-top:12px">
                <?= ops_csrf_field() ?>
                <div class="ops-field"><label for="x-cat">Kind</label><select class="ops-select" id="x-cat" name="category"><?php foreach ($categories as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="ops-field"><label for="x-amount">Amount (₦)</label><input class="ops-input" id="x-amount" name="amount" inputmode="decimal" required></div>
                <div class="ops-field"><label for="x-date">Date</label><input class="ops-input" id="x-date" name="expense_date" type="date" value="<?= e(date('Y-m-d')) ?>"></div>
                <div class="ops-field"><label for="x-desc">What for <span class="muted">(optional)</span></label><input class="ops-input" id="x-desc" name="description" maxlength="500"></div>
                <label class="ops-check" style="margin-bottom:14px"><input type="checkbox" name="recurring" value="1"><span>Every month</span></label>
                <button class="ops-btn ops-btn-primary" type="submit">Add it</button>
            </form>
        </div></div>
    </div>
</section>
