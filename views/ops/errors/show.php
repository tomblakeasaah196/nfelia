<?php
/**
 * One problem (detail): $g, $events, $clients, $days, $day_events, $notes,
 * $history, $team, $focus (an error code to show open), $explain, $copy.
 */
require __DIR__ . '/_bits.php';
$max = max(1, ...array_values($days));
?>
<p class="ops-back"><a href="<?= e(ops_url('errors')) ?>">← Errors</a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <div class="ops-chips-row"><?= ops_error_level_chip($g['level']) ?> <?= ops_error_kind_chip($g['kind']) ?> <?= ops_error_status_chip($g['status']) ?>
            <?php if ($g['regressed_at']): ?><span class="ops-chip ops-chip-bad">Came back <?= e(ops_ago($g['regressed_at'])) ?></span><?php endif; ?></div>
        <h1 class="ops-break"><?= e($g['title']) ?></h1>
        <p><?= number_format((int)$g['events']) ?> time<?= (int)$g['events'] === 1 ? '' : 's' ?>, <?= $day_events ?> in the last day<?= $clients ? ', for ' . count($clients) . ' client' . (count($clients) === 1 ? '' : 's') : '' ?>.
           First <?= e(ops_ago($g['first_seen_at'])) ?>, last <?= e(ops_ago($g['last_seen_at'])) ?>.</p>
    </div>
    <div class="ops-head-actions">
        <button type="button" class="ops-btn ops-btn-secondary" data-ops-copy="#copy-text"><?= ops_icon('copy') ?> <span>Copy for AI</span></button>
    </div>
</div>
<textarea id="copy-text" class="sr-only" readonly tabindex="-1" aria-hidden="true"><?= e($copy) ?></textarea>

