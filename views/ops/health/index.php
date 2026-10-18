<?php
/** Health (a glance): $groups (group => checks), $state (ok|warn|bad), $beats. */
$word = ['ok' => 'Working', 'warn' => 'Worth a look', 'bad' => 'Needs attention'];
?>
<div class="ops-head">
    <div>
        <h1>Health</h1>
        <p>Everything Nfelia depends on behind the scenes. Checked every time this page opens; the cron also checks every 5 minutes and tells Admin and Support when something breaks.</p>
    </div>
</div>

<div class="ops-state ops-state-<?= e($state) ?>" role="status">
    <span class="ops-dot ops-dot-<?= e($state) ?>" aria-hidden="true"></span>
    <b><?= e(['ok' => 'Everything is working.', 'warn' => 'Working, with something worth a look.', 'bad' => 'Something needs attention.'][$state]) ?></b>
</div>

<?php foreach ($groups as $group => $checks): ?>
<section class="ops-section">
    <h2><?= e($group) ?></h2>
    <ul class="ops-checks">
        <?php foreach ($checks as $c): ?>
            <li class="is-<?= e($c['state']) ?>">
                <span class="ops-dot ops-dot-<?= e($c['state']) ?>" aria-hidden="true"></span>
                <div class="grow">
                    <b><?= e($c['label']) ?></b> <span class="ops-chip ops-chip-<?= $c['state'] === 'ok' ? 'ok' : ($c['state'] === 'bad' ? 'bad' : 'warn') ?>"><?= e($word[$c['state']]) ?></span>
                    <p><?= e($c['detail']) ?></p>
                </div>
                <?php if ($c['link']): ?><a class="ops-btn ops-btn-ghost ops-btn-sm" href="<?= e(ops_url($c['link'])) ?>">Open</a><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endforeach; ?>

<?php if ($beats): ?>
<section class="ops-section">
    <h2>Background runs</h2>
    <p>Each job's last run, as it reported it.</p>
    <div class="ops-table-wrap">
        <table class="ops-table is-stacked">
            <thead><tr><th scope="col">Job</th><th scope="col">Last finished</th><th scope="col">Took</th><th scope="col">How it went</th><th scope="col" class="num">Runs</th><th scope="col" class="num">Failed</th></tr></thead>
            <tbody>
            <?php foreach ($beats as $b): ?>
                <tr>
                    <td><span class="cell-main"><?= e(['planner' => 'The 5-minute cron', 'paystack_webhook' => 'Paystack webhooks'][$b['name']] ?? $b['name']) ?></span></td>
                    <td data-label="Last finished"><?= e(ops_ago($b['finished_at'])) ?></td>
                    <td data-label="Took" class="num"><?= $b['duration_ms'] !== null ? number_format($b['duration_ms'] / 1000, 1) . ' s' : '—' ?></td>
                    <td data-label="How it went"><?= (int)$b['ok'] ? '<span class="ops-chip ops-chip-ok">Fine</span>' : '<span class="ops-chip ops-chip-bad">Had errors</span>' ?> <span class="cell-sub ops-break"><?= e($b['note'] ?? '') ?></span></td>
                    <td data-label="Runs" class="num"><?= number_format((int)$b['runs']) ?></td>
                    <td data-label="Failed" class="num"><?= number_format((int)$b['failures']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
