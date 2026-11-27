<?php
/**
 * Leads: a list or a board of the same leads (one page, a toggle).
 * $mode, $rows (list: rows; board: stage => rows), $total, $page, $pages, $counts, $due_count, $filter, $sales.
 */
$keep = array_filter($filter + ['view' => $mode === 'board' ? 'board' : ''], fn($v) => $v !== '' && $v !== null);
$url  = fn(array $over) => ops_url('leads?' . http_build_query(array_filter($over + $keep, fn($v) => $v !== '' && $v !== null)));
$chips = [];
if ($filter['stage'] !== '' && $filter['stage'] !== 'open') $chips[] = [LEAD_STAGES[$filter['stage']]['label'], $url(['stage' => '', 'page' => null])];
if ($filter['source'] !== '') $chips[] = [LEAD_SOURCES[$filter['source']], $url(['source' => '', 'page' => null])];
if ($filter['owner'] !== '') $chips[] = [$filter['owner'] === 'me' ? 'Mine' : ($filter['owner'] === 'none' ? 'Nobody\'s' : 'One person\'s'), $url(['owner' => '', 'page' => null])];
if ($filter['due'] !== '') $chips[] = ['Follow up by tomorrow', $url(['due' => '', 'page' => null])];
$noun = ['lead', 'leads'];
$when = function (?string $at, bool $overdue): string {
    if (!$at) return '<span class="muted">—</span>';
    $text = date('D j M, g:ia', strtotime($at));
    return $overdue ? '<span class="ops-chip ops-chip-bad">' . e($text) . '</span>' : e($text);
};
$card = function (array $l) use ($when) {
    ob_start(); ?>
    <li class="ops-lead-card">
        <a class="cell-main" href="<?= e(ops_url('leads/' . (int)$l['id'])) ?>"><?= e($l['business_name'] ?: $l['name']) ?></a>
        <span class="cell-sub"><?= e($l['business_name'] ? $l['name'] : '') ?><?= $l['phone'] ? ($l['business_name'] ? ' · ' : '') . e($l['phone']) : '' ?></span>
        <span class="cell-sub"><?= e(LEAD_SOURCES[$l['source']] ?? $l['source']) ?><?= $l['owner_name'] ? ' · ' . e($l['owner_name']) : '' ?></span>
        <?php if ($l['next_follow_up_at']): ?><span class="ops-lead-when"><?= $when($l['next_follow_up_at'], (bool)$l['overdue']) ?></span><?php endif; ?>
    </li>
    <?php return ob_get_clean();
};
?>
<div class="ops-head">
    <div>
        <h1>Leads</h1>
        <p>Everyone who might use Nfelia: messages from the home page, sign-ups that stalled, trials ending, and people the team reached out to.
           <?= $due_count ? '<b>' . $due_count . ' follow-up' . ($due_count === 1 ? '' : 's') . ' due now.</b>' : '' ?>
           <a href="<?= e(ops_url('leads/held')) ?>"><?= (int)($held_count ?? 0) ?> message<?= (int)($held_count ?? 0) === 1 ? '' : 's' ?> held as spam</a></p>
    </div>
    <div class="ops-head-actions">
        <button type="button" class="ops-btn ops-btn-primary" data-ops-dialog="add-lead"><?= ops_icon('plus') ?> Add a lead</button>
    </div>
</div>

