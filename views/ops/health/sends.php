<?php
/** Emails and phone notifications that failed or are being retried (list): $rows, $total, $page, $pages, $filter. */
$url = fn(array $over) => ops_url('health/sends?' . http_build_query(array_filter($over + $filter, fn($v) => $v !== '' && $v !== null && $v !== 'problems')));
$noun = ['send', 'sends'];
?>
<p class="ops-back"><a href="<?= e(ops_url('health')) ?>">← Health</a></p>
<div class="ops-head">
    <div>
        <h1>Failed sends</h1>
        <p>Emails and phone notifications from the last 30 days that did not go through, or are being tried again. Each is retried by itself (up to 6 times for email, 4 for phones); "Try again" sends a failed one now.</p>
    </div>
</div>

<form class="ops-toolbar" method="get" action="<?= e(ops_url('health/sends')) ?>">
    <details class="ops-filters">
        <summary class="ops-btn ops-btn-secondary">Filters<?= $filter['channel'] || $filter['status'] !== 'problems' ? ' · on' : '' ?></summary>
        <div class="ops-filters-panel">
            <div class="ops-field">
                <label for="f-channel">Kind</label>
                <select class="ops-select" id="f-channel" name="channel">
                    <option value="">Emails and phone notifications</option>
                    <option value="email"<?= $filter['channel'] === 'email' ? ' selected' : '' ?>>Emails</option>
                    <option value="push"<?= $filter['channel'] === 'push' ? ' selected' : '' ?>>Phone notifications</option>
                </select>
            </div>
            <div class="ops-field">
                <label for="f-status">State</label>
                <select class="ops-select" id="f-status" name="status">
                    <option value="problems">Failed or being retried</option>
                    <option value="failed"<?= $filter['status'] === 'failed' ? ' selected' : '' ?>>Failed (gave up)</option>
                    <option value="retrying"<?= $filter['status'] === 'retrying' ? ' selected' : '' ?>>Being retried</option>
                    <option value="skipped"<?= $filter['status'] === 'skipped' ? ' selected' : '' ?>>Not sent on purpose (quiet hours, no phone)</option>
                </select>
            </div>
            <button class="ops-btn ops-btn-primary ops-btn-block" type="submit">Show</button>
        </div>
    </details>
</form>

<div class="ops-table-wrap">
    <table class="ops-table is-stacked">
        <thead><tr><th scope="col">What</th><th scope="col">To</th><th scope="col">Client</th><th scope="col" class="num">Tries</th><th scope="col">Why</th><th scope="col">State</th><th scope="col" class="cell-actions"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><span class="cell-main"><?= $r['channel'] === 'email' ? 'Email' : 'Phone' ?>: <?= e($r['event']) ?></span><span class="cell-sub"><?= e(ops_ago($r['created_at'])) ?></span></td>
                <td data-label="To" class="ops-break"><?= e($r['staff_name'] ? $r['staff_name'] . ' (Ops)' : ($r['user_name'] ?: ($r['recipient'] ?: '—'))) ?></td>
                <td data-label="Client"><?= e($r['business_name'] ?? '—') ?></td>
                <td data-label="Tries" class="num"><?= (int)$r['attempts'] ?></td>
                <td data-label="Why" class="ops-break"><?= e($r['last_error'] ?? '') ?></td>
                <td data-label="State"><?= match ($r['status']) {
                    'failed'  => '<span class="ops-chip ops-chip-bad">Gave up</span>',
                    'skipped' => '<span class="ops-chip">Not sent</span>',
                    default   => '<span class="ops-chip ops-chip-warn">Next try ' . e(ops_ago($r['next_attempt_at'])) . '</span>',
                } ?></td>
                <td class="cell-actions">
                    <?php if (in_array($r['status'], ['failed', 'skipped'], true)): ?>
                        <form method="post" action="<?= e(ops_url('health/sends/' . (int)$r['id'] . '/retry')) ?>"><?= ops_csrf_field() ?>
                            <button class="ops-btn ops-btn-secondary ops-btn-sm" type="submit">Try again</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="7"><div class="ops-empty"><h3>Nothing failed</h3><p>Every email and phone notification in the last 30 days went through.</p></div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php require __DIR__ . '/../_pager.php'; ?>
</div>
