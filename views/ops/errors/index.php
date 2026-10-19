<?php
/** Errors (list): $rows, $total, $page, $pages, $filter, $statuses, $kinds, $levels. */
require __DIR__ . '/_bits.php';
$url = fn(array $over) => ops_url('errors?' . http_build_query(array_filter($over + $filter, fn($v) => $v !== '' && $v !== null && $v !== 'active' && $v !== 'recent')));
$chips = [];
if ($filter['status'] !== 'active') $chips[] = [($filter['status'] === 'all' ? 'Any status' : ($statuses[$filter['status']] ?? $filter['status'])), $url(['status' => 'active', 'page' => null])];
if ($filter['kind'] !== '')  $chips[] = [$kinds[$filter['kind']] ?? $filter['kind'], $url(['kind' => '', 'page' => null])];
if ($filter['level'] !== '') $chips[] = [$levels[$filter['level']] ?? $filter['level'], $url(['level' => '', 'page' => null])];
?>
<div class="ops-head">
    <div>
        <h1>Errors</h1>
        <p>Everything that went wrong, grouped: server errors, crashes in someone's browser, background jobs, slow pages and the server log. A client's error code finds its problem here.</p>
    </div>
</div>

<form class="ops-toolbar" method="get" action="<?= e(ops_url('errors')) ?>">
    <div class="ops-search">
        <?= ops_icon('search') ?>
        <label class="sr-only" for="err-q">Search errors</label>
        <input class="ops-input" id="err-q" name="q" type="search" value="<?= e($filter['q']) ?>" placeholder="Search, or an error code like E7KQ2M9X" data-ops-slash>
    </div>
    <details class="ops-filters">
        <summary class="ops-btn ops-btn-secondary">Filters<?= $chips ? ' · ' . count($chips) : '' ?></summary>
        <div class="ops-filters-panel">
            <div class="ops-field">
                <label for="f-status">Status</label>
                <select class="ops-select" id="f-status" name="status">
                    <option value="active"<?= $filter['status'] === 'active' ? ' selected' : '' ?>>Open or being handled</option>
                    <?php foreach ($statuses as $k => $label): ?><option value="<?= e($k) ?>"<?= $filter['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                    <option value="all"<?= $filter['status'] === 'all' ? ' selected' : '' ?>>Any status</option>
                </select>
            </div>
            <div class="ops-field">
                <label for="f-kind">Where it happened</label>
                <select class="ops-select" id="f-kind" name="kind">
                    <option value="">Anywhere</option>
                    <?php foreach ($kinds as $k => $label): ?><option value="<?= e($k) ?>"<?= $filter['kind'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="ops-field">
                <label for="f-level">How bad</label>
                <select class="ops-select" id="f-level" name="level">
                    <option value="">Any</option>
                    <?php foreach ($levels as $k => $label): ?><option value="<?= e($k) ?>"<?= $filter['level'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="ops-btn ops-btn-primary ops-btn-block" type="submit">Show</button>
        </div>
    </details>
    <label class="sr-only" for="f-sort">Order</label>
    <select class="ops-select ops-select-auto" id="f-sort" name="sort" data-ops-autosubmit>
        <option value="recent"<?= $filter['sort'] === 'recent' ? ' selected' : '' ?>>Latest first</option>
        <option value="count"<?= $filter['sort'] === 'count' ? ' selected' : '' ?>>Most today</option>
        <option value="new"<?= $filter['sort'] === 'new' ? ' selected' : '' ?>>Newest problems</option>
    </select>
</form>
<?php if ($chips): ?>
    <div class="ops-chips">
        <?php foreach ($chips as [$label, $remove]): ?>
            <a class="ops-chip ops-chip-accent" href="<?= e($remove) ?>"><?= e($label) ?> <span aria-label="Remove this filter">×</span></a>
        <?php endforeach; ?>
        <a class="small" href="<?= e(ops_url('errors')) ?>">Clear all</a>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(ops_url('errors/bulk')) ?>" data-ops-bulk>
    <?= ops_csrf_field() ?>
    <div class="ops-bulk" data-ops-bulk-bar hidden>
        <span><b data-ops-bulk-count>0</b> ticked</span>
        <button class="ops-btn ops-btn-primary ops-btn-sm" type="submit" formaction="<?= e(ops_url('errors/export')) ?>" title="One Markdown file with every ticked error, to share with an AI"><?= ops_icon('download') ?> <span>Download for AI</span></button>
        <button class="ops-btn ops-btn-secondary ops-btn-sm" type="submit" name="status" value="fixed">Mark fixed</button>
        <button class="ops-btn ops-btn-secondary ops-btn-sm" type="submit" name="status" value="ignored">Ignore</button>
        <?php if ($filter['status'] === 'fixed' || $filter['status'] === 'ignored'): ?>
            <button class="ops-btn ops-btn-secondary ops-btn-sm" type="submit" name="status" value="open">Open again</button>
        <?php endif; ?>
    </div>
    <div class="ops-table-wrap">
        <table class="ops-table is-stacked is-compact">
            <thead>
                <tr>
                    <th scope="col" class="cell-check"><label class="sr-only" for="tick-all">Tick every error on this page</label><input type="checkbox" id="tick-all" data-ops-tick-all></th>
                    <th scope="col">Problem</th>
                    <th scope="col" class="num">Today</th>
                    <th scope="col" class="num">In all</th>
                    <th scope="col" class="num">Clients</th>
                    <th scope="col">Last seen</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $g): ?>
                <tr class="is-link">
                    <td class="cell-check"><label class="sr-only" for="tick-<?= (int)$g['id'] ?>">Tick this error</label><input type="checkbox" id="tick-<?= (int)$g['id'] ?>" name="ids[]" value="<?= (int)$g['id'] ?>" data-ops-tick></td>
                    <td class="cell-problem">
                        <a class="cell-main" href="<?= e(ops_url('errors/' . (int)$g['id'])) ?>"><?= e(mb_strimwidth($g['title'], 0, 140, '…')) ?></a>
                        <span class="cell-sub"><?= ops_error_level_chip($g['level']) ?> <?= ops_error_kind_chip($g['kind']) ?> <?= e(ops_error_where($g)) ?>
                            <?php if ($g['regressed_at'] && $g['status'] === 'open'): ?><span class="ops-chip ops-chip-bad">Came back</span><?php endif; ?></span>
                    </td>
                    <td class="num" data-label="Today"><?= (int)$g['day_events'] ?></td>
                    <td class="num" data-label="In all"><?= number_format((int)$g['events']) ?></td>
                    <td class="num" data-label="Clients"><?= (int)$g['clients'] ?: '<span class="muted">—</span>' ?></td>
                    <td data-label="Last seen"><time title="<?= e($g['last_seen_at']) ?>"><?= e(ops_ago($g['last_seen_at'])) ?></time></td>
                    <td data-label="Status"><?= ops_error_status_chip($g['status']) ?><?php if ($g['assignee']): ?><span class="cell-sub"><?= e($g['assignee']) ?></span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="7"><div class="ops-empty">
                    <?php if ($filter['q'] !== '' || $chips): ?>
                        <h3>Nothing matches</h3><p>Try other words, or <a href="<?= e(ops_url('errors')) ?>">clear the filters</a>.</p>
                    <?php else: ?>
                        <h3>No open errors</h3><p>When something breaks, it shows here within seconds, and Admin and Support are told.</p>
                    <?php endif; ?>
                </div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php require __DIR__ . '/../_pager.php'; ?>
    </div>
</form>
