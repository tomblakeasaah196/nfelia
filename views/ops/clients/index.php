<?php
/** Clients (list): $rows, $total, $page, $pages, $filter, $plans, $countries. */
$url = fn(array $over) => ops_url('clients?' . http_build_query(array_filter($over + $filter, fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== 'new')));
$chips = [];
if ($filter['plan'] !== '') $chips[] = [$filter['plan'] === 'attention' ? 'Needs attention' : ($plans[$filter['plan']] ?? $filter['plan']), $url(['plan' => '', 'page' => null])];
if ($filter['country']) {
    $c = array_values(array_filter($countries, fn($c) => (int)$c['id'] === $filter['country']))[0] ?? null;
    $chips[] = [$c ? $c['name'] : 'Country', $url(['country' => 0, 'page' => null])];
}
$noun = ['client', 'clients'];
?>
<div class="ops-head">
    <div>
        <h1>Clients</h1>
        <p>Every business on Nfelia: its plan, its people, and how it uses Nfelia. Open one for the whole picture.</p>
    </div>
</div>

<form class="ops-toolbar" method="get" action="<?= e(ops_url('clients')) ?>">
    <div class="ops-search">
        <?= ops_icon('search') ?>
        <label class="sr-only" for="c-q">Search clients</label>
        <input class="ops-input" id="c-q" name="q" type="search" value="<?= e($filter['q']) ?>" placeholder="Search by business, person, email, phone or shop" data-ops-slash>
    </div>
    <details class="ops-filters">
        <summary class="ops-btn ops-btn-secondary">Filters<?= $chips ? ' · ' . count($chips) : '' ?></summary>
        <div class="ops-filters-panel">
            <div class="ops-field">
                <label for="f-plan">Plan</label>
                <select class="ops-select" id="f-plan" name="plan">
                    <option value="">Any plan</option>
                    <option value="attention"<?= $filter['plan'] === 'attention' ? ' selected' : '' ?>>Needs attention (due, paused, being checked, trial ending)</option>
                    <?php foreach ($plans as $k => $label): ?><option value="<?= e($k) ?>"<?= $filter['plan'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="ops-field">
                <label for="f-country">Country</label>
                <select class="ops-select" id="f-country" name="country">
                    <option value="">Any country</option>
                    <?php foreach ($countries as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $filter['country'] === (int)$c['id'] ? ' selected' : '' ?>><?= e(trim(($c['flag_emoji'] ?? '') . ' ' . $c['name'])) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="ops-btn ops-btn-primary ops-btn-block" type="submit">Show</button>
        </div>
    </details>
    <label class="sr-only" for="f-sort">Order</label>
    <select class="ops-select ops-select-auto" id="f-sort" name="sort" data-ops-autosubmit>
        <option value="new"<?= $filter['sort'] === 'new' ? ' selected' : '' ?>>Newest first</option>
        <option value="active"<?= $filter['sort'] === 'active' ? ' selected' : '' ?>>Last active</option>
        <option value="name"<?= $filter['sort'] === 'name' ? ' selected' : '' ?>>By name</option>
    </select>
</form>
<?php if ($chips): ?>
    <div class="ops-chips">
        <?php foreach ($chips as [$label, $remove]): ?><a class="ops-chip ops-chip-accent" href="<?= e($remove) ?>"><?= e($label) ?> <span aria-label="Remove this filter">×</span></a><?php endforeach; ?>
        <a class="small" href="<?= e(ops_url('clients')) ?>">Clear all</a>
    </div>
<?php endif; ?>

<div class="ops-table-wrap">
    <table class="ops-table is-stacked is-compact">
        <thead><tr><th scope="col">Business</th><th scope="col">Plan</th><th scope="col" class="num">Team</th><th scope="col">Last active</th><th scope="col">Joined</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $b): ?>
            <tr class="is-link">
                <td>
                    <a class="cell-main" href="<?= e(ops_url('clients/' . (int)$b['id'])) ?>"><?= e($b['name']) ?></a>
                    <span class="cell-sub"><?= e(trim(($b['owner'] ? $b['owner'] . ' · ' : '') . ($b['email'] ?? ''))) ?><?= $b['subdomain'] ? ' · ' . e($b['subdomain'] . '.' . shop_base_host()) : '' ?><?= $b['country'] ? ' · ' . e(trim(($b['flag_emoji'] ?? '') . ' ' . $b['country'])) : '' ?></span>
                </td>
                <td data-label="Plan"><?= ops_plan_chip($b) ?>
                    <?php if ($b['plan_status'] === 'trial' && $b['trial_ends_at']): ?><span class="cell-sub">ends <?= e(date('j M', strtotime($b['trial_ends_at']))) ?></span>
                    <?php elseif ($b['current_period_end'] && in_array($b['plan_status'], ['active', 'past_due', 'cancelled'], true)): ?><span class="cell-sub">to <?= e(date('j M', strtotime($b['current_period_end']))) ?></span><?php endif; ?>
                </td>
                <td data-label="Team" class="num"><?= (int)$b['team'] ?></td>
                <td data-label="Last active"><?= $b['last_login'] ? e(ops_ago($b['last_login'])) : '<span class="muted">Never</span>' ?></td>
                <td data-label="Joined"><?= e(date('j M Y', strtotime($b['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="5"><div class="ops-empty"><h3>No clients match</h3><p>Try other words, or <a href="<?= e(ops_url('clients')) ?>">clear the filters</a>.</p></div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php require __DIR__ . '/../_pager.php'; ?>
</div>