<form class="ops-toolbar" method="get" action="<?= e(ops_url('leads')) ?>">
    <?php if ($mode === 'board'): ?><input type="hidden" name="view" value="board"><?php endif; ?>
    <div class="ops-search">
        <?= ops_icon('search') ?>
        <label class="sr-only" for="l-q">Search leads</label>
        <input class="ops-input" id="l-q" name="q" type="search" value="<?= e($filter['q']) ?>" placeholder="Search by name, business, phone or email" data-ops-slash>
    </div>
    <details class="ops-filters">
        <summary class="ops-btn ops-btn-secondary">Filters<?= $chips ? ' · ' . count($chips) : '' ?></summary>
        <div class="ops-filters-panel">
            <?php if ($mode !== 'board'): ?>
            <div class="ops-field"><label for="f-stage">Stage</label>
                <select class="ops-select" id="f-stage" name="stage">
                    <option value="open"<?= $filter['stage'] === 'open' ? ' selected' : '' ?>>Still open (New to Trial)</option>
                    <?php foreach (LEAD_STAGES as $k => $s): ?><option value="<?= e($k) ?>"<?= $filter['stage'] === $k ? ' selected' : '' ?>><?= e($s['label']) ?></option><?php endforeach; ?>
                </select></div>
            <?php endif; ?>
            <div class="ops-field"><label for="f-source">Came from</label>
                <select class="ops-select" id="f-source" name="source"><option value="">Anywhere</option>
                    <?php foreach (LEAD_SOURCES as $k => $label): ?><option value="<?= e($k) ?>"<?= $filter['source'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
            <div class="ops-field"><label for="f-owner">Whose</label>
                <select class="ops-select" id="f-owner" name="owner"><option value="">Anyone's</option>
                    <option value="me"<?= $filter['owner'] === 'me' ? ' selected' : '' ?>>Mine</option>
                    <option value="none"<?= $filter['owner'] === 'none' ? ' selected' : '' ?>>Nobody's yet</option>
                    <?php foreach ($sales as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $filter['owner'] === (string)$s['id'] ? ' selected' : '' ?>><?= e($s['full_name']) ?></option><?php endforeach; ?>
                </select></div>
            <label class="ops-check" style="margin-bottom:14px"><input type="checkbox" name="due" value="1"<?= $filter['due'] ? ' checked' : '' ?>><span>Follow up by tomorrow</span></label>
            <button class="ops-btn ops-btn-primary ops-btn-block" type="submit">Show</button>
        </div>
    </details>
    <div class="ops-toggle" role="group" aria-label="Show as">
        <a href="<?= e($url(['view' => '', 'page' => null])) ?>"<?= $mode === 'list' ? ' aria-current="page"' : '' ?>>List</a>
        <a href="<?= e($url(['view' => 'board', 'page' => null, 'stage' => ''])) ?>"<?= $mode === 'board' ? ' aria-current="page"' : '' ?>>Board</a>
    </div>
</form>
<?php if ($chips): ?>
    <div class="ops-chips">
        <?php foreach ($chips as [$label, $remove]): ?><a class="ops-chip ops-chip-accent" href="<?= e($remove) ?>"><?= e($label) ?> <span aria-label="Remove this filter">×</span></a><?php endforeach; ?>
        <a class="small" href="<?= e(ops_url('leads' . ($mode === 'board' ? '?view=board' : ''))) ?>">Clear all</a>
    </div>
<?php endif; ?>

<?php if ($mode === 'board'): ?>
    <div class="ops-board">
        <?php foreach (LEAD_STAGES as $stage => $s): ?>
            <section class="ops-board-col" aria-label="<?= e($s['label']) ?>">
                <h2><?= e($s['label']) ?> <span class="muted num"><?= count($rows[$stage]) ?></span></h2>
                <p class="small muted"><?= e($s['hint']) ?></p>
                <ul>
                    <?php foreach (array_slice($rows[$stage], 0, 60) as $l): ?><?= $card($l) ?><?php endforeach; ?>
                    <?php if (!$rows[$stage]): ?><li class="ops-lead-empty">Nobody</li><?php endif; ?>
                </ul>
                <?php if (count($rows[$stage]) > 60): ?><a class="small" href="<?= e($url(['view' => '', 'stage' => $stage])) ?>">All <?= count($rows[$stage]) ?> in the list</a><?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="ops-table-wrap">
        <table class="ops-table is-stacked is-compact">
            <thead><tr><th scope="col">Lead</th><th scope="col">Stage</th><th scope="col">Follow up</th><th scope="col">Whose</th><th scope="col">Came from</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $l): ?>
                <tr class="is-link">
                    <td><a class="cell-main" href="<?= e(ops_url('leads/' . (int)$l['id'])) ?>"><?= e($l['business_name'] ?: $l['name']) ?></a>
                        <span class="cell-sub"><?= e(trim(($l['business_name'] ? $l['name'] . ' · ' : '') . ($l['phone'] ?? '') . ($l['phone'] && $l['email'] ? ' · ' : '') . ($l['email'] ?? ''))) ?></span></td>
                    <td data-label="Stage"><?= ops_lead_stage_chip($l['stage']) ?></td>
                    <td data-label="Follow up"><?= $when($l['next_follow_up_at'], (bool)$l['overdue']) ?><?php if ($l['follow_up_note']): ?><span class="cell-sub"><?= e($l['follow_up_note']) ?></span><?php endif; ?></td>
                    <td data-label="Whose"><?= $l['owner_name'] ? e($l['owner_name']) : '<span class="muted">Nobody yet</span>' ?></td>
                    <td data-label="Came from"><?= e(LEAD_SOURCES[$l['source']] ?? $l['source']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="5"><div class="ops-empty"><h3><?= $filter['q'] !== '' || $chips ? 'Nobody matches' : 'No open leads' ?></h3>
                    <p><?= $filter['q'] !== '' || $chips ? 'Try other words, or clear the filters.' : 'Messages from the home page, stalled sign-ups and trials ending arrive here by themselves. Add the people you reach out to.' ?></p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php require __DIR__ . '/../_pager.php'; ?>
    </div>
<?php endif; ?>

<dialog class="ops-dialog" id="add-lead" aria-labelledby="add-lead-title">
    <form method="post" action="<?= e(ops_url('leads')) ?>">
        <?= ops_csrf_field() ?>
        <div class="ops-dialog-head"><h2 id="add-lead-title">Add a lead</h2><button type="button" data-ops-close aria-label="Close"><?= ops_icon('close') ?></button></div>
        <div class="ops-dialog-body">
            <div class="ops-row">
                <div class="ops-field"><label for="nl-name">Their name</label><input class="ops-input" id="nl-name" name="name" required autocomplete="off"></div>
                <div class="ops-field"><label for="nl-biz">Business <span class="muted">(optional)</span></label><input class="ops-input" id="nl-biz" name="business_name" autocomplete="off"></div>
            </div>
            <div class="ops-row">
                <div class="ops-field"><label for="nl-phone">Phone or WhatsApp</label><input class="ops-input" id="nl-phone" name="phone" type="tel" inputmode="tel" autocomplete="off"></div>
                <div class="ops-field"><label for="nl-email">Email <span class="muted">(optional)</span></label><input class="ops-input" id="nl-email" name="email" type="email" autocomplete="off"></div>
            </div>
            <div class="ops-row">
                <div class="ops-field"><label for="nl-source">How you met</label>
                    <select class="ops-select" id="nl-source" name="source"><?php foreach (LEAD_SOURCES as $k => $label): if (in_array($k, ['website', 'signup', 'trial', 'booking'], true)) continue; ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="ops-field"><label for="nl-stage">Where it is</label>
                    <select class="ops-select" id="nl-stage" name="stage"><?php foreach (['new', 'contacted', 'demo', 'trial', 'lost'] as $k): ?><option value="<?= $k ?>"<?= $k === 'contacted' ? ' selected' : '' ?>><?= e(LEAD_STAGES[$k]['label']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="ops-row">
                <div class="ops-field"><label for="nl-fu">Follow up <span class="muted">(optional)</span></label><input class="ops-input" id="nl-fu" name="follow_up" type="datetime-local"></div>
                <div class="ops-field"><label for="nl-fun">About</label><input class="ops-input" id="nl-fun" name="follow_up_note" placeholder="e.g. send the price list"></div>
            </div>
            <div class="ops-field"><label for="nl-note">Note <span class="muted">(optional)</span></label><textarea class="ops-textarea" id="nl-note" name="note" placeholder="What they sell, what they asked, where you met"></textarea></div>
        </div>
        <div class="ops-dialog-foot"><button type="button" class="ops-btn ops-btn-secondary" data-ops-close>Cancel</button><button type="submit" class="ops-btn ops-btn-primary">Add the lead</button></div>
    </form>
</dialog>