<div class="ops-detail">
    <div class="ops-detail-main">
        <?php if ($explain): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>What it means</h2>
            <p class="ops-lead"><?= e($explain) ?></p>
            <?php if ($g['message'] && $g['message'] !== $g['title']): ?>
                <details class="ops-more"><summary>The full message</summary><pre class="ops-pre"><?= e($g['message']) ?></pre></details>
            <?php endif; ?>
        </div></section>
        <?php endif; ?>

        <section class="ops-card"><div class="ops-card-body">
            <h2>The last 14 days</h2>
            <div class="ops-bars" role="img" aria-label="<?= e(implode(', ', array_map(fn($d, $n) => date('j M', strtotime($d)) . ': ' . $n, array_keys($days), $days))) ?>">
                <?php foreach ($days as $d => $n): ?>
                    <div class="ops-bar" title="<?= e(date('D j M', strtotime($d)) . ': ' . $n) ?>">
                        <span class="ops-bar-fill" style="height: <?= $n ? max(4, (int)round($n / $max * 100)) : 0 ?>%"></span>
                        <span class="ops-bar-label"><?= e(date('j', strtotime($d))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="small muted">Most in a day: <?= (int)$max ?>. Lagos time.</p>
        </div></section>

        <section class="ops-card" id="events"><div class="ops-card-body">
            <h2>Each time</h2>
            <p class="muted">The latest <?= count($events) ?>, newest first. The code is what the person saw on the error page.</p>
            <ul class="ops-events">
                <?php foreach ($events as $ev): $open = $focus === $ev['ref']; ?>
                <li id="<?= e($ev['ref']) ?>"<?= $open ? ' class="is-focus"' : '' ?>>
                    <details<?= $open ? ' open' : '' ?>>
                        <summary>
                            <span class="ev-ref"><?= e($ev['ref']) ?></span>
                            <span class="ev-what">
                                <?= e($ev['business_name'] ?? 'No client') ?><?= $ev['user_name'] ? ' · ' . e($ev['user_name']) : '' ?>
                                <span class="cell-sub"><?= e(trim(($ev['method'] && $ev['method'] !== 'CLI' ? $ev['method'] . ' ' : '') . ($ev['path'] ?? ''))) ?> · <?= e($ev['source']) ?><?= $ev['duration_ms'] ? ' · ' . number_format($ev['duration_ms'] / 1000, 1) . ' s' : '' ?></span>
                            </span>
                            <time class="ev-when" title="<?= e($ev['created_at']) ?>"><?= e(ops_ago($ev['created_at'])) ?></time>
                        </summary>
                        <div class="ev-body">
                            <?php if ($ev['message'] && $ev['message'] !== $g['title']): ?><pre class="ops-pre"><?= e($ev['message']) ?></pre><?php endif; ?>
                            <?php if ($ev['trace']): ?><pre class="ops-pre"><?= e($ev['trace']) ?></pre><?php endif; ?>
                            <p class="small muted">
                                <?= e($ev['created_at']) ?> Lagos · release <?= e($ev['release_id'] ?: '?') ?><?= $ev['memory_mb'] ? ' · ' . (int)$ev['memory_mb'] . ' MB' : '' ?>
                                <?php if ($ev['user_agent']): ?><br><?= e($ev['user_agent']) ?><?php endif; ?>
                            </p>
                        </div>
                    </details>
                </li>
                <?php endforeach; ?>
            </ul>
        </div></section>

        <section class="ops-card" id="notes"><div class="ops-card-body">
            <h2>Notes</h2>
            <?php if ($notes): ?>
                <ul class="ops-notes">
                    <?php foreach ($notes as $n): ?>
                        <li><b><?= e($n['full_name'] ?? 'Nfelia') ?></b> <span class="muted small"><?= e(ops_ago($n['created_at'])) ?></span><p><?= nl2br(e($n['body'])) ?></p></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">What you found, what you tried, what fixed it: for whoever meets this next.</p>
            <?php endif; ?>
            <form method="post" action="<?= e(ops_url('errors/' . (int)$g['id'] . '/notes')) ?>">
                <?= ops_csrf_field() ?>
                <div class="ops-field">
                    <label for="note-body">Add a note</label>
                    <textarea class="ops-textarea" id="note-body" name="body" required maxlength="4000"></textarea>
                </div>
                <button class="ops-btn ops-btn-secondary" type="submit">Save the note</button>
            </form>
        </div></section>
    </div>

    <aside class="ops-detail-side">
        <section class="ops-card"><div class="ops-card-body">
            <h2>Status</h2>
            <div class="ops-stack">
                <?php if (in_array($g['status'], ['open'], true)): ?>
                    <form method="post" action="<?= e(ops_url('errors/' . (int)$g['id'] . '/status')) ?>"><?= ops_csrf_field() ?><input type="hidden" name="status" value="handling">
                        <button class="ops-btn ops-btn-primary ops-btn-block" type="submit">I'm on it</button></form>
                <?php endif; ?>
                <?php if (in_array($g['status'], ['open', 'handling'], true)): ?>
                    <form method="post" action="<?= e(ops_url('errors/' . (int)$g['id'] . '/status')) ?>"><?= ops_csrf_field() ?><input type="hidden" name="status" value="fixed">
                        <button class="ops-btn <?= $g['status'] === 'handling' ? 'ops-btn-primary' : 'ops-btn-secondary' ?> ops-btn-block" type="submit">Mark fixed</button></form>
                    <form method="post" action="<?= e(ops_url('errors/' . (int)$g['id'] . '/status')) ?>" data-ops-confirm="Ignore this problem? It stays counted, but nobody is told about it again."><?= ops_csrf_field() ?><input type="hidden" name="status" value="ignored">
                        <button class="ops-btn ops-btn-ghost ops-btn-block" type="submit">Ignore</button></form>
                <?php else: ?>
                    <form method="post" action="<?= e(ops_url('errors/' . (int)$g['id'] . '/status')) ?>"><?= ops_csrf_field() ?><input type="hidden" name="status" value="open">
                        <button class="ops-btn ops-btn-secondary ops-btn-block" type="submit">Open again</button></form>
                <?php endif; ?>
            </div>
            <?php if ($g['status_changed_at']): ?><p class="small muted" style="margin-top:10px"><?= e(['open' => 'Opened', 'handling' => 'Taken on', 'fixed' => 'Marked fixed', 'ignored' => 'Ignored'][$g['status']]) ?> <?= e(ops_ago($g['status_changed_at'])) ?>.</p><?php endif; ?>
            <form method="post" action="<?= e(ops_url('errors/' . (int)$g['id'] . '/assign')) ?>" class="ops-field" style="margin:16px 0 0">
                <?= ops_csrf_field() ?>
                <label for="assign">Who has it</label>
                <select class="ops-select" id="assign" name="staff_id" data-ops-autosubmit>
                    <option value="0">Nobody</option>
                    <?php foreach ($team as $t): ?><option value="<?= (int)$t['id'] ?>"<?= (int)$g['assigned_to'] === (int)$t['id'] ? ' selected' : '' ?>><?= e($t['full_name']) ?></option><?php endforeach; ?>
                </select>
            </form>
        </div></section>

        <section class="ops-card"><div class="ops-card-body">
            <h2>Where</h2>
            <dl class="ops-facts">
                <?php if ($g['file']): ?><dt>File</dt><dd class="ops-break"><?= e($g['file'] . ($g['line'] ? ':' . $g['line'] : '')) ?></dd><?php endif; ?>
                <?php if ($g['route']): ?><dt>Page</dt><dd class="ops-break"><?= e($g['route']) ?></dd><?php endif; ?>
                <dt>First seen</dt><dd><?= e(date('j M Y, H:i', strtotime($g['first_seen_at']))) ?><?= $g['first_release'] ? ' · ' . e($g['first_release']) : '' ?></dd>
                <dt>Last seen</dt><dd><?= e(date('j M Y, H:i', strtotime($g['last_seen_at']))) ?><?= $g['last_release'] ? ' · ' . e($g['last_release']) : '' ?></dd>
                <?php if ((int)$g['regressions']): ?><dt>Came back</dt><dd><?= (int)$g['regressions'] ?> time<?= (int)$g['regressions'] === 1 ? '' : 's' ?></dd><?php endif; ?>
            </dl>
        </div></section>

        <section class="ops-card"><div class="ops-card-body">
            <h2>Clients</h2>
            <?php if ($clients): ?>
                <ul class="ops-plain">
                    <?php foreach ($clients as $c): ?>
                        <li><span><?= e($c['name']) ?></span> <span class="muted num"><?= (int)$c['n'] ?>×, <?= e(ops_ago($c['last_at'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">Not tied to a client (a background job, a shopper, or someone signed out).</p>
            <?php endif; ?>
        </div></section>

        <?php if ($history): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>History</h2>
            <ul class="ops-plain">
                <?php foreach ($history as $h): ?><li><span><?= e(($h['full_name'] ?? 'Nfelia') . ': ' . preg_replace('/:\s*".*$/', '', $h['summary'])) ?></span> <span class="muted"><?= e(ops_ago($h['created_at'])) ?></span></li><?php endforeach; ?>
            </ul>
        </div></section>
        <?php endif; ?>
    </aside>
</div>
